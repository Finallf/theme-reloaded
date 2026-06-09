<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Donations - Pix, GitHub Sponsors and Paypal
 *******************************************************************************/

/*******************************************************************************
 * Helper: serves the `rd-qr` (240x240) version of the QR code when possible   *
 *                                                                             *
 * Admin uploads via the panel are usually 600px+, but display is ~160-200px.  *
 * `attachment_url_to_postid` resolves the stored URL back to the attachment   *
 * ID; with the ID in hand, `wp_get_attachment_image` builds the tag with the  *
 * correct src + srcset + width/height for the `rd-qr` size.                   *
 *                                                                             *
 * Fallback: if the URL doesn't belong to the local Media Library (edge case), *
 * serves the original with fixed dims so the layout doesn't break.            *
 *                                                                             *
 * Note: uploads PRIOR to the `rd-qr` size registration don't have the variant *
 * generated — admin must run "Regenerate Thumbnails" (plugin) or re-upload.   *
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

	// Fallback for external URLs / unresolvable uploads
	return sprintf(
		'<img src="%s" class="rd-qr-img" alt="%s" width="240" height="240">',
		esc_url( $stored_url ),
		esc_attr( $alt )
	);
}

/*******************************************************************************
 * Support Module                                                 - (Donations)*
 *                                                                             *
 * Builds the donation block HTML (GitHub Sponsors, PayPal, PIX) from a data   *
 * array and RETURNS it as a string ('' when nothing is configured, so the     *
 * caller can skip the widget wrapper). Config now lives in the                *
 * RD_Support_Widget form (Appearance → Widgets), not in the theme panel.      *
 *******************************************************************************/
function rd_get_support_block_html( array $data ): string {
	$github     = $data['github_sponsors'] ?? '';
	$pix_key    = $data['pix_chave'] ?? '';
	$pix_img    = $data['pix_qrcode'] ?? '';
	$pix_url    = $data['pix_url'] ?? '';
	$paypal_url = $data['paypal_url'] ?? '';
	$paypal_qr  = $data['paypal_qrcode'] ?? '';
	// Heading text — configurable in the widget; falls back to the default
	// when left empty, preserving the historical "Support the Project" label.
	$title = ( isset( $data['title'] ) && '' !== $data['title'] ) ? $data['title'] : __( 'Support the Project', 'reloaded' );

	if ( empty( $github ) && empty( $pix_key ) && empty( $pix_img ) && empty( $paypal_url ) ) {
		return '';
	}

	ob_start();
	echo '<section class="rd-support-block">';
	echo '  <h3 class="rd-support-title">' . esc_html( $title ) . '</h3>';
	echo '  <div class="rd-support-content">';

	// GitHub Sponsors
	if ( $github ) {
		echo '<a href="' . esc_url( $github ) . '" target="_blank" class="rd-qr-link">';
		echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/github-sponsor.svg' ) . '" alt="GitHub Sponsors" width="200" height="48">';
		echo '</a>';
	}

	// PIX (Domestic)
	if ( $pix_key || $pix_img ) {
		echo '<div class="rd-support-subitem">';

		// PIX image with Dynamic Link
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

		// PIX Key with Modern Copy Button
		if ( $pix_key ) {
			echo '<div class="rd-pix-copy-area">';
			echo '  <span class="rd-key-label">' . esc_html__( 'PIX Key:', 'reloaded' ) . '</span>';
			// Uses the generic data-rd-copy handler (defined in navigation.js).
			// By convention, the "Copied!" feedback goes to .rd-copy-text (fallback).
			echo '  <button class="rd-copy-btn" data-rd-copy="' . esc_attr( $pix_key ) . '" aria-label="' . esc_attr__( 'Copy PIX key', 'reloaded' ) . '" title="' . esc_attr__( 'Click to copy', 'reloaded' ) . '">';
			echo '    <span class="rd-copy-text">' . esc_html( $pix_key ) . '</span>';
			echo '    <i class="fas fa-copy"></i>';
			echo '  </button>';
			echo '</div>';
		}

		echo '</div>';
	}

	// PayPal (International)
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

	return (string) ob_get_clean();
}
