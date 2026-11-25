<?php
/**
 * Verifies the data PHP hands to the block editor scripts.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Editor_Data extends WP_UnitTestCase {

    use Plugin_Test_Helpers;

    /** @return array<string,mixed> */
    private function editor_data( string $block ): array {
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'jp-form-builder/' . $block );
        $this->assertNotNull( $block_type, "Block $block is registered." );
        $this->assertNotEmpty( $block_type->editor_script_handles, "Block $block has an editor script." );

        // Every enqueue appends another inline script; the latest one is what the editor would run last.
        $script = implode( "\n", (array) wp_scripts()->get_data( $block_type->editor_script_handles[0], 'before' ) );
        $this->assertGreaterThan( 0, preg_match_all( '/window\.jpFormBuilderEditor\.' . $block . ' = (\{.*\});/', $script, $matches ), "Block $block gets editor data." );
        return json_decode( end( $matches[1] ), true );
    }

    private function open_form_editor( string $role = 'administrator' ): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
        $GLOBALS['post'] = get_post( self::factory()->post->create( [ 'post_type' => 'jp_form', 'post_status' => 'draft' ] ) );
        do_action( 'enqueue_block_editor_assets' );
    }

    public function test_country_block_receives_the_translated_country_list(): void {
        $this->open_form_editor();

        $countries = $this->editor_data( 'country' )['countries'];

        $this->assertCount( 32, $countries );
        $this->assertSame( 'Austria', $countries['AT'] );
    }

    public function test_phone_block_receives_only_the_supported_regions(): void {
        $this->open_form_editor();

        $this->assertSame( [ 'AT', 'DE', 'CH' ], array_keys( $this->editor_data( 'phone' )['regions'] ) );
    }

    public function test_form_block_reports_whether_turnstile_is_configured(): void {
        $this->open_form_editor();
        $this->assertFalse( $this->editor_data( 'form' )['turnstileConfigured'] );

        $this->save_settings( [ 'turnstile_site_key' => 'site-key', 'turnstile_secret_key' => 'secret-key' ] );
        do_action( 'enqueue_block_editor_assets' );

        $this->assertTrue( $this->editor_data( 'form' )['turnstileConfigured'] );
    }

    public function test_embed_block_offers_the_edit_link_only_to_users_who_may_edit_forms(): void {
        $this->open_form_editor();
        $admin_data = $this->editor_data( 'embed' );
        $this->assertTrue( $admin_data['canEditForms'] );
        $this->assertSame( admin_url( 'post.php' ), $admin_data['editFormUrl'] );

        $this->open_form_editor( 'editor' );
        $this->assertFalse( $this->editor_data( 'embed' )['canEditForms'] );
    }

    public function test_form_block_receives_the_code_editor_settings_on_form_screens_only(): void {
        $this->open_form_editor();

        $this->assertTrue( wp_script_is( 'code-editor', 'enqueued' ) );
        $this->assertArrayHasKey( 'codemirror', $this->editor_data( 'form' )['codeEditor'] );

        wp_dequeue_script( 'code-editor' );
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        $GLOBALS['post'] = get_post( self::factory()->post->create( [ 'post_type' => 'page' ] ) );
        do_action( 'enqueue_block_editor_assets' );

        $this->assertFalse( wp_script_is( 'code-editor', 'enqueued' ) );
    }
}
