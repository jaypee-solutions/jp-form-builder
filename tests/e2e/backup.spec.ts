import { readFile } from 'node:fs/promises';
import { login } from './support/auth';
import { test, expect } from './support/fixtures';

const SETTINGS_PAGE = '/wp-admin/edit.php?post_type=jp_form&page=jp-form-builder-settings';

type BackupForm = { id: number; title: string; status: string; slug: string; content: string };

test.describe( 'Backup & restore', () => {
	test( 'the settings page downloads a JSON Backup that holds the seeded Form', async ( { page } ) => {
		await login( page );
		await page.goto( SETTINGS_PAGE );
		await expect( page.getByRole( 'heading', { name: 'Backup & restore' } ) ).toBeVisible();
		await expect( page.getByText( 'Deleting the plugin removes all Forms and the settings.' ) ).toBeVisible();

		const downloadPromise = page.waitForEvent( 'download' );
		await page.getByRole( 'link', { name: 'Download backup' } ).click();
		const download = await downloadPromise;

		expect( download.suggestedFilename() ).toMatch( /^jp-form-builder-backup-\d{4}-\d{2}-\d{2}\.json$/ );
		const backup = JSON.parse( await readFile( ( await download.path() ) as string, 'utf8' ) );
		expect( backup.format ).toBe( 'jp-form-builder-backup' );
		expect( backup.version ).toBe( 1 );
		const form = ( backup.forms as BackupForm[] ).find( ( entry ) => entry.slug === 'e2e-contact' );
		expect( form?.title ).toBe( 'E2E contact' );
		expect( form?.status ).toBe( 'publish' );
		expect( form?.content ).toContain( 'wp:jp-form-builder/form' );
		expect( JSON.stringify( backup ) ).not.toMatch( /smtp_password|turnstile_secret_key/ );
	} );

	test( 'restoring a Backup shows the result report and skips an existing Form', async ( { page } ) => {
		await login( page );
		await page.goto( SETTINGS_PAGE );
		const downloadPromise = page.waitForEvent( 'download' );
		await page.getByRole( 'link', { name: 'Download backup' } ).click();
		const buffer = await readFile( ( await ( await downloadPromise ).path() ) as string );

		await page.getByLabel( 'Backup file' ).setInputFiles( { name: 'backup.json', mimeType: 'application/json', buffer } );
		await page.getByRole( 'button', { name: 'Restore from backup' } ).click();

		await expect( page ).toHaveURL( /backup=restored/ );
		await expect( page.locator( '.notice' ).filter( { hasText: 'Skipped: "E2E contact"' } ) ).toBeVisible();
	} );

	test( 'the Plugins list links to the download', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/plugins.php' );

		const link = page.locator( 'tr.active', { hasText: 'JayPee Solutions Form Builder' } ).getByRole( 'link', { name: 'Back up forms' } );
		await expect( link ).toHaveAttribute( 'href', /action=jp_form_builder_download_backup.*_wpnonce=/ );
	} );
} );
