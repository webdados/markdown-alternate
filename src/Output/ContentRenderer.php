<?php
/**
 * Content renderer for markdown output.
 *
 * @package MarkdownAlternate
 */

namespace MarkdownAlternate\Output;

use WP_Post;
use MarkdownAlternate\Converter\MarkdownConverter;

/**
 * Renders WordPress posts as markdown with YAML frontmatter.
 *
 * Handles complete content rendering including:
 * - YAML frontmatter with metadata
 * - H1 title heading
 * - HTML to markdown conversion
 * - Footer with categories and tags
 */
class ContentRenderer {

    /**
     * The HTML to Markdown converter.
     *
     * @var MarkdownConverter
     */
    private $converter;

    /**
     * Constructor.
     *
     * Initializes the markdown converter.
     */
    public function __construct() {
        $this->converter = new MarkdownConverter();
    }

    /**
     * Render a post as complete markdown output.
     *
     * @param WP_Post $post The post to render.
     * @return string The rendered markdown content.
     */
    public function render(WP_Post $post): string {
        // Generate frontmatter
        $frontmatter = $this->generate_frontmatter($post);

        // Get title for H1 heading
        $title = get_the_title($post);

        // Get content and apply WordPress filters (renders shortcodes and blocks)
        $content = $post->post_content;
        $content = apply_filters('the_content', $content);

        // Convert HTML to markdown
        $body = $this->converter->convert($content);

        // Generate footer with categories/tags
        $footer = $this->generate_footer($post);

        // Assemble output
        $output = $frontmatter . "\n";
        $output .= '# ' . $title . "\n\n";
        $output .= $body;

        if ($footer !== '') {
            $output .= "\n\n" . $footer;
        }

        return $output;
    }

    /**
     * Generate YAML frontmatter for a post.
     *
     * @param WP_Post $post The post to generate frontmatter for.
     * @return string The YAML frontmatter block.
     */
    private function generate_frontmatter(WP_Post $post): string {
        $lines = ['---'];

        // Title (always included)
        $title = get_the_title($post);
        $lines[] = 'title: "' . $this->escape_yaml($title) . '"';

        // Date (always included)
        $date = get_the_date('Y-m-d', $post);
        $lines[] = 'date: ' . $date;

        // Author (always included)
        $author = get_the_author_meta('display_name', $post->post_author);
        $lines[] = 'author: "' . $this->escape_yaml($author) . '"';

        // Featured image (only if set)
        $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
        if ($featured_image) {
            $lines[] = 'featured_image: "' . $this->escape_yaml($featured_image) . '"';
        }

        // Categories (only if present and not WP_Error)
        $categories = get_the_terms($post->ID, 'category');
        if ($categories && !is_wp_error($categories)) {
            $lines[] = 'categories:';
            foreach ($categories as $category) {
                $lines[] = '  - "' . $this->escape_yaml($category->name) . '"';
            }
        }

        // Tags (only if present and not WP_Error)
        $tags = get_the_terms($post->ID, 'post_tag');
        if ($tags && !is_wp_error($tags)) {
            $lines[] = 'tags:';
            foreach ($tags as $tag) {
                $lines[] = '  - "' . $this->escape_yaml($tag->name) . '"';
            }
        }

        $lines[] = '---';

        return implode("\n", $lines);
    }

    /**
     * Generate footer section with categories and tags.
     *
     * @param WP_Post $post The post to generate footer for.
     * @return string The footer section, or empty string if no categories/tags.
     */
    private function generate_footer(WP_Post $post): string {
        $footer_parts = [];

        // Categories
        $categories = get_the_terms($post->ID, 'category');
        if ($categories && !is_wp_error($categories)) {
            $category_names = array_map(function ($cat) {
                return $cat->name;
            }, $categories);
            $footer_parts[] = '**Categories:** ' . implode(', ', $category_names);
        }

        // Tags
        $tags = get_the_terms($post->ID, 'post_tag');
        if ($tags && !is_wp_error($tags)) {
            $tag_names = array_map(function ($tag) {
                return $tag->name;
            }, $tags);
            $footer_parts[] = '**Tags:** ' . implode(', ', $tag_names);
        }

        // Return empty string if no categories or tags
        if (empty($footer_parts)) {
            return '';
        }

        // Build footer with separator
        return "---\n\n" . implode("\n", $footer_parts);
    }

    /**
     * Escape a string for use in YAML.
     *
     * @param string $value The value to escape.
     * @return string The escaped value.
     */
    private function escape_yaml(string $value): string {
        // Escape backslashes first, then quotes
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);

        return $value;
    }
}
