<?php
/**
 * Test seed: creates the fixtures the E2E suite expects.
 *
 * Call: npm run env:seed  (wp eval-file tests/seed.php)
 *
 * No declare(strict_types=1): wp eval-file does not evaluate the contents as the
 * first statement of a script, which would be a fatal error.
 *
 * @package JayPee\FormBuilder
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "Only callable via WP-CLI.\n" );
    exit( 1 );
}

// Pretty permalinks — without them every fixture URL 404s.
global $wp_rewrite;
update_option( 'permalink_structure', '/%postname%/' );
$wp_rewrite->init();
$wp_rewrite->flush_rules();
require_once ABSPATH . 'wp-admin/includes/misc.php';
save_mod_rewrite_rules();

// Fresh users otherwise get onboarding dialogs that cover the editor unpredictably.
global $wpdb;
foreach ( get_users( [ 'fields' => 'ID' ] ) as $user_id ) {
    update_user_meta(
        $user_id,
        $wpdb->get_blog_prefix() . 'persisted_preferences',
        [
            'core/edit-post' => [ 'welcomeGuide' => false ],
            'core'           => [ 'enableChoosePatternModal' => false ],
            '_modified'      => gmdate( 'c' ),
        ]
    );
}

// Add the plugin's fixtures below. Keep seeding idempotent: look up records by
// stable SKU or slug before creating them, since the runner seeds an existing
// database before every E2E run. Do not rely on generated post/user IDs.

WP_CLI::success( 'Seed complete.' );
