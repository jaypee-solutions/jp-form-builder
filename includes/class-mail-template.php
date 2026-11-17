<?php
/**
 * Renders restricted Body templates.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Template {

    /** @param Submitted_Field[] $fields @param array<string,string> $context */
    public function render( string $template, array $fields, array $context = [], string $mode = 'html' ): string {
        $template = $this->sanitize( $template );
        if ( 'text' === $mode ) {
            $template = preg_replace( '/<br\s*\/?\s*>/i', "\n", $template );
            $template = preg_replace( '/<\/(?:p|div|tr|h[1-6]|li)>/i', "\n", (string) $template );
            $template = html_entity_decode( wp_strip_all_tags( (string) $template ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }
        $field_map = [];
        foreach ( $fields as $field ) {
            if ( $field instanceof Submitted_Field ) {
                $field_map[ $field->key() ] = $field;
            }
        }
        $section_results = [];
        $template = preg_replace_callback(
            '/\{#([A-Za-z0-9_-]+)\}(.*?)\{\/\1\}/s',
            function ( array $section_match ) use ( $field_map, $context, $mode, &$section_results ): string {
                if ( ! isset( $field_map[ $section_match[1] ] ) || ! $this->non_empty( $field_map[ $section_match[1] ] ) ) {
                    return '';
                }
                $placeholder = "\x01JPFB_SECTION_" . count( $section_results ) . "\x02";
                $section_results[ $placeholder ] = $this->replace( $section_match[2], $field_map, $context, $mode );
                return $placeholder;
            },
            $template
        );
        $rendered = $this->replace( (string) $template, $field_map, $context, $mode );
        return strtr( $rendered, $section_results );
    }

    public function sanitize( string $template ): string {
        $template = preg_replace( '#<script\b[^>]*>.*?</script\s*>#is', '', $template );
        $tags     = Mail_Remote_Assets::without_fetching_tags( wp_kses_allowed_html( 'post' ) );
        unset( $tags['script'], $tags['style'] );
        foreach ( $tags as &$attributes ) {
            $attributes['style'] = true;
        }
        unset( $attributes );
        return Mail_Remote_Assets::strip( wp_kses( $template, apply_filters( 'jp_form_builder_mail_allowed_html', $tags ) ) );
    }

    /**
     * @param array<string,Submitted_Field> $fields
     * @param array<string,string> $context
     */
    private function replace( string $template, array $fields, array $context, string $mode ): string {
        return (string) preg_replace_callback(
            '/\{([A-Za-z0-9_-]+)(:label)?\}/',
            function ( array $token_match ) use ( $fields, $context, $mode ): string {
                $key = $token_match[1];
                if ( ':label' === ( $token_match[2] ?? '' ) ) {
                    return isset( $fields[ $key ] ) ? $this->escape( $fields[ $key ]->label(), $mode ) : '';
                }
                if ( 'all_fields' === $key ) {
                    return 'html' === $mode ? $this->field_table( $fields ) : $this->field_lines( $fields );
                }
                if ( isset( $fields[ $key ] ) ) {
                    $value = $fields[ $key ]->as_text();
                    return 'text' === $mode ? str_replace( "\n", "\n  ", $value ) : $this->escape( $value, $mode );
                }
                return isset( $context[ $key ] ) ? $this->escape( $context[ $key ], $mode ) : '';
            },
            $template
        );
    }

    private function non_empty( Submitted_Field $field ): bool {
        return '' !== trim( $field->as_text() );
    }

    private function escape( string $value, string $mode ): string {
        return 'text' === $mode ? $value : nl2br( esc_html( $value ), false );
    }

    /** @param array<string,Submitted_Field> $fields */
    private function field_lines( array $fields ): string {
        $lines = [];
        foreach ( $fields as $field ) {
            $lines[] = $field->label() . ': ' . str_replace( "\n", "\n  ", $field->as_text() );
        }
        return implode( "\n", $lines );
    }

    /** @param array<string,Submitted_Field> $fields */
    private function field_table( array $fields ): string {
        if ( [] === $fields ) {
            return '';
        }
        $rows = '';
        foreach ( $fields as $field ) {
            $rows .= '<tr><th style="text-align:left;padding:4px">' . esc_html( $field->label() ) . '</th><td style="padding:4px">' . nl2br( esc_html( $field->as_text() ), false ) . '</td></tr>';
        }
        return '<table style="font:inherit;color:inherit;border-collapse:collapse">' . $rows . '</table>';
    }
}
