import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MailPreview } from './mail-preview';
import { MailTemplateEditor } from './mail-template-editor';
import { useMailPreview } from './use-mail-preview';

function modalTitle( kind ) {
	return kind === 'confirmation'
		? __( 'Confirmation Body template', 'jp-form-builder' )
		: __( 'Notification Body template', 'jp-form-builder' );
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
	const [ draft, setDraft ] = useState( {
		subject: savedSubject,
		body: savedBody,
	} );
	const preview = useMailPreview( { kind, ...draft, fields } );

	return (
		<Modal
			title={ modalTitle( kind ) }
			onRequestClose={ onClose }
			size="large"
			className="jp-form-builder__body-modal"
		>
			<div className="jp-form-builder__body-modal-layout">
				<MailTemplateEditor
					kind={ kind }
					id="jp-form-builder-modal"
					subject={ draft.subject }
					body={ draft.body }
					fields={ fields }
					onChange={ setDraft }
				>
					<p>
						{ __(
							'Leave the subject or body empty to use the site default, or the built-in text when the site has no default.',
							'jp-form-builder'
						) }
					</p>
				</MailTemplateEditor>
				<div className="jp-form-builder__body-modal-preview">
					<h2>{ __( 'Preview', 'jp-form-builder' ) }</h2>
					<MailPreview preview={ preview } />
				</div>
			</div>
			<div className="jp-form-builder__body-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'jp-form-builder' ) }
				</Button>
				<Button variant="primary" onClick={ () => onSave( draft ) }>
					{ __( 'Save', 'jp-form-builder' ) }
				</Button>
			</div>
		</Modal>
	);
}
