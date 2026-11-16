<?php
/**
 * Registers Form Builder WP-CLI commands.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

final class Cli_Command {

    private Settings $settings;

    public function __construct( ?Settings $settings = null ) {
        $this->settings = $settings ?? new Settings();
    }

    public function init(): void {
        WP_CLI::add_command( 'jp-form-builder', $this );
    }

    /** Sends a test mail. */
    public function mail( array $args, array $assoc_args ): void {
        unset( $assoc_args );
        if ( ( $args[0] ?? '' ) !== 'test' || empty( $args[1] ) ) {
            WP_CLI::error( 'Usage: wp jp-form-builder mail test <to>' );
        }
        $result = ( new Mailer( $this->settings ) )->send_test( sanitize_email( $args[1] ) );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }
        WP_CLI::success( 'Test mail sent.' );
    }

    /** Lists, changes, or deletes settings. */
    public function settings( array $args, array $assoc_args ): void {
        $action = $args[0] ?? 'list';
        if ( 'list' === $action ) {
            $rows = [];
            foreach ( $this->settings->keys() as $key ) {
                $value   = $this->settings->get( $key );
                $display = $this->settings->is_secret( $key ) ? ( '' === (string) $value ? 'not set' : '********' ) : ( is_scalar( $value ) ? (string) $value : '' );
                $rows[]  = [ 'key' => $key, 'value' => $display, 'source' => $this->settings->source( $key ) ];
            }
            Utils\format_items( $assoc_args['format'] ?? 'table', $rows, [ 'key', 'value', 'source' ] );
            return;
        }
        $key = $args[1] ?? '';
        if ( 'set' === $action ) {
            if ( '' === $key || ! in_array( $key, $this->settings->keys(), true ) ) {
                WP_CLI::error( 'Unknown setting.' );
            }
            if ( $this->settings->is_locked( $key ) ) {
                WP_CLI::error( 'Setting is locked by a constant.' );
            }
            $value = $args[2] ?? stream_get_contents( STDIN );
            $this->settings->save( [ $key => trim( (string) $value ) ] );
            WP_CLI::success( 'Setting saved.' );
            return;
        }
        if ( 'delete' === $action && '' !== $key ) {
            if ( ! in_array( $key, $this->settings->keys(), true ) ) {
                WP_CLI::error( 'Unknown setting.' );
            }
            if ( $this->settings->is_locked( $key ) ) {
                WP_CLI::error( 'Setting is locked by a constant.' );
            }
            $option = get_option( 'jp_form_builder_settings', [] );
            unset( $option[ $key ] );
            update_option( 'jp_form_builder_settings', $option, false );
            WP_CLI::success( 'Setting deleted.' );
            return;
        }
        WP_CLI::error( 'Unknown settings command.' );
    }

    /** Lists Forms. */
    public function form( array $args, array $assoc_args ): void {
        $posts = get_posts( [ 'post_type' => 'jp_form', 'post_status' => 'any', 'numberposts' => -1 ] );
        $rows = array_map( static fn( \WP_Post $post ): array => [ 'id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status ], $posts );
        Utils\format_items( $assoc_args['format'] ?? 'table', $rows, [ 'id', 'title', 'status' ] );
    }
}
