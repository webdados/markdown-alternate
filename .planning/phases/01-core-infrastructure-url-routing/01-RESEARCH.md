# Phase 1: Core Infrastructure & URL Routing - Research

**Researched:** 2026-01-30
**Domain:** WordPress plugin infrastructure with custom URL rewrite rules
**Confidence:** HIGH

## Summary

Phase 1 establishes the foundational infrastructure for the Markdown Alternate plugin: Composer/PSR-4 autoloading, WordPress plugin boilerplate with readme files, and URL routing for `.md` extensions. The core technical challenge is implementing WordPress rewrite rules that capture URLs ending in `.md` and route them to a custom handler that outputs markdown content.

The standard approach uses WordPress's Rewrite API (`add_rewrite_rule()`, `query_vars` filter, `template_redirect` action) rather than direct .htaccess manipulation. This ensures server-agnostic operation and proper WordPress integration. Rewrite rules must be flushed on activation/deactivation to prevent 404 errors on fresh installs.

Key decisions from CONTEXT.md constrain the implementation: nested page URLs use `/parent/child.md` pattern, trailing slashes redirect via 301, lowercase `.md` extension only, password-protected posts return 403, and draft/private/scheduled posts return 404.

**Primary recommendation:** Use `add_rewrite_rule()` with pattern `(.+?)\.md$` combined with `query_vars` filter and `template_redirect` handler. Flush rules on activation via flag pattern, not on every page load.

## Standard Stack

### Core

| Library/API | Version | Purpose | Why Standard |
|-------------|---------|---------|--------------|
| WordPress Rewrite API | WP 6.0+ | URL routing for `.md` extensions | Native WordPress solution; server-agnostic; integrates with WP query system |
| Composer | 2.x | PSR-4 autoloading, dependency management | Industry standard for PHP; required by project constraints (INFR-01) |
| `template_redirect` | WP 1.5.0+ | Intercept requests before template loading | Correct hook for serving non-HTML content; fires after query parsing |

### Supporting

| Library/Tool | Version | Purpose | When to Use |
|--------------|---------|---------|-------------|
| `add_rewrite_rule()` | WP 2.1.0+ | Register custom URL patterns | On `init` hook to register `.md` URL patterns |
| `query_vars` filter | WP 1.5.0+ | Register custom query variables | Allow WordPress to recognize `markdown_request` parameter |
| `get_query_var()` | WP 1.5.0+ | Retrieve query variable value | Check if current request is for markdown output |
| `wp_redirect()` | WP 1.5.0+ | Handle trailing slash redirects | Redirect `/slug.md/` to `/slug.md` with 301 |
| `post_password_required()` | WP 1.0.0+ | Check password protection status | Return 403 for password-protected posts |
| `get_post_status()` | WP 2.0.0+ | Check post visibility | Return 404 for draft/private/scheduled posts |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `add_rewrite_rule()` | Direct .htaccess rules | WordPress-native preferred; .htaccess is Apache-only |
| `template_redirect` | `template_include` filter | `template_include` is for swapping templates, not serving non-HTML |
| `template_redirect` | REST API endpoint | Overkill for simple content serving; adds complexity |
| Flag-based flush | `flush_rewrite_rules()` in `init` | Performance disaster; writes to database every page load |

**Installation:**
```bash
# Initialize Composer (if not exists)
composer init --type=wordpress-plugin --name=joost/markdown-alternate

# No runtime dependencies for Phase 1
# league/html-to-markdown added in Phase 2
```

## Architecture Patterns

### Recommended Project Structure
```
markdown-alternate/
├── markdown-alternate.php     # Main plugin file (bootstrap only)
├── composer.json              # PSR-4 autoloading configuration
├── readme.txt                 # WordPress.org plugin readme
├── README.md                  # GitHub/development readme
├── src/
│   ├── Plugin.php             # Core orchestrator (singleton)
│   └── Router/
│       └── RewriteHandler.php # URL rewrite rules and routing
└── vendor/                    # Composer autoloader (generated)
```

### Pattern 1: Single Entry Point Bootstrap

