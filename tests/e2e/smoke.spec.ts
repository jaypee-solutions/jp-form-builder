import { login } from './support/auth';
import { test, expect } from './support/fixtures';

test.describe( 'JayPee Solutions Form Builder — smoke', () => {
	test( 'the plugin is active and its Updates page loads', async ( { page } ) => {
		await login( page );

		await page.goto( '/wp-admin/plugins.php' );
		await expect(
			page.locator( 'tr.active', { hasText: 'JayPee Solutions Form Builder' } )
		).toBeVisible();

		await page.goto( '/wp-admin/options-general.php?page=jp-form-builder-updates' );
		await expect( page.getByLabel( 'GitHub Token' ) ).toBeVisible();
	} );

	test( 'the front page renders without a PHP error', async ( { page } ) => {
		const response = await page.goto( '/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
	} );
} );
