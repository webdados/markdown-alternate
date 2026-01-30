# Feature Research

**Domain:** WordPress Alternate Content Format Plugin (Markdown)
**Researched:** 2026-01-30
**Confidence:** MEDIUM

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| `.md` URL endpoint | Standard pattern for alternate formats (like `.json`, `.xml`); intuitive discovery | LOW | WordPress rewrite rules, template intercept |
| Content negotiation via `Accept: text/markdown` | RFC 7763 defines `text/markdown` MIME type; modern web standard | MEDIUM | Must handle header parsing, response headers, `Vary: Accept` |
| `<link rel="alternate">` in `<head>` | Standard HTML5 way to declare alternate representations; enables discovery | LOW | `wp_head` action, simple output |
| Post title in output | Markdown without title is incomplete document | LOW | Access via `get_the_title()` |
| Post content converted to markdown | The core value proposition; HTML-to-markdown conversion | MEDIUM | Requires HTML-to-markdown library (League HTMLToMarkdown or similar) |
| Publication date | Expected metadata for any article format | LOW | Access via `get_the_date()` |
| Author name | Expected metadata for attribution | LOW | Access via `get_the_author()` |
| Works on posts | Primary content type in WordPress | LOW | Default behavior |
| Works on pages | Secondary content type users expect | LOW | Same handler as posts |
| Correct `Content-Type` header | `text/markdown; charset=UTF-8` per RFC 7763 | LOW | PHP `header()` call |

### Differentiators (Competitive Advantage)

Features that set the product apart. Not required, but valuable.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Zero configuration | Competes with Roots plugin which requires filters for pages; "just works" is rare | LOW | Deliberate simplicity, no options page |
| Featured image URL in metadata | LLMs and static site generators value this; competitors often omit | LOW | `get_the_post_thumbnail_url()` |
| Categories and tags in output | Useful for content classification; aids LLM understanding | LOW | `get_the_category()`, `get_the_tags()` |
| YAML frontmatter output | Compatible with static site generators (Hugo, Jekyll, Eleventy); LLM-friendly structured metadata | MEDIUM | Structured header instead of inline metadata |
| Custom post type support via filter | Power users can extend without plugin modification | LOW | `apply_filters()` pattern |
| Shortcode stripping | Clean output without `[shortcode]` artifacts that confuse LLMs | MEDIUM | `strip_shortcodes()` or selective rendering |
| Block-to-markdown conversion | Gutenberg blocks rendered properly, not as HTML comments | HIGH | Requires block parsing, per-block conversion |
| Feed endpoint `/feed/markdown/` | Batch discovery of all posts in markdown; valuable for LLM crawlers | MEDIUM | Custom feed template, pagination |
| Query parameter fallback `?format=markdown` | Works when Accept headers can't be set (browser testing, simple tools) | LOW | URL parameter check |
| `/llms.txt` generation | Emerging standard for LLM content discovery; early mover advantage | MEDIUM | Site-wide index file generation |
| Excerpt/summary in metadata | Useful for LLM context without full content | LOW | `get_the_excerpt()` |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create problems.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Admin settings page | "Customize everything" | Adds complexity, maintenance burden, decision fatigue; plugin should "just work" | Use filters for power users |
| Per-post markdown toggle | "Some posts shouldn't have markdown" | Clutters UI, rarely needed, edge case for v1 | Filter hook to exclude specific posts |
| Custom markdown templates | "I want different output format" | Template maintenance, breaking changes, scope creep | Fixed format with filter for post-processing |
| Markdown editor (input) | "Write in markdown too" | Completely different product category; many plugins do this well (Jetpack, WP Githuber MD) | Recommend existing markdown editor plugins |
| Markdown import | "Upload .md files" | Different use case, Ultimate Markdown does this well | Out of scope |
| Built-in caching layer | "Performance!" | Adds complexity; WordPress object cache, server cache, CDN cache already exist | Rely on existing caching infrastructure |
| Real-time conversion preview | "See what it looks like" | Requires JavaScript, complexity for minimal value | Users can visit `.md` URL directly |
| PDF export alongside markdown | "I want PDF too" | Completely different format, different libraries, scope creep | Separate plugin |
| Bidirectional sync | "Keep markdown and HTML in sync" | Complexity explosion, conflict resolution, data integrity risks | One-way only: HTML-to-markdown on request |
| RSS feed modification | "Include markdown in RSS" | Interferes with existing RSS functionality, surprising behavior | Separate `/feed/markdown/` endpoint |

