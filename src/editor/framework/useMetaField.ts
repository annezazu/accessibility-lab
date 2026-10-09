/**
 * useMetaField hook shim.
 *
 * Returns TextControl-compatible props that reflect current validation state
 * for a given meta key. Use in custom sidebar components:
 *
 *   const props = useMetaField( 'band_origin' );
 *   <TextControl {...props} />
 */

import { useSelect } from '@wordpress/data';
import { VALIDATION_STORE, type Issue } from './store';

export type MetaFieldProps = {
	className: string;
	help?: string;
};

export function useMetaField( metaKey: string ): MetaFieldProps {
	return useSelect(
		( selectFn ) => {
			const store = selectFn( VALIDATION_STORE ) as unknown as {
				getIssues: () => Issue[];
			};
			const relevant = store
				.getIssues()
				.filter( ( i ) => i.scope === 'meta' && i.metaKey === metaKey );
			if ( relevant.length === 0 ) {
				return { className: 'validation-api-meta-field' };
			}
			const worst = relevant.some( ( i ) => i.severity === 'error' )
				? 'error'
				: 'warning';
			return {
				className: `validation-api-meta-field validation-api-meta-field--${ worst }`,
				help: relevant.map( ( i ) => i.message ).join( ' ' ),
			};
		},
		[ metaKey ]
	);
}
