import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Address' }</div>;
}
registerBlockType( 'jp-form-builder/address', {
	edit: Edit,
	save: () => null,
} );
