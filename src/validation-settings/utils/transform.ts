/**
 * Converts between the REST payload shape and DataViews rows.
 *
 * The REST response keeps checks grouped by scope; the table wants one flat
 * list. Going the other way, only rows that actually differ from their
 * registered default are persisted, so defaults keep tracking whichever
 * plugin registered the check.
 */

import type {
	Check,
	CheckType,
	ChecksPayload,
	Level,
	Row,
	Scope,
} from '../types';

const { blockTitles = {}, postTypeLabels = {} } =
	window.accessibilityLabValidation ?? {};

const SCOPE_LABELS: Record< Scope, CheckType > = {
	block: 'Block',
	meta: 'Meta',
	editor: 'Editor',
};

/**
 * Human-readable description of what a check is attached to.
 *
 * @param check Check to describe.
 */
function targetLabel( check: Check ): string {
	if ( check.scope === 'block' ) {
		const slug = check.block_type ?? '';
		return blockTitles[ slug ] ?? slug;
	}

	const postType = check.post_type ?? '';
	const postTypeLabel = postTypeLabels[ postType ] ?? postType;

	if ( check.scope === 'meta' ) {
		return `${ check.meta_key ?? '' } (${ postTypeLabel })`;
	}

	return postTypeLabel;
}

/**
 * Flattens the payload into table rows, resolving each row's effective level.
 *
 * Checks registered with `configurable: false` are omitted — there is nothing
 * to configure, so listing them would only be noise.
 *
 * @param payload   Response from GET /wp-validation/v1/checks.
 * @param overrides Stored overrides, keyed by check id.
 */
export function checksToRows(
	payload: ChecksPayload,
	overrides: Record< string, Level >
): Row[] {
	const all: Check[] = [
		...( payload.checks?.block ?? [] ),
		...( payload.checks?.meta ?? [] ),
		...( payload.checks?.editor ?? [] ),
	];

	return all
		.filter( ( check ) => check.configurable )
		.map( ( check ) => {
			const override = overrides[ check.id ];
			return {
				id: check.id,
				title: check.title || check.name,
				name: check.name,
				description: check.description,
				check_type: SCOPE_LABELS[ check.scope ],
				target: targetLabel( check ),
				plugin_title: check.plugin_title || check.namespace,
				level: override ?? check.level,
				default_level: check.level,
				has_override: override !== undefined,
			};
		} );
}

/**
 * Inverse of `checksToRows` — the map POSTed back to the server.
 *
 * @param rows Current table rows.
 */
export function rowsToOverrides( rows: Row[] ): Record< string, Level > {
	const out: Record< string, Level > = {};
	for ( const row of rows ) {
		if ( row.has_override ) {
			out[ row.id ] = row.level;
		}
	}
	return out;
}
