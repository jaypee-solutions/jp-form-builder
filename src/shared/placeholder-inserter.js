import { Button, Dropdown, MenuGroup, MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {string} kind `notification` or `confirmation`.
 * @return {{token: string, description: string}[]} The placeholders that do not depend on a Field.
 */
function generalPlaceholders( kind ) {
	const shared = [
		{
			token: '{form_title}',
			description: __( 'Title of the Form', 'jp-form-builder' ),
		},
		{
			token: '{site_name}',
			description: __( 'Name of the site', 'jp-form-builder' ),
		},
	];
	const submittedAt = {
		token: '{submitted_at}',
		description: __( 'Time of the Submission', 'jp-form-builder' ),
	};
	if ( kind === 'confirmation' ) {
		return [ ...shared, submittedAt ];
	}
	return [
		{
			token: '{all_fields}',
			description: __(
				'Table of all Fields and their values',
				'jp-form-builder'
			),
		},
		...shared,
		{
			token: '{page_url}',
			description: __( 'Page the Form was sent from', 'jp-form-builder' ),
		},
		submittedAt,
	];
}

function fieldPlaceholders( key ) {
	return [
		{
			token: `{${ key }}`,
			text: `{${ key }}`,
			description: __( 'Value', 'jp-form-builder' ),
		},
		{
			token: `{${ key }:label}`,
			text: `{${ key }:label}`,
			description: __( 'Label', 'jp-form-builder' ),
		},
		{
			token: `{#${ key }}…{/${ key }}`,
			text: `{#${ key }}{${ key }:label}: {${ key }}{/${ key }}`,
			description: __(
				'Section, only shown when filled in',
				'jp-form-builder'
			),
		},
	];
}

/**
 * Dropdown that offers the placeholders a Body template may use.
 *
 * A Confirmation never contains submitted content (ADR 0008), so it lists no Fields.
 *
 * @param {Object}                 props          Component props.
 * @param {string}                 props.kind     `notification` or `confirmation`.
 * @param {Object[]}               props.fields   The Form's Fields as `{key, label}`.
 * @param {(text: string) => void} props.onInsert Called with the text to put at the cursor.
 */
export function PlaceholderInserter( { kind, fields, onInsert } ) {
	const fieldGroups = kind === 'notification' ? fields : [];

	return (
		<Dropdown
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="secondary"
					aria-expanded={ isOpen }
					onClick={ onToggle }
				>
					{ __( 'Insert placeholder', 'jp-form-builder' ) }
				</Button>
			) }
			renderContent={ ( { onClose } ) => {
				const insert = ( text ) => () => {
					onInsert( text );
					onClose();
				};
				return (
					<div className="jp-form-builder__placeholder-menu">
						<MenuGroup label={ __( 'General', 'jp-form-builder' ) }>
							{ generalPlaceholders( kind ).map( ( item ) => (
								<MenuItem
									key={ item.token }
									info={ item.description }
									onClick={ insert( item.token ) }
								>
									{ item.token }
								</MenuItem>
							) ) }
						</MenuGroup>
						{ fieldGroups.map( ( field ) => (
							<MenuGroup key={ field.key } label={ field.label }>
								{ fieldPlaceholders( field.key ).map(
									( item ) => (
										<MenuItem
											key={ item.token }
											info={ item.description }
											onClick={ insert( item.text ) }
										>
											{ item.token }
										</MenuItem>
									)
								) }
							</MenuGroup>
						) ) }
					</div>
				);
			} }
		/>
	);
}