## Feature Dependencies

```
[.md URL endpoint]
    |
    +--requires--> [HTML-to-markdown conversion library]
    |                   |
    |                   +--enhances--> [Shortcode stripping]
    |                   +--enhances--> [Block-to-markdown conversion]
    |
    +--enhances--> [Query parameter fallback ?format=markdown]

[Content negotiation Accept header]
    |
    +--requires--> [.md URL endpoint logic] (shared conversion code)
    +--requires--> [Correct Content-Type header]
    +--requires--> [Vary: Accept header] (for caching compatibility)

[<link rel="alternate">]
    (independent, no dependencies)

[YAML frontmatter]
    |
    +--requires--> [Title, date, author, categories, tags] (metadata features)
    +--conflicts--> [Inline metadata format] (choose one style)

[Feed endpoint /feed/markdown/]
    |
    +--requires--> [.md URL endpoint logic]
    +--enhances--> [/llms.txt generation] (can reference feed)

[Custom post type support]
    |
    +--enhances--> [Works on posts/pages]
    +--requires--> [Filter hook architecture]
```

### Dependency Notes

- **HTML-to-markdown conversion** is the foundational capability; all output features depend on it
- **Shortcode stripping** should happen before markdown conversion to prevent artifacts
- **YAML frontmatter and inline metadata** are mutually exclusive output styles; choose one
- **Vary: Accept header** is required for caching compatibility with content negotiation
- **Feed endpoint** reuses core conversion logic but adds pagination and list structure
- **Block-to-markdown** is enhancement-only; basic conversion works without it but produces inferior output for Gutenberg content

## MVP Definition

### Launch With (v1)

Minimum viable product that validates core value proposition.

- [x] `.md` URL endpoint for posts and pages - Core feature, enables discovery
- [x] `<link rel="alternate" type="text/markdown">` in head - Enables programmatic discovery
- [x] Content negotiation via `Accept: text/markdown` - Modern web standard
- [x] Title, date, author in output - Essential metadata
- [x] Post content converted to markdown - The core value
- [x] Categories and tags in output - Useful context for LLMs
- [x] Featured image URL - Valuable metadata often omitted by competitors
- [x] Works for posts and pages without configuration - Zero-friction adoption

### Add After Validation (v1.x)

Features to add once core is working and users request them.

- [ ] YAML frontmatter as output option - Trigger: Static site generator users request it
- [ ] Query parameter `?format=markdown` fallback - Trigger: Users report Accept header limitations
- [ ] Custom post type support via filter - Trigger: Users with CPTs want markdown
- [ ] Shortcode stripping option - Trigger: Shortcode artifacts reported as problem
- [ ] Feed endpoint `/feed/markdown/` - Trigger: LLM crawlers request batch access

### Future Consideration (v2+)

Features to defer until product-market fit is established.

- [ ] Block-to-markdown conversion (enhanced) - Complex, Gutenberg-specific, wait for demand
- [ ] `/llms.txt` generation - Emerging standard, wait for adoption signal
- [ ] Excerpt/summary metadata field - Nice to have, not essential
- [ ] Post thumbnail as inline image - Could clutter output

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| `.md` URL endpoint | HIGH | LOW | P1 |
| `<link rel="alternate">` in head | HIGH | LOW | P1 |
| Content negotiation | MEDIUM | MEDIUM | P1 |
| Title/date/author metadata | HIGH | LOW | P1 |
| HTML-to-markdown conversion | HIGH | MEDIUM | P1 |
| Categories/tags in output | MEDIUM | LOW | P1 |
| Featured image URL | MEDIUM | LOW | P1 |
| Correct Content-Type header | HIGH | LOW | P1 |
| YAML frontmatter option | MEDIUM | LOW | P2 |
| Query parameter fallback | LOW | LOW | P2 |
| Custom post type filter | MEDIUM | LOW | P2 |
| Shortcode stripping | MEDIUM | LOW | P2 |
| Feed endpoint | MEDIUM | MEDIUM | P2 |
| Block-to-markdown (enhanced) | LOW | HIGH | P3 |
| `/llms.txt` generation | LOW | MEDIUM | P3 |

