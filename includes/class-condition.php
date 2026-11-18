<?php
/**
 * Represents the Condition of a Conditional group.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Condition {

    /** Keep in step with `operator` in blocks/conditional/block.json and OPERATORS in src/shared/condition.js. */
    public const OPERATORS = [ 'is', 'is_not', 'is_one_of', 'includes', 'is_checked', 'is_not_checked', 'is_filled', 'is_empty' ];

    /** Field key of the Field the Condition looks at. */
    public string $source;

    public string $operator;

    /** @var string[] */
    public array $values;

    /** False when the source is missing, not earlier in the Form, or the operator is unknown; the group is then always shown. */
    public bool $valid;

    /** @param string[] $values */
    public function __construct( string $source, string $operator, array $values, bool $valid = true ) {
        $this->source   = $source;
        $this->operator = $operator;
        $this->values   = $values;
        $this->valid    = $valid;
    }

    /** @return array{sourceField: string, operator: string, values: string[]} The format of `data-condition`. */
    public function to_array(): array {
        return [
            'sourceField' => $this->source,
            'operator'    => $this->operator,
            'values'      => $this->values,
        ];
    }
}
