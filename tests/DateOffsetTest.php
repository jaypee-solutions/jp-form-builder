<?php
/**
 * Unit tests for Date_Offset and Date_Format. tests/js/date-offset.test.mjs checks the same fixtures.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class DateOffsetTest extends TestCase {

    /** @return array<string,array{string,string,?string}> The rows of tests/fixtures/date-offsets.json. */
    public function offsets(): array {
        $cases = [];
        foreach ( (array) json_decode( (string) file_get_contents( __DIR__ . '/fixtures/date-offsets.json' ), true ) as $case ) {
            $cases[ $case['today'] . ' ' . wp_json_encode( $case['expression'] ) ] = [ $case['today'], $case['expression'], $case['resolved'] ];
        }
        return $cases;
    }

    /** @dataProvider offsets */
    public function test_resolves_expressions_like_the_browser( string $today, string $expression, ?string $expected ): void {
        $this->assertSame( $expected, Date_Offset::resolve( $expression, new \DateTimeImmutable( $today . ' 23:59:59', new \DateTimeZone( 'UTC' ) ) ) );
    }

    public function test_is_valid_iso_accepts_only_real_dates_in_the_supported_years(): void {
        $this->assertTrue( Date_Offset::is_valid_iso( '2024-02-29' ) );
        $this->assertTrue( Date_Offset::is_valid_iso( '1900-01-01' ) );
        $this->assertTrue( Date_Offset::is_valid_iso( '2100-12-31' ) );
        foreach ( [ '1900-02-29', '2023-02-29', '2026-04-31', '1899-12-31', '2101-01-01', '2026-1-1', "2026-01-01\n", '2026-01-01 ', '' ] as $bad ) {
            $this->assertFalse( Date_Offset::is_valid_iso( $bad ), wp_json_encode( $bad ) );
        }
    }

    public function test_german_format_pads_on_output_and_accepts_unpadded_input(): void {
        $de = Date_Format::for_locale( 'de_DE_formal' );

        $this->assertSame( 'TT.MM.JJJJ', $de->placeholder() );
        $this->assertSame( 'dmy', $de->name() );
        $this->assertSame( '05.03.2026', $de->format( '2026-03-05' ) );
        $this->assertSame( '2026-03-05', $de->parse( '5.3.2026' ) );
        $this->assertSame( '2026-03-05', $de->parse( '05.03.2026' ) );
        foreach ( [ '2026-03-05', '5.3.26', '31.04.2026', '1.1.1899', '05.03.2026.', '' ] as $bad ) {
            $this->assertNull( $de->parse( $bad ), $bad );
        }
    }

    public function test_every_other_locale_uses_iso(): void {
        foreach ( [ 'en_US', 'fr_FR', '' ] as $locale ) {
            $iso = Date_Format::for_locale( $locale );
            $this->assertSame( 'YYYY-MM-DD', $iso->placeholder() );
            $this->assertSame( 'iso', $iso->name() );
            $this->assertSame( '2026-03-05', $iso->format( '2026-03-05' ) );
            $this->assertSame( '2026-03-05', $iso->parse( '2026-03-05' ) );
            $this->assertNull( $iso->parse( '5.3.2026' ) );
            $this->assertNull( $iso->parse( '2026-3-5' ) );
        }
    }
}
