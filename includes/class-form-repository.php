<?php
/** Loads published Forms and retained revisions. */
declare( strict_types = 1 );
namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;
final class Form_Repository {
    private Form_Definition_Parser $parser;
    public function __construct( ?Form_Definition_Parser $parser = null ) {
        $this->parser = $parser ?? new Form_Definition_Parser(); }
    public function find( int $id ): ?Form_Definition {
        $post = get_post( $id );
        if ( ! $post || 'jp_form' !== $post->post_type || 'publish' !== $post->post_status ) {
            return null; }
        return $this->parser->parse( parse_blocks( $post->post_content ), $id, get_the_title( $post ), $post->post_content );
    }
    public function find_fields( int $id, string $rev ): ?Form_Definition {
        $form = $this->find( $id );
        if ( $form && hash_equals( $form->rev, $rev ) ) {
            return $form; }
        $revisions = wp_get_post_revisions( $id, [ 'numberposts' => 10, 'orderby' => 'date', 'order' => 'DESC' ] );
        foreach ( $revisions as $revision ) {
            $revision_rev = substr( hash( 'sha256', $revision->post_content ), 0, 16 );
            if ( hash_equals( $revision_rev, $rev ) ) {
                return $this->parser->parse( parse_blocks( $revision->post_content ), $id, get_the_title( $id ), $revision->post_content ); }
        }
        return null;
    }
}
