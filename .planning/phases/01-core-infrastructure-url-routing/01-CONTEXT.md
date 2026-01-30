# Phase 1: Core Infrastructure & URL Routing - Context

**Gathered:** 2026-01-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Plugin loads correctly, routes `.md` URLs, and serves basic markdown output for posts and pages. This phase establishes the URL routing infrastructure that all subsequent phases build on. Full markdown conversion, content negotiation, and extensibility come in later phases.

</domain>

<decisions>
## Implementation Decisions

### URL Structure
- Nested page URLs use `/parent/child.md` pattern (append .md to final slug)
- Trailing slashes redirect: `/post-slug.md/` → 301 redirect to `/post-slug.md`
- Date-based permalinks supported: if post is at `/2024/01/my-post/`, then `/2024/01/my-post.md` works
- Extension is lowercase only: `.md` works, `.MD` or `.Md` return 404

### Basic Output Format
- Content-Type header: `text/markdown; charset=UTF-8` from the start
- Password-protected posts return 403 Forbidden (respect protection)
- Claude's discretion: minimal output structure (title + content approach)
- Claude's discretion: whether to include frontmatter placeholder

### 404 Behavior
- Non-existent posts: standard WordPress 404 (let WP handle it)
- Draft posts: return 404 (treat as non-existent)
- Private posts: return 404 (treat as non-existent)
- Scheduled posts: return 404 until publish date
- Posts with noindex: serve markdown anyway (SEO settings are independent)

### Activation/Deactivation
- No admin notice on activation (silent)
- Deactivation: only remove rewrite rules (no additional cleanup needed in Phase 1)
- Auto-flush rewrite rules when permalink settings change
- Minimum PHP version: 7.4+ (matches WordPress 6.x minimum)

### Claude's Discretion
- Basic output format details (what minimal markdown looks like)
- Whether to include empty frontmatter block as placeholder
- Internal architecture for URL routing (rewrite rules implementation)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard WordPress plugin approaches.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 01-core-infrastructure-url-routing*
*Context gathered: 2026-01-30*
