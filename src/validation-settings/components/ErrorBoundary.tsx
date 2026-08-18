/**
 * Catches render-time failures so the Validation screen degrades to a
 * readable message rather than a blank page.
 *
 * Note this only covers errors thrown while React renders. A failure during
 * module evaluation — the private-APIs opt-in in @wordpress/dataviews is one —
 * happens before any of this mounts, and is handled by the load guard in
 * index.tsx instead.
 */

import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { ReactNode } from 'react';

type Props = { children: ReactNode };
type State = { hasError: boolean };

export class ErrorBoundary extends Component< Props, State > {
	constructor( props: Props ) {
		super( props );
		this.state = { hasError: false };
	}

	static getDerivedStateFromError(): State {
		return { hasError: true };
	}

	componentDidCatch( error: Error ): void {
		// eslint-disable-next-line no-console
		console.error(
			'Accessibility Lab: validation settings crashed',
			error
		);
	}

	render(): ReactNode {
		if ( this.state.hasError ) {
			return (
				<div className="accessibility-lab-validation-error">
					<h1>{ __( 'Validation', 'accessibility-lab' ) }</h1>
					<p>
						{ __(
							'The validation settings screen could not be displayed. Check the browser console for details.',
							'accessibility-lab'
						) }
					</p>
				</div>
			);
		}
		return this.props.children;
	}
}
