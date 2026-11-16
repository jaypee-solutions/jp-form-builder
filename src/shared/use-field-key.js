import { store as blockEditorStore } from '@wordpress/block-editor';
import { useRegistry, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { newFieldKey, normalizeFieldKey } from './field-key';

function siblingFieldKeys( select, clientId ) {
	const { getBlockRootClientId, getBlocks } = select( blockEditorStore );
	return getBlocks( getBlockRootClientId( clientId ) )
		.filter( ( block ) => block.name.startsWith( 'jp-form-builder/' ) )
		.map( ( block ) => ( {
			clientId: block.clientId,
			key: normalizeFieldKey( block.attributes.fieldName ?? '' ),
		} ) );
}

/**
 * Gives a Field without a Field key a unique one when it is inserted.
 *
 * Reads the store at effect time, so Fields inserted together (a pasted
 * pattern) see each other's keys instead of a stale snapshot.
 *
 * @param {Object}                    props               Field block props.
 * @param {string}                    props.type          Field type, e.g. `email`.
 * @param {string}                    props.clientId      Block client id.
 * @param {Object}                    props.attributes    Block attributes.
 * @param {(changes: Object) => void} props.setAttributes Block attribute setter.
 */
export function useFieldKey( { type, clientId, attributes, setAttributes } ) {
	const registry = useRegistry();
	const { fieldName, label } = attributes;

	useEffect( () => {
		if ( fieldName ) {
			return;
		}
		const taken = siblingFieldKeys( registry.select, clientId )
			.filter( ( sibling ) => sibling.clientId !== clientId )
			.map( ( sibling ) => sibling.key )
			.filter( Boolean );
		setAttributes( { fieldName: newFieldKey( type, label, taken ) } );
	}, [ fieldName, label, type, clientId, registry, setAttributes ] );
}

/**
 * @param {string} clientId  Block client id.
 * @param {string} fieldName The Field's current key.
 * @return {boolean} Whether an earlier Field of the Form uses the same key, so the parser would rename this one.
 */
export function useDuplicateFieldKey( clientId, fieldName ) {
	return useSelect(
		( select ) => {
			const key = normalizeFieldKey( fieldName ?? '' );
			if ( ! key ) {
				return false;
			}
			const siblings = siblingFieldKeys( select, clientId );
			const position = siblings.findIndex(
				( sibling ) => sibling.clientId === clientId
			);
			return siblings
				.slice( 0, Math.max( position, 0 ) )
				.some( ( sibling ) => sibling.key === key );
		},
		[ clientId, fieldName ]
	);
}
