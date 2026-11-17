import {
	FieldBlock,
	OptionsEditor,
	PreviewChoices,
	PreviewGroup,
	PreviewHelp,
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
			type="radio"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			controls={ controls }
		>
			<PreviewGroup
				legend={ attributes.label.trim() || defaultLabel( 'radio' ) }
				required={ attributes.required }
			>
				<PreviewChoices
					variant="radio"
					options={ attributes.options }
				/>
			</PreviewGroup>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
