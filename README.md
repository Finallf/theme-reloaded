<p align="center">
	<a href="https://reloaded.com.br/"><img alt="ReloadeD" src="https://raw.githubusercontent.com/Finallf/theme-reloaded/master/assets/img/reloaded-banner.webp"></a>
</p>

<br>

<p align="center">
	<a href="#-features">Features</a>&nbsp;&nbsp;•&nbsp;
	<a href="#%EF%B8%8F-requirements">Requirements</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-installation">Installation</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-auto-updates">Auto-updates</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-screenshots">Screenshots</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-theme-options">Theme Options</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-faq">FAQ</a>&nbsp;&nbsp;•&nbsp;
	<a href="#%EF%B8%8F-roadmap">Roadmap</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-branches--releases">Branches & Releases</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-development">Development</a>&nbsp;&nbsp;•&nbsp;
	<a href="#-support-the-project--apoie-o-projeto">Support</a>
</p>

<br>

---
## 🖥️ About the Project
### High-performance custom WordPress theme for technology, gaming, and news portals. Built around a strict <em>zero external plugins</em> philosophy — every feature is bundled into the theme itself and exposed through an 11-tab admin panel.

<br>

<p align="center">
	<a href="https://github.com/Finallf/theme-reloaded"><img alt="GitHub" src="https://img.shields.io/badge/GitHub-Finallf%2Ftheme--reloaded-blue?style=plastic&logo=github"></a>
	&nbsp;
	<a href="https://github.com/Finallf/theme-reloaded?tab=GPL-2.0-1-ov-file"><img alt="License" src="https://img.shields.io/github/license/Finallf/theme-reloaded?style=plastic"></a>
	&nbsp;
	<a href="https://github.com/Finallf/theme-reloaded/commits/master"><img alt="Last commit" src="https://img.shields.io/github/last-commit/Finallf/theme-reloaded?style=plastic"></a>
	&nbsp;
	<a href="https://github.com/Finallf/theme-reloaded/releases"><img alt="Latest release" src="https://img.shields.io/github/v/release/Finallf/theme-reloaded?style=plastic"></a>
	&nbsp;
	<a href="https://github.com/Finallf/theme-reloaded/releases"><img alt="Downloads" src="https://img.shields.io/github/downloads/Finallf/theme-reloaded/total?style=plastic"></a>
	&nbsp;
	<a href="https://github.com/Finallf/theme-reloaded"><img alt="Stars" src="https://img.shields.io/github/stars/Finallf/theme-reloaded?style=plastic"></a>
	<br>
	<br>
	<a href="https://wordpress.org"><img alt="WordPress" src="https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=plastic&logo=wordpress&logoColor=white"></a>
	&nbsp;
	<a href="https://www.php.net"><img alt="PHP" src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=plastic&logo=php&logoColor=white"></a>
	&nbsp;
	<a href="https://mariadb.org"><img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-10.5%2B-003545?style=plastic&logo=mariadb&logoColor=white"></a>
</p>

<br>

> [!NOTE]
> **Status:** Production-ready. Continuously evolving via `feat`/`fix` releases.

<br>

---
## 🪶 Features

### Zero external plugins
 - ✅ Self-contained architecture — no third-party plugin dependencies for core functionality. Markdown rendering, syntax highlighting, charts, and all features ship inside the theme itself.

### Native auto-updater via GitHub Releases
 - ✅ After the first manual install, the theme automatically checks GitHub for new releases every 24 hours. When a new version is published, the standard WordPress "Update available" notice appears — one click updates the theme, exactly like themes from the official repository.

### Native Markdown post authoring
 - ✅ Write posts in Markdown syntax (similar to GitHub READMEs) and have them rendered automatically through Parsedown. Code blocks are highlighted via Prism.js, loaded only on single posts to preserve performance.

### 11-tab admin control panel
 - ✅ Every feature is exposed via a dedicated tab — Dashboard, General, Images & Media, Performance, Security & Privacy, Integrations, SEO, Monetization, Statistics, Maintenance, and Backup. All settings are documented inline with contextual help for each option.

