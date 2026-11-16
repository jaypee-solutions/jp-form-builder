import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeFieldKey, sanitizeFieldKeyInput } from './field-key';
import { defaultLabel } from './labels';
import { useDuplicateFieldKey } from './use-field-key';

/**
 * Inspector controls every Field shares, followed by the type-specific ones.
 *
 * @param {Object}                    props                Component props.
 * @param {string}                    props.type           Field type, e.g. `email`.
 * @param {string}                    props.clientId       Block client id.
 * @param {Object}                    props.attributes     Block attributes.
 * @param {(changes: Object) => void} props.setAttributes  Block attribute setter.
 * @param {boolean}                   props.hasPlaceholder Whether the Field offers a placeholder.
 * @param {import('react').ReactNode} props.children       Type-specific controls.
 */
export function FieldInspector( {
	type,
	clientId,
	attributes,
	setAttributes,
	hasPlaceholder = false,
	children,
} ) {
	const isConsent = type === 'consent';
	const isDuplicateKey = useDuplicateFieldKey(
		clientId,
		attributes.fieldName
	);
	const set = ( name ) => ( value ) => setAttributes( { [ name ]: value } );

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Field', 'jp-form-builder' ) }>
				<TextControl
					label={ __( 'Label', 'jp-form-builder' ) }
					value={ attributes.label }
					placeholder={ defaultLabel( type ) }
					onChange={ set( 'label' ) }
				/>
				<TextControl
					label={ __( 'Field key', 'jp-form-builder' ) }
					value={ attributes.fieldName }
					onChange={ ( value ) =>
						setAttributes( {
							fieldName: sanitizeFieldKeyInput( value ),
						} )
					}
					onBlur={ () =>
						setAttributes( {
							fieldName: normalizeFieldKey(
								attributes.fieldName
							),
						} )
					}
					help={
						isDuplicateKey
							? __(
									'Another Field in this Form already uses this key. Choose a unique one.',
									'jp-form-builder'
								)
							: __(
									'Used in mail placeholders. Lowercase letters, numbers and underscores; unique within this Form.',
									'jp-form-builder'
								)
					}
				/>
				{ isConsent ? (
					<ToggleControl
						label={ __( 'Required', 'jp-form-builder' ) }
						checked
						disabled
						onChange={ () => {} }
						help={ __(
							'A consent checkbox is always required.',
							'jp-form-builder'
						) }
					/>
				) : (
					<ToggleControl
						label={ __( 'Required', 'jp-form-builder' ) }
						checked={ attributes.required }
						onChange={ set( 'required' ) }
					/>
				) }
				<TextareaControl
					label={ __( 'Help text', 'jp-form-builder' ) }
					value={ attributes.helpText }
					onChange={ set( 'helpText' ) }
				/>
				{ hasPlaceholder && (
					<TextControl
						label={ __( 'Placeholder', 'jp-form-builder' ) }
						value={ attributes.placeholder }
						onChange={ set( 'placeholder' ) }
					/>
				) }
			</PanelBody>
			{ children && (
				<PanelBody title={ __( 'Field options', 'jp-form-builder' ) }>
					{ children }
				</PanelBody>
			) }
		</InspectorControls>
	);
}
