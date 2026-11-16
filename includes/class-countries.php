<?php
/** Country choices and postcode patterns. */
declare( strict_types = 1 );
namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;
final class Countries {
    /** @return array<string,string> */
    public static function all(): array {
        $countries = [
            'AT' => __( 'Austria', 'jp-form-builder' ), 'BE' => __( 'Belgium', 'jp-form-builder' ),
            'BG' => __( 'Bulgaria', 'jp-form-builder' ), 'HR' => __( 'Croatia', 'jp-form-builder' ),
            'CY' => __( 'Cyprus', 'jp-form-builder' ), 'CZ' => __( 'Czechia', 'jp-form-builder' ),
            'DK' => __( 'Denmark', 'jp-form-builder' ), 'EE' => __( 'Estonia', 'jp-form-builder' ),
            'FI' => __( 'Finland', 'jp-form-builder' ), 'FR' => __( 'France', 'jp-form-builder' ),
            'DE' => __( 'Germany', 'jp-form-builder' ), 'GR' => __( 'Greece', 'jp-form-builder' ),
            'HU' => __( 'Hungary', 'jp-form-builder' ), 'IE' => __( 'Ireland', 'jp-form-builder' ),
            'IT' => __( 'Italy', 'jp-form-builder' ), 'LV' => __( 'Latvia', 'jp-form-builder' ),
            'LT' => __( 'Lithuania', 'jp-form-builder' ), 'LU' => __( 'Luxembourg', 'jp-form-builder' ),
            'MT' => __( 'Malta', 'jp-form-builder' ), 'NL' => __( 'Netherlands', 'jp-form-builder' ),
            'PL' => __( 'Poland', 'jp-form-builder' ), 'PT' => __( 'Portugal', 'jp-form-builder' ),
            'RO' => __( 'Romania', 'jp-form-builder' ), 'SK' => __( 'Slovakia', 'jp-form-builder' ),
            'SI' => __( 'Slovenia', 'jp-form-builder' ), 'ES' => __( 'Spain', 'jp-form-builder' ),
            'SE' => __( 'Sweden', 'jp-form-builder' ), 'CH' => __( 'Switzerland', 'jp-form-builder' ),
            'LI' => __( 'Liechtenstein', 'jp-form-builder' ), 'NO' => __( 'Norway', 'jp-form-builder' ),
            'IS' => __( 'Iceland', 'jp-form-builder' ), 'GB' => __( 'United Kingdom', 'jp-form-builder' ),
        ];
        return $countries;
    }
    public static function postcode_pattern( string $country ): string {
        return in_array( $country, [ 'DE' ], true ) ? '^\\d{5}$' : ( in_array( $country, [ 'AT', 'CH', 'LI' ], true ) ? '^\\d{4}$' : '^[A-Za-z0-9][A-Za-z0-9 -]{1,9}$' ); }
}
