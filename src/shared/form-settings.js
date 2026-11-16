import { store as blockEditorStore } from '@wordpress/block-editor';
import { Button, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { BodyTemplateModal } from './body-template-modal';
import { getEditorData } from './editor-data';
import { collectFormFields } from './form-fields';
import { defaultSubmitLabel } from './labels';
import { splitRecipients } from './recipients';

function RecipientsControl( { value, onChange } ) {
	const { invalid } = splitRecipients( value );

	return (
		<TextControl
			label={ __( 'Recipients', 'jp-form-builder' ) }
			value={ value }
			onChange={ onChange }
			className={
				invalid.length > 0 ? 'has-invalid-recipients' : undefined
			}
			aria-invalid={ invalid.length > 0 }
			help={
				invalid.length > 0
					? sprintf(
							/* translators: %s: comma-separated list of entries that are not email addresses. */
							__(
								'Not a valid email address: %s',
								'jp-form-builder'
							),
							invalid.join( ', ' )
						)
					: __(
							'Separate several addresses with commas. Leave empty to use the site default.',
							'jp-form-builder'
						)
			}
		/>
	);
}

/**
 * The Form-level settings of the `jp-form-builder/form` block. Shown in the
 * block inspector and in the document sidebar, so both edit the same attributes.
 *
 * @param {Object}                    props               Component props.
 * @param {string}                    props.clientId      Client id of the Form block.
 * @param {Object}                    props.attributes    Attributes of the Form block.
 * @param {(changes: Object) => void} props.setAttributes Setter for the Form block's attributes.
 */
export function FormSettings( { clientId, attributes, setAttributes } ) {
	const turnstileConfigured = !! getEditorData( 'form' ).turnstileConfigured;
	const set = ( name ) => ( value ) => setAttributes( { [ name ]: value } );
	// Which Body template modal is open: `notification`, `confirmation` or none.
	const [ openModal, setOpenModal ] = useState( null );
	const blocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);
	const fields = useMemo( () => collectFormFields( blocks ), [ blocks ] );

	const saveModal = ( { subject, body } ) => {
		setAttributes(
			openModal === 'confirmation'
				? { confirmationSubject: subject, confirmationBody: body }
				: { subject, notificationBody: body }
		);
		setOpenModal( null );
	};

	return (
		<div className="jp-form-builder__settings">
			<TextControl
				label={ __( 'Submit button label', 'jp-form-builder' ) }
				value={ attributes.submitLabel }
				placeholder={ defaultSubmitLabel() }
				onChange={ set( 'submitLabel' ) }
			/>
			<TextControl
				label={ __( 'Success message', 'jp-form-builder' ) }
				value={ attributes.successMessage }
				onChange={ set( 'successMessage' ) }
				help={ __(
					'Shown after a successful submission. Leave empty for the default message.',
					'jp-form-builder'
				) }
			/>
			<h3 className="jp-form-builder__settings-heading">
				{ __( 'Notification', 'jp-form-builder' ) }
			</h3>
			<RecipientsControl
				value={ attributes.recipients }
				onChange={ set( 'recipients' ) }
			/>
			<TextControl
				label={ __( 'Notification subject', 'jp-form-builder' ) }
				value={ attributes.subject }
				onChange={ set( 'subject' ) }
				help={ __(
					'Leave empty for the default subject.',
					'jp-form-builder'
				) }
			/>
			<Button
				variant="secondary"
				onClick={ () => setOpenModal( 'notification' ) }
			>
				{ __( 'Edit Notification Body template', 'jp-form-builder' ) }
			</Button>
			<h3 className="jp-form-builder__settings-heading">
				{ __( 'Confirmation', 'jp-form-builder' ) }
			</h3>
			<ToggleControl
				label={ __(
					'Send a confirmation to the Submitter',
					'jp-form-builder'
				) }
				checked={ attributes.confirmationEnabled }
				disabled={ ! turnstileConfigured }
				onChange={ set( 'confirmationEnabled' ) }
				help={
					turnstileConfigured
						? __(
								'The Submitter receives a short acknowledgement without the submitted content.',
								'jp-form-builder'
							)
						: __(
								'Not available: confirmations go to an address the visitor enters, so they need Cloudflare Turnstile as bot protection. Add the Turnstile keys in the settings first.',
								'jp-form-builder'
							)
				}
			/>
			{ attributes.confirmationEnabled && (
				<>
					<TextControl
						label={ __(
							'Confirmation subject',
							'jp-form-builder'
						) }
						value={ attributes.confirmationSubject }
						onChange={ set( 'confirmationSubject' ) }
						help={ __(
							'Leave empty for the default subject.',
							'jp-form-builder'
						) }
					/>
					<Button
						variant="secondary"
						onClick={ () => setOpenModal( 'confirmation' ) }
					>
						{ __(
							'Edit Confirmation Body template',
							'jp-form-builder'
						) }
					</Button>
				</>
			) }
			{ openModal && (
				<BodyTemplateModal
					kind={ openModal }
					subject={
						openModal === 'confirmation'
							? attributes.confirmationSubject
							: attributes.subject
					}
					body={
						openModal === 'confirmation'
							? attributes.confirmationBody
							: attributes.notificationBody
					}
					fields={ fields }
					onSave={ saveModal }
					onClose={ () => setOpenModal( null ) }
				/>
			) }
		</div>
	);
}
