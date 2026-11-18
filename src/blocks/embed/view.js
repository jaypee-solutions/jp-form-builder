import { __ } from '@wordpress/i18n';
import { HONEYPOT_NAME, readFields } from './front/dom';
import {
	clearErrors,
	issuesFromServer,
	showIssues,
	showMessage,
} from './front/errors';
import { initGroups } from './front/groups';
import { createTurnstileWidget } from './front/turnstile';
import { validateForm } from './front/validation';

const noop = () => {};

// An error whose message is meant for the visitor.
function visitorError( message ) {
	return Object.assign( new Error( message ), { visitorMessage: message } );
}

const delay = ( milliseconds ) =>
	new Promise( ( resolve ) => setTimeout( resolve, milliseconds ) );

function initForm( form ) {
	const { formId, restRoot, rev, locale, turnstileSitekey } = form.dataset;
	const minFillMilliseconds = Number( form.dataset.minFill || 0 ) * 1000;
	const submitButton = form.querySelector( 'button[type="submit"]' );
	const status = form.querySelector( '.jp-form-builder__status' );
	const turnstileContainer = form.querySelector(
		'.jp-form-builder__turnstile'
	);

	let activated = false;
	let sending = false;
	let token = null;
	let tokenRequest = null;
	let widget = null;

	// aria-disabled instead of disabled keeps the button focusable.
	submitButton.removeAttribute( 'disabled' );

	const isReady = () =>
		token !== null &&
		Date.now() >= token.readyAt &&
		( widget === null || widget.hasAnswer() );

	function refreshSubmitButton() {
		if ( sending || ! isReady() ) {
			submitButton.setAttribute( 'aria-disabled', 'true' );
		} else {
			submitButton.removeAttribute( 'aria-disabled' );
		}
	}

	function loadToken() {
		if ( ! tokenRequest ) {
			tokenRequest = fetch( `${ restRoot }forms/${ formId }/token`, {
				cache: 'no-store',
				credentials: 'omit',
			} )
				.then( ( response ) => {
					if ( ! response.ok ) {
						throw new Error( 'The token request failed.' );
					}
					return response.json();
				} )
				.then( ( body ) => {
					token = {
						value: body.token,
						readyAt: Date.now() + minFillMilliseconds,
					};
					setTimeout( refreshSubmitButton, minFillMilliseconds );
					refreshSubmitButton();
					return token;
				} )
				.catch( ( error ) => {
					tokenRequest = null;
					throw error;
				} );
		}
		return tokenRequest;
	}

	function discardToken() {
		token = null;
		tokenRequest = null;
		refreshSubmitButton();
	}

	function startTurnstile() {
		if ( turnstileSitekey && turnstileContainer ) {
			widget = createTurnstileWidget(
				turnstileContainer,
				turnstileSitekey,
				refreshSubmitButton
			);
		}
	}

	// ADR 0002: nothing is requested before the visitor interacts with the Form.
	function activate() {
		if ( activated ) {
			return;
		}
		activated = true;
		loadToken().catch( noop );
		startTurnstile();
	}

	async function collectProofs() {
		if ( widget?.failed() ) {
			startTurnstile();
		}
		if ( ! isReady() ) {
			status.textContent = __( 'Please wait…', 'jp-form-builder' );
		}

		let currentToken;
		try {
			currentToken = await loadToken();
		} catch {
			throw visitorError( networkMessage() );
		}
		await delay( currentToken.readyAt - Date.now() );

		let turnstileResponse = '';
		if ( widget ) {
			try {
				turnstileResponse = await widget.answer();
			} catch {
				throw visitorError(
					__(
						'The spam protection check could not be loaded. Please reload the page and try again.',
						'jp-form-builder'
					)
				);
			}
		}
		return { token: currentToken.value, turnstile: turnstileResponse };
	}

	async function send() {
		const proofs = await collectProofs();
		const payload = {
			...proofs,
			hp: form.querySelector( `input[name="${ HONEYPOT_NAME }"]` ).value,
			page_url: window.location.href,
			locale,
			rev,
			fields: readFields( form ),
		};

		let response;
		try {
			response = await fetch(
				`${ restRoot }forms/${ formId }/submissions`,
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'omit',
					body: JSON.stringify( payload ),
				}
			);
		} catch {
			throw visitorError( networkMessage() );
		}
		const body = await response.json().catch( () => ( {} ) );
		status.textContent = '';
		return { ok: response.ok, body };
	}

	function networkMessage() {
		return __(
			'Your message could not be sent. Please check your connection and try again.',
			'jp-form-builder'
		);
	}

	function showSuccess( message ) {
		form.classList.add( 'is-submitted' );
		status.textContent = message;
		status.setAttribute( 'tabindex', '-1' );
		status.focus();
		widget?.remove();
	}

	function showFailure( body ) {
		const errors = body.data?.errors;
		if ( body.code === 'jpfb_invalid_fields' && errors ) {
			showIssues( form, issuesFromServer( form, errors ) );
		} else if ( body.code === 'jpfb_token_invalid' ) {
			discardToken();
			loadToken().catch( noop );
			showMessage(
				form,
				__(
					'Your session could not be verified. Please submit the form again.',
					'jp-form-builder'
				)
			);
		} else {
			showMessage( form, body.message || networkMessage() );
		}
	}

	async function submit( event ) {
		event.preventDefault();
		if ( sending ) {
			return;
		}
		activate();
		clearErrors( form );
		groups.refresh();

		const issues = validateForm( form );
		if ( issues.length > 0 ) {
			showIssues( form, issues );
			return;
		}

		sending = true;
		form.setAttribute( 'aria-busy', 'true' );
		refreshSubmitButton();
		let succeeded = false;
		try {
			const { ok, body } = await send();
			if ( ok ) {
				succeeded = true;
				showSuccess( body.message );
			} else {
				showFailure( body );
			}
		} catch ( error ) {
			status.textContent = '';
			showMessage( form, error.visitorMessage ?? networkMessage() );
		} finally {
			sending = false;
			form.removeAttribute( 'aria-busy' );
			if ( ! succeeded ) {
				widget?.reset();
			}
			refreshSubmitButton();
		}
	}

	submitButton.setAttribute( 'aria-disabled', 'true' );
	const groups = initGroups( form );
	form.addEventListener( 'focusin', activate, { once: true } );
	form.addEventListener( 'submit', submit );
}

function init() {
	document.querySelectorAll( 'form[data-form-id]' ).forEach( initForm );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
