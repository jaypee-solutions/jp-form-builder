import { __, sprintf } from '@wordpress/i18n';
import {
	formatDate,
	isFullLength,
	placeholderFor,
} from '../../../shared/date-format.js';
import { clearFieldError, setFieldError } from './errors';
import {
	checkDateText,
	nativePickerValue,
	resolveDateWindow,
} from './date-window';

const TODAY_EVENT = 'jpfb:today';

// The site date per Form, known once the token response arrived.
const todayByForm = new WeakMap();

export const todayOf = ( form ) => todayByForm.get( form ) ?? null;

// Stores the site date and lets the Date fields re-check what was typed before it was known.
export function setToday( form, today ) {
	todayByForm.set( form, today );
	form.dispatchEvent( new CustomEvent( TODAY_EVENT ) );
}

const windowOf = ( control ) =>
	resolveDateWindow(
		{
			earliest: control.dataset.earliest,
			latest: control.dataset.latest,
			openingDate: control.dataset.openingDate,
		},
		todayOf( control.form )
	);

/**
 * @param {HTMLInputElement} control A date text field.
 * @return {string} The message for its current text, or '' when the text is empty or acceptable.
 */
export function dateIssueMessage( control ) {
	const format = control.dataset.dateFormat;
	const result = checkDateText( control.value, format, windowOf( control ) );
	switch ( result.status ) {
		case 'invalid':
			return sprintf(
				/* translators: %s: expected date format, for example YYYY-MM-DD. */
				__( 'Please enter a valid date (%s).', 'jp-form-builder' ),
				placeholderFor( format )
			);
		case 'before':
			return sprintf(
				/* translators: %s: the earliest allowed date. */
				__( 'Date must not be before %s.', 'jp-form-builder' ),
				formatDate( result.limit, format )
			);
		case 'after':
			return sprintf(
				/* translators: %s: the latest allowed date. */
				__( 'Date must not be after %s.', 'jp-form-builder' ),
				formatDate( result.limit, format )
			);
		default:
			return '';
	}
}

function showResult( control ) {
	const message = dateIssueMessage( control );
	if ( message ) {
		setFieldError( control, message );
	} else {
		clearFieldError( control );
	}
}

function createNativeInput() {
	const input = document.createElement( 'input' );
	input.type = 'date';
	input.tabIndex = -1;
	input.setAttribute( 'aria-hidden', 'true' );
	input.className = 'jp-form-builder__date-native';
	return input;
}

function initDate( form, control, whenToday ) {
	const format = control.dataset.dateFormat;
	const wrapper = control.closest( '.jp-form-builder__date' );
	const button = wrapper.querySelector( '.jp-form-builder__date-picker' );
	const native = createNativeInput();
	wrapper.append( native );

	// Typing: the error appears once the text is complete; edits to incomplete text drop a stale error.
	control.addEventListener( 'input', () => {
		if ( isFullLength( control.value, format ) ) {
			showResult( control );
		} else {
			clearFieldError( control );
		}
	} );

	control.addEventListener( 'blur', ( event ) => {
		if ( event.relatedTarget === button ) {
			return;
		}
		const result = checkDateText(
			control.value,
			format,
			windowOf( control )
		);
		if ( result.iso ) {
			control.value = formatDate( result.iso, format );
		}
		showResult( control );
	} );

	form.addEventListener( TODAY_EVENT, () => {
		if ( isFullLength( control.value, format ) ) {
			showResult( control );
		}
	} );

	if ( ! ( 'showPicker' in window.HTMLInputElement.prototype ) ) {
		return;
	}
	button.hidden = false;

	button.addEventListener( 'click', async () => {
		await whenToday();
		const dateWindow = windowOf( control );
		native.min = dateWindow.earliest ?? '';
		native.max = dateWindow.latest ?? '';
		native.value = nativePickerValue( control.value, format, dateWindow );
		try {
			native.showPicker();
		} catch {
			// The browser refused to open the picker; typing still works.
		}
	} );

	native.addEventListener( 'change', () => {
		if ( native.value ) {
			control.value = formatDate( native.value, format );
			showResult( control );
			control.focus();
		}
	} );
}

/**
 * Initialises every date text field of a Form.
 *
 * @param {HTMLFormElement}     form      The Form.
 * @param {() => Promise<void>} whenToday Starts the token request if needed; resolves once the site date is known or the request failed.
 */
export function initDates( form, whenToday ) {
	form.querySelectorAll( 'input[data-date-format]' ).forEach( ( control ) =>
		initDate( form, control, whenToday )
	);
}
