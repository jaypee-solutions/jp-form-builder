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

    private function address( bool $required = true ): Field_Definition {
        return $this->field( 'address', 'address', [ 'required' => $required ], [ 'street', 'postcode', 'city', 'country' ] );
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
            $this->address( false ),
        ];

        $result = $this->validator->validate( $fields, [] );

        $this->assertSame( [], $result['errors'] );
        $this->assertSame( '', $result['values']['email'] );
        $this->assertSame( '', $result['values']['phone'] );
        $this->assertSame( [ 'street' => '', 'postcode' => '', 'city' => '', 'country' => '' ], $result['values']['address'] );
    }

    public function test_phone_uses_the_default_region(): void {
        $field = $this->field( 'phone', 'phone', [ 'required' => true, 'defaultRegion' => 'AT' ] );

        $this->assertSame( [ 'phone' => '+431234567' ], $this->validate_one( $field, [ 'phone' => '01 234567' ] )['values'] );
        $this->assertSame( [ 'phone' => 'Please enter a valid phone number.' ], $this->validate_one( $field, [ 'phone' => 'abc' ] )['errors'] );
        $this->assertSame( [ 'phone' => 'Phone is required.' ], $this->validate_one( $field, [ 'phone' => '' ] )['errors'] );
    }

    public function test_address(): void {
        $field = $this->address();
        $valid = [ 'street' => 'Hauptstr. 1', 'postcode' => '86150', 'city' => 'Augsburg', 'country' => 'DE' ];

        $this->assertSame( [ 'address' => $valid ], $this->validate_one( $field, [ 'address' => $valid ] )['values'] );

        $this->assertSame(
            [ 'address.postcode' => 'Please enter a valid postcode.' ],
            $this->validate_one( $field, [ 'address' => [ 'postcode' => '8615' ] + $valid ] )['errors']
        );
        $this->assertSame(
            [],
            $this->validate_one( $field, [ 'address' => [ 'postcode' => '8001', 'country' => 'CH' ] + $valid ] )['errors']
        );
        $this->assertSame(
            [ 'address.postcode' => 'Please enter a valid postcode.' ],
            $this->validate_one( $field, [ 'address' => [ 'postcode' => '86150', 'country' => 'AT' ] + $valid ] )['errors']
        );
        $this->assertSame(
            [],
            $this->validate_one( $field, [ 'address' => [ 'postcode' => 'SW1A 1AA', 'country' => 'GB' ] + $valid ] )['errors']
        );
        $this->assertSame(
            [ 'address.country' => 'Please choose a valid country.' ],
            $this->validate_one( $field, [ 'address' => [ 'country' => 'US' ] + $valid ] )['errors']
        );
        $this->assertSame(
            [
                'address.street' => 'Street and number must not be longer than 200 characters.',
                'address.city'   => 'City must not be longer than 100 characters.',
            ],
            $this->validate_one( $field, [ 'address' => [ 'street' => str_repeat( 's', 201 ), 'city' => str_repeat( 'c', 101 ) ] + $valid ] )['errors']
        );
    }

    public function test_partial_address_reports_the_missing_parts(): void {
        foreach ( [ true, false ] as $required ) {
            $this->assertSame(
                [
                    'address.postcode' => 'Postcode is required.',
                    'address.city'     => 'City is required.',
                    'address.country'  => 'Country is required.',
                ],
                $this->validate_one( $this->address( $required ), [ 'address' => [ 'street' => 'Hauptstr. 1' ] ] )['errors']
            );
        }
        $this->assertCount( 4, $this->validate_one( $this->address(), [] )['errors'] );
    }

    public function test_optional_address_with_only_the_default_country_is_empty(): void {
        $result = $this->validate_one( $this->address( false ), [ 'address' => [ 'country' => 'DE' ] ] );

        $this->assertSame( [], $result['errors'] );
        $this->assertSame( '', $result['values']['address']['country'] );
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
