<?php
/**
 * Unit tests for the shared Field label resolver.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class FieldLabelsTest extends TestCase {

    public function test_field_label_falls_back_to_the_type_default(): void {
        $this->assertSame( 'Work email', Field_Labels::for_field( new Field_Definition( 'email', 'email_1', [ 'label' => '  Work email ' ] ) ) );
        $this->assertSame( 'Email', Field_Labels::for_field( new Field_Definition( 'email', 'email_1', [ 'label' => '' ] ) ) );
        $this->assertSame( 'Message', Field_Labels::for_field( new Field_Definition( 'textarea', 'textarea_1', [] ) ) );
        $this->assertSame( 'Consent', Field_Labels::for_field( new Field_Definition( 'consent', 'consent_1', [] ) ) );
    }

    public function test_part_labels_use_overrides_for_names_and_fixed_text_for_addresses(): void {
        $name = new Field_Definition( 'name', 'name_1', [ 'firstLabel' => 'Given name' ], [ 'first', 'last' ] );
        $this->assertSame( 'Given name', Field_Labels::for_part( $name, 'first' ) );
        $this->assertSame( 'Last name', Field_Labels::for_part( $name, 'last' ) );
        $address = new Field_Definition( 'address', 'address_1', [], [ 'street', 'postcode', 'city', 'country' ] );
        $this->assertSame( 'Street and number', Field_Labels::for_part( $address, 'street' ) );
        $this->assertSame( 'Country', Field_Labels::for_part( $address, 'country' ) );
    }
}
