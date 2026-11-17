import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { isPlausiblePhoneNumber } from '../../src/blocks/embed/front/phone.js';

// PHP's PhoneNormalizerTest checks the same file: a number the server turns into E.164 passes here, the rest does not.
const fixtures = JSON.parse(
	readFileSync(
		new URL( '../fixtures/phone-numbers.json', import.meta.url ),
		'utf8'
	)
);

describe( 'isPlausiblePhoneNumber', () => {
	for ( const [ input, region, expected ] of fixtures ) {
		it( `${ expected === null ? 'rejects' : 'accepts' } ${ JSON.stringify(
			input
		) } (${ region })`, () => {
			assert.equal(
				isPlausiblePhoneNumber( input, region ),
				expected !== null
			);
		} );
	}
} );
