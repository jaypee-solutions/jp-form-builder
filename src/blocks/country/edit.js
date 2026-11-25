import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewControl,
	PreviewHelp,
	PreviewLabel,
	countryOptions,
	defaultLabel,
	getEditorData,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const countries = getEditorData( 'country' ).countries ?? {};

	const controls = (
		<SelectControl
			label={ __( 'Default country', 'jp-form-builder' ) }
			value={ attributes.defaultCountry }
			options={ [
				{ label: __( 'Please choose…', 'jp-form-builder' ), value: '' },
				...countryOptions( countries ),
			] }
			onChange={ ( defaultCountry ) =>
				setAttributes( { defaultCountry } )
			}
			help={ __(
				'Choose “Please choose…” to make visitors pick a country themselves.',
				'jp-form-builder'
			) }
		/>
	);

	return (
		<FieldBlock
			type="country"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'country' ) }
			</PreviewLabel>
			<PreviewControl variant="select">
				{ countries[ attributes.defaultCountry ] ??
					__( 'Please choose…', 'jp-form-builder' ) }
			</PreviewControl>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
