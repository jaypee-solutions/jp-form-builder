import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	browserTodayIso,
	expressionStatus,
	windowStatus,
} from '../../src/shared/date-window-status.js';

const TODAY = '2026-11-25';

describe( 'browserTodayIso', () => {
	it( 'pads month and day', () => {
		assert.equal( browserTodayIso( new Date( 2026, 0, 5 ) ), '2026-01-05' );
	} );
} );

describe( 'expressionStatus', () => {
	it( 'is empty for blank text', () => {
		assert.equal( expressionStatus( '  ', TODAY, 'iso' ).state, 'empty' );
	} );

	it( 'resolves offsets in the display format', () => {
		assert.deepEqual( expressionStatus( '-1y', TODAY, 'dmy' ), {
			state: 'resolved',
			iso: '2025-11-25',
			display: '25.11.2025',
		} );
	} );

	it( 'resolves a fixed date', () => {
		assert.equal(
			expressionStatus( '2027-01-02', TODAY, 'iso' ).display,
			'2027-01-02'
		);
	} );

	it( 'flags junk and impossible dates', () => {
		assert.equal(
			expressionStatus( 'soon', TODAY, 'iso' ).state,
			'invalid'
		);
		assert.equal(
			expressionStatus( '2026-02-30', TODAY, 'iso' ).state,
			'invalid'
		);
	} );
} );

describe( 'windowStatus', () => {
	it( 'warns when earliest is after latest', () => {
		const status = windowStatus(
			{ earliest: '+30d', latest: 'now' },
			TODAY,
			'iso'
		);
		assert.equal( status.inverted, true );
	} );

	it( 'does not warn for an ordered, open or invalid window', () => {
		for ( const [ earliest, latest ] of [
			[ 'now', '+1d' ],
			[ 'now', '' ],
			[ 'x', 'now' ],
			[ 'now', 'now' ],
		] ) {
			assert.equal(
				windowStatus( { earliest, latest }, TODAY, 'iso' ).inverted,
				false
			);
		}
	} );
} );
