import { login } from './support/auth';
import { test, expect, type Page } from './support/fixtures';
import { wpCli } from './support/wp-cli';

type EditorBlock = { name: string; isValid: boolean; fieldName: string | null };

async function openNewForm( page: Page ): Promise<void> {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=jp_form' );
	await page.waitForFunction(
		() =>
			// @ts-ignore wp is provided by the block editor page.
			window.wp?.data?.select( 'core/block-editor' )?.getBlocks().some( ( block: { name: string; innerBlocks: unknown[] } ) => block.name === 'jp-form-builder/form' && block.innerBlocks.length > 0 )
	);
}

async function formFields( page: Page ): Promise<EditorBlock[]> {
	return page.evaluate( () => {
		const flatten = ( blocks: any[] ): any[] => blocks.flatMap( ( block ) => [ block, ...flatten( block.innerBlocks ) ] );
		// @ts-ignore wp is provided by the block editor page.
		const all = flatten( window.wp.data.select( 'core/block-editor' ).getBlocks() );
		return all.map( ( block ) => ( {
			name: block.name,
			isValid: block.isValid,
			fieldName: block.attributes.fieldName ?? null,
		} ) );
	} );
}

async function selectField( page: Page, type: string ): Promise<void> {
	await page
		.frameLocator( 'iframe[name="editor-canvas"]' )
		.locator( `.wp-block-jp-form-builder-${ type }` )
		.first()
		.click();
	await expect( page.getByRole( 'textbox', { name: 'Field key' } ) ).toBeVisible();
}

