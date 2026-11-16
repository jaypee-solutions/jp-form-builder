<?php
/**
 * Removes plugin settings and transient data on uninstall.
 *
 * @package JayPee\FormBuilder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jp_form_builder_settings' );
delete_option( 'jp_form_builder_mail_log' );
delete_option( 'jp_form_builder_github_token' );

global $wpdb;
$like = $wpdb->esc_like( '_transient_jp_form_builder_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$like = $wpdb->esc_like( '_transient_timeout_jp_form_builder_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
