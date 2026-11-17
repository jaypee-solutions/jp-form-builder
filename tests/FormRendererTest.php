<?php
/** Tests accessible form rendering. */
use JayPee\FormBuilder\Field_Definition;
use JayPee\FormBuilder\Field_Validator;
use JayPee\FormBuilder\Form_Definition;
use JayPee\FormBuilder\Form_Renderer;
use PHPUnit\Framework\TestCase;
final class FormRendererTest extends TestCase {
    public function test_markup_escapes_labels_and_exposes_accessible_controls(): void {
        $form = new Form_Definition( 12, 'Contact', '1234567890abcdef', [ 'submitLabel' => 'Send' ], [
            new Field_Definition( 'text', 'company', [ 'label' => '<script>bad</script>', 'required' => true, 'maxLength' => 40 ] ),
            new Field_Definition( 'name', 'person', [ 'label' => 'Name', 'required' => true, 'split' => true ], [ 'first', 'last' ] ),
            new Field_Definition( 'name', 'single', [ 'label' => 'Name', 'required' => true ] ),
            new Field_Definition( 'email', 'email', [ 'label' => 'Email', 'required' => true ] ),
            new Field_Definition( 'phone', 'phone', [ 'label' => 'Phone', 'required' => false ] ),
            new Field_Definition( 'address', 'home', [ 'label' => 'Address', 'required' => true ], [ 'street', 'postcode', 'city', 'country' ] ),
            new Field_Definition( 'consent', 'privacy', [ 'required' => false ] ),
            new Field_Definition( 'textarea', 'message', [ 'label' => 'Message', 'helpText' => 'How can we help?' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form );
        self::assertStringContainsString( '<form method="post"', $markup );
        self::assertStringContainsString( 'data-rev="1234567890abcdef"', $markup );
        self::assertStringContainsString( 'data-locale="en_US"', $markup );
        self::assertStringNotContainsString( 'data-turnstile-sitekey', $markup );
        self::assertStringNotContainsString( 'jp-form-builder__turnstile', $markup );
        self::assertStringContainsString( 'data-rest-root="https://example.test/wp-json/jp-form-builder/v1/"', $markup );
        self::assertStringContainsString( 'data-min-fill="0"', $markup );
        self::assertStringContainsString( '&lt;script&gt;bad&lt;/script&gt;', $markup );
        self::assertStringContainsString( 'id="jpfb-12-1-company"', $markup );
        self::assertStringContainsString( 'for="jpfb-12-1-company"', $markup );
        self::assertStringContainsString( 'fieldset><legend>Name <span class="jp-form-builder__required"', $markup );
        self::assertStringContainsString( 'autocomplete="given-name"', $markup );
        self::assertStringContainsString( 'autocomplete="family-name"', $markup );
        self::assertStringContainsString( 'autocomplete="name" maxlength="100"', $markup );
        self::assertStringContainsString( 'autocomplete="email"', $markup );
        self::assertStringContainsString( 'autocomplete="tel"', $markup );
        self::assertStringContainsString( 'autocomplete="street-address"', $markup );
        self::assertStringContainsString( 'autocomplete="postal-code"', $markup );
        self::assertStringContainsString( 'autocomplete="address-level2"', $markup );
        self::assertStringContainsString( 'autocomplete="country"', $markup );
        self::assertStringContainsString( 'data-postcode-patterns=', $markup );
        self::assertStringNotContainsString( 'pattern="^\\d{5}$"', $markup );
        self::assertStringContainsString( 'aria-describedby="jpfb-12-1-message-help jpfb-12-1-message-error"', $markup );
        self::assertStringContainsString( '<p id="jpfb-12-1-message-help">How can we help?</p>', $markup );
        self::assertStringNotContainsString( 'jpfb-12-1-company-help', $markup );
        self::assertStringContainsString( 'I agree to the <a', $markup );
        self::assertStringContainsString( 'required', $markup );
        self::assertStringContainsString( 'maxlength="40"', $markup );
        self::assertStringContainsString( 'tabindex="-1" autocomplete="off"', $markup );
        self::assertStringContainsString( '<noscript>', $markup );
        self::assertStringContainsString( 'class="wp-element-button" disabled', $markup );
    }

    public function test_maxlength_attributes_come_from_the_validator_limits(): void {
        $form   = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Field_Definition( 'name', 'person', [ 'label' => 'Name', 'split' => true ], [ 'first', 'last' ] ),
            new Field_Definition( 'email', 'email', [ 'label' => 'Email' ] ),
            new Field_Definition( 'phone', 'phone', [ 'label' => 'Phone' ] ),
            new Field_Definition( 'text', 'company', [ 'label' => 'Company' ] ),
            new Field_Definition( 'textarea', 'message', [ 'label' => 'Message' ] ),
            new Field_Definition( 'address', 'home', [ 'label' => 'Address' ], [ 'street', 'postcode', 'city', 'country' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form );
        foreach ( [ 'first', 'last' ] as $part ) {
            self::assertStringContainsString( 'name="person[' . $part . ']" maxlength="' . Field_Validator::MAX_NAME_LENGTH . '"', $markup );
        }
        self::assertStringContainsString( 'name="email" maxlength="' . Field_Validator::MAX_EMAIL_LENGTH . '"', str_replace( ' autocomplete="email"', '', $markup ) );
        self::assertStringContainsString( 'name="company" maxlength="' . Field_Validator::DEFAULT_TEXT_LENGTH . '"', $markup );
        self::assertStringContainsString( 'maxlength="' . Field_Validator::DEFAULT_TEXTAREA_LENGTH . '"', $markup );
        foreach ( [ 'street' => Field_Validator::MAX_STREET_LENGTH, 'postcode' => Field_Validator::MAX_POSTCODE_LENGTH, 'city' => Field_Validator::MAX_CITY_LENGTH ] as $part => $limit ) {
            self::assertStringContainsString( 'name="home[' . $part . ']" maxlength="' . $limit . '"', str_replace( ' required', '', $markup ) );
        }
    }

    public function test_empty_label_uses_a_translated_default(): void {
        $form = new Form_Definition( 12, 'Kontakt', '1234567890abcdef', [], [
            new Field_Definition( 'email', 'email_1', [ 'label' => '' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form );
        self::assertStringContainsString( '>Email</label>', $markup );
    }

    public function test_script_markup_carries_the_min_fill_time_and_a_turnstile_container_only_with_a_site_key(): void {
        $form = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [ new Field_Definition( 'email', 'email', [ 'label' => 'Email' ] ) ] );

        $configured = ( new Form_Renderer() )->render( $form, 1, 'site-key', 3 );
        self::assertStringContainsString( 'data-turnstile-sitekey="site-key"', $configured );
        self::assertStringContainsString( 'data-min-fill="3"', $configured );
        self::assertSame( 1, substr_count( $configured, 'class="jp-form-builder__turnstile"' ) );

        $plain = ( new Form_Renderer() )->render( $form, 1 );
        self::assertStringNotContainsString( 'jp-form-builder__turnstile', $plain );
    }
}
