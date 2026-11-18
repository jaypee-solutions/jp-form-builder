import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	isConditionMet,
	OPERATORS,
	resolveGroups,
} from '../../src/shared/condition.js';

// PHP's ConditionEvaluatorTest checks the same file, so the server and the browser agree.
const fixtures = JSON.parse(
	readFileSync(
		new URL( '../fixtures/conditions.json', import.meta.url ),
		'utf8'
	)
);

describe( 'isConditionMet', () => {
	for ( const testCase of fixtures.conditions ) {
		it( testCase.name, () => {
			const condition = {
				sourceField: 'source',
				operator: testCase.operator,
				values: testCase.values,
			};
			assert.equal(
				isConditionMet( condition, { source: testCase.value } ),
				testCase.expected
			);
		} );
	}

	it( 'is covered for every operator', () => {
		const covered = new Set(
			fixtures.conditions.map( ( testCase ) => testCase.operator )
		);
		assert.deepEqual( [ ...covered ].sort(), [ ...OPERATORS ].sort() );
	} );
} );

describe( 'resolveGroups', () => {
	for ( const testCase of fixtures.groups ) {
		it( testCase.name, () => {
			const result = resolveGroups( testCase.groups, testCase.values );
			assert.deepEqual( result.shown, testCase.shown );
			assert.deepEqual( result.hidden, testCase.hidden );
			assert.deepEqual( result.values, testCase.visibleValues );
		} );
	}

	it( 'leaves the given values untouched', () => {
		const values = { topic: 'support', budget: '1' };
		resolveGroups(
			[
				{
					condition: {
						sourceField: 'topic',
						operator: 'is',
						values: [ 'sales' ],
					},
					fields: [ 'budget' ],
				},
			],
			values
		);
		assert.deepEqual( values, { topic: 'support', budget: '1' } );
	} );
} );
