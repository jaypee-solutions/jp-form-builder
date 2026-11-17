<?php
/**
 * Removes every Form and all plugin data on uninstall (single site).
 *
 * Embed blocks in page content stay and render nothing. The README and the
 * settings page tell administrators to download a Backup first.
 *
 * @package JayPee\FormBuilder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$jp_form_builder_form_ids = get_posts(
    [
        'post_type'        => 'jp_form',
        'post_status'      => array_values( get_post_stati() ),
        'numberposts'      => -1,
        'fields'           => 'ids',
        'suppress_filters' => true,
    ]
);
foreach ( $jp_form_builder_form_ids as $jp_form_builder_form_id ) {
    wp_delete_post( (int) $jp_form_builder_form_id, true );
}

delete_option( 'jp_form_builder_settings' );
delete_option( 'jp_form_builder_mail_log' );
delete_option( 'jp_form_builder_github_token' );
delete_option( 'external_updates-jp-form-builder' ); // Plugin Update Checker state.

global $wpdb;
foreach ( [ '_transient_jp_form_builder_', '_transient_timeout_jp_form_builder_' ] as $jp_form_builder_prefix ) {
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $jp_form_builder_prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of transient rows.
}
