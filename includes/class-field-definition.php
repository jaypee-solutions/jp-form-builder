<?php
/**
 * Represents a Field in a Form.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Field_Definition {

    public string $type;

    public string $key;

    /** @var array<string,mixed> */
    public array $attributes;

    /** @var string[] */
    public array $subkeys;

    /**
     * @param array<string,mixed> $attributes Field attributes.
     * @param string[]            $subkeys    Compound field keys.
     */
    public function __construct( string $type, string $key, array $attributes, array $subkeys = [] ) {
        $this->type       = $type;
        $this->key        = $key;
        $this->attributes = $attributes;
        $this->subkeys    = $subkeys;
    }
}
