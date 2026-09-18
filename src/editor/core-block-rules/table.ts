/**
 * core/table validation logic.
 */

import { addFilter } from '@wordpress/hooks';

type Cell = { content?: string; tag?: string };
type Row = { cells?: Cell[] };
type TableAttributes = {
	head?: Row[];
	body?: Row[];
	caption?: string;
};

function stripHtml( html: string ): string {
	return html.replace( /<[^>]*>/g, '' ).trim();
}

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/table',
	(
		isValid: boolean,
		blockType: string,
		attributes: TableAttributes,
		checkName: string
	) => {
		if ( blockType !== 'core/table' ) {
			return isValid;
		}

		switch ( checkName ) {
			case 'check_table_headers': {
				const hasHead =
					Array.isArray( attributes.head ) &&
					attributes.head.length > 0;
				if ( hasHead ) {
					return true;
				}
				const firstRow = attributes.body?.[ 0 ];
				if ( firstRow?.cells?.every( ( c ) => c.tag === 'th' ) ) {
					return true;
				}
				return false;
			}

			case 'check_table_caption': {
				const caption = stripHtml( String( attributes.caption ?? '' ) );
				return caption.length > 0;
			}

			default:
				return isValid;
		}
	}
);
