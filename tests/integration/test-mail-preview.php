<?php
/**
 * Verifies access and rendering for mail previews.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Mail_Preview extends WP_UnitTestCase {

    public function test_preview_permission_rendering_and_layout_validation(): void {
        $request = static function ( array $params = [] ): WP_REST_Response {
            $rest = new WP_REST_Request( 'POST', '/jp-form-builder/v1/mail-preview' );
            $rest->set_header( 'content-type', 'application/json' );
            $rest->set_body( wp_json_encode( array_merge( [ 'kind' => 'notification', 'subject' => 'Hello {name}', 'body' => '<p>{name} {email} {message}</p>' ], $params ) ) );
            return rest_get_server()->dispatch( $rest );
        };
        wp_set_current_user( 0 );
        $this->assertSame( 401, $request()->get_status() );
        $editor = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor );
        $this->assertSame( 403, $request()->get_status() );
        add_filter( 'jp_form_builder_capability', static fn(): string => 'edit_posts' );
        do_action( 'init' );
        $this->assertSame( 200, $request()->get_status() );
        $this->assertSame( 403, $request( [ 'layout' => '' ] )->get_status() );
        remove_all_filters( 'jp_form_builder_capability' );
        $admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin );
        $preview = $request( [ 'body' => '<p>{name} {email} {message}</p><script>alert(1)</script>', 'layout' => '<section>{site_name}{content}</section>' ] );
        $this->assertSame( 200, $preview->get_status() );
        $data = $preview->get_data();
        $this->assertSame( 'Hello Jane Example', $data['subject'] );
        $this->assertStringContainsString( 'Jane Example', $data['html'] );
        $this->assertStringContainsString( 'jane@example.test', $data['html'] );
        $this->assertStringContainsString( get_bloginfo( 'name' ), $data['html'] );
        $this->assertStringNotContainsString( '<script', $data['html'] );
        $confirmation = $request( [ 'kind' => 'confirmation', 'body' => '<p>{name} {email} {message} {all_fields}</p>' ] );
        $this->assertSame( 200, $confirmation->get_status() );
        $html = $confirmation->get_data()['html'];
        $this->assertStringNotContainsString( 'Jane Example', $html );
        $this->assertStringNotContainsString( 'jane@example.test', $html );
        $this->assertStringNotContainsString( 'sample message', $html );
        $this->assertSame( 400, $request( [ 'layout' => '<p>missing content</p>' ] )->get_status() );
        $this->assertSame( 200, $request( [ 'layout' => '' ] )->get_status() );
        $this->assertSame( [], tests_retrieve_phpmailer_instance()->getToAddresses() );
    }

    /** @param array<string,mixed> $params */
    private function preview( array $params ): WP_REST_Response {
        $rest = new WP_REST_Request( 'POST', '/jp-form-builder/v1/mail-preview' );
        $rest->set_header( 'content-type', 'application/json' );
        $rest->set_body( wp_json_encode( array_merge( [ 'kind' => 'notification', 'subject' => '', 'body' => '' ], $params ) ) );
        return rest_get_server()->dispatch( $rest );
    }

    public function test_preview_with_fields_uses_the_given_labels_and_sample_values(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

        $response = $this->preview(
            [
                'subject' => 'Request from {company_contact}',
                'body'    => '<p>{all_fields}</p><p>{company_contact} / {#phone_number}{phone_number:label}: {phone_number}{/phone_number}</p>',
                'fields'  => [
                    [ 'key' => 'Company Contact', 'label' => 'Your <b>name</b>', 'type' => 'name', 'split' => true ],
                    [ 'key' => 'work_email', 'label' => 'Work email', 'type' => 'email' ],
                    [ 'key' => 'phone_number', 'label' => 'Call me on', 'type' => 'phone' ],
                    [ 'key' => 'zip', 'label' => 'Zip area', 'type' => 'postcode' ],
                    [ 'key' => 'land', 'label' => 'Residence', 'type' => 'country' ],
                    [ 'key' => 'agree', 'label' => 'Agree', 'type' => 'consent' ],
                ],
            ]
        );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( 'Request from Jane Example', $data['subject'] );
        foreach ( [ 'Your name', 'Work email', 'jane@example.test', 'Call me on', '+49821123456', 'Zip area', '86150', 'Residence', 'Germany', 'Yes (' ] as $expected ) {
            $this->assertStringContainsString( $expected, $data['html'] );
        }
        $this->assertStringNotContainsString( '<b>', $data['html'] );
        $this->assertStringNotContainsString( 'sample message', $data['html'] );
    }

    public function test_preview_shows_a_date_in_the_display_format_of_the_locale(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        $params = [
            'body'   => '{arrival}',
            'fields' => [ [ 'key' => 'arrival', 'label' => 'Arrival', 'type' => 'date' ] ],
        ];

        $this->assertStringContainsString( '2026-03-14', $this->preview( $params )->get_data()['html'] );

        $german = static fn(): string => 'de_DE';
        add_filter( 'locale', $german );
        $html = $this->preview( $params )->get_data()['html'];
        remove_filter( 'locale', $german );
        $this->assertStringContainsString( '14.03.2026', $html );
    }

    public function test_preview_with_fields_drops_invalid_entries_and_duplicate_keys(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

        $response = $this->preview(
            [
                'body'   => '{all_fields}',
                'fields' => [
                    [ 'key' => 'kept', 'label' => 'Kept', 'type' => 'text' ],
                    [ 'key' => 'kept', 'label' => 'Duplicate', 'type' => 'email' ],
                    [ 'key' => 'bad_type', 'label' => 'Bad type', 'type' => 'file' ],
                    [ 'key' => '!!!', 'label' => 'Bad key', 'type' => 'text' ],
                    [ 'key' => 'no_type', 'label' => 'No type' ],
                    'not an entry',
                ],
            ]
        );

        $this->assertSame( 200, $response->get_status() );
        $html = $response->get_data()['html'];
        $this->assertStringContainsString( 'Kept', $html );
        foreach ( [ 'Duplicate', 'Bad type', 'Bad key', 'No type', 'Jane Example' ] as $dropped ) {
            $this->assertStringNotContainsString( $dropped, $html );
        }
    }

    public function test_confirmation_preview_with_fields_contains_no_sample_values(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

        $response = $this->preview(
            [
                'kind'    => 'confirmation',
                'subject' => 'Thanks {work_email}',
                'body'    => '<p>{work_email} {work_email:label} {all_fields} {#work_email}shown{/work_email} {form_title}</p>',
                'fields'  => [ [ 'key' => 'work_email', 'label' => 'Work email', 'type' => 'email' ] ],
            ]
        );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertStringNotContainsString( 'jane@example.test', $data['html'] );
        $this->assertStringNotContainsString( 'Work email', $data['html'] );
        $this->assertStringNotContainsString( 'shown', $data['html'] );
        $this->assertStringContainsString( 'Sample form', $data['html'] );
        $this->assertSame( 'Thanks', $data['subject'] );
    }

    public function test_preview_with_fields_keeps_the_form_capability_requirement(): void {
        $fields = [ [ 'key' => 'name', 'label' => 'Name', 'type' => 'name' ] ];
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
        $this->assertSame( 403, $this->preview( [ 'fields' => $fields ] )->get_status() );

        add_filter( 'jp_form_builder_capability', static fn(): string => 'edit_posts' );
        $this->assertSame( 200, $this->preview( [ 'fields' => $fields ] )->get_status() );
        $this->assertSame( 403, $this->preview( [ 'fields' => $fields, 'layout' => '' ] )->get_status() );
        remove_all_filters( 'jp_form_builder_capability' );
    }

    public function test_preview_with_fields_accepts_radio_and_checkbox_types(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        $response = $this->preview(
            [
                'body'   => '{all_fields}',
                'fields' => [
                    [ 'key' => 'colour', 'label' => 'Colour', 'type' => 'radio' ],
                    [ 'key' => 'extras', 'label' => 'Extras', 'type' => 'checkbox' ],
                ],
            ]
        );

        $this->assertSame( 200, $response->get_status() );
        foreach ( [ 'Colour', 'Extras', 'Sample option', 'Another option' ] as $expected ) {
            $this->assertStringContainsString( $expected, $response->get_data()['html'] );
        }
    }
}
