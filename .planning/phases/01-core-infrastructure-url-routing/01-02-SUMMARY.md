---
phase: 01-core-infrastructure-url-routing
plan: 02
subsystem: router
tags: [wordpress-rewrite-api, url-routing, markdown, template-redirect, query-vars]

# Dependency graph
requires:
  - phase: 01-01
    provides: Composer PSR-4 autoloading and Plugin singleton
provides:
  - URL rewrite rules for .md URLs
  - Markdown request detection via query var
  - Content serving with text/markdown content type
  - Edge case handling (trailing slash, case sensitivity, post status, password protection)
affects: [02-markdown-conversion, 03-feeds-sitemaps, all-future-phases]

# Tech tracking
tech-stack:
  added: [wordpress-rewrite-api]
  patterns: [rewrite-handler-pattern, early-template-redirect]

key-files:
  created:
    - src/Router/RewriteHandler.php
  modified:
    - src/Plugin.php
    - markdown-alternate.php

key-decisions:
  - "Used non-greedy regex pattern (.+?)\\.md$ to support nested pages"
  - "Priority 1 on template_redirect to intercept before theme templates"
  - "Lowercase .md extension only - uppercase variants return 404"
  - "403 Forbidden for password-protected posts with text/plain message"
  - "Static register_rules() method for activation hook"
  - "Moved activation hooks to main plugin file (WordPress requirement)"

patterns-established:
  - "RewriteHandler pattern: register() method hooks all actions/filters"
  - "Static register_rules() for activation hooks"
  - "Early template_redirect (priority 1) for content interception"

# Metrics
duration: 2min
completed: 2026-01-30
---

# Phase 1 Plan 02: URL Routing Summary

**WordPress rewrite rules for .md URLs with full edge case handling: trailing slash redirect, case-sensitive extension, post status validation, and password protection**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-30T09:01:00Z
- **Completed:** 2026-01-30T09:03:00Z
- **Tasks:** 3 (2 code tasks + 1 verification)
- **Files modified:** 3

## Accomplishments

- Created RewriteHandler with complete URL routing logic for .md URLs
- Added rewrite rule pattern (.+?)\.md$ capturing posts and pages
- Implemented markdown_request query var for request detection
- Added edge case handling: trailing slash 301 redirect, lowercase-only extension, post status validation
- Password-protected posts return 403 Forbidden
- Wired router into Plugin singleton and activation hooks
- Moved activation/deactivation hook registration to main plugin file

## Task Commits

Each task was committed atomically:

1. **Task 1: Create RewriteHandler with URL routing logic** - `e57ef47` (feat)
2. **Task 2: Wire router into Plugin and activation hooks** - `c5ca638` (feat)
3. **Task 3: Verify complete routing** - (verification only, no commit)

## Files Created/Modified

- `src/Router/RewriteHandler.php` - URL rewrite rules and markdown request handling with all edge cases
- `src/Plugin.php` - Added RewriteHandler instantiation and static activation methods
- `markdown-alternate.php` - Added register_activation_hook and register_deactivation_hook calls

## Decisions Made

- **Non-greedy regex pattern:** Used `(.+?)\.md$` to correctly capture nested page slugs like `/parent/child.md`
- **Template redirect priority 1:** Intercept markdown requests early before theme template processing
- **Lowercase extension only:** `.md` works, `.MD` or `.Md` return 404 (per CONTEXT.md decision)
- **403 for password-protected:** Return 403 Forbidden with text/plain message rather than redirect to password form
- **Static activation methods:** Made activate/deactivate static for use as hook callbacks from main plugin file
- **Hooks in main file:** Moved register_activation_hook/register_deactivation_hook to markdown-alternate.php (WordPress requirement)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 1 implementation is complete
- Plugin structure verified with all required files
- Autoloading confirmed working for both classes
- Ready for WordPress installation testing
- Phase 2 will add HTML-to-markdown conversion for post_content

---
*Phase: 01-core-infrastructure-url-routing*
*Completed: 2026-01-30*
