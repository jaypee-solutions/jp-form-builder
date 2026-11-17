/**
 * Non-interactive stand-ins for the front-end markup of Form_Renderer.
 */

export function PreviewLabel( { children, required } ) {
	return (
		<span className="jp-form-builder__label">
			{ children }
			{ required && (
				<>
					{ ' ' }
					<span
						className="jp-form-builder__required"
						aria-hidden="true"
					>
						*
					</span>
				</>
			) }
		</span>
	);
}

export function PreviewControl( { children, variant } ) {
	const classes = [ 'jp-form-builder__control' ];
	if ( variant ) {
		classes.push( `jp-form-builder__control--${ variant }` );
	}
	return (
		<div className={ classes.join( ' ' ) }>{ children || '\u00a0' }</div>
	);
}

export function PreviewHelp( { text } ) {
	return text ? <p className="jp-form-builder__help">{ text }</p> : null;
}

export function PreviewGroup( { legend, required, children } ) {
	return (
		<fieldset className="jp-form-builder__group">
			<legend>
				<PreviewLabel required={ required }>{ legend }</PreviewLabel>
			</legend>
			{ children }
		</fieldset>
	);
}

/**
 * Non-interactive option list of a Radio buttons or Checkboxes Field.
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.variant `radio` or `checkbox`.
 * @param {Object[]} props.options The Field's `{ label, value }` options.
 */
export function PreviewChoices( { variant, options } ) {
	return (
		<div className="jp-form-builder__choices">
			{ options.map( ( option, index ) => (
				<div
					className={ `jp-form-builder__choice jp-form-builder__choice--${ variant }` }
					// Options have no stable id and are edited in place.

					key={ index }
				>
					<span
						className="jp-form-builder__choice-input"
						aria-hidden="true"
					/>
					{ option.label || option.value }
				</div>
			) ) }
		</div>
	);
}

export function PreviewPart( { label, children } ) {
	return (
		<div className="jp-form-builder__part">
			<PreviewLabel>{ label }</PreviewLabel>
			<PreviewControl>{ children }</PreviewControl>
		</div>
	);
}
