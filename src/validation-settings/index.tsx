/**
 * Mount point for the Validation Settings admin app.
 *
 * The app is pulled in with `require` inside a try/catch rather than a static
 * import so that a failure while its module graph evaluates is catchable.
 * The dataviews package opts into core's private APIs at module scope, which
 * throws if a WordPress release ever drops it from the allowlist — and that
 * happens before React exists, so an error boundary cannot see it. A
 * synchronous require keeps this to a single chunk; a dynamic import() would
 * split a second file for no benefit.
 */

import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { ErrorBoundary } from './components/ErrorBoundary';
import './style.scss';

const mount = document.getElementById(
	'accessibility-lab-validation-settings'
);

if ( mount ) {
	const root = createRoot( mount );
	try {
		const { App } = require( './App' ) as {
			App: () => JSX.Element;
		};
		root.render(
			<ErrorBoundary>
				<App />
			</ErrorBoundary>
		);
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error(
			'Accessibility Lab: validation settings failed to load',
			error
		);
		root.render(
			<div className="accessibility-lab-validation-error">
				<h1>{ __( 'Validation', 'accessibility-lab' ) }</h1>
				<p>
					{ __(
						'The validation settings screen could not be loaded. Check the browser console for details.',
						'accessibility-lab'
					) }
				</p>
			</div>
		);
	}
}
