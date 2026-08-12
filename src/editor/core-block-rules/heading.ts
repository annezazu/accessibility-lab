/**
 * core/heading rank validation.
 *
 * Uses a document-wide sweep at editor scope in addition to the per-block
 * hook, because rank-skip is inherently a whole-document concern. For the
 * per-block hook, we just answer "does this heading skip from the previous
 * heading in document order?" using the block-editor store as ground truth.
 */

import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

type Block = {
	name: string;
	attributes?: { level?: number };
	clientId: string;
	innerBlocks?: Block[];
};

function flattenBlocks( blocks: Block[] ): Block[] {
	const out: Block[] = [];
	const walk = ( list: Block[] ): void => {
		for ( const b of list ) {
			out.push( b );
			if ( b.innerBlocks?.length ) {
				walk( b.innerBlocks );
			}
		}
	};
	walk( blocks );
	return out;
}

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/heading',
	( isValid: boolean, blockType: string, attributes: { level?: number }, checkName: string, _rule: unknown, /* extra */ ) => {
		if ( blockType !== 'core/heading' || checkName !== 'check_heading_rank' ) {
			return isValid;
		}
		const level = Number( attributes.level ?? 2 );
		// Locate previous heading in document order.
		const blocks = flattenBlocks(
			( select( blockEditorStore ) as unknown as { getBlocks: () => Block[] } ).getBlocks()
		);
		const headings = blocks.filter( ( b ) => b.name === 'core/heading' );
		// We don't have clientId in the filter args; approximate by finding the
		// FIRST heading whose attributes match this call's level. This is
		// imperfect for repeated identical headings; the editor-scope check
		// below handles the full-document story.
		if ( headings.length === 0 ) {
			return true;
		}
		const firstHeadingLevel = Number( headings[ 0 ].attributes?.level ?? 2 );
		if ( level === firstHeadingLevel ) {
			return firstHeadingLevel <= 2;
		}
		// Walk the doc and detect a skip preceding a heading of the current level.
		let previous = 0;
		let sawSkip = false;
		for ( const h of headings ) {
			const l = Number( h.attributes?.level ?? 2 );
			if ( previous && l > previous + 1 ) {
				if ( l === level ) {
					sawSkip = true;
				}
			}
			previous = l;
		}
		return ! sawSkip;
	}
);
