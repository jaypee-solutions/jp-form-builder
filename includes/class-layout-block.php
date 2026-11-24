<?php
/**
 * Represents a core layout block (Columns, Column, Group) in a Form.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Layout_Block {

    /** @var array<string,mixed> The parsed block; `innerBlocks` is empty and `innerContent` holds one `null` per item. */
    public array $block;

    /** @var array<int,Field_Definition|Layout_Block|array<string,mixed>> Kept children in document order. */
    public array $items;

    /**
     * @param array<string,mixed>                                               $block
     * @param array<int,Field_Definition|Layout_Block|array<string,mixed>> $items
     */
    public function __construct( array $block, array $items ) {
        $this->block = $block;
        $this->items = $items;
    }
}
