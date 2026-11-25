import { test, expect, type Page } from './support/fixtures';
import { wpCli } from './support/wp-cli';

const FORM_PAGE = '/e2e-contact-page/';
const TOKEN_ROUTE = /\/forms\/\d+\/token/;
const SUBMISSIONS_ROUTE = /\/forms\/\d+\/submissions/;
const CLOUDFLARE = /challenges\.cloudflare\.com/;

type CapturedMail = { to: string[] | string; subject: string; message: string; headers: string[] | string };

function clearCapturedMail(): void {
	wpCli( 'eval', "delete_option( 'jp_form_builder_e2e_last_mail' );" );
}

function capturedMail(): CapturedMail {
	return JSON.parse( wpCli( 'option', 'get', 'jp_form_builder_e2e_last_mail', '--format=json' ) );
}

function requestsTo( page: Page, pattern: RegExp ): string[] {
	const urls: string[] = [];
	page.on( 'request', ( request ) => {
		if ( pattern.test( request.url() ) ) {
			urls.push( request.url() );
		}
	} );
	return urls;
}

async function openForm( page: Page ) {
	await page.goto( FORM_PAGE );
	const form = page.locator( 'form[data-form-id]' );
	await expect( form.getByRole( 'button', { name: 'Submit' } ) ).toHaveAttribute( 'aria-disabled', 'true' );
	return {
		form,
		name: form.getByRole( 'textbox', { name: 'Name', exact: true } ),
		email: form.getByRole( 'textbox', { name: 'Email', exact: true } ),
		message: form.getByRole( 'textbox', { name: 'Message', exact: true } ),
		consent: form.getByRole( 'checkbox' ),
		submit: form.getByRole( 'button', { name: 'Submit' } ),
		status: form.locator( '[role="status"]' ),
		summary: form.locator( '.jp-form-builder__error-summary' ),
	};
}

