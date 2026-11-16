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
        $mailer = new Mailer();
        $mailer->init();
        ( new Mail_Log() )->init();
        Form_Post_Type::init();
        ( new Settings_Page( null, $mailer ) )->init();
        ( new Mail_Preview_Controller() )->init();
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            ( new Cli_Command( null, $mailer ) )->init();
        }
    }
}
