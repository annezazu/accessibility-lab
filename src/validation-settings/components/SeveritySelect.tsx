/**
 * The severity picker rendered inside the table's "Level" cell.
 */

import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { Level } from '../types';

export const LEVEL_OPTIONS: Array< { label: string; value: Level } > = [
	{ label: __( 'Error', 'accessibility-lab' ), value: 'error' },
	{ label: __( 'Warning', 'accessibility-lab' ), value: 'warning' },
	{ label: __( 'Disabled', 'accessibility-lab' ), value: 'none' },
];

type Props = {
	value: Level;
	onChange: ( value: Level ) => void;
};

export function SeveritySelect( { value, onChange }: Props ): JSX.Element {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			label={ __( 'Level', 'accessibility-lab' ) }
			hideLabelFromVision
			value={ value }
			options={ LEVEL_OPTIONS }
			onChange={ ( next ) => onChange( next as Level ) }
		/>
	);
}