### Built-in security suite
 - ✅ Native Content Security Policy (Report-Only + Enforce modes with custom origins editor), login protection (rate limit + hide URL via configurable slug), HTTP security headers (X-Frame-Options, Referrer-Policy, Permissions-Policy), XML-RPC disabled in four layers, and defense-in-depth `defined('ABSPATH')` guards across all PHP files.

### Performance-first architecture
 - ✅ Critical CSS inline with async stylesheet loading (per-template), automatic WebP/AVIF generation for uploaded images with `<picture>` wrap, lazy iframe facade system for YouTube and Discord embeds, font preload of critical variants, WordPress bloat removal (emojis, Gutenberg CSS, generator meta), and a CDN-ready image URL filter for one-line activation of Cloudflare/Bunny/KeyCDN.

### Native engagement tracking and Statistics Dashboard
 - ✅ Per-post view counter with automatic bot filtering and 30-minute IP deduplication. Dedicated Statistics tab with Chart.js visualizations: monthly growth, top posts, traffic trends. A 7-day activity chart is also embedded on the Dashboard for quick overview.

### Native backup and restore with import preview
 - ✅ One-click export of all theme settings to JSON. Import flow includes a structured preview before applying changes (shows added, modified, and removed keys). Restore from any prior export. All changes are audited locally — no external services contacted.

### Reader UX bonuses
 - ✅ Reading time estimate on every post, Sticky Table of Contents (FAB-style on long posts), Related Posts at the end of single posts (algorithm based on shared categories and tags), Author Bio Box with social links, and Search Suggestions with debounced AJAX autocomplete.

### Print-friendly and 100/100 Accessibility
 - ✅ Dedicated print stylesheet that hides all UI chrome and ads, forces white background and high-contrast text, and shows external link URLs inline. Lighthouse Accessibility score of 100/100 on both mobile and desktop, with ARIA landmarks, skip-to-content link, and keyboard navigation on all interactive elements.

### LGPD compliance and SEO out of the box
 - ✅ Granular cookie consent banner with 4 categories (necessary, analytics, marketing, integrations), Open Graph and Twitter Card meta tags, native sitemap (configurable), customizable `robots.txt`, Schema.org JSON-LD for posts and Organization, and HTTP 503 maintenance mode that preserves SEO.

### Donations and integrations
 - ✅ Built-in support for GitHub Sponsors, PayPal (with QR code), and PIX (with QR code and copy-paste key). Discord server widget. Google Analytics, Microsoft Clarity, Facebook Pixel, TikTok Pixel, Plausible, and Umami integrations. Eight social network links. Pre-defined ad zones for AdSense (728×90, 320×100, 300×250, 300×600) plus a global header script slot.

<br>

---
## ⚒️ Requirements

> [!IMPORTANT]
> ✔️ **WordPress 6.0 or later** (tested with 6.9.4 and 7.0)
> ✔️ **PHP 8.0 or later** (CI-verified 8.0–8.3 compatibility via PHPCompatibilityWP, actively tested on 8.3)
> ✔️ **MariaDB 10.5+** (or MySQL equivalent)
> ✔️ **HTTPS strongly recommended** (required for CSP, secure cookies, and modern browser features)

<br>

---
## 📦 Installation

There are two ways to install the ReloadeD theme on your WordPress site.

<br>

### Option 1 — From the WordPress admin (recommended)

