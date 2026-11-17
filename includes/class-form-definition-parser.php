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
            $item = Form_Blocks::CONDITIONAL === ( $block['blockName'] ?? '' )
                ? $this->parse_group( $block, $counts, $keys )
                : $this->parse_item( $block, $counts, $keys );
            if ( null !== $item ) {
                $items[] = $item;
            }
        }

        return new Form_Definition( $id, $title, Form_Definition::hash_content( $content ), $root_attrs, $items );
    }

    /**
     * A Conditional group keeps its Fields and pass-through blocks; a nested group is dropped like any foreign block.
     * Its Condition is valid only when the source is a Field before the group, so a source that is missing, later or
     * inside the group itself leaves the group always shown.
     *
     * @param array<string,mixed> $block  The group block.
     * @param array<string,int>   $counts Fields seen per type; updated.
     * @param array<string,true>  $keys   Field keys taken so far; updated.
     */
    private function parse_group( array $block, array &$counts, array &$keys ): Conditional_Group {
        $attrs    = $this->defaults->merge( Form_Blocks::CONDITIONAL, $block['attrs'] ?? [] );
        $earlier  = $keys;
        $source   = Field_Key::normalize( (string) ( $attrs['sourceField'] ?? '' ) );
        $operator = (string) ( $attrs['operator'] ?? '' );
        $values   = is_array( $attrs['values'] ?? null ) ? array_values( array_filter( $attrs['values'], 'is_string' ) ) : [];
        $valid    = '' !== $source && isset( $earlier[ $source ] ) && in_array( $operator, Condition::OPERATORS, true );

        $items = [];
        foreach ( $block['innerBlocks'] ?? [] as $child ) {
            $item = $this->parse_item( $child, $counts, $keys );
            if ( null !== $item ) {
                $items[] = $item;
            }
        }
        return new Conditional_Group( new Condition( $source, $operator, $values, $valid ), $items );
    }

    /**
     * @param array<string,mixed> $block  A Field block or a pass-through block; any other block yields null.
     * @param array<string,int>   $counts Fields seen per type; updated.
     * @param array<string,true>  $keys   Field keys taken so far; updated.
     * @return Field_Definition|array<string,mixed>|null
     */
    private function parse_item( array $block, array &$counts, array &$keys ) {
        $name = (string) ( $block['blockName'] ?? '' );
        if ( in_array( $name, Form_Blocks::PASS_THROUGH, true ) ) {
            return $block;
        }
        $type = Form_Blocks::field_type( $name );
        if ( '' === $type ) {
            return null;
        }

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
        return new Field_Definition( $type, $key, $attrs, $subkeys );
    }
}
