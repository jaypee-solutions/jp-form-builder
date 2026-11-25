import { editorCountryOptions } from './countries';
import { defaultLabel } from './labels';
import { normalizeFieldKey, uniqueFieldKey } from './field-key';

const BLOCK_NAMESPACE = 'jp-form-builder/';

export const CONDITIONAL_BLOCK = 'jp-form-builder/conditional';

const CHOICE_TYPES = [ 'select', 'radio', 'checkbox' ];

export const FIELD_TYPES = [
	'name',
	'email',
	'phone',
	'postcode',
	'country',
	'text',
	'select',
	'radio',
	'checkbox',
	'textarea',
	'consent',
];

// The Field type of a block name, or '' when the block is not a Field block.
function fieldTypeOf( name ) {
	const type = name.startsWith( BLOCK_NAMESPACE )
		? name.slice( BLOCK_NAMESPACE.length )
		: '';
	return FIELD_TYPES.includes( type ) ? type : '';
}

/**
 * Every Field block of a Form in document order, at any depth.
 *
 * @param {Object[]} blocks The Form block's inner blocks.
 * @return {Object[]} The Field blocks, including those inside Conditional groups and layout blocks.
 */
export function fieldBlocks( blocks ) {
	return [ ...walkBlocks( blocks ) ].filter( ( block ) =>
		fieldTypeOf( block.name )
	);
}

// Every Field block in document order, keyed like Form_Definition_Parser does. A Conditional group's own
// Fields follow the group; `stopAt` ends the walk at that group, leaving the Fields before it.
function keyedFieldBlocks( blocks, stopAt = '' ) {
	const countsByType = {};
	const takenKeys = [];
	const keyed = [];

	for ( const block of walkBlocks( blocks ) ) {
		if ( block.clientId === stopAt && block.name === CONDITIONAL_BLOCK ) {
			break;
		}
		const type = fieldTypeOf( block.name );
		if ( ! type ) {
			continue;
		}
		countsByType[ type ] = ( countsByType[ type ] ?? 0 ) + 1;
		const key = uniqueFieldKey(
			normalizeFieldKey( block.attributes.fieldName ?? '' ) ||
				`${ type }_${ countsByType[ type ] }`,
			takenKeys
		);
		takenKeys.push( key );
		keyed.push( {
			block,
			type,
			key,
			label:
				( block.attributes.label ?? '' ).trim() || defaultLabel( type ),
		} );
	}
	return keyed;
}

// Blocks in document order; a container comes before its children.
function* walkBlocks( blocks ) {
	for ( const block of blocks ) {
		yield block;
		yield* walkBlocks( block.innerBlocks ?? [] );
	}
}

/**
 * Lists a Form's Fields the way Form_Definition_Parser keys them: an empty key
 * becomes `<type>_<n>` (n counts per type) and a duplicate gets `_2`, `_3`, …
 *
 * @param {Object[]} blocks The Form block's inner blocks.
 * @return {{key: string, label: string, type: string, split: boolean}[]} The Fields in document order.
 */
export function collectFormFields( blocks ) {
	return keyedFieldBlocks( blocks ).map(
		( { block, type, key, label } ) => ( {
			key,
			label,
			type,
			split: type === 'name' && !! block.attributes.split,
		} )
	);
}

/**
 * The Fields a Conditional group's Condition may look at: those before the
 * group in the Form, including the ones inside earlier groups.
 *
 * @param {Object[]} blocks  The Form block's inner blocks.
 * @param {string}   groupId Client id of the Conditional group.
 * @return {{key: string, label: string, type: string, options: {label: string, value: string}[]}[]} The Fields in document order; `options` is empty for Fields without choices.
 */
export function collectConditionSources( blocks, groupId ) {
	return keyedFieldBlocks( blocks, groupId ).map(
		( { block, type, key, label } ) => ( {
			key,
			label,
			type,
			options: sourceOptions( block, type ),
		} )
	);
}

// The choices a Condition may compare a source Field with; empty for Fields without choices.
function sourceOptions( block, type ) {
	if ( type === 'country' ) {
		return editorCountryOptions();
	}
	return CHOICE_TYPES.includes( type )
		? ( block.attributes.options ?? [] ).filter(
				( option ) => ( option.value ?? '' ) !== ''
			)
		: [];
}
