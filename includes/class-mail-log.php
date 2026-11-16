<?php
/**
 * Records redacted mail failures.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Log {

    private const OPTION = 'jp_form_builder_mail_log';

    public function init(): void {
        add_action( 'wp_mail_failed', [ $this, 'record' ] );
    }

    public function record( \WP_Error $error ): void {
        $message = self::redact( $error->get_error_message() );
        error_log( 'jp-form-builder: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        $entries   = get_option( self::OPTION, [] );
        $entries   = is_array( $entries ) ? $entries : [];
        $entries[] = [ 'time' => gmdate( 'c' ), 'form_id' => null, 'message' => $message ];
        update_option( self::OPTION, array_slice( $entries, -10 ), false );
    }

    public static function redact( string $message ): string {
        return (string) preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted]', $message );
    }
}
