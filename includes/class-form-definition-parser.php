<?php
/**
 * Parses block trees into a Form definition.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Definition_Parser {

    private Block_Defaults $defaults;

    /** @param Block_Defaults|null $defaults Attribute defaults. */
    public function __construct( ?Block_Defaults $defaults = null ) {
        $this->defaults = $defaults ?? new Block_Defaults();
    }

    /** @param array<int,array<string,mixed>> $blocks */
    public function parse( array $blocks, int $id = 0, string $title = '', string $content = '' ): ?Form_Definition {
        $root = null;
        foreach ( $blocks as $block ) {
            if ( 'jp-form-builder/form' === ( $block['blockName'] ?? '' ) ) {
                $root = $block;
                break;
            }
        }
        if ( null === $root ) {
            return null;
        }

        $root_attrs = $this->defaults->merge( 'jp-form-builder/form', $root['attrs'] ?? [] );
        $allowed_types = [ 'name', 'email', 'phone', 'address', 'text', 'select', 'textarea', 'consent' ];
        $counts        = [];
        $keys          = [];
        $items         = [];

        foreach ( $root['innerBlocks'] ?? [] as $block ) {
            $name = (string) ( $block['blockName'] ?? '' );
            if ( str_starts_with( $name, 'jp-form-builder/' ) ) {
                $type = substr( $name, strlen( 'jp-form-builder/' ) );
                if ( ! in_array( $type, $allowed_types, true ) ) {
                    continue;
                }
                $attrs           = $this->defaults->merge( $name, $block['attrs'] ?? [] );
                $counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
                $key             = strtolower( (string) ( $attrs['fieldName'] ?? '' ) );
                $key             = preg_replace( '/[^a-z0-9_]/', '_', $key );
                $key             = trim( (string) $key, '_' );
                if ( '' === $key ) {
                    $key = $type . '_' . $counts[ $type ];
                }
                $base   = $key;
                $suffix = 2;
                while ( isset( $keys[ $key ] ) ) {
                    $key = $base . '_' . $suffix;
                    ++$suffix;
                }
                $keys[ $key ] = true;
                if ( 'consent' === $type ) {
                    $attrs['required'] = true;
                }
                $subkeys = 'name' === $type && ! empty( $attrs['split'] )
                    ? [ 'first', 'last' ]
                    : ( 'address' === $type ? [ 'street', 'postcode', 'city', 'country' ] : [] );
                $items[] = new Field_Definition( $type, $key, $attrs, $subkeys );
            } elseif ( in_array( $name, [ 'core/paragraph', 'core/heading' ], true ) ) {
                $items[] = $block;
            }
        }

        $rev = substr( hash( 'sha256', $content ), 0, 16 );
        return new Form_Definition( $id, $title, $rev, $root_attrs, $items );
    }
}
