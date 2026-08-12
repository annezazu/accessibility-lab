/**
 * Validation sidebar plugin.
 *
 * Unified list of every current issue, grouped by severity. Clicking a
 * block-scope issue selects that block in the editor.
 */

import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { Button, Icon, PanelBody } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import { caution, close } from '@wordpress/icons';

import { VALIDATION_STORE, type Issue } from './store';
import { useValidationSync, useValidationConsequences } from './index';

function IssueRow( { issue }: { issue: Issue } ): JSX.Element {
	const { selectBlock } = useDispatch( 'core/block-editor' ) as unknown as {
		selectBlock: ( clientId: string ) => void;
	};
	const onClick = (): void => {
		if ( issue.clientId ) {
			selectBlock( issue.clientId );
		}
	};
	const icon = issue.severity === 'error' ? close : caution;
	return (
		<li className={ `validation-issue validation-issue--${ issue.severity }` }>
			<Button
				variant="tertiary"
				onClick={ onClick }
				disabled={ ! issue.clientId }
				className="validation-issue__button"
			>
				<Icon icon={ icon } />
				<span className="validation-issue__message">{ issue.message }</span>
				<span className="validation-issue__meta">
					{ issue.scope === 'block' && issue.blockType }
					{ issue.scope === 'meta' && issue.metaKey }
					{ issue.scope === 'editor' && __( 'Document', 'accessibility-lab' ) }
				</span>
			</Button>
		</li>
	);
}

/**
 * Sibling components for the framework lifecycle. Kept separate so
 * `useValidationSync` (which dispatches into the validation store) and
 * `useValidationConsequences` (which reads from the validation store) don't
 * share a re-render cycle. Sharing one component causes an infinite loop:
 * sync dispatches → re-render → consequences read → dispatch lock → …
 */
function ValidationSync(): null {
	useValidationSync();
	return null;
}

function ValidationConsequences(): null {
	useValidationConsequences();
	return null;
}

function ValidationSidebarPanel(): JSX.Element {
	// Guard every selector: the sidebar can mount before the validation
	// store's first dispatch, and a thrown selector white-screens the editor.
	const { issues, errorCount, warningCount } = useSelect(
		( selectFn ) => {
			try {
				const store = selectFn( VALIDATION_STORE ) as unknown as {
					getIssues?: () => Issue[];
					errorCount?: () => number;
					warningCount?: () => number;
				} | null;
				return {
					issues: store?.getIssues?.() ?? [],
					errorCount: store?.errorCount?.() ?? 0,
					warningCount: store?.warningCount?.() ?? 0,
				};
			} catch {
				return { issues: [] as Issue[], errorCount: 0, warningCount: 0 };
			}
		},
		[]
	);

	const errors = issues.filter( ( i ) => i.severity === 'error' );
	const warnings = issues.filter( ( i ) => i.severity === 'warning' );

	return (
		<>
			<PluginSidebarMoreMenuItem target="accessibility-lab-validation">
				{ __( 'Validation', 'accessibility-lab' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="accessibility-lab-validation"
				title={ __( 'Validation', 'accessibility-lab' ) }
			>
				<PanelBody>
					<p>
						{ sprintf(
							/* translators: 1: error count, 2: warning count. */
							_n(
								'%1$d error, %2$d warning.',
								'%1$d errors, %2$d warnings.',
								errorCount + warningCount,
								'accessibility-lab'
							),
							errorCount,
							warningCount
						) }
					</p>
				</PanelBody>
				{ errors.length > 0 && (
					<PanelBody title={ __( 'Errors', 'accessibility-lab' ) } initialOpen={ true }>
						<ul className="validation-issue-list">
							{ errors.map( ( i ) => (
								<IssueRow key={ i.id } issue={ i } />
							) ) }
						</ul>
					</PanelBody>
				) }
				{ warnings.length > 0 && (
					<PanelBody title={ __( 'Warnings', 'accessibility-lab' ) } initialOpen={ true }>
						<ul className="validation-issue-list">
							{ warnings.map( ( i ) => (
								<IssueRow key={ i.id } issue={ i } />
							) ) }
						</ul>
					</PanelBody>
				) }
			</PluginSidebar>
		</>
	);
}

function ValidationPlugin(): JSX.Element {
	return (
		<>
			<ValidationSync />
			<ValidationConsequences />
			<ValidationSidebarPanel />
		</>
	);
}

try {
	registerPlugin( 'accessibility-lab-validation', {
		render: ValidationPlugin,
	} );
} catch ( err ) {
	// eslint-disable-next-line no-console
	console.warn( 'Accessibility Lab: sidebar registration failed', err );
}
