import { login } from './support/auth';
import { test, expect, type Page } from './support/fixtures';
import { wpCli } from './support/wp-cli';

type CapturedMail = { message: string };

const CANVAS = 'iframe[name="editor-canvas"]';

function clearCapturedMail(): void {
	wpCli( 'eval', "delete_option( 'jp_form_builder_e2e_last_mail' );" );
}

function capturedMail(): CapturedMail {
	return JSON.parse(
		wpCli(
			'option',
			'get',
			'jp_form_builder_e2e_last_mail',
			'--format=json'
		)
	);
}

async function openNewForm( page: Page ): Promise< void > {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=jp_form' );
	await page.waitForFunction( () =>
		// @ts-ignore wp is provided by the block editor page.
		window.wp?.data
			?.select( 'core/block-editor' )
			?.getBlocks()
			.some(
				( block: { name: string; innerBlocks: unknown[] } ) =>
					block.name === 'jp-form-builder/form' &&
					block.innerBlocks.length > 0
			)
	);
}

type BlockSpec = {
	name: string;
	attributes?: Record< string, unknown >;
	inner?: BlockSpec[];
};

// Replaces the Form's blocks, so a test starts from a known tree instead of clicking every Field into place.
async function setFormBlocks(
	page: Page,
	blocks: BlockSpec[]
): Promise< void > {
	await page.evaluate( ( specs ) => {
		// @ts-ignore wp is provided by the block editor page.
		const { data, blocks: wpBlocks } = window.wp;
		const build = ( spec: any ): unknown =>
			wpBlocks.createBlock(
				spec.name,
				spec.attributes ?? {},
				( spec.inner ?? [] ).map( build )
			);
		const form = data
			.select( 'core/block-editor' )
			.getBlocks()
			.find(
				( block: { name: string } ) =>
					block.name === 'jp-form-builder/form'
			);
		data.dispatch( 'core/block-editor' ).replaceInnerBlocks(
			form.clientId,
			specs.map( build )
		);
	}, blocks );
}

async function selectBlockNamed(
	page: Page,
	name: string,
	index = 0
): Promise< void > {
	await page.evaluate(
		( { blockName, position } ) => {
			// @ts-ignore wp is provided by the block editor page.
			const { data } = window.wp;
			const flatten = ( blocks: any[] ): any[] =>
				blocks.flatMap( ( block ) => [
					block,
					...flatten( block.innerBlocks ),
				] );
			const found = flatten(
				data.select( 'core/block-editor' ).getBlocks()
			).filter( ( block ) => block.name === blockName );
			data.dispatch( 'core/block-editor' ).selectBlock(
				found[ position ].clientId
			);
		},
		{ blockName: name, position: index }
	);
}

async function publish( page: Page ): Promise< number > {
	await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await page
		.getByRole( 'region', { name: 'Editor publish' } )
		.getByRole( 'button', { name: 'Publish', exact: true } )
		.click();
	await expect(
		page.getByText( /is now live|published/i ).first()
	).toBeVisible();
	return page.evaluate(
		// @ts-ignore wp is provided by the block editor page.
		() =>
			window.wp.data.select( 'core/editor' ).getCurrentPostId() as number
	);
}

function embedPage( formId: number, title: string ): string {
	return wpCli(
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		`--post_title=${ title }`,
		`--post_content=<!-- wp:jp-form-builder/embed {"formId":${ formId }} /-->`,
		'--porcelain'
	);
}

async function sourceFieldOptions( page: Page ): Promise< string[] > {
	return page
		.getByLabel( 'Source Field' )
		.locator( 'option' )
		.allTextContents();
}

