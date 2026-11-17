import { __, sprintf } from '@wordpress/i18n';
import { controlValue, fieldControls, fieldWrappers, labelOf } from './dom';
import { isPlausiblePhoneNumber } from './phone';

const codePoints = ( text ) => [ ...text ].length;

function requiredMessage( label ) {
	/* translators: %s: field label. */
	return sprintf( __( '%s is required.', 'jp-form-builder' ), label );
}

function tooLongMessage( label, max ) {
	return sprintf(
		/* translators: 1: field label, 2: maximum number of characters. */
		__(
			'%1$s must not be longer than %2$d characters.',
			'jp-form-builder'
		),
		label,
		max
	);
}

// The message for one control's value, or '' when it is acceptable. Empty
// values are the caller's business.
function invalidValueMessage( form, control, value, postcodePatterns ) {
	if ( control.type === 'email' && control.validity.typeMismatch ) {
		return __( 'Please enter a valid email address.', 'jp-form-builder' );
	}
	if ( control.maxLength > 0 && codePoints( value ) > control.maxLength ) {
		return tooLongMessage( labelOf( form, control ), control.maxLength );
	}
	if (
		control.type === 'tel' &&
		! isPlausiblePhoneNumber( value, control.dataset.defaultRegion || 'DE' )
	) {
		return __( 'Please enter a valid phone number.', 'jp-form-builder' );
	}
	if ( control.tagName === 'SELECT' ) {
		const isOption = [ ...control.options ].some(
			( option ) => option.value === control.value
		);
		return isOption
			? ''
			: __(
					'Please choose one of the available options.',
					'jp-form-builder'
				);
	}
	if ( postcodePatterns && control.name.endsWith( '[postcode]' ) ) {
		const country = control
			.closest( 'fieldset' )
			.querySelector( 'select' )?.value;
		const pattern = postcodePatterns[ country ];
		if ( pattern && ! new RegExp( pattern ).test( value ) ) {
			return __( 'Please enter a valid postcode.', 'jp-form-builder' );
		}
	}
	return '';
}

function emptyMessage( form, control ) {
	if ( control.type === 'checkbox' ) {
		return __( 'Please accept to continue.', 'jp-form-builder' );
	}
	return requiredMessage( labelOf( form, control ) );
}

function validateSingle( form, control ) {
	const value = controlValue( control ).trim();
	if ( value === '' ) {
		return control.required
			? [ { control, message: emptyMessage( form, control ) } ]
			: [];
	}
	const message = invalidValueMessage( form, control, value, null );
	return message ? [ { control, message } ] : [];
}

// A compound Field is empty only when every part is; the country select is
// preselected, so it never makes an address "filled" (mirrors Field_Validator).
function validateCompound( form, fieldset, controls ) {
	const postcodePatterns = fieldset.dataset.postcodePatterns
		? JSON.parse( fieldset.dataset.postcodePatterns )
		: null;
	const isEmpty = controls.every(
		( control ) =>
			control.tagName === 'SELECT' || control.value.trim() === ''
	);
	if ( isEmpty && ! controls.some( ( control ) => control.required ) ) {
		return [];
	}

	const issues = [];
	for ( const control of controls ) {
		const value = control.value.trim();
		const message =
			value === ''
				? emptyMessage( form, control )
				: invalidValueMessage( form, control, value, postcodePatterns );
		if ( message ) {
			issues.push( { control, message } );
		}
	}
	return issues;
}

// Checks the Form with the rules its markup carries: one issue per invalid
// control, in document order.
export function validateForm( form ) {
	const issues = [];
	for ( const wrapper of fieldWrappers( form ) ) {
		const controls = fieldControls( wrapper );
		const fieldset = wrapper.querySelector( 'fieldset' );
		issues.push(
			...( fieldset
				? validateCompound( form, fieldset, controls )
				: validateSingle( form, controls[ 0 ] ) )
		);
	}
	return issues;
}
