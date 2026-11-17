<?php
/**
 * Unit tests for the pure parts of Backup export and restore validation.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class FormBackupTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['jp_form_builder_test_options'] = [];
    }

    private function block( string $name, array $inner = [] ): array {
        return [ 'blockName' => $name, 'attrs' => [], 'innerBlocks' => $inner, 'innerHTML' => '', 'innerContent' => [] ];
    }

    private function whitespace(): array {
        return [ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "\n\n", 'innerContent' => [ "\n\n" ] ];
    }

    public function test_document_has_the_documented_shape(): void {
        $forms    = [ [ 'id' => 7, 'title' => 'Contact', 'status' => 'publish', 'slug' => 'contact', 'content' => '<!-- wp:x /-->' ] ];
        $document = ( new Form_Backup() )->document( $forms, [ 'smtp_host' => 'mail.test' ] );

        $this->assertSame( [ 'format', 'version', 'plugin_version', 'exported_at', 'forms', 'settings' ], array_keys( $document ) );
        $this->assertSame( 'jp-form-builder-backup', $document['format'] );
        $this->assertSame( 1, $document['version'] );
        $this->assertSame( VERSION, $document['plugin_version'] );
        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $document['exported_at'] );
        $this->assertSame( $forms, $document['forms'] );
        $this->assertSame( [ 'smtp_host' => 'mail.test' ], $document['settings'] );
    }

    public function test_exportable_settings_leave_out_secrets_locked_keys_and_defaults(): void {
        $box = new Secret_Box();
        $GLOBALS['jp_form_builder_test_options']['jp_form_builder_settings'] = [
            'smtp_host'            => 'mail.test',
            'smtp_user'            => 'mailer',
            'smtp_password'        => $box->encrypt( 'hunter2' ),
            'turnstile_secret_key' => $box->encrypt( 'cf-secret' ),
            'm365_client_secret'   => $box->encrypt( 'm365-secret' ),
            'from_name'            => 'Option name',
        ];
        $settings = new Settings( static fn( string $name ): array => 'JP_FORM_BUILDER_FROM_NAME' === $name ? [ true, 'Locked name' ] : [ false, null ] );

        $exported = ( new Form_Backup( $settings ) )->exportable_settings();

        $this->assertSame( [ 'smtp_host' => 'mail.test', 'smtp_user' => 'mailer' ], $exported );
        $this->assertStringNotContainsString( 'hunter2', (string) json_encode( $exported ) );
    }

    public function test_content_error_accepts_one_form_block_with_allowed_children(): void {
        $form = $this->block(
            'jp-form-builder/form',
            [
                $this->block( 'core/heading' ),
                $this->block( 'jp-form-builder/name' ),
                $this->block( 'jp-form-builder/email' ),
                $this->block( 'jp-form-builder/phone' ),
                $this->block( 'jp-form-builder/address' ),
                $this->block( 'jp-form-builder/text' ),
                $this->block( 'jp-form-builder/select' ),
                $this->block( 'jp-form-builder/textarea' ),
                $this->block( 'jp-form-builder/consent' ),
                $this->block( 'core/paragraph' ),
            ]
        );

        $this->assertNull( ( new Form_Backup() )->content_error( [ $form ] ) );
        $this->assertNull( ( new Form_Backup() )->content_error( [ $this->whitespace(), $form, $this->whitespace() ] ) );
    }

    /** @dataProvider invalid_content */
    public function test_content_error_rejects_everything_else( array $blocks ): void {
        $this->assertIsString( ( new Form_Backup() )->content_error( $blocks ) );
    }

    /** @return array<string,array{array<int,array<string,mixed>>}> */
    public function invalid_content(): array {
        $form = $this->block( 'jp-form-builder/form', [ $this->block( 'jp-form-builder/name' ) ] );
        return [
            'no blocks'                 => [ [] ],
            'only whitespace'           => [ [ $this->whitespace() ] ],
            'foreign top-level block'   => [ [ $this->block( 'core/paragraph' ) ] ],
            'two Form blocks'           => [ [ $form, $form ] ],
            'Form plus other block'     => [ [ $form, $this->block( 'core/paragraph' ) ] ],
            'text outside the Form'     => [ [ $form, [ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'stray', 'innerContent' => [ 'stray' ] ] ] ],
            'foreign child'             => [ [ $this->block( 'jp-form-builder/form', [ $this->block( 'core/html' ) ] ) ] ],
            'embed inside the Form'     => [ [ $this->block( 'jp-form-builder/form', [ $this->block( 'jp-form-builder/embed' ) ] ) ] ],
            'nested Form block'         => [ [ $this->block( 'jp-form-builder/form', [ $this->block( 'jp-form-builder/form' ) ] ) ] ],
            'child with inner blocks'   => [ [ $this->block( 'jp-form-builder/form', [ $this->block( 'core/paragraph', [ $this->block( 'core/paragraph' ) ] ) ] ) ] ],
        ];
    }

    public function test_decode_accepts_a_current_document(): void {
        $backup   = new Form_Backup();
        $document = $backup->decode( (string) json_encode( $backup->document( [], [] ) ), 2097152 );

        $this->assertSame( [], $document['forms'] );
        $this->assertSame( [], $document['settings'] );
    }

    /** @dataProvider undecodable_documents */
    public function test_decode_rejects_documents_it_cannot_restore( string $json ): void {
        $this->expectException( \InvalidArgumentException::class );
        ( new Form_Backup() )->decode( $json, 2097152 );
    }

    /** @return array<string,array{string}> */
    public function undecodable_documents(): array {
        return [
            'not JSON'            => [ 'not json' ],
            'a JSON scalar'       => [ '42' ],
            'wrong format'        => [ '{"format":"other","version":1,"forms":[]}' ],
            'missing version'     => [ '{"format":"jp-form-builder-backup","forms":[]}' ],
            'future version'      => [ '{"format":"jp-form-builder-backup","version":2,"forms":[]}' ],
            'string version'      => [ '{"format":"jp-form-builder-backup","version":"1","forms":[]}' ],
            'missing forms'       => [ '{"format":"jp-form-builder-backup","version":1}' ],
            'forms not an array'  => [ '{"format":"jp-form-builder-backup","version":1,"forms":"x"}' ],
            'settings not array'  => [ '{"format":"jp-form-builder-backup","version":1,"forms":[],"settings":"x"}' ],
        ];
    }

    public function test_decode_rejects_oversized_documents(): void {
        $this->expectException( \InvalidArgumentException::class );
        $json = (string) json_encode( ( new Form_Backup() )->document( [], [] ) );
        ( new Form_Backup() )->decode( $json, strlen( $json ) - 1 );
    }

    public function test_the_backup_size_limit_defaults_to_two_mebibytes_and_follows_the_constant(): void {
        $this->assertSame( 2097152, ( new Settings() )->tunable( 'max_backup_bytes' ) );
        $settings = new Settings( static fn( string $name ): array => 'JP_FORM_BUILDER_MAX_BACKUP_BYTES' === $name ? [ true, '1024' ] : [ false, null ] );
        $this->assertSame( 1024, $settings->tunable( 'max_backup_bytes' ) );
    }

    public function test_restore_reports_an_undecodable_document_without_touching_anything(): void {
        $report = ( new Form_Backup() )->restore( '{"format":"jp-form-builder-backup","version":9,"forms":[]}' );

        $this->assertNotSame( '', $report['error'] );
        $this->assertSame( [], $report['restored'] );
        $this->assertSame( [ $report['error'] ], ( new Form_Backup() )->describe( $report ) );
    }

    public function test_restore_reads_booleans_from_strings_and_skips_unreadable_ones(): void {
        $json   = (string) json_encode(
            [
                'format'   => 'jp-form-builder-backup',
                'version'  => 1,
                'forms'    => [],
                'settings' => [ 'smtp_auth' => 'false', 'force_from' => 'maybe', 'from_name' => 'Restored' ],
            ]
        );
        $backup = new Form_Backup();

        $dry_run = $backup->restore( $json, false, true );
        $this->assertSame( [ 'smtp_auth', 'from_name' ], $dry_run['settings']['restored'] );
        $this->assertSame( [ 'force_from' ], array_keys( $dry_run['settings']['skipped'] ) );
        $this->assertSame( 'default', ( new Settings() )->source( 'smtp_auth' ), 'a dry run writes nothing' );

        $report = $backup->restore( $json );
        $this->assertSame( [ 'smtp_auth', 'from_name' ], $report['settings']['restored'] );
        $this->assertSame( [ 'force_from' ], array_keys( $report['settings']['skipped'] ) );
        $this->assertFalse( ( new Settings() )->get( 'smtp_auth' ) );
        $this->assertSame( 'Restored', ( new Settings() )->get( 'from_name' ) );
    }

    public function test_reports_start_empty_and_a_failed_report_carries_only_the_error(): void {
        $backup = new Form_Backup();
        $empty  = $backup->empty_report( true );
        $this->assertSame( '', $empty['error'] );
        $this->assertTrue( $empty['dry_run'] );
        $this->assertSame( [], $backup->describe( $backup->empty_report() ) );

        $failed = $backup->failed( 'Nope' );
        $this->assertSame( 'Nope', $failed['error'] );
        $this->assertSame( array_diff_key( $empty, [ 'error' => 1, 'dry_run' => 1 ] ), array_diff_key( $failed, [ 'error' => 1, 'dry_run' => 1 ] ) );
    }
}
