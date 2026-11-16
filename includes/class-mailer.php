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

    public function __construct( ?Settings $settings = null ) {
        $this->settings = $settings ?? new Settings();
    }

    public function init(): void {
        add_action( 'phpmailer_init', [ $this, 'configure' ], 1000 );
        add_action( 'wp_mail_failed', [ $this, 'capture_failure' ] );
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
        if ( '' !== $from && ( (bool) $this->settings->get( 'force_from' ) || $this->is_wordpress_default( (string) $mailer->From ) ) ) {
            $mailer->setFrom( $from, (string) $this->settings->get( 'from_name' ), false );
        }
        if ( '' !== $from ) {
            $mailer->Sender = $from;
        }
        $mailer->AltBody = null !== $this->pending_mail ? $this->pending_mail->text_body() : '';
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
        return $sent && null === $this->last_error ? true : ( $this->last_error ?? new \WP_Error( 'jpfb_mail_failed', __( 'Mail could not be sent.', 'jp-form-builder' ) ) );
    }

    public function send_test( string $to ) {
        $html = ( new Mail_Layout( $this->settings ) )->wrap( '<p>' . esc_html__( 'This is a test message from Form Builder.', 'jp-form-builder' ) . '</p>' );
        $text = wp_strip_all_tags( $html );
        return $this->send( new Mail( [ $to ], __( 'Form Builder test mail', 'jp-form-builder' ), $html, $text ) );
    }

    private function is_wordpress_default( string $from ): bool {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return is_string( $host ) && strtolower( $from ) === strtolower( 'wordpress@' . $host );
    }
}
