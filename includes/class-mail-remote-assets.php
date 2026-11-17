<?php
/**
 * Keeps mail from fetching remote content: a mail client must not tell a third party that it was opened.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail_Remote_Assets {

    /** Elements that load content from a URL. */
    private const FETCHING_TAGS = [ 'img', 'picture', 'source', 'video', 'audio', 'track', 'iframe', 'object', 'embed', 'form' ];

    /**
     * @param array<string,mixed> $allowed A kses allow-list.
     * @return array<string,mixed> The allow-list without the elements that fetch content.
     */
    public static function without_fetching_tags( array $allowed ): array {
        return array_diff_key( $allowed, array_flip( self::FETCHING_TAGS ) );
    }

    /**
     * Removes what is left after kses: image elements, attributes that carry a source, `@import` and CSS `url()` to a remote host.
     * Expects markup that kses has normalised, whose attribute values are quoted.
     */
    public static function strip( string $html ): string {
        $html = (string) preg_replace( '#<img\b[^>]*>#i', '', $html );
        $html = (string) preg_replace( '/\s(?:src|srcset|poster|background)\s*=\s*(["\']).*?\1/is', '', $html );
        $html = (string) preg_replace( '/@import\b[^;]*;?/i', '', $html );
        return (string) preg_replace( '/url\(\s*(["\']?)\s*(?:https?:)?\/\/[^)]*\)/i', 'none', $html );
    }
}
