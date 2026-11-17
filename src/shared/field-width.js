import { __ } from '@wordpress/i18n';

/** Widths in percent; the first is the default. Keep in step with Form_Style::width_class(). */
export const FIELD_WIDTHS = [ '100', '50', '33' ];

export function fieldWidthOptions() {
	return [
		{ value: '100', label: __( 'Full width', 'jp-form-builder' ) },
		{ value: '50', label: __( 'Half width', 'jp-form-builder' ) },
		{ value: '33', label: __( 'One third', 'jp-form-builder' ) },
	];
}

/**
 * The class Form_Renderer gives a narrower Field. A consent Field is always full width.
 *
 * @param {string} type  Field type, e.g. `email`.
 * @param {string} width The width attribute.
 * @return {string} The class, or an empty string for a full-width Field.
 */
export function fieldWidthClassName( type, width ) {
	if ( type === 'consent' || width === FIELD_WIDTHS[ 0 ] ) {
		return '';
	}
	return FIELD_WIDTHS.includes( width )
		? `jp-form-builder__field--width-${ width }`
		: '';
}
