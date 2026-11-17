// Mirrors Phone_Normalizer::normalize() in PHP, so an obviously wrong number is
// caught in the browser and does not cost a rate-limit attempt. The server stays
// the authority: it normalises to E.164 and applies the same rules again.

const COUNTRY_CODES = { DE: '49', AT: '43', CH: '41' };

// National number length range (without the leading 0) per country code.
const NATIONAL_LENGTHS = { 49: [ 6, 13 ], 43: [ 4, 13 ], 41: [ 9, 9 ] };

// Removes the national trunk 0 after a DE/AT/CH country code; null when the national length is off.
function withoutTrunkPrefix( international ) {
	const countryCode = international.slice( 0, 2 );
	const lengths = NATIONAL_LENGTHS[ countryCode ];
	if ( ! lengths ) {
		return international;
	}
	let national = international.slice( 2 );
	if ( national.startsWith( '0' ) ) {
		national = national.slice( 1 );
	}
	if ( national.length < lengths[ 0 ] || national.length > lengths[ 1 ] ) {
		return null;
	}
	return countryCode + national;
}

/**
 * @param {string} input  What the visitor typed.
 * @param {string} region Default region (`DE`, `AT` or `CH`) for numbers that start with 0.
 * @return {boolean} Whether the server would accept the number.
 */
export function isPlausiblePhoneNumber( input, region ) {
	const value = input.trim();
	if (
		! /^[0-9 +\-/().]+$/.test( value ) ||
		value.indexOf( '+', 1 ) !== -1
	) {
		return false;
	}

	const digits = value.replace( /\(0\)/g, '' ).replace( /\D/g, '' );
	let international;
	if ( value.startsWith( '+' ) ) {
		international = digits;
	} else if ( digits.startsWith( '00' ) ) {
		international = digits.slice( 2 );
	} else if ( digits.startsWith( '0' ) ) {
		const countryCode = COUNTRY_CODES[ region.toUpperCase() ];
		if ( ! countryCode ) {
			return false;
		}
		international = countryCode + digits.slice( 1 );
	} else {
		return false;
	}

	if ( international === '' || international.startsWith( '0' ) ) {
		return false;
	}
	const normalized = withoutTrunkPrefix( international );
	return (
		normalized !== null && normalized.length >= 8 && normalized.length <= 15
	);
}
