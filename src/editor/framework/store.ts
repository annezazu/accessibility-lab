/**
 * `accessibility-lab/validation` @wordpress/data store.
 *
 * Holds the current set of validation issues discovered by running the
 * `editor.validateBlock`, `editor.validateMeta`, and `editor.validateEditor`
 * filters. UI subscribes here; publish-lock reads here.
 *
 * The `core/` namespace is reserved for WordPress core stores — using it
 * previously caused the editor to white-screen when core registered a
 * conflicting store on that key.
 */

import { createReduxStore, register } from '@wordpress/data';

export type IssueSeverity = 'error' | 'warning';

export type Issue = {
	id: string; // unique key
	severity: IssueSeverity;
	scope: 'block' | 'meta' | 'editor';
	message: string;
	// Block scope
	clientId?: string;
	blockType?: string;
	// Meta scope
	metaKey?: string;
	// Common
	checkName: string;
	namespace: string;
};

type State = {
	issues: Record< string, Issue >;
};

const initial: State = { issues: {} };

const actions = {
	setIssues( scope: Issue[ 'scope' ], key: string, issues: Issue[] ) {
		return { type: 'SET_ISSUES', scope, key, issues } as const;
	},
	clearAll() {
		return { type: 'CLEAR_ALL' } as const;
	},
};

type Action = ReturnType< ( typeof actions )[ keyof typeof actions ] >;

const reducer = ( state: State = initial, action: Action ): State => {
	switch ( action.type ) {
		case 'SET_ISSUES': {
			// Replace all issues whose id begins with `${scope}:${key}:`.
			const prefix = `${ action.scope }:${ action.key }:`;
			const remaining = Object.fromEntries(
				Object.entries( state.issues ).filter( ( [ id ] ) => ! id.startsWith( prefix ) )
			);
			for ( const issue of action.issues ) {
				remaining[ issue.id ] = issue;
			}
			return { ...state, issues: remaining };
		}
		case 'CLEAR_ALL':
			return { ...state, issues: {} };
		default:
			return state;
	}
};

const selectors = {
	getIssues( state: State ): Issue[] {
		return Object.values( state.issues );
	},
	getIssuesForBlock( state: State, clientId: string ): Issue[] {
		return Object.values( state.issues ).filter( ( i ) => i.clientId === clientId );
	},
	hasErrors( state: State ): boolean {
		return Object.values( state.issues ).some( ( i ) => i.severity === 'error' );
	},
	hasWarnings( state: State ): boolean {
		return Object.values( state.issues ).some( ( i ) => i.severity === 'warning' );
	},
	errorCount( state: State ): number {
		return Object.values( state.issues ).filter( ( i ) => i.severity === 'error' ).length;
	},
	warningCount( state: State ): number {
		return Object.values( state.issues ).filter( ( i ) => i.severity === 'warning' ).length;
	},
};

export const VALIDATION_STORE = 'accessibility-lab/validation';

export const validationStore = createReduxStore( VALIDATION_STORE, {
	reducer,
	actions,
	selectors,
} );

// `@wordpress/data` throws on duplicate registration. That can happen when
// this module is bundled into more than one editor entry point (framework
// + core-block-rules) that both run on the same page.
try {
	register( validationStore );
} catch {
	/* already registered */
}