test.describe( 'Form submission', () => {
	test( 'requests nothing until the visitor interacts with the Form', async ( { page } ) => {
		const tokenRequests = requestsTo( page, TOKEN_ROUTE );
		const cloudflareRequests = requestsTo( page, CLOUDFLARE );

		const { form, name, submit } = await openForm( page );
		await expect( submit ).not.toHaveAttribute( 'disabled' );
		await page.waitForLoadState( 'networkidle' );
		expect( tokenRequests ).toEqual( [] );
		expect( cloudflareRequests ).toEqual( [] );
		await expect( form.locator( '.jp-form-builder__turnstile' ) ).toHaveCount( 0 );

		const tokenRequest = page.waitForRequest( TOKEN_ROUTE );
		await name.focus();
		await tokenRequest;
		await expect( submit ).not.toHaveAttribute( 'aria-disabled', 'true' );
		await page.waitForLoadState( 'networkidle' );
		expect( tokenRequests ).toHaveLength( 1 );
		expect( cloudflareRequests ).toEqual( [] );
	} );

	test( 'a valid submission shows the focused status message and mails the Field values with Reply-To', async ( { page } ) => {
		clearCapturedMail();
		const { form, name, email, message, consent, submit, status } = await openForm( page );

		await name.fill( 'Ada Lovelace' );
		await email.fill( 'ada@example.test' );
		await message.fill( 'Hello from the E2E suite' );
		await consent.check();
		await submit.click();

		await expect( status ).toHaveText( 'Thank you! Your message has been sent.' );
		await expect( status ).toBeFocused();
		await expect( name ).toBeHidden();
		await expect( submit ).toBeHidden();
		await expect( form.locator( '.jp-form-builder__error-summary' ) ).toBeEmpty();

		const mail = capturedMail();
		expect( mail.to ).toEqual( [ 'owner@example.test' ] );
		expect( mail.subject ).toContain( 'E2E contact' );
		for ( const value of [ 'Ada Lovelace', 'ada@example.test', 'Hello from the E2E suite' ] ) {
			expect( mail.message ).toContain( value );
		}
		expect( [ mail.headers ].flat().join( '\n' ) ).toMatch( /Reply-To: .*ada@example\.test/ );
	} );

	test( 'an empty required submit shows a focused error summary with links and sends nothing', async ( { page } ) => {
		const submissions = requestsTo( page, SUBMISSIONS_ROUTE );
		const { form, name, email, message, consent, submit, summary } = await openForm( page );

		// The button is aria-disabled until the visitor interacts; Playwright would wait for that to end.
		await submit.click( { force: true } );

		await expect( summary ).toBeFocused();
		await expect( summary.getByRole( 'heading', { name: 'Please correct the marked fields.' } ) ).toBeVisible();
		const links = summary.getByRole( 'link' );
		await expect( links ).toHaveText( [ 'Name is required.', 'Email is required.', 'Message is required.', 'Please accept to continue.' ] );
		for ( const control of [ name, email, message, consent ] ) {
			await expect( control ).toHaveAttribute( 'aria-invalid', 'true' );
		}
		await expect( form.locator( '.jp-form-builder__error' ).first() ).toHaveText( 'Name is required.' );

		await links.nth( 1 ).click();
		await expect( email ).toBeFocused();
		expect( submissions ).toEqual( [] );
	} );

	test( 'a Form can be completed with the keyboard alone', async ( { page } ) => {
		clearCapturedMail();
		const { name, email, message, consent, submit, status } = await openForm( page );

		await name.focus();
		await page.keyboard.type( 'Grace Hopper' );
		await page.keyboard.press( 'Tab' );
		await expect( email ).toBeFocused();
		await page.keyboard.type( 'grace@example.test' );
		await page.keyboard.press( 'Tab' );
		await expect( message ).toBeFocused();
		await page.keyboard.type( 'Typed without a mouse' );
		await page.keyboard.press( 'Tab' );
		await expect( consent ).toBeFocused();
		await page.keyboard.press( 'Space' );
		await expect( consent ).toBeChecked();

		// The privacy policy link sits between the checkbox and the button.
		await page.keyboard.press( 'Tab' );
		if ( ! ( await submit.evaluate( ( button ) => button === document.activeElement ) ) ) {
			await page.keyboard.press( 'Tab' );
		}
		await expect( submit ).toBeFocused();
		await page.keyboard.press( 'Enter' );

		await expect( status ).toHaveText( 'Thank you! Your message has been sent.' );
		await expect( status ).toBeFocused();
		expect( capturedMail().message ).toContain( 'Typed without a mouse' );
	} );

	test( 'Enter in a text field submits the Form', async ( { page } ) => {
		clearCapturedMail();
		const { name, email, message, consent, status } = await openForm( page );

		await name.fill( 'Katherine Johnson' );
		await message.fill( 'Submitted with Enter' );
		await consent.check();
		await email.fill( 'katherine@example.test' );
		await email.press( 'Enter' );

		await expect( status ).toHaveText( 'Thank you! Your message has been sent.' );
		expect( capturedMail().message ).toContain( 'Submitted with Enter' );
	} );

	test( 'a server-side 422 is mapped onto the right control and keeps the input', async ( { page } ) => {
		// The browser accepts "name@localhost"; the server's email validation does not.
		const { name, email, message, consent, submit, summary } = await openForm( page );

		await name.fill( 'Ada Lovelace' );
		await email.fill( 'ada@localhost' );
		await message.fill( 'Keep me' );
		await consent.check();
		const answer = page.waitForResponse( SUBMISSIONS_ROUTE );
		await submit.click();

		expect( ( await answer ).status() ).toBe( 422 );
		await expect( summary ).toBeFocused();
		await expect( summary.getByRole( 'link' ) ).toHaveText( [ 'Please enter a valid email address.' ] );
		await expect( email ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( name ).not.toHaveAttribute( 'aria-invalid', 'true' );
		await expect( page.locator( `#${ ( await email.getAttribute( 'id' ) ) }-error` ) ).toHaveText( 'Please enter a valid email address.' );
		await expect( message ).toHaveValue( 'Keep me' );

		await summary.getByRole( 'link' ).click();
		await expect( email ).toBeFocused();
	} );

	test( 'an implausible phone number is caught in the browser and costs no attempt', async ( { page } ) => {
		clearCapturedMail();
		const submissions = requestsTo( page, SUBMISSIONS_ROUTE );
		await page.goto( '/e2e-phone-page/' );
		const form = page.locator( 'form[data-form-id]' );
		const phone = form.getByRole( 'textbox', { name: 'Phone', exact: true } );
		const summary = form.locator( '.jp-form-builder__error-summary' );
		const submit = form.getByRole( 'button', { name: 'Submit' } );

		for ( const invalid of [ 'abc', '12345', '0821 12x456', '+49 123' ] ) {
			await phone.fill( invalid );
			await submit.click( { force: true } );
			await expect( summary.getByRole( 'link' ) ).toHaveText( [ 'Please enter a valid phone number.' ] );
			await expect( phone ).toHaveAttribute( 'aria-invalid', 'true' );
		}
		expect( submissions ).toEqual( [] );

		await phone.fill( '0821 123456' );
		await expect( submit ).not.toHaveAttribute( 'aria-disabled', 'true' );
		await submit.click();
		await expect( form.locator( '[role="status"]' ) ).toHaveText( 'Thank you! Your message has been sent.' );
		expect( submissions ).toHaveLength( 1 );
		expect( capturedMail().message ).toContain( '+49821123456' );
	} );

	test( 'a Postcode follows its Country in the browser, and without a Country it must be five digits', async ( { page } ) => {
		clearCapturedMail();
		const submissions = requestsTo( page, SUBMISSIONS_ROUTE );
		await page.goto( '/e2e-postcode-page/' );
		const form = page.locator( 'form[data-form-id]' );
		const postcode = form.getByRole( 'textbox', { name: 'Postcode', exact: true } );
		const country = form.getByRole( 'combobox', { name: 'Country', exact: true } );
		const summary = form.locator( '.jp-form-builder__error-summary' );
		const submit = form.getByRole( 'button', { name: 'Submit' } );

		await country.selectOption( 'AT' );
		await postcode.fill( '86150' );
		await submit.click( { force: true } );
		await expect( summary.getByRole( 'link' ) ).toHaveText( [ 'Please enter a valid postcode.' ] );
		await expect( postcode ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( submissions ).toEqual( [] );

		await postcode.fill( '1010' );
		await expect( submit ).not.toHaveAttribute( 'aria-disabled', 'true' );
		await submit.click();
		await expect( form.locator( '[role="status"]' ) ).toHaveText( 'Thank you! Your message has been sent.' );
		expect( submissions ).toHaveLength( 1 );
		expect( capturedMail().message ).toContain( '1010' );
		expect( capturedMail().message ).toContain( 'Austria' );

		// A Form without a Country field falls back to the German rule: five digits.
		await page.goto( '/e2e-postcode-alone-page/' );
		const alone = page.locator( 'form[data-form-id]' );
		const lonePostcode = alone.getByRole( 'textbox', { name: 'Postcode', exact: true } );
		await lonePostcode.fill( '1010' );
		await alone.getByRole( 'button', { name: 'Submit' } ).click( { force: true } );
		await expect( alone.locator( '.jp-form-builder__error-summary' ).getByRole( 'link' ) ).toHaveText( [ 'Please enter a valid postcode.' ] );
		await lonePostcode.fill( '86150' );
		await alone.getByRole( 'button', { name: 'Submit' } ).click();
		await expect( alone.locator( '[role="status"]' ) ).toHaveText( 'Thank you! Your message has been sent.' );
		expect( submissions ).toHaveLength( 2 );
		expect( capturedMail().message ).toContain( '86150' );
	} );

	test( 'with Turnstile configured, the widget loads on first interaction and renews after a failed attempt', async ( { page } ) => {
		const original = wpCli(
			'eval',
			"$settings = new JayPee\\FormBuilder\\Settings(); echo base64_encode( serialize( get_option( 'jp_form_builder_settings', null ) ) ); $settings->save( [ 'turnstile_site_key' => 'e2e-site-key', 'turnstile_secret_key' => 'e2e-secret' ] );"
		);
		try {
			// Stand-in for Cloudflare's script: renders by answering at once, and answers again after reset().
			await page.route( CLOUDFLARE, ( route ) =>
				route.fulfill( {
					contentType: 'application/javascript',
					body: `window.turnstileCalls = [];
						let count = 0;
						let options;
						const answer = () => setTimeout( () => options.callback( 'ts-response-' + ( ++count ) ), 10 );
						window.turnstile = {
							render: ( container, renderOptions ) => { options = renderOptions; window.turnstileCalls.push( [ 'render', renderOptions.sitekey, renderOptions.action, renderOptions.appearance, container.className ] ); answer(); return 'widget'; },
							reset: () => { window.turnstileCalls.push( [ 'reset' ] ); answer(); },
							remove: () => window.turnstileCalls.push( [ 'remove' ] ),
						};`,
				} )
			);
			const scriptRequests = requestsTo( page, CLOUDFLARE );
			const sent: Array<{ turnstile: string }> = [];
			let attempts = 0;
			await page.route( SUBMISSIONS_ROUTE, ( route ) => {
				sent.push( route.request().postDataJSON() );
				if ( ++attempts === 1 ) {
					return route.fulfill( { status: 400, contentType: 'application/json', body: JSON.stringify( { code: 'jpfb_turnstile_failed', message: 'The spam protection check failed. Please try again.', data: { status: 400 } } ) } );
				}
				return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { message: 'Thank you! Your message has been sent.' } ) } );
			} );

			const { form, name, email, message, consent, submit, status, summary } = await openForm( page );
			await expect( form.locator( '.jp-form-builder__turnstile' ) ).toHaveCount( 1 );
			await page.waitForLoadState( 'networkidle' );
			expect( scriptRequests ).toEqual( [] );

			await name.fill( 'Ada Lovelace' );
			await email.fill( 'ada@example.test' );
			await message.fill( 'Hello' );
			await consent.check();
			await expect( submit ).not.toHaveAttribute( 'aria-disabled', 'true' );
			expect( scriptRequests ).toHaveLength( 1 );
			expect( scriptRequests[ 0 ] ).toContain( 'render=explicit' );
			expect( await page.evaluate( () => ( window as any ).turnstileCalls[ 0 ] ) ).toEqual( [ 'render', 'e2e-site-key', 'jpfb-submit', 'interaction-only', 'jp-form-builder__turnstile' ] );

			await submit.click();
			await expect( summary ).toHaveText( 'The spam protection check failed. Please try again.' );
			await expect( summary ).toBeFocused();
			expect( sent.map( ( payload ) => payload.turnstile ) ).toEqual( [ 'ts-response-1' ] );
			await expect.poll( () => page.evaluate( () => ( window as any ).turnstileCalls.map( ( call: string[] ) => call[ 0 ] ) ) ).toEqual( [ 'render', 'reset' ] );
			await expect( submit ).not.toHaveAttribute( 'aria-disabled', 'true' );

			await submit.click();
			await expect( status ).toHaveText( 'Thank you! Your message has been sent.' );
			expect( sent.map( ( payload ) => payload.turnstile ) ).toEqual( [ 'ts-response-1', 'ts-response-2' ] );
			expect( scriptRequests ).toHaveLength( 1 );
		} finally {
			wpCli( 'eval', `$original = unserialize( base64_decode( '${ original }' ) ); if ( null === $original ) { delete_option( 'jp_form_builder_settings' ); } else { update_option( 'jp_form_builder_settings', $original ); }` );
		}
	} );
} );
