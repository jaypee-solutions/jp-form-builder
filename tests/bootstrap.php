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
require dirname( __DIR__ ) . '/includes/class-form-backup.php';
require dirname( __DIR__ ) . '/includes/class-mail.php';
require dirname( __DIR__ ) . '/includes/class-submitted-field.php';
require dirname( __DIR__ ) . '/includes/class-mail-header-guard.php';
require dirname( __DIR__ ) . '/includes/class-recipient-list.php';
require dirname( __DIR__ ) . '/includes/class-mail-remote-assets.php';
require dirname( __DIR__ ) . '/includes/class-mail-template.php';
require dirname( __DIR__ ) . '/includes/class-mail-layout.php';
require dirname( __DIR__ ) . '/includes/class-mail-log.php';
require dirname( __DIR__ ) . '/includes/class-mailer.php';
require dirname( __DIR__ ) . '/includes/class-mail-builder.php';
require dirname( __DIR__ ) . '/includes/class-form-blocks.php';
require dirname( __DIR__ ) . '/includes/class-field-key.php';
require dirname( __DIR__ ) . '/includes/class-field-definition.php';
require dirname( __DIR__ ) . '/includes/class-condition.php';
require dirname( __DIR__ ) . '/includes/class-conditional-group.php';
require dirname( __DIR__ ) . '/includes/class-layout-block.php';
require dirname( __DIR__ ) . '/includes/class-condition-evaluator.php';
require dirname( __DIR__ ) . '/includes/class-form-definition.php';
require dirname( __DIR__ ) . '/includes/class-block-defaults.php';
require dirname( __DIR__ ) . '/includes/class-form-definition-parser.php';
require dirname( __DIR__ ) . '/includes/class-countries.php';
require dirname( __DIR__ ) . '/includes/class-text.php';
require dirname( __DIR__ ) . '/includes/class-phone-normalizer.php';
require dirname( __DIR__ ) . '/includes/class-field-validator.php';
require dirname( __DIR__ ) . '/includes/class-client-ip.php';
require dirname( __DIR__ ) . '/includes/class-rate-limiter.php';
require dirname( __DIR__ ) . '/includes/class-turnstile-verifier.php';
require dirname( __DIR__ ) . '/includes/class-turnstile-result.php';
require dirname( __DIR__ ) . '/includes/class-form-token.php';
require dirname( __DIR__ ) . '/includes/class-field-labels.php';
require dirname( __DIR__ ) . '/includes/class-form-style.php';
require dirname( __DIR__ ) . '/includes/class-form-renderer.php';
