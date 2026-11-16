import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
registerBlockType( 'jp-form-builder/embed', { edit: () => <div { ...useBlockProps() }>Form</div>, save: () => null } );
