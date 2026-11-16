import { defaultLabel } from './labels';
import { normalizeFieldKey, uniqueFieldKey } from './field-key';

const BLOCK_NAMESPACE = 'jp-form-builder/';

export const FIELD_TYPES = [
	'name',
	'email',
	'phone',
	'address',
	'text',
	'select',
	'textarea',
	'consent',
];

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

	for ( const block of blocks ) {
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
