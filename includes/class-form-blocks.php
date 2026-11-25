<?php
/**
 * Names the blocks a Form is made of.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Blocks {

    public const NAMESPACE_PREFIX = 'jp-form-builder/';

    public const FORM = 'jp-form-builder/form';

    /** The Conditional group block; it sits in the Form and holds Fields, never another group. */
    public const CONDITIONAL = 'jp-form-builder/conditional';

    /** Field types; each has the block `jp-form-builder/<type>`. */
    public const FIELD_TYPES = [ 'name', 'email', 'phone', 'address', 'date', 'text', 'select', 'radio', 'checkbox', 'textarea', 'consent' ];

    /** Core blocks that may sit between Fields and are rendered as they are. */
    public const PASS_THROUGH = [ 'core/paragraph', 'core/heading' ];

    /** Core layout blocks that may hold Fields and pass-through blocks; they never hold a Conditional group. */
    public const LAYOUT = [ 'core/columns', 'core/column', 'core/group' ];

    /** @return string The Field type of a block name, or '' when the block is not a Field block. */
    public static function field_type( string $block_name ): string {
        if ( ! str_starts_with( $block_name, self::NAMESPACE_PREFIX ) ) {
            return '';
        }
        $type = substr( $block_name, strlen( self::NAMESPACE_PREFIX ) );
        return in_array( $type, self::FIELD_TYPES, true ) ? $type : '';
    }

    /** @return string[] Blocks allowed inside a Conditional group or a layout block: Field blocks, pass-through blocks and layout blocks. */
    public static function group_children(): array {
        $fields = array_map( static fn( string $type ): string => self::NAMESPACE_PREFIX . $type, self::FIELD_TYPES );
        return array_merge( $fields, self::PASS_THROUGH, self::LAYOUT );
    }

    /** @return string[] Blocks allowed inside the Form block: Field blocks, pass-through blocks and Conditional groups. */
    public static function allowed_children(): array {
        return array_merge( self::group_children(), [ self::CONDITIONAL ] );
    }

    /** @return string[] Blocks allowed in the editor of a Form. */
    public static function allowed_in_editor(): array {
        return array_merge( [ self::FORM ], self::allowed_children() );
    }
}
