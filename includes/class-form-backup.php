<?php
/**
 * Exports Forms and non-secret settings to a Backup document and restores it.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Backup {

    public const FORMAT  = 'jp-form-builder-backup';
    public const VERSION = 1;

    private const STATUSES = [ 'publish', 'draft', 'pending', 'private' ];

    private Settings $settings;

    public function __construct( ?Settings $settings = null ) {
        $this->settings = $settings ?? new Settings();
    }

    /** @return array<string,mixed> The Backup document of every Form (except trashed) and the exportable settings. */
    public function export(): array {
        $forms = [];
        foreach ( get_posts( [ 'post_type' => 'jp_form', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ] ) as $post ) {
            $forms[] = [
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'status'  => $post->post_status,
                'slug'    => $post->post_name,
                'content' => $post->post_content,
            ];
        }
        return $this->document( $forms, $this->exportable_settings() );
    }

    /**
     * @param array<int,array<string,mixed>> $forms
     * @param array<string,mixed>            $settings
     * @return array<string,mixed>
     */
    public function document( array $forms, array $settings ): array {
        return [
            'format'         => self::FORMAT,
            'version'        => self::VERSION,
            'plugin_version' => VERSION,
            'exported_at'    => gmdate( 'c' ),
            'forms'          => $forms,
            'settings'       => $settings,
        ];
    }

    /** @return array<string,mixed> Stored settings that are neither secret nor locked by a constant. */
    public function exportable_settings(): array {
        $exportable = [];
        foreach ( $this->settings->keys() as $key ) {
            if ( 'option' === $this->settings->source( $key ) && ! $this->settings->is_secret( $key ) ) {
                $exportable[ $key ] = $this->settings->get( $key );
            }
        }
        return $exportable;
    }

    /**
     * Decodes a Backup document and checks its format, version and size.
     *
     * @return array<string,mixed>
     * @throws \InvalidArgumentException With a translated reason when the document cannot be restored.
     */
    public function decode( string $json, int $max_bytes ): array {
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Reasons are plain translated text, escaped where the report is rendered.
        if ( strlen( $json ) > $max_bytes ) {
            throw new \InvalidArgumentException( __( 'The Backup file is too large.', 'jp-form-builder' ) );
        }
        $document = json_decode( $json, true );
        if ( ! is_array( $document ) || ( $document['format'] ?? null ) !== self::FORMAT ) {
            throw new \InvalidArgumentException( __( 'This is not a Form Builder Backup file.', 'jp-form-builder' ) );
        }
        if ( ( $document['version'] ?? null ) !== self::VERSION ) {
            throw new \InvalidArgumentException( __( 'This Backup file has an unsupported version.', 'jp-form-builder' ) );
        }
        if ( ! isset( $document['forms'] ) || ! is_array( $document['forms'] ) || ( isset( $document['settings'] ) && ! is_array( $document['settings'] ) ) ) {
            throw new \InvalidArgumentException( __( 'The Backup file is malformed.', 'jp-form-builder' ) );
        }
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $document['settings'] = $document['settings'] ?? [];
        return $document;
    }

    /**
     * Why parsed Form content cannot be restored, or null when it is exactly one Form block with allowed children.
     *
     * @param array<int,array<string,mixed>> $blocks Output of parse_blocks().
     */
    public function content_error( array $blocks ): ?string {
        $top_level = array_values(
            array_filter(
                $blocks,
                static fn( array $block ): bool => null !== ( $block['blockName'] ?? null ) || '' !== trim( (string) ( $block['innerHTML'] ?? '' ) )
            )
        );
        if ( 1 !== count( $top_level ) || Form_Blocks::FORM !== ( $top_level[0]['blockName'] ?? null ) ) {
            return __( 'The content must be exactly one Form block.', 'jp-form-builder' );
        }
        foreach ( $top_level[0]['innerBlocks'] ?? [] as $child ) {
            $error = $this->child_error( $child, Form_Blocks::allowed_children() );
            if ( null !== $error ) {
                return $error;
            }
        }
        return null;
    }

    /**
     * Why a child of the Form or of a Conditional group cannot be restored. Only a group may hold blocks, and only
     * Fields and pass-through blocks without blocks of their own, so groups never nest.
     *
     * @param array<string,mixed> $block   The child block.
     * @param string[]            $allowed Block names allowed at this level.
     */
    private function child_error( array $block, array $allowed ): ?string {
        $name = (string) ( $block['blockName'] ?? '' );
        if ( ! in_array( $name, $allowed, true ) ) {
            /* translators: %s: block name. */
            return sprintf( __( 'The block "%s" is not allowed in a Form.', 'jp-form-builder' ), '' === $name ? 'freeform' : $name );
        }
        if ( Form_Blocks::CONDITIONAL !== $name ) {
            return empty( $block['innerBlocks'] ) ? null : __( 'Nested blocks are not allowed in a Form.', 'jp-form-builder' );
        }
        foreach ( $block['innerBlocks'] ?? [] as $child ) {
            $error = $this->child_error( $child, Form_Blocks::group_children() );
            if ( null !== $error ) {
                return $error;
            }
        }
        return null;
    }

    /**
     * Restores from a Backup file, refusing files over the size limit before reading them.
     *
     * @return array<string,mixed> The report.
     */
    public function restore_file( string $path, bool $overwrite = false, bool $dry_run = false ): array {
        if ( ! is_readable( $path ) || is_dir( $path ) ) {
            return $this->failed( __( 'The Backup file cannot be read.', 'jp-form-builder' ), $dry_run );
        }
        if ( (int) filesize( $path ) > (int) $this->settings->tunable( 'max_backup_bytes' ) ) {
            return $this->failed( __( 'The Backup file is too large.', 'jp-form-builder' ), $dry_run );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local upload or CLI file.
        return $this->restore( (string) file_get_contents( $path ), $overwrite, $dry_run );
    }

    /** @return array<string,mixed> A report of a restore that could not start. */
    public function failed( string $message, bool $dry_run = false ): array {
        return array_merge( $this->empty_report( $dry_run ), [ 'error' => $message ] );
    }

    /** @return array<string,mixed> A report with nothing restored, skipped or rejected, and no error. */
    public function empty_report( bool $dry_run = false ): array {
        return [
            'error'    => '',
            'dry_run'  => $dry_run,
            'restored' => [],
            'skipped'  => [],
            'rejected' => [],
            'mapped'   => [],
            'settings' => [ 'restored' => [], 'skipped' => [] ],
        ];
    }

    /** The Backup document as pretty-printed JSON. */
    public function export_json(): string {
        $document             = $this->export();
        $document['settings'] = (object) $document['settings'];
        return (string) wp_json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Restores Forms and settings from a Backup document.
     *
     * @return array<string,mixed> The report; see {@see self::describe()}.
     */
    public function restore( string $json, bool $overwrite = false, bool $dry_run = false ): array {
        $report = $this->empty_report( $dry_run );
        try {
            $document = $this->decode( $json, (int) $this->settings->tunable( 'max_backup_bytes' ) );
        } catch ( \InvalidArgumentException $exception ) {
            $report['error'] = $exception->getMessage();
            return $report;
        }

        foreach ( $document['forms'] as $entry ) {
            $this->restore_form( is_array( $entry ) ? $entry : [], $overwrite, $dry_run, $report );
        }
        $this->restore_settings( $document['settings'], $dry_run, $report );
        return $report;
    }

    /** @return string[] The report as translated one-line messages. */
    public function describe( array $report ): array {
        if ( '' !== $report['error'] ) {
            return [ $report['error'] ];
        }
        $lines = $report['dry_run'] ? [ __( 'Dry run: nothing was written.', 'jp-form-builder' ) ] : [];
        foreach ( $report['restored'] as $item ) {
            $lines[] = sprintf(
                /* translators: 1: Form title, 2: Form ID. */
                'overwritten' === $item['action'] ? __( 'Overwritten: "%1$s" (ID %2$d).', 'jp-form-builder' ) : __( 'Restored: "%1$s" (ID %2$d).', 'jp-form-builder' ),
                $item['title'],
                $item['id']
            );
        }
        foreach ( $report['mapped'] as $item ) {
            $lines[] = null === $item['to']
                /* translators: 1: Form title, 2: old Form ID. */
                ? sprintf( __( 'Mapped: "%1$s" would get a new ID because ID %2$d is taken by another post. Embeds are not rewritten.', 'jp-form-builder' ), $item['title'], $item['from'] )
                /* translators: 1: Form title, 2: old Form ID, 3: new Form ID. */
                : sprintf( __( 'Mapped: "%1$s" ID %2$d → %3$d. Embeds are not rewritten.', 'jp-form-builder' ), $item['title'], $item['from'], $item['to'] );
        }
        foreach ( $report['skipped'] as $item ) {
            /* translators: 1: Form title, 2: Form ID, 3: reason. */
            $lines[] = sprintf( __( 'Skipped: "%1$s" (ID %2$d): %3$s', 'jp-form-builder' ), $item['title'], $item['id'], $item['reason'] );
        }
        foreach ( $report['rejected'] as $item ) {
            /* translators: 1: Form title, 2: Form ID, 3: reason. */
            $lines[] = sprintf( __( 'Rejected: "%1$s" (ID %2$d): %3$s', 'jp-form-builder' ), $item['title'], $item['id'], $item['reason'] );
        }
        if ( [] !== $report['settings']['restored'] ) {
            /* translators: %s: comma-separated setting keys. */
            $lines[] = sprintf( __( 'Settings restored: %s', 'jp-form-builder' ), implode( ', ', $report['settings']['restored'] ) );
        }
        foreach ( $report['settings']['skipped'] as $key => $reason ) {
            /* translators: 1: setting key, 2: reason. */
            $lines[] = sprintf( __( 'Setting skipped: %1$s: %2$s', 'jp-form-builder' ), $key, $reason );
        }
        return $lines;
    }

    /**
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $report
     */
    private function restore_form( array $entry, bool $overwrite, bool $dry_run, array &$report ): void {
        $id    = is_int( $entry['id'] ?? null ) ? $entry['id'] : 0;
        $title = is_string( $entry['title'] ?? null ) ? sanitize_text_field( $entry['title'] ) : '';
        $label = [ 'id' => $id, 'title' => $title ];

        if ( $id <= 0 || ! is_string( $entry['content'] ?? null ) ) {
            $report['rejected'][] = $label + [ 'reason' => __( 'A Form in the Backup is missing its ID or content.', 'jp-form-builder' ) ];
            return;
        }
        $error = $this->content_error( parse_blocks( $entry['content'] ) );
        if ( null !== $error ) {
            $report['rejected'][] = $label + [ 'reason' => $error ];
            return;
        }

        $status  = in_array( $entry['status'] ?? '', self::STATUSES, true ) ? $entry['status'] : 'draft';
        $slug    = is_string( $entry['slug'] ?? null ) ? sanitize_title( $entry['slug'] ) : '';
        $post    = [
            'post_type'    => 'jp_form',
            'post_title'   => $title,
            'post_status'  => $status,
            'post_name'    => $slug,
            'post_content' => $entry['content'],
        ];
        $existing = get_post( $id );

        if ( $existing && 'jp_form' === $existing->post_type ) {
            if ( ! $overwrite ) {
                $report['skipped'][] = $label + [ 'reason' => __( 'A Form with this ID exists. Overwrite is off.', 'jp-form-builder' ) ];
                return;
            }
            $result = $dry_run ? $id : wp_update_post( wp_slash( $post + [ 'ID' => $id ] ), true );
            $action = 'overwritten';
        } elseif ( $existing ) {
            $result = $dry_run ? null : wp_insert_post( wp_slash( $post ), true );
            $action = 'inserted';
        } else {
            $result = $dry_run ? $id : wp_insert_post( wp_slash( $post + [ 'import_id' => $id ] ), true );
            $action = 'inserted';
        }

        if ( is_wp_error( $result ) || ( ! $dry_run && ! $result ) ) {
            $report['rejected'][] = $label + [ 'reason' => is_wp_error( $result ) ? $result->get_error_message() : __( 'WordPress could not save the Form.', 'jp-form-builder' ) ];
            return;
        }
        if ( $existing && 'jp_form' !== $existing->post_type ) {
            $report['mapped'][] = [ 'title' => $title, 'from' => $id, 'to' => $result ];
        }
        if ( null !== $result ) {
            $report['restored'][] = [ 'id' => (int) $result, 'title' => $title, 'action' => $action ];
        }
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $report
     */
    private function restore_settings( array $settings, bool $dry_run, array &$report ): void {
        foreach ( $settings as $key => $value ) {
            $key = (string) $key;
            if ( ! in_array( $key, $this->settings->keys(), true ) ) {
                $report['settings']['skipped'][ $key ] = __( 'Unknown setting.', 'jp-form-builder' );
            } elseif ( $this->settings->is_secret( $key ) ) {
                $report['settings']['skipped'][ $key ] = __( 'Secrets are never restored.', 'jp-form-builder' );
            } elseif ( $this->settings->is_locked( $key ) ) {
                $report['settings']['skipped'][ $key ] = __( 'Locked by a constant.', 'jp-form-builder' );
            } elseif ( ! is_scalar( $value ) ) {
                $report['settings']['skipped'][ $key ] = __( 'Invalid value.', 'jp-form-builder' );
            } else {
                $rejection = $dry_run ? $this->settings->rejection( $key, $value ) : ( $this->settings->save( [ $key => $value ] )[ $key ] ?? null );
                if ( null === $rejection ) {
                    $report['settings']['restored'][] = $key;
                } else {
                    $report['settings']['skipped'][ $key ] = $rejection;
                }
            }
        }
    }
}
