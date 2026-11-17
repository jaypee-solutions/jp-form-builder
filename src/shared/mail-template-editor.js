import { TextControl } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { BodyEditor } from './body-editor';
import { PlaceholderInserter } from './placeholder-inserter';
import './mail-editor.css';

/**
 * @param {string} kind `notification` or `confirmation`.
 * @return {{subject: string, body: string}} The labels of the two controls.
 */
export function mailTemplateLabels( kind ) {
	return kind === 'confirmation'
		? {
				subject: __( 'Confirmation subject', 'jp-form-builder' ),
				body: __( 'Confirmation body', 'jp-form-builder' ),
			}
		: {
				subject: __( 'Notification subject', 'jp-form-builder' ),
				body: __( 'Notification body', 'jp-form-builder' ),
			};
}

/**
 * The controls for the subject and Body template of a Notification or
 * Confirmation: a subject field, the code editor and the placeholder inserter.
 * A Form's Body template modal and the site defaults on the settings page both
 * use it, and show `useMailPreview()` next to it.
 *
 * @param {Object}                                            props          Component props.
 * @param {string}                                            props.kind     `notification` or `confirmation`.
 * @param {string}                                            props.id       Prefix for the ids of the controls; unique on the page.
 * @param {string}                                            props.subject  Current subject template.
 * @param {string}                                            props.body     Body template the editor starts with.
 * @param {Object[]}                                          props.fields   Fields offered as placeholders, as `{key, label}`.
 * @param {(values: {subject: string, body: string}) => void} props.onChange Called with both values after every edit.
 * @param {import('react').ReactNode}                         props.children Help shown above the controls.
 */
export function MailTemplateEditor( {
	kind,
	id,
	subject,
	body,
	fields,
	onChange,
	children,
} ) {
	const editor = useRef( null );
	const labels = mailTemplateLabels( kind );

	return (
		<div className="jp-form-builder__body-modal-form">
			{ children }
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
				onChange={ ( value ) => onChange( { subject: value, body } ) }
			/>
			<BodyEditor
				ref={ editor }
				id={ `${ id }-body` }
				label={ labels.body }
				value={ body }
				onChange={ ( value ) => onChange( { subject, body: value } ) }
			/>
			<PlaceholderInserter
				kind={ kind }
				fields={ fields }
				onInsert={ ( text ) => editor.current.insert( text ) }
			/>
		</div>
	);
}
