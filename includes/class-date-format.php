<?php
/**
 * The display format of Date fields. The JS mirror is src/shared/date-format.js.
 *
 * German locales show `TT.MM.JJJJ`, every other locale ISO `YYYY-MM-DD`. The wire value is always ISO.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Date_Format {

    private bool $day_first;

    private function __construct( bool $day_first ) {
        $this->day_first = $day_first;
    }

    public static function for_locale( string $locale ): self {
        return new self( str_starts_with( $locale, 'de' ) );
    }

    /** Value of the `data-date-format` attribute. */
    public function name(): string {
        return $this->day_first ? 'dmy' : 'iso';
    }

    public function placeholder(): string {
        return $this->day_first ? 'TT.MM.JJJJ' : 'YYYY-MM-DD';
    }

    /** The ISO date in the display format; text that is not a valid ISO date is returned unchanged. */
    public function format( string $iso ): string {
        if ( ! Date_Offset::is_valid_iso( $iso ) ) {
            return $iso;
        }
        return $this->day_first ? substr( $iso, 8, 2 ) . '.' . substr( $iso, 5, 2 ) . '.' . substr( $iso, 0, 4 ) : $iso;
    }

    /** @return string|null ISO date of a display-format text; null when it is not a valid date in this format. */
    public function parse( string $text ): ?string {
        $pattern = $this->day_first ? '/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})\z/' : '/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/';
        if ( 1 !== preg_match( $pattern, trim( $text ), $match ) ) {
            return null;
        }
        $iso = $this->day_first
            ? sprintf( '%s-%02d-%02d', $match[3], (int) $match[2], (int) $match[1] )
            : $match[0];
        return Date_Offset::is_valid_iso( $iso ) ? $iso : null;
    }
}
