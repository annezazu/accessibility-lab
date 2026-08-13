/**
 * Media Library — view-config popover.
 *
 * Injects a gear button into the media modal's toolbar (and the Media
 * Library grid). Clicking it opens a popover with three per-view
 * preferences: infinite scrolling, thumbnail density, and items per page.
 *
 * The media modal is Backbone-based (`wp.media`), so this module operates
 * on the DOM directly — no React, no @wordpress/data. Preferences persist
 * per user via the plugin's own AJAX endpoint; `infinite_scrolling` is the
 * same core user-meta key the profile-screen field writes to, so the two
 * surfaces stay in sync automatically.
 *
 * Inspired by WordPress Core PR #12795 and jasmussen's review comment
 * (https://github.com/WordPress/wordpress-develop/pull/12795#issuecomment-5165432047)
 * which points at the DataViews view-config pattern.
 */

import './style.scss';

type Density = 'compact' | 'comfortable' | 'spacious';

type Config = {
	context: 'modal' | 'grid';
	nonce: string;
	ajaxUrl: string;
	canToggleInfiniteScrolling: boolean;
	infiniteScrolling: boolean;
	density: Density;
	itemsPerPage: number;
	showFilenames: boolean;
	densityOptions: Density[];
	perPageOptions: number[];
	i18n: {
		buttonLabel: string;
		popoverTitle: string;
		infiniteScrolling: string;
		density: string;
		itemsPerPage: string;
		showFilenames: string;
		showFilenamesDescription: string;
		densityCompact: string;
		densityComfortable: string;
		densitySpacious: string;
		preferenceSaved: string;
		preferenceSaveFailed: string;
		preferenceAppliesOnReopen: string;
		nextOpenHint: string;
	};
};

interface WpMediaLibrary {
	props?: { set?: ( k: string, v: unknown ) => void };
	reset?: () => void;
	_hasMore?: boolean;
	mirroring?: { args?: Record< string, unknown > };
	args?: Record< string, unknown >;
}

interface WpMediaFrame {
	state?: () =>
		| { get?: ( k: string ) => WpMediaLibrary | undefined }
		| undefined;
	views?: {
		all?: () => Array< { $el?: JQuery } >;
	};
	$el?: JQuery;
}

declare global {
	interface Window {
		accessibilityLabMediaViewConfig?: Config;
		wp?: {
			a11y?: { speak?: ( msg: string ) => void };
			media?: {
				view?: {
					settings?: { infiniteScrolling?: boolean };
				};
				model?: {
					Query?: {
						defaultArgs?: Record< string, unknown >;
					};
				};
				frame?: WpMediaFrame;
				frames?: Record< string, WpMediaFrame >;
			};
		};
	}
}

type JQuery = { trigger?: ( name: string ) => unknown };

const config = window.accessibilityLabMediaViewConfig;

// Boot runs at the bottom of the file (see `runBoot()` below). It cannot
// run here because several module-scoped `let` bindings — `scrollHandler`,
// `openPopover`, etc. — are declared later in the file. Function declarations
// are hoisted, but the `let` closure variables they read are in the
// temporal dead zone until their declaration line executes.

function primeWpMediaDefaults(): void {
	// Push the current items-per-page into the Query default so the very
	// first fetch honours it.
	applyItemsPerPage( config!.itemsPerPage );
	// Turn on scroll-to-load-more if the user wants infinite scroll.
	applyInfiniteScrolling( config!.infiniteScrolling );
}

function densityLabel( d: Density ): string {
	const { i18n } = config!;
	if ( d === 'compact' ) {
		return i18n.densityCompact;
	}
	if ( d === 'spacious' ) {
		return i18n.densitySpacious;
	}
	return i18n.densityComfortable;
}

function speak( msg: string ): void {
	try {
		window.wp?.a11y?.speak?.( msg );
	} catch {
		/* wp.a11y not present */
	}
}

function toPrefValue( value: string | number | boolean ): string {
	if ( typeof value === 'boolean' ) {
		return value ? '1' : '0';
	}
	return String( value );
}

async function savePref(
	key: string,
	value: string | number | boolean
): Promise< boolean > {
	if ( ! config ) {
		return false;
	}
	const body = new URLSearchParams();
	body.set( 'action', 'accessibility_lab_save_media_view_pref' );
	body.set( 'nonce', config.nonce );
	body.set( 'key', key );
	body.set( 'value', toPrefValue( value ) );
	try {
		const resp = await fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} );
		if ( ! resp.ok ) {
			throw new Error( 'HTTP ' + resp.status );
		}
		const json = ( await resp.json() ) as { success?: boolean };
		return Boolean( json.success );
	} catch {
		return false;
	}
}