&emsp;1 - Download the latest `reloaded.zip` from the [Releases page](https://github.com/Finallf/theme-reloaded/releases).

&emsp;2 - In your WordPress admin, navigate to **Appearance → Themes → Add New → Upload Theme**.

&emsp;3 - Select the downloaded `reloaded.zip` file and click **Install Now**.

&emsp;4 - Once installation is complete, click **Activate** to apply the theme.

<br>

### Option 2 — Manual upload via FTP/SFTP

&emsp;1 - Download and extract `reloaded.zip` locally.

&emsp;2 - Upload the extracted `reloaded` folder to your server's `wp-content/themes/` directory.

&emsp;3 - In your WordPress admin, go to **Appearance → Themes** and click **Activate** on ReloadeD.

<br>

> [!NOTE]
> After activation, the theme's control panel becomes available under **ReloadeD** in the WordPress admin sidebar (positioned right below Dashboard).

> [!TIP]
> **Beta channel users:** to get pre-release builds with features in active testing, download the `reloaded.zip` from a release tagged as **Pre-release** on the [Releases page](https://github.com/Finallf/theme-reloaded/releases). See [Branches & Releases](#-branches--releases) for the channel model.

<br>

---
## 🔄 Auto-updates

After the first manual install, the theme automatically checks GitHub Releases for new versions every 24 hours. When a new stable release is published, the standard **"Update available"** notice appears on **Appearance → Themes** — one click updates the theme, exactly as if it came from the WordPress.org repository.

The auto-updater is built into the theme itself, with no external dependencies:

 - 🔹 Queries the public GitHub API anonymously (no token required, no telemetry)
 - 🔹 Compares the `Version:` header in `style.css` against the latest GitHub release tag
 - 🔹 Downloads the binary `reloaded.zip` asset attached to each release (not the auto-generated source tarball)
 - 🔹 Ignores pre-releases by default — only stable releases from the `master` branch trigger update notices
 - 🔹 Renders release notes (rendered from Markdown) in the standard WordPress "View version details" modal

<br>

> [!TIP]
> Use the **Check for updates** button in **ReloadeD → Dashboard → Theme Updates** to force an immediate check without waiting 24 hours. Useful right after a new release is published.

<!-- TODO: add screenshot of the "Theme Updates" card in the Dashboard once production screenshots are ready -->

<br>

---
## 📸 Screenshots

> [!NOTE]
> Screenshots below reflect the sandbox at `reloaded.com.br`. Production version with real content will be added once the portal is live.

<!-- TODO: replace placeholders with real screenshots once production content is ready -->

| Dashboard | Theme Updates card | Backup & Restore |
| :---: | :---: | :---: |
| <em>screenshot soon</em> | <em>screenshot soon</em> | <em>screenshot soon</em> |

| Statistics | CSP Reports | Frontend Home |
| :---: | :---: | :---: |
| <em>screenshot soon</em> | <em>screenshot soon</em> | <em>screenshot soon</em> |

<br>

---
## 🎨 Theme Options

After activation, the theme's control panel is accessible via **ReloadeD** in the WordPress admin sidebar. All settings are organized into **11 dedicated tabs**.

<br>

### 📊 Dashboard

Read-only landing page with current state at a glance — feature status badges, quick metrics (24h views, posts, comments), 7-day activity chart, theme update status, and quick action shortcuts.

<br>

### 🔧 General

Core theme behavior — UI controls, date formatting, and content presentation.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Back to top button | Floating button at the bottom-right for quick page-top navigation | `ON` |
| Top bar | Header strip with date, latest news ticker, and social links | `ON` |
| Featured image control | Per-post toggle to hide the featured image when reading (ideal for video posts) | `ON` |
| Comment accessibility | Adds labels and autocomplete attributes to the comment form | `ON` |
| "Read More" button text | Custom text for the excerpt button | `Empty` |
| Date format | PHP-style date format string | `Empty` |
| View counter | Per-post view tracking (1 IP = 1 view per 30 minutes, bots auto-filtered) | `ON` |

<br>

### 🖼️ Images & Media

Image processing and next-generation format delivery.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Image hard crop | Forces exact crops on uploaded images for banner/card alignment | `ON` |
| JPEG quality | Image quality in % (WP default is 82). Lower = faster, less fidelity | `90` |
| Next-gen formats | Generate WebP and/or AVIF on upload (auto-detects Imagick/GD capabilities) | `ON (AVIF)` |
| Format mode | `avif`, `webp`, or `both` — controls which next-gen formats are generated | `avif` |
| Regenerate attachments | One-click batch processor for existing Media Library images | — |

<br>

### ⚡ Performance

Technical optimizations — rendering, asset loading, and WordPress bloat removal.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Markdown | Enable Markdown syntax in posts (rendered via Parsedown) | `ON` |
| Code highlighting | Prism.js for code blocks (loaded only on single posts) | `ON` |
| Critical CSS | Inline above-the-fold CSS per template + async load main stylesheet | `ON` |
| Iframe facade system | Replace heavy iframes (YouTube, Discord) with lightweight placeholders | `ON` |
| Disable native emojis | Remove WordPress emoji script (modern browsers render emojis natively) | `ON` |
| Disable Gutenberg CSS | Remove Gutenberg block styles globally (recommended with Markdown) | `ON` |
| Hide WP version | Remove the `<meta name="generator">` tag from the source code | `ON` |

<br>

### 🔒 Security & Privacy

Hardening features and LGPD compliance — bundled into one tab.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Content Security Policy | Native CSP with Report-Only and Enforce modes, custom origins editor, REST endpoint for browser reports | `Report-Only` |
| Login protection | Rate limit failed attempts (configurable threshold + window) plus optional hide-login slug | `ON` |
| Trusted Proxy IPs | CIDR allowlist for header-spoof-safe IP detection (Cloudflare ranges hardcoded) | `Empty` |
| Cookie banner | Granular consent for 4 categories (necessary, analytics, marketing, integrations) | `ON` |
| Cookie banner text | Custom HTML for the banner (basic markup allowed) | `Default LGPD message` |

<br>

### 🔌 Integrations

External analytics and marketing pixels (all opt-in, gated by cookie consent).

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Google Analytics | Tracking ID (e.g. `G-XXXXXXX`) | `Empty` |
| Microsoft Clarity | Project ID for heatmaps | `Empty` |
| Facebook Pixel | Pixel ID | `Empty` |
| TikTok Pixel | Pixel ID | `Empty` |
| Plausible / Umami | Self-hosted analytics endpoint and domain | `Empty` |
| Discord widget | Display the Discord server widget in the sidebar | `OFF` |
| Discord server ID | Server ID required for the official Discord widget | `Empty` |
| Social networks | 8 channels (Discord, Telegram, YouTube, Instagram, Steam, Twitter/X, Facebook, WhatsApp) | `Empty` |

<br>

### 🔍 SEO

Open Graph, Twitter Cards, Schema.org, sitemap, and meta verification.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Open Graph meta tags | Generate OG and Twitter Card tags for social previews | `ON` |
| OG fallback image | Default image used when a post has no featured image | `Empty` |
| Custom verification meta | Free-form textarea for Pinterest, Yandex, Facebook Domain, Naver, etc. | `Empty` |
| Native sitemap | Toggle the WordPress native sitemap output | `ON` |
| `robots.txt` | Custom rules appended to WordPress defaults | `Empty` |

<br>

### 💰 Monetization

Donations and ad zones — bundled into one tab.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| GitHub Sponsors | Sponsor page URL | `Empty` |
| PayPal donation link | Direct PayPal donation URL | `Empty` |
| PayPal QR code | QR code image upload for PayPal | `Empty` |
| PIX URL | Direct PIX payment URL | `Empty` |
| PIX QR code | QR code image upload for PIX | `Empty` |
| PIX key | Direct PIX key (email, CPF/CNPJ, or random) | `Empty` |
| Global ads script | `<head>` global tag (e.g. AdSense Auto Ads) | `Empty` |
| Top banner — desktop | Header banner for large screens (728×90) | `Empty` |
| Top banner — mobile | Header banner for small screens (320×100) | `Empty` |
| Sidebar top banner | Sidebar slot above other widgets (300×250) | `Empty` |
| Sidebar sticky banner | Sidebar slot that follows scroll (300×600) | `Empty` |

<br>

### 📈 Statistics

Per-post view tracking and traffic analytics with Chart.js visualizations.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| View tracking | Master switch for the entire stats system (when off, no tracking and no UI) | `ON` |
| Monthly growth chart | Bar chart of views per month over the last 12 months | `ON` |
| Top posts ranking | Configurable list of most-viewed posts with their counts | `ON` |
| Activity trend | 7-day views per day chart (also embedded on the Dashboard tab) | `ON` |

<br>

### 🚧 Maintenance

Maintenance mode with developer bypass — preserves SEO via proper HTTP 503 status.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Maintenance mode | Block visitors and show a "back soon" page (returns HTTP 503 to crawlers) | `OFF` |
| Developer password | Bypass password stored as bcrypt hash. Access via `yoursite.com/?rd-dev-login` | `Empty` |
| Maintenance message | Custom HTML message shown on the block page | `Empty` |

<br>

### 💾 Backup

Export and restore all theme settings — perfect for staging-to-production sync.

| ***Setting*** | ***Description*** | ***Default*** |
| :--- | :---: | :---: |
| Export settings | Download a JSON file with all theme configuration | — |
| Import settings | Upload a JSON file with structured preview of changes before applying | — |
| Restore from prior export | Browse and restore from any previously generated export | — |

<br>

---
## ❓ FAQ

> [!NOTE]
> <details>
> <summary><strong>Can I use this theme on multiple sites?</strong></summary><br>
> Yes. The theme is licensed under GPL-2.0-or-later, so you can install it on as many sites as you want, modify it freely, and even redistribute modified versions as long as they remain under a GPL-compatible license.
> </details>

> [!NOTE]
> <details>
> <summary><strong>Does the theme work without an internet connection?</strong></summary><br>
> Yes. All rendering, security, and admin features work fully offline. The only network-dependent feature is the auto-updater, which silently fails (no errors thrown) if GitHub is unreachable — the theme continues to function with the currently installed version.
> </details>

> [!NOTE]
> <details>
> <summary><strong>How do I disable auto-updates?</strong></summary><br>
> There is no built-in toggle to disable them — the auto-updater is considered a core feature. If you really need to disable it, comment out the <code>require_once</code> for <code>inc/mod-self-update.php</code> in <code>functions.php</code>. Be aware that you will lose update notifications and have to upgrade manually thereafter.
> </details>

> [!NOTE]
> <details>
> <summary><strong>Can I use a child theme?</strong></summary><br>
> Yes — standard WordPress child theme rules apply. Create a child theme with <code>Template: reloaded</code> in its <code>style.css</code> header, override templates and styles as needed, and your customizations survive parent theme updates.
> </details>

> [!NOTE]
> <details>
> <summary><strong>Why no <code>readme.txt</code> in WordPress.org format?</strong></summary><br>
> The theme is distributed exclusively via GitHub Releases, not the official WordPress.org theme repository. The <code>readme.txt</code> format is a requirement for wp.org submission only — this <code>README.md</code> covers all the equivalent information in a more flexible format.
> </details>

> [!NOTE]
> <details>
> <summary><strong>How do I report a security issue?</strong></summary><br>
> Please email <a href="mailto:finallf@reloaded.com.br">finallf@reloaded.com.br</a> directly instead of opening a public GitHub issue. Include a description of the vulnerability, affected versions, and reproduction steps if available. Responsible disclosure is appreciated — you will receive an acknowledgment within 48 hours.
> </details>

> [!NOTE]
> <details>
> <summary><strong>What languages is the theme available in?</strong></summary><br>
> The theme is English-first, with full Brazilian Portuguese translation (<code>pt_BR.po</code> / <code>pt_BR.mo</code>) shipped in the <code>languages/</code> folder. WordPress automatically loads the appropriate translation based on the site language setting. Additional translations are welcome via Pull Request.
> </details>

> [!NOTE]
> <details>
> <summary><strong>How can I contribute new features?</strong></summary><br>
> See <a href="CONTRIBUTING.md">CONTRIBUTING.md</a> for the full workflow. In short: fork the repo, create a feature branch from <code>beta</code>, commit using <a href="https://www.conventionalcommits.org/">Conventional Commits</a> (the release pipeline relies on commit prefixes for version bumps), and open a Pull Request targeting <code>beta</code>.
> </details>

<br>

---
## 🗺️ Roadmap

ReloadeD evolves continuously. Active items and proposed features are tracked on the [Issues page](https://github.com/Finallf/theme-reloaded/issues). Major directions on the horizon include a native newsletter system, public REST API for headless usage, and additional integrations (Mastodon, Bluesky). See the [Releases page](https://github.com/Finallf/theme-reloaded/releases) for what has shipped recently.

<br>

---
## 🌿 Branches & Releases

The project follows a two-channel release model managed by [semantic-release](https://semantic-release.gitbook.io/). Releases are generated automatically from commit messages following the [Conventional Commits](https://www.conventionalcommits.org/) specification.

<br>

### Channels

| ***Branch*** | ***Channel*** | ***Use case*** |
| :---: | :---: | :--- |
| `master` | Stable | Production-ready releases. Recommended for live sites. The auto-updater follows this channel by default. |
| `beta` | Pre-release | Early testing of new features. Tagged as **Pre-release** on the [Releases page](https://github.com/Finallf/theme-reloaded/releases) and ignored by the auto-updater. |

<br>

### How releases are triggered

Only certain commit types create a new release:

| ***Commit type*** | ***Triggers release?*** | ***Version bump*** |
| :--- | :---: | :---: |
| `feat:` | ✅ Yes | Minor (`1.2.0` → `1.3.0`) |
| `fix:` | ✅ Yes | Patch (`1.2.0` → `1.2.1`) |
| Breaking change (`feat!:` or `BREAKING CHANGE:` footer) | ✅ Yes | Major (`1.2.0` → `2.0.0`) |
| `chore:`, `docs:`, `ci:`, `style:`, `refactor:`, `test:`, `build:` | ❌ No | — |

<br>

### Release artifacts

Each release publishes a `reloaded.zip` file ready for WordPress installation (see [Installation](#-installation)) — folder structure wrapped in `reloaded/`, dev-only files excluded. Download from the [Releases page](https://github.com/Finallf/theme-reloaded/releases).

The `CHANGELOG.md` is regenerated automatically from commit history with each release.

<br>

---
## 💻 Development

### Stack

ReloadeD is built with **PHP 8.0+** on the WordPress framework, **SCSS** with the modular `@use` system, and **vanilla JavaScript** — no JS frameworks. Development tooling uses **Composer** (for PHPCS/WPCS lint) and **npm** (for Sass compilation and Critical CSS extraction), but neither is required at runtime — the theme ships with everything pre-compiled.

<br>

### Local setup

&emsp;1 - Clone the repository into your local WordPress installation:

```bash
cd wp-content/themes/
git clone https://github.com/Finallf/theme-reloaded.git reloaded
```

&emsp;2 - Open the `reloaded` folder in your IDE. The repository ships with `.vscode/settings.json` (workspace settings) and `.vscode/extensions.json` (recommended extensions) — VS Code offers to install the recommended extensions on first open.

&emsp;3 - Activate the theme via **Appearance → Themes** in the WordPress admin.

&emsp;4 - (Optional) Install dev dependencies for lint and tooling:

```bash
composer install   # PHPCS + WordPress Coding Standards
npm install        # Sass + Critical CSS extractor
```

<br>

### SCSS compilation

SCSS source files live in `sass/`. Two compilation paths are supported:

 - 🔹 **VS Code extension** ([Live Sass Compiler](https://marketplace.visualstudio.com/items?itemName=glenn2223.live-sass)) — auto-compiles on save when VS Code opens. Settings versioned in `.vscode/settings.json`.
 - 🔹 **CLI via npm** — for editors other than VS Code or CI usage:

```bash
npm run sass:build   # one-shot compile
npm run sass:watch   # watch and recompile on save
```

Both produce the same `assets/css/style.css` output (committed to the repo, so end-users don't need any build step).

<br>

> [!NOTE]
> Brand color tokens are defined in `sass/base/_variables.scss`. Primary palette: `#031CFF` (`$brand-blue-dark`), `#00A8FF` (`$brand-blue-light`), `#151515` (`$dark-bg`). Typography: Inter and Poppins (self-hosted in `assets/fonts/`).

<br>

### Repository structure

| ***Path*** | ***Purpose*** |
| :--- | :--- |
| `style.css` | Theme header (auto-bumped by semantic-release on each release) |
| `inc/` | PHP modules — every feature in its own `mod-*.php` file (36 modules) |
| `lib/` | Third-party runtime libraries (Parsedown, Prism, Chart.js) — single file each, no nesting |
| `assets/css/` | Compiled CSS (generated by Sass, committed) |
| `assets/js/` | Vanilla JavaScript (no build step) |
| `assets/fonts/` | Self-hosted woff2 fonts (Inter, Poppins, JetBrains Mono) |
| `assets/img/` | Brand assets and theme images |
| `sass/` | SCSS source files (modular `@use` structure) |
| `docs/pt-BR/` | Documentation in Brazilian Portuguese (architecture, modules, panel, CSP, etc.) |
| `languages/` | Internationalization (`.pot`, `.po`, `.mo`) |
| `tools/critical/` | Node-based Critical CSS extractor (dev tool, excluded from release ZIP) |
| `.github/workflows/` | CI/CD pipelines (semantic-release + PHPCS lint on push) |
| `.vscode/` | Versioned workspace settings and recommended extensions |
| `.editorconfig` | Editor consistency rules (4-space indent, LF, UTF-8) |

<br>

### Contributing changes

Follow the [Conventional Commits](https://www.conventionalcommits.org/) specification on commit messages — the release pipeline relies on commit prefixes (`feat:`, `fix:`, etc.) to determine version bumps. See [Branches & Releases](#-branches--releases) for which types trigger a release. Full contribution workflow in [CONTRIBUTING.md](CONTRIBUTING.md).

<br>

---
## ☕ Support the Project / Apoie o Projeto

If this project has helped you in any way, consider buying me a coffee! Your donation helps keep the updates and documentation current.

🇧🇷 Se este projeto te ajudou de alguma forma, considere me pagar um café! Sua doação ajuda a manter as atualizações e a documentação.

| 🌎 GitHub Sponsors | <img src="https://upload.wikimedia.org/wikipedia/commons/5/50/Pix_%28Brazil%29_logo.svg" width="50px" alt="PIX Logo"> | <img src="https://avatars.githubusercontent.com/u/476675?s=48&v=4" width="15px" alt="PayPal Logo"> PayPal |
| :---: | :---: | :---: |
| You can support me through<br>GitHub Sponsors.<br><br><a href="https://github.com/sponsors/Finallf"><img src="https://img.shields.io/badge/Sponsor-GitHub-ea4aaa?style=for-the-badge&logo=github-sponsors"></a> | 🇧🇷 Escaneie o QR Code:<br><a href="https://pag.ae/81FaYZrhJ"><img src="https://raw.githubusercontent.com/Finallf/theme-reloaded/master/docs/img/qrcode-pix.webp" width="200px" alt="Pix QR Code"></a> | Click or scan the QR code:<br><a href="https://www.paypal.com/donate/?hosted_button_id=9MS3GZX5KGLP2"><img src="https://raw.githubusercontent.com/Finallf/theme-reloaded/master/docs/img/qrcode-paypal.webp" width="200px" alt="PayPal QR Code"></a> |

🇧🇷 Ou utilize a Chave Pix (Copia e Cola):

```
25d1d528-df10-4005-bb28-2acf89706243
```

<br>

---
## 💪 How to Contribute

> [!NOTE]
> Full contribution guide: [CONTRIBUTING.md](CONTRIBUTING.md)
>
> &emsp;1 - Fork the repository.
> &emsp;2 - Create a feature branch from `beta`:
> &emsp;&emsp;`git checkout -b feat/my-feature`
> &emsp;3 - Make changes and commit using Conventional Commits:
> &emsp;&emsp;`git commit -m "feat: add custom widget for related posts"`
> &emsp;4 - Push to your fork:
> &emsp;&emsp;`git push origin feat/my-feature`
> &emsp;5 - Open a Pull Request targeting `beta` (or `master` for critical hotfixes only).

> [!TIP]
> Before opening a PR, recompile the SCSS (`npm run sass:build` or via Live Sass) to keep `assets/css/style.css` in sync. Since the compiled CSS is committed to the repository, untested SCSS changes can break the visual layer for everyone.

<br>

---
## 🛠️ Technologies

The following tools and technologies were used in the construction of this project:

<p align="center">
	<a href="https://wordpress.org"><img alt="WordPress" src="https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white"></a>
	<a href="https://www.php.net"><img alt="PHP" src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white"></a>
	<a href="https://sass-lang.com"><img alt="Sass" src="https://img.shields.io/badge/Sass-CC6699?style=for-the-badge&logo=sass&logoColor=white"></a>
	<a href="https://html.spec.whatwg.org"><img alt="HTML5" src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white"></a>
	<a href="https://www.w3.org/Style/CSS"><img alt="CSS3" src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white"></a>
	<a href="https://developer.mozilla.org/docs/Web/JavaScript"><img alt="JavaScript" src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black"></a>
	<a href="https://github.com/features/actions"><img alt="GitHub Actions" src="https://img.shields.io/badge/GitHub_Actions-2088FF?style=for-the-badge&logo=githubactions&logoColor=white"></a>
</p>

<br>

---
## 🧑‍💻 Collaborators:
💜 Thank you to everyone who contributed to the improvement of this project 😊

<p align="center">
<!-- readme: collaborators,contributors -start -->
<table>
	<tbody>
		<tr>
            <td align="center">
                <a href="https://github.com/Finallf">
                    <img src="https://avatars.githubusercontent.com/u/8967685?v=4" width="80;" alt="Finallf"/>
                    <br />
                    <sub><b>Finallf</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/semantic-release-bot">
                    <img src="https://avatars.githubusercontent.com/u/32174276?v=4" width="80;" alt="semantic-release-bot"/>
                    <br />
                    <sub><b>semantic-release-bot</b></sub>
                </a>
            </td>
		</tr>
	<tbody>
</table>
<!-- readme: collaborators,contributors -end -->
</p>

<br>

---
## 🧙‍♂️ Author:
<p align="center">
	<a href="https://reloaded.com.br"><img alt="Finallf" width="100" src="https://avatars.githubusercontent.com/u/8967685"></a>
	<br>
	<br>
	<a href="mailto:finallf@reloaded.com.br"><img alt="Email" src="https://img.shields.io/badge/-finallf@reloaded.com.br-c14438?style=plastic&logo=gmail&logoColor=white"></a>
	&nbsp;
	<a href="https://x.com/ReloadeDtec"><img alt="Twitter" src="https://img.shields.io/badge/@ReloadeDtec-blue?style=plastic&logo=X"></a>
	&nbsp;
	<a href="https://forum.reloaded.com.br"><img alt="Forum" src="https://img.shields.io/badge/Forum-ReloadeD-blue?style=plastic&logo=phpbb"></a>
	&nbsp;
	<a href="https://discord.gg/HxmqAEkY"><img alt="Discord" src="https://img.shields.io/badge/Discord-Finallf-purple?style=plastic&logo=discord"></a>
</p>

<br>

---
## 📝 License:
> [!WARNING]
> This project is licensed under: <a href="https://github.com/Finallf/theme-reloaded?tab=GPL-2.0-1-ov-file">GPL-2.0-or-later license</a>.
---
