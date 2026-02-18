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

		// Check if permalink ends with a file extension (.html, .htm, .php, .aspx, .asp)
		// and replace it with .md instead of appending
		if ( preg_match( '/\.(html?|php|aspx?)$/i', $normalized_permalink ) ) {
			return preg_replace( '/\.(html?|php|aspx?)$/i', '.md', $normalized_permalink );
		}

		// No extension found - append .md
		return $normalized_permalink . '.md';
	}
}
