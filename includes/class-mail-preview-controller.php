<?php
/**
 * Provides an admin-only mail rendering preview.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Preview_Controller {

    private const FIELD_TYPES = [ 'name', 'email', 'phone', 'address', 'text', 'select', 'textarea', 'consent' ];

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route(
            'jp-form-builder/v1',
            '/mail-preview',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'preview' ],
                'permission_callback' => [ $this, 'permission' ],
                'args'                => [
                    'kind'    => [
                        'type'     => 'string',
                        'enum'     => [ 'notification', 'confirmation' ],
                        'required' => true,
                    ],
                    'subject' => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'body'    => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    // No default on purpose: its presence marks a draft layout preview.
                    'layout'  => [ 'type' => 'string' ],
                    // Entries are validated one by one in sample_fields(): an invalid entry is dropped, not rejected.
                    'fields'  => [ 'type' => 'array' ],
                ],
            ]
        );
    }

    public function permission( \WP_REST_Request $request ): bool {
        return current_user_can( $request->has_param( 'layout' ) ? 'manage_options' : Form_Post_Type::capability() );
    }

    /** @return \WP_REST_Response|\WP_Error */
    public function preview( \WP_REST_Request $request ) {
        $layout = new Mail_Layout();
        if ( $request->has_param( 'layout' ) ) {
            $layout = $layout->with_draft( (string) $request->get_param( 'layout' ) );
        }
        $builder    = new Mail_Builder( null, $layout );
        $subject    = (string) $request->get_param( 'subject' );
        $body       = (string) $request->get_param( 'body' );
        $recipients = [ (string) get_option( 'admin_email' ) ];
        $context    = [
            'form_title'   => __( 'Sample form', 'jp-form-builder' ),
            'site_name'    => get_bloginfo( 'name' ),
            'page_url'     => home_url( '/' ),
            'submitted_at' => gmdate( 'Y-m-d H:i' ) . ' UTC',
        ];
        $fields     = $request->has_param( 'fields' )
            ? $this->sample_fields_for( (array) $request->get_param( 'fields' ), $context['submitted_at'] )
            : $this->sample_fields();
        try {
            $mail = 'confirmation' === $request->get_param( 'kind' )
                ? $builder->confirmation(
                    'jane@example.test',
                    $recipients,
                    [
                        'confirmationSubject' => $subject,
                        'confirmationBody'    => $body,
                    ],
                    $fields,
                    $context
                )
                : $builder->notification(
                    $recipients,
                    [
                        'subject'          => $subject,
                        'notificationBody' => $body,
                    ],
                    $fields,
                    $context
                );
        } catch ( \InvalidArgumentException $exception ) {
            return new \WP_Error( 'jpfb_invalid_mail_layout', $exception->getMessage(), [ 'status' => 400 ] );
        }
        return rest_ensure_response(
            [
                'subject' => $mail->subject(),
                'html'    => $mail->html_body(),
            ]
        );
    }

    /**
     * Sample Fields for the Form's own Fields, so the preview shows its labels and placeholders.
     *
     * @param array<int,mixed> $entries Requested `{key, label, type}` entries; invalid ones are dropped.
     * @return Submitted_Field[]
     */
    private function sample_fields_for( array $entries, string $submitted_at ): array {
        $fields   = [];
        $reply_to = false;
        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) || ! is_string( $entry['key'] ?? null ) || ! is_string( $entry['type'] ?? null ) ) {
                continue;
            }
            $key  = trim( (string) preg_replace( '/[^a-z0-9_]/', '_', strtolower( $entry['key'] ) ), '_' );
            $type = $entry['type'];
            if ( '' === $key || isset( $fields[ $key ] ) || ! in_array( $type, self::FIELD_TYPES, true ) ) {
                continue;
            }
            $label = is_string( $entry['label'] ?? null ) ? sanitize_text_field( $entry['label'] ) : '';
            $is_reply_to = 'email' === $type && ! $reply_to;
            $reply_to    = $reply_to || $is_reply_to;
            $fields[ $key ] = new Submitted_Field( $key, '' !== $label ? $label : $key, $type, $this->sample_value( $type, ! empty( $entry['split'] ), $submitted_at ), $is_reply_to );
        }
        return array_values( $fields );
    }

    /** @return string|string[] */
    private function sample_value( string $type, bool $split_name, string $submitted_at ) {
        switch ( $type ) {
            case 'name':
                return $split_name ? [
                    'first' => 'Jane',
                    'last'  => 'Example',
                ] : 'Jane Example';
            case 'email':
                return 'jane@example.test';
            case 'phone':
                return '+49821123456';
            case 'address':
                return [
                    'street'   => __( 'Example Street 1', 'jp-form-builder' ),
                    'postcode' => '86150',
                    'city'     => __( 'Sampletown', 'jp-form-builder' ),
                    'country'  => Countries::all()['DE'] ?? 'DE',
                ];
            case 'select':
                return __( 'Sample option', 'jp-form-builder' );
            case 'textarea':
                return "Hello,\nthis is a sample message.";
            case 'consent':
                /* translators: %s: UTC timestamp of the Submission. */
                return sprintf( __( 'Yes (%s)', 'jp-form-builder' ), $submitted_at );
            default:
                return __( 'Sample text', 'jp-form-builder' );
        }
    }

    /** @return Submitted_Field[] */
    private function sample_fields(): array {
        return [
            new Submitted_Field( 'name', __( 'Name', 'jp-form-builder' ), 'name', 'Jane Example' ),
            new Submitted_Field( 'email', __( 'Email', 'jp-form-builder' ), 'email', 'jane@example.test', true ),
            new Submitted_Field( 'message', __( 'Message', 'jp-form-builder' ), 'textarea', "Hello,\nthis is a sample message." ),
        ];
    }
}
