/**
 * core/image validation logic.
 */

import { addFilter } from '@wordpress/hooks';

const GENERIC_WORDS = [ 'image', 'photo', 'pic', 'picture', 'graphic', 'img' ];
const GENERIC_PHRASE = /^(image|photo|pic|picture|graphic)\s+of\b/i;
const FILENAME = /\.(png|jpe?g|gif|webp|svg|bmp|avif)$/i;

function stripHtml( html: string ): string {
	return html.replace( /<[^>]*>/g, '' ).trim();
}

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/image',
	(
		isValid: boolean,
		blockType: string,
		attributes: Record< string, unknown >,
		checkName: string
	) => {
		if ( blockType !== 'core/image' ) {
			return isValid;
		}
		const alt = String( attributes.alt ?? '' ).trim();
		const isDecorative = Boolean( attributes.isDecorative );
		const hasImage = Boolean( attributes.url || attributes.id );

		switch ( checkName ) {
			case 'check_image_alt_text':
				return hasImage && ! isDecorative ? alt.length > 0 : true;

			case 'check_image_alt_text_length':
				if ( ! alt ) {
					return true;
				}
				return alt.length <= 125;

			case 'check_image_alt_caption_match': {
				const caption = stripHtml( String( attributes.caption ?? '' ) );
				if ( ! alt || ! caption ) {
					return true;
				}
				return alt.toLowerCase() !== caption.toLowerCase();
			}

			case 'check_image_alt_text_patterns':
				if ( ! alt || isDecorative ) {
					return true;
				}
				if ( FILENAME.test( alt ) ) {
					return false;
				}
				const trimmed = alt.trim().toLowerCase();
				if ( GENERIC_WORDS.includes( trimmed ) ) {
					return false;
				}
				if ( GENERIC_PHRASE.test( alt ) ) {
					return false;
				}
				return true;

			default:
				return isValid;
		}
	}
);
