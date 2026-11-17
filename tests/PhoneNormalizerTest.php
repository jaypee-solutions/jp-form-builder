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

    /** @return array<string,array{string,string,?string}> */
    public function phone_numbers(): array {
        return [
            'DE national'               => [ '0821 123456', 'DE', '+49821123456' ],
            'DE with (0) and separators' => [ '+49 (0) 821 / 12 34-56', 'DE', '+49821123456' ],
            'DE 00 prefix'              => [ '0049 821 123456', 'DE', '+49821123456' ],
            'DE plus with trunk zero'   => [ '+49 0821 123456', 'DE', '+49821123456' ],
            'DE mobile'                 => [ '0171 1234567', 'DE', '+491711234567' ],
            'AT national'               => [ '01 234567', 'AT', '+431234567' ],
            'foreign number'            => [ '+1 202 555 0100', 'DE', '+12025550100' ],
            'foreign number region ignored' => [ '+1 202 555 0100', 'CH', '+12025550100' ],
            'dots and parentheses'      => [ '(0821) 123.456', 'DE', '+49821123456' ],
            'lowercase region'          => [ '0821 123456', 'de', '+49821123456' ],
            'CH national, 9 digits'     => [ '044 668 18 00', 'CH', '+41446681800' ],
            'CH international'          => [ '+41 44 668 18 00', 'DE', '+41446681800' ],
            'surrounding whitespace'    => [ '  0821 123456 ', 'DE', '+49821123456' ],
            'no prefix'                 => [ '12345', 'DE', null ],
            'letters'                   => [ 'abc', 'DE', null ],
            'empty'                     => [ '', 'DE', null ],
            'only plus'                 => [ '+', 'DE', null ],
            'too many digits'           => [ '+1234567890123456', 'DE', null ],
            'too few digits'            => [ '+49 123', 'DE', null ],
            'plus not at start'         => [ '49+821123456', 'DE', null ],
            'country code starts with 0' => [ '+0 821 123456', 'DE', null ],
            'double zero then zero'     => [ '000 821 123456', 'DE', null ],
            'disallowed letter'         => [ '0821 123456 ext', 'DE', null ],
            'disallowed hash'           => [ '0821 123456#', 'DE', null ],
            'unsupported region'        => [ '0821 123456', 'FR', null ],
            'AT too short'              => [ '+43 123', 'AT', null ],
            'AT shortest total length'  => [ '+43 123456', 'AT', '+43123456' ],
            'AT total below 8 digits'   => [ '+43 12345', 'AT', null ],
            'AT too long'               => [ '+43 12345678901234', 'AT', null ],
            'CH too short'              => [ '+41 44 668 18', 'DE', null ],
            'CH too long'               => [ '+41 44 668 18 000', 'DE', null ],
            'DE national too short'     => [ '+49 12345', 'DE', null ],
            'DE national too long'      => [ '+49 12345678901234', 'DE', null ],
        ];
    }

    /** @dataProvider phone_numbers */
    public function test_normalizes_to_e164( string $input, string $region, ?string $expected ): void {
        $this->assertSame( $expected, ( new Phone_Normalizer() )->normalize( $input, $region ) );
    }
}
