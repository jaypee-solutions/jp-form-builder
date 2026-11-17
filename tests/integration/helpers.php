<?php
/**
 * Helpers that drive the plugin the way WordPress does: admin-post actions and REST requests.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

trait Plugin_Test_Helpers {

    /**
     * Runs an admin-post action as the current user and returns where it redirects.
     *
     * @param array<string,mixed> $post Fields posted next to the nonce.
     */
    private function run_admin_post( string $action, array $post = [] ): string {
        $_POST    = array_merge( [ 'action' => $action, '_wpnonce' => wp_create_nonce( $action ) ], $post );
        $_REQUEST = $_POST;
        $redirect = static function ( $location ) {
            throw new Settings_Page_Denied( (string) $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( 'admin_post_' . $action );
        } catch ( Settings_Page_Denied $exception ) {
            return $exception->getMessage();
        } finally {
            remove_filter( 'wp_redirect', $redirect );
            $_POST    = [];
            $_REQUEST = [];
        }
        $this->fail( 'The ' . $action . ' action did not redirect.' );
    }

    /**
     * Saves settings through the settings page, as an administrator, and returns where it redirects.
     *
     * @param array<string,mixed> $values
     */
    private function save_settings( array $values ): string {
        $previous = get_current_user_id();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        try {
            return $this->run_admin_post( 'jp_form_builder_save_settings', [ 'jp_form_builder_settings' => $values ] );
        } finally {
            wp_set_current_user( $previous );
        }
    }

    /** Sends the test mail through the settings page, as an administrator, and returns where it redirects. */
    private function send_test_mail( string $recipient = 'recipient@example.test' ): string {
        $previous = get_current_user_id();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        try {
            return $this->run_admin_post( 'jp_form_builder_test_mail', [ 'recipient' => $recipient ] );
        } finally {
            wp_set_current_user( $previous );
        }
    }

    /**
     * Requests a mail preview as an administrator.
     *
     * @param array<string,mixed> $params
     */
    private function preview_mail( array $params ): WP_REST_Response {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        $request = new WP_REST_Request( 'POST', '/jp-form-builder/v1/mail-preview' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( wp_json_encode( array_merge( [ 'kind' => 'notification', 'subject' => '', 'body' => '' ], $params ) ) );
        return rest_get_server()->dispatch( $request );
    }

    /** The `rev` a visitor's browser would send for the Form: what the embed markup carries. */
    private function embedded_rev( int $form_id ): string {
        $markup = do_blocks( '<!-- wp:jp-form-builder/embed {"formId":' . $form_id . '} /-->' );
        $this->assertSame( 1, preg_match( '/data-rev="([0-9a-f]{16})"/', $markup, $match ), 'The embed renders the Form.' );
        return $match[1];
    }
}
