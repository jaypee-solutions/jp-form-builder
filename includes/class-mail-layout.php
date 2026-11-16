<?php
/**
 * Resolves and sanitizes the site-wide Mail layout.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Layout {

    private Settings $settings;
    private string $default_file;
    private ?string $draft = null;

    public function __construct( ?Settings $settings = null, ?string $default_file = null ) {
        $this->settings     = $settings ?? new Settings();
        $this->default_file = $default_file ?? dirname( __DIR__ ) . '/templates/mail/layout.html';
    }

    /** An unsaved layout for previews; empty means the bundled default, as when saving. */
    public function with_draft( string $layout ): self {
        $copy        = clone $this;
        $copy->draft = $layout;
        return $copy;
    }

    public function get_layout(): string {
        $configured = $this->draft ?? (string) $this->settings->get( 'mail_layout' );
        $layout     = '' !== trim( $configured ) ? $configured : (string) file_get_contents( $this->default_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return (string) apply_filters( 'jp_form_builder_mail_layout', $layout );
    }

    /**
     * Keeps tables, inline styles and a `<style>` block, but nothing that makes a mail client
     * fetch remote content (images, embeds, `src` attributes, remote CSS `url()` or `@import`).
     *
     * @throws \InvalidArgumentException When {content} is absent.
     */
    public function sanitize( string $layout ): string {
        $allowed = wp_kses_allowed_html( 'post' );
        unset( $allowed['img'], $allowed['picture'], $allowed['source'], $allowed['video'], $allowed['audio'], $allowed['iframe'], $allowed['object'], $allowed['embed'], $allowed['form'] );
        $tags = [ 'body', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'div', 'span', 'p', 'a', 'strong', 'em', 'br', 'h1', 'h2', 'h3', 'ul', 'ol', 'li' ];
        foreach ( $tags as $tag ) {
            $allowed[ $tag ] = [ 'style' => true, 'class' => true, 'role' => true, 'align' => true, 'valign' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'width' => true, 'height' => true ];
        }
        $allowed['html']      = [ 'lang' => true, 'dir' => true ];
        $allowed['head']      = [];
        $allowed['meta']      = [ 'charset' => true, 'name' => true, 'content' => true ];
        $allowed['a']['href'] = true;
        $allowed['style']     = [];
        $clean                = wp_kses( $layout, $allowed, [ 'http', 'https', 'mailto' ] );
        $clean                = (string) preg_replace( '/\ssrc\s*=\s*(["\']).*?\1/i', '', $clean );
        $clean                = (string) preg_replace( '/@import\b[^;]*;?/i', '', $clean );
        $clean                = (string) preg_replace( '/url\(\s*(["\']?)\s*(?:https?:)?\/\/[^)]*\)/i', 'none', $clean );
        if ( false === strpos( $clean, '{content}' ) ) {
            throw new \InvalidArgumentException( esc_html__( 'The mail layout must contain {content}.', 'jp-form-builder' ) );
        }
        return $clean;
    }

    /** @param array<string,string> $context */
    public function wrap( string $content, array $context = [] ): string {
        $values = [
            '{content}'    => $content,
            '{site_name}'  => esc_html( $context['site_name'] ?? get_bloginfo( 'name' ) ),
            '{site_url}'   => esc_url( $context['site_url'] ?? home_url( '/' ) ),
            '{form_title}' => esc_html( $context['form_title'] ?? '' ),
        ];
        return strtr( $this->sanitize( $this->get_layout() ), $values );
    }
}
