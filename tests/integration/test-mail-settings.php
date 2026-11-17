<?php
/**
 * Verifies settings-page saving of the mail layout and the default recipient, and the layout's use in mail.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mail_Settings extends WP_UnitTestCase {

    use Plugin_Test_Helpers;

    public function set_up(): void {
        parent::set_up();
        update_option( 'home', 'https://example.test' );
        update_option( 'siteurl', 'https://example.test' );
        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test' ] );
        reset_phpmailer_instance();
    }

    public function tear_down(): void {
        reset_phpmailer_instance();
        parent::tear_down();
    }

    public function test_invalid_layout_redirects_keeps_the_saved_layout_and_saves_the_other_settings(): void {
        update_option( 'jp_form_builder_settings', [ 'mail_layout' => '<main>{content}</main>', 'from_name' => 'Old name' ] );
        $location = $this->save_settings( [ 'mail_layout' => '<p>no content token</p>', 'from_name' => 'New name', 'smtp_host' => 'mail.example.test' ] );
        $this->assertStringStartsWith( admin_url( 'edit.php?post_type=jp_form&page=jp-form-builder-settings' ), $location );
        $this->assertStringContainsString( 'settings-error=invalid-layout', $location );
        $saved = get_option( 'jp_form_builder_settings' );
        $this->assertSame( '<main>{content}</main>', $saved['mail_layout'] );
        $this->assertSame( 'New name', $saved['from_name'] );
        $this->assertSame( 'mail.example.test', $saved['smtp_host'] );
    }

    public function test_default_recipient_is_saved_as_a_list_of_valid_addresses_and_receives_the_notification(): void {
        $this->save_settings( [ 'default_recipient' => 'a@x.de, b@y.de ,not-an-address' ] );
        $this->assertSame( 'a@x.de, b@y.de', get_option( 'jp_form_builder_settings' )['default_recipient'] );

        $form_id = self::factory()->post->create(
            [
                'post_type'    => 'jp_form',
                'post_status'  => 'publish',
                'post_title'   => 'Contact',
                'post_content' => '<!-- wp:jp-form-builder/form --><!-- wp:jp-form-builder/email {"fieldName":"email"} /--><!-- /wp:jp-form-builder/form -->',
            ]
        );
        $rev = $this->embedded_rev( $form_id );
        wp_set_current_user( 0 );
        $_SERVER['REMOTE_ADDR'] = '203.0.113.30';
        add_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
        $token   = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/jp-form-builder/v1/forms/' . $form_id . '/token' ) )->get_data()['token'];
        $request = new WP_REST_Request( 'POST', '/jp-form-builder/v1/forms/' . $form_id . '/submissions' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( wp_json_encode( [ 'token' => $token, 'hp' => '', 'rev' => $rev, 'fields' => [ 'email' => 'jane@example.test' ] ] ) );
        try {
            $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
        } finally {
            remove_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
            unset( $_SERVER['REMOTE_ADDR'] );
        }
        $recipients = array_column( tests_retrieve_phpmailer_instance()->getToAddresses(), 0 );
        $this->assertSame( [ 'a@x.de', 'b@y.de' ], $recipients );
    }

    public function test_empty_layout_uses_bundled_default_and_remote_assets_are_removed(): void {
        $this->save_settings( [ 'mail_layout' => '' ] );
        $this->assertSame( '', get_option( 'jp_form_builder_settings' )['mail_layout'] );
        $this->assertStringContainsString( 'test-mail=sent', $this->send_test_mail( 'recipient@example.test' ) );
        // PHPMailer exposes the HTML part as its camel-case `Body` property.
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        $message = tests_retrieve_phpmailer_instance()->Body;
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        $this->assertSame( 'recipient@example.test', tests_retrieve_phpmailer_instance()->getToAddresses()[0][0] );
        $this->assertStringContainsString( '<html', $message );
        $this->save_settings( [ 'mail_layout' => '<div>{content}<img src="https://remote.test/a.png"><style>@import url(https://remote.test/a.css); p{background:url(https://remote.test/b.png)}</style></div>' ] );
        $saved = get_option( 'jp_form_builder_settings' )['mail_layout'];
        $this->assertStringNotContainsString( 'https://', $saved );
        $this->assertStringNotContainsString( '@import', $saved );
        $this->assertStringNotContainsString( '<img', $saved );
    }
}
