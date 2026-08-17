/**
 * Validation Settings admin app.
 *
 * One table of every registered validation check, from every plugin that
 * registered one. Admins pick a severity per check; overrides matching the
 * check's registered default aren't stored, so defaults keep tracking the
 * plugin that owns the check.
 */

import { Page } from '@wordpress/admin-ui';
import apiFetch from '@wordpress/api-fetch';
import { Button, SnackbarList, Spinner } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { LEVEL_OPTIONS, SeveritySelect } from './components/SeveritySelect';
import type { Action, Field, View } from '@wordpress/dataviews';
import type { ChecksPayload, Level, OverridesPayload, Row } from './types';
import { checksToRows, rowsToOverrides } from './utils/transform';

const CHECKS_PATH = '/wp-validation/v1/checks';
const OVERRIDES_PATH = '/accessibility-lab/v1/validation-settings';

const DEFAULT_VIEW: View = {
	type: 'table',
	search: '',
	filters: [],
	page: 1,
	perPage: 25,
	sort: { field: 'name', direction: 'asc' },
	titleField: 'name',
	descriptionField: 'description',
	fields: [ 'target', 'check_type', 'plugin_title', 'level' ],
};

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

export function App(): JSX.Element {
	const [ rows, setRows ] = useState< Row[] | null >( null );
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );
	const [ isDirty, setIsDirty ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		Promise.all( [
			apiFetch< ChecksPayload >( { path: CHECKS_PATH } ),
			apiFetch< OverridesPayload >( { path: OVERRIDES_PATH } ),
		] )
			.then( ( [ checks, settings ] ) => {
				setRows( checksToRows( checks, settings.overrides ?? {} ) );
			} )
			.catch( () => {
				setRows( [] );
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

	// Nothing is persisted until Save, so guard against losing edits.
	useEffect( () => {
		if ( ! isDirty ) {
			return;
		}
		const handler = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ isDirty ] );

	const handleLevelChange = useCallback( ( id: string, level: Level ) => {
		setRows( ( current ) =>
			( current ?? [] ).map( ( row ) =>
				row.id === id
					? {
							...row,
							level,
							has_override: level !== row.default_level,
					  }
					: row
			)
		);
		setIsDirty( true );
	}, [] );

	const handleReset = useCallback( ( items: Row[] ) => {
		const ids = new Set( items.map( ( item ) => item.id ) );
		setRows( ( current ) =>
			( current ?? [] ).map( ( row ) =>
				ids.has( row.id )
					? { ...row, level: row.default_level, has_override: false }
					: row
			)
		);
		setIsDirty( true );
	}, [] );

	const handleSave = useCallback( async () => {
		if ( ! rows ) {
			return;
		}
		setIsSaving( true );
		try {
			const saved = await apiFetch< OverridesPayload >( {
				path: OVERRIDES_PATH,
				method: 'POST',
				data: { overrides: rowsToOverrides( rows ) },
			} );
			// Re-derive from the server's response so anything it rejected is
			// reflected in the table rather than silently kept in local state.
			const accepted = saved.overrides ?? {};
			setRows( ( current ) =>
				( current ?? [] ).map( ( row ) => {
					const override = accepted[ row.id ];
					return {
						...row,
						level: override ?? row.default_level,
						has_override: override !== undefined,
					};
				} )
			);
			setIsDirty( false );
			createSuccessNotice(
				__( 'Validation settings saved.', 'accessibility-lab' ),
				{
					type: 'snackbar',
				}
			);
		} catch {
			createErrorNotice(
				__( 'Failed to save. Please try again.', 'accessibility-lab' ),
				{
					type: 'snackbar',
				}
			);
		} finally {
			setIsSaving( false );
		}
	}, [ rows, createSuccessNotice, createErrorNotice ] );

	// Only list plugins that actually registered a check on this site.
	const pluginElements = useMemo( () => {
		const names = [
			...new Set( ( rows ?? [] ).map( ( row ) => row.plugin_title ) ),
		].sort();
		return names.map( ( name ) => ( { value: name, label: name } ) );
	}, [ rows ] );

	const fields = useMemo< Field< Row >[] >(
		() => [
			{
				id: 'name',
				label: __( 'Check', 'accessibility-lab' ),
				enableGlobalSearch: true,
				enableSorting: true,
				enableHiding: false,
			},
			{
				id: 'description',
				label: __( 'Description', 'accessibility-lab' ),
				enableGlobalSearch: true,
				enableSorting: false,
				enableHiding: false,
			},
			{
				id: 'target',
				label: __( 'Target', 'accessibility-lab' ),
				enableGlobalSearch: true,
				enableSorting: true,
			},
			{
				id: 'check_type',
				label: __( 'Type', 'accessibility-lab' ),
				enableSorting: true,
				elements: [
					{
						value: 'Block',
						label: __( 'Block', 'accessibility-lab' ),
					},
					{ value: 'Meta', label: __( 'Meta', 'accessibility-lab' ) },
					{
						value: 'Editor',
						label: __( 'Editor', 'accessibility-lab' ),
					},
				],
				filterBy: { isPrimary: true, operators: [ 'is' ] },
			},
			{
				id: 'plugin_title',
				label: __( 'Plugin', 'accessibility-lab' ),
				enableSorting: true,
				enableGlobalSearch: true,
				elements: pluginElements,
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'level',
				label: __( 'Level', 'accessibility-lab' ),
				enableSorting: false,
				elements: LEVEL_OPTIONS.map( ( { value, label } ) => ( {
					value,
					label,
				} ) ),
				filterBy: { isPrimary: true, operators: [ 'is' ] },
				render: ( { item }: { item: Row } ) => (
					<SeveritySelect
						value={ item.level }
						onChange={ ( level ) =>
							handleLevelChange( item.id, level )
						}
					/>
				),
			},
		],
		[ pluginElements, handleLevelChange ]
	);

	const actions = useMemo< Action< Row >[] >(
		() => [
			{
				id: 'reset-to-default',
				label: __( 'Reset to default', 'accessibility-lab' ),
				isEligible: ( item: Row ) => item.has_override,
				supportsBulk: true,
				callback: ( items: Row[] ) => handleReset( items ),
			},
		],
		[ handleReset ]
	);

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( rows ?? [], view, fields ),
		[ rows, view, fields ]
	);

	if ( ! rows ) {
		return (
			<Page title={ __( 'Validation', 'accessibility-lab' ) }>
				<p role="status" aria-live="polite">
					<Spinner />
					<span className="screen-reader-text">
						{ __( 'Loading…', 'accessibility-lab' ) }
					</span>
				</p>
			</Page>
		);
	}

	return (
		<Page
			className="accessibility-lab-validation-page"
			title={ __( 'Validation', 'accessibility-lab' ) }
			subTitle={ __(
				'Set the severity of every validation check registered on this site. Checks left at their default follow whichever plugin registered them.',
				'accessibility-lab'
			) }
			actions={
				<Button
					variant="primary"
					onClick={ handleSave }
					disabled={ ! isDirty || isSaving }
					isBusy={ isSaving }
					__next40pxDefaultSize
				>
					{ __( 'Save changes', 'accessibility-lab' ) }
				</Button>
			}
		>
			<DataViews< Row >
				data={ data }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				paginationInfo={ paginationInfo }
				actions={ actions }
				defaultLayouts={ { table: {} } }
				empty={
					<p>
						{ __(
							'No validation checks are registered.',
							'accessibility-lab'
						) }
					</p>
				}
			/>
			<Snackbars />
		</Page>
	);
}
