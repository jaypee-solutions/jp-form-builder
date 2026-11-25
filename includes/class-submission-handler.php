<?php
/**
 * Runs a Submission through the spam pipeline and sends its mails.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Submission_Handler {

    /** Seconds between two Confirmations to the same address. */
    private const CONFIRMATION_WINDOW = 3600;

    private Mailer $mailer;
    private Settings $settings;
    private Form_Repository $repository;
    private Field_Validator $validator;
    private Form_Token $token;
    private Turnstile_Verifier $turnstile;
    private Mail_Builder $mail_builder;

    public function __construct(
        Mailer $mailer,
        ?Settings $settings = null,
        ?Form_Repository $repository = null,
        ?Field_Validator $validator = null,
        ?Form_Token $token = null,
        ?Turnstile_Verifier $turnstile = null,
        ?Mail_Builder $mail_builder = null
    ) {
        $this->mailer       = $mailer;
        $this->settings     = $settings ?? new Settings();
        $this->repository   = $repository ?? new Form_Repository();
        $this->validator    = $validator ?? new Field_Validator();
        $this->token        = $token ?? new Form_Token();
        $this->turnstile    = $turnstile ?? new Turnstile_Verifier();
        $this->mail_builder = $mail_builder ?? new Mail_Builder( null, null, null, $this->settings );
    }

    /**
     * @param int                 $form_id    Form ID from the route.
     * @param array<string,mixed> $payload    Decoded JSON body.
     * @param int                 $body_bytes Size of the raw request body.
     * @param string              $client_ip  Resolved client IP; '' when unknown.
     * @return array{message: string}|\WP_Error
     */
    public function handle( int $form_id, array $payload, int $body_bytes, string $client_ip ) {
        if ( $body_bytes > (int) $this->settings->tunable( 'max_payload_bytes' ) ) {
            return $this->error( 'jpfb_payload_too_large', __( 'The request is too large.', 'jp-form-builder' ), 413 );
        }

        $form = $this->repository->find( $form_id );
        if ( null === $form ) {
            return $this->error( 'jpfb_form_not_found', __( 'This form does not exist.', 'jp-form-builder' ), 404 );
        }

        $switched = $this->switch_locale( $this->string_param( $payload, 'locale' ) );
        try {
            return $this->process( $form, $payload, $client_ip );
        } finally {
            if ( $switched ) {
                restore_previous_locale();
            }
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{message: string}|\WP_Error
     */
    private function process( Form_Definition $form, array $payload, string $client_ip ) {
        $limiter = new Rate_Limiter( wp_salt( 'auth' ) );
        if ( ! $limiter->attempt( 'rl', $client_ip, (int) $this->settings->tunable( 'rate_limit' ), (int) $this->settings->tunable( 'rate_window' ) ) ) {
            return $this->error( 'jpfb_rate_limited', __( 'Too many attempts. Please try again later.', 'jp-form-builder' ), 429 );
        }

        $success = [ 'message' => $this->success_message( $form ) ];
        if ( $this->is_filled( $payload['hp'] ?? '' ) ) {
            return $success;
        }

        if ( ! $this->token->verify( $this->string_param( $payload, 'token' ), $form->id, time(), $this->settings->min_fill_seconds(), Form_Token::MAX_AGE, wp_salt( 'nonce' ) ) ) {
            return $this->error( 'jpfb_token_invalid', __( 'Your session could not be verified. Please reload the page and try again.', 'jp-form-builder' ), 400 );
        }

        $turnstile_error = $this->check_turnstile( $this->string_param( $payload, 'turnstile' ) );
        if ( null !== $turnstile_error ) {
            return $turnstile_error;
        }

        $definition = $this->repository->find_by_rev( $form->id, $this->string_param( $payload, 'rev' ) );
        if ( null === $definition ) {
            return $this->error( 'jpfb_form_changed', __( 'This form was updated. Please reload the page.', 'jp-form-builder' ), 409 );
        }

        $submitted = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : [];
        $result    = $this->validator->validate_form( $definition, $submitted );
        if ( [] !== $result['errors'] ) {
            return $this->error(
                'jpfb_invalid_fields',
                __( 'Please correct the marked fields.', 'jp-form-builder' ),
                422,
                [ 'errors' => $result['errors'] ]
            );
        }

        $submitted_at = gmdate( 'Y-m-d H:i' ) . ' UTC';
        $fields       = $this->submitted_fields( $definition, $result['values'], $submitted_at );
        $context      = [
            'form_title'   => $form->title,
            'site_name'    => get_bloginfo( 'name' ),
            'page_url'     => esc_url_raw( Text::single_line( $this->string_param( $payload, 'page_url' ) ) ),
            'submitted_at' => $submitted_at,
        ];
        $recipients   = $this->recipients( $form );

        $notification = $this->mail_builder->notification( $recipients, $form->attributes, $fields, $context );
        if ( is_wp_error( $this->mailer->send( $notification, $form->id ) ) ) {
            return $this->error( 'jpfb_mail_failed', __( 'Your message could not be sent. Please try again later or contact us another way.', 'jp-form-builder' ), 500 );
        }

        $this->send_confirmation( $form, $fields, $recipients, $context );
        return $success;
    }

    /** @return \WP_Error|null Null when Turnstile is not configured or the visitor passed. */
    private function check_turnstile( string $response ): ?\WP_Error {
        if ( ! $this->settings->turnstile_configured() ) {
            return null;
        }

        $result = $this->turnstile->verify( $response, (string) $this->settings->get( 'turnstile_secret_key' ) );
        if ( $result->is_unavailable() ) {
            return $this->error( 'jpfb_turnstile_unavailable', __( 'The spam protection check is currently unavailable. Please try again later.', 'jp-form-builder' ), 503 );
        }
        if ( ! $result->is_passed() ) {
            return $this->error( 'jpfb_turnstile_failed', __( 'The spam protection check failed. Please try again.', 'jp-form-builder' ), 400 );
        }
        return null;
    }

    /**
     * Sends the Confirmation when ADR 0011 allows it; a failure is only logged by the Mailer.
     *
     * @param Submitted_Field[]   $fields
     * @param string[]            $recipients
     * @param array<string,string> $context
     */
    private function send_confirmation( Form_Definition $form, array $fields, array $recipients, array $context ): void {
        if ( empty( $form->attributes['confirmationEnabled'] ) || ! $this->settings->turnstile_configured() ) {
            return;
        }

        $submitter = '';
        foreach ( $fields as $field ) {
            if ( $field->is_reply_to() && is_string( $field->value() ) ) {
                $submitter = $field->value();
            }
        }
        if ( '' === $submitter || ! is_email( $submitter ) ) {
            return;
        }

        $limiter = new Rate_Limiter( wp_salt( 'auth' ) );
        if ( ! $limiter->attempt( 'cf', strtolower( $submitter ), 1, self::CONFIRMATION_WINDOW ) ) {
            return;
        }

        $this->mailer->send( $this->mail_builder->confirmation( $submitter, $recipients, $form->attributes, [], $context ), $form->id );
    }

    /**
     * Fields with a value, labelled as the Form shows them, for the mail.
     *
     * @param array<string,string|array<string,string>> $values Cleaned values of valid Fields.
     * @return Submitted_Field[]
     */
    private function submitted_fields( Form_Definition $definition, array $values, string $submitted_at ): array {
        $fields      = [];
        $has_reply_to = false;
        foreach ( $definition->fields() as $field ) {
            $value = $this->mail_value( $field, $values[ $field->key ] ?? '', $submitted_at );
            if ( '' === $value ) {
                continue;
            }
            $is_reply_to  = ! $has_reply_to && 'email' === $field->type && ! empty( $field->attributes['replyTo'] );
            $has_reply_to = $has_reply_to || $is_reply_to;
            $fields[]     = new Submitted_Field( $field->key, Field_Labels::for_field( $field ), $field->type, $value, $is_reply_to );
        }
        return $fields;
    }

    /**
     * Human-readable value; '' for an empty Field.
     *
     * @param string|array<int|string,string> $value Cleaned value.
     */
    private function mail_value( Field_Definition $field, $value, string $submitted_at ): string {
        if ( 'checkbox' === $field->type && is_array( $value ) ) {
            return implode( ', ', array_map( fn( string $chosen ): string => $this->option_label( $field, $chosen ), $value ) );
        }
        if ( is_array( $value ) ) {
            if ( 'address' !== $field->type ) {
                return trim( implode( ' ', $value ) );
            }
            if ( '' === implode( '', array_diff_key( $value, [ 'country' => true ] ) ) ) {
                return '';
            }
            $countries = Countries::all();
            return $value['street'] . "\n" . $value['postcode'] . ' ' . $value['city'] . "\n" . ( $countries[ $value['country'] ] ?? $value['country'] );
        }
        if ( '' === $value ) {
            return '';
        }
        if ( 'consent' === $field->type ) {
            /* translators: %s: UTC timestamp of the Submission. */
            return sprintf( __( 'Yes (%s)', 'jp-form-builder' ), $submitted_at );
        }
        if ( 'date' === $field->type ) {
            return Date_Format::for_locale( determine_locale() )->format( $value );
        }
        return in_array( $field->type, [ 'select', 'radio' ], true ) ? $this->option_label( $field, $value ) : $value;
    }

    /** The label of the option with this value; the value itself when the option has no label. */
    private function option_label( Field_Definition $field, string $value ): string {
        foreach ( (array) ( $field->attributes['options'] ?? [] ) as $option ) {
            if ( is_array( $option ) && ( $option['value'] ?? null ) === $value && '' !== trim( (string) ( $option['label'] ?? '' ) ) ) {
                return (string) $option['label'];
            }
        }
        return $value;
    }

    /** @return string[] The Form's valid Recipients, else the default recipient setting, else the site admin. */
    private function recipients( Form_Definition $form ): array {
        $lists = [
            (string) ( $form->attributes['recipients'] ?? '' ),
            (string) $this->settings->get( 'default_recipient' ),
            (string) get_option( 'admin_email' ),
        ];
        foreach ( $lists as $list ) {
            $valid = Recipient_List::parse( $list );
            if ( [] !== $valid ) {
                return $valid;
            }
        }
        return [];
    }

    private function success_message( Form_Definition $form ): string {
        $message = trim( (string) ( $form->attributes['successMessage'] ?? '' ) );
        return '' !== $message ? $message : __( 'Thank you! Your message has been sent.', 'jp-form-builder' );
    }

    /** Switches to the visitor's page locale when it is installed (ADR 0013); true when the caller must restore. */
    private function switch_locale( string $locale ): bool {
        if ( '' === $locale || determine_locale() === $locale ) {
            return false;
        }
        if ( 'en_US' !== $locale && ! in_array( $locale, get_available_languages(), true ) ) {
            return false;
        }
        return switch_to_locale( $locale );
    }

    /** @param mixed $value */
    private function is_filled( $value ): bool {
        return is_array( $value ) ? [] !== $value : '' !== trim( (string) $value );
    }

    /** @param array<string,mixed> $payload */
    private function string_param( array $payload, string $name ): string {
        return isset( $payload[ $name ] ) && is_string( $payload[ $name ] ) ? $payload[ $name ] : '';
    }

    /** @param array<string,mixed> $data */
    private function error( string $code, string $message, int $status, array $data = [] ): \WP_Error {
        return new \WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
    }
}
