import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/textarea', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Message' }</div>, save: () => null } );
