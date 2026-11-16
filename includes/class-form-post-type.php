<?php
/**
 * Registers the reusable Form post type.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Post_Type {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_filter( 'allowed_block_types_all', [ __CLASS__, 'allowed_blocks' ], 10, 2 );
    }

    public static function capability(): string {
        return (string) apply_filters( 'jp_form_builder_capability', 'manage_options' );
    }

    public static function register(): void {
        $capability = self::capability();
        $caps       = [];
        foreach ( [ 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ] as $primitive ) {
            $caps[ $primitive ] = $capability;
        }
        register_post_type(
            'jp_form',
            [
                'labels'             => [ 'name' => __( 'Forms', 'jp-form-builder' ), 'singular_name' => __( 'Form', 'jp-form-builder' ) ],
                'public'             => false,
                'publicly_queryable' => false,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'menu_position'      => 25,
                'menu_icon'          => 'dashicons-feedback',
                'show_in_rest'       => true,
                'rest_controller_class' => Form_Posts_Controller::class,
                'supports'           => [ 'title', 'editor', 'revisions' ],
                'capability_type'    => [ 'jp_form', 'jp_forms' ],
                'capabilities'       => $caps,
                'template'           => [ [ 'jp-form-builder/form', [] ] ],
                'template_lock'      => 'insert',
                'map_meta_cap'       => false,
            ]
        );
    }

    /** @param string[]|bool $allowed @param WP_Block_Editor_Context $context */
    public static function allowed_blocks( $allowed, $context ) {
        if ( empty( $context->post ) || 'jp_form' !== $context->post->post_type ) {
            return $allowed;
        }
        return [
            'jp-form-builder/form',
            'jp-form-builder/name',
            'jp-form-builder/email',
            'jp-form-builder/phone',
            'jp-form-builder/address',
            'jp-form-builder/text',
            'jp-form-builder/select',
            'jp-form-builder/textarea',
            'jp-form-builder/consent',
            'core/paragraph',
            'core/heading',
        ];
    }
}
