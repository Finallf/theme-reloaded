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
 * @param string $html Ad code (after CSP nonce injection).
 * @return string Same HTML with already-seen loader <script> tags removed.
 */
function rd_ads_dedupe_loader( string $html ): string {
	static $seen = array();

	return (string) preg_replace_callback(
		'#<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</script>#i',
		function ( $matches ) use ( &$seen ) {
			$src = $matches[1];
			if ( isset( $seen[ $src ] ) ) {
				return ''; // loader already on the page — drop the duplicate
			}
			$seen[ $src ] = true;
			return $matches[0];
		},
		$html
	);
}

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
