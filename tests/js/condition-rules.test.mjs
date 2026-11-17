import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	conditionForSource,
	isConditionComplete,
	operatorsForType,
	valuesForOperator,
} from '../../src/shared/condition-rules.js';
import { OPERATORS } from '../../src/shared/condition.js';

const options = [
	{ label: 'Silver', value: 'silver' },
	{ label: 'Gold', value: 'gold' },
];

describe( 'operatorsForType', () => {
	it( 'offers the operators of each Field type', () => {
		assert.deepEqual( operatorsForType( 'select' ), [
			'is',
			'is_not',
			'is_one_of',
		] );
		assert.deepEqual(
			operatorsForType( 'radio' ),
			operatorsForType( 'select' )
		);
		assert.deepEqual( operatorsForType( 'checkbox' ), [
			'includes',
			'is_filled',
			'is_empty',
		] );
		assert.deepEqual( operatorsForType( 'consent' ), [
			'is_checked',
			'is_not_checked',
		] );
		for ( const type of [
			'name',
			'email',
			'phone',
			'address',
			'text',
			'textarea',
		] ) {
			assert.deepEqual( operatorsForType( type ), [
				'is_filled',
				'is_empty',
			] );
		}
	} );

	it( 'only offers operators the evaluator knows', () => {
		for ( const type of [ 'select', 'checkbox', 'consent', 'text' ] ) {
			for ( const operator of operatorsForType( type ) ) {
				assert.ok( OPERATORS.includes( operator ) );
			}
		}
	} );
} );

describe( 'conditionForSource', () => {
	it( 'starts with the first operator of the source and no values', () => {
		assert.deepEqual(
			conditionForSource( { key: 'plan', type: 'select' } ),
			{
				sourceField: 'plan',
				operator: 'is',
				values: [],
			}
		);
		assert.deepEqual(
			conditionForSource( { key: 'news', type: 'consent' } ),
			{
				sourceField: 'news',
				operator: 'is_checked',
				values: [],
			}
		);
	} );
} );

describe( 'valuesForOperator', () => {
	it( 'keeps one value for a single-value operator', () => {
		assert.deepEqual(
			valuesForOperator( 'is', [ 'silver', 'gold' ], options ),
			[ 'silver' ]
		);
	} );

	it( 'keeps every listed option for a multiple-value operator', () => {
		assert.deepEqual(
			valuesForOperator( 'is_one_of', [ 'silver', 'gold' ], options ),
			[ 'silver', 'gold' ]
		);
	} );

	it( 'drops values the source does not offer and values of operators without any', () => {
		assert.deepEqual(
			valuesForOperator( 'includes', [ 'gone', 'gold' ], options ),
			[ 'gold' ]
		);
		assert.deepEqual(
			valuesForOperator( 'is_filled', [ 'gold' ], options ),
			[]
		);
	} );
} );

describe( 'isConditionComplete', () => {
	const select = { type: 'select' };

	it( 'needs a source before the group', () => {
		assert.equal(
			isConditionComplete(
				{ operator: 'is', values: [ 'gold' ] },
				undefined
			),
			false
		);
	} );

	it( 'needs values for an operator that takes them', () => {
		assert.equal(
			isConditionComplete( { operator: 'is', values: [] }, select ),
			false
		);
		assert.equal(
			isConditionComplete(
				{ operator: 'is', values: [ 'gold' ] },
				select
			),
			true
		);
	} );

	it( 'needs an operator that fits the source', () => {
		assert.equal(
			isConditionComplete(
				{ operator: 'is_checked', values: [] },
				select
			),
			false
		);
		assert.equal(
			isConditionComplete(
				{ operator: 'is_filled', values: [] },
				{ type: 'text' }
			),
			true
		);
	} );
} );
