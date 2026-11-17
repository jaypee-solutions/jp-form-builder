<?php
/**
 * Fixed-window attempt counter stored in transients.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

    private string $key;

    /** @param string $key HMAC key hashing the subject, so transients never hold raw IPs or addresses. */
    public function __construct( string $key ) {
        $this->key = $key;
    }

    /**
     * Counts one attempt and tells whether it is still within the limit.
     *
     * The window starts with the first attempt and every attempt counts, also those over the limit.
     *
     * @param string   $bucket  Counter family, e.g. "rl" or "cf".
     * @param string   $subject What is limited, e.g. an IP or an email address.
     * @param int      $limit   Attempts allowed per window.
     * @param int      $window  Window length in seconds.
     * @param int|null $now     Current Unix time; defaults to now.
     * @return bool False once the limit is exceeded.
     */
    public function attempt( string $bucket, string $subject, int $limit, int $window, ?int $now = null ): bool {
        $now       = $now ?? time();
        $transient = 'jp_form_builder_' . $bucket . '_' . substr( hash_hmac( 'sha256', $subject, $this->key ), 0, 32 );
        $state     = get_transient( $transient );

        if ( is_array( $state ) && isset( $state['count'], $state['reset'] ) && $state['reset'] > $now ) {
            $count = (int) $state['count'] + 1;
            $reset = (int) $state['reset'];
        } else {
            $count = 1;
            $reset = $now + $window;
        }

        set_transient(
            $transient,
            [
                'count' => $count,
                'reset' => $reset,
            ],
            max( 1, $reset - $now )
        );

        return $count <= $limit;
    }
}
