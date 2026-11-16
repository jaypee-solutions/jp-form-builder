<?php
/** Represents a parsed Form. */
declare( strict_types = 1 );
namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;
final class Form_Definition {
    public int $id;
    public string $title;
    public string $rev;
    /** @var array<string,mixed> */ public array $attributes;
    /** @var array<int,Field_Definition|array<string,mixed>> */ public array $items;
    public function __construct( int $id, string $title, string $rev, array $attributes, array $items ) {
        $this->id = $id;
        $this->title = $title;
        $this->rev = $rev;
        $this->attributes = $attributes;
        $this->items = $items;
    }
    /** @return Field_Definition[] */
    public function fields(): array {
        return array_values( array_filter( $this->items, static fn( $item ) => $item instanceof Field_Definition ) ); }
}
