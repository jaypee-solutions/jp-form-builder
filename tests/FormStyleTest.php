<?php
/** Tests the sanitising of Form embed design settings. */
use JayPee\FormBuilder\Field_Definition;
use JayPee\FormBuilder\Form_Definition;
use JayPee\FormBuilder\Form_Renderer;
use JayPee\FormBuilder\Form_Style;
use PHPUnit\Framework\TestCase;
final class FormStyleTest extends TestCase {
    /** @return array<string,array{0:string,1:string}> */
    public static function allowed_colors(): array {
        return [
            'palette slug'       => [ 'vivid-red', 'var(--wp--preset--color--vivid-red)' ],
            'numbered slug'      => [ 'accent-2', 'var(--wp--preset--color--accent-2)' ],
            'short hex'          => [ '#fff', '#fff' ],
            'hex'                => [ '#1E73BE', '#1e73be' ],
            'hex with alpha'     => [ '#1e73be80', '#1e73be80' ],
            'rgb'                => [ 'rgb(30, 115, 190)', '#1e73be' ],
            'rgba'               => [ 'rgba(0,0,0,0.5)', '#00000080' ],
            'modern rgb'         => [ 'rgb(0 0 0 / 50%)', '#00000080' ],
            'rgb percentages'    => [ 'rgb(100% 0% 50%)', '#ff0080' ],
            'rgb clamped'        => [ 'rgb(300, -5, 0)', '#ff0000' ],
            'hsl'                => [ 'hsl(210, 50%, 40%)', '#336699' ],
            'hsla'               => [ 'hsla(210deg 50% 40% / .4)', '#33669966' ],
            'negative hue'       => [ 'hsl(-150 100% 50%)', '#0080ff' ],
            'transparent'        => [ 'transparent', 'transparent' ],
            'currentcolor'       => [ 'currentColor', 'currentcolor' ],
            'surrounding spaces' => [ "  #fff\n", '#fff' ],
        ];
    }

    /** @dataProvider allowed_colors */
    public function test_color_accepts_slugs_hex_functions_and_keywords( string $input, string $expected ): void {
        self::assertSame( $expected, Form_Style::color( $input ) );
    }

    /** @return array<string,array{0:mixed}> */
    public static function rejected_colors(): array {
        return [
            'declaration break out' => [ 'red;}body{' ],
            'url'                   => [ 'url(https://example.test/a.png)' ],
            'expression'            => [ 'expression(alert(1))' ],
            'var injection'         => [ 'var(--x)' ],
            'var inside rgb'        => [ 'rgb(var(--x), 0, 0)' ],
            'closing brace'         => [ '#fff}' ],
            'semicolon after hex'   => [ '#fff;color:red' ],
            'bad hex length'        => [ '#12345' ],
            'rgb with two values'   => [ 'rgb(1 2)' ],
            'rgb with a word'       => [ 'rgb(1 2 three)' ],
            'hsl without percent'   => [ 'hsl(210 50 40)' ],
            'hsl with turns'        => [ 'hsl(0.5turn 50% 40%)' ],
            'comment'               => [ 'rgb(0 0 0 /* x */)' ],
            'quote'                 => [ 'rgb(0,0,0)"' ],
            'backslash'             => [ '\\72 ed' ],
            'uppercase slug'        => [ 'Vivid-Red' ],
            'empty'                 => [ '' ],
            'array'                 => [ [ '#fff' ] ],
            'int'                   => [ 5 ],
        ];
    }

    /**
     * @dataProvider rejected_colors
     * @param mixed $input
     */
    public function test_color_drops_everything_else( $input ): void {
        self::assertSame( '', Form_Style::color( $input ) );
    }

    /** @return array<string,array{0:mixed,1:string}> */
    public static function lengths(): array {
        return [
            'px'          => [ '6px', '6px' ],
            'decimal rem' => [ '0.75rem', '0.75rem' ],
            'em'          => [ '1em', '1em' ],
            'percent'     => [ '50%', '50%' ],
            'zero px'     => [ '0px', '0px' ],
            'negative'    => [ '-1px', '' ],
            'unitless'    => [ '6', '' ],
            'calc'        => [ 'calc(1px + 1px)', '' ],
            'var'         => [ 'var(--x)', '' ],
            'viewport'    => [ '5vw', '' ],
            'trailing'    => [ '6px;color:red', '' ],
            'number'      => [ 6, '' ],
        ];
    }

    /**
     * @dataProvider lengths
     * @param mixed $input
     */
    public function test_length_accepts_plain_lengths_only( $input, string $expected ): void {
        self::assertSame( $expected, Form_Style::length( $input ) );
    }

