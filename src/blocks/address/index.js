import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/address', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Address' }</div>, save: () => null } );
