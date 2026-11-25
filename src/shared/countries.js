import { getEditorData } from './editor-data';

/**
 * @param {Object<string, string>} countries Translated country names keyed by code.
 * @return {{value: string, label: string}[]} The countries as select options, sorted by name.
 */
export function countryOptions( countries ) {
	return Object.entries( countries )
		.map( ( [ value, label ] ) => ( { value, label } ) )
		.sort( ( a, b ) => a.label.localeCompare( b.label ) );
}

/**
 * @return {{value: string, label: string}[]} The choices of a Country Field, as PHP hands them to the editor.
 */
export function editorCountryOptions() {
	return countryOptions( getEditorData( 'country' ).countries ?? {} );
}
