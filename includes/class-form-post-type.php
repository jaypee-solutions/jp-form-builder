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
				'map_meta_cap'       => false,
			]
		);
	}
}
