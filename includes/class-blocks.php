<?php
/**
 * Registers Form Builder blocks and routes.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Blocks {

    private Form_Repository $repository;

    public function __construct( ?Form_Repository $repository = null ) {
        $this->repository = $repository ?? new Form_Repository();
    }

    public function init(): void {
        add_action( 'init', [ $this, 'register' ] );
        add_action( 'rest_api_init', [ $this, 'routes' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_data' ] );
    }

    public function register(): void {
        $metadata_files = glob( dirname( __DIR__ ) . '/blocks/*/block.json' );
        foreach ( false === $metadata_files ? [] : $metadata_files as $metadata ) {
            $name = basename( dirname( $metadata ) );
            if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'jp-form-builder/' . $name ) ) {
                continue;
            }
            $block = register_block_type(
                dirname( $metadata ),
                [
                    'render_callback' => function ( $attributes, $content, $block ) use ( $name ) {
                        unset( $content, $block );
                        if ( 'embed' !== $name ) {
                            return '';
                        }
                        $form = $this->repository->find( (int) ( $attributes['formId'] ?? 0 ) );
                        if ( ! $form ) {
                            return '';
                        }
                        $site_key = $this->turnstile_site_key( new Settings() );
                        return ( new Form_Renderer() )->render( $form, 0, $site_key );
                    },
                ]
            );
            if ( $block && $block->editor_script ) {
                wp_set_script_translations( $block->editor_script, 'jp-form-builder', dirname( __DIR__ ) . '/languages' );
            }
            if ( $block && $block->view_script ) {
                wp_set_script_translations( $block->view_script, 'jp-form-builder', dirname( __DIR__ ) . '/languages' );
            }
        }
    }

    /**
     * Hands the editor scripts the data that lives in PHP: translated country names,
     * whether Turnstile is configured, and what the current user may do with Forms.
     */
    public function enqueue_editor_data(): void {
        $regions = array_intersect_key( Countries::all(), array_flip( [ 'DE', 'AT', 'CH' ] ) );
        $data    = [
            'address' => [ 'countries' => Countries::all() ],
            'phone'   => [ 'regions' => $regions ],
            'form'    => [ 'turnstileConfigured' => '' !== $this->turnstile_site_key( new Settings() ) ],
            'embed'   => [
                'canEditForms' => current_user_can( Form_Post_Type::capability() ),
                'editFormUrl'  => admin_url( 'post.php' ),
            ],
        ];
        foreach ( $data as $name => $block_data ) {
            $block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'jp-form-builder/' . $name );
            foreach ( $block_type ? $block_type->editor_script_handles : [] as $handle ) {
                wp_add_inline_script(
                    $handle,
                    'window.jpFormBuilderEditor = window.jpFormBuilderEditor || {}; window.jpFormBuilderEditor.' . $name . ' = ' . wp_json_encode( $block_data ) . ';',
                    'before'
                );
            }
        }
    }

    /** The Turnstile site key, or '' unless both Turnstile keys are set. */
    private function turnstile_site_key( Settings $settings ): string {
        return '' !== $settings->get( 'turnstile_site_key' ) && '' !== $settings->get( 'turnstile_secret_key' )
            ? (string) $settings->get( 'turnstile_site_key' )
            : '';
    }

    public function routes(): void {
        register_rest_route(
            'jp-form-builder/v1',
            '/forms',
            [
                'methods'             => 'GET',
                'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
                'callback'            => [ $this, 'list_forms' ],
            ]
        );
    }

    /** @return array<int,array{id:int,title:string}> */
    public function list_forms(): array {
        $posts = get_posts(
            [
                'post_type'   => 'jp_form',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
            ]
        );
        return array_map( static fn( $post ) => [ 'id' => (int) $post->ID, 'title' => get_the_title( $post ) ], $posts );
    }
}
