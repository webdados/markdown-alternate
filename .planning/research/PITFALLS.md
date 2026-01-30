# Pitfalls Research

**Domain:** WordPress plugins with custom URL endpoints, rewrite rules, and HTML-to-Markdown conversion
**Researched:** 2026-01-30
**Confidence:** HIGH (verified against WordPress developer documentation and community patterns)

## Critical Pitfalls

### Pitfall 1: Rewrite Rules Not Flushed After Activation

**What goes wrong:**
Plugin registers custom rewrite rules (e.g., for `.md` URLs) but they never work because WordPress's rewrite cache still holds the old rules. Users get 404 errors on `/post-slug.md` URLs immediately after activation.

**Why it happens:**
WordPress stores rewrite rules in the database. Adding rules via `add_rewrite_rule()` only registers them in memory — they must be flushed to the database cache. Developers often call `flush_rewrite_rules()` on every request or forget it entirely.

**How to avoid:**
1. Never call `flush_rewrite_rules()` on every page load — it's expensive
2. Use the activation hook pattern with a flag:
   ```php
   // On activation: set a flag
   register_activation_hook(__FILE__, function() {
       update_option('markdown_alternate_flush_rules', true);
   });

   // On init: check flag and flush once
   add_action('init', function() {
       if (get_option('markdown_alternate_flush_rules')) {
           flush_rewrite_rules();
           delete_option('markdown_alternate_flush_rules');
       }
   }, 20); // Priority 20 = after rules are registered
   ```
3. Alternative: Simply `delete_option('rewrite_rules')` — WordPress regenerates on next load
4. Also flush on deactivation to remove stale rules

**Warning signs:**
- `.md` URLs return 404 immediately after activation
- URLs work after manually saving Settings > Permalinks
- Works in development but fails on fresh installs

**Phase to address:**
Phase 1 (Core Infrastructure) — This is foundational; must be correct from the start.

---

### Pitfall 2: Plain Permalink Structure Breaks Custom Rewrite Rules

**What goes wrong:**
Plugin works fine with pretty permalinks (`/sample-post/`) but completely fails when the site uses "Plain" permalink structure (`?p=123`). Custom rewrite rules are ignored because mod_rewrite is bypassed.

**Why it happens:**
Pretty permalinks require Apache mod_rewrite (or Nginx equivalent). When "Plain" permalinks are active, WordPress routes all requests through query strings without touching .htaccess rules. Any `add_rewrite_rule()` calls become irrelevant.

**How to avoid:**
1. Detect permalink structure on activation and warn users:
   ```php
   if (get_option('permalink_structure') === '') {
       add_action('admin_notices', function() {
           echo '<div class="notice notice-error"><p>';
           echo 'Markdown Alternate requires pretty permalinks. ';
           echo 'Please update Settings > Permalinks.';
           echo '</p></div>';
       });
   }
   ```
2. For `.md` URLs, consider a fallback using query parameter: `?format=markdown`
3. Document this requirement clearly in readme.txt

**Warning signs:**
- Site works with `/%postname%/` but not with plain permalinks
- Works on most sites but fails on specific installations
- No errors in logs — rules simply don't match

**Phase to address:**
Phase 1 (Core Infrastructure) — Must handle gracefully or document limitation.

---

### Pitfall 3: Content Not Rendered Before Conversion (Shortcodes/Blocks)

**What goes wrong:**
Markdown output contains raw shortcode tags like `[gallery ids="1,2,3"]` or Gutenberg block comments like `<!-- wp:image -->` instead of the actual content. The HTML-to-Markdown converter receives unprocessed content.

**Why it happens:**
`get_the_content()` returns raw post content without processing shortcodes or rendering dynamic blocks. Developers assume it returns "ready" HTML. In reality, WordPress applies `the_content` filter with `do_shortcode()` and `do_blocks()` to transform raw content into rendered HTML.

**How to avoid:**
1. Apply the content filter before conversion:
   ```php
   $content = get_the_content(null, false, $post);
   $content = apply_filters('the_content', $content);
   // Now convert to markdown
   ```
