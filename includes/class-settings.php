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
		'default_recipient' => [ 'JP_FORM_BUILDER_DEFAULT_RECIPIENT', '', 'email' ],
		'smtp_auth_method' => [ 'JP_FORM_BUILDER_SMTP_AUTH_METHOD', 'password', 'auth_method' ],
		'm365_tenant_id' => [ 'JP_FORM_BUILDER_M365_TENANT_ID', '', 'text' ],
		'm365_client_id' => [ 'JP_FORM_BUILDER_M365_CLIENT_ID', '', 'text' ],
		'm365_client_secret' => [ 'JP_FORM_BUILDER_M365_CLIENT_SECRET', '', 'secret' ],
		'mail_layout' => [ '', '', 'textarea' ],
		'notification_subject' => [ '', '', 'single_line' ],
		'notification_body' => [ '', '', 'textarea' ],
		'confirmation_subject' => [ '', '', 'single_line' ],
		'confirmation_body' => [ '', '', 'textarea' ],
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

	/** @param array<string,mixed> $values */
	public function save( array $values ): void {
		$options = get_option( self::OPTION, [] );
		$options = is_array( $options ) ? $options : [];
		foreach ( $values as $key => $value ) {
			if ( ! isset( self::DEFINITIONS[ $key ] ) || $this->is_locked( $key ) ) {
				continue;
			}
			[ , , $type ] = self::DEFINITIONS[ $key ];
			if ( 'secret' === $type ) {
				if ( '' !== trim( (string) $value ) ) {
					$options[ $key ] = $this->secret_box->encrypt( trim( (string) $value ) );
				}
				continue;
			}
			$options[ $key ] = $this->sanitize( $type, $value );
		}
		update_option( self::OPTION, $options, false );
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

	/** @param mixed $value @return mixed */
	private function sanitize( string $type, $value ) {
		switch ( $type ) {
			case 'bool':
				return (bool) $value;
			case 'port':
				return max( 1, min( 65535, absint( $value ) ) );
			case 'encryption':
				return in_array( $value, [ 'none', 'starttls', 'smtps' ], true ) ? $value : 'starttls';
			case 'auth_method':
				return in_array( $value, [ 'password', 'm365_oauth' ], true ) ? $value : 'password';
			case 'email':
				return sanitize_email( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'single_line':
				return sanitize_text_field( (string) $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
