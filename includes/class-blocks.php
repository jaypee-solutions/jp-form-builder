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

    private Settings $settings;
    private Form_Repository $repository;

    public function __construct( ?Settings $settings = null, ?Form_Repository $repository = null ) {
        $this->settings   = $settings ?? new Settings();
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
                        $markup = ( new Form_Renderer() )->render( $form, 0, $this->turnstile_site_key(), $this->settings->min_fill_seconds() );
                        $style  = Form_Style::declarations( $this->with_global_gap( $attributes ) );
                        return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes( '' !== $style ? [ 'style' => $style ] : [] ), $markup );
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
     * WordPress outputs a block's Global Styles "Block spacing" only as `gap` for blocks with a layout, which the embed
     * has not, so it reaches the Fields through the same custom property as the block's own setting.
     *
     * @param array<string,mixed> $attributes Embed block attributes.
     * @return array<string,mixed> The attributes, with the Global Styles gap when the block sets none.
     */
    private function with_global_gap( array $attributes ): array {
        if ( '' === Form_Style::gap( $attributes['style']['spacing']['blockGap'] ?? '' ) ) {
            $attributes['style']['spacing']['blockGap'] = wp_get_global_styles( [ 'spacing', 'blockGap' ], [ 'block_name' => 'jp-form-builder/embed' ] );
        }
        return $attributes;
    }

    /**
     * Hands the editor scripts the data that lives in PHP: translated country names,
     * whether Turnstile is configured, what the current user may do with Forms, and, when
     * editing a Form, the core code editor settings for the Body template modal.
     */
    public function enqueue_editor_data(): void {
        $regions = array_intersect_key( Countries::all(), array_flip( [ 'DE', 'AT', 'CH' ] ) );
        $data    = [
            'address' => [ 'countries' => Countries::all() ],
            'phone'   => [ 'regions' => $regions ],
            'form'    => [ 'turnstileConfigured' => '' !== $this->turnstile_site_key() ],
            'embed'   => [
                'canEditForms' => current_user_can( Form_Post_Type::capability() ),
                'editFormUrl'  => admin_url( 'post.php' ),
            ],
        ];
        if ( 'jp_form' === get_post_type() ) {
            // False when the user turned syntax highlighting off; the modal then falls back to a plain textarea.
            $code_editor = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
            if ( false !== $code_editor ) {
                $data['form']['codeEditor'] = $code_editor;
            }
        }
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
    private function turnstile_site_key(): string {
        return $this->settings->turnstile_configured() ? (string) $this->settings->get( 'turnstile_site_key' ) : '';
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
