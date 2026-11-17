// Mirrors Field_Key::normalize() in PHP, rule for rule (tests/fixtures/field-keys.json checks both):
// lowercase; ä, ö, ü, ß become ae, oe, ue, ss; the accents of the Latin-1 Supplement and Latin
// Extended-A letters are removed; every other character outside a-z, 0-9 and `_` becomes `_`;
// leading and trailing underscores are trimmed.

const TRANSLITERATIONS = { ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' };

// The letters PHP folds with its own table: U+00C0 to U+017F.
const ACCENTED_LATIN = /[\u00c0-\u017f]/g;

/**
 * Applies every character rule except the trim, so a trailing underscore
 * survives while the user is still typing and the next character arrives.
 *
 * @param {string} value Raw input.
 * @return {string} Lowercase text with every character outside a-z0-9_ replaced by `_`.
 */
export function sanitizeFieldKeyInput( value ) {
	return value
		.toLowerCase()
		.replace( /[äöüß]/g, ( char ) => TRANSLITERATIONS[ char ] )
		.replace( ACCENTED_LATIN, ( char ) =>
			char.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
		)
		.replace( /[^a-z0-9_]/g, '_' );
}

/**
 * @param {string} value Raw key or label.
 * @return {string} The key the parser derives from it; empty when nothing usable is left.
 */
export function normalizeFieldKey( value ) {
	return sanitizeFieldKeyInput( value ).replace( /^_+|_+$/g, '' );
}

/**
 * Appends `_2`, `_3`, … like the parser until the key is free.
 *
 * @param {string}   base      Preferred key.
 * @param {string[]} takenKeys Keys of the other Fields in the Form.
 * @return {string} A key not contained in `takenKeys`.
 */
export function uniqueFieldKey( base, takenKeys ) {
	let key = base;
	let suffix = 2;
	while ( takenKeys.includes( key ) ) {
		key = `${ base }_${ suffix }`;
		suffix += 1;
	}
	return key;
}

/**
 * @param {string}   type      Field type, e.g. `email`.
 * @param {string}   label     The Field's label, possibly empty.
 * @param {string[]} takenKeys Keys of the other Fields in the Form.
 * @return {string} A unique key derived from the label, else from the type.
 */
export function newFieldKey( type, label, takenKeys ) {
	return uniqueFieldKey( normalizeFieldKey( label ) || type, takenKeys );
}
