import { __ } from '@wordpress/i18n';

// Same msgids as Form_Renderer, so the editor preview and the front end share translations.

export function defaultLabel( type ) {
	switch ( type ) {
		case 'name':
			return __( 'Name', 'jp-form-builder' );
		case 'email':
			return __( 'Email', 'jp-form-builder' );
		case 'phone':
			return __( 'Phone', 'jp-form-builder' );
		case 'address':
			return __( 'Address', 'jp-form-builder' );
		case 'text':
			return __( 'Text', 'jp-form-builder' );
		case 'select':
			return __( 'Select', 'jp-form-builder' );
		case 'textarea':
			return __( 'Message', 'jp-form-builder' );
		case 'consent':
			return __( 'Consent', 'jp-form-builder' );
		default:
			return '';
	}
}

export function defaultFirstNameLabel() {
	return __( 'First name', 'jp-form-builder' );
}

export function defaultLastNameLabel() {
	return __( 'Last name', 'jp-form-builder' );
}

export function addressPartLabels() {
	return {
		street: __( 'Street and number', 'jp-form-builder' ),
		postcode: __( 'Postcode', 'jp-form-builder' ),
		city: __( 'City', 'jp-form-builder' ),
		country: __( 'Country', 'jp-form-builder' ),
	};
}

export function defaultConsentText() {
	return __( 'I agree to the {privacy_policy}.', 'jp-form-builder' );
}

export function defaultConsentLinkText() {
	return __( 'privacy policy', 'jp-form-builder' );
}

export function defaultSubmitLabel() {
	return __( 'Submit', 'jp-form-builder' );
}
