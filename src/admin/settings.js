import { createRoot } from '@wordpress/element';
import { MailSettings } from './mail-settings';

const SETTING_KEYS = [
	'mail_layout',
	'notification_subject',
	'notification_body',
	'confirmation_subject',
	'confirmation_body',
];

/**
 * Takes over the mail section of the settings page. PHP renders plain fields
 * there, which work without this script and hold the saved values.
 */
function init() {
	const container = document.getElementById( 'jpfb-mail-settings' );
	if ( ! container ) {
		return;
	}
	const initial = {};
	for ( const key of SETTING_KEYS ) {
		initial[ key ] =
			container.querySelector(
				`[name="jp_form_builder_settings[${ key }]"]`
			)?.value ?? '';
	}
	createRoot( container ).render( <MailSettings initial={ initial } /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
