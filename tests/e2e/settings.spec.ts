import { login } from './support/auth';
import { test, expect } from './support/fixtures';
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
} );
