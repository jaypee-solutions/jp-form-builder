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

/**
 * Creates the post of the given type and slug, or resets an existing one to the given content.
 *
 * @param string $post_type Post type.
 * @param string $slug      Stable slug the lookup uses.
 * @param string $title     Post title.
 * @param string $content   Block markup.
 * @return int Post ID.
 */
function jpfb_seed_post( string $post_type, string $slug, string $title, string $content ): int {
    $existing = get_page_by_path( $slug, OBJECT, $post_type );
    $post     = [
        'post_type'    => $post_type,
        'post_name'    => $slug,
        'post_title'   => $title,
        'post_status'  => 'publish',
        'post_content' => $content,
    ];
    if ( $existing ) {
        $post['ID'] = $existing->ID;
    }
    $post_id = wp_insert_post( wp_slash( $post ), true );
    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( $post_id->get_error_message() );
    }
    return $post_id;
}

$jpfb_form_id = jpfb_seed_post(
    'jp_form',
    'e2e-contact',
    'E2E contact',
    '<!-- wp:jp-form-builder/form {"recipients":"owner@example.test"} -->'
    . '<!-- wp:jp-form-builder/name {"fieldName":"name","required":true} /-->'
    . '<!-- wp:jp-form-builder/email {"fieldName":"email","required":true} /-->'
    . '<!-- wp:jp-form-builder/textarea {"fieldName":"message","required":true} /-->'
    . '<!-- wp:jp-form-builder/consent {"fieldName":"privacy"} /-->'
    . '<!-- /wp:jp-form-builder/form -->'
);
jpfb_seed_post(
    'page',
    'e2e-contact-page',
    'E2E contact page',
    '<!-- wp:jp-form-builder/embed {"formId":' . $jpfb_form_id . '} /-->'
);

$jpfb_phone_form_id = jpfb_seed_post(
    'jp_form',
    'e2e-phone',
    'E2E phone',
    '<!-- wp:jp-form-builder/form {"recipients":"owner@example.test"} -->'
    . '<!-- wp:jp-form-builder/phone {"fieldName":"phone","required":true} /-->'
    . '<!-- /wp:jp-form-builder/form -->'
);
jpfb_seed_post(
    'page',
    'e2e-phone-page',
    'E2E phone page',
    '<!-- wp:jp-form-builder/embed {"formId":' . $jpfb_phone_form_id . '} /-->'
);

$jpfb_choices_form_id = jpfb_seed_post(
    'jp_form',
    'e2e-choices',
    'E2E choices',
    '<!-- wp:jp-form-builder/form {"recipients":"owner@example.test"} -->'
    . '<!-- wp:jp-form-builder/radio {"fieldName":"plan","label":"Plan","required":true,"options":[{"label":"Silber","value":"plan-s"},{"label":"Gold","value":"plan-g"}]} /-->'
    . '<!-- wp:jp-form-builder/checkbox {"fieldName":"extras","label":"Extras","required":true,"options":[{"label":"Versand","value":"extra-ship"},{"label":"Montage","value":"extra-install"}]} /-->'
    . '<!-- wp:jp-form-builder/conditional {"sourceField":"extras","operator":"includes","values":["extra-install"]} -->'
    . '<!-- wp:jp-form-builder/text {"fieldName":"floor","label":"Etage"} /-->'
    . '<!-- /wp:jp-form-builder/conditional -->'
    . '<!-- /wp:jp-form-builder/form -->'
);
jpfb_seed_post(
    'page',
    'e2e-choices-page',
    'E2E choices page',
    '<!-- wp:jp-form-builder/embed {"formId":' . $jpfb_choices_form_id . '} /-->'
);

WP_CLI::success( 'Seed complete.' );
