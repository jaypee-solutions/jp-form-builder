import { TextControl, TextareaControl } from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewHelp,
	PreviewLabel,
	defaultConsentLinkText,
	defaultConsentText,
} from '../../shared';

const PRIVACY_TOKEN = '{privacy_policy}';

// The text only reaches the block while it still contains the token, so the
// privacy-policy link can't be edited away; an invalid draft is reverted on blur.
function ConsentTextControl( { value, onChange } ) {
	const [ draft, setDraft ] = useState( value );
	const missingToken = ! draft.includes( PRIVACY_TOKEN );

	useEffect( () => {
		setDraft( value );
	}, [ value ] );

	return (
		<TextareaControl
			label={ __( 'Consent text', 'jp-form-builder' ) }
			value={ draft }
			onChange={ ( next ) => {
				setDraft( next );
				if ( next.includes( PRIVACY_TOKEN ) ) {
					onChange( next );
				}
			} }
			onBlur={ () => setDraft( value ) }
			help={
				missingToken
					? __(
							'The text must contain {privacy_policy}; without it the link would be lost. The previous text is restored when you leave this field.',
							'jp-form-builder'
						)
					: __(
							'Write {privacy_policy} where the privacy-policy link should appear.',
							'jp-form-builder'
						)
			}
		/>
	);
}

export default function Edit( { clientId, attributes, setAttributes } ) {
	const text = attributes.text.trim() || defaultConsentText();
	const linkText = attributes.linkText.trim() || defaultConsentLinkText();
	const parts = text.split( PRIVACY_TOKEN );

	const controls = (
		<>
			<ConsentTextControl
				value={ text }
				onChange={ ( next ) => setAttributes( { text: next } ) }
			/>
			<TextControl
				label={ __( 'Link text', 'jp-form-builder' ) }
				value={ attributes.linkText }
				placeholder={ defaultConsentLinkText() }
				onChange={ ( next ) => setAttributes( { linkText: next } ) }
			/>
			<TextControl
				label={ __( 'Privacy policy URL', 'jp-form-builder' ) }
				type="url"
				value={ attributes.privacyUrl }
				onChange={ ( next ) => setAttributes( { privacyUrl: next } ) }
				help={ __(
					'Leave empty to link to the site’s privacy policy page.',
					'jp-form-builder'
				) }
			/>
		</>
	);

	return (
		<FieldBlock
			type="consent"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			controls={ controls }
		>
			<div className="jp-form-builder__consent">
				<span
					className="jp-form-builder__checkbox"
					aria-hidden="true"
				/>
				<PreviewLabel required>
					{ parts.map( ( part, index ) => (
						// The parts are positional and never reordered.

						<Fragment key={ index }>
							{ index > 0 && (
								<span className="jp-form-builder__link">
									{ linkText }
								</span>
							) }
							{ part }
						</Fragment>
					) ) }
				</PreviewLabel>
			</div>
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
