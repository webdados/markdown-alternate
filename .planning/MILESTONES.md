# Project Milestones: Markdown Alternate

## v1.0 MVP (Shipped: 2026-01-30)

**Delivered:** WordPress plugin that serves clean markdown versions of posts and pages via `.md` URLs, content negotiation, and query parameter fallback.

**Phases completed:** 1-4 (8 plans total)

**Key accomplishments:**

- URL routing via `.md` extension — Clean `/post-slug.md` URLs route to markdown output
- Complete markdown conversion — HTML-to-markdown with YAML frontmatter (title, date, author, featured image, categories, tags)
- Content negotiation — `Accept: text/markdown` header triggers 303 redirect to `.md` URL
- Alternate link discovery — `<link rel="alternate" type="text/markdown">` injected in HTML head
- Query parameter fallback — `?format=markdown` for simple clients
- Extensibility — Custom post types via `markdown_alternate_supported_post_types` filter

**Stats:**

- 104 files created/modified
- 754 lines of PHP
- 4 phases, 8 plans
- 1 day from project start to ship

**Git range:** `dd8c480` → `10722d2`

**What's next:** v2 features (llms.txt endpoint, markdown feed, enhanced block support)

---
