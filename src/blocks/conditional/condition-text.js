import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * @param {string} operator One of the Condition operators.
 * @return {string} The operator as the Operator select shows it.
 */
export function operatorLabel( operator ) {
	switch ( operator ) {
		case 'is':
			return _x( 'is', 'Condition operator', 'jp-form-builder' );
		case 'is_not':
			return _x( 'is not', 'Condition operator', 'jp-form-builder' );
		case 'is_one_of':
			return _x( 'is one of', 'Condition operator', 'jp-form-builder' );
		case 'includes':
			return _x( 'includes', 'Condition operator', 'jp-form-builder' );
		case 'is_checked':
			return _x( 'is ticked', 'Condition operator', 'jp-form-builder' );
		case 'is_not_checked':
			return _x(
				'is not ticked',
				'Condition operator',
				'jp-form-builder'
			);
		case 'is_filled':
			return _x( 'is filled', 'Condition operator', 'jp-form-builder' );
		case 'is_empty':
			return _x( 'is empty', 'Condition operator', 'jp-form-builder' );
		default:
			return operator;
	}
}

/**
 * @param {Object[]} options The source's `{ label, value }` options.
 * @param {string[]} values  The chosen values.
 * @return {string} The chosen options by label, comma separated.
 */
function valueLabels( options, values ) {
	return values
		.map(
			( value ) =>
				options
					.find( ( option ) => option.value === value )
					?.label?.trim() || value
		)
		.join( ', ' );
}

/**
 * The Condition in words, e.g. "Shown when Package is Gold".
 *
 * @param {{operator: string, values: string[]}} condition The group's Condition.
 * @param {{label: string, options: Object[]}}   source    The Field it looks at.
 * @return {string} The sentence.
 */
export function describeCondition( condition, source ) {
	const values = valueLabels( source.options, condition.values );
	switch ( condition.operator ) {
		case 'is':
			return sprintf(
				/* translators: 1: Field label, 2: option label. */
				__( 'Shown when %1$s is %2$s', 'jp-form-builder' ),
				source.label,
				values
			);
		case 'is_not':
			return sprintf(
				/* translators: 1: Field label, 2: option label. */
				__( 'Shown when %1$s is not %2$s', 'jp-form-builder' ),
				source.label,
				values
			);
		case 'is_one_of':
			return sprintf(
				/* translators: 1: Field label, 2: comma-separated option labels. */
				__( 'Shown when %1$s is one of %2$s', 'jp-form-builder' ),
				source.label,
				values
			);
		case 'includes':
			return sprintf(
				/* translators: 1: Field label, 2: comma-separated option labels. */
				__( 'Shown when %1$s includes %2$s', 'jp-form-builder' ),
				source.label,
				values
			);
		case 'is_checked':
			return sprintf(
				/* translators: %s: Field label. */
				__( 'Shown when %s is ticked', 'jp-form-builder' ),
				source.label
			);
		case 'is_not_checked':
			return sprintf(
				/* translators: %s: Field label. */
				__( 'Shown when %s is not ticked', 'jp-form-builder' ),
				source.label
			);
		case 'is_filled':
			return sprintf(
				/* translators: %s: Field label. */
				__( 'Shown when %s is filled', 'jp-form-builder' ),
				source.label
			);
		default:
			return sprintf(
				/* translators: %s: Field label. */
				__( 'Shown when %s is empty', 'jp-form-builder' ),
				source.label
			);
	}
}
