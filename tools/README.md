# 🛠️ Developer Tools

Scripts used during theme development. **Not shipped with the release ZIP** — the CI workflow excludes this entire folder from `reloaded.zip` (see `.github/workflows/master.yml`).

This folder is meant for theme maintainers and contributors only. End users installing the theme via WordPress admin never see these scripts.

---

## 📐 `critical/extract.js`

Generates Critical CSS — the minimal set of styles needed to render above-the-fold content — for each page template. The generated files are inlined in `<head>` by `inc/mod-critical-css.php` when the "Inline Critical CSS" toggle is enabled in **Performance**, eliminating render-blocking on first paint.

### Prerequisites

- **Node 18+** and **npm**
- **Theme dev dependencies installed** (`npm install` from repo root)
- **A live URL serving the theme** (default: `https://reloaded.com.br`). Override via the `RD_SANDBOX_URL` environment variable if you run the theme on a different host.

### Usage

```bash
# From the repo root:
npm run critical:home      # generates critical-home.css
npm run critical:single    # generates critical-single.css
npm run critical:page      # generates critical-page.css
npm run critical:archive   # generates critical-archive.css
npm run critical:search    # generates critical-search.css

# Generate all five at once:
npm run critical:all
```

Output goes to `assets/css/critical-{template}.css`.

### When to re-run

- After modifying above-the-fold SCSS (`sass/layout/_header.scss`, `_grid.scss`, `_single.scss`, etc.)
- After adding or removing a feature that affects the top of a page
- Before each major release, as part of pre-release polish

> The `critical` npm package uses headless Chrome (Puppeteer) to actually visit the URL and measure which CSS rules apply to the viewport. The process takes 10–60 seconds per template.

---

## 🛡️ `security/test-admin-access.ps1`

PowerShell script that probes a list of admin endpoints (5 theme-specific + 5 WordPress core + 3 public endpoints) and verifies they reject unauthenticated requests. Useful as a smoke test after deployment changes to confirm no admin endpoint accidentally got exposed.

### Prerequisites

- **PowerShell 5.1+** (Windows native) or **PowerShell Core 7+** (cross-platform)
- **Network access** to the URL being tested

### Usage

```powershell
# Default: tests the staging URL hardcoded in the script
.\test-admin-access.ps1

# Custom URL (e.g. production):
.\test-admin-access.ps1 -BaseUrl "https://your-production-site.com"
```

### Expected outcome

The script prints 13 line-by-line results and a summary. A passing run shows **`13/13 PASS`**.

- **Admin endpoints** (`admin-post.php`, authenticated `admin-ajax.php` actions, REST routes requiring `manage_options`) → must return `400` (input validation), `401`, `403`, or redirect to `wp-login.php`
- **Public endpoints** (`wp_ajax_nopriv_*`, REST routes with `permission_callback => '__return_true'`) → must respond normally (`200` or `400` depending on payload), **never** with `401`/`403` due to missing auth

If any line shows `FAIL`, investigate immediately — an admin endpoint may have been exposed by recent changes.

---

## 📝 Other notes

- **`wp-cli.phar`** is gitignored. If you want WP-CLI available locally without polluting `$PATH`, download it here:
  ```bash
  curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod +x wp-cli.phar    # Linux/Mac only
  ```
  Then invoke as `php tools/wp-cli.phar <command>`.

- **Adding new scripts:** keep them OS-agnostic when possible (Node, Python, or POSIX shell over PowerShell-only). If a script is platform-specific, name it accordingly (`*.ps1` for PowerShell, `*.sh` for bash) and document the platform in this README.
