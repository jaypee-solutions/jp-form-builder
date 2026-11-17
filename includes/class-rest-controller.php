<?php
/**
 * Registers the public Form routes.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Rest_Controller {

    private Submission_Handler $handler;
    private Settings $settings;
    private Form_Repository $repository;
    private Form_Token $token;
    private Client_Ip $client_ip;

    public function __construct( Submission_Handler $handler, ?Settings $settings = null, ?Form_Repository $repository = null, ?Form_Token $token = null, ?Client_Ip $client_ip = null ) {
        $this->handler    = $handler;
        $this->settings   = $settings ?? new Settings();
        $this->repository = $repository ?? new Form_Repository();
        $this->token      = $token ?? new Form_Token();
        $this->client_ip  = $client_ip ?? new Client_Ip();
    }

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        // Public on purpose and without a nonce: a nonce would go stale behind page caches (ADR 0002).
        register_rest_route(
            'jp-form-builder/v1',
            '/forms/(?P<id>\d+)/token',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'token' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            'jp-form-builder/v1',
            '/forms/(?P<id>\d+)/submissions',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'submit' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /** @return \WP_REST_Response */
    public function token( \WP_REST_Request $request ) {
        $form_id = (int) $request['id'];
        if ( null === $this->repository->find( $form_id ) ) {
            return $this->respond( new \WP_Error( 'jpfb_form_not_found', __( 'This form does not exist.', 'jp-form-builder' ), [ 'status' => 404 ] ) );
        }
        return $this->respond( [ 'token' => $this->token->issue( $form_id, time(), wp_salt( 'nonce' ) ) ] );
    }

    /** @return \WP_REST_Response */
    public function submit( \WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $result  = $this->handler->handle(
            (int) $request['id'],
            is_array( $payload ) ? $payload : [],
            strlen( $request->get_body() ),
            $this->client_ip->resolve( $_SERVER, $this->client_ip->parse_trusted( (string) $this->settings->tunable( 'trusted_proxies' ) ) )
        );
        return $this->respond( $result );
    }

    /**
     * @param array<string,mixed>|\WP_Error $result
     * @return \WP_REST_Response
     */
    private function respond( $result ) {
        $response = is_wp_error( $result ) ? rest_convert_error_to_response( $result ) : new \WP_REST_Response( $result, 200 );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }
}
