<?php
/**
 * Normalises Field keys.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * The rule, which src/shared/field-key.js follows exactly: lowercase; ä, ö, ü, ß become ae, oe, ue, ss;
 * the accents of the Latin-1 Supplement and Latin Extended-A letters are removed; every other character
 * outside a-z, 0-9 and "_" becomes "_"; leading and trailing underscores are trimmed.
 */
final class Field_Key {

    private const UMLAUTS = [
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'ß' => 'ss',
    ];

    /** Base letter => the accented letters that reduce to it (the ones Unicode decomposes into letter + accent). */
    private const ACCENTED = [
        'a' => 'àáâãåāăą',
        'c' => 'çćĉċč',
        'd' => 'ď',
        'e' => 'èéêëēĕėęě',
        'g' => 'ĝğġģ',
        'h' => 'ĥ',
        'i' => 'ìíîïĩīĭį',
        'j' => 'ĵ',
        'k' => 'ķ',
        'l' => 'ĺļľ',
        'n' => 'ñńņň',
        'o' => 'òóôõōŏő',
        'r' => 'ŕŗř',
        's' => 'śŝşš',
        't' => 'ţť',
        'u' => 'ùúûũūŭůűų',
        'w' => 'ŵ',
        'y' => 'ýÿŷ',
        'z' => 'źżž',
    ];

    /** @var array<string,string>|null */
    private static ?array $replacements = null;

    /** @return string The key, or '' when nothing usable is left. */
    public static function normalize( string $raw ): string {
        $key = strtr( Text::lowercase( $raw ), self::replacements() );
        return trim( (string) preg_replace( '/[^a-z0-9_]/', '_', $key ), '_' );
    }

    /** @return array<string,string> */
    private static function replacements(): array {
        if ( null === self::$replacements ) {
            self::$replacements = self::UMLAUTS;
            foreach ( self::ACCENTED as $base => $letters ) {
                foreach ( (array) preg_split( '//u', $letters, -1, PREG_SPLIT_NO_EMPTY ) as $letter ) {
                    self::$replacements[ $letter ] = $base;
                }
            }
        }
        return self::$replacements;
    }
}
