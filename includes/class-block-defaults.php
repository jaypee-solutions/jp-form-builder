<?php
/**
 * Reads built block attribute defaults.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Block_Defaults {

    private string $directory;

    /** @var array<string,array<string,mixed>>|null Read from the `block.json` files on first use, once. */
    private ?array $defaults = null;

    public function __construct( ?string $directory = null ) {
        $this->directory = $directory ?? dirname( __DIR__ ) . '/blocks';
    }

    /**
     * `parse_blocks()` omits attributes left at their defaults, so the defaults are merged back in.
     *
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    public function merge( string $block_name, array $attributes ): array {
        return array_merge( $this->all()[ $block_name ] ?? [], $attributes );
    }

    /** @return array<string,array<string,mixed>> */
    private function all(): array {
        if ( null === $this->defaults ) {
            $this->defaults = [];
            $files          = glob( $this->directory . '/*/block.json' );
            foreach ( false === $files ? [] : $files as $file ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local block.json file.
                $metadata = json_decode( (string) file_get_contents( $file ), true );
                if ( ! is_array( $metadata ) || empty( $metadata['name'] ) ) {
                    continue;
                }
                foreach ( $metadata['attributes'] ?? [] as $name => $attribute ) {
                    if ( array_key_exists( 'default', $attribute ) ) {
                        $this->defaults[ $metadata['name'] ][ $name ] = $attribute['default'];
                    }
                }
            }
        }
        return $this->defaults;
    }
}
