# Changelog

All notable changes to the ReloadeD WordPress theme will be documented in this file.<br>
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).<br>

---
<br>


# [0.1.0-beta.5](https://github.com/Finallf/theme-reloaded/compare/v0.1.0-beta.4...v0.1.0-beta.5) (2026-05-08)


### Bug Fixes

* **seo:** validate og:image source and stop declaring fake dimensions ([e09cf82](https://github.com/Finallf/theme-reloaded/commit/e09cf8276e71660f13807261147b0912510e4f83))


<br>

---

# [0.1.0-beta.4](https://github.com/Finallf/theme-reloaded/compare/v0.1.0-beta.3...v0.1.0-beta.4) (2026-05-08)


### Features

* **pagination:** add styled pagination across listing pages ([d7e2a73](https://github.com/Finallf/theme-reloaded/commit/d7e2a735485b071f5f4d9748c76750f51c434907))


<br>

---

# [0.1.0-beta.3](https://github.com/Finallf/theme-reloaded/compare/v0.1.0-beta.2...v0.1.0-beta.3) (2026-05-08)


### Features

* **templates:** add author and archive templates ([dae69b4](https://github.com/Finallf/theme-reloaded/commit/dae69b452e7ca9f7801f3b6b0bab2e294da6a9ce))


<br>

---

# [0.1.0-beta.2](https://github.com/Finallf/theme-reloaded/compare/v0.1.0-beta.1...v0.1.0-beta.2) (2026-05-08)


### Features

* **search:** add multi-layout search results page ([914e138](https://github.com/Finallf/theme-reloaded/commit/914e13876470af6da290038fe458a310a2cfbea4))

<br>

---

# [0.1.0-beta.1](https://github.com/Finallf/theme-reloaded/compare/v0.0.0...v0.1.0-beta.1) (2026-05-06)


### Features

* expand WordPress compatibility up to 6.9.4 ([3c9ca1b](https://github.com/Finallf/theme-reloaded/commit/3c9ca1bd451100dfe3a3f1d515faa8bf9ae1b90d))

# 📜 Legacy History (Pre-Automation)

This section records improvements implemented before the migration to the current CI/CD system. Entries are dated rather than versioned, since formal releases didn't exist during early development.

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