async function publish( page: Page ): Promise<number> {
	await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await page.getByRole( 'region', { name: 'Editor publish' } ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await expect( page.getByText( /is now live|published/i ).first() ).toBeVisible();
	return page.evaluate(
		// @ts-ignore wp is provided by the block editor page.
		() => window.wp.data.select( 'core/editor' ).getCurrentPostId() as number
	);
}

async function openFormSettingsPanel( page: Page ): Promise<void> {
	const toggle = page.getByRole( 'button', { name: 'Form settings', exact: true } );
	if ( ! ( await toggle.isVisible() ) ) {
		await page.getByRole( 'tab', { name: 'Post' } ).click();
	}
	if ( ( await toggle.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
		await toggle.click();
	}
	await expect( page.getByRole( 'textbox', { name: 'Recipients' } ) ).toBeVisible();
}

test.describe( 'Form editor', () => {
	test( 'a new Form opens with the default template and no block validation errors', async ( { page } ) => {
		await openNewForm( page );

		const blocks = await formFields( page );
		expect( blocks.map( ( block ) => block.name ) ).toEqual( [
			'jp-form-builder/form',
			'jp-form-builder/name',
			'jp-form-builder/email',
			'jp-form-builder/textarea',
			'jp-form-builder/consent',
		] );
		expect( blocks.every( ( block ) => block.isValid ) ).toBe( true );
		expect( blocks.slice( 1 ).map( ( block ) => block.fieldName ) ).toEqual( [ 'name', 'email', 'textarea', 'consent' ] );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( canvas.locator( '.block-editor-warning' ) ).toHaveCount( 0 );
		await expect( canvas.locator( '.wp-block-jp-form-builder-email' ) ).toContainText( 'Email' );
		await expect( canvas.locator( '.wp-block-jp-form-builder-consent' ) ).toContainText( 'I agree to the privacy policy.' );
	} );

	test( 'a changed Field label shows on the front end of a page that embeds the Form', async ( { page } ) => {
		await openNewForm( page );
		await page.frameLocator( 'iframe[name="editor-canvas"]' ).getByRole( 'textbox', { name: 'Add title' } ).fill( 'E2E label form' );

		await selectField( page, 'email' );
		await page.getByRole( 'textbox', { name: 'Label', exact: true } ).fill( 'Your work email' );
		await expect( page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '.wp-block-jp-form-builder-email' ) ).toContainText( 'Your work email' );

		await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
		await page.getByRole( 'region', { name: 'Editor publish' } ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
		await expect( page.getByText( /is now live|published/i ).first() ).toBeVisible();

		const formId = await page.evaluate(
			// @ts-ignore wp is provided by the block editor page.
			() => window.wp.data.select( 'core/editor' ).getCurrentPostId() as number
		);
		const pageId = wpCli(
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			'--post_title=E2E label page',
			`--post_content=<!-- wp:jp-form-builder/embed {"formId":${ formId }} /-->`,
			'--porcelain'
		);
		try {
			await page.goto( `/?page_id=${ pageId }` );
			await expect( page.locator( 'form[data-form-id]' ).getByText( 'Your work email' ) ).toBeVisible();
		} finally {
			wpCli( 'post', 'delete', pageId, String( formId ), '--force' );
		}
	} );

	test( 'a second email Field gets a unique Field key', async ( { page } ) => {
		await openNewForm( page );
		await selectField( page, 'email' );

		await page.getByRole( 'button', { name: /Block Inserter|Add block/i } ).first().click();
		await page.getByRole( 'searchbox', { name: /Search/ } ).fill( 'Email' );
		await page.getByRole( 'option', { name: 'Email', exact: true } ).click();

		const keys = ( await formFields( page ) )
			.filter( ( block ) => block.name === 'jp-form-builder/email' )
			.map( ( block ) => block.fieldName );
		expect( keys ).toEqual( [ 'email', 'email_2' ] );
		await expect( page.getByRole( 'textbox', { name: 'Field key' } ) ).toHaveValue( 'email_2' );
	} );

	test( 'the document sidebar edits Recipients and the Notification Body template, which persist', async ( { page } ) => {
		await openNewForm( page );
		await page.frameLocator( 'iframe[name="editor-canvas"]' ).getByRole( 'textbox', { name: 'Add title' } ).fill( 'E2E body template form' );
		await openFormSettingsPanel( page );
		await page.getByRole( 'textbox', { name: 'Recipients' } ).fill( 'owner@example.test' );

		await page.getByRole( 'button', { name: 'Edit Notification Body template' } ).click();
		const modal = page.getByRole( 'dialog', { name: 'Notification Body template' } );
		await modal.locator( '.CodeMirror' ).click();
		await modal.getByRole( 'button', { name: 'Insert placeholder' } ).click();
		await page.getByRole( 'menuitem', { name: '{email}' } ).click();
		await expect( modal.locator( '.CodeMirror-code' ) ).toContainText( '{email}' );
		// The empty body previews as the default {all_fields} table; only the new body drops the other sample values.
		const preview = modal.frameLocator( 'iframe[title="Mail preview"]' );
		await expect( preview.getByText( 'jane@example.test' ) ).toBeVisible();
		await expect( preview.getByText( 'this is a sample message' ) ).toHaveCount( 0 );
		await modal.getByRole( 'button', { name: 'Save' } ).click();
		await expect( modal ).toBeHidden();

		const formId = await publish( page );
		try {
			await page.goto( `/wp-admin/post.php?post=${ formId }&action=edit` );
			await page.waitForFunction(
				() =>
					// @ts-ignore wp is provided by the block editor page.
					window.wp?.data?.select( 'core/block-editor' )?.getBlocks().some( ( block: { name: string } ) => block.name === 'jp-form-builder/form' )
			);
			const attributes = await page.evaluate(
				// @ts-ignore wp is provided by the block editor page.
				() => window.wp.data.select( 'core/block-editor' ).getBlocks().find( ( block: { name: string } ) => block.name === 'jp-form-builder/form' ).attributes
			);
			expect( attributes.notificationBody ).toBe( '{email}' );
			expect( attributes.recipients ).toBe( 'owner@example.test' );

			await openFormSettingsPanel( page );
			await expect( page.getByRole( 'textbox', { name: 'Recipients' } ) ).toHaveValue( 'owner@example.test' );
		} finally {
			wpCli( 'post', 'delete', String( formId ), '--force' );
		}
	} );
} );
