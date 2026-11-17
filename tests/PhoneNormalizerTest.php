<?php
/**
 * Unit tests for Phone_Normalizer.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase {

    /** @return array<string,array{string,string,?string}> The rows of tests/fixtures/phone-numbers.json, which the browser check in tests/js/phone.test.mjs also uses. */
    public function phone_numbers(): array {
        $cases = [];
        foreach ( (array) json_decode( (string) file_get_contents( __DIR__ . '/fixtures/phone-numbers.json' ), true ) as $case ) {
            $cases[ $case[0] . ' (' . $case[1] . ')' ] = $case;
        }
        return $cases;
    }

    /** @dataProvider phone_numbers */
    public function test_normalizes_to_e164( string $input, string $region, ?string $expected ): void {
        $this->assertSame( $expected, ( new Phone_Normalizer() )->normalize( $input, $region ) );
    }
}
