const ADDRESS_PATTERN = /^[^\s@<>,;"()]+@[^\s@<>,;"()]+\.[^\s@<>,;"()]{2,}$/;

/**
 * @param {string} value Comma-separated list of addresses.
 * @return {{valid: string[], invalid: string[]}} The entries split by plausibility.
 */
export function splitRecipients( value ) {
	const entries = value
		.split( ',' )
		.map( ( entry ) => entry.trim() )
		.filter( Boolean );
	return {
		valid: entries.filter( ( entry ) => ADDRESS_PATTERN.test( entry ) ),
		invalid: entries.filter( ( entry ) => ! ADDRESS_PATTERN.test( entry ) ),
	};
}
