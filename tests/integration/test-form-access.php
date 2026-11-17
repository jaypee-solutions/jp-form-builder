<?php
/**
 * Verifies access control for Forms and settings.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Settings_Page_Denied extends Exception {}

final class Test_Form_Access extends WP_UnitTestCase {

    public function test_anonymous_rest_reads_are_denied(): void {
        $form_id = self::factory()->post->create( [ 'post_type' => 'jp_form', 'post_status' => 'publish', 'post_title' => 'Private form' ] );
        wp_set_current_user( 0 );
        $item = new WP_REST_Request( 'GET', '/wp/v2/jp_form/' . $form_id );
        $list = new WP_REST_Request( 'GET', '/wp/v2/jp_form' );
        $this->assertContains( rest_get_server()->dispatch( $item )->get_status(), [ 401, 403 ] );
        $this->assertContains( rest_get_server()->dispatch( $list )->get_status(), [ 401, 403 ] );
    }

    public function test_editor_requires_form_capability_filter(): void {
        $form_id = self::factory()->post->create( [ 'post_type' => 'jp_form', 'post_status' => 'publish' ] );
        $editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor );
        $this->assertFalse( current_user_can( 'edit_post', $form_id ) );
        add_filter( 'jp_form_builder_capability', static fn(): string => 'edit_posts' );
        do_action( 'init' );
        $this->assertTrue( current_user_can( 'edit_post', $form_id ) );
        remove_all_filters( 'jp_form_builder_capability' );
    }

    public function test_settings_handler_denies_non_admin_and_saves_allowed_values(): void {
        $editor = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor );
        add_filter(
            'wp_die_handler',
            static function (): callable {
                return static function (): void {
                    throw new Settings_Page_Denied();
                };
            }
        );
        try {
            do_action( 'admin_post_jp_form_builder_save_settings' );
            $this->fail( 'Non-admin settings save was not denied.' );
        } catch ( Settings_Page_Denied $exception ) {
            $this->assertInstanceOf( Settings_Page_Denied::class, $exception );
        } finally {
            remove_all_filters( 'wp_die_handler' );
        }

        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $nonce   = wp_create_nonce( 'jp_form_builder_save_settings' );
        $_POST   = [
            'action'                   => 'jp_form_builder_save_settings',
            '_wpnonce'                 => $nonce,
            'jp_form_builder_settings' => [ 'smtp_host' => 'overwrite.example', 'smtp_port' => 465, 'smtp_user' => 'saved-user' ],
        ];
        $_REQUEST = $_POST;
        add_filter(
            'wp_redirect',
            static function ( $location ) {
                throw new Settings_Page_Denied( (string) $location );
            }
        );
        try {
            do_action( 'admin_post_jp_form_builder_save_settings' );
        } catch ( Settings_Page_Denied $exception ) {
            $this->assertStringContainsString( 'settings-updated=1', $exception->getMessage() );
        } finally {
            remove_all_filters( 'wp_redirect' );
            $_POST    = [];
            $_REQUEST = [];
        }
        $stored = get_option( 'jp_form_builder_settings', [] );
        $this->assertSame( 'saved-user', $stored['smtp_user'] );
        $mailer = tests_retrieve_phpmailer_instance();
        do_action( 'phpmailer_init', $mailer );
        $this->assertSame( 2525, $mailer->Port ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
        $this->assertArrayNotHasKey( 'smtp_port', $stored );
    }
}
