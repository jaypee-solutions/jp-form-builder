<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads only the plugin classes needed for isolated unit tests — no wp-env /
 * WordPress test suite required.
 *
 * Call: vendor/bin/phpunit
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

require dirname( __DIR__ ) . '/vendor/autoload.php';

// The plugin classes check defined( 'ABSPATH' ) || exit.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require __DIR__ . '/stubs.php';
require dirname( __DIR__ ) . '/includes/class-updates.php';
require dirname( __DIR__ ) . '/includes/class-secret-box.php';
require dirname( __DIR__ ) . '/includes/class-settings.php';
require dirname( __DIR__ ) . '/includes/class-mail.php';
require dirname( __DIR__ ) . '/includes/class-submitted-field.php';
require dirname( __DIR__ ) . '/includes/class-mail-header-guard.php';
require dirname( __DIR__ ) . '/includes/class-mail-template.php';
require dirname( __DIR__ ) . '/includes/class-mail-layout.php';
require dirname( __DIR__ ) . '/includes/class-mail-builder.php';
