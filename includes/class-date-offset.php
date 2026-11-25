<?php
/**
 * Resolves Date offsets and checks calendar dates. The JS mirror is src/shared/date-offset.js; tests/fixtures/date-offsets.json drives both.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Date_Offset {

    public const MIN_DATE = '1900-01-01';
    public const MAX_DATE = '2100-12-31';

    /** Today in the site timezone. */
    public static function today(): \DateTimeImmutable {
        return new \DateTimeImmutable( 'now', wp_timezone() );
    }

    /**
     * Resolves an empty value, a fixed ISO date or a Date offset against today.
     *
     * @return string|null ISO date clamped to the supported years; null for an empty or invalid expression.
     */
    public static function resolve( string $expression, \DateTimeImmutable $today ): ?string {
        $text = strtolower( trim( $expression ) );
        if ( '' === $text ) {
            return null;
        }
        if ( self::is_valid_iso( $text ) ) {
            return $text;
        }
        if ( 'now' === $text || 'today' === $text ) {
            return self::clamp_parts( (int) $today->format( 'Y' ), (int) $today->format( 'n' ), (int) $today->format( 'j' ) );
        }
        if ( 1 !== preg_match( '/^([+-]?)([0-9]{1,3})([dwmy])\z/', $text, $match ) ) {
            return null;
        }

        $amount = (int) $match[2] * ( '-' === $match[1] ? -1 : 1 );
        $year   = (int) $today->format( 'Y' );
        $month  = (int) $today->format( 'n' );
        $day    = (int) $today->format( 'j' );

        if ( 'd' === $match[3] || 'w' === $match[3] ) {
            $shifted = $today->modify( sprintf( '%+d days', 'w' === $match[3] ? $amount * 7 : $amount ) );
            return self::clamp_parts( (int) $shifted->format( 'Y' ), (int) $shifted->format( 'n' ), (int) $shifted->format( 'j' ) );
        }

        $months = $year * 12 + $month - 1 + ( 'y' === $match[3] ? $amount * 12 : $amount );
        $year   = intdiv( $months, 12 );
        $month  = $months % 12 + 1;
        // A target day that does not exist moves to the end of the month.
        $day    = min( $day, self::days_in_month( $year, $month ) );
        return self::clamp_parts( $year, $month, $day );
    }

    /** Whether the text is an ISO `YYYY-MM-DD` date that exists and lies within the supported years. */
    public static function is_valid_iso( string $iso ): bool {
        if ( 1 !== preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $iso, $match ) ) {
            return false;
        }
        $year  = (int) $match[1];
        $month = (int) $match[2];
        $day   = (int) $match[3];
        return $year >= 1900 && $year <= 2100 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= self::days_in_month( $year, $month );
    }

    private static function days_in_month( int $year, int $month ): int {
        $days = [ 31, self::is_leap_year( $year ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
        return $days[ $month - 1 ];
    }

    private static function is_leap_year( int $year ): bool {
        return ( 0 === $year % 4 && 0 !== $year % 100 ) || 0 === $year % 400;
    }

    private static function clamp_parts( int $year, int $month, int $day ): string {
        $iso = $year < 1900 ? self::MIN_DATE : sprintf( '%04d-%02d-%02d', $year, $month, $day );
        return $iso < self::MIN_DATE ? self::MIN_DATE : ( $iso > self::MAX_DATE ? self::MAX_DATE : $iso );
    }
}
