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
            'submitted_at' => gmdate( 'Y-m-d H:i' ) . ' UTC',
        ];
        try {
            $mail = 'confirmation' === $request->get_param( 'kind' )
                ? $builder->confirmation(
                    'jane@example.test',
                    $recipients,
                    [
                        'confirmationSubject' => $subject,
                        'confirmationBody'    => $body,
                    ],
                    $this->sample_fields(),
                    $context
                )
                : $builder->notification(
                    $recipients,
                    [
                        'subject'          => $subject,
                        'notificationBody' => $body,
                    ],
                    $this->sample_fields(),
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

    /** @return Submitted_Field[] */
    private function sample_fields(): array {
        return [
            new Submitted_Field( 'name', __( 'Name', 'jp-form-builder' ), 'name', 'Jane Example' ),
            new Submitted_Field( 'email', __( 'Email', 'jp-form-builder' ), 'email', 'jane@example.test', true ),
            new Submitted_Field( 'message', __( 'Message', 'jp-form-builder' ), 'textarea', "Hello,\nthis is a sample message." ),
        ];
    }
}
