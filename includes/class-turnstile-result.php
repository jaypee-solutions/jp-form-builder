<?php
/**
 * Outcome of a Turnstile verification.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Turnstile_Result {

    /** The token is genuine, for this site and for the Form action. */
    public const PASSED = 'passed';

    /** Cloudflare answered, but the visitor did not pass. */
    public const FAILED = 'failed';

    /** No usable answer from Cloudflare (transport error, non-200 status or malformed body). */
    public const UNAVAILABLE = 'unavailable';

    public string $outcome;

    /** @var string[] Cloudflare error codes of a failed verification. */
    public array $error_codes;

    /** @param string[] $error_codes Cloudflare error codes. */
    private function __construct( string $outcome, array $error_codes = [] ) {
        $this->outcome     = $outcome;
        $this->error_codes = $error_codes;
    }

    public static function passed(): self {
        return new self( self::PASSED );
    }

    /** @param string[] $error_codes Cloudflare error codes. */
    public static function failed( array $error_codes = [] ): self {
        return new self( self::FAILED, $error_codes );
    }

    public static function unavailable(): self {
        return new self( self::UNAVAILABLE );
    }

    public function is_passed(): bool {
        return self::PASSED === $this->outcome;
    }

    public function is_failed(): bool {
        return self::FAILED === $this->outcome;
    }

    public function is_unavailable(): bool {
        return self::UNAVAILABLE === $this->outcome;
    }
}
