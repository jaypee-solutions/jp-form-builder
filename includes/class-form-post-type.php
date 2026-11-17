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
        add_filter( 'bulk_post_updated_messages', [ __CLASS__, 'bulk_messages' ], 10, 2 );
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
                'labels'             => self::labels(),
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

    /** Without a full set, WordPress falls back to the Post labels ("Add Post", "Post published."). */
    private static function labels(): array {
        return [
            'name'                     => __( 'Forms', 'jp-form-builder' ),
            'singular_name'            => __( 'Form', 'jp-form-builder' ),
            'menu_name'                => __( 'Forms', 'jp-form-builder' ),
            'name_admin_bar'           => __( 'Form', 'jp-form-builder' ),
            'add_new'                  => __( 'Add Form', 'jp-form-builder' ),
            'add_new_item'             => __( 'Add Form', 'jp-form-builder' ),
            'new_item'                 => __( 'New Form', 'jp-form-builder' ),
            'edit_item'                => __( 'Edit Form', 'jp-form-builder' ),
            'view_item'                => __( 'View Form', 'jp-form-builder' ),
            'view_items'               => __( 'View Forms', 'jp-form-builder' ),
            'all_items'                => __( 'All Forms', 'jp-form-builder' ),
            'search_items'             => __( 'Search Forms', 'jp-form-builder' ),
            'not_found'                => __( 'No Forms found.', 'jp-form-builder' ),
            'not_found_in_trash'       => __( 'No Forms found in Trash.', 'jp-form-builder' ),
            'filter_items_list'        => __( 'Filter Forms list', 'jp-form-builder' ),
            'items_list_navigation'    => __( 'Forms list navigation', 'jp-form-builder' ),
            'items_list'               => __( 'Forms list', 'jp-form-builder' ),
            'item_published'           => __( 'Form published.', 'jp-form-builder' ),
            'item_published_privately' => __( 'Form published privately.', 'jp-form-builder' ),
            'item_reverted_to_draft'   => __( 'Form reverted to draft.', 'jp-form-builder' ),
            'item_trashed'             => __( 'Form trashed.', 'jp-form-builder' ),
            'item_scheduled'           => __( 'Form scheduled.', 'jp-form-builder' ),
            'item_updated'             => __( 'Form updated.', 'jp-form-builder' ),
        ];
    }

    /**
     * @param array<string,array<string,string>> $messages
     * @param array<string,int>                  $counts
     * @return array<string,array<string,string>>
     */
    public static function bulk_messages( array $messages, array $counts ): array {
        $messages['jp_form'] = [
            /* translators: %s: number of Forms. */
            'updated'   => _n( '%s Form updated.', '%s Forms updated.', $counts['updated'], 'jp-form-builder' ),
            /* translators: %s: number of Forms. */
            'locked'    => _n( '%s Form not updated, somebody is editing it.', '%s Forms not updated, somebody is editing them.', $counts['locked'], 'jp-form-builder' ),
            /* translators: %s: number of Forms. */
            'deleted'   => _n( '%s Form permanently deleted.', '%s Forms permanently deleted.', $counts['deleted'], 'jp-form-builder' ),
            /* translators: %s: number of Forms. */
            'trashed'   => _n( '%s Form moved to the Trash.', '%s Forms moved to the Trash.', $counts['trashed'], 'jp-form-builder' ),
            /* translators: %s: number of Forms. */
            'untrashed' => _n( '%s Form restored from the Trash.', '%s Forms restored from the Trash.', $counts['untrashed'], 'jp-form-builder' ),
        ];
        return $messages;
    }

    /** @param string[]|bool $allowed @param WP_Block_Editor_Context $context */
    public static function allowed_blocks( $allowed, $context ) {
        if ( empty( $context->post ) || 'jp_form' !== $context->post->post_type ) {
            return $allowed;
        }
        return Form_Blocks::allowed_in_editor();
    }
}
