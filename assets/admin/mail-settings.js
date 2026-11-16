( function () {
	'use strict';
	const layout = document.getElementById( 'jpfb-mail_layout' );
	if ( layout && window.wp && wp.codeEditor ) {
		wp.codeEditor.initialize( layout, window.jpFormBuilderMailEditor || {} );
	}
	const frame = document.getElementById( 'jpfb-mail-preview' );
	const subject = document.getElementById( 'jpfb-notification_subject' );
	const body = document.getElementById( 'jpfb-notification_body' );
	const update = function () {
		if ( ! frame || ! subject || ! body || ! window.wpApiSettings ) return;
		fetch( wpApiSettings.root + 'jp-form-builder/v1/mail-preview', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpApiSettings.nonce },
			body: JSON.stringify( { kind: 'notification', subject: subject.value, body: body.value, layout: true, layout_html: layout ? layout.value : '' } ),
		} ).then( function ( response ) { return response.json(); } ).then( function ( result ) {
			if ( result.html ) frame.srcdoc = result.html;
		} );
	};
	[ subject, body, layout ].forEach( function ( field ) { if ( field ) field.addEventListener( 'input', update ); } );
	const reset = document.getElementById( 'jpfb-reset-mail-layout' );
	if ( reset && layout ) reset.addEventListener( 'click', function () { layout.value = ''; } );
}() );