**Priority key:**
- P1: Must have for launch
- P2: Should have, add when possible
- P3: Nice to have, future consideration

## Competitor Feature Analysis

| Feature | Roots post-content-to-markdown | Ultimate Markdown | Simple Export to MD | Our Approach |
|---------|-------------------------------|-------------------|---------------------|--------------|
| Accept header negotiation | Yes | No | No | Yes |
| Query parameter | Yes (`?format=markdown`) | No | No | v1.x |
| `.md` URL pattern | No (relies on Accept/query) | No | No | Yes (core feature) |
| `<link rel="alternate">` | RSS feed only | No | No | Yes |
| Feed endpoint | Yes (`/feed/markdown/`) | No | No | v1.x |
| Zero config | No (needs filter for pages) | No (has settings) | Yes (editor-only) | Yes |
| YAML frontmatter | Yes (in feed) | Yes (import) | No | v1.x |
| Custom post type support | Via filter | Via settings | No | v1.x via filter |
| Posts per page config | Yes | N/A | N/A | Not needed |
| HTML-to-markdown library | Yes | Yes (Turndown) | Yes (Turndown) | Yes |

### Competitive Positioning

**Roots post-content-to-markdown** is the closest competitor. Our differentiation:
1. Dedicated `.md` URLs (intuitive, bookmarkable, shareable)
2. Zero configuration for posts AND pages (Roots needs filter for pages)
3. `<link rel="alternate">` on every post/page (not just RSS)

**Ultimate Markdown** focuses on input (writing in markdown) not output. Different product category.

**Simple Export to MD** is editor-only export (manual download). Not automated/discoverable.

## Caching Considerations

**Critical Issue:** Content negotiation (`Accept: text/markdown`) conflicts with many WordPress caching plugins. The ActivityPub plugin has documented this extensively.

**Mitigation Strategies:**
1. Always send `Vary: Accept` header with negotiated responses
2. Document caching plugin compatibility in readme
3. `.md` URLs sidestep the issue entirely (different URL = different cache entry)
4. Recommend `.md` URLs over content negotiation for production use

**Caching Plugin Compatibility:**
- WP Super Cache: Partial support with configuration
- W3 Total Cache: May require Vary header configuration
- Redis Object Cache: Generally works
- Cloudflare: Respects Vary header if configured
- WP Rocket: Unknown, needs testing

## Sources

- [Roots post-content-to-markdown](https://github.com/roots/post-content-to-markdown) - Primary competitor analysis
- [Ultimate Markdown](https://wordpress.org/plugins/ultimate-markdown/) - Feature comparison
- [RFC 7763: text/markdown Media Type](https://www.rfc-editor.org/rfc/rfc7763.html) - MIME type specification
- [shkspr.mobi: link rel="alternate" type="text/plain"](https://shkspr.mobi/blog/2024/05/link-relalternate-typetext-plain/) - Implementation approach
- [Mintlify: What is llms.txt?](https://www.mintlify.com/blog/what-is-llms-txt) - Emerging standard for LLM discovery
- [ActivityPub caching issues](https://github.com/Automattic/wordpress-activitypub/issues/783) - Content negotiation + caching conflicts
- [SSW Rules: Best practices for Frontmatter](https://www.ssw.com.au/rules/best-practices-for-frontmatter-in-markdown) - YAML frontmatter guidance
- [MDN: Content negotiation](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Content_negotiation) - HTTP standard reference

---
*Feature research for: WordPress Markdown Alternate Plugin*
*Researched: 2026-01-30*
