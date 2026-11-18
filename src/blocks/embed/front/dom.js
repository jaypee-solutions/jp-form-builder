import { __ } from '@wordpress/i18n';
import { choiceKind, cleanFieldValue } from './values';

export const HONEYPOT_NAME = '_jpfb_field_914';

export function fieldWrappers( form ) {
	return [ ...form.querySelectorAll( '[data-field-key]' ) ];
}

export function fieldControls( wrapper ) {
	return [ ...wrapper.querySelectorAll( 'input, select, textarea' ) ];
}

// The visible label of a control without its required marker.
export function labelOf( form, control ) {
	// An option of Radio buttons or Checkboxes is labelled by its own text; the error is about the group.
	const label = choiceKind( [ control ] )
		? control.closest( 'fieldset' ).querySelector( 'legend' )
		: form.querySelector(
				`label[for="${ window.CSS.escape( control.id ) }"]`
			);
	if ( ! label ) {
		return __( 'This field', 'jp-form-builder' );
	}
	const clone = label.cloneNode( true );
	clone
		.querySelectorAll( '.jp-form-builder__required' )
		.forEach( ( marker ) => marker.remove() );
	return clone.textContent.trim();
}

// Fields inside a Conditional group that is currently hidden take no part in validation or the payload.
export function isInHiddenGroup( wrapper ) {
	return wrapper.closest( '.jp-form-builder__group[hidden]' ) !== null;
}

// The cleaned value of every Field, hidden groups included; Conditions are evaluated on these.
export function readValues( form ) {
	const values = {};
	for ( const wrapper of fieldWrappers( form ) ) {
		values[ wrapper.dataset.fieldKey ] = cleanFieldValue(
			wrapper.dataset.fieldKey,
			fieldControls( wrapper )
		);
	}
	return values;
}

// The Fields as the submissions route expects them: key to string, list or sub-key map, without hidden groups.
export function readFields( form ) {
	const fields = {};
	for ( const wrapper of fieldWrappers( form ) ) {
		if ( ! isInHiddenGroup( wrapper ) ) {
			fields[ wrapper.dataset.fieldKey ] = cleanFieldValue(
				wrapper.dataset.fieldKey,
				fieldControls( wrapper )
			);
		}
	}
	return fields;
}
