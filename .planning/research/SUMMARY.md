# Project Research Summary

**Project:** Markdown Alternate - WordPress Plugin
**Domain:** WordPress Plugin Development (Content Negotiation & Alternate Format Delivery)
**Researched:** 2026-01-30
**Confidence:** HIGH

## Executive Summary

This WordPress plugin delivers markdown versions of post/page content via two mechanisms: dedicated `.md` URLs and HTTP content negotiation via Accept headers. The recommended approach uses WordPress's native Rewrite API for URL routing, `template_redirect` hook for response interception, and `league/html-to-markdown` (the definitive PHP library with 26+ million downloads) for HTML-to-Markdown conversion. The architecture is straightforward: register rewrite rules, intercept requests, convert content, and serve with proper headers.

The plugin represents a zero-configuration solution where competitors require filters or settings pages. Core differentiation comes from dedicated `.md` URLs (more cacheable and intuitive than Accept header negotiation alone), automatic support for both posts and pages, and `<link rel="alternate">` tags for programmatic discovery. The technical implementation is low-complexity with well-documented WordPress APIs, though several critical pitfalls around rewrite rule flushing, content rendering, and caching integration must be carefully avoided.

Key risks center on rewrite rule management (404s if not flushed properly on activation), content processing (shortcodes/blocks must be rendered before conversion), and cache compatibility (content negotiation conflicts with most WordPress caching plugins). These are all well-understood problems with documented solutions. The overall technical complexity is LOW to MEDIUM, making this an achievable v1 with high confidence.

## Key Findings

### Recommended Stack

WordPress plugin development follows established patterns. The research identifies `league/html-to-markdown` as the definitive solution for HTML-to-Markdown conversion — it's DOM-based (not fragile regex), extensible, actively maintained by The PHP League, and supports PHP 7.4+ through 8.4. The WordPress Rewrite API handles custom URL patterns, while the `template_redirect` hook enables clean response interception before template loading.

**Core technologies:**
- **PHP 7.4+**: Meets WordPress 6.0+ requirements; enables typed properties and modern syntax
- **league/html-to-markdown ^5.1**: The standard PHP library for HTML-to-Markdown conversion; 26M+ downloads, DOM-based parsing, configurable converters
- **WordPress 6.0+**: Platform baseline; ensures `send_headers` fires after `pre_get_posts` for proper conditional tag usage
- **Composer 2.x with PSR-4 autoloading**: Standard dependency management; cleaner than traditional WordPress class file naming

**WordPress APIs:**
- Rewrite API (`add_rewrite_rule`, `add_rewrite_tag`) for `.md` URL routing
- Template Redirect hook for request interception and markdown serving
- Query Vars filter for custom parameter registration
- wp_head hook for `<link rel="alternate">` injection

### Expected Features

Users expect markdown versions to be discoverable and complete. Table stakes features are those that make the plugin functional and intuitive. Differentiators set this plugin apart from the primary competitor (Roots post-content-to-markdown) through better URL patterns and zero-configuration operation.

**Must have (table stakes):**
- `.md` URL endpoint for posts and pages — intuitive, shareable, standard pattern like `.json` or `.xml`
- `<link rel="alternate" type="text/markdown">` in HTML head — enables programmatic discovery
- Content negotiation via `Accept: text/markdown` header — modern web standard (RFC 7763)
- Post metadata (title, date, author, categories, tags, featured image) — complete document context
- HTML-to-Markdown conversion with proper rendering — core value proposition
- Correct `Content-Type: text/markdown` header — proper HTTP semantics

**Should have (competitive advantage):**
- Zero configuration for posts AND pages — competitor requires filter for pages
- Featured image URL in metadata — valuable for LLMs, often omitted by competitors
- Categories and tags in output — useful context for content classification
- Shortcode stripping option — clean output without `[shortcode]` artifacts
- Query parameter fallback (`?format=markdown`) — works when Accept headers unavailable

**Defer (v2+):**
- YAML frontmatter output — wait for static site generator users to request
- Block-to-markdown enhanced conversion — complex, Gutenberg-specific, defer until demand proven
- Feed endpoint (`/feed/markdown/`) — batch access for LLM crawlers, add when requested
- `/llms.txt` generation — emerging standard, wait for adoption signal

