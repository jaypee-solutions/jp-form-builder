<?php
/**
 * WordPress integration-test bootstrap.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
// Keep one constant-locked setting available to every test without defining constants mid-suite.
define( 'JP_FORM_BUILDER_SMTP_PORT', 2525 );
require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function (): void {
        require_once dirname( __DIR__, 2 ) . '/jp-form-builder.php';
    }
);

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
require __DIR__ . '/helpers.php';

/**
 * Detect handlers that terminate PHP before PHPUnit can report a result.
 */
final class Integration_Run_Completion implements \PHPUnit\Runner\AfterLastTestHook {

    public static bool $completed = false;

    public function executeAfterLastTest(): void {
        self::$completed = true;
    }
}

register_shutdown_function(
    static function (): void {
        if ( ! Integration_Run_Completion::$completed ) {
            fwrite( STDERR, PHP_EOL . 'Integration run aborted before PHPUnit finished: a test or handler reached exit()/die().' . PHP_EOL );
            exit( 1 );
        }
    }
);
