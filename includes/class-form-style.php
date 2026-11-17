<?php
/**
 * Turns the design settings of a Form embed into sanitised CSS custom properties.
 *
 * Block attributes are editable by every author, so nothing reaches the markup that is not
 * on an allow-list: preset slugs, hex/rgb/hsl colours, a few keywords and plain lengths.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Style {

    /** Block attribute => custom property, for colours. */
    private const COLORS = [
        'fieldBackground'  => '--jpfb-field-background',
        'fieldBorderColor' => '--jpfb-field-border-color',
        'focusColor'       => '--jpfb-focus-color',
    ];

    /** Block attribute => custom property, for lengths. */
    private const LENGTHS = [
        'fieldBorderWidth' => '--jpfb-field-border-width',
        'fieldRadius'      => '--jpfb-field-radius',
    ];

    private const COLOR_KEYWORDS = [ 'transparent', 'currentcolor', 'inherit' ];

    /** Field widths in percent; 100 is the default and needs no class. */
    private const NARROW_WIDTHS = [ '50', '33' ];

    /**
     * @param array<string,mixed> $attributes Embed block attributes, including `style.spacing.blockGap`.
     * @return array<string,string> Custom property => value; only valid values are included.
     */
    public static function properties( array $attributes ): array {
        $properties = [];
        foreach ( self::COLORS as $attribute => $property ) {
            $value = self::color( $attributes[ $attribute ] ?? '' );
            if ( '' !== $value ) {
                $properties[ $property ] = $value;
            }
        }
        foreach ( self::LENGTHS as $attribute => $property ) {
            $value = self::length( $attributes[ $attribute ] ?? '' );
            if ( '' !== $value ) {
                $properties[ $property ] = $value;
            }
        }
        $gap = self::gap( $attributes['style']['spacing']['blockGap'] ?? '' );
        if ( '' !== $gap ) {
            $properties['--jpfb-field-gap'] = $gap;
        }
        return $properties;
    }

    /** @param array<string,mixed> $attributes Embed block attributes. */
    public static function declarations( array $attributes ): string {
        $declarations = '';
        foreach ( self::properties( $attributes ) as $property => $value ) {
            $declarations .= $property . ':' . $value . ';';
        }
        return $declarations;
    }

    /**
     * @param mixed $value A palette slug, a custom colour or a CSS keyword.
     * @return string A CSS colour, or '' when the value is not allowed.
     */
    public static function color( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = trim( $value );
        $lower = strtolower( $value );
        if ( in_array( $lower, self::COLOR_KEYWORDS, true ) ) {
            return $lower;
        }
        if ( 1 === preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $lower ) ) {
            return $lower;
        }
        // WordPress's own filter on wrapper styles drops rgb() and hsl() in custom properties, so they leave as hex.
        $hex = self::function_to_hex( $lower );
        if ( '' !== $hex ) {
            return $hex;
        }
        if ( 1 === preg_match( '/^[a-z0-9-]+$/', $value ) ) {
            return 'var(--wp--preset--color--' . $value . ')';
        }
        return '';
    }

    /** @return string `#rrggbb` or `#rrggbbaa`, or '' when the value is not a valid rgb()/hsl() colour. */
    private static function function_to_hex( string $value ): string {
        if ( 1 !== preg_match( '/^(rgb|hsl)a?\(([-+0-9a-z.%\s,\/]*)\)$/', $value, $match ) ) {
            return '';
        }
        $parts = preg_split( '/[\s,\/]+/', trim( $match[2] ), -1, PREG_SPLIT_NO_EMPTY );
        if ( ! is_array( $parts ) || ! in_array( count( $parts ), [ 3, 4 ], true ) ) {
            return '';
        }

        $alpha = isset( $parts[3] ) ? self::number( $parts[3], '%' ) : 1.0;
        if ( null === $alpha ) {
            return '';
        }
        $alpha = str_ends_with( $parts[3] ?? '', '%' ) ? $alpha / 100 : $alpha;

        if ( 'rgb' === $match[1] ) {
            $channels = [];
            foreach ( array_slice( $parts, 0, 3 ) as $part ) {
                $channel = self::number( $part, '%' );
                if ( null === $channel ) {
                    return '';
                }
                $channels[] = str_ends_with( $part, '%' ) ? $channel * 255 / 100 : $channel;
            }
        } else {
            $hue        = self::number( $parts[0], 'deg' );
            $saturation = str_ends_with( $parts[1], '%' ) ? self::number( $parts[1], '%' ) : null;
            $lightness  = str_ends_with( $parts[2], '%' ) ? self::number( $parts[2], '%' ) : null;
            if ( null === $hue || null === $saturation || null === $lightness ) {
                return '';
            }
            $channels = self::hsl_to_rgb( fmod( $hue, 360 ) + ( $hue < 0 ? 360 : 0 ), min( max( $saturation, 0 ), 100 ) / 100, min( max( $lightness, 0 ), 100 ) / 100 );
        }

        $hex = '#';
        foreach ( $channels as $channel ) {
            $hex .= sprintf( '%02x', (int) round( min( max( $channel, 0 ), 255 ) ) );
        }
        $alpha = min( max( $alpha, 0 ), 1 );
        return $alpha < 1 ? $hex . sprintf( '%02x', (int) round( $alpha * 255 ) ) : $hex;
    }

    /** @return float[] Red, green and blue as 0 to 255. */
    private static function hsl_to_rgb( float $hue, float $saturation, float $lightness ): array {
        $chroma   = ( 1 - abs( 2 * $lightness - 1 ) ) * $saturation;
        $second   = $chroma * ( 1 - abs( fmod( $hue / 60, 2 ) - 1 ) );
        $offset   = $lightness - $chroma / 2;
        $sextants = [ [ $chroma, $second, 0 ], [ $second, $chroma, 0 ], [ 0, $chroma, $second ], [ 0, $second, $chroma ], [ $second, 0, $chroma ], [ $chroma, 0, $second ] ];
        $rgb      = $sextants[ (int) floor( $hue / 60 ) % 6 ];
        return array_map( static fn( $channel ): float => ( $channel + $offset ) * 255, $rgb );
    }

    /** @return float|null The number in `$value`, optionally followed by `$unit`; null for anything else. */
    private static function number( string $value, string $unit ): ?float {
        if ( 1 !== preg_match( '/^[-+]?(?:\d+(?:\.\d+)?|\.\d+)(?:' . preg_quote( $unit, '/' ) . ')?$/', $value ) ) {
            return null;
        }
        return (float) $value;
    }

    /**
     * @param mixed $value A length such as `6px`.
     * @return string The length, or '' when the value is not allowed.
     */
    public static function length( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = trim( $value );
        return 1 === preg_match( '/^\d+(?:\.\d+)?(?:px|rem|em|%)$/', $value ) ? $value : '';
    }

    /**
     * @param mixed $value A length or a spacing preset such as `var:preset|spacing|40`.
     * @return string A CSS length, or '' when the value is not allowed.
     */
    public static function gap( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        if ( 1 === preg_match( '/^var:preset\|spacing\|([a-z0-9-]+)$/', trim( $value ), $match ) ) {
            return 'var(--wp--preset--spacing--' . $match[1] . ')';
        }
        return self::length( $value );
    }

    /**
     * @param mixed $width The Field's width in percent: 100, 50 or 33.
     * @return string The CSS class for a narrower Field, or '' for full width and unknown values.
     */
    public static function width_class( $width ): string {
        $width = is_int( $width ) || is_string( $width ) ? (string) $width : '';
        return in_array( $width, self::NARROW_WIDTHS, true ) ? 'jp-form-builder__field--width-' . $width : '';
    }
}
