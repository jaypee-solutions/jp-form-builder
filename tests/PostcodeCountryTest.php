<?php
/**
 * Unit tests for Postcode_Country: which Country Field a Postcode follows.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class PostcodeCountryTest extends TestCase {

    /** @return Field_Definition[] One Field per `type:key` entry, in the order given. */
    private function fields( string ...$entries ): array {
        return array_map(
            static function ( string $entry ): Field_Definition {
                [ $type, $key ] = explode( ':', $entry );
                return new Field_Definition( $type, $key, [] );
            },
            $entries
        );
    }

    public function test_a_postcode_follows_the_nearest_preceding_country(): void {
        $fields = $this->fields( 'country:first', 'text:city', 'country:second', 'postcode:plz' );

        self::assertSame( [ 'plz' => 'second' ], Postcode_Country::sources( $fields ) );
    }

    public function test_a_postcode_follows_the_nearest_following_country_when_none_precedes(): void {
        $fields = $this->fields( 'postcode:plz', 'text:city', 'country:near', 'country:far' );

        self::assertSame( [ 'plz' => 'near' ], Postcode_Country::sources( $fields ) );
    }

    public function test_a_preceding_country_wins_over_a_nearer_following_one(): void {
        $fields = $this->fields( 'country:before', 'text:a', 'text:b', 'postcode:plz', 'country:after' );

        self::assertSame( [ 'plz' => 'before' ], Postcode_Country::sources( $fields ) );
    }

    public function test_side_by_side_blocks_each_get_their_own_country(): void {
        $fields = $this->fields( 'postcode:bill_plz', 'country:bill_country', 'postcode:ship_plz', 'country:ship_country' );

        self::assertSame( [ 'bill_plz' => 'bill_country', 'ship_plz' => 'bill_country' ], Postcode_Country::sources( $fields ) );
    }

    public function test_absent_countries_are_skipped(): void {
        $fields = $this->fields( 'country:before', 'postcode:plz', 'country:after' );

        self::assertSame( [ 'plz' => 'after' ], Postcode_Country::sources( $fields, [ 'before' => true ] ) );
        self::assertSame( [], Postcode_Country::sources( $fields, [ 'before' => true, 'after' => true ] ) );
    }

    public function test_a_form_without_a_country_binds_nothing(): void {
        self::assertSame( [], Postcode_Country::sources( $this->fields( 'postcode:plz', 'text:city' ) ) );
    }
}
