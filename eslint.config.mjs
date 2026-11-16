// Block build lint config. Extends the @wordpress/scripts default; the
// @wordpress/* imports are externals provided by WordPress at runtime, so they
// are not installed as packages and must not fail import resolution.
import wpScriptsConfig from '@wordpress/scripts/config/eslint.config.cjs';

const wordpressExternals = [
	'@wordpress/api-fetch',
	'@wordpress/block-editor',
	'@wordpress/blocks',
	'@wordpress/components',
	'@wordpress/data',
	'@wordpress/editor',
	'@wordpress/element',
	'@wordpress/i18n',
	'@wordpress/plugins',
	'@wordpress/server-side-render',
	'@wordpress/url',
];

export default [
	...wpScriptsConfig,
	{
		settings: {
			'import/core-modules': wordpressExternals,
		},
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
		},
	},
];