**Anti-features (avoid):**
- Admin settings page — adds complexity; plugin should "just work"
- Per-post markdown toggle — UI clutter for edge case
- Built-in caching layer — WordPress already has object cache infrastructure
- Markdown editor (input direction) — completely different product category

### Architecture Approach

The architecture follows WordPress plugin conventions with a modern PSR-4 autoloaded structure. Components are cleanly separated: URL routing registers rewrite rules, content negotiation inspects headers and intercepts requests, markdown conversion wraps the external library, and output assembly gathers all post data. The data flow is linear: request → route detection → content gathering → HTML-to-Markdown conversion → response with headers.

**Major components:**
1. **URL Router** (`Router/RewriteHandler.php`) — Registers `.md` rewrite rules via WordPress Rewrite API on `init` hook; handles query var registration
2. **Content Negotiator** (`Negotiation/ContentNegotiator.php`) — Detects markdown requests (query var or Accept header) on `template_redirect`; orchestrates response
3. **Markdown Converter** (`Converter/MarkdownConverter.php`) — Wraps `league/html-to-markdown`; isolates external dependency with security configuration
4. **Markdown Renderer** (`Output/MarkdownRenderer.php`) — Assembles post metadata and converted content into final markdown string
5. **Alternate Link** (`Integration/AlternateLink.php`) — Injects `<link rel="alternate">` tags into HTML head via `wp_head` hook

**Key architectural patterns:**
- Single entry point bootstrap (main plugin file only initializes)
- Dependency injection via central Plugin orchestrator
- Early exit pattern (`template_redirect` + output + `exit()`) for non-HTML responses
- Hook registration timing: `init` for rules, `template_redirect` for interception, `wp_head` for links

**Build order (due to dependencies):**
1. Plugin bootstrap + autoloading
2. Rewrite handler (URL patterns used elsewhere)
3. Markdown converter (isolated, independently testable)
4. Markdown renderer (depends on converter)
5. Content negotiator (ties everything together)
6. Alternate link (independent, can build in parallel)

### Critical Pitfalls

Seven critical pitfalls were identified, all well-documented with proven solutions. The most dangerous involve rewrite rule flushing (causes immediate 404s if wrong), content rendering (produces broken output with shortcode artifacts), and caching interference (serves wrong content type).

1. **Rewrite rules not flushed after activation** — Plugin activates but `.md` URLs return 404 because WordPress's cached rules don't include the new patterns. Prevention: Use flag-based flush on first `init` after activation, never flush on every page load (expensive). Address in Phase 1.

2. **Plain permalink structure breaks custom rules** — Plugin fails completely on sites using "Plain" permalinks because mod_rewrite is bypassed. Prevention: Detect and show admin notice, or add query parameter fallback (`?format=markdown`). Address in Phase 1.

3. **Content not rendered before conversion** — Markdown output contains raw `[shortcode]` tags and `<!-- wp:block -->` comments instead of rendered content. Prevention: Apply `the_content` filter before conversion to process shortcodes and render blocks. Address in Phase 2.

4. **Security: Unsanitized HTML in markdown output** — Malicious HTML passes through to markdown, potentially exploitable when displayed elsewhere. Prevention: Configure converter with `strip_tags` option and/or use `wp_kses_post()` sanitization. Address in Phase 2.

5. **Content negotiation interferes with caching** — CDN or page cache serves wrong content type because cache key doesn't account for Accept header. Prevention: Send `Vary: Accept` header; document that `.md` URLs are preferred for production. Address in Phase 3.

6. **Using `template_redirect` with `exit()` breaks other plugins** — Early exit prevents WordPress shutdown hooks, potentially breaking analytics and causing resource leaks. Prevention: Use early priority and be surgical about when to exit; alternatively use `template_include` filter for template swapping. Address in Phase 1.

7. **Featured image returns false without check** — Code assumes `get_the_post_thumbnail_url()` returns string, but it returns `false` when no image, causing "Featured Image: false" in output. Prevention: Always check with conditional before using. Address in Phase 2.

## Implications for Roadmap

Based on combined research, the natural phase structure follows dependency order: infrastructure first (routing and hooks), then content conversion (the core value), then enhanced features (content negotiation and discovery).

### Suggested Phase Structure

