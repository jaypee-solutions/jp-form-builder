<?php
/** Tests the design settings of the Form block through WordPress block rendering. */
final class Test_Form_Styling extends WP_UnitTestCase {

    private function create_form( string $fields = '' ): int {
        $fields = '' !== $fields ? $fields : '<!-- wp:jp-form-builder/email {"fieldName":"email"} /-->';
        return self::factory()->post->create(
            [
                'post_type'    => 'jp_form',
                'post_status'  => 'publish',
                'post_title'   => 'Styled Form',
                'post_content' => '<!-- wp:jp-form-builder/form -->' . $fields . '<!-- /wp:jp-form-builder/form -->',
            ]
        );
    }

    /** @param array<string,mixed> $attributes */
    private function render_embed( int $form_id, array $attributes = [] ): string {
        return do_blocks( '<!-- wp:jp-form-builder/embed ' . wp_json_encode( array_merge( [ 'formId' => $form_id ], $attributes ) ) . ' /-->' );
    }

    private function wrapper_tag( string $markup ): string {
        $this->assertSame( 1, preg_match( '/^<div [^>]*>/', trim( $markup ), $match ), 'The embed starts with its wrapper.' );
        return $match[0];
    }

    public function test_block_registers_style_variations_and_design_supports(): void {
        $type = WP_Block_Type_Registry::get_instance()->get_registered( 'jp-form-builder/embed' );
        $this->assertSame( [ 'standard', 'soft', 'underlined', 'rounded' ], array_column( $type->styles, 'name' ) );
        $this->assertSame( [ 'standard' ], array_column( array_filter( $type->styles, static fn( array $style ): bool => ! empty( $style['isDefault'] ) ), 'name' ) );
        foreach ( [ 'color', 'typography', 'spacing', '__experimentalBorder' ] as $support ) {
            $this->assertNotEmpty( $type->supports[ $support ], $support . ' support is registered' );
        }
        $this->assertTrue( $type->supports['spacing']['blockGap'] );
        $this->assertTrue( $type->supports['color']['button'] );
        $this->assertTrue( $type->supports['color']['link'] );
    }

    public function test_styled_embed_carries_the_variation_supports_and_sanitised_properties(): void {
        $id      = $this->create_form();
        $markup  = $this->render_embed(
            $id,
            [
                'className'        => 'is-style-soft',
                'textColor'        => 'contrast',
                'fontSize'         => 'large',
                'style'            => [
                    'color'   => [ 'background' => '#fafafa' ],
                    'spacing' => [
                        'padding'  => [ 'top' => '24px' ],
                        'blockGap' => 'var:preset|spacing|30',
                    ],
                    'border'  => [ 'radius' => '12px' ],
                ],
                'fieldBackground'  => 'base',
                'fieldBorderColor' => '#AABBCC',
                'focusColor'       => 'rgb(1, 2, 3)',
                'fieldRadius'      => '10px',
                'fieldBorderWidth' => '2px',
            ]
        );
        $wrapper = $this->wrapper_tag( $markup );

        $this->assertStringContainsString( 'wp-block-jp-form-builder-embed', $wrapper );
        $this->assertStringContainsString( 'is-style-soft', $wrapper );
        $this->assertStringContainsString( 'has-contrast-color', $wrapper );
        $this->assertStringContainsString( 'has-large-font-size', $wrapper );
        $this->assertStringContainsString( 'background-color:#fafafa', $wrapper );
        $this->assertStringContainsString( 'padding-top:24px', $wrapper );
        $this->assertStringContainsString( 'border-radius:12px', $wrapper );
        $this->assertStringContainsString( '--jpfb-field-background:var(--wp--preset--color--base)', $wrapper );
        $this->assertStringContainsString( '--jpfb-field-border-color:#aabbcc', $wrapper );
        $this->assertStringContainsString( '--jpfb-focus-color:#010203', $wrapper );
        $this->assertStringContainsString( '--jpfb-field-radius:10px', $wrapper );
        $this->assertStringContainsString( '--jpfb-field-border-width:2px', $wrapper );
        $this->assertStringContainsString( '--jpfb-field-gap:var(--wp--preset--spacing--30)', $wrapper );
        $this->assertStringContainsString( '<form method="post" class="jp-form-builder"', $markup );
    }

