/**
 * Mount point for the Validation Settings admin app.
 */

import { createRoot } from '@wordpress/element';

import { App } from './App';
import './style.scss';

const mount = document.getElementById(
	'accessibility-lab-validation-settings'
);
if ( mount ) {
	createRoot( mount ).render( <App /> );
}
