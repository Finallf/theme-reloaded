document.addEventListener('DOMContentLoaded', function () {

	// Localized i18n strings (reloaded_i18n), with en-US fallbacks if absent
	var t = (typeof reloaded_i18n !== 'undefined') ? reloaded_i18n : {};

	/* Back to top */
	const backToTopBtn = document.getElementById("back-to-top");

	if (backToTopBtn) {
		// `passive: true` tells the browser the handler won't prevent scrolling,
		// allowing optimization. requestAnimationFrame batches the write into the next
		// frame, avoiding layout thrashing when scroll fires dozens of times per second
		// and each class add/remove invalidates the style cache.
		let scrollTicking = false;
		window.addEventListener("scroll", function() {
			if (scrollTicking) return;
			scrollTicking = true;
			window.requestAnimationFrame(function() {
				const scrollPosition = window.scrollY || document.documentElement.scrollTop;
				if (scrollPosition > 300) {
					backToTopBtn.classList.add("show");
				} else {
					backToTopBtn.classList.remove("show");
				}
				scrollTicking = false;
			});
		}, { passive: true });

		backToTopBtn.addEventListener("click", function() {
			window.scrollTo({
				top: 0,
				behavior: "smooth"
			});
		});
	}

	/* MENU (HAMBURGER) + SEARCH INTEGRATED IN THE PANEL */
	// .menu-toggle (hamburger, left of the bar) only OPENS the panel — the
	// panel slides over it. Closing is done by .menu-close, the floating X
	// beside the open panel. No icon morphing: each button keeps its own glyph.
	const menuPanel       = document.getElementById('primary-menu-panel');
	const menuToggleBtn   = document.querySelector('.menu-toggle');
	const menuCloseBtn    = document.querySelector('.menu-close');
	const menuSearchInput = document.querySelector('.menu-search-field');

	function openMenuPanel(focusSearch) {
		if (!menuPanel) return;
		menuPanel.classList.add('toggled');
		if (menuToggleBtn) {
			menuToggleBtn.setAttribute('aria-expanded', 'true');
		}
		if (focusSearch && menuSearchInput) {
			// Small delay to wait for the panel's transition to finish
			setTimeout(function () { menuSearchInput.focus(); }, 250);
		}
	}

	function closeMenuPanel() {
		if (!menuPanel) return;
		menuPanel.classList.remove('toggled');
		if (menuToggleBtn) {
			menuToggleBtn.setAttribute('aria-expanded', 'false');
		}
	}

	// Expose for other handlers (e.g. the 404 trigger)
	window.rdOpenMenuPanel  = openMenuPanel;
	window.rdCloseMenuPanel = closeMenuPanel;

	if (menuPanel && menuToggleBtn) {
		menuToggleBtn.onclick = function () {
			openMenuPanel(false);
		};
	}

	if (menuCloseBtn) {
		menuCloseBtn.addEventListener('click', function () {
			closeMenuPanel();
		});
	}

	// Resize → close the panel when crossing into desktop (>1024px),
	// preventing it from showing open if the user goes back to tablet/mobile.
	// Also adds .rd-resizing on <body> during the resize to neutralize
	// transitions globally — avoids the .menu-panel "flash" when it switches
	// from `display: contents` (desktop) to `display: block` (mobile/tablet)
	// and the transform transition fires visually.
	// Simple debounce so it doesn't run hundreds of times.
	let resizeTimer;
	window.addEventListener('resize', function () {
		document.body.classList.add('rd-resizing');
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			document.body.classList.remove('rd-resizing');
			if (window.innerWidth > 1024 && menuPanel && menuPanel.classList.contains('toggled')) {
				closeMenuPanel();
			}
		}, 150);
	});

	/* FACADES (YOUTUBE, DISCORD, ETC.) - OPTIMIZED */
	const facades = document.querySelectorAll('.rd-facade');
	const isDiscordOpen = sessionStorage.getItem('rd_discord_open');

	facades.forEach(function (facade) {
		const id = facade.getAttribute('data-id');
		const type = facade.getAttribute('data-type');

		if (type === 'discord' && isDiscordOpen === 'true') {
			const iframeUrl = `https://ptb.discord.com/widget?id=${id}&theme=dark`;
			// defensive loading="lazy": session-restore can happen with the sidebar off-viewport
			facade.innerHTML = `<iframe src="${iframeUrl}" width="100%" height="500" allowtransparency="true" frameborder="0" loading="lazy" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>`;
			facade.style.cursor = 'default';
			return;
		}

		facade.addEventListener('click', function () {
			let iframeUrl = '';
			let iframeHtml = '';

			if (type === 'youtube') {
				// Timestamp preserved from the original oembed (PHP parsed `?t=Xh Ym Zs` → seconds into data-t)
				const t = parseInt(facade.getAttribute('data-t'), 10);
				const startParam = (t > 0) ? `&start=${t}` : '';
				iframeUrl = `https://www.youtube.com/embed/${id}?autoplay=1&rel=0${startParam}`;
				// loading="lazy" more for consistency — on click the iframe is already in-viewport
				iframeHtml = `<iframe src="${iframeUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" style="width:100%; height:100%; position:absolute; top:0; left:0;"></iframe>`;
			}
			else if (type === 'discord') {
				iframeUrl = `https://ptb.discord.com/widget?id=${id}&theme=dark`;
				iframeHtml = `<iframe src="${iframeUrl}" width="100%" height="500" allowtransparency="true" frameborder="0" loading="lazy" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>`;
				sessionStorage.setItem('rd_discord_open', 'true');
			}

			if (iframeUrl !== '') {
				this.innerHTML = iframeHtml;
				this.style.cursor = 'default';
			}
		});
	});

    /* ============================================================
       COPY TO CLIPBOARD — generic function + auto-handler

       Programmatic use (from any JS):
           window.rdCopyToClipboard('text', { feedbackEl, feedbackText, revertAfter, onSuccess, onError });

       Declarative use (zero JS):
           <button data-rd-copy="value to copy"
                   data-rd-copy-feedback=".my-span"   (optional — selector or 'self')
                   data-rd-copy-text="Copied!"         (optional — overrides reloaded_i18n.copied)
                   data-rd-copy-revert="2000">         (optional — ms until reverting to the original text)
               <span class="rd-copy-text">visible value</span>
           </button>
       ============================================================ */

    // 1. Utility function — exposed globally for use by any feature
    window.rdCopyToClipboard = function (text, options) {
        options = options || {};
        var feedbackText = (options.feedbackText !== undefined)
            ? options.feedbackText
            : (typeof reloaded_i18n !== 'undefined' ? reloaded_i18n.copied : 'Copied!');
        var feedbackClass = options.feedbackClass || 'copied';
        var revertAfter   = options.revertAfter   || 2000;

        // Resolve feedbackEl — can be a DOM element or a string selector
        var feedbackEl = null;
        if (options.feedbackEl) {
            feedbackEl = (typeof options.feedbackEl === 'string')
                ? document.querySelector(options.feedbackEl)
                : options.feedbackEl;
        }

        return navigator.clipboard.writeText(text).then(function () {
            if (feedbackEl && feedbackText !== null) {
                var originalText = feedbackEl.innerText;
                feedbackEl.innerText = feedbackText;
                feedbackEl.classList.add(feedbackClass);
                setTimeout(function () {
                    feedbackEl.innerText = originalText;
                    feedbackEl.classList.remove(feedbackClass);
                }, revertAfter);
            }
            if (typeof options.onSuccess === 'function') options.onSuccess();
            return true;
        }).catch(function (err) {
            var errMsg = (typeof reloaded_i18n !== 'undefined') ? reloaded_i18n.copy_error : 'Copy failed:';
            console.error(errMsg, err);
            if (typeof options.onError === 'function') options.onError(err);
            return false;
        });
    };

    // 2. Auto-handler — any element with [data-rd-copy] is handled automatically.
    //    The feedback goes to the element pointed at by data-rd-copy-feedback (selector),
    //    or 'self' (the button itself), or the first descendant .rd-copy-text as a fallback.
    document.querySelectorAll('[data-rd-copy]').forEach(function (el) {
        el.addEventListener('click', function () {
            var text = this.getAttribute('data-rd-copy');
            if (!text) return;

            var feedbackSelector = this.getAttribute('data-rd-copy-feedback');
            var feedbackEl = null;
            if (feedbackSelector === 'self') {
                feedbackEl = this;
            } else if (feedbackSelector) {
                feedbackEl = this.querySelector(feedbackSelector) || document.querySelector(feedbackSelector);
            } else {
                // Fallback: .rd-copy-text inside the button
                feedbackEl = this.querySelector('.rd-copy-text');
            }

            var customText   = this.getAttribute('data-rd-copy-text');
            var revertAttr   = parseInt(this.getAttribute('data-rd-copy-revert'), 10);

            // The button container gets the `.copied` class (for styling like changing bg)
            var self = this;
            window.rdCopyToClipboard(text, {
                feedbackEl:   feedbackEl,
                feedbackText: customText || undefined,
                revertAfter:  isNaN(revertAttr) ? 2000 : revertAttr,
                onSuccess: function () { self.classList.add('copied'); },
            });

            // Remove the button's class after the same interval
            setTimeout(function () { self.classList.remove('copied'); }, isNaN(revertAttr) ? 2000 : revertAttr);
        });
    });

	/* PRISM - Add line-numbers to every <pre> */
	document.querySelectorAll('pre').forEach(block => block.classList.add('line-numbers'));

	// ============================================================
	// AJAX comment submit — avoids a full page reload
	// ============================================================
	// Behavior:
	//   1. Captures the #commentform submit, prevents the native send
	//   2. Sends FormData via fetch to /wp-comments-post.php
	//   3. WP processes (Akismet, validations, save) and returns a 302
	//      redirect (success) or 200 with error HTML
	//   4. Success → shows feedback + soft reload to display the
	//      just-created comment (with the correct position/anchor)
	//   5. Error → shows the extracted message + form stays editable
	// ============================================================
	const commentForm = document.getElementById('commentform');
	if (commentForm) {
		const submitBtn = commentForm.querySelector('.submit, input[type="submit"], button[type="submit"]');
		const formActions = commentForm.querySelector('.form-submit') || commentForm;

		// Helper: creates/updates the feedback block
		function showFeedback(type, message) {
			let box = commentForm.querySelector('.rd-comment-feedback');
			if (!box) {
				box = document.createElement('div');
				box.className = 'rd-comment-feedback';
				formActions.appendChild(box);
			}
			box.className = 'rd-comment-feedback is-' + type;
			box.textContent = message;
		}

		commentForm.addEventListener('submit', async function (e) {
			// Only act if the form is still the native WP one — if another plugin
			// is already controlling it (Discourse, Disqus, etc.), don't interfere.
			if (!commentForm.action || commentForm.action.indexOf('wp-comments-post.php') === -1) {
				return;
			}

			e.preventDefault();

			const originalLabel = submitBtn ? (submitBtn.value || submitBtn.textContent) : '';
			if (submitBtn) {
				submitBtn.disabled = true;
				var sendingLabel = t.comment_sending || 'Sending…';
				if ('value' in submitBtn) submitBtn.value = sendingLabel;
				else submitBtn.textContent = sendingLabel;
			}

			try {
				// `redirect: 'manual'` — we do NOT follow the 302 automatically.
				// When WP returns a 302 (= comment created successfully), the fetch
				// returns response.type === 'opaqueredirect'. If we follow
				// (redirect: 'follow'), the #comment-XXX fragment from the Location
				// header is lost (HTTP doesn't send fragments), and it becomes impossible
				// to tell success apart from any 200 that comes back.
				const response = await fetch(commentForm.action, {
					method: 'POST',
					body: new FormData(commentForm),
					credentials: 'same-origin',
					redirect: 'manual',
				});

				// Redirect = comment created. Reload the current page so the user
				// sees the comment in the list (the page already has it in the DB).
				if (response.type === 'opaqueredirect') {
					showFeedback('success', t.comment_sent || 'Comment sent! Loading…');
					setTimeout(function () { window.location.reload(); }, 600);
					return;
				}

				// Not a redirect → WP returned an error (400/403/500) or wp_die.
				// Try to extract the message from the #error-page wrapper or .wp-die-message.
				const html = await response.text();

				let errMsg = '';
				const dieMatch = html.match(/<div[^>]*id=["']error-page["'][^>]*>([\s\S]*?)<\/div>\s*<\/body>/i)
					|| html.match(/<div[^>]*class=["'][^"']*wp-die-message[^"']*["'][^>]*>([\s\S]*?)<\/div>/i);

				if (dieMatch) {
					const pMatch = dieMatch[1].match(/<p[^>]*>([\s\S]*?)<\/p>/i);
					if (pMatch) errMsg = pMatch[1].replace(/<[^>]+>/g, '').trim();
				}

				if (!errMsg) {
					errMsg = t.comment_error || 'Unexpected error submitting the comment. Reload the page and try again.';
				}

				// Reaching here means a real error (200 + wp_die HTML). A successful
				// comment — including one held for moderation — comes back as a 302
				// redirect, handled by the opaqueredirect branch above, so it never
				// lands here. No locale-specific moderation string matching needed.
				showFeedback('error', errMsg);
			} catch (err) {
				showFeedback('error', (t.network_error || 'Network error: ') + err.message);
			} finally {
				if (submitBtn) {
					submitBtn.disabled = false;
					if ('value' in submitBtn) submitBtn.value = originalLabel;
					else submitBtn.textContent = originalLabel;
				}
			}
		});
	}

});


// ============================================================
// LGPD — Granular consent (expandable banner + JSON cookie)
// ============================================================
// Flow:
//   1. Banner renders in "compact" mode (text + 3 buttons)
//   2. User clicks "Customize" → banner expands, toggles appear
//   3. User clicks "Save/Reject/Accept" → writes the JSON cookie +
//      deletes the legacy cookie + triggers a soft reload so gated
//      scripts read the new consent
//   4. "Cookie Preferences" link in the footer → deletes the cookie and reloads
// ============================================================
document.addEventListener("DOMContentLoaded", function () {

    const banner = document.getElementById("rd-lgpd-banner");

    // ------ Utility function: writes the consent JSON cookie ------
    function rdLgpdSaveConsent(analytics, marketing) {
        const data = {
            necessary: true,
            analytics: !!analytics,
            marketing: !!marketing,
            date: new Date().toISOString().slice(0, 10),
            version: 1
        };
        const expires = new Date();
        expires.setTime(expires.getTime() + 365 * 24 * 60 * 60 * 1000);
        const secure = location.protocol === "https:" ? "; Secure" : "";
        document.cookie =
            "rd_lgpd_consent=" + encodeURIComponent(JSON.stringify(data)) +
            "; expires=" + expires.toUTCString() +
            "; path=/; SameSite=Lax" + secure;

        // Clear the legacy cookie (old "single button" banner) if it still exists
        document.cookie = "rd_lgpd_accepted=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
    }

    // ------ Utility function: animates the banner out and reloads ------
    function rdLgpdCloseAndReload() {
        if (!banner) {
            location.reload();
            return;
        }
        banner.classList.add("rd-lgpd-closing");
        // 350ms = CSS transition + slack; then reload so gated scripts read the new consent
        setTimeout(function () { location.reload(); }, 350);
    }

    // ------ Banner handlers (when rendered) ------
    if (banner) {
        const btnReject    = document.getElementById("rd-lgpd-reject");
        const btnCustomize = document.getElementById("rd-lgpd-customize");
        const btnAccept    = document.getElementById("rd-lgpd-accept");
        const checkAnalytics = document.getElementById("rd-lgpd-analytics");
        const checkMarketing = document.getElementById("rd-lgpd-marketing");

        // "Reject all" — analytics + marketing = false (necessary stays true always)
        if (btnReject) {
            btnReject.addEventListener("click", function () {
                rdLgpdSaveConsent(false, false);
                rdLgpdCloseAndReload();
            });
        }

        // "Accept all" — analytics + marketing = true
        if (btnAccept) {
            btnAccept.addEventListener("click", function () {
                rdLgpdSaveConsent(true, true);
                rdLgpdCloseAndReload();
            });
        }

        // "Customize" / "Save preferences" — dual behavior:
        //   First click: expands the banner to show the toggles
        //   Second click (after expand): saves consent based on the toggles
        if (btnCustomize) {
            btnCustomize.addEventListener("click", function () {
                const isExpanded = banner.getAttribute("data-state") === "expanded";

                if (!isExpanded) {
                    // Expanded mode — show toggles and swap the button label.
                    // `inert` removed to enable tab order + screen readers on the
                    // checkboxes (fixes the Lighthouse "aria-hidden with focusable
                    // descendants" failure).
                    banner.setAttribute("data-state", "expanded");
                    const optionsBlock = banner.querySelector(".rd-lgpd-options");
                    if (optionsBlock) optionsBlock.removeAttribute("inert");
                    btnCustomize.textContent = btnCustomize.getAttribute("data-label-expanded") || "Save";
                } else {
                    // Already expanded — save based on the toggles
                    const a = checkAnalytics ? checkAnalytics.checked : false;
                    const m = checkMarketing ? checkMarketing.checked : false;
                    rdLgpdSaveConsent(a, m);
                    rdLgpdCloseAndReload();
                }
            });
        }
    }

    // ------ "Cookie Preferences" link in the footer ------
    // Doesn't delete the cookie, doesn't reload. Only:
    //   1. Reveals the banner (already in the DOM, hidden by the .rd-lgpd-hidden class)
    //   2. Expands straight to toggle mode (user came from "Customize", already wants to see)
    //   3. Toggles come pre-checked by PHP with the cookie's current values
    // The reload only happens when the user saves the NEW choice.
    const reopenLink = document.getElementById("rd-lgpd-reopen");
    if (reopenLink && banner) {
        reopenLink.addEventListener("click", function (e) {
            e.preventDefault();

            banner.classList.remove("rd-lgpd-hidden");
            banner.setAttribute("data-state", "expanded");

            const optionsBlock = banner.querySelector(".rd-lgpd-options");
            if (optionsBlock) optionsBlock.removeAttribute("inert");

            const btnCustomizeReopen = document.getElementById("rd-lgpd-customize");
            if (btnCustomizeReopen) {
                btnCustomizeReopen.textContent =
                    btnCustomizeReopen.getAttribute("data-label-expanded") || "Save";
            }
        });
    }
});

/* SEARCH LAYOUT CONTROL — AJAX Redistribution (Phase 5.5)
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

/* AD CLOSE BUTTON (Mobile anchor ad) */
document.addEventListener('DOMContentLoaded', function () {
    const adCloseButtons = document.querySelectorAll('.rd-ad-close');

    adCloseButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const ad = this.closest('.rd-ad-mobile');
            if (!ad) return;
            ad.style.opacity = '0';
            setTimeout(function () { ad.style.display = 'none'; }, 300);
        });
    });
});

/* 404 PAGE — "Search content" focuses the search appropriate to the current breakpoint */
document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.getElementById('rd-404-search-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', function () {
        const desktopSearch = document.querySelector('.header-search-container .search-field');

        // ≥769px: header's expandable search visible → focus directly
        if (desktopSearch && desktopSearch.offsetParent !== null) {
            desktopSearch.focus();
            desktopSearch.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // ≤768px: open the hamburger panel and focus the inner search
        if (typeof window.rdOpenMenuPanel === 'function') {
            window.rdOpenMenuPanel(true);
        }
    });
});

/* DARK/LIGHT MODE TOGGLE */
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('rd-theme-toggle');
    if (!themeToggle) return;

    themeToggle.addEventListener('click', function() {
        // Reads and writes on <html> (documentElement) — consistent with the
        // anti-FOUC script that runs in the <head>.
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('rd-theme', newTheme);
    });
});
