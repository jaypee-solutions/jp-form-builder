<?php
/** Tests Form model behavior through WordPress. */
final class Test_Form_Model extends WP_UnitTestCase {
    use Plugin_Test_Helpers;

    /** Submits an empty Submission for the Form as a visitor who loaded it at `$rev`. */
    private function submit_with_rev( int $form_id, string $rev ): WP_REST_Response {
        wp_set_current_user( 0 );
        $remote_addr            = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.20';
        add_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
        $token   = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/jp-form-builder/v1/forms/' . $form_id . '/token' ) )->get_data()['token'];
        $request = new WP_REST_Request( 'POST', '/jp-form-builder/v1/forms/' . $form_id . '/submissions' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( wp_json_encode( [ 'token' => $token, 'hp' => '', 'rev' => $rev, 'fields' => [] ] ) );
        try {
            return rest_get_server()->dispatch( $request );
        } finally {
            remove_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
            if ( null === $remote_addr ) {
                unset( $_SERVER['REMOTE_ADDR'] );
            } else {
                $_SERVER['REMOTE_ADDR'] = $remote_addr;
            }
        }
    }

    private function create_form( string $status = 'publish', string $type = 'jp_form' ): int {
        return self::factory()->post->create( [
            'post_type' => $type,
            'post_status' => $status,
            'post_title' => 'Integration Form',
            'post_content' => '<!-- wp:jp-form-builder/form {"submitLabel":"Send"} --><!-- wp:jp-form-builder/email {"label":"Work Email","fieldName":"Your E-Mail!"} /--><!-- wp:jp-form-builder/embed {"formId":99} /--><!-- wp:jp-form-builder/consent {"fieldName":"privacy","required":false} /--><!-- /wp:jp-form-builder/form -->',
        ] );
    }
    public function test_embed_renders_only_published_form_and_revision_lookup_works(): void {
        $id = $this->create_form();
        $rendered = do_blocks( '<!-- wp:jp-form-builder/embed {"formId":' . $id . '} /-->' );
        $this->assertStringContainsString( 'Work Email', $rendered );
        $this->assertStringContainsString( 'data-field-key="your_e_mail"', $rendered );
        $this->assertStringNotContainsString( 'data-field-key="embed_', $rendered );
        $this->assertStringContainsString( 'name="privacy" value="1" required', $rendered );
        $this->assertStringContainsString( 'data-rev="', $rendered );
        $rev = $this->embedded_rev( $id );
        wp_save_post_revision( $id );
        wp_update_post( [ 'ID' => $id, 'post_content' => '<!-- wp:jp-form-builder/form --><!-- wp:jp-form-builder/name /--><!-- /wp:jp-form-builder/form -->' ] );
        $this->assertNotSame( $rev, $this->embedded_rev( $id ), 'the rev follows the content' );
        $stale = $this->submit_with_rev( $id, $rev );
        $this->assertSame( 422, $stale->get_status() );
        $this->assertArrayHasKey( 'privacy', $stale->get_data()['data']['errors'], 'the old rev still validates against the Fields the visitor saw' );
        $this->assertSame( 'jpfb_form_changed', $this->submit_with_rev( $id, '0000000000000000' )->get_data()['code'] );
        $this->assertSame( '', do_blocks( '<!-- wp:jp-form-builder/form /-->' ) );
    }
    public function test_embed_is_wrapped_in_the_block_wrapper_so_alignment_and_margin_apply(): void {
        $id     = $this->create_form();
        $markup = do_blocks( '<!-- wp:jp-form-builder/embed {"formId":' . $id . ',"align":"wide","style":{"spacing":{"margin":{"top":"10px"}}}} /-->' );
        $this->assertMatchesRegularExpression( '/^<div [^>]*class="[^"]*\bwp-block-jp-form-builder-embed\b[^"]*"[^>]*><form /', $markup );
        $this->assertMatchesRegularExpression( '/^<div [^>]*class="[^"]*\balignwide\b/', $markup );
        $this->assertStringContainsString( 'margin-top:10px', $markup );
        $this->assertStringEndsWith( '</form></div>', trim( $markup ) );
    }
    public function test_empty_field_label_is_translated_when_rendered(): void {
        $id = self::factory()->post->create(
            [
                'post_type'    => 'jp_form',
                'post_status'  => 'publish',
                'post_title'   => 'Kontakt',
                'post_content' => '<!-- wp:jp-form-builder/form --><!-- wp:jp-form-builder/email /--><!-- /wp:jp-form-builder/form -->',
            ]
        );
        $translation = static function ( $translated, $text, $domain ) {
            return 'jp-form-builder' === $domain && 'Email' === $text ? 'E-Mail' : $translated;
        };
        add_filter( 'gettext', $translation, 10, 3 );
        try {
            $rendered = do_blocks( '<!-- wp:jp-form-builder/embed {"formId":' . $id . '} /-->' );
            $this->assertStringContainsString( 'E-Mail <span class="jp-form-builder__required"', $rendered );
        } finally {
            remove_filter( 'gettext', $translation, 10 );
        }
    }
    public function test_embed_ignores_unpublished_and_other_post_types(): void {
        $draft = $this->create_form( 'draft' );
        $private = $this->create_form( 'private' );
        $page = $this->create_form( 'publish', 'page' );
        foreach ( [ $draft, $private, $page ] as $id ) {
            $this->assertSame( '', do_blocks( '<!-- wp:jp-form-builder/embed {"formId":' . $id . '} /-->' ) );
        }
    }
    public function test_forms_route_requires_edit_posts_and_lists_only_published_forms(): void {
        $published = $this->create_form();
        $this->create_form( 'draft' );
        wp_set_current_user( 0 );
        $request = new WP_REST_Request( 'GET', '/jp-form-builder/v1/forms' );
        $this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status() );
        $user = self::factory()->user->create( [ 'role' => 'contributor' ] );
        wp_set_current_user( $user );
        $response = rest_get_server()->dispatch( $request );
        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ $published ], array_column( $response->get_data(), 'id' ) );
    }
    public function test_cpt_template_and_allowed_blocks_are_registered(): void {
        $type = get_post_type_object( 'jp_form' );
        $this->assertSame( [ [ 'jp-form-builder/form', [] ] ], $type->template );
        $id = $this->create_form();
        $context = new WP_Block_Editor_Context( [ 'post' => get_post( $id ) ] );
        $allowed = apply_filters( 'allowed_block_types_all', true, $context );
        $this->assertContains( 'jp-form-builder/form', $allowed );
        $this->assertContains( 'jp-form-builder/consent', $allowed );
        $this->assertContains( 'core/paragraph', $allowed );
        $this->assertContains( 'jp-form-builder/radio', $allowed );
        $this->assertContains( 'jp-form-builder/checkbox', $allowed );
        $this->assertContains( 'jp-form-builder/conditional', $allowed );
        $this->assertContains( 'core/columns', $allowed );
        $this->assertContains( 'core/column', $allowed );
        $this->assertContains( 'core/group', $allowed );
    }
}
