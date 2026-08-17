/**
 * Block Validation Framework — editor runtime.
 *
 * Wires the `editor.validateBlock` / `editor.validateMeta` / `editor.validateEditor`
 * filters into the `accessibility-lab/validation` data store, drives visual
 * indicators, and locks post save when errors are present.
 *
 * Filter signatures — see docs/features/validation-api.md:
 *
 *   editor.validateBlock  ( isValid, blockName, attributes, checkName, check, clientId )
 *   editor.validateMeta   ( isValid, metaKey, value, check )
 *   editor.validateEditor ( isValid, checkName, check )
 *
 * Each returns a boolean, and must return `isValid` untouched for checks it
 * does not own. Editor-scope filters get no document state and are expected
 * to read it from the editor/block-editor stores themselves.
 *
 * Architecture: everything is driven by React hooks mounted inside the
 * PluginSidebar render tree — not by top-level subscribe() calls on the
 * data registry. Registry-wide subscribers fire on every dispatch across
 * every store and, if they dispatch back, deadlock the editor.
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';
import { addFilter, applyFilters } from '@wordpress/hooks';

import './store';
import { VALIDATION_STORE, type Issue, type IssueSeverity } from './store';
import './sidebar';
import './style.scss';

type ResolvedCheck = {
	scope: 'block' | 'meta' | 'editor';
	namespace: string;
	name: string;
	level: string;
	resolved_level?: string;
	description: string;
	error_msg: string;
	warning_msg: string;
	plugin_title: string;
	configurable: boolean;
	block_type?: string;
	post_type?: string;
	meta_key?: string;
};

type FrameworkSettings = {
	checks?: {
		block?: ResolvedCheck[];
		meta?: ResolvedCheck[];
		editor?: ResolvedCheck[];
	};
};

type BlockLike = {
	clientId: string;
	name: string;
	attributes?: Record< string, unknown >;
	innerBlocks?: BlockLike[];
};

function isActiveLevel( level: string ): level is IssueSeverity {
	return level === 'error' || level === 'warning';
}

function messageFor( check: ResolvedCheck, level: IssueSeverity ): string {
	if ( level === 'error' ) {
		return check.error_msg || check.warning_msg || check.description;
	}
	return check.warning_msg || check.error_msg || check.description;
}

function computeBlockIssues(
	clientId: string,
	blockName: string,
	attributes: Record< string, unknown >,
	checks: ResolvedCheck[]
): Issue[] {
	const issues: Issue[] = [];
	for ( const check of checks ) {
		if ( check.block_type !== blockName ) {
			continue;
		}
		const level = ( check.resolved_level ?? check.level ) as string;
		if ( ! isActiveLevel( level ) ) {
			continue;
		}
		let isValid = true;
		try {
			isValid = applyFilters(
				'editor.validateBlock',
				true,
				blockName,
				attributes,
				check.name,
				check,
				// Attributes alone can't express structural rules; the
				// clientId lets a filter reach the block tree via
				// select( blockEditorStore ).getBlock( clientId ).
				clientId
			) as boolean;
		} catch {
			continue;
		}
		if ( isValid ) {
			continue;
		}
		issues.push( {
			id: `block:${ clientId }:${ check.namespace }:${ check.name }`,
			severity: level,
			scope: 'block',
			message: messageFor( check, level ),
			clientId,
			blockType: blockName,
			checkName: check.name,
			namespace: check.namespace,
		} );
	}
	return issues;
}

function computeMetaIssues(
	postType: string,
	meta: Record< string, unknown >,
	checks: ResolvedCheck[]
): Issue[] {
	const issues: Issue[] = [];
	for ( const check of checks ) {
		if ( check.post_type !== postType ) {
			continue;
		}
		const metaKey = check.meta_key ?? '';
		const level = ( check.resolved_level ?? check.level ) as string;
		if ( ! isActiveLevel( level ) ) {
			continue;
		}
		let isValid = true;
		try {
			isValid = applyFilters(
				'editor.validateMeta',
				true,
				metaKey,
				meta[ metaKey ],
				check
			) as boolean;
		} catch {
			continue;
		}
		if ( isValid ) {
			continue;
		}
		issues.push( {
			id: `meta:${ metaKey }:${ check.namespace }:${ check.name }`,
			severity: level,
			scope: 'meta',
			message: messageFor( check, level ),
			metaKey,
			checkName: check.name,
			namespace: check.namespace,
		} );
	}
	return issues;
}

function computeEditorIssues( postType: string, checks: ResolvedCheck[] ): Issue[] {
	const issues: Issue[] = [];
	for ( const check of checks ) {
		if ( check.post_type !== postType && check.post_type !== '*' ) {
			continue;
		}
		const level = ( check.resolved_level ?? check.level ) as string;
		if ( ! isActiveLevel( level ) ) {
			continue;
		}
		let isValid = true;
		try {
			isValid = applyFilters( 'editor.validateEditor', true, check.name, check ) as boolean;
		} catch {
			continue;
		}
		if ( isValid ) {
			continue;
		}
		issues.push( {
			id: `editor:_:${ check.namespace }:${ check.name }`,
			severity: level,
			scope: 'editor',
			message: messageFor( check, level ),
			checkName: check.name,
			namespace: check.namespace,
		} );
	}
	return issues;
}

function walkBlocks( blocks: BlockLike[], visit: ( b: BlockLike ) => void ): void {
	for ( const block of blocks ) {
		visit( block );
		if ( block.innerBlocks?.length ) {
			walkBlocks( block.innerBlocks, visit );
		}
	}
}

/**
 * Read the server-injected checks from editor settings. Returns a stable
 * shape even when the editor store isn't ready yet.
 */
