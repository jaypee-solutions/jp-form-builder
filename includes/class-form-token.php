<?php
/**
 * Signed token proving when a visitor loaded a Form.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Token {

    /** Longest accepted age in seconds. */
    public const MAX_AGE = 7200;

    /** @return string Token of the form "<issued_at>.<hmac>". */
    public function issue( int $form_id, int $issued_at, string $secret ): string {
        return $issued_at . '.' . $this->signature( $form_id, $issued_at, $secret );
    }

    /** Whether the token is genuine for the Form and its age lies between $min_age and $max_age seconds. */
    public function verify( string $token, int $form_id, int $now, int $min_age, int $max_age, string $secret ): bool {
        if ( 1 !== preg_match( '/^(\d{1,15})\.([0-9a-f]{64})$/', $token, $matches ) ) {
            return false;
        }

        $issued_at = (int) $matches[1];
        if ( ! hash_equals( $this->signature( $form_id, $issued_at, $secret ), $matches[2] ) ) {
            return false;
        }

        $age = $now - $issued_at;
        return $age >= $min_age && $age <= $max_age;
    }

    private function signature( int $form_id, int $issued_at, string $secret ): string {
        return hash_hmac( 'sha256', $form_id . '|' . $issued_at, $secret );
    }
}
