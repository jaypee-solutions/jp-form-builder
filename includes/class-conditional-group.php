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

    /** @var array<int,Field_Definition|array<string,mixed>> Fields and pass-through blocks; groups never nest. */
    public array $items;

    /** @param array<int,Field_Definition|array<string,mixed>> $items */
    public function __construct( Condition $condition, array $items ) {
        $this->condition = $condition;
        $this->items     = $items;
    }

    /** @return Field_Definition[] */
    public function fields(): array {
        return array_values( array_filter( $this->items, static fn( $item ) => $item instanceof Field_Definition ) );
    }
}
