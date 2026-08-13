<?php
/**
 * Attribution block for adopted Features.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

/**
 * Attribution metadata for a module adopted from a community source.
 */
final class Credits {

	/**
	 * Build an attribution record.
	 *
	 * @param string      $author              Original author's name.
	 * @param string|null $source_plugin_slug  WordPress.org slug the module was adapted from, if any.
	 * @param string|null $source_plugin_url   URL to the source plugin or PR, if any.
	 * @param string|null $license             License of the source, if any.
	 */
	public function __construct(
		public readonly string $author,
		public readonly ?string $source_plugin_slug = null,
		public readonly ?string $source_plugin_url = null,
		public readonly ?string $license = null
	) {}

	/**
	 * Serialize for the REST payload.
	 *
	 * @return array<string, string|null>
	 */
	public function to_array(): array {
		return array(
			'author'             => $this->author,
			'source_plugin_slug' => $this->source_plugin_slug,
			'source_plugin_url'  => $this->source_plugin_url,
			'license'            => $this->license,
		);
	}
}
