<?php
/**
 * Plugin Name: Markdown Alternate
 * Plugin URI: https://github.com/joostdevalk/markdown-alternate
 * Description: Provides markdown versions of posts and pages via .md URLs
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Joost de Valk
 * Author URI: https://joost.blog
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: markdown-alternate
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MARKDOWN_ALTERNATE_FILE', __FILE__);
define('MARKDOWN_ALTERNATE_VERSION', '1.0.0');

require_once __DIR__ . '/vendor/autoload.php';

\MarkdownAlternate\Plugin::instance();
