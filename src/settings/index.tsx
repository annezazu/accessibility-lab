/**
 * Accessibility Lab — settings page.
 *
 * Layout follows the WordPress AI plugin:
 *   - Sticky top toolbar: beaker icon + title (left) / Docs + Contribute
 *     links + kebab (right).
 *   - Centered content column (~820 px).
 *   - One flat list of every module. Dependents render indented directly
 *     beneath their required framework module.
 *
 * Badges: "Stable" is the assumed default and is NEVER shown. We only
 * render badges for `Experiment` (from `bucket`) and `Core Track` (from
 * `track`). They're independent axes and can co-occur on the same row.
 */

import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	SnackbarList,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	createRoot,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { external } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

import './style.scss';

type Bucket = 'feature' | 'experiment';
type Track = 'core-track' | 'practical';

type Credits = {
	author: string;
	source_plugin_slug: string | null;
	source_plugin_url: string | null;
	license: string | null;
};

type Module = {
	id: string;
	bucket: Bucket;
	track: Track;
	name: string;
	description: string;
	enabled: boolean;
	credits: Credits | null;
};

type ApiPayload = {
	modules: Module[];
	settings: Record< string, boolean >;
};

type SettingsData = Record< string, boolean >;

const REST_PATH = '/accessibility-lab/v1/modules';

// Which modules require another to be enabled first.
const DEPENDS_ON: Record< string, string > = {
	core_block_validation_rules: 'block_validation_framework',
	validation_settings: 'block_validation_framework',
};

function creditsLine( credits: Credits ): string {
	return sprintf(
		/* translators: %s: original plugin author name. */
		__( 'Adopted from %s.', 'accessibility-lab' ),
		credits.author
	);
}

function UniversalAccessIcon(): JSX.Element {
	// WordPress `dashicons-universal-access`. Rendered via the icon font so
	// the glyph tracks any future dashicon updates. Dashicons are enqueued
	// as a style dep on the settings script.
	return (
		<span
			className="dashicons dashicons-universal-access"
			aria-hidden="true"
		/>
	);
}

/**
 * A single badge. Only rendered when the module is genuinely in that
 * state — "Stable" is the default and never gets a chip.
 * @param root0
 * @param root0.kind
 * @param root0.label
 */
function Badge( {
	kind,
	label,
}: {
	kind: 'experiment' | 'core-track';
	label: string;
} ): JSX.Element {
	return (
		<span
			className={ `accessibility-lab-badge accessibility-lab-badge--${ kind }` }
		>
			{ label }
		</span>
	);
}

function ModuleBadges( { module }: { module: Module } ): JSX.Element | null {
	const badges: JSX.Element[] = [];
	if ( module.bucket === 'experiment' ) {
		badges.push(
			<Badge
				key="experiment"
				kind="experiment"
				label={ __( 'Experiment', 'accessibility-lab' ) }
			/>
		);
	}
	if ( module.track === 'core-track' ) {
		badges.push(
			<Badge
				key="core-track"
				kind="core-track"
				label={ __( 'Core Track', 'accessibility-lab' ) }
			/>
		);
	}
	if ( badges.length === 0 ) {
		return null;
	}
	return <span className="accessibility-lab-badges">{ badges }</span>;
}

/**
 * A single module row within a group card. When a group has dependents,
 * the parent + its dependents share one outer bordered card and each row
 * is separated by a hairline — same visual language as WordPress `Card`
 * with multiple `CardBody` sections.
 * @param root0
 * @param root0.module
 * @param root0.depMet
 * @param root0.checked
 * @param root0.isDependent
 * @param root0.onChange
 */
function ModuleRow( {
	module,
	depMet,
	checked,
	isDependent,
	onChange,
}: {
	module: Module;
	depMet: boolean;
	checked: boolean;
	isDependent: boolean;
	onChange: ( value: boolean ) => void;
} ): JSX.Element {
	const cls = [
		'accessibility-lab-row',
		isDependent ? 'is-dependent' : '',
		! depMet ? 'is-disabled' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	const labelWithBadges = (
		<span className="accessibility-lab-row__label">
			<span className="accessibility-lab-row__name">{ module.name }</span>
			<ModuleBadges module={ module } />
		</span>
	);

	return (
		<div className={ cls }>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ labelWithBadges as unknown as string }
				help={ module.description }
				checked={ checked }
				disabled={ ! depMet }
				onChange={ onChange }
			/>
			{ module.credits && (
				<p className="accessibility-lab-row__credit">
					{ creditsLine( module.credits ) }
				</p>
			) }
		</div>
	);
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
	return (
		<SnackbarList
			className="accessibility-lab-snackbars"
			notices={ notices }
			onRemove={ removeNotice }
		/>
	);
}

function Toolbar(): JSX.Element {
	return (
		<header className="accessibility-lab-toolbar">
			<div className="accessibility-lab-toolbar__title">
				<span className="accessibility-lab-toolbar__icon">
					<UniversalAccessIcon />
				</span>
				<div className="accessibility-lab-toolbar__text">
					<h1 className="accessibility-lab-toolbar__heading">
						{ __( 'Accessibility Lab', 'accessibility-lab' ) }
					</h1>
					<p className="accessibility-lab-toolbar__description">
						{ __(
							'Community plugin for accessibility features and experiments.',
							'accessibility-lab'
						) }
					</p>
				</div>
			</div>
			<div className="accessibility-lab-toolbar__actions">
				<Button
					variant="tertiary"
					href="https://make.wordpress.org/accessibility/"
					target="_blank"
					rel="noopener noreferrer"
					icon={ external }
					iconPosition="right"
				>
					{ __( 'Docs', 'accessibility-lab' ) }
				</Button>
				<Button
					variant="tertiary"
					href="https://github.com/annezazu/accessibility-lab"
					target="_blank"
					rel="noopener noreferrer"
					icon={ external }
					iconPosition="right"
				>
					{ __( 'Contribute', 'accessibility-lab' ) }
				</Button>
			</div>
		</header>
	);
}

