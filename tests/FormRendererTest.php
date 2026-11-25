<?php
/** Tests accessible form rendering. */
use JayPee\FormBuilder\Condition;
use JayPee\FormBuilder\Conditional_Group;
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

    public function test_country_renders_a_select_of_countries_with_the_default_selected(): void {
        $form = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Field_Definition( 'country', 'land', [ 'label' => 'Land', 'required' => true, 'defaultCountry' => 'AT', 'helpText' => 'Where you live' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form, 1 );

        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--country', $markup );
        self::assertStringContainsString( '<label for="jpfb-12-1-land">Land <span class="jp-form-builder__required"', $markup );
        self::assertStringContainsString( '<select id="jpfb-12-1-land" name="land" required autocomplete="country" aria-describedby="jpfb-12-1-land-help jpfb-12-1-land-error">', $markup );
        self::assertSame( 32, substr_count( $markup, '<option value="' ) );
        self::assertMatchesRegularExpression( '~<option value="AT" selected=.selected.>Austria</option>~', $markup );
        self::assertStringNotContainsString( '<option value="">', $markup );
    }

    public function test_country_without_a_default_offers_a_placeholder_option(): void {
        $form = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Field_Definition( 'country', 'land', [ 'label' => 'Land', 'defaultCountry' => '' ] ),
            new Field_Definition( 'country', 'other', [ 'label' => 'Other' ] ),
        ] );
        $markup = ( new Form_Renderer() )->render( $form, 1 );

        self::assertSame( 1, substr_count( $markup, '<option value="">Please choose…</option>' ) );
        self::assertSame( 1, preg_match_all( '~<option value="DE" selected=.selected.>~', $markup ) );
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

    public function test_radio_and_checkboxes_render_as_a_fieldset_with_one_labelled_input_per_option(): void {
        $options = [ [ 'label' => 'Red <b>', 'value' => 'red' ], [ 'label' => '', 'value' => 'green' ] ];
        $form    = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Field_Definition( 'radio', 'colour', [ 'label' => 'Colour', 'required' => true, 'helpText' => 'Pick one', 'width' => '50', 'options' => $options ] ),
            new Field_Definition( 'checkbox', 'extras', [ 'label' => 'Extras', 'required' => true, 'options' => $options ] ),
            new Field_Definition( 'checkbox', 'optional', [ 'label' => 'Optional', 'options' => $options ] ),
        ] );

        $markup = ( new Form_Renderer() )->render( $form, 1 );

        self::assertStringContainsString( 'class="jp-form-builder__field jp-form-builder__field--radio jp-form-builder__field--width-50" data-field-key="colour"', $markup );
        self::assertStringContainsString( '<fieldset aria-describedby="jpfb-12-1-colour-help jpfb-12-1-colour-error"><legend>Colour <span class="jp-form-builder__required"', $markup );
        self::assertStringContainsString( '<label for="jpfb-12-1-colour-1"><input type="radio" id="jpfb-12-1-colour-1" name="colour" value="red" required> Red &lt;b&gt;</label>', $markup );
        self::assertStringContainsString( '<label for="jpfb-12-1-colour-2"><input type="radio" id="jpfb-12-1-colour-2" name="colour" value="green" required> green</label>', $markup );
        self::assertStringContainsString( '<p id="jpfb-12-1-colour-help">Pick one</p>', $markup );
        self::assertStringContainsString( '<div id="jpfb-12-1-colour-error" class="jp-form-builder__error"></div>', $markup );

        self::assertStringContainsString( '<fieldset aria-describedby="jpfb-12-1-extras-error" data-required="true"><legend>Extras', $markup );
        self::assertStringContainsString( '<label for="jpfb-12-1-extras-1"><input type="checkbox" id="jpfb-12-1-extras-1" name="extras[]" value="red"> Red &lt;b&gt;</label>', $markup );
        self::assertStringContainsString( '<input type="checkbox" id="jpfb-12-1-extras-2" name="extras[]" value="green"> green</label>', $markup );
        self::assertStringContainsString( '<fieldset aria-describedby="jpfb-12-1-optional-error"><legend>Optional</legend>', $markup );
    }

    public function test_a_group_wraps_its_own_grid_and_carries_its_condition(): void {
        $condition = new Condition( 'topic', 'is_one_of', [ 'a"b', 'c' ] );
        $form      = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Field_Definition( 'select', 'topic', [ 'label' => 'Topic', 'options' => [] ] ),
            new Conditional_Group( $condition, [
                [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>More details</p>' ],
                new Field_Definition( 'text', 'budget', [ 'label' => 'Budget', 'width' => '50' ] ),
            ] ),
        ] );

        $markup = ( new Form_Renderer() )->render( $form, 1 );

        self::assertStringContainsString( '<div class="jp-form-builder__group" data-condition="{&quot;sourceField&quot;:&quot;topic&quot;,&quot;operator&quot;:&quot;is_one_of&quot;,&quot;values&quot;:[&quot;a\&quot;b&quot;,&quot;c&quot;]}" hidden><div class="jp-form-builder__fields"><p>More details</p><div class="jp-form-builder__field jp-form-builder__field--text jp-form-builder__field--width-50" data-field-key="budget">', $markup );
        self::assertSame( 2, substr_count( $markup, 'class="jp-form-builder__fields"' ), 'The Form and the group each have a grid.' );
    }

    public function test_a_group_is_visible_for_an_empty_form_when_its_condition_holds_then(): void {
        $field = new Field_Definition( 'text', 'budget', [ 'label' => 'Budget' ] );
        $form  = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Conditional_Group( new Condition( 'topic', 'is_empty', [] ), [ $field ] ),
            new Conditional_Group( new Condition( 'topic', 'is_not', [ 'a' ] ), [ $field ] ),
            new Conditional_Group( new Condition( 'topic', 'is_not_checked', [] ), [ $field ] ),
            new Conditional_Group( new Condition( 'topic', 'is_filled', [] ), [ $field ] ),
        ] );

        $markup = ( new Form_Renderer() )->render( $form, 1 );

        preg_match_all( '/<div class="jp-form-builder__group"[^>]*>/', $markup, $tags );
        self::assertCount( 4, $tags[0] );
        self::assertSame( [ false, false, false, true ], array_map( static fn( string $tag ): bool => str_ends_with( $tag, ' hidden>' ), $tags[0] ) );
    }

    public function test_a_group_with_an_invalid_condition_is_plain_and_visible(): void {
        $form = new Form_Definition( 12, 'Contact', '1234567890abcdef', [], [
            new Conditional_Group( new Condition( 'nope', 'is', [ 'a' ], false ), [ new Field_Definition( 'text', 'budget', [ 'label' => 'Budget' ] ) ] ),
        ] );

        $markup = ( new Form_Renderer() )->render( $form, 1 );

        self::assertStringContainsString( '<div class="jp-form-builder__group"><div class="jp-form-builder__fields">', $markup );
        self::assertStringNotContainsString( 'data-condition', $markup );
        self::assertStringNotContainsString( ' hidden', $markup );
    }
}
