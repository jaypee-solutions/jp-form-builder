import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const allowedBlocks = [
	'jp-form-builder/name', 'jp-form-builder/email', 'jp-form-builder/phone',
	'jp-form-builder/address', 'jp-form-builder/text', 'jp-form-builder/select',
	'jp-form-builder/textarea', 'jp-form-builder/consent', 'core/paragraph', 'core/heading',
];
const template = [
	[ 'jp-form-builder/name' ], [ 'jp-form-builder/email' ],
	[ 'jp-form-builder/textarea' ], [ 'jp-form-builder/consent' ],
];
registerBlockType( 'jp-form-builder/form', {
	edit: () => <div { ...useBlockProps() }><InnerBlocks allowedBlocks={ allowedBlocks } template={ template } templateLock={ false } /></div>,
	save: () => <InnerBlocks.Content />,
} );
