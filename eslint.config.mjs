// Block build lint config. Extends the @wordpress/scripts default; the
// @wordpress/* imports are externals provided by WordPress at runtime, so they
// are not installed as packages and must not fail import resolution.
import wpScriptsConfig from '@wordpress/scripts/config/eslint.config.cjs';

export default [
	...wpScriptsConfig,
	{
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
		},
	},
];
