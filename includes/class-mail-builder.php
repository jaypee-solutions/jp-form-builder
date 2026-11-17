<?php
/**
 * Builds Notification and Confirmation mails from a Submission.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Builder {

    private const MAX_SUBJECT_VALUE_LENGTH = 200;
    private const MAX_SUBJECT_LENGTH       = 250;

    private Mail_Template $template;
    private Mail_Layout $layout;
    private Mail_Header_Guard $header_guard;
    private Settings $settings;

    public function __construct( ?Mail_Template $template = null, ?Mail_Layout $layout = null, ?Mail_Header_Guard $header_guard = null, ?Settings $settings = null ) {
        $this->settings     = $settings ?? new Settings();
        $this->template     = $template ?? new Mail_Template();
        $this->layout       = $layout ?? new Mail_Layout( $this->settings );
        $this->header_guard = $header_guard ?? new Mail_Header_Guard();
    }

    /**
     * @param string[] $recipients
     * @param Submitted_Field[] $fields
     * @param array<string,mixed> $form Form-level subject/body and confirmation settings.
     * @param array<string,string> $context Values including form_title, site_name, page_url, submitted_at.
     */
    public function notification( array $recipients, array $form, array $fields, array $context ): Mail {
        $subject_template = $this->choose( $form['subject'] ?? '', $this->settings->get( 'notification_subject' ), __( 'New message via {form_title}', 'jp-form-builder' ) );
        $context          = $this->validated_context( $context );
        $built_in_body    = '' === ( $context['page_url'] ?? '' )
            ? '{all_fields}'
            /* translators: %s: placeholder for the address of the page that held the Form. */
            : '<div>{all_fields}</div><p>' . sprintf( __( 'Page: %s', 'jp-form-builder' ), '{page_url}' ) . '</p>';
        $body_template    = $this->choose( $form['notificationBody'] ?? '', $this->settings->get( 'notification_body' ), $built_in_body );
        $subject = $this->render_subject( $subject_template, $fields, $context );
        $html    = $this->template->render( $body_template, $fields, $context, 'html' );
        $text    = $this->template->render( $body_template, $fields, $context, 'text' );
        $headers          = [];
        foreach ( $fields as $field ) {
            if ( $field instanceof Submitted_Field && 'email' === $field->type() && $field->is_reply_to() ) {
                $name = '';
                foreach ( $fields as $candidate ) {
                    if ( $candidate instanceof Submitted_Field && 'name' === $candidate->type() ) {
                        $name = $candidate->as_text();
                        break;
                    }
                }
                $header = $this->header_guard->reply_to( $name, $field->as_text() );
                if ( null !== $header ) {
                    $headers[] = $header;
                }
                break;
            }
        }
        return new Mail( $recipients, $subject, $this->layout->wrap( $html, $context ), $text, $headers );
    }

    /**
     * @param string[] $recipients
     * @param Submitted_Field[] $fields
     * @param array<string,mixed> $form
     * @param array<string,string> $context
     */
    public function confirmation( string $submitter, array $recipients, array $form, array $fields, array $context ): Mail {
        $subject_template = $this->choose( $form['confirmationSubject'] ?? '', $this->settings->get( 'confirmation_subject' ), __( 'Thank you for contacting us', 'jp-form-builder' ) );
        $body_template    = $this->choose( $form['confirmationBody'] ?? '', $this->settings->get( 'confirmation_body' ), __( 'Thank you for your message. We will get back to you soon.', 'jp-form-builder' ) );
        $safe_context     = array_intersect_key( $context, array_flip( [ 'form_title', 'site_name', 'submitted_at' ] ) );
        $subject = $this->render_subject( $subject_template, [], $safe_context );
        $html    = $this->template->render( $body_template, [], $safe_context, 'html' );
        $text    = $this->template->render( $body_template, [], $safe_context, 'text' );
        $headers          = [];
        if ( isset( $recipients[0] ) ) {
            $reply_to = $this->header_guard->reply_to( '', $recipients[0] );
            if ( null !== $reply_to ) {
                $headers[] = $reply_to;
            }
        }
        return new Mail( [ $submitter ], $subject, $this->layout->wrap( $html, $safe_context ), $text, $headers );
    }

    /** @param array<string,string> $context @return array<string,string> */
    private function validated_context( array $context ): array {
        if ( isset( $context['page_url'] ) ) {
            $page_host = wp_parse_url( $context['page_url'], PHP_URL_HOST );
            $home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
            if ( ! is_string( $page_host ) || ! is_string( $home_host ) || strtolower( $page_host ) !== strtolower( $home_host ) ) {
                $context['page_url'] = '';
            }
        }
        return $context;
    }

    private function choose( $form, $site, string $built_in ): string {
        return '' !== trim( (string) $form ) ? (string) $form : ( '' !== trim( (string) $site ) ? (string) $site : $built_in );
    }

    /** @param Submitted_Field[] $fields @param array<string,string> $context */
    private function render_subject( string $template, array $fields, array $context ): string {
        $values = [];
        foreach ( $fields as $field ) {
            if ( $field instanceof Submitted_Field ) {
                $values[ $field->key() ] = $field->as_text();
            }
        }
        $values = array_merge( $context, $values );
        $subject = preg_replace_callback(
            '/\{([A-Za-z0-9_-]+)\}/',
            static function ( array $placeholder ) use ( $values ): string {
                return isset( $values[ $placeholder[1] ] ) ? Text::truncate( trim( (string) preg_replace( '/[\r\n\x00]+/', ' ', (string) $values[ $placeholder[1] ] ) ), self::MAX_SUBJECT_VALUE_LENGTH ) : '';
            },
            wp_strip_all_tags( $template )
        );
        return Text::truncate( trim( (string) $subject ), self::MAX_SUBJECT_LENGTH );
    }
}