    public function test_malicious_design_values_are_dropped(): void {
        $id     = $this->create_form();
        $markup = $this->render_embed(
            $id,
            [
                'fieldBackground'  => 'red;}body{background:url(//evil.test/x)',
                'fieldBorderColor' => 'url(javascript:alert(1))',
                'focusColor'       => 'var(--x)',
                'fieldRadius'      => 'expression(alert(1))',
                'fieldBorderWidth' => '5px;color:red',
                'style'            => [ 'spacing' => [ 'blockGap' => '1px;}</style><script>alert(1)</script>' ] ],
            ]
        );

        $this->assertStringNotContainsString( '--jpfb-', $markup );
        $this->assertStringNotContainsString( 'evil.test', $markup );
        $this->assertStringNotContainsString( 'expression(', $markup );
        $this->assertStringNotContainsString( 'javascript:', $markup );
        $this->assertStringNotContainsString( '<script>', $markup );
        $this->assertStringNotContainsString( 'style=', $this->wrapper_tag( $markup ) );
    }

    public function test_unstyled_embed_has_no_style_attribute(): void {
        $markup = $this->render_embed( $this->create_form() );

        $this->assertStringNotContainsString( 'style=', $markup );
        $this->assertStringNotContainsString( '--jpfb-', $markup );
        $this->assertStringNotContainsString( 'is-style-', $markup );
    }

    public function test_fields_render_inside_the_field_grid_with_width_classes(): void {
        $id     = $this->create_form(
            '<!-- wp:jp-form-builder/name {"fieldName":"name","width":"50"} /-->'
            . '<!-- wp:jp-form-builder/email {"fieldName":"email","width":"50"} /-->'
            . '<!-- wp:jp-form-builder/phone {"fieldName":"phone","width":"33"} /-->'
            . '<!-- wp:jp-form-builder/text {"fieldName":"company","width":"25"} /-->'
            . '<!-- wp:jp-form-builder/textarea {"fieldName":"message"} /-->'
            . '<!-- wp:jp-form-builder/consent {"fieldName":"privacy","width":"50"} /-->'
        );
        $markup = $this->render_embed( $id );

        $this->assertStringContainsString( '<div class="jp-form-builder__fields">', $markup );
        $this->assertStringContainsString( 'jp-form-builder__field--name jp-form-builder__field--width-50" data-field-key="name"', $markup );
        $this->assertStringContainsString( 'jp-form-builder__field--email jp-form-builder__field--width-50" data-field-key="email"', $markup );
        $this->assertStringContainsString( 'jp-form-builder__field--phone jp-form-builder__field--width-33" data-field-key="phone"', $markup );
        $this->assertStringContainsString( 'jp-form-builder__field--text" data-field-key="company"', $markup, 'an unknown width falls back to full width' );
        $this->assertStringContainsString( 'jp-form-builder__field--textarea" data-field-key="message"', $markup );
        $this->assertStringContainsString( 'jp-form-builder__field--consent" data-field-key="privacy"', $markup, 'consent is always full width' );
    }

    public function test_editor_preview_request_renders_the_same_properties(): void {
        $id = $this->create_form();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        $request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/jp-form-builder/embed' );
        $request->set_query_params(
            [
                'context'    => 'edit',
                'attributes' => [
                    'formId'          => $id,
                    'fieldBackground' => 'base',
                    'fieldRadius'     => '6px',
                    'style'           => [ 'spacing' => [ 'blockGap' => '1rem' ] ],
                ],
            ]
        );
        $response = rest_get_server()->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $rendered = $response->get_data()['rendered'];
        $this->assertStringContainsString( '--jpfb-field-background:var(--wp--preset--color--base)', $rendered );
        $this->assertStringContainsString( '--jpfb-field-radius:6px', $rendered );
        $this->assertStringContainsString( '--jpfb-field-gap:1rem', $rendered );
    }
}
