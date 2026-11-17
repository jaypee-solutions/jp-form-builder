import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './mail-editor.css';

/**
 * Shows a mail rendered by `useMailPreview()`: its subject and body in a
 * sandboxed frame, or the error. The error is rendered as text, never as markup.
 *
 * @param {Object}                                         props         Component props.
 * @param {{subject: string, html: string, error: string}} props.preview Result of `useMailPreview()`.
 */
export function MailPreview( { preview } ) {
	if ( preview.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ preview.error }
			</Notice>
		);
	}
	return (
		<>
			<p className="jp-form-builder__preview-subject">
				<strong>{ __( 'Subject:', 'jp-form-builder' ) }</strong>{ ' ' }
				{ preview.subject }
			</p>
			<iframe
				className="jp-form-builder__mail-preview"
				title={ __( 'Mail preview', 'jp-form-builder' ) }
				sandbox=""
				srcDoc={ preview.html }
			/>
		</>
	);
}
