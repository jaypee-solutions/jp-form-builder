<?php
/**
 * Parses comma-separated lists of Recipient addresses.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Recipient_List {

    /** @return string[] The valid addresses of a comma-separated list, without duplicates. */
    public static function parse( string $addresses ): array {
        $valid = [];
        foreach ( explode( ',', $addresses ) as $address ) {
            $address = trim( $address );
            if ( '' !== $address && false !== is_email( $address ) ) {
                $valid[] = $address;
            }
        }
        return array_values( array_unique( $valid ) );
    }
}