function buildPopover(): HTMLElement {
	const cfg = config!;
	const wrap = document.createElement( 'div' );
	wrap.className = 'accessibility-lab-media-view-config__popover';
	wrap.setAttribute( 'role', 'dialog' );
	wrap.setAttribute( 'aria-modal', 'true' );
	// Anchor the accessible name to the heading below so screen readers
	// announce "View options dialog" when focus lands inside.
	const headingId = 'accessibility-lab-media-view-config-title';
	wrap.setAttribute( 'aria-labelledby', headingId );
	// Make the container itself focusable so we can land focus on it when
	// no interactive control precedes the checkbox (e.g. when the infinite-
	// scrolling toggle is hidden by an active filter).
	wrap.tabIndex = -1;

	const heading = document.createElement( 'h2' );
	heading.className = 'accessibility-lab-media-view-config__title';
	heading.id = headingId;
	heading.textContent = cfg.i18n.popoverTitle;
	wrap.appendChild( heading );

	// Infinite scrolling toggle — only when saving takes effect.
	if ( cfg.canToggleInfiniteScrolling ) {
		const field = document.createElement( 'label' );
		field.className = 'accessibility-lab-media-view-config__field';
		const cb = document.createElement( 'input' );
		cb.type = 'checkbox';
		cb.checked = cfg.infiniteScrolling;
		cb.addEventListener( 'change', async () => {
			const ok = await savePref( 'infinite_scrolling', cb.checked );
			speak(
				ok ? cfg.i18n.preferenceSaved : cfg.i18n.preferenceSaveFailed
			);
			cfg.infiniteScrolling = cb.checked;
			document.body.dataset.mediaInfiniteScrolling = cb.checked
				? 'on'
				: 'off';
			applyInfiniteScrolling( cb.checked );
			// The items-per-page field is only rendered when infinite scroll
			// is off. Rebuild the popover so that control appears/disappears
			// with the toggle.
			rebuildOpenPopover();
		} );
		field.appendChild( cb );
		field.appendChild(
			document.createTextNode( ' ' + cfg.i18n.infiniteScrolling )
		);
		wrap.appendChild( field );
	}

	// Thumbnail density.
	const densityField = document.createElement( 'div' );
	densityField.className = 'accessibility-lab-media-view-config__field';
	const densityLbl = document.createElement( 'span' );
	densityLbl.className = 'accessibility-lab-media-view-config__field-label';
	densityLbl.textContent = cfg.i18n.density;
	densityField.appendChild( densityLbl );

	const densityRadios = document.createElement( 'div' );
	densityRadios.className = 'accessibility-lab-media-view-config__radios';
	cfg.densityOptions.forEach( ( d ) => {
		const lbl = document.createElement( 'label' );
		const input = document.createElement( 'input' );
		input.type = 'radio';
		input.name = 'accessibility-lab-media-density';
		input.value = d;
		input.checked = cfg.density === d;
		input.addEventListener( 'change', async () => {
			const ok = await savePref( 'density', d );
			speak(
				ok ? cfg.i18n.preferenceSaved : cfg.i18n.preferenceSaveFailed
			);
			cfg.density = d;
			applyDensity( d );
		} );
		lbl.appendChild( input );
		lbl.appendChild( document.createTextNode( ' ' + densityLabel( d ) ) );
		densityRadios.appendChild( lbl );
	} );
	densityField.appendChild( densityRadios );
	wrap.appendChild( densityField );

	// Always-show filenames.
	const filenamesField = document.createElement( 'label' );
	filenamesField.className = 'accessibility-lab-media-view-config__field';
	const filenamesCb = document.createElement( 'input' );
	filenamesCb.type = 'checkbox';
	filenamesCb.checked = cfg.showFilenames;
	filenamesCb.addEventListener( 'change', async () => {
		const ok = await savePref( 'show_filenames', filenamesCb.checked );
		speak( ok ? cfg.i18n.preferenceSaved : cfg.i18n.preferenceSaveFailed );
		cfg.showFilenames = filenamesCb.checked;
		applyFilenames( filenamesCb.checked );
	} );
	filenamesField.appendChild( filenamesCb );
	filenamesField.appendChild(
		document.createTextNode( ' ' + cfg.i18n.showFilenames )
	);
	const filenamesDesc = document.createElement( 'p' );
	filenamesDesc.className =
		'accessibility-lab-media-view-config__field-description';
	filenamesDesc.textContent = cfg.i18n.showFilenamesDescription;
	filenamesField.appendChild( filenamesDesc );
	wrap.appendChild( filenamesField );

	// Items per page — only meaningful when infinite scroll is OFF (with it
	// on, users don't count "pages", the grid just keeps loading). Hide the
	// control in that case to avoid a confusing knob that changes fetch
	// batch size without any visible pagination.
	if ( ! cfg.infiniteScrolling ) {
		const perPageField = document.createElement( 'label' );
		perPageField.className = 'accessibility-lab-media-view-config__field';
		const perPageLbl = document.createElement( 'span' );
		perPageLbl.className =
			'accessibility-lab-media-view-config__field-label';
		perPageLbl.textContent = cfg.i18n.itemsPerPage;
		perPageField.appendChild( perPageLbl );

		const select = document.createElement( 'select' );
		cfg.perPageOptions.forEach( ( n ) => {
			const opt = document.createElement( 'option' );
			opt.value = String( n );
			opt.textContent = String( n );
			opt.selected = cfg.itemsPerPage === n;
			select.appendChild( opt );
		} );
		select.addEventListener( 'change', async () => {
			const n = Number( select.value );
			const ok = await savePref( 'items_per_page', n );
			speak(
				ok ? cfg.i18n.preferenceSaved : cfg.i18n.preferenceSaveFailed
			);
			cfg.itemsPerPage = n;
			applyItemsPerPage( n );
		} );
		perPageField.appendChild( select );
		wrap.appendChild( perPageField );
	}

	return wrap;
}

