import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Name' }</div>;
}
registerBlockType( 'jp-form-builder/name', { edit: Edit, save: () => null } );
