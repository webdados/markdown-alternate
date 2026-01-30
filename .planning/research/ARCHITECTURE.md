# Architecture Research

**Domain:** WordPress plugin with custom URL endpoints and content negotiation
**Researched:** 2026-01-30
**Confidence:** HIGH

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          WordPress Request Lifecycle                         │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   Rewrite   │───▶│   Request   │───▶│   Query     │───▶│  Template   │  │
│  │   Handler   │    │   Parser    │    │   Resolver  │    │   Router    │  │
│  └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘  │
│        │                  │                  │                   │          │
│        │ add_rewrite_rule │ parse_request    │ get_query_var    │ template │
│        │                  │                  │                   │ _redirect│
├────────┴──────────────────┴──────────────────┴───────────────────┴──────────┤
│                          Plugin Components                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   URL       │    │   Content   │    │  Markdown   │    │  Alternate  │  │
│  │   Router    │───▶│Negotiation │───▶│  Converter  │───▶│  Link       │  │
│  └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘  │
├─────────────────────────────────────────────────────────────────────────────┤
│                          Data Layer                                          │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐          │
│  │   WP_Post        │  │   Post Meta      │  │   Taxonomies     │          │
│  │   (content)      │  │   (featured img) │  │   (cats, tags)   │          │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘          │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | WordPress Integration |
|-----------|----------------|----------------------|
| URL Router | Register `.md` URL patterns, handle rewrite rules | `add_rewrite_rule()`, `add_rewrite_tag()`, `init` hook |
| Content Negotiator | Detect `Accept: text/markdown` header, intercept response | `template_redirect` hook, `$_SERVER['HTTP_ACCEPT']` |
| Markdown Converter | Transform HTML post content to markdown | `league/html-to-markdown` library |
| Alternate Link | Add `<link rel="alternate">` to HTML head | `wp_head` hook |
| Post Data Assembler | Gather post title, date, author, featured image, categories, tags | `get_post()`, `get_the_author_meta()`, `get_the_post_thumbnail_url()`, `get_the_category()`, `get_the_tags()` |

## Recommended Project Structure

```
markdown-alternate/
├── markdown-alternate.php     # Main plugin file (bootstrap only)
├── composer.json              # Autoloader + dependencies
├── uninstall.php              # Cleanup on uninstall
├── readme.txt                 # WordPress.org readme
├── README.md                  # GitHub readme
├── src/
│   ├── Plugin.php             # Core orchestrator class
│   ├── Router/
│   │   └── RewriteHandler.php # URL rewrite rules
│   ├── Negotiation/
│   │   └── ContentNegotiator.php # Accept header handling
│   ├── Converter/
│   │   └── MarkdownConverter.php # HTML-to-markdown conversion
│   ├── Output/
│   │   └── MarkdownRenderer.php  # Assemble final markdown output
│   └── Integration/
│       └── AlternateLink.php     # wp_head link injection
└── vendor/                    # Composer dependencies (autoload + html-to-markdown)
```

### Structure Rationale

- **src/**: Namespaced PHP classes following PSR-4. Keeps plugin root clean.
- **Router/**: Isolated rewrite rule logic. WordPress rewrite API is complex; isolating it aids testing.
- **Negotiation/**: Separates HTTP header inspection from URL handling. Two different entry points.
- **Converter/**: Single responsibility — transforms HTML to markdown. Wraps external library.
- **Output/**: Assembles all post data into final markdown string. Formatting logic centralized.
- **Integration/**: WordPress hook integrations that don't fit elsewhere (like wp_head).

## Architectural Patterns

### Pattern 1: Single Entry Point Bootstrap

**What:** Main plugin file only contains bootstrap code that initializes the Plugin class.
**When to use:** All WordPress plugins with OOP architecture.
**Trade-offs:** Cleaner code, easier testing. Slightly more files.

**Example:**
```php
<?php
/**
 * Plugin Name: Markdown Alternate
 */

namespace MarkdownAlternate;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Initialize plugin
Plugin::instance();
```

### Pattern 2: Hook Registration via Dependency Injection

**What:** Core Plugin class instantiates components and wires their hooks in a central location.
**When to use:** When components need to register WordPress hooks.
**Trade-offs:** Explicit dependencies, testable. Requires careful ordering.

**Example:**
```php
<?php

