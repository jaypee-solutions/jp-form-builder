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
        $log = new Mail_Log();
        $log->init();
        $mailer = new Mailer( null, $log );
        $mailer->init();
        Form_Post_Type::init();
        ( new Blocks() )->init();
        ( new Settings_Page( null, $mailer ) )->init();
        ( new Privacy() )->init();
        ( new Mail_Preview_Controller() )->init();
        ( new Rest_Controller( new Submission_Handler( $mailer ) ) )->init();
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            ( new Cli_Command( null, $mailer ) )->init();
        }
    }
}
