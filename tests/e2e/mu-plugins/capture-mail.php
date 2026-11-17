<?php
/**
 * Plugin Name: E2E mail capture
 * Description: Keeps the last mail in an option instead of sending it, and relaxes the Form Builder's anti-spam timing for E2E runs.
 *
 * Mapped into the development wp-env only (.wp-env.json).
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// Every E2E run submits from the same address; the default limit would fail repeated runs.
if ( ! defined( 'JP_FORM_BUILDER_RATE_LIMIT' ) ) {
    define( 'JP_FORM_BUILDER_RATE_LIMIT', 1000 );
}

add_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );

add_filter(
    'pre_wp_mail',
    static function ( $short_circuit, array $mail ) {
        unset( $short_circuit );
        update_option( 'jp_form_builder_e2e_last_mail', $mail, false );
        return true;
    },
    10,
    2
);
