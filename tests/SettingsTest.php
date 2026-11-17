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

    public function test_tunables_use_defaults_unless_a_constant_is_defined(): void {
        $defaults = new Settings();
        $this->assertSame( 32768, $defaults->tunable( 'max_payload_bytes' ) );
        $this->assertSame( 10, $defaults->tunable( 'rate_limit' ) );
        $this->assertSame( 600, $defaults->tunable( 'rate_window' ) );
        $this->assertSame( 3, $defaults->tunable( 'min_fill_seconds' ) );
        $this->assertSame( '', $defaults->tunable( 'trusted_proxies' ) );

        $constants = [
            'JP_FORM_BUILDER_RATE_LIMIT'      => '25',
            'JP_FORM_BUILDER_TRUSTED_PROXIES' => '10.0.0.0/8',
            'JP_FORM_BUILDER_RATE_WINDOW'     => -5,
        ];
        $settings  = new Settings( static fn( string $name ): array => isset( $constants[ $name ] ) ? [ true, $constants[ $name ] ] : [ false, null ] );
        $this->assertSame( 25, $settings->tunable( 'rate_limit' ) );
        $this->assertSame( '10.0.0.0/8', $settings->tunable( 'trusted_proxies' ) );
        $this->assertSame( 0, $settings->tunable( 'rate_window' ) );
        $this->assertSame( 3, $settings->tunable( 'min_fill_seconds' ) );
    }

    public function test_min_fill_seconds_applies_the_filter_and_never_goes_negative(): void {
        $this->assertSame( 3, ( new Settings() )->min_fill_seconds() );

        $GLOBALS['jp_form_builder_test_filters']['jp_form_builder_min_fill_seconds'] = static fn() => 0;
        $this->assertSame( 0, ( new Settings() )->min_fill_seconds() );

        $GLOBALS['jp_form_builder_test_filters']['jp_form_builder_min_fill_seconds'] = static fn() => -4;
        $this->assertSame( 0, ( new Settings() )->min_fill_seconds() );
        unset( $GLOBALS['jp_form_builder_test_filters']['jp_form_builder_min_fill_seconds'] );
    }

    public function test_turnstile_is_configured_only_with_both_keys(): void {
        $this->assertFalse( ( new Settings() )->turnstile_configured() );
        $GLOBALS['jp_form_builder_test_options']['jp_form_builder_settings'] = [ 'turnstile_site_key' => 'site' ];
        $this->assertFalse( ( new Settings() )->turnstile_configured() );
        ( new Settings() )->save( [ 'turnstile_secret_key' => 'secret' ] );
        $this->assertTrue( ( new Settings() )->turnstile_configured() );
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

    public function test_default_recipient_keeps_every_valid_address_of_a_comma_list(): void {
        $settings = new Settings();
        $this->assertSame( [], $settings->save( [ 'default_recipient' => 'a@x.de, b@y.de ,nope, a@x.de' ] ) );
        $this->assertSame( 'a@x.de, b@y.de', $settings->get( 'default_recipient' ) );
        $settings->save( [ 'default_recipient' => 'single@x.de' ] );
        $this->assertSame( 'single@x.de', $settings->get( 'default_recipient' ) );
        $settings->save( [ 'default_recipient' => 'nobody' ] );
        $this->assertSame( '', $settings->get( 'default_recipient' ) );
    }

    /** @return array<string,array{mixed,bool}> */
    public function accepted_booleans(): array {
        return [
            'true'      => [ 'true', true ],
            'TRUE'      => [ ' TRUE ', true ],
            'one'       => [ '1', true ],
            'yes'       => [ 'yes', true ],
            'on'        => [ 'on', true ],
            'false'     => [ 'false', false ],
            'zero'      => [ '0', false ],
            'no'        => [ 'No', false ],
            'off'       => [ 'off', false ],
            'bool true' => [ true, true ],
            'bool false' => [ false, false ],
            'int zero'  => [ 0, false ],
        ];
    }

    /** @dataProvider accepted_booleans */
    public function test_booleans_are_read_from_words_and_numbers( $input, bool $expected ): void {
        $settings = new Settings();
        $this->assertSame( [], $settings->save( [ 'smtp_auth' => $input ] ) );
        $this->assertSame( $expected, $settings->get( 'smtp_auth' ) );
    }

    public function test_an_unreadable_boolean_is_rejected_and_keeps_the_saved_value(): void {
        $settings = new Settings();
        $settings->save( [ 'smtp_auth' => 'false', 'force_from' => 'yes' ] );
        $rejected = $settings->save( [ 'smtp_auth' => 'maybe', 'force_from' => 'no', 'from_name' => 'Kept' ] );
        $this->assertSame( [ 'smtp_auth' ], array_keys( $rejected ) );
        $this->assertFalse( $settings->get( 'smtp_auth' ), 'the previous value stays' );
        $this->assertFalse( $settings->get( 'force_from' ), 'valid values are saved alongside' );
        $this->assertSame( 'Kept', $settings->get( 'from_name' ) );
        $this->assertNotNull( $settings->rejection( 'smtp_auth', 'maybe' ) );
        $this->assertNull( $settings->rejection( 'smtp_auth', 'off' ) );
    }

    public function test_an_invalid_layout_is_rejected_without_discarding_the_other_settings(): void {
        $settings = new Settings();
        $settings->save( [ 'mail_layout' => '<p>{content}</p>' ] );
        $rejected = $settings->save( [ 'from_name' => 'Kept', 'mail_layout' => '<p>no placeholder</p>', 'smtp_host' => 'mail.test' ] );
        $this->assertSame( [ 'mail_layout' ], array_keys( $rejected ) );
        $this->assertSame( '<p>{content}</p>', $settings->get( 'mail_layout' ), 'only the old layout is kept' );
        $this->assertSame( 'Kept', $settings->get( 'from_name' ) );
        $this->assertSame( 'mail.test', $settings->get( 'smtp_host' ) );
    }

    public function test_types_name_the_kind_of_every_setting(): void {
        $settings = new Settings();
        $this->assertSame( 'bool', $settings->type( 'smtp_auth' ) );
        $this->assertSame( 'port', $settings->type( 'smtp_port' ) );
        $this->assertSame( 'secret', $settings->type( 'smtp_password' ) );
        $this->assertSame( 'mail_layout', $settings->type( 'mail_layout' ) );
        $this->assertSame( '', $settings->type( 'unknown' ) );
    }

    public function test_delete_removes_the_saved_value(): void {
        $settings = new Settings();
        $settings->save( [ 'from_name' => 'Gone' ] );
        $settings->delete( 'from_name' );
        $this->assertSame( 'default', $settings->source( 'from_name' ) );
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
