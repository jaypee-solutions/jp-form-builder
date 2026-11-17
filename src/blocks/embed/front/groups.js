import { resolveGroups } from '../../../shared/condition';
import { fieldWrappers, readValues } from './dom';
import { clearGroupErrors } from './errors';

// A group without a usable Condition is always shown, like an invalid Condition on the server.
function conditionOf( element ) {
	try {
		return JSON.parse( element.dataset.condition ?? 'null' );
	} catch {
		return null;
	}
}

/**
 * Shows and hides a Form's Conditional groups while the visitor fills it in.
 *
 * @param {HTMLFormElement} form The Form.
 * @return {{refresh: () => void}} `refresh` evaluates every Condition and toggles `hidden`; it runs once now and after every `input` and `change`.
 */
export function initGroups( form ) {
	const groups = [
		...form.querySelectorAll( '.jp-form-builder__group' ),
	].map( ( element ) => ( {
		element,
		condition: conditionOf( element ),
		fields: fieldWrappers( element ).map(
			( wrapper ) => wrapper.dataset.fieldKey
		),
	} ) );

	function refresh() {
		const { shown } = resolveGroups( groups, readValues( form ) );
		groups.forEach( ( { element }, index ) => {
			const wasHidden = element.hidden;
			element.hidden = ! shown[ index ];
			if ( element.hidden && ! wasHidden ) {
				clearGroupErrors( form, element );
			}
		} );
	}

	if ( groups.length > 0 ) {
		form.addEventListener( 'input', refresh );
		form.addEventListener( 'change', refresh );
		refresh();
	}
	return { refresh: groups.length > 0 ? refresh : () => {} };
}
