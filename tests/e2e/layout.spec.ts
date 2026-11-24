import {
	CANVAS,
	capturedMail,
	clearCapturedMail,
	embedPage,
	openNewForm,
	publish,
	selectBlockNamed,
	setFormBlocks,
	sourceFieldOptions,
} from './support/form-editor';
import { test, expect } from './support/fixtures';
import { wpCli } from './support/wp-cli';

test.describe( 'Layout blocks in a Form', () => {
	test( 'Columns and Groups hold Fields in the editor and on the front end, next to a Conditional group', async ( {
		page,
	} ) => {
		await openNewForm( page );
		await page
			.frameLocator( CANVAS )
			.getByRole( 'textbox', { name: 'Add title' } )
			.fill( 'E2E layout form' );
		await setFormBlocks( page, [
			{
				name: 'core/columns',
				inner: [
					{
						name: 'core/column',
						inner: [
							{
								name: 'jp-form-builder/select',
								attributes: {
									label: 'Paket',
									fieldName: 'paket',
									options: [
										{ label: 'Silber', value: 'silver' },
										{ label: 'Gold', value: 'gold' },
									],
								},
							},
						],
					},
					{
						name: 'core/column',
						inner: [
							{
								name: 'jp-form-builder/text',
								attributes: {
									label: 'Firma',
									fieldName: 'firma',
								},
							},
						],
					},
				],
			},
			{
				name: 'jp-form-builder/conditional',
				inner: [
					{
						name: 'core/group',
						attributes: {
							layout: { type: 'flex', flexWrap: 'nowrap' },
						},
						inner: [
							{
								name: 'jp-form-builder/text',
								attributes: {
									label: 'Rabattcode',
									fieldName: 'rabattcode',
									required: true,
								},
							},
						],
					},
				],
			},
		] );

		// Layout blocks go where Fields go; a Conditional group never goes into one.
		const insertable = await page.evaluate( () => {
			// @ts-ignore wp is provided by the block editor page.
			const { data } = window.wp;
			const editor = data.select( 'core/block-editor' );
			const flatten = ( blocks: any[] ): any[] =>
				blocks.flatMap( ( block ) => [
					block,
					...flatten( block.innerBlocks ),
				] );
			const all = flatten( editor.getBlocks() );
			const form = all.find(
				( block ) => block.name === 'jp-form-builder/form'
			);
			const column = all.find( ( block ) => block.name === 'core/column' );
			const group = all.find( ( block ) => block.name === 'core/group' );
			const can = ( name: string, root: string ): boolean =>
				editor.canInsertBlockType( name, root );
			return {
				columnsInForm: can( 'core/columns', form.clientId ),
				groupInForm: can( 'core/group', form.clientId ),
				emailInColumn: can( 'jp-form-builder/email', column.clientId ),
				conditionalInColumn: can(
					'jp-form-builder/conditional',
					column.clientId
				),
				conditionalInGroup: can(
					'jp-form-builder/conditional',
					group.clientId
				),
			};
		} );
		expect( insertable ).toEqual( {
			columnsInForm: true,
			groupInForm: true,
			emailInColumn: true,
			conditionalInColumn: false,
			conditionalInGroup: false,
		} );

		// Fields inside earlier layout blocks are Condition sources.
		await selectBlockNamed( page, 'jp-form-builder/conditional' );
		expect( await sourceFieldOptions( page ) ).toEqual( [
			'Choose a Field',
			'Paket (paket)',
			'Firma (firma)',
		] );
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Paket (paket)' } );
		await page
			.getByLabel( 'Value', { exact: true } )
			.selectOption( { label: 'Gold' } );
		await page.screenshot( { path: 'test-results/layout-editor.png' } );

		const formId = await publish( page );
		const pageId = embedPage( formId, 'E2E layout page' );
		try {
			await page.goto( `/?page_id=${ pageId }` );
			const form = page.locator( 'form[data-form-id]' );
			const paket = form.getByRole( 'combobox', { name: 'Paket' } );
			const firma = form.getByRole( 'textbox', { name: 'Firma' } );
			const rabattcode = form.getByRole( 'textbox', {
				name: 'Rabattcode',
			} );
			const submit = form.getByRole( 'button', { name: 'Submit' } );

			await expect(
				form.locator( '.wp-block-columns .wp-block-column' ).first()
			).toContainText( 'Paket' );
			await expect(
				form
					.locator( '.wp-block-columns .wp-block-column' )
					.first()
					.getByRole( 'combobox', { name: 'Paket' } )
			).toBeVisible();
			const paketBox = await paket.boundingBox();
			const firmaBox = await firma.boundingBox();
			expect( paketBox!.y ).toBeCloseTo( firmaBox!.y, 0 );
			expect( firmaBox!.x ).toBeGreaterThan( paketBox!.x );
			await expect( rabattcode ).toHaveCount( 0 );
			await page.screenshot( { path: 'test-results/layout-front.png' } );

			clearCapturedMail();
			await paket.selectOption( 'Silber' );
			await firma.fill( 'Muster GmbH' );
			await submit.click();
			await expect( form.locator( '[role="status"]' ) ).toHaveText(
				'Thank you! Your message has been sent.'
			);
			const silverMail = capturedMail().message;
			expect( silverMail ).toContain( 'Firma' );
			expect( silverMail ).toContain( 'Muster GmbH' );
			expect( silverMail ).not.toContain( 'Rabattcode' );

			await page.goto( `/?page_id=${ pageId }` );
			await paket.selectOption( 'Gold' );
			await expect( rabattcode ).toBeVisible();
			await submit.click( { force: true } );
			await expect(
				form
					.locator( '.jp-form-builder__error-summary' )
					.getByRole( 'link', { name: 'Rabattcode is required.' } )
			).toBeVisible();

			clearCapturedMail();
			await rabattcode.fill( 'GOLD-CODE' );
			await submit.click();
			await expect( form.locator( '[role="status"]' ) ).toHaveText(
				'Thank you! Your message has been sent.'
			);
			expect( capturedMail().message ).toContain( 'GOLD-CODE' );
		} finally {
			wpCli( 'post', 'delete', pageId, String( formId ), '--force' );
		}
	} );
} );
