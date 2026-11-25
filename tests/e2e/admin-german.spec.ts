import { test, expect } from './support/fixtures';
import { login } from './support/auth';
import { withUserLocale } from './support/locale';

test( 'a de_DE user sees Form wording, never Post wording, on the Forms screen', async ( { page } ) => {
	await login( page );

	await withUserLocale( page, 'de_DE', async () => {
		await page.goto( '/wp-admin/edit.php?post_type=jp_form' );

		const screen = page.locator( '#wpbody-content' );
		await expect( screen.getByRole( 'heading', { level: 1, name: 'Formulare' } ) ).toBeVisible();
		await expect( screen.getByRole( 'link', { name: 'Formular hinzufügen' } ) ).toBeVisible();
		await expect( screen.getByRole( 'button', { name: 'Formulare durchsuchen' } ) ).toBeVisible();
		await expect( screen ).not.toContainText( 'Beitrag' );
	} );
} );
