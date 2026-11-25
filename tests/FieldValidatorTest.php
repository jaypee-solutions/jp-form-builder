<?php
/**
 * Unit tests for Field_Validator.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class FieldValidatorTest extends TestCase {

    private Field_Validator $validator;

    protected function setUp(): void {
        $this->validator = new Field_Validator();
    }

    /**
     * @param array<string,mixed> $attributes Field attributes.
     */
    private function field( string $type, string $key, array $attributes = [], array $subkeys = [] ): Field_Definition {
        return new Field_Definition( $type, $key, $attributes + [ 'label' => ucfirst( $key ) ], $subkeys );
    }

    /**
     * @param array<string,mixed> $submitted Submitted values.
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    private function validate_one( Field_Definition $field, array $submitted ): array {
        return $this->validator->validate( [ $field ], $submitted );
    }

    public function test_single_name_is_cleaned_and_limited(): void {
        $field = $this->field( 'name', 'name', [ 'required' => true ] );

        $this->assertSame( [ 'name' => 'Jane Doe' ], $this->validate_one( $field, [ 'name' => "  Jane\x00 Doe " ] )['values'] );
        $this->assertSame( [ 'name' => 'Name is required.' ], $this->validate_one( $field, [ 'name' => '   ' ] )['errors'] );
        $this->assertSame( [ 'name' => 'Name is required.' ], $this->validate_one( $field, [] )['errors'] );
        $this->assertSame( [], $this->validate_one( $field, [ 'name' => str_repeat( 'ä', 100 ) ] )['errors'] );
        $this->assertSame(
            [ 'name' => 'Name must not be longer than 100 characters.' ],
            $this->validate_one( $field, [ 'name' => str_repeat( 'ä', 101 ) ] )['errors']
        );
    }

    public function test_split_name_reports_missing_parts_only(): void {
        $field = $this->field( 'name', 'person', [ 'required' => false, 'lastLabel' => 'Surname' ], [ 'first', 'last' ] );

        $this->assertSame( [], $this->validate_one( $field, [ 'person' => [ 'first' => '', 'last' => '' ] ] )['errors'] );
        $this->assertSame( [ 'person' => [ 'first' => '', 'last' => '' ] ], $this->validate_one( $field, [] )['values'] );
        $this->assertSame(
            [ 'person.last' => 'Surname is required.' ],
            $this->validate_one( $field, [ 'person' => [ 'first' => 'Jane' ] ] )['errors']
        );
        $this->assertSame(
            [ 'person' => [ 'first' => 'Jane', 'last' => 'Doe' ] ],
            $this->validate_one( $field, [ 'person' => [ 'first' => 'Jane', 'last' => 'Doe' ] ] )['values']
        );
        $this->assertSame(
            [ 'person.first' => 'First name must not be longer than 100 characters.' ],
            $this->validate_one( $field, [ 'person' => [ 'first' => str_repeat( 'a', 101 ), 'last' => 'Doe' ] ] )['errors']
        );
    }

    public function test_required_split_name_needs_both_parts(): void {
        $field = $this->field( 'name', 'person', [ 'required' => true ], [ 'first', 'last' ] );

        $this->assertSame(
            [
                'person.first' => 'First name is required.',
                'person.last'  => 'Last name is required.',
            ],
            $this->validate_one( $field, [ 'person' => 'not a map' ] )['errors']
        );
    }

    public function test_email(): void {
        $field = $this->field( 'email', 'email', [ 'required' => true ] );

        $this->assertSame( [ 'email' => 'jane@example.test' ], $this->validate_one( $field, [ 'email' => ' jane@example.test ' ] )['values'] );
        $this->assertSame( [ 'email' => 'Email is required.' ], $this->validate_one( $field, [ 'email' => '' ] )['errors'] );
        foreach ( [ 'jane', 'jane@', '@example.test', 'jane@example', 'jane doe@example.test', "jane@example.test\r\nBcc: evil@example.test" ] as $invalid ) {
            $this->assertSame(
                [ 'email' => 'Please enter a valid email address.' ],
                $this->validate_one( $field, [ 'email' => $invalid ] )['errors'],
                $invalid
            );
        }
        $too_long = str_repeat( 'a', 64 ) . '@' . str_repeat( 'b', 63 ) . '.' . str_repeat( 'c', 63 ) . '.' . str_repeat( 'd', 63 ) . '.de';
        $this->assertGreaterThan( 254, strlen( $too_long ) );
        $this->assertArrayHasKey( 'email', $this->validate_one( $field, [ 'email' => $too_long ] )['errors'] );
    }

    public function test_email_with_idn_domain_is_converted_to_ascii(): void {
        if ( ! function_exists( 'idn_to_ascii' ) ) {
            $this->markTestSkipped( 'ext-intl is not available.' );
        }
        $field = $this->field( 'email', 'email' );

        $this->assertSame( [ 'email' => 'jane@xn--mnchen-3ya.de' ], $this->validate_one( $field, [ 'email' => 'jane@münchen.de' ] )['values'] );
        $this->assertArrayHasKey( 'email', $this->validate_one( $field, [ 'email' => 'jäne@münchen.de' ] )['errors'] );
    }

    public function test_optional_empty_fields_are_accepted(): void {
        $fields = [
            $this->field( 'email', 'email' ),
            $this->field( 'phone', 'phone' ),
            $this->field( 'select', 'topic', [ 'options' => [ [ 'label' => 'A', 'value' => 'a' ] ] ] ),
            $this->field( 'textarea', 'message' ),
            $this->field( 'text', 'subject' ),
            $this->field( 'consent', 'consent', [ 'required' => false ] ),
            $this->field( 'postcode', 'zip' ),
            $this->field( 'country', 'land', [ 'defaultCountry' => '' ] ),
        ];

        $result = $this->validator->validate( $fields, [] );

        $this->assertSame( [], $result['errors'] );
        $this->assertSame( '', $result['values']['email'] );
        $this->assertSame( '', $result['values']['phone'] );
        $this->assertSame( '', $result['values']['zip'] );
        $this->assertSame( '', $result['values']['land'] );
    }

    public function test_phone_uses_the_default_region(): void {
        $field = $this->field( 'phone', 'phone', [ 'required' => true, 'defaultRegion' => 'AT' ] );

        $this->assertSame( [ 'phone' => '+431234567' ], $this->validate_one( $field, [ 'phone' => '01 234567' ] )['values'] );
        $this->assertSame( [ 'phone' => 'Please enter a valid phone number.' ], $this->validate_one( $field, [ 'phone' => 'abc' ] )['errors'] );
        $this->assertSame( [ 'phone' => 'Phone is required.' ], $this->validate_one( $field, [ 'phone' => '' ] )['errors'] );
    }

    public function test_country_accepts_a_known_code_only(): void {
        $field = $this->field( 'country', 'land', [ 'required' => true ] );

        $this->assertSame( [ 'land' => 'AT' ], $this->validate_one( $field, [ 'land' => ' AT ' ] )['values'] );
        $this->assertSame( [ 'land' => 'Please choose a valid country.' ], $this->validate_one( $field, [ 'land' => 'US' ] )['errors'] );
        $this->assertSame( [ 'land' => 'Please choose a valid country.' ], $this->validate_one( $field, [ 'land' => 'at' ] )['errors'] );
        $this->assertSame( [ 'land' => 'Land is required.' ], $this->validate_one( $field, [ 'land' => '' ] )['errors'] );
        $this->assertSame( [ 'land' => 'Land is required.' ], $this->validate_one( $field, [] )['errors'] );
    }

    public function test_optional_country_may_stay_empty(): void {
        $field = $this->field( 'country', 'land', [ 'required' => false ] );

        $this->assertSame( [ 'land' => '' ], $this->validate_one( $field, [ 'land' => '' ] )['values'] );
        $this->assertSame( [], $this->validate_one( $field, [] )['errors'] );
        $this->assertSame( [ 'land' => 'Please choose a valid country.' ], $this->validate_one( $field, [ 'land' => 'XX' ] )['errors'] );
    }

    /** @return array<string,array{string,string,bool}> The cases of tests/fixtures/postcodes.json, which tests/js/postcode.test.mjs also uses. */
    public function postcode_cases(): array {
        $cases = [];
        foreach ( (array) json_decode( (string) file_get_contents( __DIR__ . '/fixtures/postcodes.json' ), true )['cases'] as $case ) {
            $cases[ $case[0] . ' ' . $case[1] ] = $case;
        }
        return $cases;
    }

    public function test_the_postcode_fixture_lists_the_pattern_of_every_country(): void {
        $patterns = (array) json_decode( (string) file_get_contents( __DIR__ . '/fixtures/postcodes.json' ), true )['patterns'];

        $this->assertSame( array_keys( Countries::all() ), array_keys( $patterns ) );
        foreach ( $patterns as $code => $pattern ) {
            $this->assertSame( Countries::postcode_pattern( $code ), $pattern, $code );
        }
    }

    /** @dataProvider postcode_cases */
    public function test_postcode_follows_the_pattern_of_its_country( string $country, string $postcode, bool $valid ): void {
        $form   = $this->form(
            [
                $this->field( 'postcode', 'plz' ),
                $this->field( 'country', 'land', [ 'required' => false ] ),
            ]
        );
        $result = $this->validator->validate_form( $form, [ 'plz' => $postcode, 'land' => $country ] );

        if ( ! isset( Countries::all()[ $country ] ) && '' !== $country ) {
            $this->assertSame( [ 'land' => 'Please choose a valid country.' ], $result['errors'], 'An invalid Country skips the Postcode pattern.' );
            return;
        }
        $this->assertSame( $valid ? [] : [ 'plz' => 'Please enter a valid postcode.' ], $result['errors'] );
    }

    public function test_postcode_without_a_country_field_uses_the_german_rule(): void {
        $field = $this->field( 'postcode', 'plz', [ 'required' => true ] );

        $this->assertSame( [ 'plz' => '86150' ], $this->validate_one( $field, [ 'plz' => ' 86150 ' ] )['values'] );
        $this->assertSame( [ 'plz' => 'Please enter a valid postcode.' ], $this->validate_one( $field, [ 'plz' => '8615' ] )['errors'] );
        $this->assertSame( [ 'plz' => 'Plz is required.' ], $this->validate_one( $field, [ 'plz' => '' ] )['errors'] );
        $this->assertSame( [], $this->validator->validate_form( $this->form( [ $field ] ), [ 'plz' => '86150' ] )['errors'] );
        $this->assertSame( [ 'plz' => 'Please enter a valid postcode.' ], $this->validator->validate_form( $this->form( [ $field ] ), [ 'plz' => 'AB1 2CD' ] )['errors'] );
    }

    public function test_optional_empty_postcode_is_accepted(): void {
        $result = $this->validate_one( $this->field( 'postcode', 'plz' ), [] );

        $this->assertSame( [], $result['errors'] );
        $this->assertSame( [ 'plz' => '' ], $result['values'] );
    }

    /**
     * @param array<int,Field_Definition|Conditional_Group> $items
     * @param array<string,mixed>                           $submitted
     * @return string[] Errors of the Form keyed by Field key.
     */
    private function postcode_errors( array $items, array $submitted ): array {
        return $this->validator->validate_form( $this->form( $items ), $submitted )['errors'];
    }

    public function test_postcode_follows_a_preceding_country(): void {
        $items = [ $this->field( 'country', 'land' ), $this->field( 'postcode', 'plz' ) ];

        $this->assertSame( [], $this->postcode_errors( $items, [ 'land' => 'AT', 'plz' => '1010' ] ) );
        $this->assertSame( [ 'plz' => 'Please enter a valid postcode.' ], $this->postcode_errors( $items, [ 'land' => 'AT', 'plz' => '86150' ] ) );
    }

    public function test_postcode_follows_a_following_country(): void {
        $items = [ $this->field( 'postcode', 'plz' ), $this->field( 'text', 'city' ), $this->field( 'country', 'land' ) ];

        $this->assertSame( [], $this->postcode_errors( $items, [ 'land' => 'CH', 'plz' => '8001' ] ) );
        $this->assertSame( [ 'plz' => 'Please enter a valid postcode.' ], $this->postcode_errors( $items, [ 'land' => 'CH', 'plz' => '86150' ] ) );
    }

    public function test_postcode_follows_the_nearest_of_two_countries(): void {
        $items = [
            $this->field( 'country', 'bill_land' ),
            $this->field( 'postcode', 'bill_plz' ),
            $this->field( 'country', 'ship_land' ),
            $this->field( 'postcode', 'ship_plz' ),
        ];
        $submitted = [ 'bill_land' => 'DE', 'bill_plz' => '86150', 'ship_land' => 'AT', 'ship_plz' => '1010' ];

        $this->assertSame( [], $this->postcode_errors( $items, $submitted ) );
        $this->assertSame(
            [ 'bill_plz' => 'Please enter a valid postcode.', 'ship_plz' => 'Please enter a valid postcode.' ],
            $this->postcode_errors( $items, [ 'bill_plz' => '1010', 'ship_plz' => '86150' ] + $submitted )
        );
    }

    public function test_a_hidden_country_counts_as_absent(): void {
        $items = [
            $this->field( 'radio', 'topic', [ 'options' => [ [ 'label' => 'Abroad', 'value' => 'abroad' ], [ 'label' => 'Home', 'value' => 'home' ] ] ] ),
            $this->field( 'postcode', 'plz' ),
            $this->trigger_group( 'abroad', $this->field( 'country', 'land' ) ),
        ];

        $this->assertSame( [], $this->postcode_errors( $items, [ 'topic' => 'abroad', 'land' => 'AT', 'plz' => '1010' ] ), 'A shown Country applies.' );
        $this->assertSame( [], $this->postcode_errors( $items, [ 'topic' => 'home', 'land' => 'AT', 'plz' => '86150' ] ), 'A hidden Country falls back to Germany.' );
        $this->assertSame( [ 'plz' => 'Please enter a valid postcode.' ], $this->postcode_errors( $items, [ 'topic' => 'home', 'land' => 'AT', 'plz' => '1010' ] ) );
    }

    public function test_an_invalid_country_skips_the_pattern_and_reports_its_own_error(): void {
        $items = [ $this->field( 'postcode', 'plz' ), $this->field( 'country', 'land' ) ];

        $this->assertSame( [ 'land' => 'Please choose a valid country.' ], $this->postcode_errors( $items, [ 'land' => 'US', 'plz' => '12345-6789' ] ) );
        $this->assertSame(
            [ 'land' => 'Please choose a valid country.', 'plz' => 'Plz must not be longer than 10 characters.' ],
            $this->postcode_errors( $items, [ 'land' => 'US', 'plz' => '12345678901' ] )
        );
    }

    public function test_an_empty_country_counts_as_absent(): void {
        $items = [ $this->field( 'postcode', 'plz' ), $this->field( 'country', 'land' ) ];

        $this->assertSame( [], $this->postcode_errors( $items, [ 'land' => '', 'plz' => '86150' ] ) );
        $this->assertSame( [ 'plz' => 'Please enter a valid postcode.' ], $this->postcode_errors( $items, [ 'land' => '', 'plz' => '1010' ] ) );

        $second = [ $this->field( 'postcode', 'plz' ), $this->field( 'country', 'empty_land' ), $this->field( 'country', 'land' ) ];
        $this->assertSame( [], $this->postcode_errors( $second, [ 'empty_land' => '', 'land' => 'AT', 'plz' => '1010' ] ), 'The next Country takes over.' );
    }

    public function test_a_postcode_in_a_hidden_group_is_not_checked_and_a_condition_sees_a_valid_one(): void {
        $items = [
            $this->field( 'country', 'land' ),
            $this->field( 'postcode', 'plz' ),
            new Conditional_Group( new Condition( 'plz', 'is_filled', [] ), [ $this->field( 'text', 'note', [ 'required' => true ] ) ] ),
        ];

        $filled = $this->validator->validate_form( $this->form( $items ), [ 'land' => 'AT', 'plz' => '1010' ] );
        $this->assertSame( [ 'note' => 'Note is required.' ], $filled['errors'] );
        $this->assertSame( '1010', $filled['values']['plz'] );
    }

    public function test_select_accepts_only_defined_option_values(): void {
        $field = $this->field(
            'select',
            'topic',
            [
                'required' => true,
                'options'  => [
                    [ 'label' => 'Sales', 'value' => 'sales' ],
                    [ 'label' => 'Support', 'value' => 'support' ],
                ],
            ]
        );

        $this->assertSame( [ 'topic' => 'support' ], $this->validate_one( $field, [ 'topic' => 'support' ] )['values'] );
        $this->assertSame( [ 'topic' => 'Please choose one of the available options.' ], $this->validate_one( $field, [ 'topic' => 'Support' ] )['errors'] );
        $this->assertSame( [ 'topic' => 'Topic is required.' ], $this->validate_one( $field, [ 'topic' => '' ] )['errors'] );
        $this->assertArrayHasKey( 'topic', $this->validate_one( $field, [ 'topic' => [ 'support' ] ] )['errors'] );
    }

    public function test_text_is_single_line_and_limited(): void {
        $field = $this->field( 'text', 'subject', [ 'maxLength' => 10 ] );

        $this->assertSame( [ 'subject' => 'Hello' ], $this->validate_one( $field, [ 'subject' => "Hel\nlo" ] )['values'] );
        $this->assertSame( [], $this->validate_one( $field, [ 'subject' => '0123456789' ] )['errors'] );
        $this->assertSame(
            [ 'subject' => 'Subject must not be longer than 10 characters.' ],
            $this->validate_one( $field, [ 'subject' => '01234567890' ] )['errors']
        );
    }

    public function test_textarea_keeps_newlines_and_limits_length(): void {
        $field = $this->field( 'textarea', 'message', [ 'required' => true, 'maxLength' => 12 ] );

        $this->assertSame( [ 'message' => "Hello\nWorld" ], $this->validate_one( $field, [ 'message' => "Hello\r\nWorld" ] )['values'] );
        $this->assertSame(
            [ 'message' => 'Message must not be longer than 12 characters.' ],
            $this->validate_one( $field, [ 'message' => "Hello\nWorld, you" ] )['errors']
        );
        $this->assertSame( [ 'message' => 'Message is required.' ], $this->validate_one( $field, [ 'message' => " \n " ] )['errors'] );
    }

    public function test_consent_must_be_exactly_one(): void {
        $field = $this->field( 'consent', 'consent', [ 'required' => true ] );

        $this->assertSame( [ 'consent' => '1' ], $this->validate_one( $field, [ 'consent' => '1' ] )['values'] );
        foreach ( [ '', '0', 'on', 'true', ' 1 ', 1, true ] as $invalid ) {
            $this->assertSame(
                [ 'consent' => 'Please accept to continue.' ],
                $this->validate_one( $field, [ 'consent' => $invalid ] )['errors'],
                gettype( $invalid ) . ':' . json_encode( $invalid )
            );
        }
    }

    public function test_unknown_keys_are_ignored_and_all_fields_are_validated(): void {
        $fields = [
            $this->field( 'email', 'email', [ 'required' => true ] ),
            $this->field( 'text', 'subject', [ 'required' => true ] ),
        ];

        $result = $this->validator->validate( $fields, [ 'email' => 'jane@example.test', 'extra' => 'x', 'subject' => '' ] );

        $this->assertSame( [ 'email' => 'jane@example.test' ], $result['values'] );
        $this->assertSame( [ 'subject' => 'Subject is required.' ], $result['errors'] );
    }

    public function test_label_falls_back_to_the_label_the_form_shows(): void {
        $field = new Field_Definition( 'email', 'contact', [ 'required' => true, 'label' => '' ] );
        $this->assertSame( [ 'contact' => 'Email is required.' ], $this->validate_one( $field, [] )['errors'] );

        $unknown = new Field_Definition( 'unknown', 'subject', [ 'required' => true, 'label' => '' ] );
        $this->assertSame( [ 'subject' => 'This field is required.' ], $this->validate_one( $unknown, [] )['errors'] );
    }

    public function test_invalid_utf8_counts_as_empty(): void {
        $field = $this->field( 'text', 'subject', [ 'required' => true ] );

        $this->assertSame( [ 'subject' => 'Subject is required.' ], $this->validate_one( $field, [ 'subject' => "\xFF\xFE" ] )['errors'] );
    }

    /** @return array<int,array{label:string,value:string}> */
    private function options(): array {
        return [
            [ 'label' => 'Red', 'value' => 'red' ],
            [ 'label' => 'Green', 'value' => 'green' ],
            [ 'label' => 'Blue', 'value' => 'blue' ],
        ];
    }

    public function test_radio_accepts_an_option_value_only(): void {
        $field = $this->field( 'radio', 'colour', [ 'required' => true, 'options' => $this->options() ] );

        $this->assertSame( [ 'colour' => 'green' ], $this->validate_one( $field, [ 'colour' => ' green ' ] )['values'] );
        $this->assertSame( [ 'colour' => 'Colour is required.' ], $this->validate_one( $field, [ 'colour' => '' ] )['errors'] );
        $this->assertSame( [ 'colour' => 'Colour is required.' ], $this->validate_one( $field, [] )['errors'] );
        foreach ( [ 'Green', 'yellow', 'Red' ] as $invalid ) {
            $this->assertSame(
                [ 'colour' => 'Please choose one of the available options.' ],
                $this->validate_one( $field, [ 'colour' => $invalid ] )['errors'],
                $invalid
            );
        }
        $this->assertSame(
            [ 'colour' => 'Colour is required.' ],
            $this->validate_one( $field, [ 'colour' => [ 'red' ] ] )['errors'],
            'A list is not a radio value.'
        );

        $optional = $this->field( 'radio', 'colour', [ 'options' => $this->options() ] );
        $this->assertSame( [ 'colour' => '' ], $this->validate_one( $optional, [] )['values'] );
    }

    public function test_checkbox_accepts_a_list_of_option_values_in_option_order(): void {
        $field = $this->field( 'checkbox', 'colours', [ 'options' => $this->options() ] );

        $this->assertSame( [ 'colours' => [ 'red', 'blue' ] ], $this->validate_one( $field, [ 'colours' => [ 'blue', ' red ', 'blue', '' ] ] )['values'] );
        $this->assertSame( [ 'colours' => [ 'green' ] ], $this->validate_one( $field, [ 'colours' => 'green' ] )['values'], 'A single string counts as one choice.' );
        $this->assertSame( [ 'colours' => [] ], $this->validate_one( $field, [] )['values'] );
        $this->assertSame( [ 'colours' => [] ], $this->validate_one( $field, [ 'colours' => [] ] )['values'] );
    }

    public function test_checkbox_rejects_unknown_options_and_non_strings(): void {
        $field = $this->field( 'checkbox', 'colours', [ 'options' => $this->options() ] );

        foreach ( [ [ 'red', 'yellow' ], [ 'Red' ], [ [ 'red' ] ], [ 5 ] ] as $invalid ) {
            $result = $this->validate_one( $field, [ 'colours' => $invalid ] );
            $this->assertSame( [ 'colours' => 'Please choose only from the available options.' ], $result['errors'], (string) json_encode( $invalid ) );
            $this->assertSame( [], $result['values'] );
        }
    }

    public function test_required_checkbox_needs_at_least_one_choice(): void {
        $field = $this->field( 'checkbox', 'colours', [ 'required' => true, 'options' => $this->options() ] );

        foreach ( [ [], [ 'colours' => [] ], [ 'colours' => [ '', '  ' ] ], [ 'colours' => '' ] ] as $submitted ) {
            $this->assertSame( [ 'colours' => 'Colours is required.' ], $this->validate_one( $field, $submitted )['errors'], (string) json_encode( $submitted ) );
        }
        $this->assertSame( [], $this->validate_one( $field, [ 'colours' => [ 'green' ] ] )['errors'] );
    }

    /** @param array<int,Field_Definition|Conditional_Group> $items */
    private function form( array $items ): Form_Definition {
        return new Form_Definition( 1, 'Contact', 'rev', [], $items );
    }

    private function trigger_group( string $value, Field_Definition ...$fields ): Conditional_Group {
        return new Conditional_Group( new Condition( 'topic', 'is', [ $value ] ), $fields );
    }

    public function test_a_hidden_group_is_neither_required_nor_validated_and_leaves_no_values(): void {
        $form = $this->form(
            [
                $this->field( 'radio', 'topic', [ 'required' => true, 'options' => [ [ 'label' => 'Sales', 'value' => 'sales' ], [ 'label' => 'Help', 'value' => 'help' ] ] ] ),
                $this->trigger_group(
                    'sales',
                    $this->field( 'text', 'budget', [ 'required' => true ] ),
                    $this->field( 'email', 'contact', [ 'required' => true ] )
                ),
            ]
        );

        $result = $this->validator->validate_form( $form, [ 'topic' => 'help', 'budget' => '', 'contact' => 'not-an-email' ] );

        $this->assertSame( [], $result['errors'] );
        $this->assertSame( [ 'topic' => 'help' ], $result['values'] );

        $dropped = $this->validator->validate_form( $form, [ 'topic' => 'help', 'budget' => '5000', 'contact' => 'jane@example.test' ] );
        $this->assertSame( [ 'topic' => 'help' ], $dropped['values'], 'A value posted for a hidden Field is dropped.' );
    }

    public function test_a_field_inside_a_layout_block_of_a_hidden_group_is_neither_required_nor_kept(): void {
        $layout = new Layout_Block(
            [ 'blockName' => 'core/columns', 'attrs' => [], 'innerBlocks' => [], 'innerContent' => [ '<div>', null, '</div>' ] ],
            [ $this->field( 'text', 'code', [ 'required' => true ] ) ]
        );
        $form   = $this->form(
            [
                $this->field( 'radio', 'topic', [ 'options' => [ [ 'label' => 'Sales', 'value' => 'sales' ], [ 'label' => 'Help', 'value' => 'help' ] ] ] ),
                new Conditional_Group( new Condition( 'topic', 'is', [ 'sales' ] ), [ $layout ] ),
            ]
        );

        $hidden = $this->validator->validate_form( $form, [ 'topic' => 'help', 'code' => 'X1' ] );
        $this->assertSame( [], $hidden['errors'] );
        $this->assertSame( [ 'topic' => 'help' ], $hidden['values'] );

        $shown = $this->validator->validate_form( $form, [ 'topic' => 'sales', 'code' => '' ] );
        $this->assertArrayHasKey( 'code', $shown['errors'], 'Shown, the Field in the layout is required.' );
        $this->assertSame( [ 'code' ], ( new Condition_Evaluator() )->resolve( [ $form->groups()[0] ], [ 'topic' => 'help' ] )['hidden'] );
    }

    public function test_a_shown_group_is_validated_like_the_rest_of_the_form(): void {
        $form = $this->form(
            [
                $this->field( 'radio', 'topic', [ 'options' => [ [ 'label' => 'Sales', 'value' => 'sales' ] ] ] ),
                $this->trigger_group(
                    'sales',
                    $this->field( 'text', 'budget', [ 'required' => true ] ),
                    $this->field( 'email', 'contact' )
                ),
            ]
        );

        $result = $this->validator->validate_form( $form, [ 'topic' => 'sales', 'contact' => 'nope' ] );

        $this->assertSame( [ 'budget' => 'Budget is required.', 'contact' => 'Please enter a valid email address.' ], $result['errors'] );

        $valid = $this->validator->validate_form( $form, [ 'topic' => 'sales', 'budget' => '5000' ] );
        $this->assertSame( [], $valid['errors'] );
        $this->assertSame( [ 'topic' => 'sales', 'budget' => '5000', 'contact' => '' ], $valid['values'] );
    }

    public function test_conditions_look_at_cleaned_values_in_document_order(): void {
        $form = $this->form(
            [
                $this->field( 'text', 'topic' ),
                new Conditional_Group( new Condition( 'topic', 'is_filled', [] ), [ $this->field( 'text', 'budget' ) ] ),
                new Conditional_Group( new Condition( 'budget', 'is_filled', [] ), [ $this->field( 'text', 'details', [ 'required' => true ] ) ] ),
            ]
        );

        $blank = $this->validator->validate_form( $form, [ 'topic' => '   ', 'budget' => 'x', 'details' => '' ] );
        $this->assertSame( [], $blank['errors'], 'Whitespace is empty, so the chain stays hidden.' );
        $this->assertSame( [ 'topic' => '' ], $blank['values'] );

        $filled = $this->validator->validate_form( $form, [ 'topic' => 'a', 'budget' => 'x', 'details' => '' ] );
        $this->assertSame( [ 'details' => 'Details is required.' ], $filled['errors'] );
    }

    public function test_an_invalid_condition_leaves_the_group_required(): void {
        $form = $this->form(
            [ new Conditional_Group( new Condition( 'nope', 'is', [ 'x' ], false ), [ $this->field( 'text', 'budget', [ 'required' => true ] ) ] ) ]
        );

        $this->assertSame( [ 'budget' => 'Budget is required.' ], $this->validator->validate_form( $form, [] )['errors'] );
    }
}
