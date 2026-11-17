<?php
/**
 * Integration tests for WordPress-backed mail sanitization and layout settings, driven through
 * the settings page, the mail preview route and the test mail.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mail_Core extends WP_UnitTestCase {

    use Plugin_Test_Helpers;

    public function set_up(): void {
        parent::set_up();
        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test' ] );
        reset_phpmailer_instance();
    }

    public function tear_down(): void {
        reset_phpmailer_instance();
        parent::tear_down();
    }

    public function test_body_template_uses_wordpress_kses_profile_and_loads_no_remote_assets(): void {
        $response = $this->preview_mail(
            [
                'body' => '<p style="color:red">Keep</p><script>alert(1)</script><img src="https://remote.test/a.png">'
                    . '<video src="https://remote.test/v.mp4" poster="https://remote.test/p.png" controls></video>'
                    . '<audio src="https://remote.test/a.mp3"></audio>'
                    . '<div style="background-image:url(https://remote.test/b.png);color:blue">Background</div>'
                    . '<a href="https://remote.test/page">Link</a>',
            ]
        );
        $this->assertSame( 200, $response->get_status() );
        $html = $response->get_data()['html'];
        $this->assertStringContainsString( '<p style="color:red">Keep</p>', $html );
        $this->assertStringContainsString( 'color:blue', $html );
        $this->assertStringContainsString( 'href="https://remote.test/page"', $html, 'links stay' );
        $this->assertStringNotContainsString( '<script', $html );
        $this->assertStringNotContainsString( '<img', $html );
        $this->assertStringNotContainsString( 'remote.test/a.png', $html );
        $this->assertStringNotContainsString( 'remote.test/v.mp4', $html );
        $this->assertStringNotContainsString( 'remote.test/p.png', $html );
        $this->assertStringNotContainsString( 'remote.test/a.mp3', $html );
        $this->assertStringNotContainsString( 'remote.test/b.png', $html );
    }

    public function test_a_saved_body_template_is_stored_without_remote_assets(): void {
        $this->save_settings(
            [
                'notification_body' => '<p>Hi</p><video src="https://remote.test/v.mp4" poster="https://remote.test/p.png"></video><div style="background-image:url(https://remote.test/b.png)">x</div>',
            ]
        );
        $saved = get_option( 'jp_form_builder_settings' )['notification_body'];
        $this->assertStringContainsString( '<p>Hi</p>', $saved );
        $this->assertStringNotContainsString( 'remote.test', $saved );
    }

    public function test_layout_sanitization_on_save_and_the_content_requirement(): void {
        $this->save_settings( [ 'mail_layout' => '<style>p{color:red}</style><p>{content}</p><img src="https://remote.test/x"><a href="https://remote.test">x</a>' ] );
        $saved = get_option( 'jp_form_builder_settings' )['mail_layout'];
        $this->assertStringContainsString( '<style>', $saved );
        $this->assertStringContainsString( '{content}', $saved );
        $this->assertStringNotContainsString( '<img', $saved );
        $this->assertStringNotContainsString( 'https://remote.test', $saved );

        $location = $this->save_settings( [ 'mail_layout' => '<p>No content token</p>' ] );
        $this->assertStringContainsString( 'settings-error=invalid-layout', $location );
        $this->assertSame( $saved, get_option( 'jp_form_builder_settings' )['mail_layout'] );
    }

    public function test_saved_layout_and_filter_override_frame_the_test_mail(): void {
        $this->save_settings( [ 'mail_layout' => '<p class="saved">{content}</p>' ] );
        $this->send_test_mail();
        // PHPMailer exposes the HTML part as its camel-case `Body` property.
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        $this->assertStringContainsString( '<p class="saved">', tests_retrieve_phpmailer_instance()->Body );
        $this->assertStringContainsString( 'This is a test message from Form Builder.', tests_retrieve_phpmailer_instance()->Body );

        reset_phpmailer_instance();
        $this->save_settings( [ 'mail_layout' => '' ] );
        $this->send_test_mail();
        $this->assertStringContainsString( '<html', tests_retrieve_phpmailer_instance()->Body, 'an empty layout means the bundled default' );

        reset_phpmailer_instance();
        $override = static fn(): string => '<div class="override">{content}</div>';
        add_filter( 'jp_form_builder_mail_layout', $override );
        try {
            $this->send_test_mail();
        } finally {
            remove_filter( 'jp_form_builder_mail_layout', $override );
        }
        $this->assertStringContainsString( '<div class="override">', tests_retrieve_phpmailer_instance()->Body );
        $this->assertStringNotContainsString( '<html', tests_retrieve_phpmailer_instance()->Body );
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
    }
}
