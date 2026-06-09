<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Ads - Monetization and Banners
 *******************************************************************************/

/*******************************************************************************
 * Rendering of Ads and Banners - (Desktop and Mobile)                 - (ADS) *
 *******************************************************************************/
function rd_render_ad_topo() {
	// 1. Renders the top banner (Desktop)
	$ad_desktop = rd_get_option( 'ad_topo_desktop' );

	if ( ! empty( $ad_desktop ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injected automatically into <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container rd-ad-desktop">' . rd_csp_inject_nonce( $ad_desktop ) . '</div>';
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
		echo rd_csp_inject_nonce( $ad_mobile );
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
		echo '<div class="rd-ad-container rd-ad-sticky">' . rd_csp_inject_nonce( $ad_sidebar_sticky ) . '</div>';
	}
}
