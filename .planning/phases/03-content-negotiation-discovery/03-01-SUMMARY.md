---
phase: 03-content-negotiation-discovery
plan: 01
subsystem: api
tags: [http-headers, content-negotiation, 303-redirect, vary-header, canonical-url]

# Dependency graph
requires:
  - phase: 02-content-conversion-metadata
    provides: ContentRenderer for markdown output
provides:
  - HTTP response headers for markdown output (Vary, Link, X-Content-Type-Options)
  - Accept header content negotiation with 303 redirect
  - Canonical URL resolution for posts, pages, and archives
affects: [03-02, caching, CDN-configuration]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Response headers via dedicated private method"
    - "Accept header negotiation before URL handling"
    - "303 See Other for format redirects"

key-files:
  created: []
  modified:
    - src/Router/RewriteHandler.php

key-decisions:
  - "Vary: Accept on both markdown responses and 303 redirects for proper caching"
  - "Simple strpos() check for text/markdown - no quality factor parsing"
  - "URL wins over Accept header (markdown_request check first)"

patterns-established:
  - "set_response_headers() pattern for HTTP header management"
  - "get_current_canonical_url() helper for URL resolution across content types"

# Metrics
duration: 2min
completed: 2026-01-30
---

# Phase 3 Plan 1: Response Headers & Accept Negotiation Summary

**HTTP response headers (Vary, Link, X-Content-Type-Options) and Accept header negotiation with 303 redirect to .md URLs**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-30
- **Completed:** 2026-01-30
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Markdown responses include proper HTTP headers: Content-Type, Vary: Accept, Link (canonical), X-Content-Type-Options: nosniff
- Accept: text/markdown on HTML URLs triggers 303 See Other redirect to .md URL
- Canonical URL resolution supports singular posts/pages, category/tag/author/date archives
- URL always wins over Accept header - .md URLs serve markdown regardless of Accept header

## Task Commits

Each task was committed atomically:

1. **Task 1: Add response headers to markdown output** - `7663f00` (feat)
2. **Task 2: Add Accept header negotiation with 303 redirect** - `1476913` (feat)

## Files Created/Modified
- `src/Router/RewriteHandler.php` - Extended with set_response_headers(), handle_accept_negotiation(), get_current_canonical_url()

## Decisions Made
- Used simple strpos() check for text/markdown in Accept header - no quality factor parsing needed per 03-RESEARCH.md recommendation
- Vary: Accept header included on both markdown responses AND 303 redirect responses for proper CDN caching
- Accept negotiation hook registered first at priority 1, ensuring it runs before markdown request handling

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Response headers and Accept negotiation complete
- Ready for 03-02: Alternate link tag injection in HTML head
- Archives support Accept header negotiation but don't have markdown rendering yet (future phase)

---
*Phase: 03-content-negotiation-discovery*
*Completed: 2026-01-30*
