import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/email', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Email' }</div>, save: () => null } );
