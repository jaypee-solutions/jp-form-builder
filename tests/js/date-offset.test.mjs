import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	clampIso,
	isValidIsoDate,
	parseDateExpression,
	resolveDateExpression,
} from '../../src/shared/date-offset.js';

const fixtures = JSON.parse(
	readFileSync(
		new URL( '../fixtures/date-offsets.json', import.meta.url ),
		'utf8'
	)
);

describe( 'resolveDateExpression against the shared fixtures', () => {
	for ( const { today, expression, resolved } of fixtures ) {
		it( `${ JSON.stringify( expression ) } on ${ today }`, () => {
			assert.equal(
				resolveDateExpression( expression, today ),
				resolved
			);
		} );
	}
} );

describe( 'parseDateExpression', () => {
	it( 'classifies empty, fixed, offset and invalid', () => {
		assert.deepEqual( parseDateExpression( '  ' ), { kind: 'empty' } );
		assert.deepEqual( parseDateExpression( '2026-02-28' ), {
			kind: 'fixed',
			iso: '2026-02-28',
		} );
		assert.deepEqual( parseDateExpression( '2026-02-30' ), {
			kind: 'invalid',
		} );
		assert.deepEqual( parseDateExpression( ' Today ' ), {
			kind: 'offset',
			amount: 0,
			unit: 'd',
		} );
		assert.deepEqual( parseDateExpression( '-1Y' ), {
			kind: 'offset',
			amount: -1,
			unit: 'y',
		} );
		assert.deepEqual( parseDateExpression( '6m' ), {
			kind: 'offset',
			amount: 6,
			unit: 'm',
		} );
		for ( const junk of [ '+1d+1w', '1000d', '1.5d', '+d', 'soon' ] ) {
			assert.deepEqual( parseDateExpression( junk ), {
				kind: 'invalid',
			} );
		}
	} );
} );

describe( 'isValidIsoDate', () => {
	it( 'accepts real dates within 1900..2100 only', () => {
		assert.equal( isValidIsoDate( '2024-02-29' ), true );
		assert.equal( isValidIsoDate( '2025-02-29' ), false );
		assert.equal( isValidIsoDate( '1899-12-31' ), false );
		assert.equal( isValidIsoDate( '2101-01-01' ), false );
		assert.equal( isValidIsoDate( '2026-13-01' ), false );
		assert.equal( isValidIsoDate( '2026-1-01' ), false );
	} );
} );

describe( 'clampIso', () => {
	it( 'moves a date into the bounds', () => {
		assert.equal(
			clampIso( '2026-01-01', '2026-02-01', null ),
			'2026-02-01'
		);
		assert.equal(
			clampIso( '2026-03-01', null, '2026-02-01' ),
			'2026-02-01'
		);
		assert.equal(
			clampIso( '2026-01-15', '2026-01-01', '2026-02-01' ),
			'2026-01-15'
		);
	} );
} );