type ModuleGroup = { parent: Module; dependents: Module[] };

/**
 * Group modules for rendering: any module that other modules depend on
 * becomes a group parent, with its dependents nested inside. Independent
 * modules render as single-item groups.
 * @param modules
 */
function groupModules( modules: Module[] ): ModuleGroup[] {
	const byId = new Map( modules.map( ( m ) => [ m.id, m ] as const ) );
	const seen = new Set< string >();
	const groups: ModuleGroup[] = [];

	for ( const m of modules ) {
		if ( seen.has( m.id ) ) {
			continue;
		}

		// If this module depends on another, defer — the parent will pull
		// it in below when the parent is emitted.
		if ( DEPENDS_ON[ m.id ] ) {
			continue;
		}

		const dependents: Module[] = [];
		for ( const [ dependentId, requiredId ] of Object.entries(
			DEPENDS_ON
		) ) {
			if ( requiredId === m.id && byId.has( dependentId ) ) {
				dependents.push( byId.get( dependentId )! );
				seen.add( dependentId );
			}
		}
		groups.push( { parent: m, dependents } );
		seen.add( m.id );
	}

	// Safety net: any dependent whose required parent isn't registered
	// still renders as its own standalone group.
	for ( const m of modules ) {
		if ( ! seen.has( m.id ) ) {
			groups.push( { parent: m, dependents: [] } );
			seen.add( m.id );
		}
	}
	return groups;
}

function SettingsApp(): JSX.Element {
	const [ modules, setModules ] = useState< Module[] | null >( null );
	const [ data, setData ] = useState< SettingsData >( {} );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		apiFetch< ApiPayload >( { path: REST_PATH } )
			.then( ( payload ) => {
				setModules( payload.modules );
				setData( payload.settings );
			} )
			.catch( () => {
				createErrorNotice(
					__(
						'Failed to load Accessibility Lab settings.',
						'accessibility-lab'
					),
					{ type: 'snackbar' }
				);
			} );
	}, [ createErrorNotice ] );

	const persist = useCallback(
		async ( changed: Record< string, boolean >, next: SettingsData ) => {
			const previous = data;
			setData( next );
			try {
				const payload = await apiFetch< ApiPayload >( {
					path: REST_PATH,
					method: 'POST',
					data: { settings: next },
				} );
				setModules( payload.modules );
				setData( payload.settings );

				const [ firstId, firstValue ] =
					Object.entries( changed )[ 0 ] ?? [];
				const changedModule = modules?.find(
					( m ) => m.id === firstId
				);
				const label = changedModule?.name ?? firstId ?? '';
				createSuccessNotice(
					firstValue
						? sprintf(
								// translators: %s: Module name.
								__( '%s enabled.', 'accessibility-lab' ),
								label
						  )
						: sprintf(
								// translators: %s: Module name.
								__( '%s disabled.', 'accessibility-lab' ),
								label
						  ),
					{ type: 'snackbar' }
				);
			} catch {
				setData( previous );
				createErrorNotice(
					__(
						'Failed to save. Please try again.',
						'accessibility-lab'
					),
					{ type: 'snackbar' }
				);
			}
		},
		[ data, modules, createSuccessNotice, createErrorNotice ]
	);

	const toggle = useCallback(
		( id: string, value: boolean ) => {
			const changed: Record< string, boolean > = { [ id ]: value };
			const next: SettingsData = { ...data, [ id ]: value };
			if ( ! value ) {
				for ( const [ dependentId, requiredId ] of Object.entries(
					DEPENDS_ON
				) ) {
					if ( requiredId === id && next[ dependentId ] ) {
						next[ dependentId ] = false;
						changed[ dependentId ] = false;
					}
				}
			}
			persist( changed, next );
		},
		[ data, persist ]
	);

	if ( ! modules ) {
		return (
			<div className="accessibility-lab-page">
				<Toolbar />
				<div className="accessibility-lab-page__inner">
					<p role="status" aria-live="polite">
						<Spinner />
						<span className="screen-reader-text">
							{ __(
								'Loading Accessibility Lab settings…',
								'accessibility-lab'
							) }
						</span>
					</p>
				</div>
			</div>
		);
	}

	const groups = groupModules( modules );

	return (
		<div className="accessibility-lab-page">
			<Toolbar />
			<div className="accessibility-lab-page__inner">
				<section className="accessibility-lab-section">
					{ groups.map( ( { parent, dependents } ) => (
						<div
							className="accessibility-lab-card"
							key={ parent.id }
						>
							<ModuleRow
								module={ parent }
								depMet={ true }
								checked={ Boolean( data[ parent.id ] ) }
								isDependent={ false }
								onChange={ ( next ) =>
									toggle( parent.id, next )
								}
							/>
							{ dependents.map( ( dep ) => {
								const depMet = Boolean( data[ parent.id ] );
								return (
									<ModuleRow
										key={ dep.id }
										module={ dep }
										depMet={ depMet }
										checked={ Boolean( data[ dep.id ] ) }
										isDependent={ true }
										onChange={ ( next ) =>
											toggle( dep.id, next )
										}
									/>
								);
							} ) }
						</div>
					) ) }
				</section>
			</div>
			<Snackbars />
		</div>
	);
}

const mount = document.getElementById( 'accessibility-lab-settings' );
if ( mount ) {
	createRoot( mount ).render( <SettingsApp /> );
}
