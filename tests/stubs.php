<?php
/**
 * Minimal namespace-local stubs for the handful of WordPress functions the
 * pure-logic methods under test call. PHP resolves unqualified calls inside
 * JayPee\FormBuilder to these before falling back to the global namespace.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

/**
 * @var array<string,mixed> Backing store for the get_option() stub.
 */
$GLOBALS['jp_form_builder_test_options'] = [];

/**
 * @var array<string,mixed> Per-endpoint answers for the wp_remote_get() stub,
 *                          keyed by a substring of the requested URL; the first
 *                          match wins. Values are either a response array
 *                          ( 'response' => [ 'code' => int ], 'body', 'headers' )
 *                          or an object with get_error_message().
 */
$GLOBALS['jp_form_builder_test_http_responses'] = [];

/**
 * @var mixed Fallback answer when no per-endpoint entry matches.
 */
$GLOBALS['jp_form_builder_test_http_response'] = [ 'response' => [ 'code' => 200 ] ];

/**
 * @var array<int,array{url:string,args:array<string,mixed>}> Every request the
 *                          wp_remote_get() stub has seen, in order.
 */
$GLOBALS['jp_form_builder_test_http_requests'] = [];

/**
 * @var array<string,array{value:mixed,expiration:int}> Backing store for the
 *                          get_transient() / set_transient() stubs.
 */
$GLOBALS['jp_form_builder_test_transients'] = [];

/**
 * @var array<string,callable> Callback per hook name for the apply_filters() stub.
 */
$GLOBALS['jp_form_builder_test_filters'] = [];

// Constants the plugin file defines; the tests do not load it (it bootstraps WP).
const VERSION     = '0.0.0-test';
const PLUGIN_FILE = __FILE__;
const GITHUB_REPO = 'jaypee-solutions/jp-form-builder';
const UPDATE_URL  = 'https://github.com/jaypee-solutions/jp-form-builder/';

/**
 * @param mixed $default_value
 * @return mixed
 */
function get_option( string $option, $default_value = false ) {
    return $GLOBALS['jp_form_builder_test_options'][ $option ] ?? $default_value;
}

function wp_salt( string $scheme = 'auth' ): string {
    return 'unit-test-salt-' . $scheme;
}

function is_email( $email ) {
    return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function absint( $value ): int {
    return abs( (int) $value );
}

function sanitize_email( string $value ): string {
    return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

function sanitize_text_field( string $value ): string {
    return trim( strip_tags( $value ) );
}

function sanitize_textarea_field( string $value ): string {
    return trim( strip_tags( $value ) );
}

/**
 * @param mixed $value
 * @param mixed $autoload
 */
function update_option( string $option, $value, $autoload = null ): bool {
    $GLOBALS['jp_form_builder_test_options'][ $option ] = $value;
    return true;
}

/**
 * @param array<string,mixed> $args
 * @return mixed
 */
function stub_http_request( string $url, array $args ) {
    $GLOBALS['jp_form_builder_test_http_requests'][] = [
        'url'  => $url,
        'args' => $args,
    ];

    foreach ( $GLOBALS['jp_form_builder_test_http_responses'] as $needle => $response ) {
        if ( false !== strpos( $url, (string) $needle ) ) {
            return $response;
        }
    }

    return $GLOBALS['jp_form_builder_test_http_response'];
}

/**
 * @param array<string,mixed> $args
 * @return mixed
 */
function wp_remote_get( string $url, array $args = [] ) {
    return stub_http_request( $url, $args );
}

/**
 * @param array<string,mixed> $args
 * @return mixed
 */
function wp_remote_post( string $url, array $args = [] ) {
    return stub_http_request( $url, $args );
}

/**
 * @return mixed
 */
function get_transient( string $transient ) {
    return $GLOBALS['jp_form_builder_test_transients'][ $transient ]['value'] ?? false;
}

/**
 * @param mixed $value
 */
function set_transient( string $transient, $value, int $expiration = 0 ): bool {
    $GLOBALS['jp_form_builder_test_transients'][ $transient ] = [
        'value'      => $value,
        'expiration' => $expiration,
    ];
    return true;
}

/**
 * @param mixed $thing
 */
function is_wp_error( $thing ): bool {
    return is_object( $thing ) && method_exists( $thing, 'get_error_message' );
}

/**
 * @param mixed $response
 */
function wp_remote_retrieve_response_code( $response ): int {
    return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
}

/**
 * @param mixed $response
 */
function wp_remote_retrieve_body( $response ): string {
    return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
}

/**
 * @param mixed $response
 * @return mixed
 */
function wp_remote_retrieve_header( $response, string $header ) {
    return is_array( $response ) ? ( $response['headers'][ $header ] ?? '' ) : '';
}

function __( string $text, string $domain = 'default' ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore
    return $text;
}

function esc_html( string $text ): string {
    // Explicit charset: WordPress escapes as UTF-8 regardless of default_charset.
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( string $text ): string {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function determine_locale(): string {
    return 'en_US';
}

function get_privacy_policy_url(): string {
    return 'https://example.test/privacy';
}

function selected( $selected, $current = true, bool $should_echo = true ): string {
    unset( $should_echo );
    return (string) $selected === (string) $current ? ' selected="selected"' : '';
}

function esc_html__( string $text, string $domain = 'default' ): string {
    return esc_html( $text );
}

function esc_url( string $url ): string {
    return $url;
}

function wp_kses_allowed_html( string $context ): array {
    unset( $context );
    return [ 'p' => [], 'br' => [], 'strong' => [], 'em' => [], 'a' => [ 'href' => true ], 'table' => [], 'tr' => [], 'th' => [], 'td' => [] ];
}

function wp_kses( string $content, array $allowed_html, array $allowed_protocols = [] ): string {
    unset( $allowed_html, $allowed_protocols );
    return strip_tags( $content, '<p><br><strong><em><a><table><tr><th><td>' );
}


function apply_filters( string $hook, $value ) {
    $callback = $GLOBALS['jp_form_builder_test_filters'][ $hook ] ?? null;
    return null === $callback ? $value : $callback( $value );
}

function wp_strip_all_tags( string $text ): string {
    return strip_tags( $text );
}

function get_bloginfo( string $show = '' ): string {
    unset( $show );
    return 'Example Site';
}

function home_url( string $path = '/' ): string {
    return 'https://example.test' . $path;
}
function wp_parse_url( string $url, int $component = -1 ) {
    return parse_url( $url, $component );
}

function rest_url( string $path = '' ): string {
    return 'https://example.test/wp-json/' . $path;
}

function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . $path;
}

/**
 * @param mixed $callback
 */
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return true;
}

/**
 * @param mixed $callback
 */
function remove_filter( string $hook, $callback, int $priority = 10 ): bool {
    return true;
}

/**
 * @param mixed $callback
 */
function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return true;
}

function is_admin(): bool {
    return false;
}

function wp_json_encode( $value ): string {
    return (string) json_encode( $value );
}

/** @param array<string,mixed> $block */
function render_block( array $block ): string {
    return (string) ( $block['innerHTML'] ?? '' );
}
