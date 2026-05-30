<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Ads - Monetização e Banners
 *******************************************************************************/

/*******************************************************************************
 * Renderização de Anúncios e Banners - (Desktop e Mobile)             - (ADS) *
 *******************************************************************************/
function rd_render_ad_topo() {
	// 1. Renderiza o banner do topo (Desktop)
	$ad_desktop = rd_get_option( 'ad_topo_desktop' );

	if ( ! empty( $ad_desktop ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injetado automaticamente em <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container rd-ad-desktop">' . rd_csp_inject_nonce( $ad_desktop ) . '</div>';
	}
}

/**
 * 2. Injeta o banner Mobile (Âncora Fixa) no rodapé do HTML.
 */
function rd_render_ad_mobile_anchor() {
	$ad_mobile = rd_get_option( 'ad_topo_mobile' );

	if ( ! empty( $ad_mobile ) ) {
		echo '<div class="rd-ad-container rd-ad-mobile">';

		// Botão de fechar sobreposto (Usa opacidade e movimento para sumir)
		echo '<div class="rd-ad-close-wrap">';
		// Comportamento de fechar (fade + remove) está em assets/js/navigation.js
		echo '<button class="rd-ad-close" aria-label="' . esc_attr__( 'Close ad', 'reloaded' ) . '">&times;</button>';
		echo '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injetado automaticamente em <script> tags via rd_csp_inject_nonce().
		echo rd_csp_inject_nonce( $ad_mobile );
		echo '</div>';
	}
}
// O "add_action" faz a função rodar automaticamente no final do site, sem precisarmos mexer nos arquivos visuais!
add_action( 'wp_footer', 'rd_render_ad_mobile_anchor' );

function rd_render_ad_sidebar_top() {
	// Renderiza o banner da Sidebar (Topo)
	$ad_sidebar_top = rd_get_option( 'ad_sidebar_top' );
	if ( ! empty( $ad_sidebar_top ) ) {
		// text-align center já vem do .rd-ad-container; margin-bottom específico
		// do sidebar-top está em sass/base/_globals.scss
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injetado automaticamente em <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container rd-ad-sidebar-top">' . rd_csp_inject_nonce( $ad_sidebar_top ) . '</div>';
	}
}

/**
 * Renderiza o banner da Sidebar (Sticky).
 */
function rd_render_ad_sidebar_sticky() {
	$ad_sidebar_sticky = rd_get_option( 'ad_sidebar_sticky' );
	if ( ! empty( $ad_sidebar_sticky ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc) require raw HTML/JS. Admin with manage_options trusted under WP model. CSP nonce injetado automaticamente em <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container rd-ad-sticky">' . rd_csp_inject_nonce( $ad_sidebar_sticky ) . '</div>';
	}
}
