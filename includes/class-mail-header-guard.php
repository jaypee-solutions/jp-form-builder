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
        $name = str_replace( [ '"', '<', '>', "\r", "\n", "\0" ], '', $name );
        if ( ! $this->is_safe( $name ) ) {
            return null;
        }
        return 'Reply-To: "' . $name . '" <' . $email . '>';
    }
}
