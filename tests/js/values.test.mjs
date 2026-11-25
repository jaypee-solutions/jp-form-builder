import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { isConditionMet } from '../../src/shared/condition.js';
import {
	choiceKind,
	cleanFieldValue,
} from '../../src/blocks/embed/front/values.js';

const fixtures = JSON.parse(
	readFileSync(
		new URL( '../fixtures/conditions.json', import.meta.url ),
		'utf8'
	)
);

const control = ( type, name, value, extra = {} ) => ( {
	type,
	name,
	value,
	checked: false,
	required: false,
	...extra,
} );

// Plain objects standing in for the DOM controls the renderer produces for a Field holding `value`.
function controlsHolding( key, value ) {
	if ( Array.isArray( value ) ) {
		return [
			...value.map( ( item ) =>
				control( 'checkbox', `${ key }[]`, item, { checked: true } )
			),
			control( 'checkbox', `${ key }[]`, 'unticked' ),
		];
	}
	if ( typeof value === 'object' ) {
		return Object.entries( value ).map( ( [ subKey, part ] ) =>
			control( 'text', `${ key }[${ subKey }]`, part )
		);
	}
	if ( key === 'radio' ) {
		return [
			control( 'radio', key, value, { checked: value !== '' } ),
			control( 'radio', key, 'other' ),
		];
	}
	if ( key === 'country' ) {
		return [ control( 'select', key, value ) ];
	}
	if ( key === 'consent' ) {
		return [ control( 'checkbox', key, '1', { checked: value === '1' } ) ];
	}
	return [ control( 'text', key, value ) ];
}

describe( 'cleaned Field values against the Condition fixtures', () => {
	for ( const testCase of fixtures.conditions ) {
		it( testCase.name, () => {
			const cleaned = cleanFieldValue(
				testCase.source,
				controlsHolding( testCase.source, testCase.value )
			);
			const condition = {
				sourceField: testCase.source,
				operator: testCase.operator,
				values: testCase.values,
			};
			assert.equal(
				isConditionMet( condition, { [ testCase.source ]: cleaned } ),
				testCase.expected
			);
		} );
	}
} );

describe( 'cleanFieldValue', () => {
	it( 'reads Radio buttons as the checked value, or an empty string', () => {
		const radios = [
			control( 'radio', 'topic', 'sales' ),
			control( 'radio', 'topic', 'support', { checked: true } ),
		];
		assert.equal( choiceKind( radios ), 'radio' );
		assert.equal( cleanFieldValue( 'topic', radios ), 'support' );
		radios[ 1 ].checked = false;
		assert.equal( cleanFieldValue( 'topic', radios ), '' );
	} );

	it( 'reads Checkboxes as the list of checked values in option order', () => {
		const boxes = [
			control( 'checkbox', 'interests[]', 'a', { checked: true } ),
			control( 'checkbox', 'interests[]', 'b' ),
			control( 'checkbox', 'interests[]', 'c', { checked: true } ),
		];
		assert.equal( choiceKind( boxes ), 'checkbox' );
		assert.deepEqual( cleanFieldValue( 'interests', boxes ), [ 'a', 'c' ] );
		boxes[ 0 ].checked = false;
		boxes[ 2 ].checked = false;
		assert.deepEqual( cleanFieldValue( 'interests', boxes ), [] );
	} );

	it( 'reads a consent checkbox as 1 or an empty string', () => {
		const consent = control( 'checkbox', 'privacy', '1' );
		assert.equal( choiceKind( [ consent ] ), '' );
		assert.equal( cleanFieldValue( 'privacy', [ consent ] ), '' );
		consent.checked = true;
		assert.equal( cleanFieldValue( 'privacy', [ consent ] ), '1' );
	} );

	it( 'trims text values', () => {
		assert.equal(
			cleanFieldValue( 'name', [
				control( 'text', 'name', '  Ada \n' ),
			] ),
			'Ada'
		);
	} );

	it( 'reads a compound Field as its trimmed sub-key map', () => {
		const parts = [
			control( 'text', 'name[first]', ' Ada ' ),
			control( 'text', 'name[last]', '' ),
		];
		assert.deepEqual( cleanFieldValue( 'name', parts ), {
			first: 'Ada',
			last: '',
		} );
	} );

	describe( 'a date Field', () => {
		const dateControl = ( value, format ) =>
			control( 'text', 'when', value, {
				dataset: { dateFormat: format },
			} );

		it( 'is sent as ISO from the German display format', () => {
			assert.equal(
				cleanFieldValue( 'when', [
					dateControl( ' 5.3.2026 ', 'dmy' ),
				] ),
				'2026-03-05'
			);
		} );

		it( 'is sent unchanged from the ISO display format', () => {
			assert.equal(
				cleanFieldValue( 'when', [
					dateControl( '2026-03-05', 'iso' ),
				] ),
				'2026-03-05'
			);
		} );

		it( 'sends unparseable text as typed, so the server reports it', () => {
			assert.equal(
				cleanFieldValue( 'when', [
					dateControl( '31.02.2026', 'dmy' ),
				] ),
				'31.02.2026'
			);
			assert.equal(
				cleanFieldValue( 'when', [ dateControl( 'soon', 'iso' ) ] ),
				'soon'
			);
		} );

		it( 'stays empty when empty', () => {
			assert.equal(
				cleanFieldValue( 'when', [ dateControl( '  ', 'dmy' ) ] ),
				''
			);
		} );
	} );
	it( 'reads a Field without controls as empty', () => {
		assert.equal( cleanFieldValue( 'ghost', [] ), '' );
	} );
} );
