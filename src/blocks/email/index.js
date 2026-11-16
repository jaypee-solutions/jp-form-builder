import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Email' }</div>;
}
registerBlockType( 'jp-form-builder/email', { edit: Edit, save: () => null } );