export function useFrameworkChecks(): {
	block: ResolvedCheck[];
	meta: ResolvedCheck[];
	editor: ResolvedCheck[];
} {
	return useSelect( ( select ) => {
		try {
			const editorSelect = select( editorStore ) as unknown as {
				getEditorSettings?: () => Record< string, unknown >;
			};
			const settings = editorSelect?.getEditorSettings?.() ?? {};
			const api = ( settings.validationApi as FrameworkSettings ) ?? {};
			return {
				block: api.checks?.block ?? [],
				meta: api.checks?.meta ?? [],
				editor: api.checks?.editor ?? [],
			};
		} catch {
			return { block: [], meta: [], editor: [] };
		}
	}, [] );
}

/**
 * Recompute validation issues whenever the block tree, post meta, or check
 * registry changes, and mirror them into the validation store.
 *
 * Runs inside a React component (see sidebar.tsx). Uses `useEffect` for the
 * dispatch side effect so we never dispatch from a selector. Any errors
 * inside the effect are logged and swallowed — this must never take the
 * editor down.
 */
export function useValidationSync(): void {
	const checks = useFrameworkChecks();

	const { blocks, meta, postType } = useSelect( ( select ) => {
		try {
			const blockEditor = select( blockEditorStore ) as unknown as {
				getBlocks?: () => BlockLike[];
			};
			const editor = select( editorStore ) as unknown as {
				getCurrentPost?: () => { type?: string; meta?: Record< string, unknown > };
			};
			const currentPost = editor?.getCurrentPost?.() ?? {};
			return {
				blocks: blockEditor?.getBlocks?.() ?? [],
				meta: currentPost.meta ?? {},
				postType: currentPost.type ?? '',
			};
		} catch {
			return { blocks: [] as BlockLike[], meta: {}, postType: '' };
		}
	}, [] );

	// Fingerprint the inputs so effects only re-run on real changes. Without
	// this, useEffect deps see fresh object references on every render and
	// dispatch every frame — same failure mode as a runaway subscribe loop.
	const blocksFingerprint = useMemo( () => {
		const parts: string[] = [];
		walkBlocks( blocks, ( b ) => {
			parts.push( `${ b.clientId }:${ b.name }:${ JSON.stringify( b.attributes ?? {} ) }` );
		} );
		return parts.join( '|' );
	}, [ blocks ] );

	const metaFingerprint = useMemo( () => JSON.stringify( meta ), [ meta ] );

	const { setIssues } = useDispatch( VALIDATION_STORE ) as unknown as {
		setIssues: ( scope: Issue[ 'scope' ], key: string, issues: Issue[] ) => void;
	};

	// Track which per-block/meta keys we've written so we can clear them
	// when a block is removed or a meta key disappears.
	const knownBlockKeys = useRef< Set< string > >( new Set() );
	const knownMetaKeys = useRef< Set< string > >( new Set() );

	// Block-scope validation.
	useEffect( () => {
		if ( ! setIssues ) {
			return;
		}
		const seen = new Set< string >();
		try {
			walkBlocks( blocks, ( block ) => {
				seen.add( block.clientId );
				const issues = computeBlockIssues(
					block.clientId,
					block.name,
					block.attributes ?? {},
					checks.block
				);
				setIssues( 'block', block.clientId, issues );
			} );
			// Clear stale entries for blocks that have been removed.
			for ( const clientId of knownBlockKeys.current ) {
				if ( ! seen.has( clientId ) ) {
					setIssues( 'block', clientId, [] );
				}
			}
			knownBlockKeys.current = seen;
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn( 'Accessibility Lab: block validation failed', err );
		}
	}, [ blocksFingerprint, checks.block, setIssues ] );

	// Meta-scope validation.
	useEffect( () => {
		if ( ! setIssues ) {
			return;
		}
		const seen = new Set< string >();
		try {
			const perKey = new Map< string, Issue[] >();
			const relevantChecks = checks.meta.filter( ( c ) => c.post_type === postType );
			for ( const check of relevantChecks ) {
				const key = check.meta_key ?? '';
				perKey.set( key, [ ...( perKey.get( key ) ?? [] ) ] );
			}
			const allIssues = computeMetaIssues( postType, meta, checks.meta );
			for ( const issue of allIssues ) {
				const key = issue.metaKey ?? '';
				perKey.set( key, [ ...( perKey.get( key ) ?? [] ), issue ] );
			}
			for ( const [ key, issues ] of perKey ) {
				seen.add( key );
				setIssues( 'meta', key, issues );
			}
			for ( const key of knownMetaKeys.current ) {
				if ( ! seen.has( key ) ) {
					setIssues( 'meta', key, [] );
				}
			}
			knownMetaKeys.current = seen;
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn( 'Accessibility Lab: meta validation failed', err );
		}
	}, [ metaFingerprint, postType, checks.meta, setIssues ] );

	// Editor-scope validation.
	useEffect( () => {
		if ( ! setIssues ) {
			return;
		}
		try {
			const issues = computeEditorIssues( postType, checks.editor );
			setIssues( 'editor', '_', issues );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn( 'Accessibility Lab: editor validation failed', err );
		}
	}, [ postType, checks.editor, blocksFingerprint, metaFingerprint, setIssues ] );
}

