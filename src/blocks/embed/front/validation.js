import { __, sprintf } from '@wordpress/i18n';
import { dateIssueMessage } from './date';
import {
	countryValueOf,
	fieldControls,
	fieldWrappers,
	isInHiddenGroup,
	labelOf,
} from './dom';
import { isPlausiblePhoneNumber } from './phone';
import { isValidPostcode } from './postcode';
import { choiceKind, controlValue } from './values';

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
function invalidValueMessage( form, control, value ) {
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
	if (
		! isValidPostcode( control, value, ( key ) =>
			countryValueOf( form, key )
		)
	) {
		return __( 'Please enter a valid postcode.', 'jp-form-builder' );
	}
	if ( control.dataset.dateFormat ) {
		return dateIssueMessage( control );
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
	return '';
}

function emptyMessage( form, control ) {
	if ( control.type === 'checkbox' ) {
		return __( 'Please accept to continue.', 'jp-form-builder' );
	}
	return requiredMessage( labelOf( form, control ) );
}

function validateSingle( form, control ) {
	const value = controlValue( control );
	if ( value === '' ) {
		return control.required
			? [ { control, message: emptyMessage( form, control ) } ]
			: [];
	}
	const message = invalidValueMessage( form, control, value, null );
	return message ? [ { control, message } ] : [];
}

// Radio buttons are required with one checked option, Checkboxes with `data-required` on the group. The issue is
// attached to the first option, which the error summary links to.
function validateChoices( form, controls, kind ) {
	const [ first ] = controls;
	const required =
		kind === 'radio'
			? controls.some( ( control ) => control.required )
			: first.closest( 'fieldset' ).dataset.required === 'true';
	if ( ! required || controls.some( ( control ) => control.checked ) ) {
		return [];
	}
	return [
		{ control: first, message: requiredMessage( labelOf( form, first ) ) },
	];
}

// A split Name is empty only when both parts are; otherwise each empty part is missing (mirrors Field_Validator).
function validateCompound( form, controls ) {
	const isEmpty = controls.every(
		( control ) => control.value.trim() === ''
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
				: invalidValueMessage( form, control, value );
		if ( message ) {
			issues.push( { control, message } );
		}
	}
	return issues;
}

// Checks the Form with the rules its markup carries: one issue per invalid
// control, in document order. Fields of a hidden Conditional group are skipped.
export function validateForm( form ) {
	const issues = [];
	for ( const wrapper of fieldWrappers( form ) ) {
		if ( isInHiddenGroup( wrapper ) ) {
			continue;
		}
		const controls = fieldControls( wrapper );
		const fieldset = wrapper.querySelector( 'fieldset' );
		const kind = choiceKind( controls );
		if ( kind ) {
			issues.push( ...validateChoices( form, controls, kind ) );
		} else if ( fieldset ) {
			issues.push( ...validateCompound( form, controls ) );
		} else {
			issues.push( ...validateSingle( form, controls[ 0 ] ) );
		}
	}
	return issues;
}
