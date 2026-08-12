/**
 * Multi-entry webpack config for Accessibility Lab.
 *
 * Extends @wordpress/scripts' default config to build five independent
 * bundles from a single `npm run build`:
 *
 *   settings              → build/settings.js
 *   validation-framework  → build/validation-framework.js
 *   core-block-rules      → build/core-block-rules.js
 *   validation-settings   → build/validation-settings.js
 *   media-view-config     → build/media-view-config.js
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve( __dirname, 'src/settings/index.tsx' ),
		'validation-framework': path.resolve( __dirname, 'src/editor/framework/index.ts' ),
		'core-block-rules': path.resolve( __dirname, 'src/editor/core-block-rules/index.ts' ),
		'validation-settings': path.resolve( __dirname, 'src/validation-settings/index.tsx' ),
		'media-view-config': path.resolve( __dirname, 'src/media-view-config/index.ts' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
