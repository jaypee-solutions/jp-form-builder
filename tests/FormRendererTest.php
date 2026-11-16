<?php
/** Tests accessible form rendering. */
use JayPee\FormBuilder\Field_Definition;
use JayPee\FormBuilder\Form_Definition;
use JayPee\FormBuilder\Form_Renderer;
use PHPUnit\Framework\TestCase;
final class FormRendererTest extends TestCase {
    public function test_markup_escapes_labels_and_exposes_accessible_controls(): void {
        $form = new Form_Definition( 12, 'Contact', '1234567890abcdef', [ 'submitLabel' => 'Send' ], [
            new Field_Definition( 'text', 'company', [ 'label' => '<script>bad</script>', 'required' => true, 'maxLength' => 40 ] ),
            new Field_Definition( 'name', 'person', [ 'label' => 'Name', 'required' => true, 'split' => true ], [ 'first', 'last' ] ),
            new Field_Definition( 'address', 'home', [ 'label' => 'Address', 'required' => true ], [ 'street', 'postcode', 'city', 'country' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form );
        self::assertStringContainsString( 'data-rev="1234567890abcdef"', $markup );
        self::assertStringContainsString( 'data-locale="en_US"', $markup );
        self::assertStringNotContainsString( 'data-turnstile-sitekey', $markup );
        self::assertStringContainsString( '&lt;script&gt;bad&lt;/script&gt;', $markup );
        self::assertStringContainsString( 'id="jpfb-12-1-company"', $markup );
        self::assertStringContainsString( 'for="jpfb-12-1-company"', $markup );
        self::assertStringContainsString( 'fieldset><legend>Name</legend>', $markup );
        self::assertStringContainsString( 'aria-describedby="jpfb-12-1-company-help jpfb-12-1-company-error"', $markup );
        self::assertStringContainsString( 'required', $markup );
        self::assertStringContainsString( 'maxlength="40"', $markup );
        self::assertStringContainsString( 'maxlength="200"', $markup );
        self::assertStringContainsString( 'pattern="^\\d{5}$"', $markup );
        self::assertStringContainsString( 'tabindex="-1" autocomplete="off"', $markup );
        self::assertStringContainsString( '<noscript>', $markup );
        self::assertStringContainsString( 'class="wp-element-button" disabled', $markup );
    }
}
