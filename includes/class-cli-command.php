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
    private Mailer $mailer;
    private Form_Backup $backup;

    public function __construct( ?Settings $settings = null, ?Mailer $mailer = null, ?Form_Backup $backup = null ) {
        $this->settings = $settings ?? new Settings();
        $this->mailer   = $mailer ?? new Mailer( $this->settings );
        $this->backup   = $backup ?? new Form_Backup( $this->settings );
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
        $result = $this->mailer->send_test( sanitize_email( $args[1] ) );
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

    /** Exports a Backup (`backup export [<file>]`) or restores one (`backup restore <file> [--overwrite] [--dry-run]`). */
    public function backup( array $args, array $assoc_args ): void {
        $action = $args[0] ?? '';
        $file   = $args[1] ?? '';
        if ( 'export' === $action ) {
            $json = $this->backup->export_json();
            if ( '' === $file ) {
                WP_CLI::line( $json );
                return;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI writes the file the operator named.
            if ( false === file_put_contents( $file, $json . "\n" ) ) {
                WP_CLI::error( 'Cannot write ' . $file . '.' );
            }
            WP_CLI::success( 'Backup written to ' . $file . '.' );
            return;
        }
        if ( 'restore' === $action && '' !== $file ) {
            $dry_run = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
            $report  = $this->backup->restore_file( $file, (bool) Utils\get_flag_value( $assoc_args, 'overwrite', false ), $dry_run );
            if ( '' !== $report['error'] ) {
                WP_CLI::error( $report['error'] );
            }
            foreach ( $this->backup->describe( $report ) as $line ) {
                WP_CLI::line( $line );
            }
            if ( [] !== $report['rejected'] ) {
                WP_CLI::warning( count( $report['rejected'] ) . ' Form(s) rejected.' );
            }
            WP_CLI::success( $dry_run ? 'Dry run finished.' : 'Restore finished.' );
            return;
        }
        WP_CLI::error( 'Usage: wp jp-form-builder backup export [<file>] | backup restore <file> [--overwrite] [--dry-run]' );
    }
}
