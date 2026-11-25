import {
	FieldBlock,
	PreviewControl,
	PreviewHelp,
	PreviewLabel,
	defaultLabel,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	return (
		<FieldBlock
			type="postcode"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'postcode' ) }
			</PreviewLabel>
			<PreviewControl variant="placeholder">
				{ attributes.placeholder }
			</PreviewControl>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
