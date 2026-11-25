<?php
/**
 * Binds each Postcode Field to its Country Field (ADR 0018).
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Postcode_Country {

    /**
     * The nearest preceding Country Field of each Postcode Field, else the nearest following one.
     *
     * @param Field_Definition[] $fields Every Field of the Form in document order.
     * @param array<string,true> $absent Keys of Country Fields that do not count: hidden by a Conditional group or empty.
     * @return array<string,string> Country Field key by Postcode Field key; a Postcode without a usable Country is missing.
     */
    public static function sources( array $fields, array $absent = [] ): array {
        $sources = [];
        $before  = '';
        foreach ( $fields as $index => $field ) {
            if ( 'country' === $field->type && ! isset( $absent[ $field->key ] ) ) {
                $before = $field->key;
                continue;
            }
            if ( 'postcode' !== $field->type ) {
                continue;
            }
            $source = '' !== $before ? $before : self::following( $fields, $index, $absent );
            if ( '' !== $source ) {
                $sources[ $field->key ] = $source;
            }
        }
        return $sources;
    }

    /**
     * @param Field_Definition[] $fields Every Field of the Form in document order.
     * @param array<string,true> $absent Country Fields that do not count.
     * @return string Key of the first usable Country Field after the position, or ''.
     */
    private static function following( array $fields, int $index, array $absent ): string {
        for ( $next = $index + 1, $count = count( $fields ); $next < $count; $next++ ) {
            if ( 'country' === $fields[ $next ]->type && ! isset( $absent[ $fields[ $next ]->key ] ) ) {
                return $fields[ $next ]->key;
            }
        }
        return '';
    }
}
