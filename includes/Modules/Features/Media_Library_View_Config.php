<?php
/**
 * Feature: Media Library — view-config popover.
 *
 * Adds a DataViews-style view-config button to the Media Library modal and
 * grid, exposing per-view preferences (infinite scroll, thumbnail density,
 * items per page) in-context. Previous versions of this module flipped the
 * infinite-scroll default silently; that shipped without an in-context UI,
 * which reviewers rightly pushed back on.
 *
 * The button is only rendered when the underlying preference can actually
 * be saved. When another callback filters `media_library_infinite_scrolling`,
 * or when there is no current user, the toggle is hidden — announcing
 * "preference saved" for a value that a filter overrides on the next page
 * load would mislead users.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features;

use AccessibilityLab\Abstracts\Abstract_Module;
use AccessibilityLab\Bucket;
use AccessibilityLab\Credits;

final class Media_Library_View_Config extends Abstract_Module {

	// Reuse core's per-user infinite-scroll option so our popover and
	// core's profile-screen field stay in sync in both directions.
	private const OPT_INFINITE = 'infinite_scrolling';
	private const OPT_DENSITY   = 'accessibility_lab_media_thumbnail_density';
	private const OPT_PER_PAGE  = 'accessibility_lab_media_items_per_page';
	private const OPT_FILENAMES = 'accessibility_lab_media_show_filenames';

	private const DENSITY_VALUES  = array( 'compact', 'comfortable', 'spacious' );
	private const PER_PAGE_VALUES = array( 40, 80, 160 );

	public function id(): string {
		return 'media_library_view_config';
	}

	public function bucket(): string {
		return Bucket::FEATURE;
	}

	public function name(): string {
		return __( 'Media Library: add view options', 'accessibility-lab' );
	}

	public function description(): string {
		return __( 'Adds a view-config button to the Media Library modal and grid, exposing infinite scroll, thumbnail density, and items-per-page controls in context. Preferences persist per user.', 'accessibility-lab' );
	}

	public function credits(): ?Credits {
		return new Credits(
			author: 'WordPress core / Trac #65775',
			source_plugin_slug: 'wordpress-core',
			source_plugin_url: 'https://github.com/WordPress/wordpress-develop/pull/12795',
			license: 'GPL-2.0-or-later'
		);
	}

	public function boot(): void {
		// Single enqueue hook — detect context from the current screen
		// inside the callback so the modal (post edit) and grid (upload.php)
		// contexts don't race to overwrite each other's localized data.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_accessibility_lab_save_media_view_pref', array( $this, 'ajax_save_pref' ) );

		// Always force core to render the media library in "Load more" mode
		// (i.e. infinite scroll OFF at the server layer). That guarantees
		// the `.load-more` button is present in the DOM, and our JS then
		// controls the actual behavior at runtime — clicking the button
		// on scroll when the user has infinite scroll turned on.
		add_filter( 'media_library_infinite_scrolling', '__return_false', PHP_INT_MAX );
	}

	public function on_disable(): void {
		// Preferences persist so re-enabling the module restores each user's choices.
	}

	public function on_uninstall(): void {
		// Do NOT delete `infinite_scrolling` — that's core's user meta key,
		// shared with the profile-screen field.
		delete_metadata( 'user', 0, self::OPT_DENSITY, '', true );
		delete_metadata( 'user', 0, self::OPT_PER_PAGE, '', true );
		delete_metadata( 'user', 0, self::OPT_FILENAMES, '', true );
	}

	public function enqueue_assets( string $hook ): void {
		// upload.php is the standalone Media Library grid; anywhere else
		// that renders a media modal (post/page editors, widgets, etc.)
		// gets the modal context.
		$context = ( 'upload.php' === $hook ) ? 'grid' : 'modal';

		// Only load in admin contexts where a media library is likely to
		// appear. `wp_script_is( 'media-views', 'enqueued' )` is not
		// reliable at this point, so hook the enqueue lazily instead:
		// register now, and hand off the localized data / actual enqueue
		// once we're sure the media UI is present.
		if ( 'upload.php' === $hook ) {
			// Grid: always enqueue.
			$this->enqueue_view_config( 'grid' );
			return;
		}

		// Modal: only enqueue when wp_enqueue_media() has run. Hooking on
		// `wp_enqueue_media` guarantees we run once per page, after core's
		// media-views script is enqueued.
		add_action(
			'wp_enqueue_media',
			function () use ( $context ) {
				$this->enqueue_view_config( $context );
			}
		);
	}

	private function enqueue_view_config( string $context ): void {
		$asset_file = ACCESSIBILITY_LAB_DIR . '/build/media-view-config.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		// Depend on media-views so we can safely mutate wp.media.view.settings /
		// wp.media.model.Query at boot before any AttachmentsBrowser mounts.
		$deps = $asset['dependencies'] ?? array();
		if ( ! in_array( 'media-views', $deps, true ) ) {
			$deps[] = 'media-views';
		}
		if ( ! in_array( 'wp-a11y', $deps, true ) ) {
			$deps[] = 'wp-a11y';
		}

		wp_enqueue_script(
			'accessibility-lab-media-view-config',
			ACCESSIBILITY_LAB_URL . 'build/media-view-config.js',
			$deps,
			$asset['version'] ?? ACCESSIBILITY_LAB_VERSION,
			true
		);

		wp_localize_script(
			'accessibility-lab-media-view-config',
			'accessibilityLabMediaViewConfig',
			$this->localized_data( $context )
		);

		$style_file = ACCESSIBILITY_LAB_DIR . '/build/style-media-view-config.css';
		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'accessibility-lab-media-view-config',
				ACCESSIBILITY_LAB_URL . 'build/style-media-view-config.css',
				array( 'dashicons' ),
				$asset['version'] ?? ACCESSIBILITY_LAB_VERSION
			);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function localized_data( string $context ): array {
		$user_id = get_current_user_id();

		$infinite_pref  = $user_id ? get_user_meta( $user_id, self::OPT_INFINITE, true ) : '';
		$density_pref   = $user_id ? get_user_meta( $user_id, self::OPT_DENSITY, true ) : '';
		$per_page_pref  = $user_id ? (int) get_user_meta( $user_id, self::OPT_PER_PAGE, true ) : 0;
		$filenames_pref = $user_id ? get_user_meta( $user_id, self::OPT_FILENAMES, true ) : '';

		return array(
			'context'                   => $context,
			'nonce'                     => wp_create_nonce( 'accessibility_lab_media_view_config' ),
			'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
			'canToggleInfiniteScrolling' => $this->can_toggle_infinite_scrolling(),
			'infiniteScrolling'         => '1' === $infinite_pref,
			'density'                   => in_array( $density_pref, self::DENSITY_VALUES, true )
				? $density_pref
				: 'comfortable',
			'itemsPerPage'              => in_array( $per_page_pref, self::PER_PAGE_VALUES, true )
				? $per_page_pref
				: 80,
			// Filenames default to off: matches core's default of showing them
			// only on hover/selection. Turning this on exposes them always,
			// which helps low-vision users and reduces mouse-only interactions.
			'showFilenames'             => '1' === $filenames_pref,
			'densityOptions'            => self::DENSITY_VALUES,
			'perPageOptions'            => self::PER_PAGE_VALUES,
			'i18n'                      => array(
				'buttonLabel'            => __( 'View options', 'accessibility-lab' ),
				'popoverTitle'           => __( 'View options', 'accessibility-lab' ),
				'infiniteScrolling'      => __( 'Infinite scrolling', 'accessibility-lab' ),
				'density'                => __( 'Thumbnail density', 'accessibility-lab' ),
				'itemsPerPage'           => __( 'Items per page', 'accessibility-lab' ),
				'showFilenames'          => __( 'Always show file names', 'accessibility-lab' ),
				'showFilenamesDescription' => __( 'Show file names under every thumbnail instead of only on hover.', 'accessibility-lab' ),
				'densityCompact'         => __( 'Compact', 'accessibility-lab' ),
				'densityComfortable'     => __( 'Comfortable', 'accessibility-lab' ),
				'densitySpacious'        => __( 'Spacious', 'accessibility-lab' ),
				'preferenceSaved'         => __( 'Preference saved.', 'accessibility-lab' ),
				'preferenceSaveFailed'    => __( 'Could not save preference.', 'accessibility-lab' ),
				'preferenceAppliesOnReopen' => __( 'Preference saved. Applies next time you open the media library.', 'accessibility-lab' ),
				'nextOpenHint'            => __( 'Applies next time you open the media library.', 'accessibility-lab' ),
			),
		);
	}

	/**
	 * Whether the infinite-scroll toggle can be shown. Requires a logged-in
	 * user (so we have somewhere to store the pref). Because we control the
	 * actual scroll/load-more behavior client-side, no filter override can
	 * silently break the toggle — the guard used in core PR #12795 doesn't
	 * apply here.
	 */
	private function can_toggle_infinite_scrolling(): bool {
		return get_current_user_id() > 0;
	}

	public function ajax_save_pref(): void {
		check_ajax_referer( 'accessibility_lab_media_view_config', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'not-authenticated' ), 401 );
		}

		// Match the capability that gates media library access. Prevents
		// authenticated users without media privileges (e.g. Subscribers)
		// from touching prefs that only apply to a UI they can't see.
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$key   = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

		switch ( $key ) {
			case 'infinite_scrolling':
				update_user_meta( $user_id, self::OPT_INFINITE, '1' === (string) $value ? '1' : '0' );
				break;

			case 'density':
				$value = is_string( $value ) ? $value : '';
				if ( ! in_array( $value, self::DENSITY_VALUES, true ) ) {
					wp_send_json_error( array( 'message' => 'invalid-value' ), 400 );
				}
				update_user_meta( $user_id, self::OPT_DENSITY, $value );
				break;

			case 'items_per_page':
				$value = (int) $value;
				if ( ! in_array( $value, self::PER_PAGE_VALUES, true ) ) {
					wp_send_json_error( array( 'message' => 'invalid-value' ), 400 );
				}
				update_user_meta( $user_id, self::OPT_PER_PAGE, (string) $value );
				break;

			case 'show_filenames':
				update_user_meta( $user_id, self::OPT_FILENAMES, '1' === (string) $value ? '1' : '0' );
				break;

			default:
				wp_send_json_error( array( 'message' => 'unknown-key' ), 400 );
		}

		wp_send_json_success();
	}

}
