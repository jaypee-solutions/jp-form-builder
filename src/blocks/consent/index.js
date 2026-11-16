import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
function Edit( { attributes } ) {
	return <div { ...useBlockProps() }>{ attributes.label || 'Consent' }</div>;
}
registerBlockType( 'jp-form-builder/consent', {
	edit: Edit,
	save: () => null,
} );
