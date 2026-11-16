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

    public function __construct( ?Settings $settings = null, ?string $default_file = null ) {
        $this->settings     = $settings ?? new Settings();
        $this->default_file = $default_file ?? dirname( __DIR__ ) . '/templates/mail/layout.html';
    }

    public function get_layout(): string {
        $configured = (string) $this->settings->get( 'mail_layout' );
        $layout     = '' !== trim( $configured ) ? $configured : (string) file_get_contents( $this->default_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return (string) apply_filters( 'jp_form_builder_mail_layout', $layout );
    }

    /**
     * @throws \InvalidArgumentException When {content} is absent.
     */
    public function sanitize( string $layout ): string {
        $allowed = wp_kses_allowed_html( 'post' );
        $tags    = [ 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'div', 'span', 'p', 'a', 'strong', 'em', 'br', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'style' ];
        foreach ( $tags as $tag ) {
            $allowed[ $tag ] = [ 'style' => true, 'class' => true, 'align' => true, 'valign' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'width' => true, 'height' => true ];
        }
        $allowed['a']['href'] = true;
        $allowed['style']    = [];
        $clean = wp_kses( $layout, $allowed, [ 'http', 'https', 'mailto' ] );
        $clean = preg_replace_callback(
            '/\s(src|href)\s*=\s*(["\'])(.*?)\2/i',
            static function ( array $attribute_match ): string {
                $attribute = strtolower( $attribute_match[1] );
                $url       = trim( $attribute_match[3] );
                if ( 'src' === $attribute || preg_match( '#^(?:https?:)?//#i', $url ) ) {
                    return '';
                }
                return $attribute_match[0];
            },
            $clean
        );
        $clean = preg_replace( '#<img\b[^>]*>#i', '', (string) $clean );
        if ( false === strpos( (string) $clean, '{content}' ) ) {
            throw new \InvalidArgumentException( 'Mail layout must contain {content}.' );
        }
        return (string) $clean;
    }

    /** @param array<string,string> $context */
    public function wrap( string $content, array $context = [], ?string $layout_override = null ): string {
        $layout = null === $layout_override ? $this->get_layout() : $layout_override;
        $layout = $this->sanitize( $layout );
        $values = [
            '{content}'    => $content,
            '{site_name}'  => esc_html( $context['site_name'] ?? get_bloginfo( 'name' ) ),
            '{site_url}'   => esc_url( $context['site_url'] ?? home_url( '/' ) ),
            '{form_title}' => esc_html( $context['form_title'] ?? '' ),
        ];
        return strtr( $layout, $values );
    }
}
