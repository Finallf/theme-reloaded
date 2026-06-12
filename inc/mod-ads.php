<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Ads - Monetization and Banners
 *******************************************************************************/

/*******************************************************************************
 * Virtual ads.txt                                                             *
 *                                                                             *
 * Serves https://host/ads.txt straight from the panel option — same pattern   *
 * as the IndexNow key file (mod-indexnow.php): no physical file, no SFTP,     *
 * survives migrations and enters the theme-settings backup. AdSense/networks  *
 * crawl this file to verify who is authorized to sell the site's inventory.   *
 *                                                                             *
 * Note: a PHYSICAL ads.txt in the web root wins (the web server serves real   *
 * files before WordPress routing kicks in) — delete it to use this field.     *
 ******************************************************************************/

/**
 * Serves /ads.txt with the panel content when filled. Dormant when empty.
 */
function rd_ads_serve_ads_txt() {
	$content = trim( (string) rd_get_option( 'ads_txt_content' ) );
	if ( '' === $content ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( '/ads.txt' !== $path ) {
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text (sanitize_textarea_field on save strips any HTML); ads.txt format is comma-separated records.
	exit;
}
add_action( 'init', 'rd_ads_serve_ads_txt', 2 );

/*******************************************************************************
 * Rendering of Ads and Banners - (Desktop and Mobile)                 - (ADS) *
 *******************************************************************************/

/**
 * Whether the "Delay ads until interaction" panel toggle is ON.
 */
function rd_ads_lazy_enabled(): bool {
	return rd_get_option_bool( 'ads_lazy_load' );
}

/**
 * Per-request queue of external loader script URLs collected by
 * rd_ads_dedupe_loader() when lazy mode is ON. The footer bootstrap
 * (rd_ads_print_lazy_loader) injects them on the first interaction.
 *
 * @param string|null $add URL to enqueue (deduped); null = just read.
 * @return string[] The collected URLs.
 */
function rd_ads_loader_queue( ?string $add = null ): array {
	static $srcs = array();
	if ( null !== $add && ! in_array( $add, $srcs, true ) ) {
		$srcs[] = $add;
	}
	return $srcs;
}

/**
 * Strips duplicate ad-network loader <script src> tags at render time.
 *
 * Every AdSense unit pasted in the panel/widgets ships its own
 * `<script async src=".../adsbygoogle.js?client=...">` — with top banner +
 * mobile anchor + sidebar sticky + widgets, the page carried 5 copies of the
 * same loader (PageSpeed listed each one). Google's recommended pattern is ONE
 * loader per page serving every <ins> unit; the per-unit
 * `(adsbygoogle=[]).push({})` calls just queue until it executes, so keeping
 * only the FIRST occurrence (in render order) is safe.
 *
 * Deduped by exact src URL: units from different client IDs (rare multi-account
 * setups) keep their own loader. Static cache = per request, so every page
 * render starts fresh. Generic by design — any duplicated external loader
 * (GAM's gpt.js, etc.) gets the same treatment.
 *
 * Lazy mode ("Delay ads until interaction" toggle): instead of keeping the
 * first occurrence, EVERY loader tag is stripped and its URL queued; the
 * footer bootstrap injects them on the visitor's first scroll/touch/key/move
 * (or after the fallback timeout). The per-unit <ins> + push() snippets stay
 * inline — they only queue work for whenever the loader lands.
 *
 * @param string $html Ad code (after CSP nonce injection).
 * @return string Same HTML with loader <script> tags removed as appropriate.
 */
function rd_ads_dedupe_loader( string $html ): string {
	static $seen = array();
	$lazy        = rd_ads_lazy_enabled();

	return (string) preg_replace_callback(
		'#<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</script>#i',
		function ( $matches ) use ( &$seen, $lazy ) {
			$src = $matches[1];
			if ( $lazy ) {
				rd_ads_loader_queue( html_entity_decode( $src, ENT_QUOTES ) );
				return ''; // stripped — the footer bootstrap injects it on interaction
			}
			if ( isset( $seen[ $src ] ) ) {
				return ''; // loader already on the page — drop the duplicate
			}
			$seen[ $src ] = true;
			return $matches[0];
		},
		$html
	);
}

/**
 * Footer bootstrap for lazy ads: one inline script (CSP nonce'd, same pattern
 * as the search-suggestions lazy loader) that injects every queued ad-network
 * loader on the visitor's first interaction — scroll, touch, mouse move, key
 * press or click — or after the configured fallback timeout.
 *
 * Why this is safe and effective:
 *   - The official network loaders run unmodified; only WHEN they load changes.
 *   - Ad units (<ins> + push()) are already in the HTML with reserved space,
 *     so the late arrival costs zero CLS.
 *   - Lab runs (Lighthouse/PageSpeed) never interact, so the ~440 KB of ad JS
 *     stays out of the measured trace entirely — and real bouncing visitors
 *     get the same saving.
 *
 * Hooked at wp_footer 99: after the mobile anchor (default 10) and widgets
 * have rendered, so the queue is complete.
 */
function rd_ads_print_lazy_loader() {
	if ( ! rd_ads_lazy_enabled() ) {
		return;
	}
	$srcs = rd_ads_loader_queue();
	if ( empty( $srcs ) ) {
		return;
	}

	$timeout    = max( 0, (int) rd_get_option( 'ads_lazy_timeout', 5 ) );
	$nonce_attr = function_exists( 'rd_csp_nonce_attr' ) ? rd_csp_nonce_attr() : '';
	?>
	<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce attribute pre-escaped by rd_csp_nonce_attr(). ?>>
	(function () {
		var srcs = <?php echo wp_json_encode( array_values( $srcs ) ); ?>;
		var events = ['scroll', 'mousemove', 'touchstart', 'keydown', 'click'];
		var fired = false;
		var parked = [];

		function isVisible(el) {
			return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
		}

		// adsbygoogle.push() fills pending units in DOM order with NO visibility
		// check: a fixed 728x90 desktop unit processed while its container is
		// display:none (mobile breakpoint) gets "auto-sized" by Google to page
		// proportions (data-ad-auto-size rewrites the inline width/height — no
		// public opt-out) and comes back as a stretched blank box when the
		// breakpoint flips. So BEFORE the network loader runs, park every
		// hidden unit outside the DOM (comment placeholder marks the spot) and
		// drop its queued push; unpark + push when its container turns visible
		// (breakpoint change = window resize crossing 768px or device rotation).
		function park() {
			document.querySelectorAll('ins.adsbygoogle').forEach(function (ins) {
				if (isVisible(ins) || ins.getAttribute('data-adsbygoogle-status')) { return; }
				var ph = document.createComment('rd-parked-ad');
				ins.parentNode.insertBefore(ph, ins);
				ins.parentNode.removeChild(ins);
				parked.push({ ph: ph, ins: ins });
				// The unit's inline push({}) already queued an entry — eat one
				// so the loader doesn't look for a unit that isn't in the DOM.
				if (window.adsbygoogle && typeof window.adsbygoogle.splice === 'function' && window.adsbygoogle.length) {
					window.adsbygoogle.splice(0, 1);
				}
			});
		}

		function unparkVisible() {
			parked = parked.filter(function (slot) {
				var parent = slot.ph.parentNode;
				if (!parent) { return false; }
				if (!isVisible(parent)) { return true; } // still hidden — stay parked
				parent.insertBefore(slot.ins, slot.ph);
				parent.removeChild(slot.ph);
				(window.adsbygoogle = window.adsbygoogle || []).push({});
				return false;
			});
		}

		function load() {
			if (fired) { return; }
			fired = true;
			events.forEach(function (ev) { document.removeEventListener(ev, trigger, { passive: true }); });
			park();
			srcs.forEach(function (src) {
				var s = document.createElement('script');
				s.src = src;
				s.async = true;
				s.crossOrigin = 'anonymous';
				document.head.appendChild(s);
			});
			if (parked.length && window.matchMedia) {
				var mq = window.matchMedia('(max-width: 768px)');
				if (typeof mq.addEventListener === 'function') {
					mq.addEventListener('change', unparkVisible);
				} else if (typeof mq.addListener === 'function') {
					mq.addListener(unparkVisible); // Safari < 14
				}
			}
		}
		function trigger(e) {
			// A bare modifier key is a browser shortcut (Ctrl+U view-source,
			// Ctrl+T new tab...), not page engagement — wait for a real key.
			if (e.type === 'keydown' && (e.key === 'Control' || e.key === 'Shift' || e.key === 'Alt' || e.key === 'Meta')) {
				return;
			}
			load();
		}
		events.forEach(function (ev) { document.addEventListener(ev, trigger, { passive: true }); });
		<?php if ( $timeout > 0 ) : ?>
		setTimeout(load, <?php echo (int) ( $timeout * 1000 ); ?>);
		<?php endif; ?>
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'rd_ads_print_lazy_loader', 99 );

function rd_render_ad_topo() {
	// 1. Renders the top banner (Desktop)
	$ad_desktop = rd_get_option( 'ad_topo_desktop' );

	if ( ! empty( $ad_desktop ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injected automatically into <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container rd-ad-desktop">' . rd_ads_dedupe_loader( rd_csp_inject_nonce( $ad_desktop ) ) . '</div>';
	}
}

/**
 * 2. Injects the Mobile banner (Fixed Anchor) into the HTML footer.
 */
function rd_render_ad_mobile_anchor() {
	$ad_mobile = rd_get_option( 'ad_topo_mobile' );

	if ( ! empty( $ad_mobile ) ) {
		echo '<div class="rd-ad-container rd-ad-mobile">';

		// Overlaid close button (uses opacity and movement to disappear)
		echo '<div class="rd-ad-close-wrap">';
		// Close behavior (fade + remove) is in assets/js/navigation.js
		echo '<button class="rd-ad-close" aria-label="' . esc_attr__( 'Close ad', 'reloaded' ) . '">&times;</button>';
		echo '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injected automatically into <script> tags via rd_csp_inject_nonce().
		echo rd_ads_dedupe_loader( rd_csp_inject_nonce( $ad_mobile ) );
		echo '</div>';
	}
}
// The "add_action" makes the function run automatically at the end of the site, without us having to touch the visual files!
add_action( 'wp_footer', 'rd_render_ad_mobile_anchor' );

/**
 * Renders the Sidebar banner (Sticky).
 */
function rd_render_ad_sidebar_sticky() {
	$ad_sidebar_sticky = rd_get_option( 'ad_sidebar_sticky' );
	if ( ! empty( $ad_sidebar_sticky ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injected automatically into <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container rd-ad-sticky">' . rd_ads_dedupe_loader( rd_csp_inject_nonce( $ad_sidebar_sticky ) ) . '</div>';
	}
}
