import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/select', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Select' }</div>, save: () => null } );
