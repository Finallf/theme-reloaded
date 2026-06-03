# Changelog

All notable changes to the ReloadeD WordPress theme will be documented in this file.<br>
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).<br>

---
<br>


## [1.1.0-beta.4](https://github.com/Finallf/theme-reloaded/compare/v1.1.0-beta.3...v1.1.0-beta.4) (2026-06-03)

### 🐛 Bug Fixes

* **comments:** remove dead moderation-detection branch in AJAX submit ([8b618fa](https://github.com/Finallf/theme-reloaded/commit/8b618fa4b54591a94a253ca9fd216fa84ae4196f))

<br>

---

## [1.1.0-beta.3](https://github.com/Finallf/theme-reloaded/compare/v1.1.0-beta.2...v1.1.0-beta.3) (2026-06-03)

### 🐛 Bug Fixes

* **comments:** remove dead moderation-detection branch in AJAX submit ([f9fa237](https://github.com/Finallf/theme-reloaded/commit/f9fa23784d57e9e416748a524aa879e6ed481321))

<br>

---

## [1.1.0-beta.2](https://github.com/Finallf/theme-reloaded/compare/v1.1.0-beta.1...v1.1.0-beta.2) (2026-06-03)

### ✨ Features

* **i18n:** make navigation.js user-facing strings translatable ([461e782](https://github.com/Finallf/theme-reloaded/commit/461e78298728560834a51e218a37b24789a59d80))

<br>

---

## [1.1.0-beta.1](https://github.com/Finallf/theme-reloaded/compare/v1.0.1-beta.1...v1.1.0-beta.1) (2026-06-03)

### ✨ Features

* **i18n:** make admin media-picker strings translatable ([92e55cd](https://github.com/Finallf/theme-reloaded/commit/92e55cd0247ebc97835a84afebf163c913c8840b))

### 📝 Documentation

* **contributor:** contrib-readme-action has updated readme ([2c16aa4](https://github.com/Finallf/theme-reloaded/commit/2c16aa4ac3e796138245e258ca99a129c4ca2fd7))

<br>

---

## [1.0.1-beta.1](https://github.com/Finallf/theme-reloaded/compare/v1.0.0...v1.0.1-beta.1) (2026-05-30)

### 🐛 Bug Fixes

* **i18n:** complete and polish pt_BR translation ([9129b7a](https://github.com/Finallf/theme-reloaded/commit/9129b7a47c4d557bf74d7f551080b1b5e26d32b0))

<br>

---

## 1.0.0 (2026-05-30)

### ✨ Features

* public release v1.0.0 ([410c970](https://github.com/Finallf/theme-reloaded/commit/410c97013da69816d8d786a3a86d5f9450e11b5b))

### 📝 Documentation

* **contributor:** contrib-readme-action has updated readme ([c38bc24](https://github.com/Finallf/theme-reloaded/commit/c38bc249544cfb6fbf45bc80ca9cd4869a4dad9b))

<br>

---

# 🗓️ Legacy History (Pre-Opening Repository)

This section records the development history before the public release of this repository.<br>
Entries are dated and aggregated by day rather than mapped to individual releases, since the repository previously held over 90 incremental beta tags (`v0.0.x` → `v0.4.0-beta.67`) that were squashed into a single initial public commit.<br>
The original commit-by-commit history is preserved locally as a `git bundle` backup.

### [2026-05-30]
- **Added:** Public README rewrite for external audience (English-only, Terraria-style polish, 11 admin tabs, FAQ, Roadmap, Screenshots section, dedicated auto-updates section).
- **Changed:** Repository sanitized for public opening — `BACKLOG.md`, `tools/README.md`, `12-operacional-deploy-migracao.md`, and `11-security-comparativo-colormag.md` moved to gitignore (kept locally for development context).
- **Changed:** Sandbox URL `theme.reloaded.com.br` replaced with `reloaded.com.br` across public-facing files.
- **Changed:** Contact email updated to `finallf@reloaded.com.br`.
- **Fixed:** PHPCS configuration — replaced obsolete `inc/Parsedown.php` exclude with `lib/*` to match the new directory layout.
- **Changed:** Image asset renamed for consistency — `logo-reloaded-painel.webp` → `logo-reloaded-panel.webp`.

### [2026-05-29]
- **Added:** Native auto-updater (`inc/mod-self-update.php`) using GitHub Releases as the distribution channel. Custom-built (no external dependencies); checks for new releases every 24 hours and surfaces updates via the standard WordPress "Update available" notice.
- **Added:** Dashboard "Theme Updates" card with **Check for updates** button for immediate refresh, current/latest version display, and conditional CTA when an update is available.
- **Added:** "Update URI:" header in `style.css` to signal to WordPress core 5.8+ that the theme uses an external updater.
- **Changed:** Third-party runtime libraries reorganized into `lib/` directory — `Parsedown.php`, `prism.js`, `chartjs.min.js`. Composer dev dependencies (PHPCS/WPCS) remain in `vendor/`, gitignored.
- **Added:** Print stylesheet (`sass/components/_print.scss`) — hides UI chrome and ads, forces white background, shows external link URLs inline, applies page-break rules.
- **Added:** CDN-ready image filter hook (`inc/mod-cdn-images.php`) — dormant by default, provides `reloaded_image_url` filter for one-line CDN activation (Cloudflare, Bunny, KeyCDN).
- **Changed:** CI workflow trimmed — excludes `vendor/`, `tmp/`, `tools/`, `phpcs.xml.dist`, `composer.{json,lock}`, `package{,-lock}.json`, and `CONTRIBUTING.md` from the release ZIP.

### [2026-05-28]
- **Added:** Wave 13 reader UX features — Sticky Table of Contents (FAB-style with IntersectionObserver), Related Posts at the end of single posts (2-stage algorithm: category match + tag overlap ranking), Author Bio Box with social links, Reading Time estimate, and Search Suggestions with debounced AJAX autocomplete.
- **Added:** ARIA landmarks and skip-to-content link — Lighthouse Accessibility score 100/100 on mobile and desktop.
- **Changed:** Admin panel restructured from 13 tabs to 11 — Redes Sociais merged into Integrations; Doações + Ads merged into Monetization.
- **Changed:** Admin tab slugs translated from Portuguese to English (`geral`/`seguranca`/`integracoes`/`estatisticas`/`manutencao` → English equivalents).
- **Added:** Inline toggle switches on the Dashboard for quick feature ON/OFF without opening individual tabs (9 cards instrumented).
- **Added:** Deep-link gear buttons on Dashboard cards (CSP, Login Protection, Next-gen Images, etc.) jumping directly to the relevant configuration section.
- **Added:** Site Verification expansion — generic `custom_verification_meta` textarea for Pinterest/Yandex/Facebook Domain/Naver/others.
- **Added:** `robots.txt` hint UI showing WordPress defaults for copy-paste customization.
- **Changed:** Backup UI reorganized (3 separate rows for Export/Import/Restore) with full import-preview before applying.
- **Added:** Stats Dashboard empty-state banner with onboarding for sites with tracking off or zero data.

### [2026-05-27]
- **Added:** Dashboard tab (read-only landing page) — Site Status grid (12 feature cards with badges), Quick Metrics, Activity Trend chart (7-day views), Quick Actions, and theme/WP/PHP version footer.
- **Added:** Chart.js doughnut for CSP violations by directive (Security tab) and 7-day bar chart on Dashboard. Generic `data-rd-chart-type` auto-renderer in `admin-charts.js` reusable across tabs.
- **Changed:** Critical CSS regenerated for all 5 templates (home, single, page, archive, search); `inline_critical_css` toggle now defaults to ON.
- **Added:** WebP/AVIF auto-wrap of inline `<img>` tags in post content via DOMDocument filter on `the_content` (priority 20), complementing the existing `wp_get_attachment_image` coverage.
- **Added:** Generic helper `rd_img_wrap_url_in_picture()` for raw `<img>` markup in Discord facade, maintenance page, and WSOD.
- **Changed:** i18n `.pot` regenerated (~436 msgids, +46 from new features); `pt_BR.po` synced and `pt_BR.mo` recompiled.

### [2026-05-24]
- **Added:** Admin design system `rd-p*` — 8 PHP helpers in `inc/panel-helpers.php` (`rd_panel_dash_open/close`, `rd_panel_card_open/close`, `rd_panel_badge`, `rd_panel_status`, `rd_panel_empty`, `rd_panel_section_header`) and matching CSS components in `admin-style.css`.
- **Changed:** Settings API rewritten with new taxonomy — 12 fields atomically migrated between tabs, no orphan window.
- **Added:** "Images & Media" tab and standalone "Backup" tab introduced.
- **Removed:** "Privacy (LGPD)" as a standalone tab — merged into "Security & Privacy".
- **Changed:** 3 custom renderers (CSP Reports, Backup, Stats Dashboard) migrated to the new `rd-p*` design system. ~190 lines of duplicate CSS removed.
- **Fixed:** CSP `style-src-attr` separated from `style-src-elem` (CSP3 spec); output buffer added for legacy `WP_Styles::print_inline_style()`; JS handler swap for `mod-critical-css` `onload` (strict-dynamic doesn't cover inline event handlers).

### [2026-05-23]
- **Added:** Wave 8.5 hardening — Content Security Policy with `'strict-dynamic'` + nonce support (eliminates real `'unsafe-inline'` in modern browsers). Helper trio in `inc/mod-csp.php` propagates nonce to enqueued scripts/styles automatically and to 9 manual instrumentation points.
- **Added:** Custom CSP origins editor — 3 textareas in Security tab (Scripts & APIs, Iframes & Embeds, Styles & Fonts) with validation accepting `https://hostname[:port]`, wildcard subdomains, and schema-only entries.
- **Added:** Login protection module (`inc/mod-login-protection.php`) — combo of hide-login-URL (configurable slug, branded 404 for anonymous access to `/wp-login.php`) and rate limit (configurable threshold + window per IP, using `rd_get_client_ip()` for header-spoof-safe detection).
- **Added:** Admin panel discovery + new taxonomy documents — 92 option keys inventoried across 11 tabs and 18 sections; 8 structural decisions closed (slug renames, privacy unification, backup as standalone, etc.).
- **Changed:** Single class file renamed to follow WPCS `class-{name}.php` convention — `inc/mod-popular-widget.php` → `inc/class-rd-popular-posts-widget.php`. `git mv` preserved history.
- **Added:** Operational documentation — update strategy (WP core, themes, plugins) and rollback plan (theme reversion in under 5 minutes via panel, WP-CLI, or database).
- **Changed:** Font inventory pruned — Inter Italic and Poppins 800 removed (synthesized italic via `font-synthesis`); JetBrains Mono Regular removed from above-the-fold critical path (introduced `$font-system-mono` variable for UI elements that need monospace but aren't code). Net savings: ~105 KB and 2 HTTP requests on the critical path.
- **Added:** `LICENSE` (GPL-2.0+) confirmed present in repo root; `Requires PHP: 8.0` reconfirmed.

### [2026-05-22]
- **Added:** Critical CSS inline + async stylesheet loading (G8) — new `inc/mod-critical-css.php`. Per-template critical extraction via Node + Puppeteer in `tools/critical/extract.js`. Result: LCP Mobile 2.9s → 2.3s (crossed "Good" threshold), Performance score 95 → 98, Accessibility 96 → 100, FCP 1.2s → 1.0s.
- **Added:** Defense-in-depth `defined('ABSPATH') || exit;` guards in 15 root templates (`403.php`, `404.php`, `archive.php`, `author.php`, etc.) — prevents partial output leak if PHP runs outside WP context.
- **Added:** External security audit — OWASP ZAP Active Scan (35 alert categories, ~640 occurrences, 0 real vulnerabilities), Semgrep static analysis (44 rules, 11 findings, 11/11 false positives after triage), WPScan (`--enumerate ap,t,tt,cb,dbe,u,m`, 0 known plugin vulnerabilities), Lighthouse Best Practices 100/100.
- **Added:** REST API security headers — `rest_pre_serve_request` hook extends `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` to `/wp-json/` responses (previously missing).
- **Removed:** Cloudflare Web Analytics on the sandbox — beacon was violating restrictive CSP and removed 1 external script from the critical path.
- **Added:** Comparative CVE document — ColorMag (incumbent theme being replaced in production) vs ReloadeD audit findings. 3 public CVEs identified for ColorMag (all authenticated, all patched within days), compared with 3 internal findings of comparable severity caught proactively by the Wave 9 A code audit.

### [2026-05-21]
- **Added:** PHPCS + WordPress Coding Standards (WPCS) configured in `phpcs.xml.dist` — base `WordPress` ruleset + `PHPCompatibilityWP`, `testVersion 8.0-`, text domain `reloaded`, prefixes `rd`/`RD`. Composer scripts `composer phpcs` and `composer phpcs:fix`.
- **Added:** GitHub Actions job `phpcs-lint` in `.github/workflows/smoke-test.yml` — PHP 8.3, Composer install, full lint pass. CI gate at 0 errors.
- **Fixed:** PHPCS violations across 27 files (~180 lines of formatting) auto-corrected via `phpcbf`; manual fixes for WP-equivalent functions (`strip_tags` → `wp_strip_all_tags`, `date` → `wp_date`, `parse_url` → `wp_parse_url`), `wp_unslash` on `$_GET`/`$_SERVER`, `/* translators: */` hints, escape on echo, and version pins in `wp_enqueue`.

### [2026-05-20]
- **Added:** Internal code audit (Wave 9 A) — 11 vectors swept (SQL injection, XSS, CSRF, capability checks, path traversal, insecure deserialization, open redirect, file upload, REST/AJAX endpoints, HTTP headers, external data sanitization). Result: 3 real issues identified — A1 rate-limit bypass via `CF-Connecting-IP` spoofing, F2-M1 views inflation via the same vector, F4-M1 DoS by noise on the CSP report endpoint. All three fixed within the same day.
- **Added:** WebP/AVIF auto-generation on upload (G10) — `inc/mod-image-formats.php`. Detects Imagick + GD capabilities at runtime, generates next-gen formats for every WordPress + theme custom image size, serves via `<picture>` tag with transparent fallback to the original JPEG/PNG.
- **Added:** Custom image sizes registered in `inc/core.php` — `rd-micro` (150×84), `rd-popular-thumb` (200×113), `rd-card-half` (400×225), `rd-card` (600×338), `rd-full-banner` (1200×675), `rd-qr` (240×240). All 16:9 hard crop except QR codes.
- **Added:** Font preload of 5 critical variants (Inter 500/600/700, Poppins 500/700) via `rd_preload_critical_fonts` in `inc/mod-performance.php`.

### [2026-05-19]
- **Added:** Baseline performance measurement before Wave 8 — Lighthouse Mobile 89-90 (variable), LCP 3.5s, FCP 1.7s. Goals: cross "Good" threshold on LCP, push Performance to 95+.

### [Earlier in May 2026]
- **Added:** Statistics tab with Chart.js — monthly growth bar chart (last 12 months), top posts ranking, IP-deduplicated per-post view tracking with 30-minute window and bot filtering.
- **Added:** Backup module — JSON export/import of all theme settings, with structured preview before applying changes (added/modified/removed keys), and restore from any prior export.
- **Added:** Granular LGPD cookie consent — 4 categories (necessary, analytics, marketing, integrations), category-gated tracking script enqueue, soft reload on consent change.
- **Added:** Native HTTP security headers in `inc/mod-security.php` — X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy. XML-RPC disabled in 4 layers.
- **Added:** Maintenance mode with HTTP 503 status and developer bypass password (bcrypt hashed, accessed via dedicated form at `/?rd-dev-login`).

---
<br>


# 📜 Legacy History (Pre-Automation)

This section records improvements implemented before the migration to the current CI/CD system.<br>
Entries are dated rather than versioned, since formal releases didn't exist during early development.<br>

### [2026-05-01]
- **Changed:** Full refactoring of PHP code, split into separate files following Feature-Based Architecture.
- **Fixed:** Markdown anchor links — IDs now generated exactly as on GitHub.
- **Changed:** Maintenance mode reworked to mirror the theme's visual identity.
- **Added:** Panel option to customize the maintenance mode text.

### [2026-04-30]
- **Changed:** Comments styling fully updated.
- **Added:** Per-post toggle for the featured image (via editor); can be globally disabled in the panel.
- **Changed:** Featured image is now properly processed.
- **Changed:** Posts page updated with date, author, and tag icons (pill-style).
- **Changed:** Grid adjusted to support tablets and medium screens.
- **Changed:** Color and border refinements throughout.

### [2026-04-29]
- **Changed:** Full refactoring of the CSS (SCSS) codebase.
- **Added:** Top-bar with date, latest posts, and social networks for large screens.
- **Added:** Social network icons option.

### [2026-04-28]
- **Changed:** Theme Control Panel code refactored.
- **Added:** Donation options for GitHub Sponsors, PIX, and PayPal.
- **Added:** AdSense slots: Top, Sidebar, and Sticky (Desktop and Mobile).
- **Changed:** Image cropping standardized to 16:9 — 1200×675 (banners), 600×338 (home cards), 150×84 (widgets/sidebar).

### [2026-04-27]
- **Added:** Fallback image selector for Open Graph meta tags.
- **Changed:** PHP code refactored.
- **Added:** Panel option to customize the LGPD cookie banner text.
- **Added:** Panel option to customize the connecting phrase between author and post in the latest comments block.
- **Added:** Panel option to disable Open Graph meta tags.
- **Added:** Full social media optimization with Open Graph meta tags (SEO).

### [2026-04-26]
- **Added:** HTML, CSS, and JavaScript for the LGPD cookie banner.
- **Added:** Menu icon and ReloadeD logo in the Control Panel.
- **Added:** Back-to-Top button.
- **Added:** Panel field for Google Analytics code and ad scripts.
- **Added:** Native Markdown support via Parsedown, with custom GitHub-style CSS.
- **Added:** Aggressive iframe optimization using the Facade pattern (YouTube and Discord).
- **Added:** Native, secure theme Control Panel.

### [2026-04-25]
- **Added:** Search field next to the main menu.
- **Added:** Hamburger menu for mobile devices.
- **Added:** Basic Discord integration.
- **Added:** Native YouTube lazy-load (no plugins) using the Facade pattern.
- **Fixed:** Horizontal scrollbar that was stuck on the page.
- **Added:** Markdown support implemented using [Parsedown](https://github.com/erusev/parsedown).
- **Added:** Native lazy-load (Facade pattern) for Discord, similar to the YouTube implementation.
- **Added:** Scroll-to-top button.

### WordPress Native Options Enabled (`add_theme_support`)
*Date undetermined; added during early scaffolding.*

- **Added:** `custom-logo` — allows uploading a custom logo via the WordPress panel.
- **Added:** `post-thumbnails` — enables Featured Images for posts; required for card visuals.
- **Added:** `title-tag` — lets WordPress automatically manage the browser tab `<title>` (SEO benefit).
- **Added:** `html5` — uses modern HTML5 tags (`<search>`, `<comment-form>`, etc.) instead of legacy table-based markup.
- **Added:** `customize-selective-refresh-widgets` — refresh widgets in the Customizer without reloading the entire page.
- **Added:** `automatic-feed-links` — automatically adds RSS links in the header for feed readers.
- **Added:** `align-wide` — allows full-width images in the Gutenberg editor.
- **Added:** `responsive-embeds` — preserves correct aspect ratios for video blocks (Video, YouTube).
- **Added:** `editor-styles` — displays the theme's fonts and styles inside the Gutenberg editor.
