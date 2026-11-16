import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Message' }</div>;
}
registerBlockType( 'jp-form-builder/textarea', {
	edit: Edit,
	save: () => null,
} );
