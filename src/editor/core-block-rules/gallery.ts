/**
 * core/gallery validation — delegates each image check to core/image logic
 * by iterating the gallery's inner image blocks.
 */

import { addFilter, applyFilters } from '@wordpress/hooks';

type GalleryAttributes = {
	images?: Array< Record< string, unknown > >;
};

const CHECK_MAP: Record< string, string > = {
	gallery_check_image_alt_text: 'check_image_alt_text',
	gallery_check_image_alt_text_length: 'check_image_alt_text_length',
	gallery_check_image_alt_caption_match: 'check_image_alt_caption_match',
	gallery_check_image_alt_text_patterns: 'check_image_alt_text_patterns',
};

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/gallery',
	(
		isValid: boolean,
		blockType: string,
		attributes: GalleryAttributes,
		checkName: string,
		rule: unknown
	) => {
		if ( blockType !== 'core/gallery' ) {
			return isValid;
		}
		const delegatedCheck = CHECK_MAP[ checkName ];
		if ( ! delegatedCheck ) {
			return isValid;
		}
		const images = attributes.images ?? [];
		if ( images.length === 0 ) {
			return true;
		}
		for ( const img of images ) {
			const result = applyFilters(
				'editor.validateBlock',
				true,
				'core/image',
				img,
				delegatedCheck,
				rule
			) as boolean;
			if ( ! result ) {
				return false;
			}
		}
		return true;
	}
);
