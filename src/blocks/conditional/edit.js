import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

// A group holds Fields and pass-through blocks, never another group.
const ALLOWED_BLOCKS = [
	'jp-form-builder/name',
	'jp-form-builder/email',
	'jp-form-builder/phone',
	'jp-form-builder/address',
	'jp-form-builder/text',
	'jp-form-builder/select',
	'jp-form-builder/radio',
	'jp-form-builder/checkbox',
	'jp-form-builder/textarea',
	'jp-form-builder/consent',
	'core/paragraph',
	'core/heading',
];

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'jp-form-builder__conditional',
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'jp-form-builder__fields' },
		{ allowedBlocks: ALLOWED_BLOCKS, templateLock: false }
	);

	return (
		<div { ...blockProps }>
			<div { ...innerBlocksProps } />
		</div>
	);
}