#### Phase 1: Core Infrastructure & URL Routing
**Rationale:** Rewrite rules and hook registration are foundational. Everything else depends on URL routing working correctly. This phase establishes the request/response pipeline.

**Delivers:**
- `.md` URLs work for posts and pages
- Proper rewrite rule registration and flushing on activation/deactivation
- Basic request detection and interception via `template_redirect`
- Plain text markdown output (even if simple/unformatted initially)

**Addresses features:**
- `.md` URL endpoint (table stakes)
- Works for posts and pages (table stakes)

**Avoids pitfalls:**
- Pitfall #1: Rewrite rules not flushed (critical)
- Pitfall #2: Plain permalink structure detection (critical)
- Pitfall #6: `template_redirect` exit pattern (critical)

**Research depth:** Standard WordPress patterns. Skip `/gsd:research-phase` — well-documented Rewrite API.

---

#### Phase 2: Content Conversion & Metadata
**Rationale:** With routing working, focus shifts to the core value proposition: converting HTML to clean, accurate markdown with complete metadata.

**Delivers:**
- HTML-to-Markdown conversion using `league/html-to-markdown`
- Post metadata assembly (title, date, author, categories, tags, featured image)
- Proper content rendering (shortcodes and blocks processed)
- Security hardening (HTML sanitization, tag stripping)

**Addresses features:**
- Post content converted to markdown (table stakes)
- Title, date, author, categories, tags (table stakes)
- Featured image URL (differentiator)
- Shortcode stripping (should-have)

**Uses stack:**
- `league/html-to-markdown` library
- WordPress content filters (`the_content`, `do_shortcode`, `do_blocks`)

**Avoids pitfalls:**
- Pitfall #3: Content not rendered (critical)
- Pitfall #4: Security/unsanitized HTML (critical)
- Pitfall #7: Featured image false check (moderate)

**Research depth:** Library integration is straightforward. Skip `/gsd:research-phase` — excellent library documentation.

---

#### Phase 3: Content Negotiation & Discovery
**Rationale:** Core functionality works. Now add content negotiation (Accept headers) and discovery mechanisms (`<link rel="alternate">`).

**Delivers:**
- Accept header detection and response
- `Vary: Accept` header for cache compatibility
- `<link rel="alternate">` tags in HTML head
- Proper `Content-Type` headers

**Addresses features:**
- Content negotiation via Accept header (table stakes)
- `<link rel="alternate">` tag (table stakes)
- Correct Content-Type header (table stakes)

**Avoids pitfalls:**
- Pitfall #5: Caching interference (critical)

**Research depth:** HTTP content negotiation is well-documented. Skip `/gsd:research-phase` unless caching compatibility issues arise.

---

#### Phase 4: Polish & Edge Cases
**Rationale:** MVP is complete. This phase handles edge cases, improves output quality, and prepares for production use.

**Delivers:**
- Query parameter fallback (`?format=markdown`)
- Custom post type support via filter hook
- Enhanced error handling (404s, private posts, password-protected)
- Performance optimizations (transient caching if needed)

**Addresses features:**
- Query parameter fallback (should-have)
- Custom post type support (should-have)

**Defers to v2+:**
- YAML frontmatter option
- Feed endpoint
- Block-to-markdown enhanced conversion
- `/llms.txt` generation

**Research depth:** Mostly edge case handling. Skip research unless custom post type complexity emerges.

---

### Phase Ordering Rationale

- **Phase 1 before Phase 2:** Can't convert content until URL routing works. Rewrite rules must be correct from the start.
- **Phase 2 before Phase 3:** Content negotiation is meaningless without working conversion. Must render content properly before adding alternate delivery mechanisms.
- **Phase 3 separate from Phase 1:** Content negotiation introduces caching complexity. Isolating it allows testing `.md` URLs thoroughly before adding Accept header logic.
- **Phase 4 last:** Edge cases and optimizations make sense only after core is proven. Avoid premature optimization.

**Dependency chain:** Phase 1 → Phase 2 → Phase 3 → Phase 4 (strictly sequential)

### Research Flags

**Phases with standard patterns (skip research-phase):**
- **Phase 1:** WordPress Rewrite API is extensively documented; established activation hook patterns exist
- **Phase 2:** `league/html-to-markdown` has excellent documentation; WordPress content filters are well-known
- **Phase 3:** HTTP content negotiation is standard web protocol; Accept header handling is straightforward
- **Phase 4:** Mostly edge case handling and WordPress best practices

