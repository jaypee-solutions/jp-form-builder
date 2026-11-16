import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Select' }</div>;
}
registerBlockType( 'jp-form-builder/select', { edit: Edit, save: () => null } );
