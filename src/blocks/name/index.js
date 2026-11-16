import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/name', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Name' }</div>, save: () => null } );
