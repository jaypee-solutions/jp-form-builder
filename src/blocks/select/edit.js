import {
	FieldBlock,
	OptionsEditor,
	PreviewControl,
	PreviewHelp,
	PreviewLabel,
	defaultLabel,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const controls = (
		<OptionsEditor
			options={ attributes.options }
			onChange={ ( options ) => setAttributes( { options } ) }
		/>
	);

	return (
		<FieldBlock
			type="select"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'select' ) }
			</PreviewLabel>
			<PreviewControl variant="select">
				{ attributes.placeholder }
			</PreviewControl>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
