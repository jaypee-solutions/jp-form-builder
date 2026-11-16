<?php
/**
 * Unit tests for settings and secret storage.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['jp_form_builder_test_options'] = [];
	}

	public function test_settings_precedence_and_source(): void {
		$GLOBALS['jp_form_builder_test_options']['jp_form_builder_settings'] = [ 'smtp_host' => 'option.test' ];
		$settings = new Settings( static fn( string $name ): array => 'JP_FORM_BUILDER_SMTP_HOST' === $name ? [ true, 'constant.test' ] : [ false, null ] );
		$this->assertSame( 'constant.test', $settings->get( 'smtp_host' ) );
		$this->assertSame( 'constant', $settings->source( 'smtp_host' ) );
		$this->assertTrue( $settings->is_locked( 'smtp_host' ) );
		$this->assertSame( 'option', ( new Settings() )->source( 'smtp_host' ) );
		$this->assertSame( 'default', ( new Settings() )->source( 'smtp_port' ) );
		$this->assertSame( 587, ( new Settings() )->get( 'smtp_port' ) );
	}

	public function test_save_ignores_locked_values_and_keeps_empty_secret(): void {
		$settings = new Settings( static fn( string $name ): array => 'JP_FORM_BUILDER_SMTP_HOST' === $name ? [ true, 'locked.test' ] : [ false, null ] );
		$settings->save( [ 'smtp_host' => 'changed.test', 'smtp_password' => 'secret' ] );
		$stored = $GLOBALS['jp_form_builder_test_options']['jp_form_builder_settings'];
		$this->assertArrayNotHasKey( 'smtp_host', $stored );
		$this->assertStringStartsWith( 'v1:', $stored['smtp_password'] );
		$settings->save( [ 'smtp_password' => '' ] );
		$this->assertSame( 'secret', $settings->get( 'smtp_password' ) );
	}

	public function test_secret_box_round_trip_rejects_tampering_and_wrong_key(): void {
		$box = new Secret_Box( 'first-key' );
		$encrypted = $box->encrypt( 'confidential' );
		$this->assertSame( 'confidential', $box->decrypt( $encrypted ) );
		$this->assertSame( '', ( new Secret_Box( 'second-key' ) )->decrypt( $encrypted ) );
		$encoded = base64_decode( substr( $encrypted, 3 ), true );
		$encoded[ strlen( $encoded ) - 1 ] = chr( ord( $encoded[ strlen( $encoded ) - 1 ] ) ^ 1 );
		$this->assertSame( '', $box->decrypt( 'v1:' . base64_encode( $encoded ) ) );
	}
}
