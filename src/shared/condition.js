// Mirrors Condition_Evaluator in PHP, rule for rule (tests/fixtures/conditions.json checks both).
//
// A value is a string, a list of strings (Checkboxes) or an object of strings
// (a split Name). A Field without a value counts as empty. Values are the
// ones the server cleans: trimmed.

export const OPERATORS = [
	'is',
	'is_not',
	'is_one_of',
	'includes',
	'is_checked',
	'is_not_checked',
	'is_filled',
	'is_empty',
];

// The strings a value consists of; an empty string has none.
function partsOf( value ) {
	if ( typeof value === 'string' ) {
		return value === '' ? [] : [ value ];
	}
	return value && typeof value === 'object'
		? Object.values( value ).filter( ( part ) => typeof part === 'string' )
		: [];
}

function isFilled( value ) {
	return partsOf( value ).some( ( part ) => part.trim() !== '' );
}

/**
 * @param {{sourceField: string, operator: string, values: string[]}} condition The Condition, as in `data-condition`.
 * @param {Object<string, string|string[]|Object<string, string>>}    values    Field values keyed by Field key.
 * @return {boolean} Whether the Condition holds.
 */
export function isConditionMet( condition, values ) {
	const value = values[ condition.sourceField ] ?? '';
	const one = typeof value === 'string' ? value : '';
	const expected = condition.values;

	switch ( condition.operator ) {
		case 'is':
			return expected.length > 0 && one === expected[ 0 ];
		case 'is_not':
			return expected.length === 0 || one !== expected[ 0 ];
		case 'is_one_of':
			return expected.includes( one );
		case 'includes':
			return partsOf( value ).some( ( part ) =>
				expected.includes( part )
			);
		case 'is_checked':
			return one === '1';
		case 'is_not_checked':
			return one !== '1';
		case 'is_filled':
			return isFilled( value );
		case 'is_empty':
			return ! isFilled( value );
		default:
			return false;
	}
}

/**
 * Evaluates the groups in document order. The Fields of a hidden group count
 * as empty for later Conditions.
 *
 * @param {{condition: Object|null, fields: string[]}[]} groups Groups in document order; a `null` Condition is an invalid one, so the group is always shown.
 * @param {Object}                                       values Field values keyed by Field key.
 * @return {{shown: boolean[], hidden: string[], values: Object}} Visibility per group, keys of the hidden Fields, and the values without them.
 */
export function resolveGroups( groups, values ) {
	const effective = { ...values };
	const shown = [];
	const hidden = [];

	for ( const group of groups ) {
		const isShown =
			! group.condition || isConditionMet( group.condition, effective );
		shown.push( isShown );
		if ( ! isShown ) {
			for ( const key of group.fields ) {
				hidden.push( key );
				delete effective[ key ];
			}
		}
	}
	return { shown, hidden, values: effective };
}
