// Display formats of the Date field. The wire value is always ISO `YYYY-MM-DD`. Mirrors Date_Format in PHP.
// No DOM, no WordPress imports.

import { isValidIsoDate } from './date-offset.js';

const DMY_PATTERN = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/;
const ISO_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

const PLACEHOLDERS = { dmy: 'TT.MM.JJJJ', iso: 'YYYY-MM-DD' };

/**
 * @param {string} locale A WordPress or BCP 47 locale such as `de_DE`.
 * @return {string} `dmy` for German locales, else `iso`.
 */
export function dateFormatFor( locale ) {
	return String( locale ?? '' )
		.toLowerCase()
		.startsWith( 'de' )
		? 'dmy'
		: 'iso';
}

/**
 * @param {string} format `dmy` or `iso`.
 * @return {string} The placeholder text.
 */
export function placeholderFor( format ) {
	return PLACEHOLDERS[ format ] ?? PLACEHOLDERS.iso;
}

/**
 * @param {string} iso    `YYYY-MM-DD`.
 * @param {string} format `dmy` or `iso`.
 * @return {string} The date in the display format.
 */
export function formatDate( iso, format ) {
	if ( format !== 'dmy' ) {
		return iso;
	}
	const [ year, month, day ] = iso.split( '-' );
	return `${ day }.${ month }.${ year }`;
}

/**
 * @param {string} text   What the visitor typed (surrounding whitespace ignored).
 * @param {string} format `dmy` or `iso`.
 * @return {string|null} The ISO date, or null when the text is not a valid date in the format.
 */
export function parseDate( text, format ) {
	const value = String( text ?? '' ).trim();
	let iso;
	if ( format === 'dmy' ) {
		const match = DMY_PATTERN.exec( value );
		if ( ! match ) {
			return null;
		}
		iso = `${ match[ 3 ] }-${ match[ 2 ].padStart( 2, '0' ) }-${ match[ 1 ].padStart( 2, '0' ) }`;
	} else {
		if ( ! ISO_PATTERN.test( value ) ) {
			return null;
		}
		iso = value;
	}
	return isValidIsoDate( iso ) ? iso : null;
}

/**
 * @param {string} text   What the visitor typed.
 * @param {string} format `dmy` or `iso`.
 * @return {boolean} Whether the text has reached the length at which it is checked live: 10 characters, or in `dmy` a day/month without leading zero followed by a 4-digit year.
 */
export function isFullLength( text, format ) {
	const value = String( text ?? '' ).trim();
	return (
		value.length === 10 || ( format === 'dmy' && DMY_PATTERN.test( value ) )
	);
}
