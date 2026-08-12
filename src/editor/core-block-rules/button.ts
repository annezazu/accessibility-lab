/**
 * core/button validation logic.
 */

import { addFilter } from '@wordpress/hooks';

function stripHtml( html: string ): string {
	return html.replace( /<[^>]*>/g, '' ).trim();
}

/**
 * Minimal URL sanity check. A full Public Suffix List (PSL) integration is
 * planned as a follow-up — for now we accept any parseable http(s), mailto:,
 * tel:, or root-relative URL.
 */
function isPlausibleUrl( raw: string ): boolean {
	const value = raw.trim();
	if ( ! value ) {
		return false;
	}
	if ( value.startsWith( '/' ) || value.startsWith( '#' ) ) {
		return true;
	}
	if ( /^(mailto:|tel:)/i.test( value ) ) {
		return value.length > 7;
	}
	try {
		const parsed = new URL( value, 'https://placeholder.invalid' );
		if ( parsed.protocol !== 'http:' && parsed.protocol !== 'https:' ) {
			return false;
		}
		return parsed.hostname.includes( '.' );
	} catch {
		return false;
	}
}

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/button',
	( isValid: boolean, blockType: string, attributes: Record< string, unknown >, checkName: string ) => {
		if ( blockType !== 'core/button' ) {
			return isValid;
		}

		switch ( checkName ) {
			case 'check_button_text': {
				const text = stripHtml( String( attributes.text ?? '' ) );
				return /[\p{L}\p{N}]/u.test( text );
			}

			case 'check_button_link': {
				// <button> tag renders when tagName === 'button' — no URL required.
				const tagName = String( attributes.tagName ?? 'a' );
				if ( tagName === 'button' ) {
					return true;
				}
				const url = String( attributes.url ?? '' );
				if ( ! url ) {
					return false;
				}
				return isPlausibleUrl( url );
			}

			default:
				return isValid;
		}
	}
);
