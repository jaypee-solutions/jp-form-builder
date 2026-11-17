import { useId } from '@wordpress/element';
import { defaultLabel, defaultSubmitLabel } from '../../shared';
import { fieldWidthClassName } from '../../shared/field-width';

/**
 * Non-interactive stand-in with the front-end markup classes, shown where the block is previewed
 * (style variations, inserter) and no Form is chosen, so the previews show the design.
 */
export default function DemoForm() {
	const id = useId();
	const fields = [
		{ type: 'name', width: '50' },
		{ type: 'email', width: '50' },
		{ type: 'textarea', width: '100' },
	];

	return (
		<form className="jp-form-builder" aria-hidden="true">
			<div className="jp-form-builder__fields">
				{ fields.map( ( { type, width } ) => (
					<div
						key={ type }
						className={ `jp-form-builder__field jp-form-builder__field--${ type } ${ fieldWidthClassName(
							type,
							width
						) }` }
					>
						<label htmlFor={ `${ id }-${ type }` }>
							{ defaultLabel( type ) }
						</label>
						{ type === 'textarea' ? (
							<textarea
								id={ `${ id }-${ type }` }
								rows="3"
								tabIndex={ -1 }
								readOnly
							/>
						) : (
							<input
								id={ `${ id }-${ type }` }
								type="text"
								tabIndex={ -1 }
								readOnly
							/>
						) }
					</div>
				) ) }
			</div>
			<button type="button" className="wp-element-button" tabIndex={ -1 }>
				{ defaultSubmitLabel() }
			</button>
		</form>
	);
}
