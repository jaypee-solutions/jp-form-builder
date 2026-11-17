<?php
/**
 * Unit tests for Form_Token.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class FormTokenTest extends TestCase {

    private const SECRET = 'secret';

    public function test_valid_token_within_age_window(): void {
        $form_token = new Form_Token();
        $token      = $form_token->issue( 17, 1000, self::SECRET );

        $this->assertTrue( $form_token->verify( $token, 17, 1003, 3, 7200, self::SECRET ) );
        $this->assertTrue( $form_token->verify( $token, 17, 8200, 3, 7200, self::SECRET ) );
    }

    public function test_token_that_is_too_young_or_too_old_is_rejected(): void {
        $form_token = new Form_Token();
        $token      = $form_token->issue( 17, 1000, self::SECRET );

        $this->assertFalse( $form_token->verify( $token, 17, 1002, 3, 7200, self::SECRET ) );
        $this->assertFalse( $form_token->verify( $token, 17, 8201, 3, 7200, self::SECRET ) );
        $this->assertFalse( $form_token->verify( $token, 17, 999, 0, 7200, self::SECRET ), 'issued in the future' );
    }

    public function test_token_is_bound_to_form_and_secret(): void {
        $form_token = new Form_Token();
        $token      = $form_token->issue( 17, 1000, self::SECRET );

        $this->assertFalse( $form_token->verify( $token, 18, 1010, 3, 7200, self::SECRET ) );
        $this->assertFalse( $form_token->verify( $token, 17, 1010, 3, 7200, 'other' ) );
    }

    public function test_tampered_and_malformed_tokens_are_rejected(): void {
        $form_token = new Form_Token();
        $token      = $form_token->issue( 17, 1000, self::SECRET );
        $signature  = substr( $token, strpos( $token, '.' ) + 1 );

        $this->assertFalse( $form_token->verify( '1.' . $signature, 17, 1010, 3, 7200, self::SECRET ), 'changed issued_at' );
        $flipped = substr( $token, 0, -1 ) . ( 'a' === substr( $token, -1 ) ? 'b' : 'a' );
        $this->assertFalse( $form_token->verify( $flipped, 17, 1010, 3, 7200, self::SECRET ), 'changed signature' );
        foreach ( [ '', 'abc', '1000.', '.' . $signature, '1000.' . strtoupper( $signature ), $token . 'x', '-1000.' . $signature ] as $malformed ) {
            $this->assertFalse( $form_token->verify( $malformed, 17, 1010, 3, 7200, self::SECRET ), $malformed );
        }
    }
}