2. Be aware this executes all `the_content` filter callbacks (including potentially problematic ones)
3. Consider selective filter application if full `the_content` causes issues:
   ```php
   $content = do_blocks($content);
   $content = do_shortcode($content);
   $content = wpautop($content);
   ```

**Warning signs:**
- Raw `[shortcode]` tags appear in markdown output
- Block comments `<!-- wp:* -->` visible in output
- Embedded content (videos, galleries) missing entirely
- Works on simple posts but fails on complex content

**Phase to address:**
Phase 2 (Content Conversion) — Core to the conversion logic.

---

### Pitfall 4: Security - Unsanitized HTML in Markdown Output

**What goes wrong:**
Plugin converts content that contains malicious HTML (from compromised posts, user-submitted content, or plugins with XSS vulnerabilities). The markdown output preserves or reveals unsafe content that could be exploited when displayed elsewhere.

**Why it happens:**
HTML-to-Markdown converters preserve tags they don't recognize by default. The thephpleague/html-to-markdown library explicitly warns: "If you will be parsing untrusted input... consider setting the `strip_tags` option and using a library like HTML Purifier."

**How to avoid:**
1. Configure the converter to strip unrecognized HTML:
   ```php
   $converter = new HtmlConverter(['strip_tags' => true]);
   ```
2. For additional security, sanitize before conversion:
   ```php
   $content = wp_kses_post($content); // WordPress built-in
   ```
3. Consider the `remove_nodes` option for dangerous tags:
   ```php
   $converter = new HtmlConverter([
       'remove_nodes' => 'script style iframe'
   ]);
   ```

**Warning signs:**
- `<script>`, `<iframe>`, or `<style>` tags appear in markdown output
- Custom HTML from page builders passes through unchanged
- Security scanner flags the output endpoint

**Phase to address:**
Phase 2 (Content Conversion) — Security must be baked into conversion logic.

---

### Pitfall 5: Content Negotiation Interferes with Caching

**What goes wrong:**
CDN or page cache serves the wrong content type. A request with `Accept: text/markdown` gets a cached HTML response, or worse, a markdown response gets cached and served to normal browsers. Cache keys don't account for the Accept header.

**Why it happens:**
Most WordPress caching solutions (WP Super Cache, W3 Total Cache, server-level caching) cache based on URL alone. They don't vary cache by Accept header. Content negotiation assumes the same URL can serve different content types, which breaks naive caching.

**How to avoid:**
1. Send `Vary: Accept` header with all responses:
   ```php
   header('Vary: Accept');
   ```
2. For markdown responses, send no-cache headers:
   ```php
   header('Cache-Control: no-store, no-cache, must-revalidate');
   ```
3. Document that dedicated `.md` URLs are preferred over content negotiation for caching compatibility
4. Consider adding `X-Content-Type-Options: nosniff` to markdown responses

**Warning signs:**
- Intermittent wrong content type served
- Works without caching plugins, fails with them
- First request works, subsequent requests wrong
- Different behavior on CDN vs origin

**Phase to address:**
Phase 3 (Content Negotiation) — Only relevant when implementing Accept header handling.

---

### Pitfall 6: Using `template_redirect` with `exit()` Breaks Other Plugins

**What goes wrong:**
Plugin hooks into `template_redirect`, outputs markdown content, and calls `exit()` or `die()`. This prevents all subsequently-hooked code from running, breaking analytics plugins, logging, and potentially causing database connection leaks.

**Why it happens:**
`template_redirect` is the correct hook for intercepting requests before template loading. However, calling `exit()` after outputting content is a "scorched earth" approach that terminates PHP execution, skipping WordPress shutdown hooks and cleanup.

**How to avoid:**
1. Use `template_redirect` but be surgical:
   ```php
   add_action('template_redirect', function() {
       if (!is_markdown_request()) return;

       // Output markdown
       header('Content-Type: text/markdown; charset=utf-8');
       echo $markdown;

       // Let WordPress clean up properly
       exit;
   }, 1); // Early priority to minimize interference
   ```
2. Alternatively, use `template_include` filter for URL-based routing:
   ```php
   add_filter('template_include', function($template) {
       if (is_md_url()) {
           return PLUGIN_PATH . '/templates/markdown-output.php';
       }
       return $template;
   });
   ```
3. For REST API-style endpoints, consider using `rest_api_init` instead

