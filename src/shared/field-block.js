import { useBlockProps } from '@wordpress/block-editor';
import { FieldInspector } from './field-inspector';
import { fieldWidthClassName } from './field-width';
import { useFieldKey } from './use-field-key';

/**
 * Shared frame of every Field's `edit`: assigns the Field key, renders the
 * inspector and wraps the preview in the block wrapper.
 *
 * @param {Object}                    props                Component props.
 * @param {string}                    props.type           Field type, e.g. `email`.
 * @param {string}                    props.clientId       Block client id.
 * @param {Object}                    props.attributes     Block attributes.
 * @param {(changes: Object) => void} props.setAttributes  Block attribute setter.
 * @param {boolean}                   props.hasPlaceholder Whether the Field offers a placeholder.
 * @param {import('react').ReactNode} props.controls       Type-specific inspector controls.
 * @param {import('react').ReactNode} props.children       The preview.
 */
export function FieldBlock( {
	type,
	clientId,
	attributes,
	setAttributes,
	hasPlaceholder,
	controls,
	children,
} ) {
	useFieldKey( { type, clientId, attributes, setAttributes } );
	const blockProps = useBlockProps( {
		className: [
			'jp-form-builder__field',
			fieldWidthClassName( type, attributes.width ),
		]
			.filter( Boolean )
			.join( ' ' ),
	} );

	return (
		<>
			<FieldInspector
				type={ type }
				clientId={ clientId }
				attributes={ attributes }
				setAttributes={ setAttributes }
				hasPlaceholder={ hasPlaceholder }
			>
				{ controls }
			</FieldInspector>
			<div { ...blockProps }>{ children }</div>
		</>
	);
}
