/**
 * Post/page title editor-scope check.
 */

import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

addFilter(
	'editor.validateEditor',
	'accessibility-lab-core-blocks/post-title',
	( isValid: boolean, checkName: string ) => {
		if (
			checkName !== 'post_title_required_post' &&
			checkName !== 'post_title_required_page'
		) {
			return isValid;
		}
		const currentPost = select( editorStore ) as unknown as {
			getCurrentPost: () => { type?: string };
			getEditedPostAttribute: ( name: string ) => unknown;
		};
		const postType = currentPost.getCurrentPost().type;
		if ( checkName === 'post_title_required_post' && postType !== 'post' ) {
			return true;
		}
		if ( checkName === 'post_title_required_page' && postType !== 'page' ) {
			return true;
		}
		const title = String(
			currentPost.getEditedPostAttribute( 'title' ) ?? ''
		).trim();
		return title.length > 0;
	}
);
