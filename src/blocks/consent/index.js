import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/consent', { edit: ( { attributes } ) => <div { ...useBlockProps() }>{ attributes.label || 'Consent' }</div>, save: () => null } );
