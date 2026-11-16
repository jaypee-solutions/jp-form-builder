import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { BodyEditor } from './body-editor';
import { PlaceholderInserter } from './placeholder-inserter';
import { useMailPreview } from './use-mail-preview';

function kindLabels( kind ) {
	return kind === 'confirmation'
		? {
				title: __( 'Confirmation Body template', 'jp-form-builder' ),
				subject: __( 'Confirmation subject', 'jp-form-builder' ),
				body: __( 'Confirmation body', 'jp-form-builder' ),
			}
		: {
				title: __( 'Notification Body template', 'jp-form-builder' ),
				subject: __( 'Notification subject', 'jp-form-builder' ),
				body: __( 'Notification body', 'jp-form-builder' ),
			};
}

/**
 * Edits the subject and Body template of a Notification or Confirmation next
 * to a live preview. Nothing is written until Save; Cancel discards.
 *
 * @param {Object}                                            props         Component props.
 * @param {string}                                            props.kind    `notification` or `confirmation`.
 * @param {string}                                            props.subject Saved subject template.
 * @param {string}                                            props.body    Saved Body template.
 * @param {Object[]}                                          props.fields  The Form's Fields as `{key, label, type, split}`.
 * @param {(values: {subject: string, body: string}) => void} props.onSave  Called with the edited values.
 * @param {() => void}                                        props.onClose Called to close the modal.
 */
export function BodyTemplateModal( {
	kind,
	subject: savedSubject,
	body: savedBody,
	fields,
	onSave,
	onClose,
} ) {
	const [ subject, setSubject ] = useState( savedSubject );
	const [ body, setBody ] = useState( savedBody );
	const editor = useRef( null );
	const preview = useMailPreview( { kind, subject, body, fields } );
	const labels = kindLabels( kind );

	return (
		<Modal
			title={ labels.title }
			onRequestClose={ onClose }
			size="large"
			className="jp-form-builder__body-modal"
		>
			<div className="jp-form-builder__body-modal-layout">
				<div className="jp-form-builder__body-modal-form">
					<p>
						{ __(
							'Leave the subject or body empty to use the site default, or the built-in text when the site has no default.',
							'jp-form-builder'
						) }
					</p>
					{ kind === 'confirmation' && (
						<p>
							{ __(
								'A Confirmation never contains submitted content, so Field placeholders are not available here.',
								'jp-form-builder'
							) }
						</p>
					) }
					<TextControl
						label={ labels.subject }
						value={ subject }
						onChange={ setSubject }
					/>
					<BodyEditor
						ref={ editor }
						label={ labels.body }
						value={ body }
						onChange={ setBody }
					/>
					<PlaceholderInserter
						kind={ kind }
						fields={ fields }
						onInsert={ ( text ) => editor.current.insert( text ) }
					/>
				</div>
				<div className="jp-form-builder__body-modal-preview">
					<h2>{ __( 'Preview', 'jp-form-builder' ) }</h2>
					{ preview.error ? (
						<Notice status="error" isDismissible={ false }>
							{ preview.error }
						</Notice>
					) : (
						<>
							<p className="jp-form-builder__preview-subject">
								<strong>
									{ __( 'Subject:', 'jp-form-builder' ) }
								</strong>{ ' ' }
								{ preview.subject }
							</p>
							<iframe
								className="jp-form-builder__mail-preview"
								title={ __(
									'Mail preview',
									'jp-form-builder'
								) }
								sandbox=""
								srcDoc={ preview.html }
							/>
						</>
					) }
				</div>
			</div>
			<div className="jp-form-builder__body-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'jp-form-builder' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => onSave( { subject, body } ) }
				>
					{ __( 'Save', 'jp-form-builder' ) }
				</Button>
			</div>
		</Modal>
	);
}
