// Which operators a Condition may use on which kind of Field, and how its values follow the choice.
// Operators that take values: one for `is` and `is_not`, several for `is_one_of` and `includes`.

const SINGLE_VALUE_OPERATORS = [ 'is', 'is_not' ];
const MULTIPLE_VALUE_OPERATORS = [ 'is_one_of', 'includes' ];

/**
 * @param {string} type Field type, e.g. `select`.
 * @return {string[]} The operators the editor offers for a Field of this type.
 */
export function operatorsForType( type ) {
	switch ( type ) {
		case 'select':
		case 'radio':
			return [ 'is', 'is_not', 'is_one_of' ];
		case 'checkbox':
			return [ 'includes', 'is_filled', 'is_empty' ];
		case 'consent':
			return [ 'is_checked', 'is_not_checked' ];
		default:
			return [ 'is_filled', 'is_empty' ];
	}
}

export const takesSingleValue = ( operator ) =>
	SINGLE_VALUE_OPERATORS.includes( operator );

export const takesMultipleValues = ( operator ) =>
	MULTIPLE_VALUE_OPERATORS.includes( operator );

/**
 * The Condition after another source Field was chosen: the source's first
 * operator and no values, because the old ones may not fit.
 *
 * @param {{key: string, type: string}|undefined} source The chosen Field.
 * @return {{sourceField: string, operator: string, values: string[]}} The reset Condition.
 */
export function conditionForSource( source ) {
	return {
		sourceField: source?.key ?? '',
		operator: operatorsForType( source?.type )[ 0 ],
		values: [],
	};
}

/**
 * The values that still make sense for an operator: at most one for a
 * single-value operator, only listed options, none for operators without values.
 *
 * @param {string}   operator The operator.
 * @param {string[]} values   The values chosen so far.
 * @param {Object[]} options  The source's `{ label, value }` options.
 * @return {string[]} The values to keep.
 */
export function valuesForOperator( operator, values, options ) {
	const offered = values.filter( ( value ) =>
		options.some( ( option ) => option.value === value )
	);
	if ( takesSingleValue( operator ) ) {
		return offered.slice( 0, 1 );
	}
	return takesMultipleValues( operator ) ? offered : [];
}

/**
 * @param {{operator: string, values: string[]}} condition The group's Condition.
 * @param {{type: string}|undefined}             source    The Field it looks at, when that comes before the group.
 * @return {boolean} Whether the Condition can ever decide: the source exists before the group, the operator fits it and the values it needs are chosen.
 */
export function isConditionComplete( condition, source ) {
	if (
		! source ||
		! operatorsForType( source.type ).includes( condition.operator )
	) {
		return false;
	}
	const needsValues =
		takesSingleValue( condition.operator ) ||
		takesMultipleValues( condition.operator );
	return ! needsValues || condition.values.length > 0;
}
