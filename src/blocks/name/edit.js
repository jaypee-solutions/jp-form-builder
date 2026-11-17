import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewControl,
	PreviewGroup,
	PreviewHelp,
	PreviewLabel,
	PreviewPart,
	defaultFirstNameLabel,
	defaultLabel,
	defaultLastNameLabel,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const label = attributes.label.trim() || defaultLabel( 'name' );

	const controls = (
		<>
			<ToggleControl
				label={ __(
					'Ask for first and last name separately',
					'jp-form-builder'
				) }
				checked={ attributes.split }
				onChange={ ( split ) => setAttributes( { split } ) }
			/>
			{ attributes.split && (
				<>
					<TextControl
						label={ __( 'First name label', 'jp-form-builder' ) }
						value={ attributes.firstLabel }
						placeholder={ defaultFirstNameLabel() }
						onChange={ ( firstLabel ) =>
							setAttributes( { firstLabel } )
						}
					/>
					<TextControl
						label={ __( 'Last name label', 'jp-form-builder' ) }
						value={ attributes.lastLabel }
						placeholder={ defaultLastNameLabel() }
						onChange={ ( lastLabel ) =>
							setAttributes( { lastLabel } )
						}
					/>
				</>
			) }
		</>
	);

	return (
		<FieldBlock
			type="name"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder={ ! attributes.split }
			controls={ controls }
		>
			{ attributes.split ? (
				<PreviewGroup legend={ label } required={ attributes.required }>
					<PreviewPart
						label={
							attributes.firstLabel.trim() ||
							defaultFirstNameLabel()
						}
					/>
					<PreviewPart
						label={
							attributes.lastLabel.trim() ||
							defaultLastNameLabel()
						}
					/>
				</PreviewGroup>
			) : (
				<>
					<PreviewLabel required={ attributes.required }>
						{ label }
					</PreviewLabel>
					<PreviewControl variant="placeholder">
						{ attributes.placeholder }
					</PreviewControl>
				</>
			) }
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
