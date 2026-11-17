<?php
/**
 * Integration tests for the Submission REST routes, driven through the REST server.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Submissions extends WP_UnitTestCase {

    private const FORM_CONTENT = '<!-- wp:jp-form-builder/form {"recipients":"team@example.test"} -->'
        . '<!-- wp:jp-form-builder/name {"fieldName":"name","required":true} /-->'
        . '<!-- wp:jp-form-builder/email {"fieldName":"email","required":true} /-->'
        . '<!-- wp:jp-form-builder/select {"fieldName":"topic","options":[{"label":"Sales team","value":"sales"}]} /-->'
        . '<!-- wp:jp-form-builder/address {"fieldName":"address"} /-->'
        . '<!-- wp:jp-form-builder/textarea {"fieldName":"message","required":true} /-->'
        . '<!-- wp:jp-form-builder/consent {"fieldName":"privacy"} /-->'
        . '<!-- /wp:jp-form-builder/form -->';

    private int $form_id = 0;

    /** @var array<int,array{string,array<string,mixed>}> */
    private array $cloudflare_requests = [];

    /** @var array<string,mixed>|WP_Error */
    private $cloudflare_answer = [];

    private string $remote_addr = '';

    public function set_up(): void {
        parent::set_up();
        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test' ] );
        $this->remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        wp_set_current_user( 0 );
        reset_phpmailer_instance();
        add_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
        $this->cloudflare_requests = [];
        $this->cloudflare_answer   = [
            'success'  => true,
            'hostname' => $this->home_host(),
            'action'   => 'jpfb-submit',
        ];
        add_filter( 'pre_http_request', [ $this, 'answer_cloudflare' ], 10, 3 );
        $this->form_id = $this->create_form( self::FORM_CONTENT );
    }

    public function tear_down(): void {
        $_SERVER['REMOTE_ADDR'] = $this->remote_addr;
        unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $GLOBALS['current_screen'] );
        parent::tear_down();
    }

    /** @return mixed */
    public function answer_cloudflare( $preempt, array $args, string $url ) {
        if ( false === strpos( $url, 'challenges.cloudflare.com' ) ) {
            return $preempt;
        }
        $this->cloudflare_requests[] = [ $url, $args ];
        if ( $this->cloudflare_answer instanceof WP_Error ) {
            return $this->cloudflare_answer;
        }
        return [
            'response' => [ 'code' => 200, 'message' => 'OK' ],
            'body'     => wp_json_encode( $this->cloudflare_answer ),
            'headers'  => [],
            'cookies'  => [],
            'filename' => null,
        ];
    }

    // wp-env pins WP_HOME, so the options cannot move the site to another host.
    private function home_host(): string {
        return (string) wp_parse_url( home_url(), PHP_URL_HOST );
    }

    private function create_form( string $content, string $status = 'publish', string $title = 'Contact' ): int {
        return self::factory()->post->create(
            [
                'post_type'    => 'jp_form',
                'post_status'  => $status,
                'post_title'   => $title,
                'post_content' => $content,
            ]
        );
    }

    private function enable_turnstile(): void {
        $settings = get_option( 'jp_form_builder_settings', [] );
        update_option(
            'jp_form_builder_settings',
            array_merge(
                $settings,
                [
                    'turnstile_site_key'   => 'site-key',
                    'turnstile_secret_key' => ( new \JayPee\FormBuilder\Secret_Box() )->encrypt( 'secret-key' ),
                ]
            )
        );
    }

    private function rev( int $form_id ): string {
        return ( new \JayPee\FormBuilder\Form_Repository() )->find( $form_id )->rev;
    }

    private function fetch_token( ?int $form_id = null ): string {
        $response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/jp-form-builder/v1/forms/' . ( $form_id ?? $this->form_id ) . '/token' ) );
        $this->assertSame( 200, $response->get_status() );
        return $response->get_data()['token'];
    }

    /** @return array<string,mixed> */
    private function valid_fields(): array {
        return [
            'name'    => 'Jane Example',
            'email'   => 'jane@example.test',
            'topic'   => 'sales',
            'address' => [
                'street'   => 'Hauptstrasse 1',
                'postcode' => '86150',
                'city'     => 'Augsburg',
                'country'  => 'DE',
            ],
            'message' => "Hello,\nplease call me.",
            'privacy' => '1',
        ];
    }

    /** @param array<string,mixed> $overrides */
    private function payload( array $overrides = [], ?int $form_id = null ): array {
        $form_id = $form_id ?? $this->form_id;
        return array_replace(
            [
                'token'     => $this->fetch_token( $form_id ),
                'turnstile' => 'turnstile-response',
                'hp'        => '',
                'page_url'  => home_url( '/contact/' ),
                'locale'    => '',
                'rev'       => $this->rev( $form_id ),
                'fields'    => $this->valid_fields(),
            ],
            $overrides
        );
    }

    /** @param array<string,mixed>|string $payload */
    private function submit( $payload, ?int $form_id = null ): WP_REST_Response {
        $request = new WP_REST_Request( 'POST', '/jp-form-builder/v1/forms/' . ( $form_id ?? $this->form_id ) . '/submissions' );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( is_string( $payload ) ? $payload : wp_json_encode( $payload ) );
        return rest_get_server()->dispatch( $request );
    }

    private function mails(): array {
        return tests_retrieve_phpmailer_instance()->mock_sent;
    }

    private function assert_error( WP_REST_Response $response, int $status, string $code ): void {
        $this->assertSame( $status, $response->get_status() );
        $this->assertSame( $code, $response->get_data()['code'] );
        $this->assertSame( $status, $response->get_data()['data']['status'] );
        $this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
    }

    public function test_token_route_is_public_uncached_and_only_for_published_forms(): void {
        $response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/jp-form-builder/v1/forms/' . $this->form_id . '/token' ) );
        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ 'token' ], array_keys( $response->get_data() ) );
        $this->assertMatchesRegularExpression( '/^\d+\.[0-9a-f]{64}$/', $response->get_data()['token'] );
        $this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );

        foreach ( [ $this->create_form( self::FORM_CONTENT, 'draft' ), 999999 ] as $id ) {
            $missing = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/jp-form-builder/v1/forms/' . $id . '/token' ) );
            $this->assert_error( $missing, 404, 'jpfb_form_not_found' );
        }
    }

    public function test_happy_path_sends_one_notification_with_reply_to_and_values(): void {
        $response = $this->submit( $this->payload() );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ 'message' => 'Thank you! Your message has been sent.' ], $response->get_data() );
        $this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
        $this->assertCount( 0, $this->cloudflare_requests, 'Turnstile is not configured, so Cloudflare is not asked.' );

        $mails = $this->mails();
        $this->assertCount( 1, $mails );
        $this->assertSame( 'team@example.test', tests_retrieve_phpmailer_instance()->get_recipient( 'to' )->address );
        $this->assertSame( 'New message via Contact', $mails[0]['subject'] );
        $this->assertStringContainsString( 'Reply-To: Jane Example <jane@example.test>', $mails[0]['header'] );
        foreach ( [ 'Jane Example', 'jane@example.test', 'Sales team', 'Hauptstrasse 1', '86150 Augsburg', 'Germany', 'please call me.', 'Yes (', home_url( '/contact/' ) ] as $expected ) {
            $this->assertStringContainsString( $expected, $mails[0]['body'] );
        }
    }

    public function test_form_message_recipient_fallbacks_and_foreign_page_url(): void {
        $content = '<!-- wp:jp-form-builder/form {"successMessage":"Danke!","recipients":"not-an-address, one@example.test"} --><!-- wp:jp-form-builder/email {"fieldName":"email"} /--><!-- /wp:jp-form-builder/form -->';
        $id      = $this->create_form( $content );
        $fields  = [ 'fields' => [ 'email' => 'jane@example.test' ], 'page_url' => 'https://evil.test/x' ];

        $response = $this->submit( $this->payload( $fields, $id ), $id );
        $this->assertSame( 'Danke!', $response->get_data()['message'] );
        $this->assertSame( 'one@example.test', tests_retrieve_phpmailer_instance()->get_recipient( 'to', 0 )->address );
        $this->assertStringNotContainsString( 'evil.test', $this->mails()[0]['body'] );

        $none = $this->create_form( '<!-- wp:jp-form-builder/form --><!-- wp:jp-form-builder/email {"fieldName":"email"} /--><!-- /wp:jp-form-builder/form -->' );
        $this->assertSame( 200, $this->submit( $this->payload( $fields, $none ), $none )->get_status() );
        $this->assertSame( get_option( 'admin_email' ), tests_retrieve_phpmailer_instance()->get_recipient( 'to', 1 )->address );

        update_option( 'jp_form_builder_settings', [ 'from_email' => 'forms@example.test', 'default_recipient' => 'default@example.test' ] );
        $this->submit( $this->payload( $fields, $none ), $none );
        $this->assertSame( 'default@example.test', tests_retrieve_phpmailer_instance()->get_recipient( 'to', 2 )->address );
    }

    public function test_honeypot_returns_a_fake_success_and_sends_nothing(): void {
        $response = $this->submit( $this->payload( [ 'hp' => 'spam' ] ) );
        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'Thank you! Your message has been sent.', $response->get_data()['message'] );
        $this->assertCount( 0, $this->mails() );
    }

    public function test_token_must_be_old_enough_genuine_and_for_the_same_form(): void {
        remove_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
        $this->assert_error( $this->submit( $this->payload() ), 400, 'jpfb_token_invalid' );

        add_filter( 'jp_form_builder_min_fill_seconds', '__return_zero' );
        $token = $this->fetch_token();
        $this->assert_error( $this->submit( $this->payload( [ 'token' => substr( $token, 0, -1 ) . ( 'a' === substr( $token, -1 ) ? 'b' : 'a' ) ] ) ), 400, 'jpfb_token_invalid' );
        $this->assert_error( $this->submit( $this->payload( [ 'token' => '' ] ) ), 400, 'jpfb_token_invalid' );

        $other = $this->create_form( self::FORM_CONTENT );
        $this->assert_error( $this->submit( $this->payload( [ 'token' => $this->fetch_token( $other ) ] ) ), 400, 'jpfb_token_invalid' );
        $this->assertCount( 0, $this->mails() );
    }

    public function test_turnstile_pass_failure_and_outage(): void {
        $this->enable_turnstile();

        $this->assertSame( 200, $this->submit( $this->payload() )->get_status() );
        $this->assertCount( 1, $this->cloudflare_requests );
        $this->assertSame( 'secret-key', $this->cloudflare_requests[0][1]['body']['secret'] );
        $this->assertSame( 'turnstile-response', $this->cloudflare_requests[0][1]['body']['response'] );
        $this->assertArrayNotHasKey( 'remoteip', $this->cloudflare_requests[0][1]['body'] );

        $this->cloudflare_answer = [ 'success' => false, 'error-codes' => [ 'invalid-input-response' ] ];
        $this->assert_error( $this->submit( $this->payload() ), 400, 'jpfb_turnstile_failed' );

        $this->cloudflare_answer = new WP_Error( 'http_request_failed', 'timeout' );
        $this->assert_error( $this->submit( $this->payload() ), 503, 'jpfb_turnstile_unavailable' );
        $this->assertCount( 1, $this->mails(), 'Only the passing Submission was mailed.' );

        $this->cloudflare_requests = [];
        $this->cloudflare_answer   = [ 'success' => true, 'hostname' => $this->home_host(), 'action' => 'jpfb-submit' ];
        $this->assert_error( $this->submit( $this->payload( [ 'turnstile' => '' ] ) ), 400, 'jpfb_turnstile_failed' );
        $this->assertCount( 0, $this->cloudflare_requests, 'An empty token fails without a request.' );
    }

    public function test_rate_limit_counts_every_attempt_per_resolved_ip(): void {
        $attempt = fn(): WP_REST_Response => $this->submit( '{}' );
        for ( $i = 1; $i <= 10; $i++ ) {
            $this->assertSame( 400, $attempt()->get_status(), "Attempt $i is within the limit." );
        }
        $this->assert_error( $attempt(), 429, 'jpfb_rate_limited' );

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
        $this->assert_error( $attempt(), 429, 'jpfb_rate_limited' );
        unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';
        $this->assertSame( 400, $attempt()->get_status(), 'Another IP is not limited.' );
    }

    public function test_oversized_body_and_unknown_forms_are_rejected(): void {
        $this->assert_error( $this->submit( $this->payload( [ 'fields' => [ 'message' => str_repeat( 'a', 40000 ) ] ] ) ), 413, 'jpfb_payload_too_large' );
        $this->assertCount( 0, $this->mails() );

        $draft = $this->create_form( self::FORM_CONTENT, 'draft' );
        $this->assert_error( $this->submit( '{}', $draft ), 404, 'jpfb_form_not_found' );
        $this->assert_error( $this->submit( '{}', 999999 ), 404, 'jpfb_form_not_found' );
    }

    public function test_invalid_fields_return_422_with_keyed_errors(): void {
        $fields = $this->valid_fields();
        $fields['email']   = 'not-an-email';
        $fields['name']    = '';
        $fields['address'] = [ 'street' => 'Hauptstrasse 1', 'postcode' => '', 'city' => '', 'country' => 'DE' ];
        $fields['privacy'] = '';

        $response = $this->submit( $this->payload( [ 'fields' => $fields ] ) );
        $this->assert_error( $response, 422, 'jpfb_invalid_fields' );
        $this->assertSame( 'Please correct the marked fields.', $response->get_data()['message'] );
        $errors = $response->get_data()['data']['errors'];
        $this->assertSame( [ 'address.city', 'address.postcode', 'email', 'name', 'privacy' ], $this->sorted_keys( $errors ) );
        $this->assertSame( 'Please enter a valid email address.', $errors['email'] );
        $this->assertCount( 0, $this->mails() );
    }

    /** @param array<string,string> $errors @return string[] */
    private function sorted_keys( array $errors ): array {
        $keys = array_keys( $errors );
        sort( $keys );
        return $keys;
    }

    public function test_locale_switches_for_the_request_and_unknown_locales_are_ignored(): void {
        $translate = static function ( $translated, $text, $domain ) {
            $german = [
                'Please correct the marked fields.' => 'Bitte korrigieren Sie die markierten Felder.',
                'Email'                             => 'E-Mail',
                '%s is required.'                   => '%s ist ein Pflichtfeld.',
                'Thank you! Your message has been sent.' => 'Vielen Dank! Ihre Nachricht wurde gesendet.',
                'Yes (%s)'                          => 'Ja (%s)',
            ];
            return 'jp-form-builder' === $domain && 'de_DE' === determine_locale() && isset( $german[ $text ] ) ? $german[ $text ] : $translated;
        };
        $available = static fn( array $languages ): array => array_merge( $languages, [ 'de_DE' ] );
        add_filter( 'gettext', $translate, 10, 3 );
        add_filter( 'get_available_languages', $available );
        $original_switcher = $GLOBALS['wp_locale_switcher'];
        $switcher          = new WP_Locale_Switcher();
        $GLOBALS['wp_locale_switcher'] = $switcher;
        $switcher->init();
        try {
            $fields          = $this->valid_fields();
            $fields['email'] = '';
            $invalid         = $this->submit( $this->payload( [ 'locale' => 'de_DE', 'fields' => $fields ] ) );
            $this->assertSame( 'Bitte korrigieren Sie die markierten Felder.', $invalid->get_data()['message'] );
            $this->assertSame( 'E-Mail ist ein Pflichtfeld.', $invalid->get_data()['data']['errors']['email'] );
            $this->assertSame( 'en_US', determine_locale(), 'The locale is restored after the request.' );

            $sent = $this->submit( $this->payload( [ 'locale' => 'de_DE' ] ) );
            $this->assertSame( 'Vielen Dank! Ihre Nachricht wurde gesendet.', $sent->get_data()['message'] );
            $this->assertStringContainsString( 'Ja (', $this->mails()[0]['body'] );

            $english = $this->submit( $this->payload( [ 'locale' => 'xx_XX', 'fields' => $fields ] ) );
            $this->assertSame( 'Please correct the marked fields.', $english->get_data()['message'] );
        } finally {
            remove_filter( 'locale', [ $switcher, 'filter_locale' ] );
            remove_filter( 'determine_locale', [ $switcher, 'filter_locale' ] );
            $GLOBALS['wp_locale_switcher'] = $original_switcher;
            remove_filter( 'gettext', $translate, 10 );
            remove_filter( 'get_available_languages', $available );
        }
    }

    public function test_stale_rev_validates_against_the_old_fields_and_unknown_rev_is_a_conflict(): void {
        $old_rev = $this->rev( $this->form_id );
        wp_save_post_revision( $this->form_id );
        wp_update_post(
            [
                'ID'           => $this->form_id,
                'post_content' => str_replace( '<!-- wp:jp-form-builder/consent', '<!-- wp:jp-form-builder/phone {"fieldName":"phone","required":true} /--><!-- wp:jp-form-builder/consent', self::FORM_CONTENT ),
            ]
        );
        $this->assertNotSame( $old_rev, $this->rev( $this->form_id ) );

        $stale = $this->submit( $this->payload( [ 'rev' => $old_rev ] ) );
        $this->assertSame( 200, $stale->get_status(), 'The old Fields did not include the new required phone.' );

        $current = $this->submit( $this->payload() );
        $this->assert_error( $current, 422, 'jpfb_invalid_fields' );
        $this->assertArrayHasKey( 'phone', $current->get_data()['data']['errors'] );

        $this->assert_error( $this->submit( $this->payload( [ 'rev' => '0000000000000000' ] ) ), 409, 'jpfb_form_changed' );
        $this->assert_error( $this->submit( $this->payload( [ 'rev' => '' ] ) ), 409, 'jpfb_form_changed' );
        $this->assertSame( 'This form was updated. Please reload the page.', $this->submit( $this->payload( [ 'rev' => '' ] ) )->get_data()['message'] );
    }

    public function test_mail_failure_returns_a_generic_500_and_logs_the_form_id(): void {
        $injections = [
            'invalid from'  => [ 'wp_mail_from', static fn(): string => 'not an address', 2000 ],
            'pre_wp_mail'   => [ 'pre_wp_mail', '__return_false', 10 ],
        ];
        foreach ( $injections as $name => [ $hook, $callback, $priority ] ) {
            add_filter( $hook, $callback, $priority );
            $response = $this->submit( $this->payload() );
            $this->assert_error( $response, 500, 'jpfb_mail_failed' );
            $this->assertStringNotContainsString( 'jane@example.test', wp_json_encode( $response->get_data() ), $name );
            $this->assertStringNotContainsString( 'address', $response->get_data()['message'], $name );
            remove_filter( $hook, $callback, $priority );
        }

        $log = get_option( 'jp_form_builder_mail_log', [] );
        $this->assertCount( 2, $log, wp_json_encode( $log ) );
        $this->assertSame( $this->form_id, $log[0]['form_id'] );
        $this->assertSame( $this->form_id, $log[1]['form_id'] );
        $this->assertStringNotContainsString( 'jane@example.test', wp_json_encode( $log ) );

        add_filter( 'pre_wp_mail', '__return_false' );
        wp_mail( 'someone@example.test', 'Other mail', 'body' );
        remove_filter( 'pre_wp_mail', '__return_false' );
        $this->assertCount( 2, get_option( 'jp_form_builder_mail_log', [] ), 'Mail not sent by the plugin is not logged by wp_mail itself.' );
        do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Other failure' ) );
        $log = get_option( 'jp_form_builder_mail_log', [] );
        $this->assertNull( end( $log )['form_id'] );
    }

    public function test_confirmation_needs_turnstile_and_is_throttled_per_address(): void {
        $content = '<!-- wp:jp-form-builder/form {"confirmationEnabled":true,"recipients":"team@example.test"} --><!-- wp:jp-form-builder/email {"fieldName":"email","required":true} /--><!-- /wp:jp-form-builder/form -->';
        $id      = $this->create_form( $content );
        $send    = fn( string $email ): WP_REST_Response => $this->submit( $this->payload( [ 'fields' => [ 'email' => $email ] ], $id ), $id );

        $this->assertSame( 200, $send( 'jane@example.test' )->get_status() );
        $this->assertCount( 1, $this->mails(), 'Without Turnstile only the Notification is sent.' );

        $this->enable_turnstile();
        $this->assertSame( 200, $send( 'jane@example.test' )->get_status() );
        $mails = $this->mails();
        $this->assertCount( 3, $mails );
        $mailer = tests_retrieve_phpmailer_instance();
        $this->assertSame( 'jane@example.test', $mailer->get_recipient( 'to', 2 )->address );
        $this->assertStringContainsString( 'Reply-To: team@example.test', $mails[2]['header'] );
        $this->assertStringNotContainsString( 'Jane', $mails[2]['body'] );

        $this->assertSame( 200, $send( 'Jane@Example.test' )->get_status() );
        $this->assertCount( 4, $this->mails(), 'The second Confirmation to the same address within the hour is skipped.' );

        $this->assertSame( 200, $send( 'other@example.test' )->get_status() );
        $this->assertCount( 6, $this->mails() );
        $this->assertSame( 'other@example.test', $mailer->get_recipient( 'to', 5 )->address );
    }

    public function test_confirmation_failure_does_not_fail_the_submission_and_is_logged(): void {
        $content = '<!-- wp:jp-form-builder/form {"confirmationEnabled":true} --><!-- wp:jp-form-builder/email {"fieldName":"email"} /--><!-- /wp:jp-form-builder/form -->';
        $id      = $this->create_form( $content );
        $this->enable_turnstile();
        $payload = $this->payload( [ 'fields' => [ 'email' => 'jane@example.test' ] ], $id );

        // Fail only the mail that goes to the Submitter.
        $fail_confirmation = static function ( $short_circuit, array $atts ) {
            return in_array( 'jane@example.test', (array) $atts['to'], true ) ? false : $short_circuit;
        };
        add_filter( 'pre_wp_mail', $fail_confirmation, 10, 2 );
        $response = $this->submit( $payload, $id );
        remove_filter( 'pre_wp_mail', $fail_confirmation, 10 );

        $this->assertSame( 200, $response->get_status() );
        $log = get_option( 'jp_form_builder_mail_log', [] );
        $this->assertCount( 1, $log );
        $this->assertSame( $id, $log[0]['form_id'] );
    }

    private function render_notices( string $screen ): string {
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        set_current_screen( $screen );
        ob_start();
        do_action( 'admin_notices' );
        return (string) ob_get_clean();
    }

    public function test_settings_notices_only_appear_on_plugin_screens_and_for_admins(): void {
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $this->create_form( '<!-- wp:jp-form-builder/form {"confirmationEnabled":true} /-->', 'publish', 'Needs Turnstile' );
        $this->create_form( '<!-- wp:jp-form-builder/form /-->', 'publish', 'Plain form' );
        $_SERVER['REMOTE_ADDR']          = '10.0.0.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

        foreach ( [ 'jp_form_page_jp-form-builder-settings', 'edit-jp_form' ] as $screen ) {
            $output = $this->render_notices( $screen );
            $this->assertStringContainsString( 'Cloudflare Turnstile is not configured', $output, $screen );
            $this->assertStringContainsString( 'JP_FORM_BUILDER_TRUSTED_PROXIES', $output, $screen );
            $this->assertStringContainsString( 'Needs Turnstile</a>', $output, $screen );
            $this->assertStringContainsString( 'post.php?post=', $output, $screen );
            $this->assertStringNotContainsString( 'Plain form', $output, $screen );
        }

        $this->assertSame( '', trim( $this->render_notices( 'dashboard' ) ) );

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
        $this->assertSame( '', trim( $this->render_notices( 'edit-jp_form' ) ) );

        wp_set_current_user( $admin );
        $this->enable_turnstile();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        $this->assertSame( '', trim( $this->render_notices( 'edit-jp_form' ) ), 'A configured, direct setup raises no notice.' );
    }
}
