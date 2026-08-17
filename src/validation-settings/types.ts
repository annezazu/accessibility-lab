/**
 * Shared types for the validation settings admin app.
 */

export type Scope = 'block' | 'meta' | 'editor';

/**
 * Severity. `none` disables the check; it is labelled "Disabled" in the UI.
 */
export type Level = 'error' | 'warning' | 'none';

export type CheckType = 'Block' | 'Meta' | 'Editor';

/** A check as returned by GET /wp-validation/v1/checks. */
export type Check = {
	id: string;
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

export type ChecksPayload = {
	checks: Record< Scope, Check[] >;
	namespaces: string[];
};

export type OverridesPayload = {
	overrides: Record< string, Level >;
};

/** One DataViews row. `id` doubles as the override key. */
export type Row = {
	id: string;
	name: string;
	description: string;
	check_type: CheckType;
	target: string;
	plugin_title: string;
	level: Level;
	default_level: Level;
	has_override: boolean;
};

/** Server-injected label maps, see Admin_Pages::enqueue(). */
export type ValidationGlobals = {
	blockTitles?: Record< string, string >;
	postTypeLabels?: Record< string, string >;
};

declare global {
	interface Window {
		accessibilityLabValidation?: ValidationGlobals;
	}
}