**Phases that might need research-phase:**
- None — all four phases use well-documented WordPress APIs and established libraries. Research already completed is sufficient for roadmap and planning.

**When to trigger research-phase during planning:**
- If custom post type complexity emerges beyond simple filter hook
- If caching plugin compatibility requires deeper investigation of specific cache implementations
- If block-to-markdown conversion is pulled into v1 (currently deferred to v2+)

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | `league/html-to-markdown` is the definitive solution (26M+ downloads, PHP League official). WordPress APIs are official documentation. All version compatibility verified. |
| Features | HIGH | Competitor analysis (Roots plugin) provides clear baseline. RFC 7763 defines `text/markdown` MIME type. Feature categorization validated against user expectations. |
| Architecture | HIGH | Standard WordPress plugin patterns. Data flow is linear and simple. Component boundaries are clear. Build order is dependency-driven. |
| Pitfalls | HIGH | All pitfalls sourced from official WordPress documentation, library maintainers, or documented community issues (ActivityPub caching problems, etc.). Solutions are proven. |

**Overall confidence:** HIGH

The domain is well-understood, the stack is mature, and the architecture follows WordPress conventions. No novel technical challenges were identified. The main risks are implementation pitfalls (rewrite flushing, content rendering) that have documented solutions.

### Gaps to Address

1. **Caching plugin compatibility testing:** Research identified the issue and mitigation (`Vary: Accept` header), but specific cache plugin behavior (WP Rocket, WP Super Cache, W3 Total Cache) needs real-world testing in Phase 3. Handle via manual testing with popular plugins.

2. **Multisite support:** Not explicitly covered in research. If plugin will be network-activated, rewrite rule flushing must be per-site (`delete_option('rewrite_rules')`) rather than `flush_rewrite_rules()`. Address during Phase 1 planning if multisite is in scope.

3. **Gutenberg block rendering edge cases:** Research covered `do_blocks()` for rendering, but specific block types (embeds, custom blocks) may have quirks. Handle via testing in Phase 2; add block-specific converters in v2+ if needed.

4. **CDN-specific configuration:** Research noted CDN issues with Accept header forwarding, but specific CDN configuration (Cloudflare, Fastly, etc.) wasn't detailed. Document in readme during Phase 3 based on testing.

None of these gaps block roadmap creation or initial implementation. They're validation points during respective phases.

## Sources

### Primary (HIGH confidence)
- **WordPress Developer Documentation** — Rewrite API, hook reference (`template_redirect`, `wp_head`, `send_headers`), plugin best practices
- **league/html-to-markdown GitHub & Packagist** — Library documentation, configuration options, security recommendations, usage patterns
- **RFC 7763** — `text/markdown` Media Type specification (official IETF standard)
- **WordPress Coding Standards GitHub** — PHPCS ruleset, naming conventions, namespace guidance

### Secondary (MEDIUM confidence)
- **Roots post-content-to-markdown plugin** — Reference implementation for content negotiation patterns; primary competitor for feature comparison
- **WordPress Plugin Handbook** — Activation/deactivation hooks, rewrite rule flushing patterns
- **WPCode Markdown URLs Snippet** — Community implementation of `.md` URL pattern
- **ActivityPub plugin GitHub issues** — Documented caching conflicts with content negotiation (#783)
- **Developer blog posts** — Andrea Carraro (rewrite rule flushing), Mark Jaquith (`template_redirect` usage), Bill Erickson (block parsing)

### Tertiary (LOW confidence)
- **SSW Rules** — YAML frontmatter best practices (static site generator context, not WordPress-specific)
- **Mintlify blog** — `/llms.txt` emerging standard (new pattern, adoption unclear)

### Source Quality Notes

Stack research has highest confidence due to direct official documentation and library maintainer sources. Features research has medium-high confidence — RFC standard is authoritative, but competitor comparison relies on single reference implementation. Architecture research is high confidence due to official WordPress documentation and established patterns. Pitfalls research is high confidence — all sourced from official docs, library warnings, or documented community issues.

No research areas rely on speculation or single unverified sources. All recommendations have multiple corroborating sources or official documentation backing.

---

*Research completed: 2026-01-30*
*Ready for roadmap: yes*
