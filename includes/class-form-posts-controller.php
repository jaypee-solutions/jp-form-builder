<?php
/**
 * Restricts REST reads of Forms to the configured Form capability.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use WP_REST_Posts_Controller;

defined( 'ABSPATH' ) || exit;

final class Form_Posts_Controller extends WP_REST_Posts_Controller {

    public function get_item_permissions_check( $request ) {
        return current_user_can( Form_Post_Type::capability() );
    }

    public function get_items_permissions_check( $request ) {
        return current_user_can( Form_Post_Type::capability() );
    }
}
