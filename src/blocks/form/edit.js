import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { defaultSubmitLabel, FormSettings } from '../../shared';

const ALLOWED_BLOCKS = [
	'jp-form-builder/name',
	'jp-form-builder/email',
	'jp-form-builder/phone',
	'jp-form-builder/date',
	'jp-form-builder/address',
	'jp-form-builder/text',
	'jp-form-builder/select',
	'jp-form-builder/radio',
	'jp-form-builder/checkbox',
	'jp-form-builder/textarea',
	'jp-form-builder/conditional',
	'jp-form-builder/consent',
	'core/paragraph',
	'core/heading',
	'core/columns',
	'core/group',
];

const TEMPLATE = [
	[ 'jp-form-builder/name' ],
	[ 'jp-form-builder/email' ],
	[ 'jp-form-builder/textarea' ],
	[ 'jp-form-builder/consent' ],
];

export default function Edit( { clientId, attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'jp-form-builder__form' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'jp-form-builder__fields' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'jp-form-builder' ) }>
					<FormSettings
						clientId={ clientId }
						attributes={ attributes }
						setAttributes={ setAttributes }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div { ...innerBlocksProps } />
				<div className="jp-form-builder__submit" aria-hidden="true">
					<span className="wp-element-button">
						{ attributes.submitLabel.trim() ||
							defaultSubmitLabel() }
					</span>
				</div>
			</div>
		</>
	);
}
