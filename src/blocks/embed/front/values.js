// Reads Field values from controls the way the server cleans them (Field_Validator): trimmed, a Radio buttons
// Field as its checked value and Checkboxes as the list of checked values. The functions use `type`, `name`,
// `value`, `checked` and `required` only, so tests can pass plain objects instead of DOM controls.

// The sub-key of a compound Field's control (`key[sub]`), or '' for a single control.
export function subKeyOf( control, fieldKey ) {
	const prefix = fieldKey + '[';
	return control.name.startsWith( prefix ) && control.name.endsWith( ']' )
		? control.name.slice( prefix.length, -1 )
		: '';
}

/**
 * @param {Object[]} controls The controls of one Field.
 * @return {string} `radio` for Radio buttons, `checkbox` for Checkboxes (`name="key[]"`), else ''. A consent checkbox is a single control and has no kind.
 */
export function choiceKind( controls ) {
	const [ first ] = controls;
	if ( first?.type === 'radio' ) {
		return 'radio';
	}
	return first?.type === 'checkbox' && first.name.endsWith( '[]' )
		? 'checkbox'
		: '';
}

// The value of a single control; a lone checkbox (consent) is '1' when ticked.
export function controlValue( control ) {
	if ( control.type === 'checkbox' ) {
		return control.checked ? '1' : '';
	}
	return control.value.trim();
}

/**
 * @param {string}   fieldKey The Field key.
 * @param {Object[]} controls The controls of the Field, in document order.
 * @return {string|string[]|Object<string,string>} The cleaned value.
 */
export function cleanFieldValue( fieldKey, controls ) {
	switch ( choiceKind( controls ) ) {
		case 'radio':
			return controls.find( ( control ) => control.checked )?.value ?? '';
		case 'checkbox':
			return controls
				.filter( ( control ) => control.checked )
				.map( ( control ) => control.value );
		default:
	}

	const parts = {};
	for ( const control of controls ) {
		const subKey = subKeyOf( control, fieldKey );
		if ( subKey ) {
			parts[ subKey ] = controlValue( control );
		}
	}
	if ( Object.keys( parts ).length === 0 ) {
		return controls.length > 0 ? controlValue( controls[ 0 ] ) : '';
	}

	return parts;
}
