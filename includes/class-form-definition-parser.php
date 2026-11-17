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

        $root_attrs = $this->defaults->merge( Form_Blocks::FORM, $root['attrs'] ?? [] );
        $counts     = [];
        $keys       = [];
        $items      = [];

        foreach ( $root['innerBlocks'] ?? [] as $block ) {
            $name = (string) ( $block['blockName'] ?? '' );
            $type = Form_Blocks::field_type( $name );
            if ( '' !== $type ) {
                $attrs           = $this->defaults->merge( $name, $block['attrs'] ?? [] );
                $counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
                $key             = Field_Key::normalize( (string) ( $attrs['fieldName'] ?? '' ) );
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
            } elseif ( in_array( $name, Form_Blocks::PASS_THROUGH, true ) ) {
                $items[] = $block;
            }
        }

        return new Form_Definition( $id, $title, Form_Definition::hash_content( $content ), $root_attrs, $items );
    }
}
