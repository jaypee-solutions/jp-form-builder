import { resolveDateExpression, parseDateExpression } from './date-offset.js';
import { formatDate } from './date-format.js';

/**
 * The browser's today as an ISO date; the editor only previews, the server resolves with the site's today.
 *
 * @param {Date} now The current moment.
 * @return {string} `YYYY-MM-DD` in the browser's timezone.
 */
export function browserTodayIso( now = new Date() ) {
	const month = String( now.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( now.getDate() ).padStart( 2, '0' );
	return `${ now.getFullYear() }-${ month }-${ day }`;
}

/**
 * What one date input holds, resolved for display in the editor.
 *
 * @param {string} text     The raw attribute: empty, a fixed ISO date or a Date offset.
 * @param {string} todayIso Today as `YYYY-MM-DD`.
 * @param {string} format   `dmy` or `iso`.
 * @return {{state: string, iso: string, display: string}} `state` is `empty`, `resolved` or `invalid`.
 */
export function expressionStatus( text, todayIso, format ) {
	if ( parseDateExpression( text ).kind === 'empty' ) {
		return { state: 'empty', iso: '', display: '' };
	}
	const iso = resolveDateExpression( text, todayIso );
	if ( iso === null ) {
		return { state: 'invalid', iso: '', display: '' };
	}
	return { state: 'resolved', iso, display: formatDate( iso, format ) };
}

/**
 * The Date window of a date Field as the editor shows it.
 *
 * @param {{earliest: string, latest: string}} attributes The Field's attributes.
 * @param {string}                             todayIso   Today as `YYYY-MM-DD`.
 * @param {string}                             format     `dmy` or `iso`.
 * @return {{earliest: Object, latest: Object, inverted: boolean}} Status per bound; `inverted` when earliest is later than latest.
 */
export function windowStatus( attributes, todayIso, format ) {
	const earliest = expressionStatus( attributes.earliest, todayIso, format );
	const latest = expressionStatus( attributes.latest, todayIso, format );
	return {
		earliest,
		latest,
		inverted:
			earliest.state === 'resolved' &&
			latest.state === 'resolved' &&
			earliest.iso > latest.iso,
	};
}
