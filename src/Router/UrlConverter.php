<?php
/**
 * URL conversion utilities for markdown alternate URLs.
 *
 * @package MarkdownAlternate
 */

namespace MarkdownAlternate\Router;

/**
 * Utility class for converting permalinks to markdown URLs.
 */
class UrlConverter {

	/**
	 * Convert a permalink to a markdown URL.
	 *
	 * Handles special case for front page to avoid .com.md URLs.
	 *
	 * @param string $permalink The permalink to convert.
	 * @return string The markdown URL.
	 */
	public static function convert_to_markdown_url( string $permalink ): string {
		// Normalize both URLs by removing trailing slashes for comparison
		$normalized_permalink = rtrim( $permalink, '/' );
		$normalized_home      = rtrim( home_url( '/' ), '/' );

		// If this is the front page, use /index.md
		if ( $normalized_permalink === $normalized_home ) {
			return home_url( '/index.md' );
		}

		// Otherwise, append .md to the permalink
		return $normalized_permalink . '.md';
	}
}
