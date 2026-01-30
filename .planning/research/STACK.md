# Stack Research

**Domain:** WordPress Plugin Development (HTML-to-Markdown Conversion)
**Researched:** 2026-01-30
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended | Confidence |
|------------|---------|---------|-----------------|------------|
| PHP | 7.4+ | Runtime | Project constraint; WordPress 6.x minimum. Enables typed properties, arrow functions, null coalescing assignment. | HIGH |
| WordPress | 6.0+ | Platform | Plugin target platform. 6.0+ ensures `send_headers` fires after `pre_get_posts` (changed in 6.1), enabling conditional tag usage. | HIGH |
| Composer | 2.x | Dependency management | Standard for PHP projects. Handles autoloading and library installation. Required for league/html-to-markdown. | HIGH |

### HTML-to-Markdown Conversion

| Library | Version | Purpose | Why Recommended | Confidence |
|---------|---------|---------|-----------------|------------|
| league/html-to-markdown | ^5.1 | Convert HTML to Markdown | **The** standard PHP library for this task. 26+ million Packagist downloads, actively maintained by The PHP League, DOM-based (not regex), extensible converter architecture. Supports PHP 7.2.5+ and 8.x. | HIGH |

**Key library features:**
- ATX-style headers (`# Heading`) via `header_style` option
- Table conversion via optional `TableConverter`
- Configurable bold/italic styles
- `strip_tags` option for security (removes non-Markdown HTML tags)
- `remove_nodes` option to eliminate specific tags entirely
- Native PHP DOM extension usage (xml, lib-xml, dom - enabled by default)

### WordPress APIs

| API | Functions | Purpose | Why Use |
|-----|-----------|---------|---------|
| Rewrite API | `add_rewrite_rule()`, `add_rewrite_tag()`, `flush_rewrite_rules()` | Handle `/slug.md` URL routing | WordPress-native URL routing. Rule: `(.+?)\.md$` captures slug, routes to custom handler. |
| Query Vars | `query_vars` filter | Register custom query variable | Required for WordPress to recognize `markdown_url` parameter in rewritten URLs. |
| Template Redirect | `template_redirect` action | Intercept and serve markdown | Fires after query parsing, before template loading. Use `exit()` after output to prevent further processing. |
| Head Output | `wp_head` action | Add `<link rel="alternate">` | Standard hook for `<head>` content injection. |
| Activation Hooks | `register_activation_hook()`, `register_deactivation_hook()` | Flush rewrite rules | Essential for registering/clearing custom URL rules on plugin state changes. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| PHP_CodeSniffer (PHPCS) | Code linting | Enforces coding standards |
| WordPress Coding Standards | Ruleset for PHPCS | Custom ruleset as specified (no Yoda, short arrays) |
| PHPUnit | Testing | WordPress plugin testing framework |
| WP_Mock or Brain Monkey | Unit test mocking | Mock WordPress functions for isolated testing |

## Installation

```bash
# Core library
composer require league/html-to-markdown

# Development dependencies
composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
composer require --dev phpunit/phpunit
composer require --dev brain/monkey
```

## Composer Configuration

```json
{
    "name": "joost/markdown-alternate",
    "description": "WordPress plugin providing markdown versions of content",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4",
        "league/html-to-markdown": "^5.1"
    },
    "require-dev": {
        "squizlabs/php_codesniffer": "^3.9",
        "wp-coding-standards/wpcs": "^3.0",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
        "phpunit/phpunit": "^9.0",
        "brain/monkey": "^2.6"
    },
    "autoload": {
        "psr-4": {
            "MarkdownAlternate\\": "src/"
        }
    }
}
```

**Note on autoloading:** PSR-4 is recommended over WordPress's traditional `class-*.php` naming. PSR-4 is cleaner for namespaced code and Composer handles it natively. The project constraint specifies "Composer autoloader" which aligns with this approach.

## Alternatives Considered

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| league/html-to-markdown | nashjain/html2markdown | Single file, no dependencies, BUT: not actively maintained, limited list support, no table support, no Composer integration |
| league/html-to-markdown | Custom regex conversion | Fragile, edge cases, maintenance burden. DOM-based conversion is more reliable. |
| league/html-to-markdown | html-to-markdown (Rust via FFI) | Cross-platform consistency, BUT: requires FFI extension, adds complexity, overkill for this use case |
| `template_redirect` | `template_include` | `template_include` is for swapping templates, not for serving non-HTML content. `template_redirect` + `exit()` is correct for serving raw markdown. |
| `add_rewrite_rule()` | `.htaccess` rules | WordPress-native solution preferred. Rewrite API works regardless of server (Apache, nginx, etc.) and integrates with WP query system. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Parsedown, league/commonmark, michelf/php-markdown | These convert Markdown **to** HTML (wrong direction). Common confusion - we need HTML-to-Markdown. | league/html-to-markdown |
| Global function prefix pattern | Outdated approach. Namespaces provide better collision prevention and organization. | PHP namespaces with Composer autoloading |
| `class-*.php` file naming | WordPress convention, but not required. PSR-4 naming is cleaner with namespaced code. | PSR-4 class naming (ClassName.php) |
| `flush_rewrite_rules()` on every page load | Expensive database operation. Causes performance issues. | Only call on plugin activation/deactivation |
| `init` hook for flushing rules | Rules may not be registered yet when flush runs. | Flag-based approach or `delete_option('rewrite_rules')` on activation |
| Direct `.htaccess` modification | Server-specific, fragile, doesn't integrate with WordPress. | WordPress Rewrite API |

