<?php
/**
 * Cleans submitted text.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Text {

    public static function is_valid_utf8( string $value ): bool {
        return 1 === preg_match( '//u', $value );
    }

    /** Single line: no control characters, trimmed; invalid UTF-8 becomes ''. */
    public static function single_line( string $value ): string {
        if ( ! self::is_valid_utf8( $value ) ) {
            return '';
        }
        return self::trim( (string) preg_replace( '/\p{Cc}/u', '', $value ) );
    }

    /** Multiple lines: control characters other than "\n" removed, "\r\n" and "\r" become "\n", trimmed. */
    public static function multi_line( string $value ): string {
        if ( ! self::is_valid_utf8( $value ) ) {
            return '';
        }
        $value = str_replace( [ "\r\n", "\r" ], "\n", $value );
        return self::trim( (string) preg_replace( '/[^\P{Cc}\n]/u', '', $value ) );
    }

    /** Length in characters of a valid UTF-8 string. */
    public static function length( string $value ): int {
        return mb_strlen( $value, 'UTF-8' );
    }

    private static function trim( string $value ): string {
        return (string) preg_replace( '/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value );
    }
}
