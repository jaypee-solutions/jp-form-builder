<?php
/**
 * Guards values used in mail headers.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Header_Guard {

    public function is_safe( string $value ): bool {
        return 1 !== preg_match( '/[\r\n\x00]/', $value );
    }

    public function reply_to( string $name, string $email ): ?string {
        if ( ! $this->is_safe( $email ) || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return null;
        }
        // wp_mail() splits address headers at commas and keeps quote characters in the display name; PHPMailer quotes it as needed.
        $name = trim( (string) preg_replace( '/\s+/', ' ', str_replace( ',', ' ', str_replace( [ '"', '<', '>', "\r", "\n", "\0" ], '', $name ) ) ) );
        return '' === $name ? 'Reply-To: ' . $email : 'Reply-To: ' . $name . ' <' . $email . '>';
    }
}
