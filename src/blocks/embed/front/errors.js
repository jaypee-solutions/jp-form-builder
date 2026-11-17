import { __ } from '@wordpress/i18n';
import { fieldControls, fieldWrappers, subKeyOf } from './dom';

let summaryCount = 0;

const errorContainerOf = ( control ) =>
	control
		.closest( '[data-field-key]' )
		.querySelector( '.jp-form-builder__error' );

export function clearErrors( form ) {
	form.querySelectorAll( '[aria-invalid]' ).forEach( ( control ) =>
		control.removeAttribute( 'aria-invalid' )
	);
	form.querySelectorAll( '.jp-form-builder__error' ).forEach( ( container ) =>
		container.replaceChildren()
	);
	form.querySelector( '.jp-form-builder__error-summary' ).replaceChildren();
	form.querySelector( '.jp-form-builder__status' ).textContent = '';
}

function focusSummary( form ) {
	const summary = form.querySelector( '.jp-form-builder__error-summary' );
	summary.focus();
	return summary;
}

// Marks the invalid controls, fills their error containers and focuses an
// error summary that links to them. Issues without a control are listed in the
// summary only.
export function showIssues( form, issues ) {
	const summary = form.querySelector( '.jp-form-builder__error-summary' );
	const heading = document.createElement( 'h2' );
	heading.id = `jpfb-summary-${ ++summaryCount }`;
	heading.textContent = __(
		'Please correct the marked fields.',
		'jp-form-builder'
	);
	const list = document.createElement( 'ul' );

	for ( const { control, message } of issues ) {
		const item = document.createElement( 'li' );
		if ( control ) {
			control.setAttribute( 'aria-invalid', 'true' );
			const text = document.createElement( 'p' );
			text.textContent = message;
			errorContainerOf( control ).append( text );

			const link = document.createElement( 'a' );
			link.href = `#${ control.id }`;
			link.textContent = message;
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				control.focus();
			} );
			item.append( link );
		} else {
			item.textContent = message;
		}
		list.append( item );
	}

	summary.setAttribute( 'aria-labelledby', heading.id );
	summary.replaceChildren( heading, list );
	focusSummary( form );
}

// Shows a message that is not about one Field in the focused error summary.
export function showMessage( form, message ) {
	const summary = form.querySelector( '.jp-form-builder__error-summary' );
	const text = document.createElement( 'p' );
	text.textContent = message;
	summary.removeAttribute( 'aria-labelledby' );
	summary.replaceChildren( text );
	focusSummary( form );
}

// Maps the `data.errors` of a 422 answer (keyed `key` or `key.sub`) onto the
// Form's controls, in document order; unknown keys come last without a control.
export function issuesFromServer( form, errors ) {
	const found = new Map();
	const unmatched = [];
	for ( const [ errorKey, message ] of Object.entries( errors ) ) {
		const [ key, subKey = '' ] = errorKey.split( '.' );
		const wrapper = fieldWrappers( form ).find(
			( candidate ) => candidate.dataset.fieldKey === key
		);
		const controls = wrapper ? fieldControls( wrapper ) : [];
		const control = controls.find(
			( candidate ) => subKeyOf( candidate, key ) === subKey
		);
		if ( control ) {
			found.set( control, message );
		} else {
			unmatched.push( { control: null, message } );
		}
	}

	const ordered = [];
	for ( const wrapper of fieldWrappers( form ) ) {
		for ( const control of fieldControls( wrapper ) ) {
			if ( found.has( control ) ) {
				ordered.push( { control, message: found.get( control ) } );
			}
		}
	}
	return [ ...ordered, ...unmatched ];
}