**Warning signs:**
- Analytics tracking stops working
- Debug bar/query monitor missing data
- Transients not saving properly
- Memory leaks reported on markdown pages

**Phase to address:**
Phase 1 (Core Infrastructure) — Request routing is foundational.

---

### Pitfall 7: Featured Image Returns False/Empty Without Check

**What goes wrong:**
Markdown output shows "Featured Image: false" or causes PHP errors because code assumes `get_the_post_thumbnail_url()` always returns a string.

**Why it happens:**
`get_the_post_thumbnail_url()` returns `false` when no featured image is set. String concatenation or markdown formatting breaks with boolean false.

**How to avoid:**
1. Always check before using:
   ```php
   $featured = get_the_post_thumbnail_url($post_id, 'full');
   if ($featured) {
       $output .= "**Featured Image:** {$featured}\n";
   }
   ```
2. Or use `has_post_thumbnail()` first:
   ```php
   if (has_post_thumbnail($post_id)) {
       $featured = get_the_post_thumbnail_url($post_id, 'full');
       // ...
   }
   ```

**Warning signs:**
- "Featured Image: " with no URL in output
- "Featured Image: false" in output
- PHP warnings about string conversion

**Phase to address:**
Phase 2 (Content Conversion) — Part of metadata handling.

---

## Technical Debt Patterns

Shortcuts that seem reasonable but create long-term problems.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Calling `flush_rewrite_rules()` on every `init` | Rules always current | Major performance hit (~0.1s per request) | Never |
| Using `echo` in `template_redirect` with `die()` | Simple implementation | Breaks other plugins, skips shutdown hooks | MVP only, refactor quickly |
| Hardcoding `.md` extension without option | No settings UI needed | Can't change if conflicts arise | Fine for v1, add filter hook |
| Skipping `apply_filters('the_content')` | Faster conversion | Shortcodes/blocks not rendered | Never |
| Not handling missing featured images | Less code | PHP warnings, ugly output | Never |

## Integration Gotchas

Common mistakes when connecting to external services.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Page Caching Plugins | Markdown response cached for all users | Send `Vary: Accept` header; use no-cache for content-negotiated responses |
| SEO Plugins | `.md` URLs get indexed, creating duplicate content | Add `X-Robots-Tag: noindex` header or `rel="canonical"` pointing to HTML version |
| Multisite | Network-activating breaks rewrite rule flushing | Flush with `delete_option('rewrite_rules')` per-site, not `flush_rewrite_rules()` |
| Translation Plugins | Different language versions not considered | May need `hreflang` handling in alternate links |
| CDN | Accept header not forwarded | Document that `.md` URLs work better than content negotiation behind CDN |

## Performance Traps

Patterns that work at small scale but fail as usage grows.

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Converting content on every request | Slow response times | Cache converted markdown (transient or object cache) | >100 requests/min |
| Loading full HTML-to-Markdown library for all requests | Memory bloat | Lazy-load only when serving markdown | Any high-traffic site |
| Not setting Content-Length header | Chunked encoding, slower delivery | Calculate and send Content-Length | Large posts (>50KB) |
| Applying all `the_content` filters | Very slow on content-heavy sites | Consider selective filter application | Sites with heavy filter chains |

## Security Mistakes

Domain-specific security issues beyond general web security.

| Mistake | Risk | Prevention |
|---------|------|------------|
| Not stripping `<script>` tags | XSS if markdown displayed in another context | Configure converter with `strip_tags` or `remove_nodes` |
| Exposing draft/private posts at `.md` URLs | Information disclosure | Check post status: `$post->post_status === 'publish'` |
| Not checking user permissions for password-protected posts | Bypass password protection | Verify `post_password_required()` before serving |
| Including raw database content without `wp_kses` | Stored XSS vectors passed through | Sanitize with `wp_kses_post()` before conversion |

## UX Pitfalls

Common user experience mistakes in this domain.

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| No error message when permalinks are "Plain" | Plugin appears broken | Admin notice explaining requirement |
| Markdown URLs return HTML on activation before flush | Confusing first experience | Auto-flush rules or clear message to save permalinks |
| No indication markdown version exists | Users never discover feature | Visible `<link rel="alternate">` tag in HTML head |
| `.md` URL returns 404 for non-existent posts | Unhelpful error | Return 404 with plain text "Post not found" |

