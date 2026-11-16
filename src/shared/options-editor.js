import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Editor for a Select's label/value options: add, edit, remove and reorder.
 *
 * @param {Object}                      props          Component props.
 * @param {Object[]}                    props.options  Current `{ label, value }` options.
 * @param {(options: Object[]) => void} props.onChange Receives the new options array.
 */
export function OptionsEditor( { options, onChange } ) {
	const replaceAt = ( index, changes ) =>
		onChange(
			options.map( ( option, position ) =>
				position === index ? { ...option, ...changes } : option
			)
		);

	const editLabel = ( index, label ) => {
		const option = options[ index ];
		// The value follows the label until the author sets it separately.
		const followsLabel =
			option.value === '' || option.value === option.label;
		replaceAt( index, {
			label,
			value: followsLabel ? label : option.value,
		} );
	};

	const move = ( index, offset ) => {
		const next = [ ...options ];
		[ next[ index ], next[ index + offset ] ] = [
			next[ index + offset ],
			next[ index ],
		];
		onChange( next );
	};

	return (
		<fieldset className="jp-form-builder__options-editor">
			<legend>{ __( 'Options', 'jp-form-builder' ) }</legend>
			{ options.map( ( option, index ) => (
				<div
					className="jp-form-builder__option"
					// Options have no stable id and are edited in place.

					key={ index }
				>
					<TextControl
						label={ sprintf(
							/* translators: %d: position of the option in the list. */
							__( 'Option %d label', 'jp-form-builder' ),
							index + 1
						) }
						value={ option.label }
						onChange={ ( label ) => editLabel( index, label ) }
					/>
					<TextControl
						label={ sprintf(
							/* translators: %d: position of the option in the list. */
							__( 'Option %d value', 'jp-form-builder' ),
							index + 1
						) }
						value={ option.value }
						onChange={ ( value ) => replaceAt( index, { value } ) }
					/>
					<div className="jp-form-builder__option-actions">
						<Button
							variant="tertiary"
							size="small"
							disabled={ index === 0 }
							accessibleWhenDisabled
							aria-label={ sprintf(
								/* translators: %s: option label. */
								__( 'Move up: %s', 'jp-form-builder' ),
								option.label
							) }
							onClick={ () => move( index, -1 ) }
						>
							{ __( 'Move up', 'jp-form-builder' ) }
						</Button>
						<Button
							variant="tertiary"
							size="small"
							disabled={ index === options.length - 1 }
							accessibleWhenDisabled
							aria-label={ sprintf(
								/* translators: %s: option label. */
								__( 'Move down: %s', 'jp-form-builder' ),
								option.label
							) }
							onClick={ () => move( index, 1 ) }
						>
							{ __( 'Move down', 'jp-form-builder' ) }
						</Button>
						<Button
							variant="tertiary"
							size="small"
							isDestructive
							aria-label={ sprintf(
								/* translators: %s: option label. */
								__( 'Remove: %s', 'jp-form-builder' ),
								option.label
							) }
							onClick={ () =>
								onChange(
									options.filter(
										( _, position ) => position !== index
									)
								)
							}
						>
							{ __( 'Remove', 'jp-form-builder' ) }
						</Button>
					</div>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [ ...options, { label: '', value: '' } ] )
				}
			>
				{ __( 'Add option', 'jp-form-builder' ) }
			</Button>
		</fieldset>
	);
}
