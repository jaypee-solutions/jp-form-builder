<?php
/**
 * Verifies settings-page mail layout saving and use.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mail_Settings extends WP_UnitTestCase {

    private function submit_settings( string $layout ): string {
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $_POST = [
            'action'                   => 'jp_form_builder_save_settings',
            '_wpnonce'                 => wp_create_nonce( 'jp_form_builder_save_settings' ),
            'jp_form_builder_settings' => [ 'mail_layout' => $layout ],
        ];
        $_REQUEST = $_POST;
        $redirect = static function ( $location ) {
            throw new Settings_Page_Denied( (string) $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( 'admin_post_jp_form_builder_save_settings' );
        } catch ( Settings_Page_Denied $exception ) {
            return $exception->getMessage();
        } finally {
            remove_filter( 'wp_redirect', $redirect );
            $_POST    = [];
            $_REQUEST = [];
        }
        $this->fail( 'Settings handler did not redirect.' );
    }

    public function test_invalid_layout_redirects_and_preserves_saved_layout(): void {
        update_option( 'jp_form_builder_settings', [ 'mail_layout' => '<main>{content}</main>' ] );
        $location = $this->submit_settings( '<p>no content token</p>' );
        $this->assertStringContainsString( 'settings-error=invalid-layout', $location );
        $this->assertSame( '<main>{content}</main>', get_option( 'jp_form_builder_settings' )['mail_layout'] );
    }

    public function test_empty_layout_uses_bundled_default_and_remote_assets_are_removed(): void {
        $this->submit_settings( '' );
        $this->assertSame( '', get_option( 'jp_form_builder_settings' )['mail_layout'] );
        $mailer = new \JayPee\FormBuilder\Mailer();
        $result = $mailer->send_test( 'recipient@example.test' );
        $this->assertTrue( true === $result );
        $message = tests_retrieve_phpmailer_instance()->Body;
        $this->assertStringContainsString( 'recipient@example.test', tests_retrieve_phpmailer_instance()->getToAddresses()[0][0] );
        $this->assertStringContainsString( '<html', $message );
        $this->submit_settings( '<div>{content}<img src="https://remote.test/a.png"><style>@import url(https://remote.test/a.css); p{background:url(https://remote.test/b.png)}</style></div>' );
        $saved = get_option( 'jp_form_builder_settings' )['mail_layout'];
        $this->assertStringNotContainsString( 'https://', $saved );
        $this->assertStringNotContainsString( '@import', $saved );
        $this->assertStringNotContainsString( '<img', $saved );
    }
}
