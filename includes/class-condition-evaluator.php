<?php
/**
 * Evaluates Conditions on Field values. The JS mirror is src/shared/condition.js; tests/fixtures/conditions.json drives both.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Condition_Evaluator {

    /**
     * Whether the Condition holds for the Field values. A missing value counts as empty.
     *
     * Values are a string, a list of strings (Checkboxes) or a sub-key map of strings (compound Fields).
     *
     * @param array<string,mixed> $values Field values keyed by Field key.
     */
    public function is_met( Condition $condition, array $values ): bool {
        $value = $values[ $condition->source ] ?? '';
        $one   = is_string( $value ) ? $value : '';

        switch ( $condition->operator ) {
            case 'is':
                return [] !== $condition->values && $one === $condition->values[0];
            case 'is_not':
                return [] === $condition->values || $one !== $condition->values[0];
            case 'is_one_of':
                return in_array( $one, $condition->values, true );
            case 'includes':
                return [] !== array_intersect( $this->parts( $value ), $condition->values );
            case 'is_checked':
                return '1' === $one;
            case 'is_not_checked':
                return '1' !== $one;
            case 'is_filled':
                return $this->is_filled( $value );
            case 'is_empty':
                return ! $this->is_filled( $value );
            default:
                return false;
        }
    }

    /** Whether a group is shown: always when its Condition is invalid. */
    public function is_shown( Conditional_Group $group, array $values ): bool {
        return ! $group->condition->valid || $this->is_met( $group->condition, $values );
    }

    /**
     * Evaluates the groups in document order. The Fields of a hidden group count as empty for later Conditions.
     *
     * @param Conditional_Group[] $groups Groups in document order.
     * @param array<string,mixed> $values Field values keyed by Field key.
     * @return array{shown: bool[], hidden: string[], values: array<string,mixed>} Visibility per group, keys of the hidden Fields, and the values without them.
     */
    public function resolve( array $groups, array $values ): array {
        $shown  = [];
        $hidden = [];
        foreach ( $groups as $group ) {
            $is_shown = $this->is_shown( $group, $values );
            $shown[]  = $is_shown;
            if ( $is_shown ) {
                continue;
            }
            foreach ( $group->fields() as $field ) {
                $hidden[] = $field->key;
                unset( $values[ $field->key ] );
            }
        }
        return [
            'shown'  => $shown,
            'hidden' => $hidden,
            'values' => $values,
        ];
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function parts( $value ): array {
        if ( is_string( $value ) ) {
            return '' === $value ? [] : [ $value ];
        }
        return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : [];
    }

    /** @param mixed $value */
    private function is_filled( $value ): bool {
        foreach ( $this->parts( $value ) as $part ) {
            if ( '' !== trim( $part ) ) {
                return true;
            }
        }
        return false;
    }
}
