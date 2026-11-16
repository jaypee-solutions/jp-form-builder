<?php
/**
 * Verifies that Forms are not anonymously readable through the REST API.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Form_Access extends WP_UnitTestCase {

	public function test_anonymous_rest_reads_are_denied(): void {
		$form_id = self::factory()->post->create( [ 'post_type' => 'jp_form', 'post_status' => 'publish', 'post_title' => 'Private form' ] );
		wp_set_current_user( 0 );
		$item = new WP_REST_Request( 'GET', '/wp/v2/jp_form/' . $form_id );
		$list = new WP_REST_Request( 'GET', '/wp/v2/jp_form' );
		$this->assertContains( rest_get_server()->dispatch( $item )->get_status(), [ 401, 403 ] );
		$this->assertContains( rest_get_server()->dispatch( $list )->get_status(), [ 401, 403 ] );
	}

	public function test_settings_page_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertFalse( current_user_can( 'manage_options' ) );
		$this->assertTrue( current_user_can( 'edit_posts' ) );
	}
}
