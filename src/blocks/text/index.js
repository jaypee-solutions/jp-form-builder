import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/text', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Text' }</div>, save: () => null } );