test.describe( 'Conditional group in the editor', () => {
	test( 'is built with the inserter, states its Condition on the canvas and works on the front end', async ( {
		page,
	} ) => {
		await openNewForm( page );
		const canvas = page.frameLocator( CANVAS );
		await canvas
			.getByRole( 'textbox', { name: 'Add title' } )
			.fill( 'E2E conditional form' );
		await setFormBlocks( page, [
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
		] );

		// The group goes in right after the selected Select.
		await canvas.locator( '.wp-block-jp-form-builder-select' ).click();
		await page
			.getByRole( 'button', { name: /Block Inserter|Add block/i } )
			.first()
			.click();
		await page
			.getByRole( 'searchbox', { name: /Search/ } )
			.fill( 'Conditional group' );
		await page
			// The block's Dashicon is part of the option's accessible name.
			.getByRole( 'option', { name: /Conditional group/ } )
			.click();

		const group = canvas.locator( '.wp-block-jp-form-builder-conditional' );
		await expect( group ).toBeVisible();
		await expect(
			group.locator( '.jp-form-builder__condition-chip' )
		).toHaveText( 'Condition incomplete' );
		await expect(
			group.locator( '.jp-form-builder__conditional-hint' )
		).toContainText( 'Add Fields here' );
		await expect( group ).toHaveCSS( 'border-top-style', 'dashed' );

		await page.getByRole( 'button', { name: 'Close Block Inserter' } ).click();
		await group.getByRole( 'button', { name: /Add block/i } ).click();
		await page.getByRole( 'searchbox', { name: /Search/ } ).fill( 'Text' );
		await page.getByRole( 'option', { name: 'Text', exact: true } ).click();
		await expect(
			group.locator( '.wp-block-jp-form-builder-text' )
		).toBeVisible();
		await page
			.getByRole( 'textbox', { name: 'Label', exact: true } )
			.fill( 'Rabattcode' );
		await page.getByRole( 'checkbox', { name: 'Required' } ).check();

		// The Condition: Paket is Gold.
		await selectBlockNamed( page, 'jp-form-builder/conditional' );
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Paket (paket)' } );
		await expect(
			page.getByLabel( 'Operator' ).locator( 'option' )
		).toHaveText( [ 'is', 'is not', 'is one of' ] );
		await page
			.getByLabel( 'Value', { exact: true } )
			.selectOption( { label: 'Gold' } );
		await expect(
			group.locator( '.jp-form-builder__condition-chip' )
		).toHaveText( 'Shown when Paket is Gold' );
		await expect(
			group.locator( '.jp-form-builder__condition-chip' )
		).not.toHaveClass( /is-incomplete/ );
		await page.screenshot( {
			path: 'test-results/conditional-editor.png',
		} );

		const formId = await publish( page );
		const pageId = embedPage( formId, 'E2E conditional page' );
		try {
			await page.goto( `/?page_id=${ pageId }` );
			const form = page.locator( 'form[data-form-id]' );
			const paket = form.getByRole( 'combobox', { name: 'Paket' } );
			const rabattcode = form.getByRole( 'textbox', {
				name: 'Rabattcode',
			} );
			const submit = form.getByRole( 'button', { name: 'Submit' } );
			const hiddenGroup = form.locator( '.jp-form-builder__group' );

			// Hidden, out of the accessibility tree and out of the tab order.
			await expect( hiddenGroup ).toBeHidden();
			await expect( hiddenGroup ).toHaveAttribute( 'hidden', '' );
			await expect( rabattcode ).toHaveCount( 0 );
			await paket.focus();
			await page.keyboard.press( 'Tab' );
			await expect( submit ).toBeFocused();
			await page.screenshot( {
				path: 'test-results/conditional-front-before.png',
			} );

			// Revealed right after the trigger, focus stays on the Select.
			await paket.focus();
			await paket.selectOption( 'Gold' );
			await expect( rabattcode ).toBeVisible();
			await expect( paket ).toBeFocused();
			await expect(
				form
					.locator( '[role="status"], [aria-live]' )
					.filter( { hasText: /\S/ } )
			).toHaveCount( 0 );
			await page.screenshot( {
				path: 'test-results/conditional-front-after.png',
			} );

			await paket.selectOption( 'Silber' );
			await expect( rabattcode ).toHaveCount( 0 );
			await expect( hiddenGroup ).toBeHidden();

			// A required Field in a hidden group does not block; neither it nor its value is mailed.
			clearCapturedMail();
			await paket.selectOption( 'Gold' );
			await rabattcode.fill( 'SECRET42' );
			await paket.selectOption( 'Silber' );
			await submit.click();
			await expect( form.locator( '[role="status"]' ) ).toHaveText(
				'Thank you! Your message has been sent.'
			);
			const silverMail = capturedMail().message;
			expect( silverMail ).toContain( 'Silber' );
			expect( silverMail ).not.toContain( 'Rabattcode' );
			expect( silverMail ).not.toContain( 'SECRET42' );

			// Gold needs the group Field, which then arrives in the mail.
			await page.goto( `/?page_id=${ pageId }` );
			await paket.selectOption( 'Gold' );
			await submit.click( { force: true } );
			const summary = form.locator( '.jp-form-builder__error-summary' );
			await expect(
				summary.getByRole( 'link', { name: 'Rabattcode is required.' } )
			).toBeVisible();
			await expect( rabattcode ).toHaveAttribute(
				'aria-invalid',
				'true'
			);

			// Hiding the group clears its errors.
			await paket.selectOption( 'Silber' );
			await expect( summary ).toBeEmpty();
			await paket.selectOption( 'Gold' );
			await expect( rabattcode ).not.toHaveAttribute(
				'aria-invalid',
				'true'
			);

			clearCapturedMail();
			await rabattcode.fill( 'GOLD-CODE' );
			await submit.click();
			await expect( form.locator( '[role="status"]' ) ).toHaveText(
				'Thank you! Your message has been sent.'
			);
			const goldMail = capturedMail().message;
			expect( goldMail ).toContain( 'Gold' );
			expect( goldMail ).toContain( 'Rabattcode' );
			expect( goldMail ).toContain( 'GOLD-CODE' );
		} finally {
			wpCli( 'post', 'delete', pageId, String( formId ), '--force' );
		}
	} );

	test( 'the Condition panel only offers Fields before the group and operators that fit', async ( {
		page,
	} ) => {
		await openNewForm( page );
		await setFormBlocks( page, [
			{
				name: 'jp-form-builder/select',
				attributes: {
					label: 'Paket',
					fieldName: 'paket',
					options: [ { label: 'Gold', value: 'gold' } ],
				},
			},
			{
				name: 'jp-form-builder/conditional',
				inner: [
					{
						name: 'jp-form-builder/text',
						attributes: { label: 'Zusatz', fieldName: 'zusatz' },
					},
				],
			},
			{
				name: 'jp-form-builder/checkbox',
				attributes: {
					label: 'Extras',
					fieldName: 'extras',
					options: [
						{ label: 'Versand', value: 'ship' },
						{ label: 'Montage', value: 'install' },
					],
				},
			},
			{
				name: 'jp-form-builder/consent',
				attributes: { fieldName: 'privacy' },
			},
			{
				name: 'jp-form-builder/conditional',
				inner: [
					{
						name: 'jp-form-builder/text',
						attributes: { label: 'Innen', fieldName: 'innen' },
					},
				],
			},
			{
				name: 'jp-form-builder/text',
				attributes: { label: 'Zuletzt', fieldName: 'zuletzt' },
			},
		] );

		await selectBlockNamed( page, 'jp-form-builder/conditional', 0 );
		expect( await sourceFieldOptions( page ) ).toEqual( [
			'Choose a Field',
			'Paket (paket)',
		] );

		// The later group also sees the Fields inside the earlier group, but not its own or later ones.
		await selectBlockNamed( page, 'jp-form-builder/conditional', 1 );
		expect( await sourceFieldOptions( page ) ).toEqual( [
			'Choose a Field',
			'Paket (paket)',
			'Zusatz (zusatz)',
			'Extras (extras)',
			'Consent (privacy)',
		] );

		const operators = page.getByLabel( 'Operator' ).locator( 'option' );
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Extras (extras)' } );
		await expect( operators ).toHaveText( [
			'includes',
			'is filled',
			'is empty',
		] );
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Consent (privacy)' } );
		await expect( operators ).toHaveText( [
			'is ticked',
			'is not ticked',
		] );
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Zusatz (zusatz)' } );
		await expect( operators ).toHaveText( [ 'is filled', 'is empty' ] );

		// Several values for Checkboxes; changing the source resets operator and values.
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Extras (extras)' } );
		await page.getByRole( 'checkbox', { name: 'Versand' } ).check();
		await page.getByRole( 'checkbox', { name: 'Montage' } ).check();
		const canvas = page.frameLocator( CANVAS );
		const chip = canvas
			.locator( '.wp-block-jp-form-builder-conditional' )
			.nth( 1 )
			.locator( '.jp-form-builder__condition-chip' );
		await expect( chip ).toHaveText(
			'Shown when Extras includes Versand, Montage'
		);
		await page
			.getByLabel( 'Source Field' )
			.selectOption( { label: 'Paket (paket)' } );
		await expect( page.getByLabel( 'Operator' ) ).toHaveValue( 'is' );
		await expect( chip ).toHaveText( 'Condition incomplete' );
	} );
} );