function applyDensity( d: Density ): void {
	document.body.dataset.mediaThumbnailDensity = d;
}

function applyFilenames( show: boolean ): void {
	document.body.dataset.mediaShowFilenames = show ? 'on' : 'off';
}

/**
 * Infinite scroll: we always render the media library server-side in
 * "Load more" mode. The .load-more button therefore always exists in the
 * DOM. When the user turns infinite scroll on, we bind a scroll listener
 * that programmatically clicks the button as they approach the bottom of
 * the list; when off, we just leave the button alone. No wp.media
 * internals to fight — Backbone still owns the fetch.
 */
const SCROLL_TRIGGER_PX = 400; // Distance from bottom at which we auto-load.
let scrollHandler: ( () => void ) | null = null;

function findLoadMoreButton(): HTMLButtonElement | null {
	// Grid view uses `.load-more`; modal uses the same. It may be inside
	// `.load-more-wrapper` in newer versions.
	return document.querySelector< HTMLButtonElement >(
		'.load-more-wrapper .load-more, button.load-more'
	);
}

function nearBottom(): boolean {
	const scrollingEl = document.scrollingElement ?? document.documentElement;
	const remaining =
		scrollingEl.scrollHeight - scrollingEl.scrollTop - window.innerHeight;
	return remaining < SCROLL_TRIGGER_PX;
}

function applyInfiniteScrolling( enabled: boolean ): void {
	if ( enabled ) {
		if ( scrollHandler ) {
			return;
		}
		scrollHandler = () => {
			if ( ! nearBottom() ) {
				return;
			}
			const btn = findLoadMoreButton();
			if ( btn && ! btn.disabled ) {
				btn.click();
			}
		};
		window.addEventListener( 'scroll', scrollHandler, { passive: true } );
		// Kick once in case the initial view is already short.
		scrollHandler();
	} else if ( scrollHandler ) {
		window.removeEventListener( 'scroll', scrollHandler );
		scrollHandler = null;
	}
}

/**
 * Items-per-page: set on every library we can find so the very next
 * `.more()` call fetches the right batch size. Since we always render in
 * Load-more mode, both the click handler and our scroll handler flow
 * through the same code path — mutating library.args is enough.
 * @param n
 */
