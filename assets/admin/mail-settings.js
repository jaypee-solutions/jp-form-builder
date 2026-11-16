( function () {
	'use strict';

	const layoutField = document.getElementById( 'jpfb-mail_layout' );
	const subjectField = document.getElementById( 'jpfb-notification_subject' );
	const bodyField = document.getElementById( 'jpfb-notification_body' );
	const frame = document.getElementById( 'jpfb-mail-preview' );
	const resetButton = document.getElementById( 'jpfb-reset-mail-layout' );

	const editor =
		layoutField && window.wp && wp.codeEditor
			? wp.codeEditor.initialize(
					layoutField,
					window.jpFormBuilderMailEditor || {}
				).codemirror
			: null;

	const layoutValue = function () {
		return editor ? editor.getValue() : layoutField.value;
	};

	let timer = null;
	const renderPreview = function () {
		if ( ! frame || ! layoutField || ! window.wp || ! wp.apiFetch ) {
			return;
		}
		wp.apiFetch( {
			path: '/jp-form-builder/v1/mail-preview',
			method: 'POST',
			data: {
				kind: 'notification',
				subject: subjectField ? subjectField.value : '',
				body: bodyField ? bodyField.value : '',
				layout: layoutValue(),
			},
		} )
			.then( function ( result ) {
				frame.srcdoc = result.html;
			} )
			.catch( function ( error ) {
				frame.srcdoc = '<p>' + ( error.message || '' ) + '</p>';
			} );
	};
	const schedulePreview = function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( renderPreview, 400 );
	};

	if ( editor ) {
		editor.on( 'change', schedulePreview );
	}
	[ subjectField, bodyField ].forEach( function ( field ) {
		if ( field ) {
			field.addEventListener( 'input', schedulePreview );
		}
	} );
	if ( resetButton && layoutField ) {
		// An empty layout is saved as "use the bundled default".
		resetButton.addEventListener( 'click', function () {
			if ( editor ) {
				editor.setValue( '' );
			} else {
				layoutField.value = '';
			}
			schedulePreview();
		} );
	}
	renderPreview();
} )();
