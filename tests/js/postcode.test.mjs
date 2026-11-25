import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	isValidPostcode,
	postcodePattern,
} from '../../src/blocks/embed/front/postcode.js';

// PHP's FieldValidatorTest checks the same file, so the server and the browser agree.
const fixtures = JSON.parse(
	readFileSync(
		new URL( '../fixtures/postcodes.json', import.meta.url ),
		'utf8'
	)
);

// A Postcode control as Form_Renderer writes it when a Country Field is bound.
const bound = {
	dataset: {
		countrySource: 'land',
		postcodePatterns: JSON.stringify( fixtures.patterns ),
	},
	pattern: '',
};

// A Postcode control of a Form without a Country Field: the German rule as a `pattern` attribute.
const germanOnly = { dataset: {}, pattern: '^\\d{5}$' };

describe( 'isValidPostcode with a Country Field', () => {
	for ( const [ country, postcode, expected ] of fixtures.cases ) {
		it( `${ country || 'no country' } ${ postcode }`, () => {
			assert.equal(
				isValidPostcode( bound, postcode, () => country ),
				expected
			);
		} );
	}

	it( 'asks for the Country Field the markup names', () => {
		const asked = [];
		isValidPostcode( bound, '86150', ( key ) => {
			asked.push( key );
			return 'DE';
		} );
		assert.deepEqual( asked, [ 'land' ] );
	} );

	it( 'does not mistake a name from the prototype for a country', () => {
		assert.equal(
			postcodePattern( bound, () => 'constructor' ),
			fixtures.patterns.DE
		);
	} );
} );

describe( 'isValidPostcode without a Country Field', () => {
	it( 'applies the German pattern of the markup', () => {
		assert.equal( isValidPostcode( germanOnly, '86150', () => '' ), true );
		assert.equal( isValidPostcode( germanOnly, '1010', () => '' ), false );
		assert.equal( isValidPostcode( germanOnly, '8615A', () => '' ), false );
	} );

	it( 'accepts any value for a control without a rule', () => {
		assert.equal(
			isValidPostcode( { dataset: {} }, 'anything', () => '' ),
			true
		);
	} );
} );
