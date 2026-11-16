<?php
/**
 * Registers the plugin's features.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function init(): void {
		Form_Post_Type::init();
		( new Settings_Page() )->init();
	}
}
