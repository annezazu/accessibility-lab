<?php
/**
 * Attribution block for adopted Features.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

final class Credits {

	public function __construct(
		public readonly string $author,
		public readonly ?string $source_plugin_slug = null,
		public readonly ?string $source_plugin_url = null,
		public readonly ?string $license = null
	) {}

	/**
	 * @return array<string, string|null>
	 */
	public function to_array(): array {
		return array(
			'author'              => $this->author,
			'source_plugin_slug'  => $this->source_plugin_slug,
			'source_plugin_url'   => $this->source_plugin_url,
			'license'             => $this->license,
		);
	}
}
