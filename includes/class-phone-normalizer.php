<?php
/**
 * Normalises phone numbers to E.164.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Phone_Normalizer {

    private const REGION_COUNTRY_CODES = [
        'DE' => '49',
        'AT' => '43',
        'CH' => '41',
    ];

    /** National number length range (without the leading 0) per country code. */
    private const NATIONAL_LENGTHS = [
        '49' => [ 6, 13 ],
        '43' => [ 4, 13 ],
        '41' => [ 9, 9 ],
    ];

    /** @return string|null E.164 number such as "+49821123456", or null when the input is not a plausible number. */
    public function normalize( string $input, string $region ): ?string {
        $input = trim( $input );
        if ( 1 !== preg_match( '~^[0-9 +\-/().]+$~', $input ) || false !== strpos( $input, '+', 1 ) ) {
            return null;
        }

        $digits = (string) preg_replace( '/\D/', '', str_replace( '(0)', '', $input ) );

        if ( str_starts_with( $input, '+' ) ) {
            $international = $digits;
        } elseif ( str_starts_with( $digits, '00' ) ) {
            $international = substr( $digits, 2 );
        } elseif ( str_starts_with( $digits, '0' ) ) {
            $country_code = self::REGION_COUNTRY_CODES[ strtoupper( $region ) ] ?? null;
            if ( null === $country_code ) {
                return null;
            }
            $international = $country_code . substr( $digits, 1 );
        } else {
            return null;
        }

        if ( '' === $international || str_starts_with( $international, '0' ) ) {
            return null;
        }

        $international = $this->strip_trunk_prefix( $international );
        if ( null === $international || strlen( $international ) < 8 || strlen( $international ) > 15 ) {
            return null;
        }

        return '+' . $international;
    }

    /** Removes the national trunk 0 after a DE/AT/CH country code and checks the national length. */
    private function strip_trunk_prefix( string $international ): ?string {
        $country_code = substr( $international, 0, 2 );
        if ( ! isset( self::NATIONAL_LENGTHS[ $country_code ] ) ) {
            return $international;
        }

        $national = substr( $international, 2 );
        if ( str_starts_with( $national, '0' ) ) {
            $national = substr( $national, 1 );
        }

        list( $min, $max ) = self::NATIONAL_LENGTHS[ $country_code ];
        if ( strlen( $national ) < $min || strlen( $national ) > $max ) {
            return null;
        }
        return $country_code . $national;
    }
}
