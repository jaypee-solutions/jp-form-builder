import { expect, type Page } from '@playwright/test';

const ADMIN_USER = process.env.WP_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS ?? 'password';

export async function login( page: Page ): Promise<void> {
	await page.context().clearCookies();
	await page.request.get( '/wp-login.php' );
	const response = await page.request.post( '/wp-login.php', {
		form: {
			log: ADMIN_USER,
			pwd: ADMIN_PASS,
			'wp-submit': 'Log In',
			redirect_to: '/wp-admin/',
			testcookie: '1',
		},
		maxRedirects: 0,
	} );
	expect( response.status() ).toBe( 302 );
	await page.goto( '/wp-admin/' );
	await expect( page ).toHaveURL( /wp-admin/ );
}