test.describe( 'Radio buttons and Checkboxes on the front end', () => {
	const PAGE = '/e2e-choices-page/';

	async function openChoices( page: Page ) {
		await page.goto( PAGE );
		const form = page.locator( 'form[data-form-id]' );
		return {
			form,
			silber: form.getByRole( 'radio', { name: 'Silber' } ),
			gold: form.getByRole( 'radio', { name: 'Gold' } ),
			versand: form.getByRole( 'checkbox', { name: 'Versand' } ),
			montage: form.getByRole( 'checkbox', { name: 'Montage' } ),
			etage: form.getByRole( 'textbox', { name: 'Etage' } ),
			submit: form.getByRole( 'button', { name: 'Submit' } ),
			summary: form.locator( '.jp-form-builder__error-summary' ),
			status: form.locator( '[role="status"]' ),
		};
	}

	test( 'required errors link to the first option of each group', async ( {
		page,
	} ) => {
		const { silber, versand, summary, submit, form } =
			await openChoices( page );

		await submit.click( { force: true } );
		await expect( summary ).toBeFocused();
		const links = summary.getByRole( 'link' );
		await expect( links ).toHaveText( [
			'Plan is required.',
			'Extras is required.',
		] );
		await expect( silber ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( versand ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect(
			form.locator( '.jp-form-builder__error' ).first()
		).toHaveText( 'Plan is required.' );

		await links.nth( 1 ).click();
		await expect( versand ).toBeFocused();
		await links.nth( 0 ).click();
		await expect( silber ).toBeFocused();
	} );

	test( 'can be completed with the keyboard alone and mails the option labels', async ( {
		page,
	} ) => {
		clearCapturedMail();
		const { silber, gold, versand, montage, etage, submit, status } =
			await openChoices( page );

		// Arrow keys move within the radio group and select; Space ticks a checkbox.
		await silber.focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( gold ).toBeChecked();
		await expect( gold ).toBeFocused();
		await page.keyboard.press( 'Tab' );
		await expect( versand ).toBeFocused();
		await page.keyboard.press( 'Tab' );
		await expect( montage ).toBeFocused();
		await expect( etage ).toHaveCount( 0 );
		await page.keyboard.press( 'Space' );
		await expect( montage ).toBeChecked();

		// Ticking Montage reveals Etage right after it, without moving focus.
		await expect( etage ).toBeVisible();
		await expect( montage ).toBeFocused();
		await page.keyboard.press( 'Tab' );
		await expect( etage ).toBeFocused();
		await page.keyboard.type( '3. OG' );
		await page.keyboard.press( 'Tab' );
		await expect( submit ).toBeFocused();
		await page.keyboard.press( 'Enter' );

		await expect( status ).toHaveText(
			'Thank you! Your message has been sent.'
		);
		const { message } = capturedMail();
		expect( message ).toContain( 'Gold' );
		expect( message ).toContain( 'Montage' );
		expect( message ).toContain( '3. OG' );
		for ( const value of [ 'plan-g', 'extra-install' ] ) {
			expect( message ).not.toContain( value );
		}
	} );

	test( 'a group Field that is hidden again is not sent', async ( {
		page,
	} ) => {
		clearCapturedMail();
		const { gold, versand, montage, etage, submit, status } =
			await openChoices( page );

		await gold.check();
		await versand.check();
		await montage.check();
		await etage.fill( 'ghost floor' );
		await montage.uncheck();
		await expect( etage ).toHaveCount( 0 );
		await submit.click();

		await expect( status ).toHaveText(
			'Thank you! Your message has been sent.'
		);
		const { message } = capturedMail();
		expect( message ).toContain( 'Versand' );
		expect( message ).not.toContain( 'Montage' );
		expect( message ).not.toContain( 'ghost floor' );
	} );
} );
