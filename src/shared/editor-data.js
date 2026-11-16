/**
 * Reads the data PHP hands to one block's editor script (see Blocks::enqueue_editor_data()).
 *
 * @param {string} block Block slug without the namespace, e.g. `address`.
 * @return {Object} The data, or an empty object when PHP provided none.
 */
export function getEditorData( block ) {
	return window.jpFormBuilderEditor?.[ block ] ?? {};
}
