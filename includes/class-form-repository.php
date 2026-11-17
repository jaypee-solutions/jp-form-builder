<?php
/**
 * Loads published Forms and retained revisions.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Repository {

    private Form_Definition_Parser $parser;

    public function __construct( ?Form_Definition_Parser $parser = null ) {
        $this->parser = $parser ?? new Form_Definition_Parser();
    }

    public function find( int $id ): ?Form_Definition {
        $post = get_post( $id );
        if ( ! $post || 'jp_form' !== $post->post_type || 'publish' !== $post->post_status ) {
            return null;
        }
        return $this->parser->parse( parse_blocks( $post->post_content ), $id, get_the_title( $post ), $post->post_content );
    }

    /** @return Form_Definition[] Every published Form. */
    public function find_all(): array {
        $forms = [];
        foreach ( get_posts( [ 'post_type' => 'jp_form', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $post ) {
            $form = $this->find( $post->ID );
            if ( null !== $form ) {
                $forms[] = $form;
            }
        }
        return $forms;
    }

    /** The Form as it was when its content hashed to `$rev`: the current content or one of its last revisions; null when none matches. */
    public function find_by_rev( int $id, string $rev ): ?Form_Definition {
        $form = $this->find( $id );
        if ( $form && hash_equals( $form->rev, $rev ) ) {
            return $form;
        }

        $revisions = wp_get_post_revisions( $id, [ 'numberposts' => 10, 'orderby' => 'date', 'order' => 'DESC' ] );
        foreach ( $revisions as $revision ) {
            if ( hash_equals( Form_Definition::hash_content( $revision->post_content ), $rev ) ) {
                return $this->parser->parse( parse_blocks( $revision->post_content ), $id, get_the_title( $id ), $revision->post_content );
            }
        }
        return null;
    }
}
