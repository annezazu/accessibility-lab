<?php
/**
 * Module registry.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

use AccessibilityLab\Abstracts\Abstract_Module;

final class Registry {

	public const OPTION_KEY = 'accessibility_lab_settings';

	/** @var array<string, Abstract_Module> */
	private array $modules = array();

	public function register( Abstract_Module $module ): void {
		$id = $module->id();
		if ( isset( $this->modules[ $id ] ) ) {
			return;
		}
		if ( ! Bucket::is_valid( $module->bucket() ) ) {
			return;
		}
		if ( ! Track::is_valid( $module->track() ) ) {
			return;
		}
		$this->modules[ $id ] = $module;
	}

	/** @return array<string, Abstract_Module> */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * @param string $bucket Bucket::FEATURE|EXPERIMENT.
	 * @return array<string, Abstract_Module>
	 */
	public function by_bucket( string $bucket ): array {
		return array_filter(
			$this->modules,
			static fn( Abstract_Module $m ): bool => $m->bucket() === $bucket
		);
	}

	/**
	 * @param string $track Track::CORE_TRACK|PRACTICAL.
	 * @return array<string, Abstract_Module>
	 */
	public function by_track( string $track ): array {
		return array_filter(
			$this->modules,
			static fn( Abstract_Module $m ): bool => $m->track() === $track
		);
	}

	public function get( string $id ): ?Abstract_Module {
		return $this->modules[ $id ] ?? null;
	}

	public function is_enabled( string $id ): bool {
		return (bool) ( $this->settings()[ $id ] ?? false );
	}

	/** @return array<string, bool> */
	public function settings(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$out = array();
		foreach ( $this->modules as $id => $module ) {
			$out[ $id ] = array_key_exists( $id, $stored )
				? (bool) $stored[ $id ]
				: $module->default_enabled();
		}
		return $out;
	}

	/**
	 * @param array<string, bool> $incoming
	 * @return array<string, bool>
	 */
	public function update_settings( array $incoming ): array {
		$current = $this->settings();
		foreach ( $incoming as $id => $enabled ) {
			if ( ! isset( $this->modules[ $id ] ) ) {
				continue;
			}
			$was = $current[ $id ] ?? false;
			$now = (bool) $enabled;
			if ( $was && ! $now ) {
				$this->modules[ $id ]->on_disable();
			}
			$current[ $id ] = $now;
		}
		update_option( self::OPTION_KEY, $current, false );
		return $current;
	}

	/**
	 * @var array<string, string> Map of module id => id of module it requires.
	 * Populated via `set_dependencies()`.
	 */
	private array $dependencies = array();

	/**
	 * @param array<string, string> $dependencies module id => required id.
	 */
	public function set_dependencies( array $dependencies ): void {
		$this->dependencies = $dependencies;
	}

	/** @return array<string, string> */
	public function dependencies(): array {
		return $this->dependencies;
	}

	public function boot_enabled(): void {
		$settings = $this->settings();
		foreach ( $settings as $id => $enabled ) {
			if ( ! $enabled || ! isset( $this->modules[ $id ] ) ) {
				continue;
			}
			// Skip modules whose declared dependency is disabled — keeps the
			// state honest server-side even if the UI ever gets out of sync.
			$required = $this->dependencies[ $id ] ?? '';
			if ( '' !== $required && empty( $settings[ $required ] ) ) {
				continue;
			}
			$this->modules[ $id ]->boot();
		}
	}
}
