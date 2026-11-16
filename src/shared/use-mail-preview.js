import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

const PREVIEW_DELAY = 400;

/**
 * Renders a mail through `POST /mail-preview` shortly after the input stops changing.
 *
 * @param {Object}   input         What to preview.
 * @param {string}   input.kind    `notification` or `confirmation`.
 * @param {string}   input.subject Subject template.
 * @param {string}   input.body    Body template.
 * @param {Object[]} input.fields  The Form's Fields as `{key, label, type, split}`.
 * @return {{subject: string, html: string, error: string}} The latest result; `error` is set when the request failed.
 */
export function useMailPreview( { kind, subject, body, fields } ) {
	const [ preview, setPreview ] = useState( {
		subject: '',
		html: '',
		error: '',
	} );

	useEffect( () => {
		let current = true;
		const timer = setTimeout( () => {
			apiFetch( {
				path: '/jp-form-builder/v1/mail-preview',
				method: 'POST',
				data: { kind, subject, body, fields },
			} )
				.then(
					( result ) =>
						current &&
						setPreview( {
							subject: result.subject,
							html: result.html,
							error: '',
						} )
				)
				.catch(
					( error ) =>
						current &&
						setPreview( {
							subject: '',
							html: '',
							error: error.message,
						} )
				);
		}, PREVIEW_DELAY );
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ kind, subject, body, fields ] );

	return preview;
}
