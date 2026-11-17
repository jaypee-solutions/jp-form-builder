<?php
/**
 * Encrypts secrets stored in plugin options.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Secret_Box {

    private ?string $salt;

    /** Derived on first use: most requests never touch a secret. */
    private ?string $key = null;

    public function __construct( ?string $salt = null ) {
        $this->salt = $salt;
    }

    private function key(): string {
        if ( null === $this->key ) {
            $this->key = sodium_crypto_generichash( $this->salt ?? wp_salt( 'secure_auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
        }
        return $this->key;
    }

    public function encrypt( string $value ): string {
        $nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $ciphertext = sodium_crypto_secretbox( $value, $nonce, $this->key() );

        return 'v1:' . base64_encode( $nonce . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    public function decrypt( string $value ): string {
        if ( 0 !== strpos( $value, 'v1:' ) ) {
            return '';
        }

        $decoded = base64_decode( substr( $value, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        if ( false === $decoded || strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
            return '';
        }

        $nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->key() );

        return false === $plaintext ? '' : $plaintext;
    }
}
