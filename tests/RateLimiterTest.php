<?php
/**
 * Unit tests for Rate_Limiter.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['jp_form_builder_test_transients'] = [];
    }

    public function test_attempts_within_the_limit_pass_and_the_next_one_fails(): void {
        $limiter = new Rate_Limiter( 'key' );

        foreach ( [ 1, 2, 3 ] as $unused ) {
            $this->assertTrue( $limiter->attempt( 'rl', '203.0.113.9', 3, 600, 1000 ) );
        }
        $this->assertFalse( $limiter->attempt( 'rl', '203.0.113.9', 3, 600, 1001 ) );
        $this->assertFalse( $limiter->attempt( 'rl', '203.0.113.9', 3, 600, 1002 ), 'attempts over the limit still count' );
    }

    public function test_window_is_fixed_from_the_first_attempt(): void {
        $limiter = new Rate_Limiter( 'key' );

        $this->assertTrue( $limiter->attempt( 'rl', 'ip', 1, 600, 1000 ) );
        $this->assertFalse( $limiter->attempt( 'rl', 'ip', 1, 600, 1599 ) );
        $this->assertTrue( $limiter->attempt( 'rl', 'ip', 1, 600, 1600 ), 'a new window starts at 1000 + 600' );
        $this->assertFalse( $limiter->attempt( 'rl', 'ip', 1, 600, 1700 ) );
    }

    public function test_transient_expires_with_the_window(): void {
        $limiter = new Rate_Limiter( 'key' );

        $limiter->attempt( 'rl', 'ip', 5, 600, 1000 );
        $limiter->attempt( 'rl', 'ip', 5, 600, 1400 );

        $transient = array_values( $GLOBALS['jp_form_builder_test_transients'] );
        $this->assertCount( 1, $transient );
        $this->assertSame( 200, $transient[0]['expiration'] );
    }

    public function test_subjects_and_buckets_are_independent(): void {
        $limiter = new Rate_Limiter( 'key' );

        $this->assertTrue( $limiter->attempt( 'rl', 'ip-a', 1, 600, 1000 ) );
        $this->assertTrue( $limiter->attempt( 'rl', 'ip-b', 1, 600, 1000 ) );
        $this->assertTrue( $limiter->attempt( 'cf', 'ip-a', 1, 3600, 1000 ) );
        $this->assertFalse( $limiter->attempt( 'rl', 'ip-a', 1, 600, 1001 ) );
        $this->assertFalse( $limiter->attempt( 'cf', 'ip-a', 1, 3600, 1001 ) );
    }

    public function test_transient_name_contains_the_bucket_and_a_hash_but_not_the_subject(): void {
        $limiter = new Rate_Limiter( 'key' );

        $limiter->attempt( 'rl', '203.0.113.9', 5, 600, 1000 );
        $limiter->attempt( 'cf', 'jane@example.test', 1, 3600, 1000 );

        $names = array_keys( $GLOBALS['jp_form_builder_test_transients'] );
        $this->assertCount( 2, $names );
        $this->assertMatchesRegularExpression( '/^jp_form_builder_rl_[0-9a-f]{32}$/', $names[0] );
        $this->assertMatchesRegularExpression( '/^jp_form_builder_cf_[0-9a-f]{32}$/', $names[1] );
        $this->assertStringNotContainsString( '203.0.113.9', implode( ' ', $names ) );
        $this->assertStringNotContainsString( 'jane', implode( ' ', $names ) );
        $this->assertSame( 'jp_form_builder_rl_' . substr( hash_hmac( 'sha256', '203.0.113.9', 'key' ), 0, 32 ), $names[0] );
    }

    public function test_key_changes_the_hash(): void {
        ( new Rate_Limiter( 'one' ) )->attempt( 'rl', 'ip', 5, 600, 1000 );
        ( new Rate_Limiter( 'two' ) )->attempt( 'rl', 'ip', 5, 600, 1000 );

        $this->assertCount( 2, $GLOBALS['jp_form_builder_test_transients'] );
    }
}
