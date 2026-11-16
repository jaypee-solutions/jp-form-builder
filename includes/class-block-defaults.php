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

    /** @var array<string,array<string,mixed>> */
    private array $defaults;

    public function __construct( ?string $directory = null ) {
        $directory       = $directory ?? dirname( __DIR__ ) . '/blocks';
        $this->defaults  = [];
        $files           = glob( $directory . '/*/block.json' );

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

    /** @return array<string,mixed> */
    public function merge( string $block_name, array $attributes ): array {
        return array_merge( $this->defaults[ $block_name ] ?? [], $attributes );
    }

    /** @return array<string,mixed> */
    public function for_block( string $block_name ): array {
        return $this->defaults[ $block_name ] ?? [];
    }
}
