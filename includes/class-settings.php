<?php
/**
 * Reads and writes settings with constant precedence.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Settings {

    private const OPTION = 'jp_form_builder_settings';

    private Secret_Box $secret_box;

    /** @var callable(string):array{bool,mixed} */
    private $constant_lookup;

    private const DEFINITIONS = [
        'smtp_host' => [ 'JP_FORM_BUILDER_SMTP_HOST', '', 'text' ],
        'smtp_port' => [ 'JP_FORM_BUILDER_SMTP_PORT', 587, 'port' ],
        'smtp_encryption' => [ 'JP_FORM_BUILDER_SMTP_ENCRYPTION', 'starttls', 'encryption' ],
        'smtp_auth' => [ 'JP_FORM_BUILDER_SMTP_AUTH', true, 'bool' ],
        'smtp_user' => [ 'JP_FORM_BUILDER_SMTP_USER', '', 'text' ],
        'smtp_password' => [ 'JP_FORM_BUILDER_SMTP_PASSWORD', '', 'secret' ],
        'from_email' => [ 'JP_FORM_BUILDER_FROM_EMAIL', '', 'email' ],
        'from_name' => [ 'JP_FORM_BUILDER_FROM_NAME', '', 'text' ],
        'force_from' => [ 'JP_FORM_BUILDER_FORCE_FROM', false, 'bool' ],
        'turnstile_site_key' => [ 'JP_FORM_BUILDER_TURNSTILE_SITE_KEY', '', 'text' ],
        'turnstile_secret_key' => [ 'JP_FORM_BUILDER_TURNSTILE_SECRET_KEY', '', 'secret' ],
        'default_recipient' => [ 'JP_FORM_BUILDER_DEFAULT_RECIPIENT', '', 'email_list' ],
        'smtp_auth_method' => [ 'JP_FORM_BUILDER_SMTP_AUTH_METHOD', 'password', 'auth_method' ],
        'm365_tenant_id' => [ 'JP_FORM_BUILDER_M365_TENANT_ID', '', 'text' ],
        'm365_client_id' => [ 'JP_FORM_BUILDER_M365_CLIENT_ID', '', 'text' ],
        'm365_client_secret' => [ 'JP_FORM_BUILDER_M365_CLIENT_SECRET', '', 'secret' ],
        'mail_layout' => [ '', '', 'mail_layout' ],
        'notification_subject' => [ '', '', 'single_line' ],
        'notification_body' => [ '', '', 'mail_body' ],
        'confirmation_subject' => [ '', '', 'single_line' ],
        'confirmation_body' => [ '', '', 'mail_body' ],
    ];

    /** Constant-only tunables: name => [ constant, default ]. */
    private const TUNABLES = [
        'max_payload_bytes' => [ 'JP_FORM_BUILDER_MAX_PAYLOAD_BYTES', 32768 ],
        'rate_limit'        => [ 'JP_FORM_BUILDER_RATE_LIMIT', 10 ],
        'rate_window'       => [ 'JP_FORM_BUILDER_RATE_WINDOW', 600 ],
        'min_fill_seconds'  => [ 'JP_FORM_BUILDER_MIN_FILL_SECONDS', 3 ],
        'trusted_proxies'   => [ 'JP_FORM_BUILDER_TRUSTED_PROXIES', '' ],
        'max_backup_bytes'  => [ 'JP_FORM_BUILDER_MAX_BACKUP_BYTES', 2097152 ],
    ];

    /**
     * @param callable(string):array{bool,mixed}|null $constant_lookup
     */
    public function __construct( ?callable $constant_lookup = null, ?Secret_Box $secret_box = null ) {
        $this->constant_lookup = $constant_lookup ?? static function ( string $name ): array {
            return [ defined( $name ), defined( $name ) ? constant( $name ) : null ];
        };
        $this->secret_box = $secret_box ?? new Secret_Box();
    }

    /** @return mixed */
    public function get( string $key ) {
        if ( ! isset( self::DEFINITIONS[ $key ] ) ) {
            return null;
        }
        [ $constant, $default ] = self::DEFINITIONS[ $key ];
        [ $locked, $value ]     = '' === $constant ? [ false, null ] : ( $this->constant_lookup )( $constant );
        if ( $locked ) {
            return $value;
        }
        $options = get_option( self::OPTION, [] );
        if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
            $value = $options[ $key ];
            return 'secret' === self::DEFINITIONS[ $key ][2] ? $this->secret_box->decrypt( (string) $value ) : $value;
        }
        return $default;
    }

    /**
     * Value of a constant-only tunable (limits and trusted proxies), or its default.
     *
     * @return int|string
     */
    public function tunable( string $name ) {
        [ $constant, $default ] = self::TUNABLES[ $name ];
        [ $defined, $value ]    = ( $this->constant_lookup )( $constant );
        if ( ! $defined ) {
            return $default;
        }
        return is_int( $default ) ? max( 0, (int) $value ) : (string) $value;
    }

    /** Minimum seconds between loading a Form and submitting it, after the `jp_form_builder_min_fill_seconds` filter. */
    public function min_fill_seconds(): int {
        return max( 0, (int) apply_filters( 'jp_form_builder_min_fill_seconds', $this->tunable( 'min_fill_seconds' ) ) );
    }

    /** Whether both Turnstile keys are set. */
    public function turnstile_configured(): bool {
        return '' !== (string) $this->get( 'turnstile_site_key' ) && '' !== (string) $this->get( 'turnstile_secret_key' );
    }

    public function source( string $key ): string {
        if ( ! isset( self::DEFINITIONS[ $key ] ) ) {
            return 'default';
        }
        $constant = self::DEFINITIONS[ $key ][0];
        if ( '' !== $constant && ( $this->constant_lookup )( $constant )[0] ) {
            return 'constant';
        }
        $options = get_option( self::OPTION, [] );
        return is_array( $options ) && array_key_exists( $key, $options ) ? 'option' : 'default';
    }

    public function is_locked( string $key ): bool {
        return 'constant' === $this->source( $key );
    }

    /** @return string[] */
    public function keys(): array {
        return array_keys( self::DEFINITIONS );
    }

    public function is_secret( string $key ): bool {
        return 'secret' === $this->type( $key );
    }

    /** @return string The kind of value a setting holds, which decides its control and how it is sanitised; '' for an unknown key. */
    public function type( string $key ): string {
        return self::DEFINITIONS[ $key ][2] ?? '';
    }

    /**
     * Saves the values that are valid; a value that is not does not stop the others.
     *
     * Unknown keys and keys locked by a constant are ignored. An empty secret keeps the saved one.
     *
     * @param array<string,mixed> $values
     * @return array<string,string> The reason for each value that was rejected, by key.
     */
    public function save( array $values ): array {
        $options  = get_option( self::OPTION, [] );
        $options  = is_array( $options ) ? $options : [];
        $rejected = [];
        foreach ( $values as $key => $value ) {
            if ( ! isset( self::DEFINITIONS[ $key ] ) || $this->is_locked( $key ) ) {
                continue;
            }
            if ( $this->is_secret( $key ) ) {
                if ( '' !== trim( (string) $value ) ) {
                    $options[ $key ] = $this->secret_box->encrypt( trim( (string) $value ) );
                }
                continue;
            }
            try {
                $options[ $key ] = $this->sanitize( $this->type( $key ), $value );
            } catch ( \InvalidArgumentException $exception ) {
                $rejected[ $key ] = $exception->getMessage();
            }
        }
        update_option( self::OPTION, $options, false );
        return $rejected;
    }

    /**
     * @param mixed $value
     * @return string|null Why {@see self::save()} would reject the value, or null when it would accept it.
     */
    public function rejection( string $key, $value ): ?string {
        try {
            $this->sanitize( $this->type( $key ), $value );
        } catch ( \InvalidArgumentException $exception ) {
            return $exception->getMessage();
        }
        return null;
    }

    /** Removes the saved value, so the default applies again. A constant is not affected. */
    public function delete( string $key ): void {
        $options = get_option( self::OPTION, [] );
        if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
            unset( $options[ $key ] );
            update_option( self::OPTION, $options, false );
        }
    }

    public function has_undecryptable_secret(): bool {
        $options = get_option( self::OPTION, [] );
        if ( ! is_array( $options ) ) {
            return false;
        }
        foreach ( self::DEFINITIONS as $key => $definition ) {
            if ( 'secret' === $definition[2] && isset( $options[ $key ] ) && '' !== (string) $options[ $key ] && '' === $this->secret_box->decrypt( (string) $options[ $key ] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws \InvalidArgumentException With a translated reason when the value is not acceptable.
     */
    private function sanitize( string $type, $value ) {
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Reasons are plain translated text, escaped where they are shown.
        switch ( $type ) {
            case 'bool':
                return $this->parse_bool( $value );
            case 'port':
                return max( 1, min( 65535, absint( $value ) ) );
            case 'encryption':
                return in_array( $value, [ 'none', 'starttls', 'smtps' ], true ) ? $value : 'starttls';
            case 'auth_method':
                return in_array( $value, [ 'password', 'm365_oauth' ], true ) ? $value : 'password';
            case 'email':
                return sanitize_email( (string) $value );
            case 'email_list':
                return implode( ', ', Recipient_List::parse( (string) $value ) );
            case 'mail_layout':
                return '' === trim( (string) $value ) ? '' : ( new Mail_Layout( $this ) )->sanitize( (string) $value );
            case 'mail_body':
                return ( new Mail_Template() )->sanitize( (string) $value );
            default:
                return sanitize_text_field( (string) $value );
        }
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    /**
     * The one place that reads a yes/no value from a string, a number or a boolean.
     *
     * @param mixed $value
     * @throws \InvalidArgumentException When the value is none of true/false, 1/0, yes/no, on/off.
     */
    private function parse_bool( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_int( $value ) || is_string( $value ) ) {
            $word = strtolower( trim( (string) $value ) );
            if ( in_array( $word, [ '1', 'true', 'yes', 'on' ], true ) ) {
                return true;
            }
            if ( in_array( $word, [ '0', 'false', 'no', 'off' ], true ) ) {
                return false;
            }
        }
        throw new \InvalidArgumentException( __( 'Use true or false (also 1/0, yes/no, on/off).', 'jp-form-builder' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain translated text.
    }
}
