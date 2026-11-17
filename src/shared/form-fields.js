import { defaultLabel } from './labels';
import { normalizeFieldKey, uniqueFieldKey } from './field-key';

const BLOCK_NAMESPACE = 'jp-form-builder/';

export const CONDITIONAL_BLOCK = 'jp-form-builder/conditional';

export const FIELD_TYPES = [
	'name',
	'email',
	'phone',
	'address',
	'text',
	'select',
	'radio',
	'checkbox',
	'textarea',
	'consent',
];

/**
 * The blocks of a Form in document order with Conditional groups replaced by their children.
 *
 * @param {Object[]} blocks The Form block's inner blocks.
 * @return {Object[]} Field and pass-through blocks; groups are flattened.
 */
export function flattenFieldBlocks( blocks ) {
	return blocks.flatMap( ( block ) =>
		block.name === CONDITIONAL_BLOCK
			? flattenFieldBlocks( block.innerBlocks )
			: [ block ]
	);
}

/**
 * Lists a Form's Fields the way Form_Definition_Parser keys them: an empty key
 * becomes `<type>_<n>` (n counts per type) and a duplicate gets `_2`, `_3`, …
 *
 * @param {Object[]} blocks The Form block's inner blocks.
 * @return {{key: string, label: string, type: string, split: boolean}[]} The Fields in document order.
 */
export function collectFormFields( blocks ) {
	const countsByType = {};
	const takenKeys = [];
	const fields = [];

	for ( const block of flattenFieldBlocks( blocks ) ) {
		const type = block.name.startsWith( BLOCK_NAMESPACE )
			? block.name.slice( BLOCK_NAMESPACE.length )
			: '';
		if ( ! FIELD_TYPES.includes( type ) ) {
			continue;
		}
		countsByType[ type ] = ( countsByType[ type ] ?? 0 ) + 1;
		const key = uniqueFieldKey(
			normalizeFieldKey( block.attributes.fieldName ?? '' ) ||
				`${ type }_${ countsByType[ type ] }`,
			takenKeys
		);
		takenKeys.push( key );
		fields.push( {
			key,
			label:
				( block.attributes.label ?? '' ).trim() || defaultLabel( type ),
			type,
			split: type === 'name' && !! block.attributes.split,
		} );
	}
	return fields;
}
