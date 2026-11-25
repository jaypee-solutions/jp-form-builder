<?php
/**
 * Plugin Name:       JayPee Solutions Form Builder
 * Plugin URI:        https://github.com/jaypee-solutions/jp-form-builder
 * Description:       JayPee Solutions Form Builder is a WordPress plugin to create basic forms like a contact form.
 * Version:           2.0.0
 * Author:            Jan-Peter Alten
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jp-form-builder
 * Domain Path:       /languages
 * Requires at least: 6.6
 * Requires PHP:      8.0
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

const VERSION     = '2.0.0';
const PLUGIN_FILE = __FILE__;
const GITHUB_REPO = 'jaypee-solutions/jp-form-builder';
const UPDATE_URL  = 'https://github.com/jaypee-solutions/jp-form-builder/';

// Bundled by scripts/build-release.sh; present in dev after `composer install`.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/class-secret-box.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-form-posts-controller.php';
require_once __DIR__ . '/includes/class-form-post-type.php';
require_once __DIR__ . '/includes/class-form-backup.php';
require_once __DIR__ . '/includes/class-settings-page.php';
require_once __DIR__ . '/includes/class-mail.php';
require_once __DIR__ . '/includes/class-submitted-field.php';
require_once __DIR__ . '/includes/class-mail-header-guard.php';
require_once __DIR__ . '/includes/class-recipient-list.php';
require_once __DIR__ . '/includes/class-mail-remote-assets.php';
require_once __DIR__ . '/includes/class-mail-template.php';
require_once __DIR__ . '/includes/class-mail-layout.php';
require_once __DIR__ . '/includes/class-mailer.php';
require_once __DIR__ . '/includes/class-mail-log.php';
require_once __DIR__ . '/includes/class-mail-preview-controller.php';
require_once __DIR__ . '/includes/class-cli-command.php';
require_once __DIR__ . '/includes/class-mail-builder.php';
require_once __DIR__ . '/includes/class-form-blocks.php';
require_once __DIR__ . '/includes/class-field-key.php';
require_once __DIR__ . '/includes/class-field-definition.php';
require_once __DIR__ . '/includes/class-condition.php';
require_once __DIR__ . '/includes/class-conditional-group.php';
require_once __DIR__ . '/includes/class-layout-block.php';
require_once __DIR__ . '/includes/class-condition-evaluator.php';
require_once __DIR__ . '/includes/class-form-definition.php';
require_once __DIR__ . '/includes/class-block-defaults.php';
require_once __DIR__ . '/includes/class-form-definition-parser.php';
require_once __DIR__ . '/includes/class-form-repository.php';
require_once __DIR__ . '/includes/class-countries.php';
require_once __DIR__ . '/includes/class-postcode-country.php';
require_once __DIR__ . '/includes/class-text.php';
require_once __DIR__ . '/includes/class-phone-normalizer.php';
require_once __DIR__ . '/includes/class-field-validator.php';
require_once __DIR__ . '/includes/class-client-ip.php';
require_once __DIR__ . '/includes/class-rate-limiter.php';
require_once __DIR__ . '/includes/class-turnstile-verifier.php';
require_once __DIR__ . '/includes/class-turnstile-result.php';
require_once __DIR__ . '/includes/class-form-token.php';
require_once __DIR__ . '/includes/class-field-labels.php';
require_once __DIR__ . '/includes/class-form-style.php';
require_once __DIR__ . '/includes/class-form-renderer.php';
require_once __DIR__ . '/includes/class-submission-handler.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-privacy.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-updates.php';

Updates::init();

/**
 * Bootstrap.
 */
add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain(
            'jp-form-builder',
            false,
            dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages'
        );

        Plugin::init();
    }
);
