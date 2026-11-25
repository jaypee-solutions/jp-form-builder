// Date expressions of the Date field (Earliest, Latest, Opening date): empty, a fixed ISO date or a Date offset.
// Mirrors Date_Offset in PHP; both sides share tests/fixtures/date-offsets.json. No DOM, no WordPress imports.

export const MIN_ISO = '1900-01-01';
export const MAX_ISO = '2100-12-31';

const ISO_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const OFFSET_PATTERN = /^([+-]?)(\d{1,3})([dwmy])$/;

const daysInMonth = ( year, month ) =>
	new Date( Date.UTC( year, month, 0 ) ).getUTCDate();

// Date.UTC maps the years 0..99 to 1900..1999, so years are set explicitly.
function utcDate( year, month, day ) {
	const date = new Date( 0 );
	date.setUTCFullYear( year, month - 1, day );
	return date;
}

const pad = ( number, length ) => String( number ).padStart( length, '0' );

function toIso( date ) {
	return `${ pad( date.getUTCFullYear(), 4 ) }-${ pad(
		date.getUTCMonth() + 1,
		2
	) }-${ pad( date.getUTCDate(), 2 ) }`;
}

// Splits a well-formed real calendar date (any year 1..9999) into numbers, or null.
function splitIso( iso ) {
	const match = ISO_PATTERN.exec( iso );
	if ( ! match ) {
		return null;
	}
	const [ year, month, day ] = match.slice( 1 ).map( Number );
	if ( year < 1 || month < 1 || month > 12 || day < 1 ) {
		return null;
	}
	return day <= daysInMonth( year, month ) ? { year, month, day } : null;
}

/**
 * @param {string} iso Candidate `YYYY-MM-DD`.
 * @return {boolean} Whether it is a real calendar date with a year within 1900..2100.
 */
export function isValidIsoDate( iso ) {
	if ( typeof iso !== 'string' ) {
		return false;
	}
	return splitIso( iso ) !== null && iso >= MIN_ISO && iso <= MAX_ISO;
}

/**
 * @param {string}      iso      The date.
 * @param {string|null} earliest Lower bound or null.
 * @param {string|null} latest   Upper bound or null.
 * @return {string} The date moved into the bounds; the earliest wins when the bounds are inverted.
 */
export function clampIso( iso, earliest = null, latest = null ) {
	let result = iso;
	if ( latest && result > latest ) {
		result = latest;
	}
	if ( earliest && result < earliest ) {
		result = earliest;
	}
	return result;
}

/**
 * @param {string} text A raw expression.
 * @return {{kind: string, iso?: string, amount?: number, unit?: string}} `empty`, `fixed`, `offset` or `invalid`.
 */
export function parseDateExpression( text ) {
	const value = String( text ?? '' )
		.trim()
		.toLowerCase();
	if ( value === '' ) {
		return { kind: 'empty' };
	}
	if ( value === 'now' || value === 'today' ) {
		return { kind: 'offset', amount: 0, unit: 'd' };
	}
	if ( ISO_PATTERN.test( value ) ) {
		return isValidIsoDate( value )
			? { kind: 'fixed', iso: value }
			: { kind: 'invalid' };
	}
	const match = OFFSET_PATTERN.exec( value );
	if ( ! match ) {
		return { kind: 'invalid' };
	}
	const amount = Number( match[ 2 ] );
	return {
		kind: 'offset',
		amount: match[ 1 ] === '-' ? -amount : amount,
		unit: match[ 3 ],
	};
}

function applyOffset( todayIso, { amount, unit } ) {
	const { year, month, day } = splitIso( todayIso );
	if ( unit === 'd' || unit === 'w' ) {
		return toIso(
			utcDate( year, month, day + amount * ( unit === 'w' ? 7 : 1 ) )
		);
	}
	const totalMonths =
		year * 12 + ( month - 1 ) + amount * ( unit === 'y' ? 12 : 1 );
	const targetYear = Math.floor( totalMonths / 12 );
	const targetMonth = ( totalMonths % 12 ) + 1;
	return toIso(
		utcDate(
			targetYear,
			targetMonth,
			Math.min( day, daysInMonth( targetYear, targetMonth ) )
		)
	);
}

/**
 * @param {string} text     A raw expression.
 * @param {string} todayIso Today as `YYYY-MM-DD`.
 * @return {string|null} The ISO date clamped to 1900-01-01..2100-12-31, or null for an empty or invalid expression.
 */
export function resolveDateExpression( text, todayIso ) {
	const parsed = parseDateExpression( text );
	let iso;
	if ( parsed.kind === 'fixed' ) {
		iso = parsed.iso;
	} else if ( parsed.kind === 'offset' && splitIso( todayIso ?? '' ) ) {
		iso = applyOffset( todayIso, parsed );
	} else {
		return null;
	}
	return clampIso( iso, MIN_ISO, MAX_ISO );
}
