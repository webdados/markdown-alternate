# Phase 3: Content Negotiation & Discovery - Research

**Researched:** 2026-01-30
**Domain:** HTTP content negotiation, WordPress header injection, alternate link discovery
**Confidence:** HIGH

## Summary

Phase 3 implements HTTP-level discovery mechanisms for markdown content. The core challenge is detecting `Accept: text/markdown` headers on regular WordPress URLs and redirecting to the `.md` variant (per CONTEXT.md decision: 303 redirect, not direct response). Additionally, HTML pages need `<link rel="alternate" type="text/markdown">` tags for programmatic discovery, and markdown responses need proper HTTP headers (`Content-Type`, `Vary`, `Link`, `X-Content-Type-Options`).

The standard approach uses WordPress's `template_redirect` hook (priority 1) for Accept header detection since it fires after query parsing but before template loading—all conditional tags like `is_single()`, `is_category()`, `is_archive()` are available at this point. For alternate link injection, the `wp_head` hook provides the standard mechanism. The existing `RewriteHandler` class should be extended to add the Accept header detection logic, which checks `$_SERVER['HTTP_ACCEPT']` and issues a 303 redirect to the `.md` URL when `text/markdown` is requested.

Key decisions from CONTEXT.md constrain implementation: Accept header triggers 303 redirect (not direct markdown response), URL always wins over Accept header (`.md` URLs serve markdown regardless of Accept header), negotiation works on all content types including archives, and no Cache-Control header (let server defaults apply).

**Primary recommendation:** Extend `RewriteHandler::handle_markdown_request()` to add Accept header detection with 303 redirect, create a new `DiscoveryHandler` class for `wp_head` alternate link injection, and ensure all markdown responses include required headers (`Vary: Accept`, `Link: <canonical>; rel="canonical"`, `X-Content-Type-Options: nosniff`).

## Standard Stack

### Core

| Library/API | Version | Purpose | Why Standard |
|-------------|---------|---------|--------------|
| WordPress `template_redirect` | WP 1.5.0+ | Accept header detection and 303 redirect | Fires after query parsing; all conditionals available; correct hook for early exits |
| WordPress `wp_head` | WP 1.5.0+ | Alternate link tag injection in HTML head | Standard mechanism for all head elements; theme-independent |
| PHP `$_SERVER['HTTP_ACCEPT']` | PHP 5+ | Access raw Accept header value | Direct access to HTTP headers; no dependencies |
| RFC 7763 `text/markdown` | 2016 | MIME type for markdown content | Official IANA-registered media type |

### Supporting

| Library/Tool | Version | Purpose | When to Use |
|--------------|---------|---------|-------------|
| `wp_redirect()` | WP 1.5.0+ | Issue 303 redirect | When Accept header requests markdown on HTML URL |
| `status_header()` | WP 2.0.0+ | Set HTTP status code | Set 303 status before redirect |
| `get_permalink()` | WP 1.0.0+ | Get canonical HTML URL for post/page | Building Link header and alternate link href |
| `get_term_link()` | WP 2.3.0+ | Get canonical URL for term archives | Building alternate links for category/tag pages |
| `get_post_type_archive_link()` | WP 3.0.0+ | Get archive URL for post types | Building alternate links for post type archives |
| `is_singular()` | WP 1.5.0+ | Check if on single post/page | Conditional logic for when to add alternate links |
| `is_archive()` | WP 1.5.0+ | Check if on archive page | Conditional logic for archive alternate links |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Manual Accept header parsing | `willdurand/negotiation` library | Adds ~100 LOC; library handles quality factors, wildcards, edge cases; RECOMMENDED if complex parsing needed |
| `template_redirect` | `parse_request` or `send_headers` | `parse_request` fires before query; `send_headers` fires too late for redirects |
| Direct `header()` calls | `wp_redirect()` + `status_header()` | WordPress functions handle edge cases, apply filters |
| Inline alternate link | Separate `DiscoveryHandler` class | Class separation follows existing architecture; easier testing |

**Installation:**
```bash
# Optional: Add negotiation library for RFC 7231 compliant Accept header parsing
# ONLY if quality factors (q=) or wildcards (*/*) support is needed
composer require willdurand/negotiation

# Current project likely doesn't need this - simple substring check for "text/markdown" suffices
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── Plugin.php                    # Orchestrator (existing)
├── Router/
│   └── RewriteHandler.php        # Extended with Accept header detection
├── Output/
│   └── ContentRenderer.php       # Existing content rendering
└── Discovery/
    └── AlternateLinkHandler.php  # NEW: wp_head alternate link injection
```

### Pattern 1: Accept Header Detection with 303 Redirect

