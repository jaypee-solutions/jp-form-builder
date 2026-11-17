import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewGroup,
	PreviewHelp,
	PreviewPart,
	addressPartLabels,
	defaultLabel,
	getEditorData,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const countries = getEditorData( 'address' ).countries ?? {};
	const countryOptions = Object.entries( countries )
		.map( ( [ value, label ] ) => ( { value, label } ) )
		.sort( ( a, b ) => a.label.localeCompare( b.label ) );
	const parts = addressPartLabels();

	const controls = (
		<SelectControl
			label={ __( 'Default country', 'jp-form-builder' ) }
			value={ attributes.defaultCountry }
			options={ countryOptions }
			onChange={ ( defaultCountry ) =>
				setAttributes( { defaultCountry } )
			}
		/>
	);

	return (
		<FieldBlock
			type="address"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			controls={ controls }
		>
			<PreviewGroup
				legend={ attributes.label.trim() || defaultLabel( 'address' ) }
				required={ attributes.required }
			>
				<PreviewPart label={ parts.street } />
				<div className="jp-form-builder__parts-row">
					<PreviewPart label={ parts.postcode } />
					<PreviewPart label={ parts.city } />
				</div>
				<PreviewPart label={ parts.country }>
					{ countries[ attributes.defaultCountry ] }
				</PreviewPart>
			</PreviewGroup>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
