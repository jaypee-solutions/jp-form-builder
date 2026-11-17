import { RangeControl } from '@wordpress/components';
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
		<RangeControl
			label={ __( 'Maximum length', 'jp-form-builder' ) }
			value={ attributes.maxLength }
			min={ 1 }
			max={ 1000 }
			onChange={ ( maxLength ) =>
				setAttributes( { maxLength: maxLength ?? 200 } )
			}
		/>
	);

	return (
		<FieldBlock
			type="text"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'text' ) }
			</PreviewLabel>
			<PreviewControl variant="placeholder">
				{ attributes.placeholder }
			</PreviewControl>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
