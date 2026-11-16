<?php
/**
 * Verifies the WordPress mailer configuration and message handling.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mailer_Integration extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        update_option( 'home', 'https://example.test' );
        update_option( 'siteurl', 'https://example.test' );
        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test' ] );
        reset_phpmailer_instance();
    }

    public function test_smtp_configuration_and_no_smtp_fallback(): void {
        foreach ( [ 'none' => '', 'starttls' => 'tls', 'smtps' => 'ssl' ] as $encryption => $secure ) {
            update_option( 'jp_form_builder_settings', [
                'smtp_host'       => 'smtp.example.test',
                'smtp_encryption' => $encryption,
                'smtp_auth'       => '1',
                'smtp_user'       => 'mailer-user',
                'smtp_password'   => ( new \JayPee\FormBuilder\Secret_Box() )->encrypt( 'mailer-secret' ),
            ] );
            // PHPMailer uses camel-case configuration properties.
            // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            do_action( 'phpmailer_init', tests_retrieve_phpmailer_instance() );
            $mailer = tests_retrieve_phpmailer_instance();
            $this->assertSame( 'smtp', $mailer->Mailer );
            $this->assertSame( 'smtp.example.test', $mailer->Host );
            $this->assertSame( 2525, $mailer->Port );
            $this->assertSame( $secure, $mailer->SMTPSecure );
            $this->assertSame( 'none' !== $encryption, $mailer->SMTPAutoTLS );
            $this->assertTrue( $mailer->SMTPAuth );
            $this->assertSame( 'mailer-user', $mailer->Username );
            $this->assertSame( 'mailer-secret', $mailer->Password );
            $this->assertSame( 15, $mailer->Timeout );
            reset_phpmailer_instance();
            // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        }
        update_option( 'jp_form_builder_settings', [] );
        $mailer = tests_retrieve_phpmailer_instance();
        do_action( 'phpmailer_init', $mailer );
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        $this->assertSame( 'mail', $mailer->Mailer );
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
    }

    public function test_from_identity_only_replaces_default_unless_forced(): void {
        update_option( 'home', 'https://www.example.test' );
        update_option( 'siteurl', 'https://www.example.test' );
        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test', 'from_name' => 'Forms' ] );
        $network_url = static fn(): string => 'https://www.example.test';
        add_filter( 'network_home_url', $network_url );
        $host = wp_parse_url( network_home_url(), PHP_URL_HOST );
        $default_from = 'wordpress@' . preg_replace( '/^www\./i', '', (string) $host );
        $this->assertSame( 'forms@example.test', apply_filters( 'wp_mail_from', $default_from ) );
        remove_filter( 'network_home_url', $network_url );
        $this->assertSame( 'Forms', apply_filters( 'wp_mail_from_name', 'WordPress' ) );
        $this->assertSame( 'caller@example.test', apply_filters( 'wp_mail_from', 'caller@example.test' ) );
        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test', 'from_name' => 'Forms', 'force_from' => '1' ] );
        $this->assertSame( 'forms@example.test', apply_filters( 'wp_mail_from', 'caller@example.test' ) );
        $this->assertSame( 'Forms', apply_filters( 'wp_mail_from_name', 'Caller' ) );
        do_action( 'phpmailer_init', tests_retrieve_phpmailer_instance() );
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        $this->assertSame( 'forms@example.test', tests_retrieve_phpmailer_instance()->Sender );
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
    }

    public function test_test_mail_is_html_with_text_alternative_and_plain_mail_resets_alt_body(): void {
        reset_phpmailer_instance();
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $_POST    = [
            'action'    => 'jp_form_builder_test_mail',
            '_wpnonce'  => wp_create_nonce( 'jp_form_builder_test_mail' ),
            'recipient' => 'recipient@example.test',
        ];
        $_REQUEST = $_POST;
        $redirect = static function ( $location ) {
            throw new Settings_Page_Denied( (string) $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( 'admin_post_jp_form_builder_test_mail' );
            $this->fail( 'The test-mail action did not redirect.' );
        } catch ( Settings_Page_Denied $exception ) {
            $this->assertStringContainsString( 'test-mail=sent', $exception->getMessage() );
        } finally {
            remove_filter( 'wp_redirect', $redirect );
            $_POST    = [];
            $_REQUEST = [];
        }
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        $mailer = tests_retrieve_phpmailer_instance();
        $this->assertSame( 'multipart/alternative', $mailer->ContentType );
        $this->assertStringContainsString( 'This is a test message from Form Builder.', $mailer->AltBody );
        $this->assertStringContainsString( 'Content-Type: text/html', $mailer->get_sent()->body );

        wp_mail( 'recipient@example.test', 'plain', 'plain body' );
        $this->assertSame( '', tests_retrieve_phpmailer_instance()->AltBody );
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
    }
    public function test_test_mail_requires_nonce_and_admin_capability(): void {
        $editor = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor );
        $denied = static function (): callable {
            return static function (): void {
                throw new Settings_Page_Denied();
            };
        };
        add_filter( 'wp_die_handler', $denied );
        try {
            do_action( 'admin_post_jp_form_builder_test_mail' );
            $this->fail( 'Editor test mail was not denied.' );
        } catch ( Settings_Page_Denied $exception ) {
            $this->assertInstanceOf( Settings_Page_Denied::class, $exception );
        } finally {
            remove_filter( 'wp_die_handler', $denied );
        }
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $_POST    = [ 'recipient' => 'recipient@example.test' ];
        $_REQUEST = $_POST;
        add_filter( 'wp_die_handler', $denied );
        try {
            do_action( 'admin_post_jp_form_builder_test_mail' );
            $this->fail( 'Test mail without nonce was not denied.' );
        } catch ( Settings_Page_Denied $exception ) {
            $this->assertInstanceOf( Settings_Page_Denied::class, $exception );
        } finally {
            remove_filter( 'wp_die_handler', $denied );
            $_POST    = [];
            $_REQUEST = [];
        }
        $this->assertSame( [], tests_retrieve_phpmailer_instance()->getToAddresses() );
    }

    public function test_failed_test_mail_redirects_and_logs_redacted_recipient(): void {
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $_POST = [
            'action'    => 'jp_form_builder_test_mail',
            '_wpnonce'  => wp_create_nonce( 'jp_form_builder_test_mail' ),
            'recipient' => 'secret-recipient@example.test',
        ];
        $_REQUEST = $_POST;
        $failure = static function () {
            return false;
        };
        add_filter( 'pre_wp_mail', $failure );
        $redirect = static function ( $location ) {
            throw new Settings_Page_Denied( (string) $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( 'admin_post_jp_form_builder_test_mail' );
        } catch ( Settings_Page_Denied $exception ) {
            $this->assertStringContainsString( 'test-mail=failed', $exception->getMessage() );
        } finally {
            remove_filter( 'pre_wp_mail', $failure );
            remove_filter( 'wp_redirect', $redirect );
            $_POST    = [];
            $_REQUEST = [];
        }
        $logs = get_option( 'jp_form_builder_mail_log', [] );
        $this->assertNotEmpty( $logs );
        $this->assertStringNotContainsString( 'secret-recipient@example.test', wp_json_encode( $logs ) );
    }
}