function applyItemsPerPage( n: number ): void {
	const wp = window.wp;
	if ( wp?.media?.model?.Query?.defaultArgs ) {
		wp.media.model.Query.defaultArgs.posts_per_page = n;
	}
	const setOnLibrary = ( lib: WpMediaLibrary | undefined ): void => {
		if ( ! lib ) {
			return;
		}
		if ( lib.args ) {
			lib.args.posts_per_page = n;
		}
		if ( lib.mirroring?.args ) {
			lib.mirroring.args.posts_per_page = n;
		}
		lib.props?.set?.( 'posts_per_page', n );
	};
	setOnLibrary( wp?.media?.frame?.state?.()?.get?.( 'library' ) );
	const frames = wp?.media?.frames ?? {};
	for ( const key of Object.keys( frames ) ) {
		setOnLibrary( frames[ key ]?.state?.()?.get?.( 'library' ) );
	}
}

function makeButton(): HTMLButtonElement {
	const cfg = config!;
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	// Don't inherit the .button class — the media toolbar's own buttons use
	// different metrics and .button forces a 30px height that ends up
	// misaligned. Style the toggle explicitly instead.
	btn.className = 'accessibility-lab-media-view-config__toggle';
	btn.setAttribute( 'aria-haspopup', 'dialog' );
	btn.setAttribute( 'aria-expanded', 'false' );
	btn.setAttribute( 'aria-label', cfg.i18n.buttonLabel );
	btn.innerHTML =
		'<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>' +
		'<span class="screen-reader-text">' +
		cfg.i18n.buttonLabel +
		'</span>';
	return btn;
}

// Module-scoped popover state. Using a single popover + delegated click
// handler means the button can be cloned, moved, or re-rendered by
// Backbone without breaking the interaction — the listeners are on
// `document`, not the button itself.
let openPopover: HTMLElement | null = null;
let openTriggerBtn: HTMLElement | null = null;

function closePopover(): void {
	if ( openPopover ) {
		openPopover.remove();
		openPopover = null;
	}
	if ( openTriggerBtn ) {
		openTriggerBtn.setAttribute( 'aria-expanded', 'false' );
		openTriggerBtn = null;
	}
}

/**
 * Swap the popover contents in place when a control's toggle should
 * add/remove other controls (e.g. infinite scroll ↔ items per page).
 * Preserves the trigger button, avoids stealing focus from body.
 */
function rebuildOpenPopover(): void {
	if ( ! openPopover || ! openTriggerBtn ) {
		return;
	}
	const btn = openTriggerBtn;
	// Remember which control was focused so we can restore focus back to
	// the semantically-equivalent element in the rebuilt DOM.
	const activeName = (
		btn.ownerDocument.activeElement as HTMLElement | null
	 )?.getAttribute?.( 'name' );
	openFor( btn );
	if ( activeName && openPopover ) {
		const target = (
			openPopover as HTMLElement
		 ).querySelector< HTMLElement >( `[name="${ activeName }"]` );
		target?.focus();
	}
}

function openFor( btn: HTMLElement ): void {
	closePopover();
	const popover = buildPopover();
	// Append to <body>: media toolbars (and Backbone re-renders) commonly
	// clip children on view updates, and the popover needs to float above
	// the modal chrome.
	document.body.appendChild( popover );

	// Position: prefer aligning the popover's left edge to the button's
	// left edge, but flip to right-align if that would overflow the viewport
	// (the media grid's toolbar sits near the right edge of the page).
	const btnRect = btn.getBoundingClientRect();
	const popRect = popover.getBoundingClientRect();
	const viewportW = document.documentElement.clientWidth;
	const margin = 8;

	let left = btnRect.left + window.scrollX;
	if ( btnRect.left + popRect.width > viewportW - margin ) {
		// Right-align: end of popover matches end of button.
		left = btnRect.right + window.scrollX - popRect.width;
	}
	// Clamp to viewport so we never end up off-screen even on narrow displays.
	left = Math.max( margin + window.scrollX, left );

	popover.style.top = `${ btnRect.bottom + window.scrollY + 4 }px`;
	popover.style.left = `${ left }px`;

	btn.setAttribute( 'aria-expanded', 'true' );
	openPopover = popover;
	openTriggerBtn = btn;

	// Move focus into the popover. Prefer the first focusable control; fall
	// back to the popover container so screen-reader users hear the dialog
	// name via aria-labelledby.
	const firstFocusable = getFocusables( popover )[ 0 ];
	( firstFocusable ?? popover ).focus();
}

function getFocusables( root: HTMLElement ): HTMLElement[] {
	const selector =
		'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
	return Array.from( root.querySelectorAll< HTMLElement >( selector ) );
}