## "Looks Done But Isn't" Checklist

Things that appear complete but are missing critical pieces.

- [ ] **Rewrite rules:** Tests pass but forgot deactivation cleanup — stale rules persist after uninstall
- [ ] **Content conversion:** Simple posts work but forgot to test pages, custom post types (even if out of scope, should fail gracefully)
- [ ] **Featured images:** Works when image exists but crashes/shows "false" when missing
- [ ] **Content negotiation:** Works in browser dev tools but cache serves wrong response
- [ ] **Link alternate tag:** Added to head but forgot `type="text/markdown"` attribute
- [ ] **URL routing:** `.md` works but `/post-slug.md/` with trailing slash returns 404
- [ ] **Author name:** Shows ID or empty string instead of display name for edge cases (deleted users)
- [ ] **Categories/tags:** Works but crashes on posts with no categories assigned

## Recovery Strategies

When pitfalls occur despite prevention, how to recover.

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Rewrite rules not flushed | LOW | Visit Settings > Permalinks > Save Changes |
| Wrong content cached by CDN | LOW | Purge CDN cache; add proper cache headers |
| `exit()` breaking other plugins | MEDIUM | Refactor to use `template_include` filter; release update |
| Shortcodes not rendered | MEDIUM | Add `apply_filters('the_content')` call; release update |
| XSS in markdown output | HIGH | Emergency update with `strip_tags` option; audit exposed data |
| Plain permalinks incompatible | LOW | Document limitation; optionally add query param fallback |

## Pitfall-to-Phase Mapping

How roadmap phases should address these pitfalls.

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Rewrite rules not flushed | Phase 1: Core Infrastructure | Activate on fresh install; `.md` URLs work immediately |
| Plain permalinks unsupported | Phase 1: Core Infrastructure | Test with "Plain" structure; admin notice appears |
| Content not rendered | Phase 2: Content Conversion | Test post with shortcode; markdown shows rendered content |
| Security - unsanitized HTML | Phase 2: Content Conversion | Test post with `<script>` tag; not present in markdown |
| Caching interference | Phase 3: Content Negotiation | Test with cache plugin; correct content served |
| `template_redirect` with `exit()` | Phase 1: Core Infrastructure | Activate analytics plugin; data still collected |
| Featured image edge cases | Phase 2: Content Conversion | Test post without image; no errors, graceful omission |

## Sources

- [WordPress Rewrite API Documentation](https://developer.wordpress.org/reference/functions/add_rewrite_rule/)
- [flush_rewrite_rules() Function Reference](https://developer.wordpress.org/reference/functions/flush_rewrite_rules/)
- [How to Efficiently Flush Rewrite Rules After Plugin Activation](https://andrezrv.com/2014/08/12/efficiently-flush-rewrite-rules-plugin-activation/)
- [Mastering WordPress Rewrite Rules - BrightMinded](https://brightminded.com/blog/mastering-wordpress-rewrite-rules/)
- [template_redirect Hook Documentation](https://developer.wordpress.org/reference/hooks/template_redirect/)
- [Don't use template_redirect to load an alternative template file - Mark Jaquith](https://markjaquith.wordpress.com/2014/02/19/template_redirect-is-not-for-loading-templates/)
- [thephpleague/html-to-markdown GitHub](https://github.com/thephpleague/html-to-markdown)
- [WordPress wp_kses() Documentation](https://developer.wordpress.org/reference/functions/wp_kses/)
- [WordPress Content Negotiation - WP-Mix](https://wp-mix.com/content-negotiation-wordpress/)
- [Flushing Rewrite Rules in WordPress Multisite - Jeremy Felt](https://jeremyfelt.com/2015/07/17/flushing-rewrite-rules-in-wordpress-multisite-for-fun-and-profit/)
- [Access Block Data with PHP using parse_blocks() - Bill Erickson](https://www.billerickson.net/access-gutenberg-block-data/)

---
*Pitfalls research for: WordPress custom URL endpoints and HTML-to-Markdown conversion*
*Researched: 2026-01-30*
