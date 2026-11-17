import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewControl,
	PreviewHelp,
	PreviewLabel,
	defaultLabel,
	getEditorData,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const regions = Object.entries(
		getEditorData( 'phone' ).regions ?? {}
	).map( ( [ value, label ] ) => ( { value, label } ) );

	const controls = (
		<SelectControl
			label={ __( 'Default region', 'jp-form-builder' ) }
			value={ attributes.defaultRegion }
			options={ regions }
			onChange={ ( defaultRegion ) => setAttributes( { defaultRegion } ) }
			help={ __(
				'Numbers without a country code are read as belonging to this country.',
				'jp-form-builder'
			) }
		/>
	);

	return (
		<FieldBlock
			type="phone"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'phone' ) }
			</PreviewLabel>
			<PreviewControl variant="placeholder">
				{ attributes.placeholder }
			</PreviewControl>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
