<?php
/**
 * Verifies access and rendering for mail previews.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mail_Preview extends WP_UnitTestCase {

    public function test_preview_permission_rendering_and_layout_validation(): void {
        $request = static function ( array $params = [] ): WP_REST_Response {
            $rest = new WP_REST_Request( 'POST', '/jp-form-builder/v1/mail-preview' );
            $rest->set_header( 'content-type', 'application/json' );
            $rest->set_body( wp_json_encode( array_merge( [ 'kind' => 'notification', 'subject' => 'Hello {name}', 'body' => '<p>{name} {email} {message}</p>' ], $params ) ) );
            return rest_get_server()->dispatch( $rest );
        };
        wp_set_current_user( 0 );
        $this->assertSame( 401, $request()->get_status() );
        $editor = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor );
        $this->assertSame( 403, $request()->get_status() );
        add_filter( 'jp_form_builder_capability', static fn(): string => 'edit_posts' );
        do_action( 'init' );
        $this->assertSame( 200, $request()->get_status() );
        $this->assertSame( 403, $request( [ 'layout' => '' ] )->get_status() );
        remove_all_filters( 'jp_form_builder_capability' );
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $preview = $request( [ 'body' => '<p>{name} {email} {message}</p><script>alert(1)</script>', 'layout' => '<section>{site_name}{content}</section>' ] );
        $this->assertSame( 200, $preview->get_status() );
        $data = $preview->get_data();
        $this->assertSame( 'Hello Jane Example', $data['subject'] );
        $this->assertStringContainsString( 'Jane Example', $data['html'] );
        $this->assertStringContainsString( 'jane@example.test', $data['html'] );
        $this->assertStringContainsString( get_bloginfo( 'name' ), $data['html'] );
        $this->assertStringNotContainsString( '<script', $data['html'] );
        $confirmation = $request( [ 'kind' => 'confirmation', 'body' => '<p>{name} {email} {message} {all_fields}</p>' ] );
        $this->assertSame( 200, $confirmation->get_status() );
        $html = $confirmation->get_data()['html'];
        $this->assertStringNotContainsString( 'Jane Example', $html );
        $this->assertStringNotContainsString( 'jane@example.test', $html );
        $this->assertStringNotContainsString( 'sample message', $html );
        $this->assertSame( 400, $request( [ 'layout' => '<p>missing content</p>' ] )->get_status() );
        $this->assertSame( 200, $request( [ 'layout' => '' ] )->get_status() );
        $this->assertSame( [], tests_retrieve_phpmailer_instance()->getToAddresses() );
    }
}
