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

export function PreviewPart( { label, children } ) {
	return (
		<div className="jp-form-builder__part">
			<PreviewLabel>{ label }</PreviewLabel>
			<PreviewControl>{ children }</PreviewControl>
		</div>
	);
}
