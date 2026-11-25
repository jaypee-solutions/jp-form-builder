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
            $item = $this->parse_child( $block, true, $counts, $keys );
            if ( null !== $item ) {
                $items[] = $item;
            }
        }

        return new Form_Definition( $id, $title, Form_Definition::hash_content( $content ), $root_attrs, $items );
    }

    /**
     * A Conditional group keeps its Fields, layout blocks and pass-through blocks; a nested group is dropped like any foreign block.
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
            $item = $this->parse_child( $child, false, $counts, $keys );
            if ( null !== $item ) {
                $items[] = $item;
            }
        }
        return new Conditional_Group( new Condition( $source, $operator, $values, $valid ), $items );
    }

    /**
     * @param array<string,mixed> $block        Any child of the Form, of a group or of a layout block.
     * @param bool                $allow_groups Whether a Conditional group may sit here; only the Form itself allows it.
     * @param array<string,int>   $counts       Fields seen per type; updated.
     * @param array<string,true>  $keys         Field keys taken so far; updated.
     * @return Field_Definition|Conditional_Group|Layout_Block|array<string,mixed>|null
     */
    private function parse_child( array $block, bool $allow_groups, array &$counts, array &$keys ) {
        $name = (string) ( $block['blockName'] ?? '' );
        if ( Form_Blocks::CONDITIONAL === $name ) {
            return $allow_groups ? $this->parse_group( $block, $counts, $keys ) : null;
        }
        if ( in_array( $name, Form_Blocks::LAYOUT, true ) ) {
            return $this->parse_layout( $block, $counts, $keys );
        }
        return $this->parse_item( $block, $counts, $keys );
    }

    /**
     * A layout block keeps its own markup; children that are not allowed are dropped together with their placeholder
     * in innerContent. Walking innerContent assigns Field keys in document order and keeps placeholders aligned with items.
     *
     * @param array<string,mixed> $block  The layout block.
     * @param array<string,int>   $counts Fields seen per type; updated.
     * @param array<string,true>  $keys   Field keys taken so far; updated.
     */
    private function parse_layout( array $block, array &$counts, array &$keys ): Layout_Block {
        $children = $block['innerBlocks'] ?? [];
        $content  = [];
        $items    = [];
        $next     = 0;
        foreach ( $block['innerContent'] ?? [] as $chunk ) {
            if ( null !== $chunk ) {
                $content[] = $chunk;
                continue;
            }
            $item = isset( $children[ $next ] ) ? $this->parse_child( $children[ $next ], false, $counts, $keys ) : null;
            ++$next;
            if ( null !== $item ) {
                $items[]   = $item;
                $content[] = null;
            }
        }
        return new Layout_Block( array_merge( $block, [ 'innerBlocks' => [], 'innerContent' => $content ] ), $items );
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
        $subkeys = 'name' === $type && ! empty( $attrs['split'] ) ? [ 'first', 'last' ] : [];
        return new Field_Definition( $type, $key, $attrs, $subkeys );
    }
}
