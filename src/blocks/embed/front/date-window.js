// The pure logic of the Date field in the browser: Date window, Opening date and the check of typed text.
// `today` is the site date from the token response; while it is unknown (null), offset expressions resolve to
// nothing, so only fixed dates are enforced and the server stays authoritative.

import { parseDate } from '../../../shared/date-format.js';
import {
	clampIso,
	resolveDateExpression,
} from '../../../shared/date-offset.js';

/**
 * @param {{earliest?: string, latest?: string, openingDate?: string}} raw   The raw expressions (the control's dataset).
 * @param {string|null}                                                today The site date as `YYYY-MM-DD`, null until the token response arrived.
 * @return {{earliest: string|null, latest: string|null, opening: string|null}} The resolved window and the Opening date clamped into it.
 */
export function resolveDateWindow( raw, today ) {
	const earliest = resolveDateExpression( raw.earliest, today );
	const latest = resolveDateExpression( raw.latest, today );
	const opening = resolveDateExpression( raw.openingDate, today );
	return {
		earliest,
		latest,
		opening:
			opening === null ? null : clampIso( opening, earliest, latest ),
	};
}

/**
 * @param {string}                                       text   What the visitor typed.
 * @param {string}                                       format `dmy` or `iso`.
 * @param {{earliest: string|null, latest: string|null}} window The resolved Date window.
 * @return {{status: string, iso?: string, limit?: string}} `empty`, `ok`, `invalid`, `before` or `after` (with the violated `limit`).
 */
export function checkDateText( text, format, window ) {
	if ( text.trim() === '' ) {
		return { status: 'empty' };
	}
	const iso = parseDate( text, format );
	if ( iso === null ) {
		return { status: 'invalid' };
	}
	if ( window.earliest && iso < window.earliest ) {
		return { status: 'before', iso, limit: window.earliest };
	}
	if ( window.latest && iso > window.latest ) {
		return { status: 'after', iso, limit: window.latest };
	}
	return { status: 'ok', iso };
}

/**
 * @param {string}                                                             text   The text of the field.
 * @param {string}                                                             format `dmy` or `iso`.
 * @param {{opening: string|null, earliest: string|null, latest: string|null}} window The resolved window.
 * @return {string} The value for the native date input: the current date, or while the field is empty the Opening date; '' when there is none.
 */
export function nativePickerValue( text, format, window ) {
	if ( text.trim() === '' ) {
		return window.opening ?? '';
	}
	return parseDate( text, format ) ?? '';
}
