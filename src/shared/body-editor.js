import { TextareaControl } from '@wordpress/components';
import {
	forwardRef,
	useEffect,
	useImperativeHandle,
	useRef,
} from '@wordpress/element';
import { getEditorData } from './editor-data';

/**
 * HTML editor for a Body template: the core code editor (CodeMirror) when PHP
 * enqueued it, else a monospace textarea. `ref.insert( text )` replaces the
 * selection, so a placeholder lands at the cursor.
 *
 * The value is only read on mount; changes arrive through `onChange`.
 *
 * @param {Object}                  props          Component props.
 * @param {string}                  props.id       Id of the textarea; unique on the page.
 * @param {string}                  props.label    Accessible name.
 * @param {string}                  props.value    Initial text.
 * @param {(value: string) => void} props.onChange Called with the whole text after each edit.
 * @param {import('react').Ref}     ref            Receives `{ insert }`.
 */
export const BodyEditor = forwardRef( function BodyEditorField(
	{ id, label, value, onChange },
	ref
) {
	const codeEditorSettings = getEditorData( 'form' ).codeEditor;
	const useCodeEditor = !! ( codeEditorSettings && window.wp?.codeEditor );
	const container = useRef( null );
	const codeMirror = useRef( null );
	const pendingCaret = useRef( null );
	const latestOnChange = useRef( onChange );

	useEffect( () => {
		latestOnChange.current = onChange;
	}, [ onChange ] );

	useEffect( () => {
		if ( ! useCodeEditor ) {
			return undefined;
		}
		const editor = window.wp.codeEditor.initialize(
			container.current.querySelector( 'textarea' ),
			codeEditorSettings
		).codemirror;
		editor.getInputField().setAttribute( 'aria-label', label );
		editor.on( 'change', () =>
			latestOnChange.current( editor.getValue() )
		);
		codeMirror.current = editor;
		return () => {
			codeMirror.current = null;
			editor.toTextArea();
		};
	}, [ useCodeEditor, codeEditorSettings, label ] );

	useEffect( () => {
		if ( pendingCaret.current === null ) {
			return;
		}
		const textarea = container.current.querySelector( 'textarea' );
		textarea.focus();
		textarea.setSelectionRange(
			pendingCaret.current,
			pendingCaret.current
		);
		pendingCaret.current = null;
	}, [ value ] );

	useImperativeHandle(
		ref,
		() => ( {
			insert( text ) {
				if ( codeMirror.current ) {
					codeMirror.current.replaceSelection( text );
					codeMirror.current.focus();
					return;
				}
				const textarea = container.current.querySelector( 'textarea' );
				const { selectionStart, selectionEnd } = textarea;
				pendingCaret.current = selectionStart + text.length;
				latestOnChange.current(
					value.slice( 0, selectionStart ) +
						text +
						value.slice( selectionEnd )
				);
			},
		} ),
		[ value ]
	);

	return (
		<div ref={ container } className="jp-form-builder__body-editor">
			{ useCodeEditor ? (
				<>
					<label
						className="jp-form-builder__body-editor-label"
						htmlFor={ id }
					>
						{ label }
					</label>
					<textarea id={ id } defaultValue={ value } />
				</>
			) : (
				<TextareaControl
					label={ label }
					value={ value }
					onChange={ onChange }
					rows={ 14 }
					className="jp-form-builder__body-editor-fallback"
				/>
			) }
		</div>
	);
} );
