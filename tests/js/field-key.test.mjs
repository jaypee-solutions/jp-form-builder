import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { normalizeFieldKey } from '../../src/shared/field-key.js';

// PHP's FieldKeyTest checks the same file, so the editor and the parser derive the same keys.
const fixtures = JSON.parse(
	readFileSync(
		new URL( '../fixtures/field-keys.json', import.meta.url ),
		'utf8'
	)
);

describe( 'normalizeFieldKey', () => {
	for ( const [ input, expected ] of fixtures ) {
		it( `turns ${ JSON.stringify( input ) } into ${ JSON.stringify(
			expected
		) }`, () => {
			assert.equal( normalizeFieldKey( input ), expected );
		} );
	}
} );