namespace MarkdownAlternate;

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_components();
    }

    private function init_components(): void {
        // Order matters: rewrite rules must register on init
        $rewrite_handler = new Router\RewriteHandler();
        $content_negotiator = new Negotiation\ContentNegotiator();
        $alternate_link = new Integration\AlternateLink();

        add_action('init', [$rewrite_handler, 'register_rules']);
        add_action('template_redirect', [$content_negotiator, 'maybe_serve_markdown']);
        add_action('wp_head', [$alternate_link, 'add_link_tag']);

        // Activation/deactivation hooks
        register_activation_hook(MARKDOWN_ALTERNATE_FILE, [$this, 'activate']);
        register_deactivation_hook(MARKDOWN_ALTERNATE_FILE, [$this, 'deactivate']);
    }
}
```

### Pattern 3: Early Exit for Markdown Response

**What:** Intercept request via `template_redirect`, output markdown, and `exit()`.
**When to use:** When serving non-HTML content that bypasses the template hierarchy.
**Trade-offs:** Clean separation. Must exit early to prevent WordPress from loading templates.

**Example:**
```php
<?php

namespace MarkdownAlternate\Negotiation;

class ContentNegotiator {
    public function maybe_serve_markdown(): void {
        if (!$this->should_serve_markdown()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $renderer = new \MarkdownAlternate\Output\MarkdownRenderer();
        $markdown = $renderer->render($post);

        header('Content-Type: text/markdown; charset=utf-8');
        echo $markdown;
        exit;
    }

    private function should_serve_markdown(): bool {
        // Check for .md URL or Accept header
        return get_query_var('markdown_format')
            || $this->accepts_markdown();
    }

    private function accepts_markdown(): bool {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'text/markdown') !== false;
    }
}
```

## Data Flow

### Request Flow: .md URL

```
User requests: /hello-world.md
       ↓
[WordPress receives request]
       ↓
[Rewrite rules match ^(.+)\.md$ → index.php?pagename=$1&markdown_format=1]
       ↓
[parse_request: query vars set]
       ↓
[WordPress resolves post via pagename/name]
       ↓
[template_redirect hook fires]
       ↓
[ContentNegotiator::maybe_serve_markdown()]
       ↓
[Check: get_query_var('markdown_format') === '1'] → YES
       ↓
[MarkdownRenderer::render($post)]
       ↓
[PostDataAssembler gathers: title, date, author, featured_image, content, categories, tags]
       ↓
[MarkdownConverter::convert(post_content)]
       ↓
[Assemble final markdown string]
       ↓
[header('Content-Type: text/markdown')]
       ↓
[echo $markdown; exit;]
```

### Request Flow: Accept Header

```
User requests: /hello-world/ with Accept: text/markdown
       ↓
[WordPress receives request]
       ↓
[Normal rewrite rules match → index.php?pagename=hello-world]
       ↓
[WordPress resolves post normally]
       ↓
[template_redirect hook fires]
       ↓
[ContentNegotiator::maybe_serve_markdown()]
       ↓
[Check: get_query_var('markdown_format')] → NO
       ↓
[Check: $this->accepts_markdown()] → YES (HTTP_ACCEPT contains text/markdown)
       ↓
[Same flow as above: render, output, exit]
```

### Request Flow: HTML with Alternate Link

```
User requests: /hello-world/ with normal Accept header
       ↓
[WordPress processes normally until wp_head]
       ↓
[wp_head hook fires]
       ↓
[AlternateLink::add_link_tag()]
       ↓
[Check: is_singular(['post', 'page'])] → YES
       ↓
[Output: <link rel="alternate" type="text/markdown" href="/hello-world.md">]
       ↓
