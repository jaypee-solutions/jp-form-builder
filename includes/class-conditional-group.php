<?php
/**
 * Represents a Conditional group in a Form.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Conditional_Group {

    public Condition $condition;

    /** @var array<int,Field_Definition|Layout_Block|array<string,mixed>> Fields, layout blocks and pass-through blocks; groups never nest. */
    public array $items;

    /** @param array<int,Field_Definition|Layout_Block|array<string,mixed>> $items */
    public function __construct( Condition $condition, array $items ) {
        $this->condition = $condition;
        $this->items     = $items;
    }

    /** @return Field_Definition[] */
    public function fields(): array {
        return Form_Definition::fields_in( $this->items );
    }
}