**What:** Main plugin file contains only bootstrap code; all logic lives in namespaced classes.
**When to use:** All WordPress plugins with OOP architecture.
**Example:**
```php
<?php
/**
 * Plugin Name: Markdown Alternate
 * Description: Provides markdown versions of posts and pages via .md URLs
 * Version: 1.0.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

namespace MarkdownAlternate;

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants for activation hooks
define('MARKDOWN_ALTERNATE_FILE', __FILE__);
define('MARKDOWN_ALTERNATE_VERSION', '1.0.0');

require_once __DIR__ . '/vendor/autoload.php';

Plugin::instance();
```
Source: [WordPress Plugin Best Practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)

### Pattern 2: Rewrite Rule with Query Variable

**What:** Register a rewrite rule that captures `.md` URLs and sets a custom query variable.
**When to use:** When serving alternate content at modified URLs.
**Example:**
```php
<?php
// Source: WordPress Developer Documentation
// Register on init hook
add_action('init', function() {
    // Capture any path ending in .md
    // (.+?) = non-greedy match for path (handles nested pages)
    add_rewrite_rule(
        '(.+?)\.md$',
        'index.php?pagename=$matches[1]&markdown_request=1',
        'top'
    );
});

// Register the query variable
add_filter('query_vars', function($vars) {
    $vars[] = 'markdown_request';
    return $vars;
});
```
Source: [add_rewrite_rule() documentation](https://developer.wordpress.org/reference/functions/add_rewrite_rule/)

### Pattern 3: Early Exit for Non-HTML Response

**What:** Intercept request at `template_redirect`, output content, and exit.
**When to use:** When serving non-HTML content that bypasses template hierarchy.
**Example:**
```php
<?php
// Source: template_redirect hook documentation
add_action('template_redirect', function() {
    if (!get_query_var('markdown_request')) {
        return;
    }

    $post = get_queried_object();
    if (!$post instanceof \WP_Post) {
        return; // Let WordPress handle 404
    }

    // Set headers and output content
    header('Content-Type: text/markdown; charset=UTF-8');
    echo "# {$post->post_title}\n\n";
    echo $post->post_content;
    exit;
}, 1); // Priority 1 = early
```
Source: [template_redirect hook](https://developer.wordpress.org/reference/hooks/template_redirect/)

### Pattern 4: Flag-Based Rewrite Flush

**What:** Set a flag on activation, check and flush on next init, delete flag.
**When to use:** When rules must be flushed after activation but not on every request.
**Example:**
```php
<?php
// Source: WordPress Activation/Deactivation Hooks
register_activation_hook(__FILE__, function() {
    // Rules must be registered before flush
    \MarkdownAlternate\Router\RewriteHandler::register_rules();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
```
Source: [Activation/Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)

### Anti-Patterns to Avoid

- **Flushing rewrite rules on every init:** Performance killer; writes to database every page load
- **Using `$_GET` for rewritten URLs:** Rewritten parameters aren't in `$_GET`; use `get_query_var()`
- **Loading template via `template_redirect`:** Use `template_include` for templates; `template_redirect` is for exits
- **Hardcoding post type checks everywhere:** Use an array of supported types defined once

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| URL pattern matching | Custom regex parsing of `$_SERVER['REQUEST_URI']` | WordPress Rewrite API | WordPress handles edge cases, encoding, multisite |
| Query variable parsing | `$_GET['var']` for rewritten URLs | `get_query_var('var')` | Rewritten params aren't in `$_GET` |
| HTTP redirects | `header('Location: ...')` | `wp_redirect()` + `exit` | WordPress handles safe redirect validation |
| Post status checks | Manual database queries | `get_post_status()`, `post_password_required()` | WordPress handles caching, edge cases |
| Autoloading | Manual `require` statements | Composer PSR-4 autoloader | Industry standard, handles dependencies |

**Key insight:** WordPress's Rewrite API abstracts server differences (Apache/nginx/IIS) and integrates with the query system. Hand-rolled solutions break on edge cases like multisite, encoded characters, and unusual server configurations.

## Common Pitfalls

### Pitfall 1: Rewrite Rules Not Flushed After Activation

**What goes wrong:** Plugin registers `.md` URL rules but users get 404 errors immediately after activation.
**Why it happens:** WordPress caches rewrite rules in the database. `add_rewrite_rule()` registers in memory only.
**How to avoid:** Call `flush_rewrite_rules()` in activation hook AFTER rules are registered. Never call it on every page load.
**Warning signs:** Works after saving Settings > Permalinks; fails on fresh install.

### Pitfall 2: Plain Permalink Structure Breaks Rules

**What goes wrong:** Plugin works with pretty permalinks but fails completely with "Plain" structure (`?p=123`).
**Why it happens:** Plain permalinks bypass mod_rewrite entirely; all rewrite rules are ignored.
**How to avoid:** Check `get_option('permalink_structure')` and display admin notice if empty. Document requirement in readme.txt.
**Warning signs:** Works on most sites; fails on specific installations with no errors.

### Pitfall 3: Query Variable Not Registered

**What goes wrong:** `get_query_var('markdown_request')` always returns empty string.
**Why it happens:** Custom query variables must be registered via `query_vars` filter before WordPress parses the request.
**How to avoid:** Add variable to `query_vars` filter; ensure filter runs before `parse_request`.
**Warning signs:** Rewrite rule matches (check with Rewrite Rules Inspector plugin) but variable is empty.

### Pitfall 4: Trailing Slash Creates Duplicate URLs

**What goes wrong:** Both `/post.md` and `/post.md/` work, creating duplicate content.
**Why it happens:** WordPress doesn't automatically redirect trailing slashes for custom extensions.
**How to avoid:** Check for trailing slash in `template_redirect` and issue 301 redirect to canonical URL.
**Warning signs:** SEO tools flag duplicate content; both URLs return content.

### Pitfall 5: Private/Draft Posts Exposed

**What goes wrong:** Draft or private posts are accessible at `.md` URLs without authentication.
**Why it happens:** Rewrite rule matches before WordPress checks post status; custom handler doesn't verify.
**How to avoid:** Check `get_post_status() === 'publish'` before serving content. Also check `post_password_required()`.
**Warning signs:** Unpublished content visible to logged-out users.

### Pitfall 6: Nested Page URLs Don't Match

**What goes wrong:** `/parent/child.md` returns 404 even though `/child.md` works.
**Why it happens:** Regex `([^/]+)\.md$` only matches single path segment.
**How to avoid:** Use non-greedy multi-segment pattern: `(.+?)\.md$` which captures the full path.
**Warning signs:** Top-level pages work; nested pages return 404.

## Code Examples

### Complete Rewrite Handler Class

```php
<?php
// Source: WordPress Developer Documentation, adapted for project requirements

namespace MarkdownAlternate\Router;

class RewriteHandler {

    public function register(): void {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_markdown_request'], 1);
    }

    public function add_rewrite_rules(): void {
        // Match paths ending in .md (non-greedy for nested pages)
        // (.+?) captures: post-slug, parent/child, 2024/01/my-post
        add_rewrite_rule(
            '(.+?)\.md$',
            'index.php?pagename=$matches[1]&markdown_request=1',
            'top'
        );
    }

    public function add_query_vars(array $vars): array {
        $vars[] = 'markdown_request';
        return $vars;
    }

    public function handle_markdown_request(): void {
        if (!get_query_var('markdown_request')) {
            return;
        }

        // Handle trailing slash redirect: /post.md/ -> /post.md
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (substr($request_uri, -1) === '/' && substr($request_uri, -4, 3) === '.md') {
            wp_redirect(rtrim($request_uri, '/'), 301);
            exit;
        }

        $post = get_queried_object();

        // Validate post exists
        if (!$post instanceof \WP_Post) {
            return; // Let WordPress handle 404
        }

        // Check post status (CONTEXT.md decisions)
        $status = get_post_status($post);
        if ($status !== 'publish') {
            // Draft, private, pending, scheduled -> 404
            return;
        }

        // Check password protection (CONTEXT.md: return 403)
        if (post_password_required($post)) {
            status_header(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'This content is password protected.';
            exit;
        }

        // Serve markdown (minimal for Phase 1)
        header('Content-Type: text/markdown; charset=UTF-8');
        echo "# {$post->post_title}\n\n";
        echo $post->post_content;
        exit;
    }

    public static function register_rules(): void {
        $handler = new self();
        $handler->add_rewrite_rules();
    }
}
```

### Composer.json Configuration

```json
{
    "name": "joost/markdown-alternate",
    "description": "WordPress plugin providing markdown versions of content via .md URLs",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "MarkdownAlternate\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
```

### readme.txt Template (WordPress.org Format)

```
=== Markdown Alternate ===
Contributors: joostdevalk
Tags: markdown, content, api, llm
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides markdown versions of posts and pages via .md URLs.

== Description ==

Markdown Alternate exposes your WordPress content as clean markdown through predictable URLs. Simply append `.md` to any post or page URL to get the markdown version.

**Features:**

* Access any post at `/post-slug.md`
* Access any page at `/page-slug.md`
* Nested pages work: `/parent/child.md`
* Zero configuration required

**Requirements:**

* Pretty permalinks must be enabled (not "Plain" structure)
* PHP 7.4 or higher

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/markdown-alternate/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Visit any post or page URL with `.md` extension

== Frequently Asked Questions ==

= Why do I get 404 errors on .md URLs? =

Ensure pretty permalinks are enabled. Go to Settings > Permalinks and select any structure other than "Plain".

= Do I need to configure anything? =

No. The plugin works immediately after activation.

== Changelog ==

= 1.0.0 =
* Initial release
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Global function prefixes | PHP namespaces with Composer PSR-4 | PHP 5.3+ (2009), WordPress adoption ~2020 | Cleaner code, fewer collisions |
| `add_rewrite_tag()` for all vars | `query_vars` filter for simple vars | Always available | `add_rewrite_tag()` still needed for reusable tags in permalinks |
| Direct `.htaccess` modification | WordPress Rewrite API | WP 2.1 (2007) | Server-agnostic, multisite compatible |
| `class-*.php` file naming | PSR-4 `ClassName.php` naming | PHP ecosystem standard | Composer integration, IDE support |

**Deprecated/outdated:**
- `create_function()`: Removed in PHP 8.0; use anonymous functions
- Global function prefixes: Use namespaces instead
- Short PHP tags `<?`: Use full `<?php` tags

## Open Questions

1. **Rewrite rule for posts vs pages**
   - What we know: Using `pagename=$matches[1]` works for pages with hierarchy
   - What's unclear: Does this correctly resolve posts with date-based permalinks like `/2024/01/my-post.md`?
   - Recommendation: Test with various permalink structures; may need additional rule for `name=$matches[1]`

2. **Case sensitivity of .md extension**
   - What we know: CONTEXT.md specifies lowercase only (`.md` works, `.MD` returns 404)
   - What's unclear: How to enforce case sensitivity in rewrite rules
   - Recommendation: Rewrite rules are typically case-insensitive; may need explicit check in handler

## Sources

### Primary (HIGH confidence)
- [add_rewrite_rule() - WordPress Developer Reference](https://developer.wordpress.org/reference/functions/add_rewrite_rule/)
- [template_redirect Hook - WordPress Developer Reference](https://developer.wordpress.org/reference/hooks/template_redirect/)
- [query_vars Hook - WordPress Developer Reference](https://developer.wordpress.org/reference/hooks/query_vars/)
- [get_query_var() - WordPress Developer Reference](https://developer.wordpress.org/reference/functions/get_query_var/)
- [wp_redirect() - WordPress Developer Reference](https://developer.wordpress.org/reference/functions/wp_redirect/)
- [post_password_required() - WordPress Developer Reference](https://developer.wordpress.org/reference/functions/post_password_required/)
- [Activation/Deactivation Hooks - WordPress Plugin Handbook](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
- [How Your readme.txt Works - WordPress Plugin Handbook](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)

### Secondary (MEDIUM confidence)
- [WordPress Rewrite Rules Guide - BrightMinded](https://brightminded.com/blog/mastering-wordpress-rewrite-rules/)
- [PSR-4 Autoloading for WordPress Plugins - DLX Plugins](https://dlxplugins.com/tutorials/creating-a-psr-4-autoloading-wordpress-plugin/)
- [Implementing Namespaces in WordPress Plugin Development - WordPress Developer Blog](https://developer.wordpress.org/news/2025/09/implementing-namespaces-and-coding-standards-in-wordpress-plugin-development/)

### Tertiary (LOW confidence)
- Project research from `.planning/research/STACK.md`, `.planning/research/ARCHITECTURE.md`, `.planning/research/PITFALLS.md`

## Metadata

**Confidence breakdown:**
- Standard Stack: HIGH - Verified against WordPress official documentation
- Architecture: HIGH - Based on established WordPress plugin patterns and project research
- Pitfalls: HIGH - Documented in project research, verified with official docs

**Research date:** 2026-01-30
**Valid until:** 60 days (WordPress APIs are stable)

---
*Phase 1 research for: Markdown Alternate WordPress plugin*
*Requirements: INFR-01, INFR-02, INFR-03, URL-01, TECH-01*
