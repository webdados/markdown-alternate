# Markdown Alternate

## What This Is

A WordPress plugin that provides markdown versions of posts and pages for LLMs and users who prefer clean, structured content over HTML. It exposes markdown via content negotiation (Accept header), dedicated `.md` URLs, and query parameter fallback.

## Core Value

Every post and page should be accessible as clean markdown through a predictable URL pattern (`/post-slug.md`) — enabling LLMs and developers to consume content without HTML noise.

## Requirements

### Validated

- ✓ Add `<link rel="alternate" type="text/markdown">` to post/page `<head>` — v1.0
- ✓ Serve markdown at `/post-slug.md` URLs via WordPress rewrite rules — v1.0
- ✓ Serve markdown on original URL when `Accept: text/markdown` header is present — v1.0
- ✓ Markdown output includes: title, date, author, featured image URL, body content — v1.0
- ✓ Markdown output includes categories and tags at the end — v1.0
- ✓ Works for posts and pages without configuration — v1.0
- ✓ Query parameter fallback (`?format=markdown`) — v1.0
- ✓ Custom post type support via filter hook — v1.0
- ✓ Composer autoloader with PSR-4 namespacing — v1.0
- ✓ WordPress.org readme.txt and GitHub README.md — v1.0
- ✓ Rewrite rule flush on activation/deactivation — v1.0
- ✓ Shortcode and block processing before conversion — v1.0
- ✓ Proper HTTP headers (Content-Type, Vary: Accept) — v1.0

### Active

(None — ready for next milestone planning)

### Out of Scope

- Admin settings/options page — keep it simple, no configuration needed
- Markdown-to-HTML conversion (reverse direction) — only HTML-to-markdown
- Caching layer — rely on WordPress/server caching
- Custom markdown templates — fixed output format

## Context

**Current state:** v1.0 shipped with 754 lines of PHP across 4 classes.

**Tech stack:**
- PHP 7.4+
- Composer with PSR-4 autoloading
- league/html-to-markdown 5.1.1
- WordPress Coding Standards (with exceptions: no Yoda, short array syntax)

**Architecture:**
- `MarkdownAlternate\Plugin` — Singleton orchestrator
- `MarkdownAlternate\RewriteHandler` — URL routing and content serving
- `MarkdownAlternate\ContentRenderer` — YAML frontmatter and markdown assembly
- `MarkdownAlternate\MarkdownConverter` — HTML-to-markdown wrapper
- `MarkdownAlternate\AlternateLinkHandler` — Discovery link injection

**v2 candidates:**
- `llms.txt` endpoint for LLM discovery
- Markdown feed endpoint (`/feed/markdown/`)
- Enhanced Gutenberg block conversion
- Custom frontmatter fields via filter

## Constraints

- **PHP Version**: PHP 7.4+ — minimum supported version
- **Code Style**: WordPress Coding Standards with exceptions: no Yoda conditions, short array syntax `[]`
- **Architecture**: Namespaced, object-oriented PHP with Composer autoloader
- **Documentation**: readme.txt (WordPress.org format) + README.md (GitHub/local)
- **Dependencies**: Minimize external dependencies; use built-in WordPress functions where possible

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| .md extension URLs | Clean, intuitive URL pattern that's widely understood | ✓ Good |
| No admin settings | Simplicity; plugin should just work | ✓ Good |
| Posts and pages by default | Focused scope for v1; custom post types via filter | ✓ Good |
| Singleton pattern | Single plugin instance, clean lifecycle management | ✓ Good |
| PSR-4 autoloading | Modern PHP, clean class loading | ✓ Good |
| Non-greedy regex for URLs | Support nested pages like `/parent/child.md` | ✓ Good |
| Lowercase .md only | Consistent, predictable behavior | ✓ Good |
| 403 for password posts | Security; don't bypass WordPress protection | ✓ Good |
| 303 redirect for Accept | RFC-compliant, cacheable content negotiation | ✓ Good |
| YAML frontmatter | Standard format, machine-readable metadata | ✓ Good |
| Categories/tags in footer too | Human-readable redundancy for quick scanning | ✓ Good |
| Strip script/style/iframe | Security; prevent XSS in markdown output | ✓ Good |
| Filter hook for post types | Extensibility without configuration UI | ✓ Good |
| Query param as third option | Fallback for clients that can't set headers | ✓ Good |

---
*Last updated: 2026-01-30 after v1.0 milestone*
