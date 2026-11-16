import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit() {
	return <div { ...useBlockProps() }>Form</div>;
}
registerBlockType( 'jp-form-builder/embed', { edit: Edit, save: () => null } );