/**
 * Lock/unlock post save based on validation errors, and toggle body classes.
 *
 * Only dispatches when the lock state actually changes — this is what
 * previously caused the editor to hang: an unconditional dispatch inside a
 * `subscribe()` callback recursed via its own store change.
 */
const LOCK_KEY = 'accessibility-lab-validation';

export function useValidationConsequences(): void {
	const { hasErrors, hasWarnings } = useSelect( ( select ) => {
		try {
			const store = select( VALIDATION_STORE ) as unknown as {
				hasErrors?: () => boolean;
				hasWarnings?: () => boolean;
			} | null;
			return {
				hasErrors: Boolean( store?.hasErrors?.() ),
				hasWarnings: Boolean( store?.hasWarnings?.() ),
			};
		} catch {
			return { hasErrors: false, hasWarnings: false };
		}
	}, [] );

	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore ) as unknown as {
		lockPostSaving?: ( key: string ) => void;
		unlockPostSaving?: ( key: string ) => void;
	};

	const lockedRef = useRef< boolean >( false );

	useEffect( () => {
		try {
			if ( hasErrors && ! lockedRef.current ) {
				lockPostSaving?.( LOCK_KEY );
				lockedRef.current = true;
			} else if ( ! hasErrors && lockedRef.current ) {
				unlockPostSaving?.( LOCK_KEY );
				lockedRef.current = false;
			}
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn( 'Accessibility Lab: publish lock update failed', err );
		}
	}, [ hasErrors, lockPostSaving, unlockPostSaving ] );

	useEffect( () => {
		if ( typeof document === 'undefined' || ! document.body ) {
			return;
		}
		document.body.classList.toggle( 'has-validation-errors', hasErrors );
		document.body.classList.toggle( 'has-validation-warnings', hasWarnings && ! hasErrors );
		return () => {
			document.body?.classList.remove(
				'has-validation-errors',
				'has-validation-warnings'
			);
		};
	}, [ hasErrors, hasWarnings ] );

	// Also gate the actual save via editor.preSavePost so bypasses (autosave,
	// keyboard shortcuts) still respect the lock.
	useEffect( () => {
		const filterName = 'editor.preSavePost';
		const namespace = 'accessibility-lab/validation-safety-net';
		addFilter( filterName, namespace, async ( edits: unknown ) => {
			if ( lockedRef.current ) {
				return Promise.reject(
					new Error(
						'Accessibility Lab: cannot save while validation errors remain.'
					)
				);
			}
			return edits;
		} );
		// No unregister — filter is registered once for the lifetime of the
		// sidebar plugin, which lives as long as the editor.
	}, [] );
}

