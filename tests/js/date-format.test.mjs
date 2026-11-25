import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	dateFormatFor,
	formatDate,
	isFullLength,
	parseDate,
	placeholderFor,
} from '../../src/shared/date-format.js';

describe( 'date-format', () => {
	it( 'picks the format from the locale', () => {
		assert.equal( dateFormatFor( 'de_DE' ), 'dmy' );
		assert.equal( dateFormatFor( 'de' ), 'dmy' );
		assert.equal( dateFormatFor( 'en_US' ), 'iso' );
		assert.equal( dateFormatFor( '' ), 'iso' );
	} );

	it( 'has a placeholder per format', () => {
		assert.equal( placeholderFor( 'dmy' ), 'TT.MM.JJJJ' );
		assert.equal( placeholderFor( 'iso' ), 'YYYY-MM-DD' );
	} );

	it( 'formats ISO dates', () => {
		assert.equal( formatDate( '2026-03-05', 'dmy' ), '05.03.2026' );
		assert.equal( formatDate( '2026-03-05', 'iso' ), '2026-03-05' );
	} );

	it( 'parses German dates strictly', () => {
		assert.equal( parseDate( '05.03.2026', 'dmy' ), '2026-03-05' );
		assert.equal( parseDate( '5.3.2026', 'dmy' ), '2026-03-05' );
		assert.equal( parseDate( ' 5.3.2026 ', 'dmy' ), '2026-03-05' );
		for ( const bad of [
			'5.3.26',
			'31.02.2026',
			'2026-03-05',
			'1.1.1899',
			'1.1.2101',
			'',
		] ) {
			assert.equal( parseDate( bad, 'dmy' ), null, bad );
		}
	} );

	it( 'parses ISO dates strictly', () => {
		assert.equal( parseDate( '2026-03-05', 'iso' ), '2026-03-05' );
		for ( const bad of [
			'2026-3-5',
			'05.03.2026',
			'2026-02-30',
			'1899-01-01',
		] ) {
			assert.equal( parseDate( bad, 'iso' ), null, bad );
		}
	} );

	it( 'detects full length', () => {
		assert.equal( isFullLength( '05.03.2026', 'dmy' ), true );
		assert.equal( isFullLength( '5.3.2026', 'dmy' ), true );
		assert.equal( isFullLength( '5.3.202', 'dmy' ), false );
		assert.equal( isFullLength( '2026-03-0', 'iso' ), false );
		assert.equal( isFullLength( '2026-03-05', 'iso' ), true );
		assert.equal( isFullLength( '5.3.2026', 'iso' ), false );
	} );
} );
