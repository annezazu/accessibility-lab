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

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/table',
	( isValid: boolean, blockType: string, attributes: TableAttributes, checkName: string ) => {
		if ( blockType !== 'core/table' ) {
			return isValid;
		}

		if ( checkName !== 'check_table_headers' ) {
			return isValid;
		}

		const hasHead = Array.isArray( attributes.head ) && attributes.head.length > 0;
		if ( hasHead ) {
			return true;
		}
		const firstRow = attributes.body?.[ 0 ];
		if ( firstRow?.cells?.every( ( c ) => c.tag === 'th' ) ) {
			return true;
		}
		return false;
	}
);
