import {
	CANVAS,
	capturedMail,
	clearCapturedMail,
	embedPage,
	openNewForm,
	publish,
	selectBlockNamed,
	setFormBlocks,
} from './support/form-editor';
import { test, expect, type Page } from './support/fixtures';
import { wpCli } from './support/wp-cli';

// The seeded Form (tests/seed.php): required Date Field "Termin", earliest -1y, latest +30d.
const DATE_PAGE = '/e2e-date-page/';
const SUBMISSIONS_ROUTE = /\/forms\/\d+\/submissions/;

// Calendar arithmetic on ISO dates, independent of the browser clock and timezone.
function addDays( iso: string, days: number ): string {
	const date = new Date( `${ iso }T00:00:00Z` );
	date.setUTCDate( date.getUTCDate() + days );
	return date.toISOString().slice( 0, 10 );
}

function addYears( iso: string, years: number ): string {
	const [ year, month, day ] = iso.split( '-' ).map( Number );
	const lastDay = new Date( Date.UTC( year + years, month, 0 ) ).getUTCDate();
	return new Date( Date.UTC( year + years, month - 1, Math.min( day, lastDay ) ) )
		.toISOString()
		.slice( 0, 10 );
}

const siteToday = (): string => wpCli( 'eval', "echo wp_date( 'Y-m-d' );" );

const toGerman = ( iso: string ): string => iso.split( '-' ).reverse().join( '.' );

async function openDateForm( page: Page ) {
	await page.goto( DATE_PAGE );
	const form = page.locator( 'form[data-form-id]' );
	const input = form.getByRole( 'textbox', { name: 'Termin' } );
	await expect( input ).toBeVisible();
	return {
		form,
		input,
		submit: form.locator( 'button[type="submit"]' ),
		error: page.locator( `#${ await input.getAttribute( 'id' ) }-error` ),
	};
}

test.describe( 'Date Field in the editor', () => {
	test( 'states the resolved Date window and warns when it is inverted', async ( { page } ) => {
		await openNewForm( page );
		await page
			.frameLocator( CANVAS )
			.getByRole( 'textbox', { name: 'Add title' } )
			.fill( 'E2E date form' );
		await setFormBlocks( page, [
			{ name: 'jp-form-builder/date', attributes: { label: 'Termin', fieldName: 'termin' } },
		] );
		await selectBlockNamed( page, 'jp-form-builder/date' );

		const earliest = page.getByLabel( 'Earliest selectable date' );
		const latest = page.getByLabel( 'Latest selectable date' );
		const inverted = page.getByText( 'The earliest date is later than the latest date' );

		await earliest.fill( '-1y' );
		await latest.fill( '+30d' );
		await expect( page.getByText( /^Resolves to \d{4}-\d{2}-\d{2} today\.$/ ) ).toHaveCount( 2 );
		await expect( inverted ).toBeHidden();

		await earliest.fill( '+10d' );
		await latest.fill( '-10d' );
		await expect( inverted ).toBeVisible();

		await earliest.fill( 'soon' );
		await expect( page.getByText( /^Invalid date or offset/ ) ).toBeVisible();

		await earliest.fill( '-1y' );
		await latest.fill( '+30d' );
		await expect( inverted ).toBeHidden();

		// The saved Form renders the raw offsets for the front end to resolve.
		const formId = await publish( page );
		const pageId = embedPage( formId, 'E2E date editor page' );
		try {
			await page.goto( `/?page_id=${ pageId }` );
			const input = page.locator( 'form[data-form-id] input[data-date-format]' );
			await expect( input ).toHaveAttribute( 'data-earliest', '-1y' );
			await expect( input ).toHaveAttribute( 'data-latest', '+30d' );
		} finally {
			wpCli( 'post', 'delete', pageId, '--force' );
		}
	} );
} );

