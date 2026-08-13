const wpPlugin = require( '@wordpress/eslint-plugin' );
const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [
			'**/build/**',
			'**/node_modules/**',
			'**/vendor/**',
			'*.config.js',
		],
	},
	...wpPlugin.configs.recommended
];
