const SCRIPT_URL =
	'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let scriptRequest = null;

// Loads Cloudflare's script once per page.
function loadScript() {
	if ( ! scriptRequest ) {
		scriptRequest = new Promise( ( resolve, reject ) => {
			const script = document.createElement( 'script' );
			script.src = SCRIPT_URL;
			script.async = true;
			script.onload = () => resolve( window.turnstile );
			script.onerror = () => {
				script.remove();
				scriptRequest = null;
				reject( new Error( 'Turnstile could not be loaded.' ) );
			};
			document.head.append( script );
		} );
	}
	return scriptRequest;
}

function createDeferred() {
	const deferred = {};
	deferred.promise = new Promise( ( resolve, reject ) => {
		deferred.resolve = resolve;
		deferred.reject = reject;
	} );
	// The caller may never await an answer; a rejection alone is not an error.
	deferred.promise.catch( () => {} );
	return deferred;
}

// Renders a Turnstile widget that only shows when the visitor must interact.
// `onChange` is called whenever a response arrives or is invalidated.
export function createTurnstileWidget( container, siteKey, onChange ) {
	let api = null;
	let widgetId = null;
	let failed = false;
	let hasAnswer = false;
	let deferred = createDeferred();

	const renew = () => {
		hasAnswer = false;
		deferred = createDeferred();
		onChange();
	};

	loadScript()
		.then( ( turnstile ) => {
			api = turnstile;
			widgetId = turnstile.render( container, {
				sitekey: siteKey,
				action: 'jpfb-submit',
				appearance: 'interaction-only',
				callback: ( response ) => {
					hasAnswer = true;
					deferred.resolve( response );
					onChange();
				},
				'expired-callback': renew,
				'error-callback': () => {
					deferred.reject( new Error( 'Turnstile failed.' ) );
					renew();
				},
			} );
		} )
		.catch( ( error ) => {
			failed = true;
			deferred.reject( error );
		} );

	return {
		answer: () => deferred.promise,
		hasAnswer: () => hasAnswer,
		failed: () => failed,
		reset: () => {
			if ( api ) {
				api.reset( widgetId );
			}
			renew();
		},
		remove: () => {
			if ( api ) {
				api.remove( widgetId );
			}
		},
	};
}
