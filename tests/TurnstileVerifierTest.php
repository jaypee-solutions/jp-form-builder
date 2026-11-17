<?php
/**
 * Unit tests for Turnstile_Verifier.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class TurnstileVerifierTest extends TestCase {

    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void {
        $GLOBALS['jp_form_builder_test_http_response']  = [ 'response' => [ 'code' => 200 ] ];
        $GLOBALS['jp_form_builder_test_http_responses'] = [];
        $GLOBALS['jp_form_builder_test_http_requests']  = [];
        $GLOBALS['jp_form_builder_test_filters']        = [];
    }

    protected function tearDown(): void {
        $GLOBALS['jp_form_builder_test_filters'] = [];
    }

    /** @param array<string,mixed> $body */
    private function answer( array $body, int $code = 200 ): void {
        $this->answer_raw( (string) json_encode( $body ), $code );
    }

    private function answer_raw( string $body, int $code = 200 ): void {
        $GLOBALS['jp_form_builder_test_http_responses'] = [
            'siteverify' => [
                'response' => [ 'code' => $code ],
                'body'     => $body,
            ],
        ];
    }

    private function verify( string $token = 'token' ): Turnstile_Result {
        return ( new Turnstile_Verifier() )->verify( $token, 'secret' );
    }

    public function test_passes_for_a_matching_hostname_and_action(): void {
        $this->answer( [ 'success' => true, 'hostname' => 'example.test', 'action' => 'jpfb-submit' ] );

        $result = $this->verify();

        $this->assertTrue( $result->is_passed() );
        $this->assertSame( Turnstile_Result::PASSED, $result->outcome );
    }

    public function test_request_carries_secret_and_token_but_no_visitor_ip(): void {
        $this->answer( [ 'success' => true, 'hostname' => 'example.test', 'action' => 'jpfb-submit' ] );

        $this->verify( 'the-token' );

        $requests = $GLOBALS['jp_form_builder_test_http_requests'];
        $this->assertCount( 1, $requests );
        $this->assertSame( self::ENDPOINT, $requests[0]['url'] );
        $this->assertSame( [ 'secret' => 'secret', 'response' => 'the-token' ], $requests[0]['args']['body'] );
        $this->assertArrayNotHasKey( 'remoteip', $requests[0]['args']['body'] );
        $this->assertGreaterThan( 0, $requests[0]['args']['timeout'] );
    }

    public function test_failed_answer_keeps_the_error_codes(): void {
        $this->answer( [ 'success' => false, 'error-codes' => [ 'invalid-input-response', 'timeout-or-duplicate' ] ] );

        $result = $this->verify();

        $this->assertTrue( $result->is_failed() );
        $this->assertSame( [ 'invalid-input-response', 'timeout-or-duplicate' ], $result->error_codes );
    }

    public function test_hostname_mismatch_fails(): void {
        $this->answer( [ 'success' => true, 'hostname' => 'evil.test', 'action' => 'jpfb-submit' ] );

        $this->assertTrue( $this->verify()->is_failed() );
    }

    public function test_hostname_comparison_ignores_case_and_the_filter_adds_hostnames(): void {
        $this->answer( [ 'success' => true, 'hostname' => 'Example.TEST', 'action' => 'jpfb-submit' ] );
        $this->assertTrue( $this->verify()->is_passed() );

        $this->answer( [ 'success' => true, 'hostname' => 'staging.test', 'action' => 'jpfb-submit' ] );
        $this->assertTrue( $this->verify()->is_failed() );

        $GLOBALS['jp_form_builder_test_filters']['jp_form_builder_turnstile_hostnames'] = static fn( array $hosts ): array => array_merge( $hosts, [ 'staging.test' ] );
        $this->assertTrue( $this->verify()->is_passed() );
    }

    public function test_action_mismatch_or_missing_fails(): void {
        $this->answer( [ 'success' => true, 'hostname' => 'example.test', 'action' => 'login' ] );
        $this->assertTrue( $this->verify()->is_failed() );

        $this->answer( [ 'success' => true, 'hostname' => 'example.test' ] );
        $this->assertTrue( $this->verify()->is_failed() );
    }

    public function test_empty_token_fails_without_a_request(): void {
        $result = $this->verify( '' );

        $this->assertTrue( $result->is_failed() );
        $this->assertSame( [], $GLOBALS['jp_form_builder_test_http_requests'] );
    }

    public function test_transport_error_is_unavailable(): void {
        $GLOBALS['jp_form_builder_test_http_responses'] = [
            'siteverify' => new class() {
                public function get_error_message(): string {
                    return 'cURL error 28';
                }
            },
        ];

        $this->assertTrue( $this->verify()->is_unavailable() );
    }

    public function test_non_200_status_is_unavailable(): void {
        $this->answer( [ 'success' => true, 'hostname' => 'example.test', 'action' => 'jpfb-submit' ], 503 );

        $this->assertTrue( $this->verify()->is_unavailable() );
    }

    public function test_malformed_body_is_unavailable(): void {
        foreach ( [ 'not json', '', '"success"', '{}', '[]' ] as $body ) {
            $this->answer_raw( $body );
            $this->assertTrue( $this->verify()->is_unavailable(), $body );
        }
    }
}
