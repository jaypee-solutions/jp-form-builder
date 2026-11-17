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

    /** Builds the services once and shares them: every `Settings` or `Form_Repository` would repeat the same reads. */
    public static function init(): void {
        $settings   = new Settings();
        $repository = new Form_Repository();
        $client_ip  = Client_Ip::from_settings( $settings );
        $backup     = new Form_Backup( $settings );
        $log        = new Mail_Log();
        $log->init();
        $mailer = new Mailer( $settings, $log );
        $mailer->init();
        Form_Post_Type::init();
        ( new Blocks( $settings, $repository ) )->init();
        ( new Settings_Page( $settings, $mailer, $backup, $repository, $client_ip, $log ) )->init();
        ( new Privacy() )->init();
        ( new Mail_Preview_Controller( $settings ) )->init();
        ( new Rest_Controller( new Submission_Handler( $mailer, $settings, $repository ), $settings, $repository, null, $client_ip ) )->init();
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            ( new Cli_Command( $settings, $mailer, $backup ) )->init();
        }
    }
}