document.addEventListener(
	'click',
	( ev ) => {
		const target = ev.target as Element | null;
		if ( ! target ) {
			return;
		}
		const btn = target.closest< HTMLElement >(
			'.accessibility-lab-media-view-config__toggle'
		);
		if ( btn ) {
			ev.preventDefault();
			ev.stopPropagation();
			if ( openTriggerBtn === btn ) {
				closePopover();
			} else {
				openFor( btn );
			}
			return;
		}
		// Click landed outside popover and outside any trigger button → close.
		if ( openPopover && ! openPopover.contains( target ) ) {
			closePopover();
		}
	},
	true
);

document.addEventListener( 'keydown', ( ev ) => {
	if ( ! openPopover ) {
		return;
	}
	if ( ev.key === 'Escape' ) {
		const btn = openTriggerBtn;
		ev.preventDefault();
		ev.stopPropagation();
		closePopover();
		btn?.focus();
		return;
	}
	if ( ev.key === 'Tab' ) {
		// Trap focus inside the popover. Users can Shift+Tab past the first
		// control to wrap to the last, and Tab past the last to wrap to the
		// first. Prevents focus from escaping into the (visually hidden)
		// media modal chrome behind the popover.
		const focusables = getFocusables( openPopover );
		if ( focusables.length === 0 ) {
			ev.preventDefault();
			openPopover.focus();
			return;
		}
		const first = focusables[ 0 ];
		const last = focusables[ focusables.length - 1 ];
		const active = openPopover.ownerDocument
			.activeElement as HTMLElement | null;
		if ( ev.shiftKey && ( active === first || active === openPopover ) ) {
			ev.preventDefault();
			last.focus();
		} else if ( ! ev.shiftKey && active === last ) {
			ev.preventDefault();
			first.focus();
		}
	}
} );

function attach( host: HTMLElement ): void {
	host.appendChild( makeButton() );
	document.body.dataset.mediaInfiniteScrolling = config!.infiniteScrolling
		? 'on'
		: 'off';
	applyDensity( config!.density );
	applyFilenames( config!.showFilenames );
	// eslint-disable-next-line no-console
	console.log( '[a11y-lab] view-config button attached to', host );
}

/**
 * Modal context: wp.media renders the browser toolbar dynamically. Wait
 * until the toolbar exists and attach the button once per open.
 */
function bootModal(): void {
	const observer = new MutationObserver( () => {
		const toolbars = document.querySelectorAll< HTMLElement >(
			'.media-frame .media-toolbar-primary'
		);
		toolbars.forEach( ( bar ) => {
			if (
				bar.querySelector(
					'.accessibility-lab-media-view-config__toggle'
				)
			) {
				return;
			}
			attach( bar );
		} );
	} );
	observer.observe( document.body, { childList: true, subtree: true } );
}

/**
 * Grid context (upload.php): the toolbar exists on page load.
 */
function bootGrid(): void {
	const tryAttach = () => {
		const host =
			document.querySelector< HTMLElement >(
				'.wp-filter .search-form'
			) ?? document.querySelector< HTMLElement >( '.wp-filter' );
		if (
			host &&
			! host.querySelector(
				'.accessibility-lab-media-view-config__toggle'
			)
		) {
			attach( host );
			return true;
		}
		return false;
	};
	if ( tryAttach() ) {
		return;
	}
	// Grid loads its filter bar asynchronously in some setups; observe once.
	const observer = new MutationObserver( () => {
		if ( tryAttach() ) {
			observer.disconnect();
		}
	} );
	observer.observe( document.body, { childList: true, subtree: true } );
}

// ─── Boot ──────────────────────────────────────────────────────────────
//
// Runs at the end of the module so every module-scoped `let` binding
// (`scrollHandler`, `openPopover`, `openTriggerBtn`, etc.) is
// initialized before any function that closes over them can execute.
// Booting earlier hits TDZ ReferenceErrors — that's what broke the button.

// eslint-disable-next-line no-console
console.log( '[a11y-lab] media view config boot', {
	present: Boolean( config ),
	context: config?.context,
} );

if ( config ) {
	primeWpMediaDefaults();
	// Try both boot paths regardless of the localized context. The grid path
	// targets `.wp-filter`; the modal path targets
	// `.media-frame .media-toolbar-primary`. Whichever matches first
	// attaches the button; the guard in `attach()` prevents duplicates.
	bootGrid();
	bootModal();
}
