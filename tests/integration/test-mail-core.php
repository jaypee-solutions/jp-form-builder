<?php
/**
 * Integration tests for WordPress-backed mail sanitization and layout settings.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mail_Core extends WP_UnitTestCase {

    public function test_body_template_uses_wordpress_kses_profile(): void {
        $renderer = new \JayPee\FormBuilder\Mail_Template();
        $result   = $renderer->sanitize( '<p style="color:red">Keep</p><script>alert(1)</script><img src="https://remote.test/a.png">' );
        $this->assertStringContainsString( '<p style="color:red">Keep</p>', $result );
        $this->assertStringNotContainsString( '<script', $result );
        $this->assertStringNotContainsString( '<img', $result );
    }

    public function test_layout_sanitization_and_setting_fallback(): void {
        $settings = new \JayPee\FormBuilder\Settings();
        $layout   = new \JayPee\FormBuilder\Mail_Layout( $settings );
        $clean    = $layout->sanitize( '<style>p{color:red}</style><p>{content}</p><img src="https://remote.test/x"><a href="https://remote.test">x</a>' );
        $this->assertStringContainsString( '<style>', $clean );
        $this->assertStringNotContainsString( '<img', $clean );
        $this->assertStringNotContainsString( 'https://remote.test', $clean );
        $this->expectException( \InvalidArgumentException::class );
        $layout->sanitize( '<p>No content token</p>' );
    }

    public function test_saved_layout_and_filter_override(): void {
        $settings = new \JayPee\FormBuilder\Settings();
        $settings->save( [ 'mail_layout' => '<p>{content}</p>' ] );
        $this->assertSame( '<p>{content}</p>', ( new \JayPee\FormBuilder\Mail_Layout( $settings ) )->get_layout() );
        $settings->save( [ 'mail_layout' => '' ] );
        $this->assertStringContainsString( '{content}', ( new \JayPee\FormBuilder\Mail_Layout( $settings ) )->get_layout() );
        add_filter( 'jp_form_builder_mail_layout', static fn( string $value ): string => '' === $value ? '<div>{content}</div>' : '<div>{content}</div>' );
        $this->assertSame( '<div>{content}</div>', ( new \JayPee\FormBuilder\Mail_Layout( $settings ) )->get_layout() );
    }
}