**What:** Check Accept header early in `template_redirect`, redirect to `.md` URL if markdown requested.
**When to use:** Any HTML URL when `Accept: text/markdown` is present.
**Example:**
```php
<?php
// Source: CONTEXT.md decisions + RFC 9110 303 See Other
public function handle_accept_header_redirect(): void {
    // Skip if already handling .md URL (URL wins over Accept header)
    if (get_query_var('markdown_request')) {
        return;
    }

    // Check Accept header for text/markdown
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'text/markdown') === false) {
        return;
    }

    // Get canonical URL and append .md extension
    $canonical_url = $this->get_current_canonical_url();
    if (!$canonical_url) {
        return;
    }

    // Build .md URL (remove trailing slash, add .md)
    $md_url = rtrim($canonical_url, '/') . '.md';

    // Issue 303 See Other redirect (forces GET method on redirect)
    status_header(303);
    header('Location: ' . $md_url);
    header('Vary: Accept');
    exit;
}
```

### Pattern 2: Alternate Link Injection via wp_head

**What:** Add `<link rel="alternate" type="text/markdown">` to HTML page head.
**When to use:** All single posts, pages, and archives that have markdown versions.
**Example:**
```php
<?php
// Source: WordPress wp_head hook documentation
class AlternateLinkHandler {
    public function register(): void {
        add_action('wp_head', [$this, 'output_alternate_link'], 5);
    }

    public function output_alternate_link(): void {
        if (!is_singular() && !is_archive()) {
            return;
        }

        $md_url = $this->get_markdown_url();
        if (!$md_url) {
            return;
        }

        printf(
            '<link rel="alternate" type="text/markdown" href="%s" />%s',
            esc_url($md_url),
            "\n"
        );
    }
}
```

### Pattern 3: Response Headers for Markdown Output

**What:** Set all required HTTP headers when serving markdown content.
**When to use:** In RewriteHandler before outputting markdown.
**Example:**
```php
<?php
// Source: CONTEXT.md decisions + RFC 7763
private function set_markdown_headers(string $canonical_html_url): void {
    // Required headers (from REQUIREMENTS.md)
    header('Content-Type: text/markdown; charset=UTF-8');
    header('Vary: Accept');

    // Additional headers (from CONTEXT.md decisions)
    header('Link: <' . $canonical_html_url . '>; rel="canonical"');
    header('X-Content-Type-Options: nosniff');
}
```

### Pattern 4: Canonical URL Resolution for Different Content Types

**What:** Get the canonical HTML URL regardless of content type (post, page, archive).
**When to use:** Building Link header and alternate link href.
**Example:**
```php
<?php
// Source: WordPress Developer Documentation
private function get_current_canonical_url(): ?string {
    if (is_singular()) {
        $post = get_queried_object();
        return $post ? get_permalink($post) : null;
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if (!$term) {
            return null;
        }
        $link = get_term_link($term);
        return is_wp_error($link) ? null : $link;
    }

    if (is_post_type_archive()) {
        return get_post_type_archive_link(get_query_var('post_type'));
    }

    if (is_date()) {
        return $this->get_date_archive_url();
    }

    if (is_author()) {
        return get_author_posts_url(get_queried_object_id());
    }

    return null;
}
```

### Anti-Patterns to Avoid

- **Checking Accept header in parse_request:** Too early—conditionals not available yet
- **Serving markdown directly on Accept header:** CONTEXT.md specifies 303 redirect to .md URL instead
- **Hardcoding URLs:** Use WordPress functions (`get_permalink()`, `get_term_link()`) for portability
- **Forgetting exit after redirect:** Always call `exit;` after `wp_redirect()` or `header('Location: ...')`
- **Setting Cache-Control header:** CONTEXT.md says let server/CDN defaults apply

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Accept header parsing with q-values | Custom regex for `text/markdown;q=0.9` | Simple `strpos()` check OR `willdurand/negotiation` | q-values rarely used in Accept headers for text/markdown; library handles RFC 7231 edge cases |
| 303 redirect implementation | Direct `header('HTTP/1.1 303')` | `status_header(303)` + `wp_redirect()` | WordPress handles HTTP version detection, applies filters |
| Canonical URL generation | Manual URL building | `get_permalink()`, `get_term_link()`, etc. | WordPress handles permalinks, multisite, custom structures |
| HTML escaping in link tags | Manual string escaping | `esc_url()` and `esc_attr()` | WordPress sanitization is tested and maintained |

**Key insight:** Accept header parsing is deceptively simple for basic cases but complex for full RFC 7231 compliance (quality factors, wildcards, media type parameters). For this project, a simple `strpos()` check for `text/markdown` suffices—clients requesting markdown will send a clear `Accept: text/markdown` header, not complex negotiation strings with quality factors.

## Common Pitfalls

### Pitfall 1: Accept Header Check Before Query Parsing

