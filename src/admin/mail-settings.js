import { Button } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { BodyEditor } from '../shared/body-editor';
import { MailPreview } from '../shared/mail-preview';
import { MailTemplateEditor } from '../shared/mail-template-editor';
import { useMailPreview } from '../shared/use-mail-preview';

const FIELD_NAME = 'jp_form_builder_settings';

/**
 * Fields the placeholder menu and the preview use: a site default is not tied
 * to one Form, so it is written for the Field keys most Forms have.
 *
 * @return {{key: string, label: string, type: string}[]} Sample Fields.
 */
function sampleFields() {
	return [
		{ key: 'name', label: __( 'Name', 'jp-form-builder' ), type: 'name' },
		{
			key: 'email',
			label: __( 'Email', 'jp-form-builder' ),
			type: 'email',
		},
		{
			key: 'message',
			label: __( 'Message', 'jp-form-builder' ),
			type: 'textarea',
		},
	];
}

/**
 * The mail section of the settings page: the Mail layout and the site-default
 * Body templates, each with the editor of the Form's Body template modal and a
 * live preview. The values travel in hidden inputs, so the page saves them
 * like any other setting.
 *
 * @param {Object}                props         Component props.
 * @param {Record<string,string>} props.initial Saved values by setting key.
 */
export function MailSettings( { initial } ) {
	const fields = useMemo( sampleFields, [] );
	const [ layout, setLayout ] = useState( initial.mail_layout );
	// Remounts the layout editor, which only reads its value on mount.
	const [ layoutVersion, setLayoutVersion ] = useState( 0 );
	const [ notification, setNotification ] = useState( {
		subject: initial.notification_subject,
		body: initial.notification_body,
	} );
	const [ confirmation, setConfirmation ] = useState( {
		subject: initial.confirmation_subject,
		body: initial.confirmation_body,
	} );

	// The Mail layout is previewed with the default notification, as it frames every mail.
	const notificationPreview = useMailPreview( {
		kind: 'notification',
		...notification,
		fields,
		layout,
	} );
	const confirmationPreview = useMailPreview( {
		kind: 'confirmation',
		...confirmation,
		layout,
	} );

	const hidden = {
		mail_layout: layout,
		notification_subject: notification.subject,
		notification_body: notification.body,
		confirmation_subject: confirmation.subject,
		confirmation_body: confirmation.body,
	};

	return (
		<div className="jp-form-builder__mail-settings">
			{ Object.entries( hidden ).map( ( [ key, value ] ) => (
				<input
					key={ key }
					type="hidden"
					name={ `${ FIELD_NAME }[${ key }]` }
					value={ value }
				/>
			) ) }

			<h3>{ __( 'Mail layout', 'jp-form-builder' ) }</h3>
			<div className="jp-form-builder__body-modal-layout">
				<div>
					<p>
						{ __(
							'The frame around every mail. It must contain {content}. Leave it empty to use the bundled layout.',
							'jp-form-builder'
						) }
					</p>
					<BodyEditor
						key={ layoutVersion }
						id="jpfb-mail_layout"
						label={ __( 'Mail layout', 'jp-form-builder' ) }
						value={ layout }
						onChange={ setLayout }
					/>
					<Button
						variant="secondary"
						onClick={ () => {
							setLayout( '' );
							setLayoutVersion( ( version ) => version + 1 );
						} }
					>
						{ __( 'Reset to default', 'jp-form-builder' ) }
					</Button>
				</div>
				<div className="jp-form-builder__body-modal-preview">
					<h4>{ __( 'Live mail preview', 'jp-form-builder' ) }</h4>
					<MailPreview preview={ notificationPreview } />
				</div>
			</div>

			<h3>{ __( 'Default notification', 'jp-form-builder' ) }</h3>
			<div className="jp-form-builder__body-modal-layout">
				<MailTemplateEditor
					kind="notification"
					id="jpfb-notification"
					subject={ notification.subject }
					body={ notification.body }
					fields={ fields }
					onChange={ setNotification }
				>
					<p>
						{ __(
							'Used by Forms that set no subject or Body template of their own. Leave empty for the built-in text. The Field placeholders use the Field keys of your Forms; the examples assume the keys name, email and message.',
							'jp-form-builder'
						) }
					</p>
				</MailTemplateEditor>
				<div className="jp-form-builder__body-modal-preview">
					<h4>{ __( 'Preview', 'jp-form-builder' ) }</h4>
					<MailPreview preview={ notificationPreview } />
				</div>
			</div>

			<h3>{ __( 'Default confirmation', 'jp-form-builder' ) }</h3>
			<div className="jp-form-builder__body-modal-layout">
				<MailTemplateEditor
					kind="confirmation"
					id="jpfb-confirmation"
					subject={ confirmation.subject }
					body={ confirmation.body }
					fields={ [] }
					onChange={ setConfirmation }
				>
					<p>
						{ __(
							'Used by Forms that send a Confirmation without a subject or Body template of their own. Leave empty for the built-in text.',
							'jp-form-builder'
						) }
					</p>
				</MailTemplateEditor>
				<div className="jp-form-builder__body-modal-preview">
					<h4>{ __( 'Preview', 'jp-form-builder' ) }</h4>
					<MailPreview preview={ confirmationPreview } />
				</div>
			</div>
		</div>
	);
}
