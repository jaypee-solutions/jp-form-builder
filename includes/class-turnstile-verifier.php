<?php
/**
 * Verifies Cloudflare Turnstile tokens server-side.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Turnstile_Verifier {

    public const ACTION = 'jpfb-submit';

    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const TIMEOUT = 10;

    /**
     * Sends only the secret and the token (no visitor IP) to Cloudflare.
     *
     * Passes only when Cloudflare reports success for one of this site's hostnames and the Form action.
     */
    public function verify( string $token, string $secret ): Turnstile_Result {
        if ( '' === $token ) {
            return Turnstile_Result::failed( [ 'missing-input-response' ] );
        }

        $response = wp_remote_post(
            self::ENDPOINT,
            [
                'timeout' => self::TIMEOUT,
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                ],
            ]
        );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return Turnstile_Result::unavailable();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || ! isset( $body['success'] ) ) {
            return Turnstile_Result::unavailable();
        }

        if ( true !== $body['success'] ) {
            $codes = $body['error-codes'] ?? [];
            return Turnstile_Result::failed( is_array( $codes ) ? array_values( array_filter( $codes, 'is_string' ) ) : [] );
        }

        $hostname = isset( $body['hostname'] ) && is_string( $body['hostname'] ) ? strtolower( $body['hostname'] ) : '';
        $action   = isset( $body['action'] ) && is_string( $body['action'] ) ? $body['action'] : '';
        if ( ! in_array( $hostname, $this->allowed_hostnames(), true ) || self::ACTION !== $action ) {
            return Turnstile_Result::failed();
        }
        return Turnstile_Result::passed();
    }

    /** @return string[] */
    private function allowed_hostnames(): array {
        $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $hostnames = apply_filters( 'jp_form_builder_turnstile_hostnames', is_string( $home_host ) ? [ $home_host ] : [] );

        return array_values( array_map( 'strtolower', array_filter( (array) $hostnames, 'is_string' ) ) );
    }
}