test.describe( 'Date Field on the front end', () => {
	test( 'checks typed dates against the format and the Date window and submits the ISO value', async ( { page } ) => {
		clearCapturedMail();
		const today = siteToday();
		const { form, input, submit, error } = await openDateForm( page );
		await expect( input ).toHaveAttribute( 'placeholder', 'YYYY-MM-DD' );

		// A complete but impossible date is flagged as soon as it is fully typed.
		await input.pressSequentially( '2026-02-30' );
		await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( error ).toHaveText( 'Please enter a valid date (YYYY-MM-DD).' );

		await input.fill( '' );
		await input.pressSequentially( addDays( today, 31 ) );
		await expect( error ).toHaveText( `Date must not be after ${ addDays( today, 30 ) }.` );

		await input.fill( '' );
		await input.pressSequentially( addDays( addYears( today, -1 ), -1 ) );
		await expect( error ).toHaveText( `Date must not be before ${ addYears( today, -1 ) }.` );

		// The window edges are inclusive.
		await input.fill( '' );
		await input.pressSequentially( addDays( today, 30 ) );
		await expect( input ).not.toHaveAttribute( 'aria-invalid', 'true' );
		await input.fill( '' );
		await input.pressSequentially( addYears( today, -1 ) );
		await expect( input ).not.toHaveAttribute( 'aria-invalid', 'true' );

		const date = addDays( today, 7 );
		await input.fill( '' );
		await input.pressSequentially( date );
		await expect( input ).not.toHaveAttribute( 'aria-invalid', 'true' );
		await submit.click();

		await expect( form ).toHaveClass( /is-submitted/ );
		expect( capturedMail().message ).toContain( date );
	} );

	test( 'a malformed date that gets past the browser is rejected by the server', async ( { page } ) => {
		clearCapturedMail();
		const { input, submit, error } = await openDateForm( page );
		await page.route( SUBMISSIONS_ROUTE, ( route ) => {
			const payload = route.request().postDataJSON();
			payload.fields.termin = '2026-13-45';
			return route.continue( { postData: JSON.stringify( payload ) } );
		} );

		await input.fill( addDays( siteToday(), 3 ) );
		const answer = page.waitForResponse( SUBMISSIONS_ROUTE );
		await submit.click();

		expect( ( await answer ).status() ).toBe( 422 );
		await expect( error ).toHaveText( 'Please enter a valid date (YYYY-MM-DD).' );
		await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
	} );

	test( 'a German Form expects TT.MM.JJJJ, accepts day and month without zero and mails the German format', async ( {
		page,
		options,
	} ) => {
		options.set( 'jp_form_builder_e2e_locale', 'de_DE' );
		clearCapturedMail();
		const today = siteToday();
		const { form, input, submit } = await openDateForm( page );
		await expect( input ).toHaveAttribute( 'placeholder', 'TT.MM.JJJJ' );

		const date = addDays( today, 12 );
		const [ year, month, day ] = date.split( '-' ).map( Number );
		await input.fill( `${ day }.${ month }.${ year }` );
		await input.blur();
		await expect( input ).toHaveValue( toGerman( date ) );
		await expect( input ).not.toHaveAttribute( 'aria-invalid', 'true' );

		await submit.click();
		await expect( form ).toHaveClass( /is-submitted/ );
		expect( capturedMail().message ).toContain( toGerman( date ) );
	} );

	test( 'the calendar button writes the chosen date into the text field', async ( { page, browserName } ) => {
		test.skip( browserName !== 'chromium', 'showPicker support is only asserted for Chromium.' );
		const { form, input } = await openDateForm( page );
		const button = form.getByRole( 'button', { name: 'Choose date from calendar' } );
		await expect( button ).toBeVisible();

		const date = addDays( siteToday(), 5 );
		const native = form.locator( '.jp-form-builder__date input[type="date"]' );
		await expect( native ).toHaveCount( 1 );
		await native.fill( date );
		await expect( input ).toHaveValue( date );
		await expect( input ).not.toHaveAttribute( 'aria-invalid', 'true' );
	} );
} );
