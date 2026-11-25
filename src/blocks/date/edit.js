import { getSettings } from '@wordpress/date';
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	FieldBlock,
	PreviewControl,
	PreviewHelp,
	PreviewLabel,
	defaultLabel,
} from '../../shared';
import { dateFormatFor, placeholderFor } from '../../shared/date-format';
import { browserTodayIso, windowStatus } from '../../shared/date-window-status';

function CalendarIcon() {
	return (
		<svg
			className="jp-form-builder__date-icon"
			viewBox="0 0 24 24"
			width="20"
			height="20"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fill="currentColor"
				d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14v10Z"
			/>
		</svg>
	);
}

// Help under a date input: the resolved date, or an inline error for an invalid expression.
function boundHelp( status ) {
	if ( status.state === 'invalid' ) {
		return (
			<span className="jp-form-builder__date-error">
				{ __(
					'Invalid date or offset. Use YYYY-MM-DD or an offset such as now, -1y, +30d, +2w, 6m.',
					'jp-form-builder'
				) }
			</span>
		);
	}
	if ( status.state === 'resolved' ) {
		return sprintf(
			/* translators: %s: a date in the display format */
			__( 'Resolves to %s today.', 'jp-form-builder' ),
			status.display
		);
	}
	return __( 'Empty: no limit.', 'jp-form-builder' );
}

function windowSummary( status ) {
	const { earliest, latest } = status;
	if ( earliest.state === 'resolved' && latest.state === 'resolved' ) {
		return sprintf(
			/* translators: 1: earliest date, 2: latest date */
			__( '%1$s to %2$s', 'jp-form-builder' ),
			earliest.display,
			latest.display
		);
	}
	if ( earliest.state === 'resolved' ) {
		/* translators: %s: a date */
		return sprintf( __( 'From %s', 'jp-form-builder' ), earliest.display );
	}
	if ( latest.state === 'resolved' ) {
		/* translators: %s: a date */
		return sprintf( __( 'Until %s', 'jp-form-builder' ), latest.display );
	}
	return '';
}

export default function Edit( { clientId, attributes, setAttributes } ) {
	const format = dateFormatFor( getSettings().l10n?.locale ?? '' );
	const status = windowStatus( attributes, browserTodayIso(), format );
	const opening = windowStatus(
		{ earliest: attributes.openingDate, latest: '' },
		browserTodayIso(),
		format
	).earliest;

	const controls = (
		<>
			<p className="jp-form-builder__date-hint">
				{ __(
					'Each date is a fixed date (YYYY-MM-DD) or an offset from today: now, -1y, +30d, +2w, 6m (d days, w weeks, m months, y years).',
					'jp-form-builder'
				) }
			</p>
			<TextControl
				label={ __( 'Earliest selectable date', 'jp-form-builder' ) }
				value={ attributes.earliest }
				onChange={ ( earliest ) => setAttributes( { earliest } ) }
				help={ boundHelp( status.earliest ) }
			/>
			<TextControl
				label={ __( 'Latest selectable date', 'jp-form-builder' ) }
				value={ attributes.latest }
				onChange={ ( latest ) => setAttributes( { latest } ) }
				help={ boundHelp( status.latest ) }
			/>
			{ status.inverted && (
				<p className="jp-form-builder__date-error" role="alert">
					{ __(
						'The earliest date is later than the latest date: no date can be selected.',
						'jp-form-builder'
					) }
				</p>
			) }
			<TextControl
				label={ __( 'Opening date', 'jp-form-builder' ) }
				value={ attributes.openingDate }
				onChange={ ( openingDate ) => setAttributes( { openingDate } ) }
				help={ boundHelp( opening ) }
			/>
		</>
	);

	const summary = windowSummary( status );

	return (
		<FieldBlock
			type="date"
			clientId={ clientId }
			attributes={ attributes }
			setAttributes={ setAttributes }
			controls={ controls }
		>
			<PreviewLabel required={ attributes.required }>
				{ attributes.label.trim() || defaultLabel( 'date' ) }
			</PreviewLabel>
			<PreviewControl variant="placeholder">
				<span className="jp-form-builder__date-preview">
					{ placeholderFor( format ) }
					<CalendarIcon />
				</span>
			</PreviewControl>
			{ summary && <p className="jp-form-builder__help">{ summary }</p> }
			{ status.inverted && (
				<p className="jp-form-builder__date-error">
					{ __( 'No date can be selected.', 'jp-form-builder' ) }
				</p>
			) }
			<PreviewHelp text={ attributes.helpText } />
		</FieldBlock>
	);
}