**What goes wrong:** Check `$_SERVER['HTTP_ACCEPT']` too early; conditional tags return false.
**Why it happens:** Using `parse_request` or `init` hooks instead of `template_redirect`.
**How to avoid:** Use `template_redirect` hook (priority 1); all conditionals available at this point.
**Warning signs:** `is_singular()`, `is_archive()` always return false.

### Pitfall 2: Redirect Loop on Accept Header

**What goes wrong:** Infinite redirect loop when Accept header is present.
**Why it happens:** Not checking if already handling `.md` URL before redirect.
**How to avoid:** First check: `if (get_query_var('markdown_request')) return;` to skip redirect when already on .md URL.
**Warning signs:** Browser shows "too many redirects" error.

### Pitfall 3: Missing Vary Header Breaks Caching

**What goes wrong:** CDN serves wrong content type to clients.
**Why it happens:** Without `Vary: Accept`, CDN caches first response and serves it to all clients regardless of Accept header.
**How to avoid:** Always include `Vary: Accept` header in both markdown responses AND 303 redirect responses.
**Warning signs:** Client requests markdown, receives HTML (or vice versa) from cache.

### Pitfall 4: Alternate Link on Non-Existent Content

**What goes wrong:** Alternate link points to 404 page (e.g., private post, unsupported post type).
**Why it happens:** Adding alternate link without checking if markdown version actually exists.
**How to avoid:** Check post status, post type, and any other conditions that determine markdown availability before outputting link.
**Warning signs:** Following alternate link returns 404.

### Pitfall 5: Incorrect .md URL Construction

**What goes wrong:** `.md` URL doesn't match rewrite rules; returns 404.
**Why it happens:** Trailing slash handling inconsistent; URL encoding issues.
**How to avoid:** Use `rtrim($url, '/')` then append `.md`; test with all permalink structures.
**Warning signs:** Works on some pages but not others; works locally but not on production.

### Pitfall 6: Link Header Conflicts with CDN

**What goes wrong:** CDN strips or mangles custom Link header.
**Why it happens:** Some CDNs/proxies filter headers aggressively.
**How to avoid:** This is an infrastructure issue, not plugin issue. Document that Link header may not appear through CDN.
**Warning signs:** Link header present locally, missing on production.

## Code Examples

### Complete Accept Header Detection

```php
<?php
// Source: WordPress Developer Documentation + CONTEXT.md decisions

/**
 * Handle Accept header content negotiation.
 *
 * Redirects to .md URL when Accept: text/markdown is present.
 * Must run BEFORE handle_markdown_request() to check before URL detection.
 */
public function handle_accept_negotiation(): void {
    // Skip if already a markdown request (URL wins over Accept header)
    if (get_query_var('markdown_request')) {
        return;
    }

    // Check Accept header
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'text/markdown') === false) {
        return;
    }

    // Get canonical URL for current content
    $canonical = $this->get_current_canonical_url();
    if (!$canonical) {
        return;
    }

    // Build markdown URL
    $md_url = rtrim($canonical, '/') . '.md';

    // 303 See Other redirect (from CONTEXT.md)
    status_header(303);
    header('Vary: Accept');
    header('Location: ' . $md_url);
    exit;
}
```

### Complete Alternate Link Handler Class

```php
<?php
// Source: WordPress wp_head documentation + existing architecture patterns

namespace MarkdownAlternate\Discovery;

/**
 * Handles alternate link tag injection in HTML head.
 */
class AlternateLinkHandler {

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action('wp_head', [$this, 'output_alternate_link'], 5);
    }

    /**
     * Output alternate link tag for markdown version.
     */
    public function output_alternate_link(): void {
        $md_url = $this->get_markdown_url();
        if (!$md_url) {
            return;
        }

        printf(
            '<link rel="alternate" type="text/markdown" href="%s" />' . "\n",
            esc_url($md_url)
        );
    }

    /**
     * Get markdown URL for current content.
     *
     * @return string|null The .md URL or null if not available.
     */
    private function get_markdown_url(): ?string {
        // Only for content that has markdown versions
        if (!is_singular() && !is_archive()) {
            return null;
        }

        // Get canonical HTML URL
        $canonical = $this->get_canonical_url();
        if (!$canonical) {
            return null;
        }

        // Build .md URL
        return rtrim($canonical, '/') . '.md';
    }

    /**
     * Get canonical URL for current content.
     *
     * @return string|null The canonical URL or null.
     */
    private function get_canonical_url(): ?string {
        if (is_singular()) {
            $post = get_queried_object();
            if (!$post || get_post_status($post) !== 'publish') {
                return null;
            }
            // Check supported post types
            if (!in_array($post->post_type, ['post', 'page'], true)) {
                return null;
            }
            return get_permalink($post);
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if (!$term) {
                return null;
            }
            $link = get_term_link($term);
            return is_wp_error($link) ? null : $link;
        }

        if (is_post_type_archive()) {
            return get_post_type_archive_link(get_query_var('post_type'));
        }

        if (is_author()) {
            return get_author_posts_url(get_queried_object_id());
        }

        if (is_date()) {
            // Date archives need special handling
            if (is_year()) {
                return get_year_link(get_query_var('year'));
            }
            if (is_month()) {
                return get_month_link(get_query_var('year'), get_query_var('monthnum'));
            }
            if (is_day()) {
                return get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
            }
        }

        return null;
    }
}
```

