import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	checkDateText,
	nativePickerValue,
	resolveDateWindow,
} from '../../src/blocks/embed/front/date-window.js';

const TODAY = '2026-11-25';

describe( 'resolveDateWindow', () => {
	it( 'resolves offsets with the site date', () => {
		assert.deepEqual(
			resolveDateWindow(
				{ earliest: 'today', latest: '+1y', openingDate: '+30d' },
				TODAY
			),
			{ earliest: TODAY, latest: '2027-11-25', opening: '2026-12-25' }
		);
	} );

	it( 'enforces only fixed dates until the site date is known', () => {
		assert.deepEqual(
			resolveDateWindow(
				{ earliest: '2026-01-01', latest: '+1y', openingDate: 'today' },
				null
			),
			{ earliest: '2026-01-01', latest: null, opening: null }
		);
	} );

	it( 'clamps the Opening date into the window and ignores invalid expressions', () => {
		assert.equal(
			resolveDateWindow(
				{ earliest: '2026-12-01', openingDate: '2026-01-01' },
				TODAY
			).opening,
			'2026-12-01'
		);
		assert.deepEqual(
			resolveDateWindow( { earliest: 'junk', openingDate: 'x' }, TODAY ),
			{
				earliest: null,
				latest: null,
				opening: null,
			}
		);
	} );
} );

describe( 'checkDateText', () => {
	const window = { earliest: '2026-01-10', latest: '2026-01-20' };

	it( 'reports every status', () => {
		assert.equal( checkDateText( ' ', 'iso', window ).status, 'empty' );
		assert.equal( checkDateText( 'x', 'iso', window ).status, 'invalid' );
		assert.deepEqual( checkDateText( '2026-01-09', 'iso', window ), {
			status: 'before',
			iso: '2026-01-09',
			limit: '2026-01-10',
		} );
		assert.equal(
			checkDateText( '21.1.2026', 'dmy', window ).status,
			'after'
		);
		assert.deepEqual( checkDateText( '15.01.2026', 'dmy', window ), {
			status: 'ok',
			iso: '2026-01-15',
		} );
	} );

	it( 'reports the violated bound of an inverted window', () => {
		const inverted = { earliest: '2026-01-20', latest: '2026-01-10' };
		assert.equal(
			checkDateText( '2026-01-15', 'iso', inverted ).status,
			'before'
		);
		assert.equal(
			checkDateText( '2026-01-25', 'iso', inverted ).status,
			'after'
		);
	} );
} );

describe( 'nativePickerValue', () => {
	it( 'opens on the Opening date only while the field is empty', () => {
		const window = { opening: '2026-12-25' };
		assert.equal( nativePickerValue( '', 'iso', window ), '2026-12-25' );
		assert.equal(
			nativePickerValue( '5.3.2026', 'dmy', window ),
			'2026-03-05'
		);
		assert.equal( nativePickerValue( 'junk', 'dmy', window ), '' );
		assert.equal( nativePickerValue( '', 'iso', { opening: null } ), '' );
	} );
} );
