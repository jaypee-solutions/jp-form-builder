import { login } from './support/auth';
import { test, expect, type Page } from './support/fixtures';
import type { Locator } from '@playwright/test';
import { wpCli } from './support/wp-cli';

const SETTINGS_PAGE = '/wp-admin/edit.php?post_type=jp_form&page=jp-form-builder-settings';
const SETTINGS_URL = /post_type=jp_form&page=jp-form-builder-settings/;

test.describe( 'Settings page', () => {
	test( 'groups the settings under section headings and prints no PHP', async ( { page } ) => {
		await login( page );
		await page.goto( SETTINGS_PAGE );

		for ( const heading of [
			'SMTP',
			'Sender',
			'Spam protection (Cloudflare Turnstile)',
			'Recipients',
			'Mail',
			'Test mail',
			'Backup & restore',
			'Recent mail failures',
		] ) {
			await expect( page.getByRole( 'heading', { name: heading, exact: true } ) ).toBeVisible();
		}
		const body = await page.locator( '#wpbody-content' ).innerText();
		expect( body ).not.toMatch( /<\?php|\?>|foreach \(|esc_html/ );
	} );

	test( 'shows a constant-locked field as read-only with "Set by constant."', async ( { page } ) => {
		await login( page );
		await page.goto( SETTINGS_PAGE );

		const port = page.getByLabel( 'SMTP port' );
		await expect( port ).toBeDisabled();
		await expect( port ).toHaveValue( '2525' );
		await expect( page.locator( 'tr', { has: port } ).getByText( 'Set by constant.' ) ).toBeVisible();

		const host = page.getByLabel( 'SMTP host' );
		await expect( host ).toBeEnabled();
		await expect( page.locator( 'tr', { has: host } ).getByText( 'Set by constant.' ) ).toHaveCount( 0 );
	} );

	test( 'saving and sending a test mail return to the settings page', async ( { page, options } ) => {
		test.skip( options.has( 'jp_form_builder_settings' ), 'The test would overwrite saved settings.' );
		try {
			await login( page );
			await page.goto( SETTINGS_PAGE );

			await page.getByRole( 'button', { name: 'Save settings' } ).click();
			await expect( page ).toHaveURL( SETTINGS_URL );
			await expect( page.locator( '.notice-success', { hasText: 'Settings saved.' } ) ).toBeVisible();
			await expect( page.getByLabel( 'SMTP port' ) ).toHaveValue( '2525' );

			await page.getByRole( 'button', { name: 'Send test mail' } ).click();
			await expect( page ).toHaveURL( SETTINGS_URL );
			await expect( page.locator( '.notice-success', { hasText: 'Test mail sent.' } ) ).toBeVisible();
		} finally {
			wpCli( 'option', 'delete', 'jp_form_builder_settings' );
		}
	} );

	test.describe( 'mail editors', () => {
		const layoutEditor = ( page: Page ) => page.locator( '.CodeMirror' ).nth( 0 );
		const notificationEditor = ( page: Page ) => page.locator( '.CodeMirror' ).nth( 1 );
		const confirmationEditor = ( page: Page ) => page.locator( '.CodeMirror' ).nth( 2 );
		const previewFrame = ( page: Page, index: number ) => page.frameLocator( 'iframe.jp-form-builder__mail-preview' ).nth( index );

		async function setEditorValue( editor: Locator, value: string ): Promise<void> {
			await editor.evaluate( ( element, text ) => ( element as unknown as { CodeMirror: { setValue: ( value: string ) => void } } ).CodeMirror.setValue( text ), value );
		}

		async function editorValue( editor: Locator ): Promise<string> {
			return editor.evaluate( ( element ) => ( element as unknown as { CodeMirror: { getValue: () => string } } ).CodeMirror.getValue() );
		}

		test( 'offer the code editor, placeholders and a preview for the layout and both default texts', async ( { page } ) => {
			await login( page );
			await page.goto( SETTINGS_PAGE );

			for ( const heading of [ 'Mail layout', 'Default notification', 'Default confirmation' ] ) {
				await expect( page.getByRole( 'heading', { name: heading, exact: true } ) ).toBeVisible();
			}
			await expect( page.locator( '.CodeMirror' ) ).toHaveCount( 3 );
			await expect( page.locator( 'iframe.jp-form-builder__mail-preview' ) ).toHaveCount( 3 );

			// The notification preview shows the sample Fields; the confirmation preview never does.
			await expect( previewFrame( page, 1 ).locator( 'body' ) ).toContainText( 'Jane Example' );
			await expect( previewFrame( page, 2 ).locator( 'body' ) ).toContainText( 'Thank you for your message' );
			await expect( previewFrame( page, 2 ).locator( 'body' ) ).not.toContainText( 'Jane Example' );

			const inserters = page.getByRole( 'button', { name: 'Insert placeholder' } );
			await inserters.nth( 0 ).click();
			await expect( page.getByRole( 'menuitem', { name: '{all_fields}' } ) ).toBeVisible();
			await expect( page.getByRole( 'menuitem', { name: /^\{name\} / } ) ).toBeVisible();
			await page.keyboard.press( 'Escape' );
			await inserters.nth( 1 ).click();
			await expect( page.getByRole( 'menuitem', { name: '{site_name}' } ) ).toBeVisible();
			await expect( page.getByRole( 'menuitem', { name: /^\{name\} / } ) ).toHaveCount( 0 );
			await expect( page.getByRole( 'menuitem', { name: '{all_fields}' } ) ).toHaveCount( 0 );
		} );

		test( 'a changed default text updates the preview and is saved', async ( { page, options } ) => {
			test.skip( options.has( 'jp_form_builder_settings' ), 'The test would overwrite saved settings.' );
			try {
				await login( page );
				await page.goto( SETTINGS_PAGE );

				await setEditorValue( notificationEditor( page ), '<p>Dear team, {name:label} is {name}.</p>' );
				await expect( previewFrame( page, 1 ).locator( 'body' ) ).toContainText( 'Dear team, Name is Jane Example.' );
				await setEditorValue( confirmationEditor( page ), '<p>Thanks from {site_name}.</p>' );
				await page.getByLabel( 'Confirmation subject' ).fill( 'Thanks {form_title}' );
				await expect( previewFrame( page, 2 ).locator( 'body' ) ).toContainText( 'Thanks from' );

				await page.getByRole( 'button', { name: 'Save settings' } ).click();
				await expect( page.locator( '.notice-success', { hasText: 'Settings saved.' } ) ).toBeVisible();
				expect( await editorValue( notificationEditor( page ) ) ).toBe( '<p>Dear team, {name:label} is {name}.</p>' );
				expect( await editorValue( confirmationEditor( page ) ) ).toBe( '<p>Thanks from {site_name}.</p>' );
				await expect( page.getByLabel( 'Confirmation subject' ) ).toHaveValue( 'Thanks {form_title}' );
			} finally {
				wpCli( 'option', 'delete', 'jp_form_builder_settings' );
			}
		} );

		test( 'the layout resets to the default, and an invalid layout keeps the old one and every other setting', async ( { page, options } ) => {
			test.skip( options.has( 'jp_form_builder_settings' ), 'The test would overwrite saved settings.' );
			try {
				await login( page );
				await page.goto( SETTINGS_PAGE );

				await setEditorValue( layoutEditor( page ), '<main class="e2e-layout">{content}</main>' );
				await page.getByRole( 'button', { name: 'Save settings' } ).click();
				await expect( page.locator( '.notice-success', { hasText: 'Settings saved.' } ) ).toBeVisible();

				await setEditorValue( layoutEditor( page ), '<p>no placeholder</p>' );
				await page.getByLabel( 'From name' ).fill( 'Kept sender' );
				await page.getByRole( 'button', { name: 'Save settings' } ).click();
				await expect( page.locator( '.notice-error' ) ).toContainText( 'previous layout was kept. All other settings were saved.' );
				await expect( page.getByLabel( 'From name' ) ).toHaveValue( 'Kept sender' );
				expect( await editorValue( layoutEditor( page ) ) ).toBe( '<main class="e2e-layout">{content}</main>' );

				await page.getByRole( 'button', { name: 'Reset to default' } ).click();
				expect( await editorValue( layoutEditor( page ) ) ).toBe( '' );
				await expect( previewFrame( page, 0 ).locator( 'body' ) ).toContainText( 'Jane Example' );
			} finally {
				wpCli( 'option', 'delete', 'jp_form_builder_settings' );
			}
		} );
	} );

	test( 'WP-CLI reads a boolean setting from words and refuses anything else', async ( { options } ) => {
		test.skip( options.has( 'jp_form_builder_settings' ), 'The test would overwrite saved settings.' );
		const read = () => wpCli( 'eval', "echo var_export( ( new JayPee\\FormBuilder\\Settings() )->get( 'smtp_auth' ), true );" );
		try {
			wpCli( 'jp-form-builder', 'settings', 'set', 'smtp_auth', 'false' );
			expect( read() ).toBe( 'false' );
			wpCli( 'jp-form-builder', 'settings', 'set', 'smtp_auth', 'yes' );
			expect( read() ).toBe( 'true' );
			wpCli( 'jp-form-builder', 'settings', 'set', 'smtp_auth', 'off' );
			expect( read() ).toBe( 'false' );
			expect( () => wpCli( 'jp-form-builder', 'settings', 'set', 'smtp_auth', 'maybe' ) ).toThrow( /true or false/ );
			expect( read() ).toBe( 'false' );
		} finally {
			wpCli( 'option', 'delete', 'jp_form_builder_settings' );
		}
	} );
} );
