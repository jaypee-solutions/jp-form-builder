<?php
/**
 * A Field and its value in one Submission.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Submitted_Field {

    /** @var string|string[] */
    private $value;
    private string $key;
    private string $label;
    private string $type;
    private bool $reply_to;

    /** @param string|string[] $value */
    public function __construct( string $key, string $label, string $type, $value, bool $reply_to = false ) {
        if ( ! is_string( $value ) && ! is_array( $value ) ) {
            throw new \InvalidArgumentException( 'Field value must be a string or sub-key map.' );
        }
        $this->key      = $key;
        $this->label    = $label;
        $this->type     = $type;
        $this->value    = $value;
        $this->reply_to = $reply_to;
    }

    public function key(): string {
        return $this->key;
    }

    public function label(): string {
        return $this->label;
    }

    public function type(): string {
        return $this->type;
    }

    /** @return string|string[] */
    public function value() {
        return $this->value;
    }

    /** The value as one string: the parts of a compound Field joined with spaces. */
    public function as_text(): string {
        return is_array( $this->value ) ? implode( ' ', array_map( 'strval', $this->value ) ) : $this->value;
    }

    public function is_reply_to(): bool {
        return $this->reply_to;
    }
}
