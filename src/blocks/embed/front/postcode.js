// The rule of a Postcode Field. Form_Renderer writes the Country Field's key (`data-country-source`) and every
// Country's pattern (`data-postcode-patterns`) into the markup; the server alone resolves which Country Field that is
// (ADR 0018). Without a Country Field the markup carries the German `pattern` instead.

const FALLBACK_COUNTRY = 'DE';

/**
 * @param {{dataset: Object<string,string>, pattern?: string}} control   The Postcode control.
 * @param {(fieldKey: string) => string}                       countryOf The code the Country Field of that key holds, or '' when it is empty or hidden.
 * @return {string} The pattern the value must match, or '' when the control carries no rule.
 */
export function postcodePattern( control, countryOf ) {
	const { countrySource, postcodePatterns } = control.dataset;
	if ( ! postcodePatterns ) {
		return control.pattern || '';
	}
	const patterns = JSON.parse( postcodePatterns );
	const country = countryOf( countrySource );
	return Object.hasOwn( patterns, country )
		? patterns[ country ]
		: patterns[ FALLBACK_COUNTRY ];
}

/**
 * @param {{dataset: Object<string,string>, pattern?: string}} control   The Postcode control.
 * @param {string}                                             value     The trimmed, non-empty value.
 * @param {(fieldKey: string) => string}                       countryOf See postcodePattern().
 * @return {boolean} Whether the value fits the rule of the Postcode.
 */
export function isValidPostcode( control, value, countryOf ) {
	const pattern = postcodePattern( control, countryOf );
	return pattern === '' || new RegExp( pattern ).test( value );
}
