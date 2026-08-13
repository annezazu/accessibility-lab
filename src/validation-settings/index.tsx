/**
 * Validation Settings admin app.
 *
 * Renders a table of every registered validation check, filtered by the
 * `namespace` query arg. Admins pick a severity override (error / warning /
 * disabled) per check; overrides that match the check's default aren't saved.
 */

import { Page } from '@wordpress/admin-ui';
import apiFetch from '@wordpress/api-fetch';
import { SelectControl, SnackbarList, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	createRoot,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import './style.scss';

type Scope = 'block' | 'meta' | 'editor';
type Level = 'error' | 'warning' | 'none';
type Override = 'error' | 'warning' | 'disabled';

type Check = {
	scope: Scope;
	namespace: string;
	name: string;
	level: Level;
	resolved_level: Level;
	description: string;
	error_msg: string;
	warning_msg: string;
	plugin_title: string;
	configurable: boolean;
	block_type?: string;
	post_type?: string;
	meta_key?: string;
};

type ChecksPayload = {
	checks: {
		block: Check[];
		meta: Check[];
		editor: Check[];
	};
	namespaces: string[];
};

type OverridesPayload = {
	overrides: Record< string, Override >;
};

const CHECKS_PATH = '/wp-validation/v1/checks';
const OVERRIDES_PATH = '/accessibility-lab/v1/validation-settings';

function getNamespaceFromUrl(): string | null {
	const params = new URLSearchParams( window.location.search );
	// The admin page slug encodes namespace after the parent slug.
	const page = params.get( 'page' ) ?? '';
	if ( page.startsWith( 'accessibility-lab-validation-' ) ) {
		return page.replace( 'accessibility-lab-validation-', '' );
	}
	return null; // First (parent) page → show first-registered namespace.
}

function overrideKey( check: Check ): string {
	return `${ check.scope }:${ check.namespace }:${ check.name }`;
}

function targetLabel( check: Check ): string {
	if ( check.scope === 'block' ) {
		return check.block_type ?? '';
	}
	if ( check.scope === 'meta' ) {
		return `${ check.meta_key } (${ check.post_type })`;
	}
	if ( check.scope === 'editor' ) {
		return check.post_type ?? '';
	}
	return '';
}

function levelToOverride( level: Level ): Override {
	if ( level === 'none' ) {
		return 'disabled';
	}
	return level;
}

function Snackbars(): JSX.Element {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( n ) => n.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	return <SnackbarList notices={ notices } onRemove={ removeNotice } />;
}

function SettingsApp(): JSX.Element {
	const [ payload, setPayload ] = useState< ChecksPayload | null >( null );
	const [ overrides, setOverrides ] = useState< Record< string, Override > >(
		{}
	);
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		Promise.all( [
			apiFetch< ChecksPayload >( { path: CHECKS_PATH } ),
			apiFetch< OverridesPayload >( { path: OVERRIDES_PATH } ),
		] )
			.then( ( [ checksData, overridesData ] ) => {
				setPayload( checksData );
				setOverrides( overridesData.overrides ?? {} );
			} )
			.catch( () => {
				createErrorNotice(
					__(
						'Failed to load validation settings.',
						'accessibility-lab'
					),
					{
						type: 'snackbar',
					}
				);
			} );
	}, [ createErrorNotice ] );

	const selectedNamespace = useMemo( () => {
		const fromUrl = getNamespaceFromUrl();
		if ( fromUrl ) {
			return fromUrl;
		}
		return payload?.namespaces?.[ 0 ] ?? '';
	}, [ payload ] );

	const visibleChecks = useMemo< Check[] >( () => {
		if ( ! payload ) {
			return [];
		}
		const all: Check[] = [
			...payload.checks.block,
			...payload.checks.meta,
			...payload.checks.editor,
		];
		return all.filter( ( c ) => c.namespace === selectedNamespace );
	}, [ payload, selectedNamespace ] );

	const handleChange = useCallback(
		async ( check: Check, next: Override ) => {
			const key = overrideKey( check );
			const declaredDefault: Override = levelToOverride( check.level );
			const nextOverrides = { ...overrides };
			if ( next === declaredDefault ) {
				delete nextOverrides[ key ];
			} else {
				nextOverrides[ key ] = next;
			}
			const previous = overrides;
			setOverrides( nextOverrides );
			try {
				const saved = await apiFetch< OverridesPayload >( {
					path: OVERRIDES_PATH,
					method: 'POST',
					data: { overrides: nextOverrides },
				} );
				setOverrides( saved.overrides ?? {} );
				createSuccessNotice(
					sprintf(
						/* translators: %s: check name. */
						__( 'Saved override for %s.', 'accessibility-lab' ),
						check.name
					),
					{ type: 'snackbar' }
				);
			} catch {
				setOverrides( previous );
				createErrorNotice(
					__(
						'Failed to save. Please try again.',
						'accessibility-lab'
					),
					{
						type: 'snackbar',
					}
				);
			}
		},
		[ overrides, createSuccessNotice, createErrorNotice ]
	);

	if ( ! payload ) {
		return (
			<Page title={ __( 'Validation Settings', 'accessibility-lab' ) }>
				<p role="status" aria-live="polite">
					<Spinner />
					<span className="screen-reader-text">
						{ __( 'Loading…', 'accessibility-lab' ) }
					</span>
				</p>
			</Page>
		);
	}

	const namespaceLabel =
		visibleChecks[ 0 ]?.plugin_title ||
		selectedNamespace ||
		__( 'Validation', 'accessibility-lab' );

	return (
		<div className="accessibility-lab-page-wrapper">
			<Page
				title={ namespaceLabel }
				description={ sprintf(
					/* translators: %s: namespace slug. */
					__(
						'Severity overrides for checks registered under namespace "%s".',
						'accessibility-lab'
					),
					selectedNamespace
				) }
			>
				{ visibleChecks.length === 0 ? (
					<p>
						{ __(
							'No checks registered for this namespace.',
							'accessibility-lab'
						) }
					</p>
				) : (
					<table className="wp-list-table widefat striped">
						<thead>
							<tr>
								<th>
									{ __( 'Description', 'accessibility-lab' ) }
								</th>
								<th>{ __( 'Target', 'accessibility-lab' ) }</th>
								<th>{ __( 'Scope', 'accessibility-lab' ) }</th>
								<th>{ __( 'Level', 'accessibility-lab' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ visibleChecks.map( ( check ) => {
								const key = overrideKey( check );
								const current: Override =
									overrides[ key ] ??
									levelToOverride( check.resolved_level );
								return (
									<tr key={ key }>
										<td>
											<strong>{ check.name }</strong>
											<br />
											<span>{ check.description }</span>
										</td>
										<td>
											<code>
												{ targetLabel( check ) }
											</code>
										</td>
										<td>{ check.scope }</td>
										<td>
											<SelectControl
												label={ __(
													'Level',
													'accessibility-lab'
												) }
												hideLabelFromVision
												value={ current }
												disabled={
													! check.configurable
												}
												options={ [
													{
														label: __(
															'Error',
															'accessibility-lab'
														),
														value: 'error',
													},
													{
														label: __(
															'Warning',
															'accessibility-lab'
														),
														value: 'warning',
													},
													{
														label: __(
															'Disabled',
															'accessibility-lab'
														),
														value: 'disabled',
													},
												] }
												onChange={ ( value ) =>
													handleChange(
														check,
														value as Override
													)
												}
											/>
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				) }
				<Snackbars />
			</Page>
		</div>
	);
}

const mount = document.getElementById(
	'accessibility-lab-validation-settings'
);
if ( mount ) {
	createRoot( mount ).render( <SettingsApp /> );
}