[WordPress continues rendering normal HTML page]
```

### Key Data Flows

1. **Post → Markdown Output:** Post object → PostDataAssembler → MarkdownConverter → Assembled string
2. **URL → Query Var:** Request URI → Rewrite rules → Query variables → ContentNegotiator check
3. **Header Injection:** is_singular() check → get_permalink() + ".md" → link tag output

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k pages | No changes needed. WordPress handles well. |
| 1k-10k pages | Consider transient caching for converted markdown if HTML-to-markdown is slow |
| 10k+ pages | Add object caching (Redis/Memcached) for markdown output keyed by post_modified |

### Scaling Priorities

1. **First bottleneck:** HTML-to-markdown conversion on every request. Mitigate with transient cache keyed on post ID + modified date.
2. **Second bottleneck:** Not anticipated for typical use. This is a lightweight plugin.

## Anti-Patterns

### Anti-Pattern 1: Flushing Rewrite Rules on Every Page Load

**What people do:** Call `flush_rewrite_rules()` in the `init` hook.
**Why it's wrong:** Extremely expensive operation that rewrites `.htaccess` or queries rewrite rules from database. Causes severe performance degradation.
**Do this instead:** Flush only on plugin activation/deactivation. Use the flag pattern: set an option on activation, check on init, flush once, delete option.

### Anti-Pattern 2: Using template_redirect to Load Templates

**What people do:** Use `include()` inside `template_redirect` to load a template file.
**Why it's wrong:** Breaks the WordPress template hierarchy and can cause conflicts with other plugins. The hook is named "redirect" for a reason.
**Do this instead:** For non-HTML responses, output content directly and `exit()`. For alternate templates, use `template_include` filter.

### Anti-Pattern 3: Checking $_GET for Rewritten URLs

**What people do:** Use `$_GET['var']` to retrieve custom query variables from rewritten URLs.
**Why it's wrong:** WordPress rewrites map URL patterns to `index.php?var=value`, but `$_GET` only contains actual querystring parameters.
**Do this instead:** Use `get_query_var('var')` after registering the query var with `add_rewrite_tag()` or adding to `query_vars` filter.

### Anti-Pattern 4: Hardcoding Post Type Checks

**What people do:** Check `get_post_type() === 'post'` everywhere.
**Why it's wrong:** Makes the code rigid and harder to extend to pages or custom post types.
**Do this instead:** Define supported post types in one place (array or filter), check against that array.

## Integration Points

### WordPress Hooks Used

| Hook | Type | Purpose | Priority |
|------|------|---------|----------|
| `init` | Action | Register rewrite rules and tags | Default (10) |
| `template_redirect` | Action | Intercept request, serve markdown | Default (10) |
| `wp_head` | Action | Output `<link rel="alternate">` | Default (10) |
| `query_vars` | Filter | Register `markdown_format` query var | Default (10) |

### External Dependencies

| Dependency | Integration Pattern | Notes |
|------------|---------------------|-------|
| `league/html-to-markdown` | Composer require, instantiate HtmlConverter | Wrap in MarkdownConverter class to isolate dependency |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Router ↔ Negotiator | Query variable (`markdown_format`) | Router sets var, Negotiator reads it |
| Negotiator ↔ Renderer | Direct method call | Negotiator instantiates Renderer |
| Renderer ↔ Converter | Direct method call | Renderer calls Converter for content transformation |

## Build Order (Dependencies)

Components should be built in this order due to dependencies:

```
1. Plugin Bootstrap (markdown-alternate.php + src/Plugin.php)
   └── No dependencies, just structure

2. Rewrite Handler (src/Router/RewriteHandler.php)
   └── Depends on: Plugin bootstrap
   └── Tests: Can verify rewrite rules added

3. Markdown Converter (src/Converter/MarkdownConverter.php)
   └── Depends on: league/html-to-markdown
   └── Tests: Can unit test HTML→markdown conversion

4. Markdown Renderer (src/Output/MarkdownRenderer.php)
   └── Depends on: MarkdownConverter
   └── Tests: Can unit test post assembly

5. Content Negotiator (src/Negotiation/ContentNegotiator.php)
   └── Depends on: RewriteHandler (query var), MarkdownRenderer
   └── Tests: Integration test with WordPress

6. Alternate Link (src/Integration/AlternateLink.php)
   └── Depends on: RewriteHandler (URL pattern knowledge)
   └── Tests: Check link tag output
```

**Build order rationale:**
- Bootstrap first to establish autoloading
- Rewrite rules early because they define URL patterns used elsewhere
- Converter is isolated and can be built/tested independently
- Renderer needs Converter but is still testable in isolation
- Negotiator ties everything together — build last in core flow
- Alternate Link is independent of request handling — can be built in parallel with Negotiator

## WordPress Hook Timing Reference

```
mu-plugins_loaded
    ↓
plugins_loaded
    ↓
init                    ← Register rewrite rules here
    ↓
parse_request          ← Query vars populated
    ↓
wp                     ← WP_Query executed, post resolved
    ↓
template_redirect      ← Intercept here for markdown output
    ↓
wp_head               ← Add <link rel="alternate"> here
    ↓
the_content           ← (not used, we serve markdown instead)
```

## Sources

- [WordPress Rewrite API - Developer.WordPress.org](https://developer.wordpress.org/reference/classes/wp_rewrite/)
- [add_rewrite_rule() - Developer.WordPress.org](https://developer.wordpress.org/reference/functions/add_rewrite_rule/)
- [template_redirect Hook - Developer.WordPress.org](https://developer.wordpress.org/reference/hooks/template_redirect/)
- [wp_head Hook - Developer.WordPress.org](https://developer.wordpress.org/reference/hooks/wp_head/)
- [parse_request Hook - Developer.WordPress.org](https://developer.wordpress.org/reference/hooks/parse_request/)
- [league/html-to-markdown - Packagist](https://packagist.org/packages/league/html-to-markdown)
- [Roots Post Content to Markdown - GitHub](https://github.com/roots/post-content-to-markdown) (reference implementation)
- [WordPress Plugin Best Practices - Developer.WordPress.org](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)
- [Efficiently Flush Rewrite Rules - Andrea Carraro](https://andrezrv.com/2014/08/12/efficiently-flush-rewrite-rules-plugin-activation/)
- [Content Negotiation for WordPress - WP-Mix](https://wp-mix.com/content-negotiation-wordpress/)

---
*Architecture research for: Markdown Alternate WordPress plugin*
*Researched: 2026-01-30*