## WordPress Hook Sequence

Understanding hook timing is critical for this plugin:

```
init                      -> Register rewrite rules, query vars
parse_request            -> WordPress parses URL against rules
pre_get_posts            -> Query modification (if needed)
send_headers             -> Set custom headers (Content-Type)
template_redirect        -> Intercept, generate markdown, exit()
wp_head                  -> Add <link rel="alternate"> (normal requests)
```

## Key Implementation Patterns

### Rewrite Rule Pattern

```php
add_action('init', function() {
    // Catch URLs ending in .md
    add_rewrite_rule(
        '(.+?)\.md$',
        'index.php?markdown_url=$matches[1]',
        'top'
    );
});

// Register query variable
add_filter('query_vars', function($vars) {
    $vars[] = 'markdown_url';
    return $vars;
});
```

### Content Negotiation Pattern

```php
add_action('send_headers', function() {
    if (is_singular()) {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'text/markdown') !== false) {
            // Serve markdown via template_redirect
            add_action('template_redirect', 'serve_markdown_content', 1);
        }
    }
});
```

### Alternate Link Pattern

```php
add_action('wp_head', function() {
    if (is_singular()) {
        $md_url = trailingslashit(get_permalink()) . '.md';
        // Or: get_permalink() with slug + .md
        printf(
            '<link rel="alternate" type="text/markdown" href="%s">',
            esc_url($md_url)
        );
    }
});
```

### Activation/Deactivation Pattern

```php
register_activation_hook(__FILE__, function() {
    // Register rules first (they must exist before flush)
    MarkdownAlternate\register_rewrite_rules();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
```

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| league/html-to-markdown ^5.1 | PHP 7.2.5 - 8.4 | Tested with PHP 8.4 |
| wp-coding-standards/wpcs ^3.0 | PHP_CodeSniffer 3.9+ | Requires PHPCSUtils 1.0.10+ |
| WordPress 6.0+ | PHP 7.4+ | WP 6.0 dropped PHP 7.3 support |

## Configuration Files

### phpcs.xml

```xml
<?xml version="1.0"?>
<ruleset name="Markdown Alternate">
    <description>Custom coding standards for Markdown Alternate plugin</description>

    <file>./src</file>
    <file>./markdown-alternate.php</file>

    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>

    <rule ref="WordPress">
        <!-- Disable Yoda conditions -->
        <exclude name="WordPress.PHP.YodaConditions"/>
    </rule>

    <!-- Allow short array syntax -->
    <rule ref="Generic.Arrays.DisallowShortArraySyntax">
        <severity>0</severity>
    </rule>
    <rule ref="Generic.Arrays.DisallowLongArraySyntax"/>
</ruleset>
```

## Stack Summary

**HTML-to-Markdown:** `league/html-to-markdown` - The definitive PHP library
**URL Routing:** WordPress Rewrite API (`add_rewrite_rule`, `query_vars`, `template_redirect`)
**Alternate Links:** `wp_head` action hook
**Content Negotiation:** `send_headers` hook with Accept header parsing
**Autoloading:** Composer with PSR-4
**Code Standards:** PHPCS with customized WordPress ruleset

## Sources

### Context7/Official Documentation (HIGH confidence)
- [WordPress Rewrite API](https://developer.wordpress.org/apis/rewrite/) - Official documentation
- [add_rewrite_rule()](https://developer.wordpress.org/reference/functions/add_rewrite_rule/) - Function reference
- [template_redirect hook](https://developer.wordpress.org/reference/hooks/template_redirect/) - Hook documentation
- [send_headers hook](https://developer.wordpress.org/reference/hooks/send_headers/) - Hook documentation
- [wp_head hook](https://developer.wordpress.org/reference/hooks/wp_head/) - Hook documentation
- [Activation/Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) - Plugin handbook
- [WordPress Plugin Best Practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/) - Plugin handbook

### Library Documentation (HIGH confidence)
- [league/html-to-markdown GitHub](https://github.com/thephpleague/html-to-markdown) - Official repository
- [league/html-to-markdown Packagist](https://packagist.org/packages/league/html-to-markdown) - Version 5.1.1, 26.6M downloads
- [WordPress Coding Standards GitHub](https://github.com/WordPress/WordPress-Coding-Standards) - PHPCS ruleset

### Community/Tutorials (MEDIUM confidence)
- [WPCode Markdown URLs Snippet](https://library.wpcode.com/snippet/e5wkk195/) - Reference implementation for .md URL pattern
- [WordPress Namespaces Blog Post](https://developer.wordpress.org/news/2025/09/implementing-namespaces-and-coding-standards-in-wordpress-plugin-development/) - Official WordPress developer blog

---
*Stack research for: WordPress Plugin - Markdown Alternate*
*Researched: 2026-01-30*
