import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewHelp,
	PreviewLabel,
	defaultLabel,
} from '../../shared';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const controls = (
		<>
			<RangeControl
				label={ __( 'Rows', 'jp-form-builder' ) }
				value={ attributes.rows }
				min={ 2 }
				max={ 20 }
				onChange={ ( rows ) => setAttributes( { rows: rows ?? 6 } ) }
			/>
			<RangeControl
				label={ __( 'Maximum length', 'jp-form-builder' ) }
				value={ attributes.maxLength }
				min={ 1 }
				max={ 10000 }
				onChange={ ( maxLength ) =>
					setAttributes( { maxLength: maxLength ?? 5000 } )
				}
			/>
		</>
	);

	return (
		<FieldBlock
			type="textarea"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			hasPlaceholder
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'textarea' ) }
			</PreviewLabel>
			<div
				className="jp-form-builder__control jp-form-builder__control--placeholder"
				style={ { minHeight: `${ attributes.rows * 1.5 + 1 }em` } }
			>
				{ attributes.placeholder }
			</div>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
