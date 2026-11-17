<?php
/**
 * Represents a parsed Form.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Definition {

    public int $id;

    public string $title;

    public string $rev;

    /** @var array<string,mixed> */
    public array $attributes;

    /** @var array<int,Field_Definition|array<string,mixed>> */
    public array $items;

    /**
     * @param array<string,mixed>                            $attributes Form attributes.
     * @param array<int,Field_Definition|array<string,mixed>> $items      Ordered Form contents.
     */
    public function __construct( int $id, string $title, string $rev, array $attributes, array $items ) {
        $this->id         = $id;
        $this->title      = $title;
        $this->rev        = $rev;
        $this->attributes = $attributes;
        $this->items      = $items;
    }

    /** The `rev` of a Form's post content: it identifies the Fields a visitor saw, whichever revision is current later. */
    public static function hash_content( string $content ): string {
        return substr( hash( 'sha256', $content ), 0, 16 );
    }

    /** @return Field_Definition[] */
    public function fields(): array {
        return array_values( array_filter( $this->items, static fn( $item ) => $item instanceof Field_Definition ) );
    }
}
