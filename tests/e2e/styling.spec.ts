import type { Frame, Locator } from '@playwright/test';
import { login } from './support/auth';
import { test, expect, type Page } from './support/fixtures';
import { wpCli } from './support/wp-cli';

const FORM_PAGE = '/e2e-contact-page/';
const VARIATIONS = [ 'standard', 'soft', 'underlined', 'rounded', 'card', 'glass' ];
const SCREENSHOT_DIR = 'test-results/styling';

async function openNewForm( page: Page ): Promise<void> {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=jp_form' );
	await page.waitForFunction(
		() =>
			// @ts-ignore wp is provided by the block editor page.
			window.wp?.data?.select( 'core/block-editor' )?.getBlocks().some( ( block: { name: string; innerBlocks: unknown[] } ) => block.name === 'jp-form-builder/form' && block.innerBlocks.length > 0 )
	);
}

async function openInEditor( page: Page, postId: string, blockName: string ): Promise<void> {
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.waitForFunction(
		( name ) =>
			// @ts-ignore wp is provided by the block editor page.
			window.wp?.data?.select( 'core/block-editor' )?.getBlocks().some( ( block: { name: string } ) => block.name === name ),
		blockName
	);
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

async function setFieldWidth( page: Page, type: string, label: string ): Promise<void> {
	await page
		.frameLocator( 'iframe[name="editor-canvas"]' )
		.locator( `.wp-block-jp-form-builder-${ type }` )
		.first()
		.click();
	await page.getByRole( 'combobox', { name: 'Width' } ).selectOption( { label } );
}

type Box = { x: number; y: number; width: number };

async function presetColor( page: Page, slug: string ): Promise<string> {
	return page.evaluate( ( name ) => {
		const probe = document.createElement( 'div' );
		probe.style.backgroundColor = `var(--wp--preset--color--${ name })`;
		document.body.append( probe );
		const color = getComputedStyle( probe ).backgroundColor;
		probe.remove();
		return color;
	}, slug );
}

async function box( locator: Locator ): Promise<Box> {
	const rect = await locator.boundingBox();
	expect( rect ).not.toBeNull();
	return rect as Box;
}

function sideBySide( first: Box, second: Box ): boolean {
	return Math.abs( first.y - second.y ) < 2 && second.x > first.x + first.width - 1;
}

// What a classic theme or wp-admin's forms.css sets on bare form controls.
const HOSTILE_CONTROL_RULES = `
	input[type="text"], input[type="email"], input[type="tel"], select, textarea {
		margin: 9px; padding: 2px; min-height: 0; border: 3px solid rgb(255, 0, 0); border-radius: 0;
		background: rgb(255, 255, 0) none; box-shadow: 0 0 0 4px rgb(0, 0, 255);
		font-size: 9px; line-height: 1; color: rgb(0, 128, 0);
	}
`;
const CONTROL_PROPERTIES = [
	'margin-top',
	'padding-top',
	'padding-left',
	'min-height',
	'border-top-width',
	'border-top-color',
	'border-top-left-radius',
	'background-color',
	'background-image',
	'box-shadow',
	'font-size',
	'line-height',
	'color',
];

async function controlStyles( controls: Locator ): Promise<Record<string, string>[]> {
	return controls.evaluateAll(
		( elements, properties ) =>
			elements.map( ( element ) => {
				const style = getComputedStyle( element );
				return Object.fromEntries( properties.map( ( property ) => [ property, style.getPropertyValue( property ) ] ) );
			} ),
		CONTROL_PROPERTIES
	);
}

// Loads wp-admin's own forms.css plus classic theme rules, as a non-iframed editor or a theme would.
async function addHostileControlRules( scope: Page | Frame ): Promise<void> {
	await scope.addStyleTag( { url: '/wp-admin/css/forms.css' } );
	await scope.addStyleTag( { content: HOSTILE_CONTROL_RULES } );
	await scope.evaluate( () => document.body.classList.add( 'wp-core-ui' ) );
}

const FIELD_CONTROLS = '.jp-form-builder__field :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea)';

test.describe( 'Form styling', () => {
	test( 'a style variation, a Field color and Field widths reach the front end', async ( { page } ) => {
		await openNewForm( page );
		await page.frameLocator( 'iframe[name="editor-canvas"]' ).getByRole( 'textbox', { name: 'Add title' } ).fill( 'E2E styling form' );

		await setFieldWidth( page, 'name', 'Half width' );
		await setFieldWidth( page, 'email', 'Half width' );
		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( canvas.locator( '.wp-block-jp-form-builder-email' ) ).toHaveClass( /jp-form-builder__field--width-50/ );
		// The Form editor shows the widths as well.
		await expect.poll( async () => sideBySide( await box( canvas.locator( '.wp-block-jp-form-builder-name' ) ), await box( canvas.locator( '.wp-block-jp-form-builder-email' ) ) ) ).toBe( true );
		const formId = await publish( page );

		const pageId = wpCli(
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			'--post_title=E2E styling page',
			`--post_content=<!-- wp:jp-form-builder/embed {"formId":${ formId }} /-->`,
			'--porcelain'
		);
		try {
			await openInEditor( page, pageId, 'jp-form-builder/embed' );
			await canvas.locator( '.wp-block-jp-form-builder-embed' ).first().click();
			await page.getByRole( 'tab', { name: 'Styles' } ).click();
			await page.getByRole( 'button', { name: 'Soft' } ).click();
			await expect( canvas.locator( '.wp-block-jp-form-builder-embed' ).first() ).toHaveClass( /is-style-soft/ );

			await page.getByRole( 'button', { name: 'Field background' } ).click();
			await page.getByRole( 'option', { name: 'Accent 1' } ).click();
			await page.keyboard.press( 'Escape' );

			// The preview comes from the server and carries the same property.
			const previewWrapper = canvas.locator( '.wp-block-jp-form-builder-embed .wp-block-jp-form-builder-embed' );
			await expect( previewWrapper ).toHaveAttribute( 'style', /--jpfb-field-background:var\(--wp--preset--color--accent-1\)/ );
			await expect( canvas.locator( '.wp-block-jp-form-builder-embed input' ).first() ).not.toHaveCSS( 'background-color', 'rgba(0, 0, 0, 0)' );
			await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
			await page.waitForFunction(
				() =>
					// @ts-ignore wp is provided by the block editor page.
					! window.wp.data.select( 'core/editor' ).isEditedPostDirty() && ! window.wp.data.select( 'core/editor' ).isSavingPost()
			);

			await page.goto( `/?page_id=${ pageId }` );
			const wrapper = page.locator( '.wp-block-jp-form-builder-embed' );
			await expect( wrapper ).toHaveClass( /is-style-soft/ );
			await expect( wrapper ).toHaveAttribute( 'style', /--jpfb-field-background:var\(--wp--preset--color--accent-1\)/ );
			await expect( page.locator( '[data-field-key="email"] input' ) ).toHaveCSS( 'background-color', await presetColor( page, 'accent-1' ) );

			const name = page.locator( '[data-field-key="name"]' );
			const email = page.locator( '[data-field-key="email"]' );
			expect( sideBySide( await box( name ), await box( email ) ) ).toBe( true );
			await expect( page.locator( '[data-field-key="textarea"]' ) ).toHaveClass( /jp-form-builder__field--textarea/ );

			await page.setViewportSize( { width: 400, height: 900 } );
			const stackedName = await box( name );
			const stackedEmail = await box( email );
			expect( sideBySide( stackedName, stackedEmail ) ).toBe( false );
			expect( stackedEmail.y ).toBeGreaterThan( stackedName.y );
			expect( Math.abs( stackedEmail.x - stackedName.x ) ).toBeLessThan( 2 );
		} finally {
			wpCli( 'post', 'delete', pageId, String( formId ), '--force' );
		}
	} );

	test( 'the Form block is offered in Site Editor Styles under Blocks', async ( { page } ) => {
		await login( page );
		await page.goto( '/wp-admin/site-editor.php?p=%2Fstyles&section=%2Fblocks' );
		const formBlock = page.getByRole( 'button', { name: 'Form', exact: true } );
		await expect( formBlock ).toBeVisible();
		await formBlock.click();
		// The block's own Styles screen lists its variations and the design tools its supports declare.
		await expect( page.getByRole( 'button', { name: 'Soft', exact: true } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Underlined', exact: true } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Rounded', exact: true } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Card', exact: true } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Liquid Glass', exact: true } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Typography' } ) ).toBeVisible();
	} );

	test( 'theme and wp-admin control rules cannot reshape Fields on the page or in the editor', async ( { page } ) => {
		await page.goto( FORM_PAGE );
		const pageControls = page.locator( FIELD_CONTROLS );
		const onPage = await controlStyles( pageControls );
		expect( onPage.length ).toBeGreaterThan( 2 );
		await addHostileControlRules( page );
		expect( await controlStyles( pageControls ) ).toEqual( onPage );

		await login( page );
		const pageId = wpCli( 'post', 'list', '--post_type=page', '--name=e2e-contact-page', '--field=ID' );
		await openInEditor( page, pageId, 'jp-form-builder/embed' );
		const canvasElement = page.locator( 'iframe[name="editor-canvas"]' );
		await expect( canvasElement ).toBeVisible();
		const canvas = ( await ( await canvasElement.elementHandle() )?.contentFrame() ) as Frame;
		const editorControls = canvas.locator( FIELD_CONTROLS );
		// The embed renders server-side; wait for its Fields, not only the block.
		await expect( editorControls.first() ).toBeVisible();
		const inEditor = await controlStyles( editorControls );
		await addHostileControlRules( canvas );
		expect( await controlStyles( editorControls ) ).toEqual( inEditor );
	} );

	for ( const variation of VARIATIONS ) {
		test( `the ${ variation } style keeps a visible focus ring and the error styling`, async ( { page } ) => {
			await page.setViewportSize( { width: 1280, height: 900 } );
			await page.goto( FORM_PAGE );
			const wrapper = page.locator( '.wp-block-jp-form-builder-embed' );
			await wrapper.evaluate( ( element, name ) => element.classList.add( `is-style-${ name }` ), variation );
			const name = page.locator( 'form[data-form-id] input[name="name"]' );

			await name.focus();
			const focus = await name.evaluate( ( input ) => {
				// A canvas normalises every CSS color syntax (rgb(), color(srgb …), keywords) to RGBA bytes.
				const context = document.createElement( 'canvas' ).getContext( '2d' ) as CanvasRenderingContext2D;
				const toRgba = ( color: string ): number[] => {
					context.clearRect( 0, 0, 1, 1 );
					context.fillStyle = color;
					context.fillRect( 0, 0, 1, 1 );
					const [ red, green, blue, alpha ] = context.getImageData( 0, 0, 1, 1 ).data;
					return [ red, green, blue, alpha / 255 ];
				};
				const luminance = ( [ red, green, blue ]: number[] ) => {
					const [ r, g, b ] = [ red, green, blue ].map( ( value ) => {
						const channel = value / 255;
						return channel <= 0.03928 ? channel / 12.92 : ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
					} );
					return 0.2126 * r + 0.7152 * g + 0.0722 * b;
				};
				// The ring sits outside the Field: composite every translucent ancestor background
				// (Liquid Glass, Card) over the first opaque one, as the browser paints it.
				const layers: number[][] = [];
				for ( let node: Element | null = input.parentElement; node; node = node.parentElement ) {
					const layer = toRgba( getComputedStyle( node ).backgroundColor );
					if ( layer[ 3 ] > 0 ) {
						layers.push( layer );
					}
					if ( layer[ 3 ] === 1 ) {
						break;
					}
				}
				const background = layers.reduceRight(
					( below, [ red, green, blue, alpha ] ) => [ red * alpha + below[ 0 ] * ( 1 - alpha ), green * alpha + below[ 1 ] * ( 1 - alpha ), blue * alpha + below[ 2 ] * ( 1 - alpha ) ],
					[ 255, 255, 255 ]
				);
				const style = getComputedStyle( input );
				const [ lighter, darker ] = [ luminance( toRgba( style.outlineColor ) ), luminance( background ) ].sort( ( a, b ) => b - a );
				return { style: style.outlineStyle, width: style.outlineWidth, contrast: ( lighter + 0.05 ) / ( darker + 0.05 ) };
			} );
			expect( focus.style ).toBe( 'solid' );
			expect( focus.width ).toBe( '2px' );
			expect( focus.contrast ).toBeGreaterThanOrEqual( 3 );

			await page.locator( 'form[data-form-id] button[type="submit"]' ).click( { force: true } );
			await expect( page.locator( '.jp-form-builder__error-summary' ) ).toBeFocused();
			for ( const key of [ 'name', 'email', 'message' ] ) {
				const field = page.locator( `[data-field-key="${ key }"]` );
				await expect( field.locator( '[aria-invalid="true"]' ) ).toHaveCSS( 'border-bottom-color', 'rgb(179, 45, 46)' );
				await expect( field.locator( '.jp-form-builder__error' ) ).not.toBeEmpty();
				await expect( field.locator( '> label' ) ).toBeVisible();
			}
			await expect( page.locator( '.jp-form-builder__error-summary' ) ).toHaveCSS( 'border-top-color', 'rgb(179, 45, 46)' );
			await expect( page.locator( '.jp-form-builder__required' ).first() ).toBeVisible();
		} );
	}

	test( 'takes screenshots of every variation', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		for ( const variation of VARIATIONS ) {
			await page.goto( FORM_PAGE );
			const wrapper = page.locator( '.wp-block-jp-form-builder-embed' );
			await wrapper.evaluate( ( element, name ) => element.classList.add( `is-style-${ name }` ), variation );
			const area = await box( wrapper );
			const clip = { x: Math.max( area.x - 24, 0 ), y: Math.max( area.y - 24, 0 ), width: area.width + 48, height: area.height + 48 };
			await page.screenshot( { path: `${ SCREENSHOT_DIR }/${ variation }.png`, clip, fullPage: true } );
			await page.locator( 'form[data-form-id] input[name="name"]' ).focus();
			await page.screenshot( { path: `${ SCREENSHOT_DIR }/${ variation }-focus.png`, clip, fullPage: true } );
			await page.locator( 'form[data-form-id] button[type="submit"]' ).click( { force: true } );
			await expect( page.locator( '.jp-form-builder__error-summary' ) ).toBeFocused();
			const errorArea = await box( wrapper );
			await page.screenshot( { path: `${ SCREENSHOT_DIR }/${ variation }-error.png`, clip: { ...clip, height: errorArea.height + 48 }, fullPage: true } );
		}
	} );
} );
