import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Text' }</div>;
}
registerBlockType( 'jp-form-builder/text', { edit: Edit, save: () => null } );
