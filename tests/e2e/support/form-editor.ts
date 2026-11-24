import { login } from './auth';
import { expect, type Page } from './fixtures';
import { wpCli } from './wp-cli';

export type CapturedMail = { message: string };

export const CANVAS = 'iframe[name="editor-canvas"]';

export function clearCapturedMail(): void {
	wpCli( 'eval', "delete_option( 'jp_form_builder_e2e_last_mail' );" );
}

export function capturedMail(): CapturedMail {
	return JSON.parse(
		wpCli(
			'option',
			'get',
			'jp_form_builder_e2e_last_mail',
			'--format=json'
		)
	);
}

export async function openNewForm( page: Page ): Promise< void > {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=jp_form' );
	await page.waitForFunction( () =>
		// @ts-ignore wp is provided by the block editor page.
		window.wp?.data
			?.select( 'core/block-editor' )
			?.getBlocks()
			.some(
				( block: { name: string; innerBlocks: unknown[] } ) =>
					block.name === 'jp-form-builder/form' &&
					block.innerBlocks.length > 0
			)
	);
}

export type BlockSpec = {
	name: string;
	attributes?: Record< string, unknown >;
	inner?: BlockSpec[];
};

// Replaces the Form's blocks, so a test starts from a known tree instead of clicking every Field into place.
export async function setFormBlocks(
	page: Page,
	blocks: BlockSpec[]
): Promise< void > {
	await page.evaluate( ( specs ) => {
		// @ts-ignore wp is provided by the block editor page.
		const { data, blocks: wpBlocks } = window.wp;
		const build = ( spec: any ): unknown =>
			wpBlocks.createBlock(
				spec.name,
				spec.attributes ?? {},
				( spec.inner ?? [] ).map( build )
			);
		const form = data
			.select( 'core/block-editor' )
			.getBlocks()
			.find(
				( block: { name: string } ) =>
					block.name === 'jp-form-builder/form'
			);
		data.dispatch( 'core/block-editor' ).replaceInnerBlocks(
			form.clientId,
			specs.map( build )
		);
	}, blocks );
}

export async function selectBlockNamed(
	page: Page,
	name: string,
	index = 0
): Promise< void > {
	await page.evaluate(
		( { blockName, position } ) => {
			// @ts-ignore wp is provided by the block editor page.
			const { data } = window.wp;
			const flatten = ( blocks: any[] ): any[] =>
				blocks.flatMap( ( block ) => [
					block,
					...flatten( block.innerBlocks ),
				] );
			const found = flatten(
				data.select( 'core/block-editor' ).getBlocks()
			).filter( ( block ) => block.name === blockName );
			data.dispatch( 'core/block-editor' ).selectBlock(
				found[ position ].clientId
			);
		},
		{ blockName: name, position: index }
	);
}

export async function publish( page: Page ): Promise< number > {
	await page.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	await page
		.getByRole( 'region', { name: 'Editor publish' } )
		.getByRole( 'button', { name: 'Publish', exact: true } )
		.click();
	await expect(
		page.getByText( /is now live|published/i ).first()
	).toBeVisible();
	return page.evaluate(
		// @ts-ignore wp is provided by the block editor page.
		() =>
			window.wp.data.select( 'core/editor' ).getCurrentPostId() as number
	);
}

export function embedPage( formId: number, title: string ): string {
	return wpCli(
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		`--post_title=${ title }`,
		`--post_content=<!-- wp:jp-form-builder/embed {"formId":${ formId }} /-->`,
		'--porcelain'
	);
}

export async function sourceFieldOptions( page: Page ): Promise< string[] > {
	return page
		.getByLabel( 'Source Field' )
		.locator( 'option' )
		.allTextContents();
}
