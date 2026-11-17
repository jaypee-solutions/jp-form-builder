<?php
/**
 * Integration tests for Backup export, restore and uninstall, driven through admin-post, the Plugins list and uninstall.php.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Backup_Handler_Denied extends Exception {}

final class Backup_Handler_Redirect extends Exception {}

final class Test_Form_Backup extends WP_UnitTestCase {

    private const FORM_CONTENT = '<!-- wp:jp-form-builder/form {"recipients":"team@example.test"} -->'
        . '<!-- wp:jp-form-builder/name {"fieldName":"name","required":true} /-->'
        . '<!-- wp:jp-form-builder/email {"fieldName":"email","required":true} /-->'
        . '<!-- /wp:jp-form-builder/form -->';

    private const OTHER_CONTENT = '<!-- wp:jp-form-builder/form {"recipients":"other@example.test"} -->'
        . '<!-- wp:jp-form-builder/textarea {"fieldName":"message"} /-->'
        . '<!-- /wp:jp-form-builder/form -->';

    private string $redirect = '';

    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        update_option( 'jp_form_builder_settings', [] );
    }

    public function tear_down(): void {
        $_POST    = [];
        $_GET     = [];
        $_REQUEST = [];
        $_FILES   = [];
        remove_all_filters( 'wp_die_handler' );
        remove_all_filters( 'wp_redirect' );
        parent::tear_down();
    }

    private function create_form( string $content = self::FORM_CONTENT, string $status = 'publish', string $title = 'Contact' ): int {
        return self::factory()->post->create( [ 'post_type' => 'jp_form', 'post_status' => $status, 'post_title' => $title, 'post_content' => $content ] );
    }

    private function embed_markup( int $form_id ): string {
        return '<!-- wp:jp-form-builder/embed {"formId":' . $form_id . '} /-->';
    }

    /** @param array<int,array<string,mixed>> $forms @param array<string,mixed> $settings */
    private function document( array $forms, array $settings = [] ): string {
        return (string) wp_json_encode( [ 'format' => 'jp-form-builder-backup', 'version' => 1, 'forms' => $forms, 'settings' => $settings ] );
    }

    /** @return array<string,mixed> */
    private function entry( int $id, string $content = self::FORM_CONTENT, string $title = 'Restored', string $status = 'publish' ): array {
        return [ 'id' => $id, 'title' => $title, 'status' => $status, 'slug' => 'restored-' . $id, 'content' => $content ];
    }

    private function use_throwing_handlers(): void {
        add_filter(
            'wp_die_handler',
            static function (): callable {
                return static function (): void {
                    throw new Backup_Handler_Denied();
                };
            }
        );
        add_filter(
            'wp_redirect',
            static function ( $location ) {
                throw new Backup_Handler_Redirect( (string) $location );
            }
        );
    }

    /**
     * Submits the restore form as the current user and returns the stored report.
     *
     * @param array<string,mixed>|null $file Raw $_FILES entry; defaults to an upload of $contents.
     * @return array<string,mixed>
     */
    private function upload( string $contents, bool $overwrite = false, ?array $file = null ): array {
        $path = wp_tempnam( 'jpfb-backup' );
        file_put_contents( $path, $contents );
        $_FILES   = [ 'jp_form_builder_backup' => $file ?? [ 'name' => 'backup.json', 'type' => 'application/json', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen( $contents ) ] ];
        $_POST    = [ 'action' => 'jp_form_builder_restore_backup', '_wpnonce' => wp_create_nonce( 'jp_form_builder_restore_backup' ) ];
        if ( $overwrite ) {
            $_POST['jp_form_builder_overwrite'] = '1';
        }
        $_REQUEST = $_POST;
        $this->use_throwing_handlers();
        try {
            do_action( 'admin_post_jp_form_builder_restore_backup' );
            $this->fail( 'The restore handler did not redirect.' );
        } catch ( Backup_Handler_Redirect $exception ) {
            $this->redirect = $exception->getMessage();
        } finally {
            unlink( $path );
        }
        $report = get_transient( 'jp_form_builder_backup_report_' . get_current_user_id() );
        $this->assertIsArray( $report );
        return $report;
    }

    private function assert_handler_denied( string $action ): void {
        $this->use_throwing_handlers();
        try {
            do_action( 'admin_post_' . $action );
            $this->fail( 'The handler was not denied.' );
        } catch ( Backup_Handler_Denied $exception ) {
            $this->assertInstanceOf( Backup_Handler_Denied::class, $exception );
        } finally {
            remove_all_filters( 'wp_die_handler' );
            remove_all_filters( 'wp_redirect' );
        }
    }

    public function test_export_holds_every_form_except_trash_and_the_exportable_settings(): void {
        $published = $this->create_form( self::FORM_CONTENT, 'publish', 'Published' );
        $draft     = $this->create_form( self::OTHER_CONTENT, 'draft', 'Draft' );
        $trashed   = $this->create_form( self::FORM_CONTENT, 'trash', 'Trashed' );
        $box       = new \JayPee\FormBuilder\Secret_Box();
        update_option(
            'jp_form_builder_settings',
            [
                'smtp_host'            => 'mail.example.test',
                'smtp_port'            => 999,
                'smtp_password'        => $box->encrypt( 'hunter2' ),
                'turnstile_secret_key' => $box->encrypt( 'cf-secret' ),
            ]
        );

        $document = json_decode( ( new \JayPee\FormBuilder\Form_Backup() )->export_json(), true );

        $this->assertSame( 'jp-form-builder-backup', $document['format'] );
        $this->assertSame( 1, $document['version'] );
        $this->assertSame( [ $published, $draft ], array_column( $document['forms'], 'id' ) );
        $this->assertNotContains( $trashed, array_column( $document['forms'], 'id' ) );
        $this->assertSame( [ 'id', 'title', 'status', 'slug', 'content' ], array_keys( $document['forms'][0] ) );
        $this->assertSame( self::FORM_CONTENT, $document['forms'][0]['content'] );
        $this->assertSame( 'draft', $document['forms'][1]['status'] );
        $this->assertSame( [ 'smtp_host' => 'mail.example.test' ], $document['settings'] );
        $this->assertStringNotContainsString( 'hunter2', wp_json_encode( $document ) );
    }

    public function test_export_delete_restore_keeps_the_id_so_the_embed_renders_again(): void {
        $form_id = $this->create_form();
        $page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_content' => $this->embed_markup( $form_id ) ] );
        $json    = ( new \JayPee\FormBuilder\Form_Backup() )->export_json();
        $this->assertStringContainsString( '"settings": {}', $json );
        $this->assertStringContainsString( '<form', do_blocks( get_post( $page_id )->post_content ) );

        wp_delete_post( $form_id, true );
        $this->assertSame( '', do_blocks( get_post( $page_id )->post_content ) );

        $report = $this->upload( $json );

        $this->assertStringContainsString( 'edit.php?post_type=jp_form&page=jp-form-builder-settings&backup=restored', $this->redirect );
        $this->assertSame( '', $report['error'] );
        $this->assertSame( [ [ 'id' => $form_id, 'title' => 'Contact', 'action' => 'inserted' ] ], $report['restored'] );
        $this->assertSame( [], $report['mapped'] );
        $restored = get_post( $form_id );
        $this->assertSame( 'jp_form', $restored->post_type );
        $this->assertSame( 'publish', $restored->post_status );
        $this->assertSame( self::FORM_CONTENT, $restored->post_content );
        $this->assertStringContainsString( '<form', do_blocks( get_post( $page_id )->post_content ) );
    }

    public function test_an_id_taken_by_another_post_gets_a_new_form_and_a_mapping(): void {
        $page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Keep me' ] );

        $report = $this->upload( $this->document( [ $this->entry( $page_id, self::FORM_CONTENT, 'Mapped form' ) ] ) );

        $this->assertCount( 1, $report['mapped'] );
        $new_id = $report['mapped'][0]['to'];
        $this->assertSame( [ 'title' => 'Mapped form', 'from' => $page_id, 'to' => $new_id ], $report['mapped'][0] );
        $this->assertNotSame( $page_id, $new_id );
        $this->assertSame( 'jp_form', get_post( $new_id )->post_type );
        $this->assertSame( 'page', get_post( $page_id )->post_type );
        $this->assertSame( 'Keep me', get_post( $page_id )->post_title );
        $this->assertSame( $new_id, $report['restored'][0]['id'] );
    }

    public function test_a_form_with_the_same_id_is_skipped_unless_overwrite_is_set(): void {
        $form_id = $this->create_form( self::FORM_CONTENT, 'publish', 'Original' );
        $json    = $this->document( [ $this->entry( $form_id, self::OTHER_CONTENT, 'From backup' ) ] );

        $skipped = $this->upload( $json );

        $this->assertSame( [], $skipped['restored'] );
        $this->assertSame( $form_id, $skipped['skipped'][0]['id'] );
        $this->assertSame( 'Original', get_post( $form_id )->post_title );
        $this->assertSame( self::FORM_CONTENT, get_post( $form_id )->post_content );

        $overwritten = $this->upload( $json, true );

        $this->assertSame( [], $overwritten['skipped'] );
        $this->assertSame( [ [ 'id' => $form_id, 'title' => 'From backup', 'action' => 'overwritten' ] ], $overwritten['restored'] );
        $this->assertSame( 'From backup', get_post( $form_id )->post_title );
        $this->assertSame( self::OTHER_CONTENT, get_post( $form_id )->post_content );
        $this->assertSame( 'jp_form', get_post( $form_id )->post_type );
    }

    public function test_invalid_forms_are_rejected_with_a_reason_while_valid_ones_restore(): void {
        $paragraph_only = '<!-- wp:paragraph --><p>Not a Form</p><!-- /wp:paragraph -->';
        $two_forms      = self::FORM_CONTENT . self::OTHER_CONTENT;
        $foreign_child  = '<!-- wp:jp-form-builder/form --><!-- wp:core/html --><script>alert(1)</script><!-- /wp:core/html --><!-- /wp:jp-form-builder/form -->';
        $nested         = '<!-- wp:jp-form-builder/form --><!-- wp:jp-form-builder/form --><!-- /wp:jp-form-builder/form --><!-- /wp:jp-form-builder/form -->';
        $ids            = [ 910001, 910002, 910003, 910004, 910005 ];

        $report = $this->upload(
            $this->document(
                [
                    $this->entry( $ids[0], $paragraph_only ),
                    $this->entry( $ids[1], $two_forms ),
                    $this->entry( $ids[2], $foreign_child ),
                    $this->entry( $ids[3], $nested ),
                    $this->entry( $ids[4] ),
                    [ 'title' => 'No id or content' ],
                ]
            )
        );

        $this->assertCount( 5, $report['rejected'] );
        foreach ( $report['rejected'] as $rejected ) {
            $this->assertNotSame( '', $rejected['reason'] );
        }
        $this->assertSame( [ $ids[4] ], array_column( $report['restored'], 'id' ) );
        foreach ( array_slice( $ids, 0, 4 ) as $rejected_id ) {
            $this->assertNull( get_post( $rejected_id ) );
        }
        $this->assertSame( 'jp_form', get_post( $ids[4] )->post_type );
    }

    public function test_post_type_is_forced_to_jp_form(): void {
        $entry              = $this->entry( 910010 );
        $entry['post_type'] = 'page';

        $this->upload( $this->document( [ $entry ] ) );

        $this->assertSame( 'jp_form', get_post( 910010 )->post_type );
    }

    public function test_a_dry_run_reports_without_writing(): void {
        $existing = $this->create_form();
        $json     = $this->document(
            [ $this->entry( $existing ), $this->entry( 910020 ), $this->entry( 910021, '<!-- wp:paragraph /-->' ) ],
            [ 'smtp_host' => 'dry.example.test' ]
        );

        $report = ( new \JayPee\FormBuilder\Form_Backup() )->restore( $json, true, true );

        $this->assertTrue( $report['dry_run'] );
        $this->assertSame( [ $existing, 910020 ], array_column( $report['restored'], 'id' ) );
        $this->assertCount( 1, $report['rejected'] );
        $this->assertSame( [ 'smtp_host' ], $report['settings']['restored'] );
        $this->assertNull( get_post( 910020 ) );
        $this->assertSame( 'Contact', get_post( $existing )->post_title );
        $this->assertSame( [], get_option( 'jp_form_builder_settings' ) );
    }

    public function test_settings_restore_leaves_secrets_locked_and_unknown_keys_alone(): void {
        ( new \JayPee\FormBuilder\Settings() )->save( [ 'smtp_host' => 'old.example.test', 'smtp_password' => 'keep-me' ] );

        $report = $this->upload(
            $this->document(
                [],
                [
                    'smtp_host'            => 'new.example.test',
                    'smtp_user'            => 'restored-user',
                    'smtp_encryption'      => 'smtps',
                    'smtp_password'        => 'attacker',
                    'turnstile_secret_key' => 'attacker',
                    'm365_client_secret'   => 'attacker',
                    'smtp_port'            => 465,
                    'unknown_key'          => 'x',
                ]
            )
        );

        $settings = new \JayPee\FormBuilder\Settings();
        $stored   = get_option( 'jp_form_builder_settings' );
        $this->assertSame( [ 'smtp_host', 'smtp_user', 'smtp_encryption' ], $report['settings']['restored'] );
        $this->assertSame( [ 'smtp_password', 'turnstile_secret_key', 'm365_client_secret', 'smtp_port', 'unknown_key' ], array_keys( $report['settings']['skipped'] ) );
        $this->assertSame( 'new.example.test', $settings->get( 'smtp_host' ) );
        $this->assertSame( 'smtps', $settings->get( 'smtp_encryption' ) );
        $this->assertSame( 'keep-me', $settings->get( 'smtp_password' ) );
        $this->assertSame( '', $settings->get( 'turnstile_secret_key' ) );
        $this->assertSame( 2525, $settings->get( 'smtp_port' ) );
        $this->assertArrayNotHasKey( 'smtp_port', $stored );
        $this->assertArrayNotHasKey( 'unknown_key', $stored );
    }

    public function test_download_and_restore_handlers_reject_non_admins_and_missing_nonces(): void {
        $this->create_form();
        $_REQUEST = [];
        $this->assert_handler_denied( 'jp_form_builder_download_backup' );
        $this->assert_handler_denied( 'jp_form_builder_restore_backup' );

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
        $_GET     = [ '_wpnonce' => wp_create_nonce( 'jp_form_builder_download_backup' ) ];
        $_REQUEST = $_GET;
        $this->assert_handler_denied( 'jp_form_builder_download_backup' );
        $_POST    = [ '_wpnonce' => wp_create_nonce( 'jp_form_builder_restore_backup' ) ];
        $_REQUEST = $_POST;
        $this->assert_handler_denied( 'jp_form_builder_restore_backup' );
        $this->assertFalse( get_transient( 'jp_form_builder_backup_report_' . get_current_user_id() ) );
    }

    public function test_restore_without_a_valid_nonce_changes_nothing(): void {
        $_FILES   = [ 'jp_form_builder_backup' => [ 'name' => 'b.json', 'tmp_name' => '/nonexistent', 'error' => UPLOAD_ERR_OK, 'size' => 1 ] ];
        $_POST    = [ '_wpnonce' => 'invalid' ];
        $_REQUEST = $_POST;

        $this->assert_handler_denied( 'jp_form_builder_restore_backup' );

        $this->assertFalse( get_transient( 'jp_form_builder_backup_report_' . get_current_user_id() ) );
    }

    public function test_uploads_that_are_too_large_missing_or_not_backups_are_rejected(): void {
        $document = $this->document( [ $this->entry( 910030 ) ], [ 'smtp_host' => 'big.example.test' ] );
        $oversized = substr( $document, 0, -1 ) . str_repeat( ' ', 2097153 ) . '}';

        $too_large = $this->upload( $oversized );
        $this->assertNotSame( '', $too_large['error'] );
        $this->assertNull( get_post( 910030 ) );
        $this->assertSame( [], get_option( 'jp_form_builder_settings' ) );

        $ini_limit = $this->upload( $document, false, [ 'name' => 'b.json', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0 ] );
        $this->assertNotSame( '', $ini_limit['error'] );

        $no_file = $this->upload( $document, false, [ 'name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0 ] );
        $this->assertNotSame( '', $no_file['error'] );

        $not_a_backup = $this->upload( '{"hello":"world"}' );
        $this->assertNotSame( '', $not_a_backup['error'] );

        $future = $this->upload( str_replace( '"version":1', '"version":2', $document ) );
        $this->assertNotSame( '', $future['error'] );
        $this->assertNull( get_post( 910030 ) );
    }

    public function test_the_plugins_list_links_admins_to_a_nonce_protected_download(): void {
        $links = apply_filters( 'plugin_action_links_' . plugin_basename( dirname( __DIR__, 2 ) . '/jp-form-builder.php' ), [ 'deactivate' => 'Deactivate' ] );

        $this->assertSame( [ 'deactivate', 'jp-form-builder-backup' ], array_keys( $links ) );
        $this->assertStringContainsString( 'action=jp_form_builder_download_backup', $links['jp-form-builder-backup'] );
        $this->assertStringContainsString( '_wpnonce=', $links['jp-form-builder-backup'] );
        $this->assertStringContainsString( 'Back up forms', $links['jp-form-builder-backup'] );

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
        $links = apply_filters( 'plugin_action_links_' . plugin_basename( dirname( __DIR__, 2 ) . '/jp-form-builder.php' ), [ 'deactivate' => 'Deactivate' ] );
        $this->assertSame( [ 'deactivate' ], array_keys( $links ) );
    }

    public function test_uninstall_removes_forms_options_and_transients_but_leaves_other_content(): void {
        global $wpdb;
        $published = $this->create_form();
        $trashed   = $this->create_form( self::OTHER_CONTENT, 'trash' );
        $draft     = $this->create_form( self::OTHER_CONTENT, 'draft' );
        wp_update_post( [ 'ID' => $published, 'post_content' => self::OTHER_CONTENT ] );
        update_post_meta( $published, 'jp_test_meta', 'value' );
        $this->assertNotEmpty( wp_get_post_revisions( $published ), 'Seed: the Form has a revision.' );
        $embed    = $this->embed_markup( $published );
        $page_id  = self::factory()->post->create( [ 'post_type' => 'page', 'post_content' => $embed ] );
        $post_id  = self::factory()->post->create( [ 'post_title' => 'Unrelated post' ] );
        update_post_meta( $post_id, 'jp_test_meta', 'value' );
        update_option( 'jp_form_builder_settings', [ 'smtp_host' => 'mail.example.test' ] );
        update_option( 'jp_form_builder_mail_log', [ [ 'time' => 'now', 'message' => 'x' ] ] );
        update_option( 'jp_form_builder_github_token', 'token' );
        update_option( 'unrelated_option', 'stays' );
        set_transient( 'jp_form_builder_rl_abc', 3, 600 );
        set_transient( 'jp_form_builder_confirmation_def', 1, 600 );
        set_transient( 'jp_form_builder_backup_report_1', [], 600 );
        set_transient( 'unrelated_transient', 'stays', 600 );
        $revision_ids = array_keys( wp_get_post_revisions( $published ) );

        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', 'jp-form-builder/jp-form-builder.php' );
        }
        include dirname( __DIR__, 2 ) . '/uninstall.php';

        foreach ( [ $published, $trashed, $draft ] as $form_id ) {
            $this->assertNull( get_post( $form_id ) );
        }
        foreach ( $revision_ids as $revision_id ) {
            $this->assertNull( get_post( $revision_id ) );
        }
        $this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $published ) ) );
        $this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'jp_form'" ) );
        foreach ( [ 'jp_form_builder_settings', 'jp_form_builder_mail_log', 'jp_form_builder_github_token' ] as $option ) {
            $this->assertFalse( get_option( $option ), $option );
        }
        $like = $wpdb->esc_like( 'jp_form_builder_' ) . '%';
        $this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_' . $like, '_transient_timeout_' . $like ) ) );
        $this->assertSame( 'stays', get_option( 'unrelated_option' ) );
        $this->assertSame( 'stays', get_transient( 'unrelated_transient' ) );
        $this->assertSame( 'Unrelated post', get_post( $post_id )->post_title );
        $this->assertSame( 'value', get_post_meta( $post_id, 'jp_test_meta', true ) );
        $this->assertSame( $embed, get_post( $page_id )->post_content );
        $this->assertSame( '', do_blocks( $embed ) );
    }
}
