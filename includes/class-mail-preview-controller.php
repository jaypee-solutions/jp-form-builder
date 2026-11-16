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
        register_rest_route( 'jp-form-builder/v1', '/mail-preview', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'preview' ],
            'permission_callback' => [ $this, 'permission' ],
            'args'                => [
                'kind'        => [ 'type' => 'string', 'enum' => [ 'notification', 'confirmation' ], 'required' => true ],
                'subject'     => [ 'type' => 'string', 'default' => '' ],
                'body'        => [ 'type' => 'string', 'default' => '' ],
                'layout'      => [ 'type' => 'boolean', 'default' => false ],
                'layout_html' => [ 'type' => 'string', 'default' => '' ],
            ],
        ] );
    }

    public function permission( \WP_REST_Request $request ) {
        $capability = $request->get_param( 'layout' ) ? 'manage_options' : apply_filters( 'jp_form_builder_capability', 'manage_options' );
        return current_user_can( $capability );
    }

    public function preview( \WP_REST_Request $request ) {
        $subject = (string) $request->get_param( 'subject' );
        $body    = (string) $request->get_param( 'body' );
        $sample  = str_replace( [ '{form_title}', '{site_name}', '{all_fields}' ], [ esc_html__( 'Sample form', 'jp-form-builder' ), esc_html( get_bloginfo( 'name' ) ), '<p><strong>Name:</strong> Jane Example</p><p><strong>Email:</strong> jane@example.test</p>' ], $body );
        $sample  = ( new Mail_Template() )->render( $sample, [], [ 'form_title' => __( 'Sample form', 'jp-form-builder' ), 'site_name' => get_bloginfo( 'name' ) ] );
        try {
            $layout_html = (string) $request->get_param( 'layout_html' );
            $html = ( new Mail_Layout() )->wrap( $sample, [ 'form_title' => __( 'Sample form', 'jp-form-builder' ) ], '' === $layout_html ? null : $layout_html );
        } catch ( \InvalidArgumentException $exception ) {
            return new \WP_Error( 'jpfb_invalid_mail_layout', $exception->getMessage(), [ 'status' => 400 ] );
        }
        return rest_ensure_response( [ 'subject' => sanitize_text_field( $subject ), 'html' => $html ] );
    }
}