    public function test_gap_accepts_spacing_presets_and_lengths(): void {
        self::assertSame( 'var(--wp--preset--spacing--40)', Form_Style::gap( 'var:preset|spacing|40' ) );
        self::assertSame( '2rem', Form_Style::gap( '2rem' ) );
        self::assertSame( '', Form_Style::gap( 'var:preset|color|red' ) );
        self::assertSame( '', Form_Style::gap( 'var:preset|spacing|40;}' ) );
        self::assertSame( '', Form_Style::gap( 'var(--x)' ) );
        self::assertSame( '', Form_Style::gap( [ 'top' => '1rem' ] ) );
    }

    public function test_properties_map_every_setting_and_skip_invalid_ones(): void {
        $properties = Form_Style::properties(
            [
                'fieldBackground'  => 'base',
                'fieldBorderColor' => '#112233',
                'focusColor'       => 'url(x)',
                'fieldRadius'      => '8px',
                'fieldBorderWidth' => 'thick',
                'style'            => [ 'spacing' => [ 'blockGap' => 'var:preset|spacing|30' ] ],
            ]
        );
        self::assertSame(
            [
                '--jpfb-field-background'   => 'var(--wp--preset--color--base)',
                '--jpfb-field-border-color' => '#112233',
                '--jpfb-field-radius'       => '8px',
                '--jpfb-field-gap'          => 'var(--wp--preset--spacing--30)',
            ],
            $properties
        );
        self::assertSame(
            '--jpfb-field-background:var(--wp--preset--color--base);--jpfb-field-border-color:#112233;--jpfb-field-radius:8px;--jpfb-field-gap:var(--wp--preset--spacing--30);',
            Form_Style::declarations(
                [
                    'fieldBackground'  => 'base',
                    'fieldBorderColor' => '#112233',
                    'fieldRadius'      => '8px',
                    'style'            => [ 'spacing' => [ 'blockGap' => 'var:preset|spacing|30' ] ],
                ]
            )
        );
    }

    public function test_nothing_set_means_no_declarations(): void {
        self::assertSame( [], Form_Style::properties( [ 'formId' => 3, 'fieldBackground' => '', 'style' => [ 'color' => [ 'text' => '#000' ] ] ] ) );
        self::assertSame( '', Form_Style::declarations( [] ) );
    }

    public function test_width_class_marks_only_narrow_widths(): void {
        self::assertSame( 'jp-form-builder__field--width-50', Form_Style::width_class( '50' ) );
        self::assertSame( 'jp-form-builder__field--width-33', Form_Style::width_class( 33 ) );
        self::assertSame( '', Form_Style::width_class( '100' ) );
        self::assertSame( '', Form_Style::width_class( '' ) );
        self::assertSame( '', Form_Style::width_class( '25' ) );
        self::assertSame( '', Form_Style::width_class( '50" onclick="x' ) );
        self::assertSame( '', Form_Style::width_class( null ) );
    }

    public function test_renderer_groups_fields_adds_width_classes_and_emits_no_style_attribute(): void {
        $form   = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Field_Definition( 'name', 'name', [ 'label' => 'Name', 'width' => '50' ] ),
            new Field_Definition( 'email', 'email', [ 'label' => 'Email', 'width' => '50' ] ),
            new Field_Definition( 'phone', 'phone', [ 'label' => 'Phone', 'width' => '33' ] ),
            new Field_Definition( 'text', 'company', [ 'label' => 'Company', 'width' => '100' ] ),
            new Field_Definition( 'textarea', 'message', [ 'label' => 'Message', 'width' => 'wide' ] ),
            new Field_Definition( 'consent', 'privacy', [ 'width' => '50' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form );
        self::assertStringNotContainsString( 'style=', $markup );
        self::assertStringContainsString( '<div class="jp-form-builder__fields">', $markup );
        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--name jp-form-builder__field--width-50" data-field-key="name"', $markup );
        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--email jp-form-builder__field--width-50" data-field-key="email"', $markup );
        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--phone jp-form-builder__field--width-33" data-field-key="phone"', $markup );
        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--text" data-field-key="company"', $markup );
        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--textarea" data-field-key="message"', $markup );
        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--consent" data-field-key="privacy"', $markup );
        self::assertSame( 2, substr_count( $markup, '--width-50"' ) );
        // The error summary, the status and the button stay outside the Field grid, so they are full width.
        self::assertLessThan( strpos( $markup, 'jp-form-builder__fields' ), strpos( $markup, 'jp-form-builder__error-summary' ) );
        self::assertGreaterThan( strpos( $markup, '</div><div class="jp-form-builder__honeypot"' ), strpos( $markup, '<button type="submit"' ) );
    }
}
