import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewControl,
	PreviewHelp,
	PreviewLabel,
	defaultLabel,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const controls = (
		<ToggleControl
			label={ __( 'Use as Reply-To address', 'jp-form-builder' ) }
			checked={ attributes.replyTo }
			onChange={ ( replyTo ) => setAttributes( { replyTo } ) }
			help={ __(
				'Replying to the Notification then answers the Submitter.',
				'jp-form-builder'
			) }
		/>
	);

	return (
		<FieldBlock
			type="email"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'email' ) }
			</PreviewLabel>
			<PreviewControl variant="placeholder">
				{ attributes.placeholder }
			</PreviewControl>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
