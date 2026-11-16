import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Phone' }</div>;
}
registerBlockType( 'jp-form-builder/phone', { edit: Edit, save: () => null } );