/**
 * Wrap every BlockListBlock with a `validation-api-block-{severity}` class
 * so blocks with issues get a visible border. Uses `useSelect` inside a
 * render component so re-renders happen only when the specific block's
 * issue state changes.
 */
addFilter(
	'editor.BlockListBlock',
	'accessibility-lab/validation-block-className',
	( BlockListBlock: unknown ) => {
		const wp = ( window as unknown as {
			wp?: {
				element?: { createElement?: ( t: unknown, p: unknown ) => unknown };
				data?: { useSelect?: ( fn: unknown, deps: unknown[] ) => unknown };
			};
		} ).wp;
		const createElement = wp?.element?.createElement;
		const useSelectRuntime = wp?.data?.useSelect;
		if ( ! createElement || ! useSelectRuntime ) {
			return BlockListBlock;
		}
		const Wrapped = ( props: { clientId: string; className?: string } ) => {
			const severity = useSelectRuntime(
				( selectFn: unknown ) => {
					try {
						const store = ( selectFn as ( n: string ) => unknown )(
							VALIDATION_STORE
						) as { getIssuesForBlock?: ( id: string ) => Issue[] } | null;
						const issues = store?.getIssuesForBlock?.( props.clientId ) ?? [];
						if ( issues.some( ( i ) => i.severity === 'error' ) ) {
							return 'error';
						}
						if ( issues.some( ( i ) => i.severity === 'warning' ) ) {
							return 'warning';
						}
						return '';
					} catch {
						return '';
					}
				},
				[ props.clientId ]
			) as string;
			const extra = severity ? ` validation-api-block-${ severity }` : '';
			return createElement( BlockListBlock, {
				...props,
				className: ( props.className ?? '' ) + extra,
			} );
		};
		( Wrapped as unknown as { displayName?: string } ).displayName =
			'AccessibilityLabValidationBlock';
		return Wrapped;
	}
);
