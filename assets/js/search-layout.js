/* SEARCH LAYOUT CONTROL — AJAX Redistribution (Phase 5.5)
 *
 * Loaded ONLY on the search results page (mod-search.php enqueues it gated on
 * is_search) — it lived inside the global navigation.js for a long time,
 * shipping ~7 KiB of dead code to every other page.
 *
 * Instead of just hiding sections, fires AJAX to redistribute the
 * current page's results across the active layouts. Compact acts as
 * the overflow bucket + safety net (logic on the backend).
 *
 * When the visitor is on page > 1 and clicks a chip, it navigates via a
 * full reload to page 1 (avoids desynced pagination). When already on
 * page 1, pure AJAX.
 */
document.addEventListener('DOMContentLoaded', function() {
    const togglesContainer = document.getElementById('rd-search-toggles');
    const resultsContainer = document.querySelector('.rd-search-results-containers');

    // Bail early if: off the search page, no chips, or no localized data
    if (!togglesContainer || !resultsContainer || typeof rd_search_data === 'undefined') return;

    const chips = togglesContainer.querySelectorAll('.rd-chip');
    const STORAGE_KEY = 'rd_search_prefs';

    function loadPrefs() {
        try {
            const raw = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
            // Migration: old format 'rd-wrap-{layout}' → plain '{layout}'
            const migrated = {};
            let needsSave = false;
            for (const k in raw) {
                if (k.indexOf('rd-wrap-') === 0) {
                    migrated[k.replace('rd-wrap-', '')] = raw[k];
                    needsSave = true;
                } else {
                    migrated[k] = raw[k];
                }
            }
            if (needsSave) savePrefs(migrated);
            return migrated;
        } catch (e) {
            return {};
        }
    }

    function savePrefs(prefs) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) { /* full or blocked — silence it */ }
    }

    function getLayoutFromChip(chip) {
        // data-target="rd-wrap-{layout}" — extract just the layout name
        return chip.getAttribute('data-target').replace('rd-wrap-', '');
    }

    function getActiveLayouts() {
        const active = [];
        chips.forEach(chip => {
            if (chip.classList.contains('active')) {
                active.push(getLayoutFromChip(chip));
            }
        });
        return active;
    }

    function applyChipState(prefs) {
        chips.forEach(chip => {
            const layout = getLayoutFromChip(chip);
            const isActive = prefs[layout] !== false; // defaults to true
            chip.classList.toggle('active', isActive);
            chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function setLoading(isLoading) {
        resultsContainer.classList.toggle('rd-loading', isLoading);
    }

    function redistribute(paged) {
        const active = getActiveLayouts();
        const body = new URLSearchParams({
            action: 'rd_search_redistribute',
            nonce: rd_search_data.nonce,
            search_query: rd_search_data.query,
            active_layouts: active.join(','),
            paged: paged || 1
        });

        setLoading(true);

        return fetch(rd_search_data.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.data) {
                resultsContainer.innerHTML = data.data.html;
            } else {
                console.error('rd_search_redistribute failed:', data);
            }
        })
        .catch(err => {
            console.error('rd_search_redistribute error:', err);
        })
        .finally(() => {
            setLoading(false);
        });
    }

    // Detect the current page considering BOTH WP formats:
    //   - Pretty permalink: /page/N/ in the path
    //   - Query string:     ?paged=N
    function getCurrentPaged() {
        const url = new URL(window.location);
        const pathMatch = url.pathname.match(/\/page\/(\d+)\/?$/);
        if (pathMatch) return parseInt(pathMatch[1], 10);
        const q = parseInt(url.searchParams.get('paged'), 10);
        return q > 0 ? q : 1;
    }

    // Build the page-1 URL by removing /page/N/ AND ?paged=
    function urlForPage1() {
        const url = new URL(window.location);
        url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
        url.searchParams.delete('paged');
        return url.toString();
    }

    // INITIAL: apply chip state + redistribute if the visitor has prefs
    // different from the default (preserving the current paged)
    const savedPrefs = loadPrefs();
    if (Object.keys(savedPrefs).length > 0) {
        applyChipState(savedPrefs);
        const hasInactive = Array.from(chips).some(c => !c.classList.contains('active'));
        if (hasInactive) {
            redistribute(getCurrentPaged());
        }
    }

    // CLICK: visual toggle → save prefs → redistribute (AJAX or full reload)
    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            this.classList.toggle('active');
            const isActive = this.classList.contains('active');
            this.setAttribute('aria-pressed', isActive ? 'true' : 'false');

            const prefs = loadPrefs();
            prefs[getLayoutFromChip(this)] = isActive;
            savePrefs(prefs);

            if (getCurrentPaged() > 1) {
                // Page > 1 → full reload to page 1 (pagination syncs naturally)
                // Next load: JS reads prefs and applies them via the initial AJAX
                window.location.href = urlForPage1();
            } else {
                // Already on page 1 → pure AJAX
                redistribute(1);
            }
        });
    });

    // KEYBOARD NAV: left/right arrows to move between chips, Home/End for
    // first/last (WAI-ARIA pattern for role="toolbar").
    togglesContainer.addEventListener('keydown', function(e) {
        const target = e.target;
        if (!target.classList || !target.classList.contains('rd-chip')) return;

        const chipsArr = Array.from(chips);
        const currentIndex = chipsArr.indexOf(target);
        if (currentIndex === -1) return;

        let nextIndex = -1;

        switch (e.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                nextIndex = (currentIndex + 1) % chipsArr.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                nextIndex = (currentIndex - 1 + chipsArr.length) % chipsArr.length;
                break;
            case 'Home':
                nextIndex = 0;
                break;
            case 'End':
                nextIndex = chipsArr.length - 1;
                break;
            default:
                return; // other keys (Space, Enter, Tab) — native <button> behavior
        }

        e.preventDefault();
        chipsArr[nextIndex].focus();
    });
});
