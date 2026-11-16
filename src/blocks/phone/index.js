import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/phone', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Phone' }</div>, save: () => null } );
