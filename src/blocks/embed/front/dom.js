import { __ } from '@wordpress/i18n';

export const HONEYPOT_NAME = '_jpfb_field_914';

export function fieldWrappers( form ) {
	return [ ...form.querySelectorAll( '[data-field-key]' ) ];
}

export function fieldControls( wrapper ) {
	return [ ...wrapper.querySelectorAll( 'input, select, textarea' ) ];
}

// The sub-key of a compound Field's control (`key[sub]`), or '' for a single control.
export function subKeyOf( control, fieldKey ) {
	const prefix = fieldKey + '[';
	return control.name.startsWith( prefix ) && control.name.endsWith( ']' )
		? control.name.slice( prefix.length, -1 )
		: '';
}

// The visible label of a control without its required marker.
export function labelOf( form, control ) {
	const label = form.querySelector(
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

export function controlValue( control ) {
	if ( control.type === 'checkbox' ) {
		return control.checked ? '1' : '';
	}
	return control.value;
}

// The Fields as the submissions route expects them: key to string, or to a sub-key map.
export function readFields( form ) {
	const fields = {};
	for ( const wrapper of fieldWrappers( form ) ) {
		const key = wrapper.dataset.fieldKey;
		for ( const control of fieldControls( wrapper ) ) {
			const subKey = subKeyOf( control, key );
			if ( subKey ) {
				fields[ key ] = fields[ key ] ?? {};
				fields[ key ][ subKey ] = controlValue( control );
			} else {
				fields[ key ] = controlValue( control );
			}
		}
	}
	return fields;
}