### Updated Header Output in RewriteHandler

```php
<?php
// Source: CONTEXT.md decisions + RFC 7763 + RFC 8288

/**
 * Set all required HTTP headers for markdown response.
 *
 * @param WP_Post $post The post being served.
 */
private function set_response_headers(\WP_Post $post): void {
    // Required by TECH-03
    header('Content-Type: text/markdown; charset=UTF-8');

    // Required by TECH-04
    header('Vary: Accept');

    // From CONTEXT.md: canonical Link header
    $canonical_url = get_permalink($post);
    header('Link: <' . $canonical_url . '>; rel="canonical"');

    // From CONTEXT.md: security header
    header('X-Content-Type-Options: nosniff');
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Direct `header('HTTP/1.1 303')` | `status_header(303)` | WP 2.0 | WordPress handles HTTP version detection |
| `header('Location: ')` only | `wp_redirect()` with status | Always recommended | WordPress validates URLs, applies filters |
| REST API for format negotiation | Accept header on existing URLs | Modern best practice | Cleaner URLs, proper HTTP semantics |
| Multiple alternate links per format | Single alternate per media type | HTML5 | One link per alternate representation |

**Deprecated/outdated:**
- Using `wp_redirect()` without explicit status code: Always pass status code as second parameter
- RFC 5988 for Web Linking: Obsoleted by RFC 8288 in 2017 (same concepts, updated spec)

## Open Questions

1. **Archive markdown format**
   - What we know: CONTEXT.md says archives support markdown; category.md, tag.md should work
   - What's unclear: How should archive markdown content look? (list of posts? index style?)
   - Recommendation: Claude's discretion per CONTEXT.md; suggest list of posts with titles and links

2. **Accept header strictness**
   - What we know: CONTEXT.md marks this as Claude's discretion
   - What's unclear: Should we handle `text/markdown;q=0.8` or wildcards `*/*`?
   - Recommendation: Simple `strpos()` check for `text/markdown`; don't parse quality factors unless issues arise

3. **Hook priority for Accept header check**
   - What we know: Must run before template loads; `template_redirect` priority 1 is used by existing handler
   - What's unclear: Should Accept check be same priority or earlier?
   - Recommendation: Add as separate method in same handler, called first at same priority; order in method determines execution

## Sources

### Primary (HIGH confidence)
- [template_redirect Hook - WordPress Developer Reference](https://developer.wordpress.org/reference/hooks/template_redirect/)
- [wp_head Hook - WordPress Developer Reference](https://developer.wordpress.org/reference/hooks/wp_head/)
- [get_permalink() - WordPress Developer Reference](https://developer.wordpress.org/reference/functions/get_permalink/)
- [get_term_link() - WordPress Developer Reference](https://developer.wordpress.org/reference/functions/get_term_link/)
- [RFC 7763 - The text/markdown Media Type](https://www.rfc-editor.org/rfc/rfc7763.html)
- [RFC 9110 - HTTP Semantics (303 See Other)](https://www.rfc-editor.org/rfc/rfc9110#status.303)
- [MDN - Vary Header](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Vary)
- [MDN - HTTP 303 See Other](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/303)

### Secondary (MEDIUM confidence)
- [willdurand/negotiation - Content Negotiation for PHP](https://github.com/willdurand/Negotiation) - 117M+ downloads, MIT license
- [RFC 8288 - Web Linking](https://www.rfc-editor.org/rfc/rfc8288.html) - Link header format
- [X-Content-Type-Options in WordPress - MalCare](https://www.malcare.com/blog/x-content-type-options-wordpress/)
- [wp_headers filter - Pagely](https://support.pagely.com/hc/en-us/articles/360043987431)

### Tertiary (LOW confidence)
- Project CONTEXT.md decisions (user-provided constraints)
- Existing codebase patterns from Phase 1 and Phase 2

## Metadata

**Confidence breakdown:**
- Standard Stack: HIGH - All WordPress APIs are well-documented and stable
- Architecture: HIGH - Follows existing project patterns from Phase 1/2
- Pitfalls: HIGH - Based on documented WordPress behavior and HTTP specifications

**Research date:** 2026-01-30
**Valid until:** 60 days (WordPress APIs stable; HTTP specs don't change)

---
*Phase 3 research for: Markdown Alternate WordPress plugin*
*Requirements: URL-02, URL-03, TECH-03, TECH-04*
