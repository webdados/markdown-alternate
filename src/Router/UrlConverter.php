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
	 * File extensions used in permalink structures that should be replaced with .md.
	 *
	 * @var string[]
	 */
	public const PERMALINK_EXTENSIONS = [ '.html', '.htm', '.php', '.aspx', '.asp' ];

	/**
	 * Regex pattern matching any of the supported permalink extensions.
	 *
	 * @var string
	 */
	private const EXTENSION_PATTERN = '/\.(html?|php|aspx?)$/i';

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

		// Replace file extension with .md, or append .md if no extension
		if ( preg_match( self::EXTENSION_PATTERN, $normalized_permalink ) ) {
			return preg_replace( self::EXTENSION_PATTERN, '.md', $normalized_permalink );
		}

		return $normalized_permalink . '.md';
	}
}
