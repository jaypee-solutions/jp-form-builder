<?php
/**
 * Configures SMTP and sends plugin mail.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mailer {

    private Settings $settings;
    private ?Mail $pending_mail = null;
    private ?\WP_Error $last_error = null;
    private bool $replaced_default_from = false;

    public function __construct( ?Settings $settings = null ) {
        $this->settings = $settings ?? new Settings();
    }

    public function init(): void {
        add_action( 'phpmailer_init', [ $this, 'configure' ], 1000 );
        add_action( 'wp_mail_failed', [ $this, 'capture_failure' ] );
        add_filter( 'wp_mail_from', [ $this, 'filter_from' ], 1000 );
        add_filter( 'wp_mail_from_name', [ $this, 'filter_from_name' ], 1000 );
    }

    public function filter_from( string $from ): string {
        $configured                  = (string) $this->settings->get( 'from_email' );
        $this->replaced_default_from = '' !== $configured && ( $this->forces_from() || $this->is_wordpress_default( $from ) );
        return $this->replaced_default_from ? $configured : $from;
    }

    // Core applies `wp_mail_from_name` right after `wp_mail_from`, so the name follows the address decision.
    public function filter_from_name( string $name ): string {
        $configured = (string) $this->settings->get( 'from_name' );
        if ( '' === $configured || ! $this->replaced_default_from ) {
            return $name;
        }
        return $this->forces_from() || 'WordPress' === $name ? $configured : $name;
    }

    private function forces_from(): bool {
        return (bool) $this->settings->get( 'force_from' );
    }

    /** @param object $mailer PHPMailer instance. */
    // PHPMailer exposes settings through its documented camel-case properties.
    // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
    public function configure( $mailer ): void {
        $host = (string) $this->settings->get( 'smtp_host' );
        if ( '' !== $host ) {
            $mailer->isSMTP();
            $mailer->Host       = $host;
            $mailer->Port       = (int) $this->settings->get( 'smtp_port' );
            $mailer->SMTPAuth   = (bool) $this->settings->get( 'smtp_auth' );
            $mailer->Username   = (string) $this->settings->get( 'smtp_user' );
            $mailer->Password   = (string) $this->settings->get( 'smtp_password' );
            $mailer->SMTPSecure = [ 'none' => '', 'starttls' => 'tls', 'smtps' => 'ssl' ][ (string) $this->settings->get( 'smtp_encryption' ) ] ?? 'tls';
            $mailer->SMTPAutoTLS = 'none' !== $this->settings->get( 'smtp_encryption' );
            $mailer->Timeout    = 15;
        }
        $from = (string) $this->settings->get( 'from_email' );
        if ( '' !== $from ) {
            $mailer->Sender = $from;
        }
        if ( null !== $this->pending_mail ) {
            $mailer->AltBody = $this->pending_mail->text_body();
        } else {
            $mailer->AltBody = '';
        }
    }
    // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

    public function capture_failure( \WP_Error $error ): void {
        $this->last_error = $error;
    }

    /** @return true|\WP_Error */
    public function send( Mail $mail ) {
        $this->pending_mail = $mail;
        $this->last_error   = null;
        $headers            = $mail->headers();
        $headers[]          = 'Content-Type: text/html; charset=UTF-8';
        try {
            $sent = wp_mail( $mail->recipients(), $mail->subject(), $mail->html_body(), $headers );
        } finally {
            $this->pending_mail = null;
        }
        if ( ! $sent && null === $this->last_error ) {
            $error = new \WP_Error( 'jpfb_mail_failed', __( 'Mail could not be sent.', 'jp-form-builder' ) );
            do_action( 'wp_mail_failed', $error );
            $this->last_error = $error;
        }
        return $sent && null === $this->last_error ? true : $this->last_error;
    }

    public function send_test( string $to ) {
        $message = '<p>' . esc_html__( 'This is a test message from Form Builder.', 'jp-form-builder' ) . '</p>';
        $html = ( new Mail_Layout( $this->settings ) )->wrap( $message );
        $text = ( new Mail_Template() )->render( $message, [], [], 'text' );
        return $this->send( new Mail( [ $to ], __( 'Form Builder test mail', 'jp-form-builder' ), $html, $text ) );
    }

    private function core_default_from(): string {
        $host = wp_parse_url( network_home_url(), PHP_URL_HOST );
        return 'wordpress@' . ( is_string( $host ) ? preg_replace( '/^www\./i', '', $host ) : '' );
    }

    private function is_wordpress_default( string $from ): bool {
        return strtolower( $from ) === strtolower( $this->core_default_from() );
    }
}
