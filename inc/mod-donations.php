<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Donations - Pix, GitHub Sponsors e Paypal
 *******************************************************************************/

/*******************************************************************************
 * Helper: serve a versão `rd-qr` (240x240) do QR code quando possível         *
 *                                                                              *
 * Admin uploads via painel costumam ser 600px+, mas o display é ~160-200px.   *
 * `attachment_url_to_postid` resolve a URL armazenada de volta pro ID do      *
 * attachment; com ID em mãos, `wp_get_attachment_image` gera a tag com src    *
 * + srcset + width/height corretos pro tamanho `rd-qr`.                       *
 *                                                                              *
 * Fallback: se a URL não pertence ao Media Library local (caso edge), serve  *
 * o original com dims fixas pra não quebrar layout.                           *
 *                                                                              *
 * Nota: uploads ANTERIORES ao registro do tamanho `rd-qr` não têm a variante *
 * gerada — admin precisa rodar "Regenerate Thumbnails" (plugin) ou re-uploadar.*
 *******************************************************************************/
function rd_render_qr_img( $stored_url, $alt ) {
	if ( empty( $stored_url ) ) {
		return '';
	}

	$attachment_id = attachment_url_to_postid( $stored_url );
	if ( $attachment_id ) {
		return wp_get_attachment_image(
			$attachment_id,
			'rd-qr',
			false,
			array(
				'class' => 'rd-qr-img',
				'alt'   => $alt,
			)
		);
	}

	// Fallback pra URLs externas / uploads não-resolvíveis
	return sprintf(
		'<img src="%s" class="rd-qr-img" alt="%s" width="240" height="240">',
		esc_url( $stored_url ),
		esc_attr( $alt )
	);
}

/*******************************************************************************
 * Módulo de Apoio                                                 - (Doações) *
 *******************************************************************************/
function rd_render_support_block() {
	$github     = rd_get_option( 'github_sponsors' );
	$pix_key    = rd_get_option( 'pix_chave' );
	$pix_img    = rd_get_option( 'pix_qrcode' );
	$pix_url    = rd_get_option( 'pix_url' ); // O novo campo de link
	$paypal_url = rd_get_option( 'paypal_url' );
	$paypal_qr  = rd_get_option( 'paypal_qrcode' );

	if ( empty( $github ) && empty( $pix_key ) && empty( $pix_img ) && empty( $paypal_url ) ) {
		return;
	}

	echo '<section class="rd-support-block">';
	echo '  <h3 class="rd-support-title">' . esc_html__( 'Support the Project', 'reloaded' ) . '</h3>';
	echo '  <div class="rd-support-content">';

	// GitHub Sponsors
	if ( $github ) {
		echo '<a href="' . esc_url( $github ) . '" target="_blank" class="rd-qr-link">';
		echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/github-sponsor.svg' ) . '" alt="GitHub Sponsors" width="200" height="48">';
		echo '</a>';
	}

	// PIX (Nacional)
	if ( $pix_key || $pix_img ) {
		echo '<div class="rd-support-subitem">';

		// Imagem do PIX com Link Dinâmico
		if ( $pix_img ) {
			if ( $pix_url ) {

				echo '<a href="' . esc_url( $pix_url ) . '" target="_blank" class="rd-qr-link">';
				echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/logo-pix.svg' ) . '" alt="PIX Logo" width="60" height="36">';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_render_qr_img returns wp_get_attachment_image() output (already escaped) or pre-escaped <img> fallback
				echo '  ' . rd_render_qr_img( $pix_img, esc_attr__( 'QR Code PIX', 'reloaded' ) );
				echo '  <small>' . esc_html__( '(Click or Scan to donate)', 'reloaded' ) . '</small>';
				echo '</a>';
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_render_qr_img returns wp_get_attachment_image() output (already escaped) or pre-escaped <img> fallback
				echo rd_render_qr_img( $pix_img, esc_attr__( 'QR Code PIX', 'reloaded' ) );
			}
		}

		// Chave PIX com Botão de Copiar Moderno
		if ( $pix_key ) {
			echo '<div class="rd-pix-copy-area">';
			echo '  <span class="rd-key-label">' . esc_html__( 'PIX Key:', 'reloaded' ) . '</span>';
			// Usa o handler genérico data-rd-copy (definido em navigation.js).
			// Por convenção, o feedback de "Copiado!" vai pro .rd-copy-text (fallback).
			echo '  <button class="rd-copy-btn" data-rd-copy="' . esc_attr( $pix_key ) . '" aria-label="' . esc_attr__( 'Copy PIX key', 'reloaded' ) . '" title="' . esc_attr__( 'Click to copy', 'reloaded' ) . '">';
			echo '    <span class="rd-copy-text">' . esc_html( $pix_key ) . '</span>';
			echo '    <i class="fas fa-copy"></i>';
			echo '  </button>';
			echo '</div>';
		}

		echo '</div>';
	}

	// PayPal (Internacional)
	if ( $paypal_url || $paypal_qr ) {
		echo '<div class="rd-support-subitem rd-paypal-container">';

		if ( $paypal_qr ) {
			if ( $paypal_url ) {
				echo '<a href="' . esc_url( $paypal_url ) . '" target="_blank" class="rd-qr-link">';
				echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/logo-paypal.svg' ) . '" alt="PayPal Logo" width="70" height="36">';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_render_qr_img returns wp_get_attachment_image() output (already escaped) or pre-escaped <img> fallback
				echo '  ' . rd_render_qr_img( $paypal_qr, esc_attr__( 'PayPal Donate', 'reloaded' ) );
				echo '  <small>' . esc_html__( '(Click or Scan to donate)', 'reloaded' ) . '</small>';
				echo '</a>';
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_render_qr_img returns wp_get_attachment_image() output (already escaped) or pre-escaped <img> fallback
				echo rd_render_qr_img( $paypal_qr, esc_attr__( 'PayPal QR', 'reloaded' ) );
			}
		} elseif ( $paypal_url ) {
			echo '<a href="' . esc_url( $paypal_url ) . '" target="_blank" class="rd-btn-support rd-paypal">' . esc_html__( 'Donate via PayPal', 'reloaded' ) . '</a>';
		}

		echo '</div>';
	}

	echo '  </div>';
	echo '</section>';
}
