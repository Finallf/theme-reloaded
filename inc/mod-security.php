<?php
defined('ABSPATH') || exit;

/*******************************************************************************
 * Module: Security — Security headers (clickjacking, MIME sniffing, etc.)     *
 *******************************************************************************/

/**
 * Envia os headers de segurança no frontend.
 *
 * Aplica apenas no frontend pra não interferir com iframes internos,
 * inline scripts pesados e AJAX que o painel admin do WP usa intensamente.
 *
 * O toggle no painel segue o padrão de migração seguro: instalações
 * existentes ficam OFF até o admin habilitar conscientemente; novas
 * instalações ganham ON por default via rd_set_default_options().
 */
function rd_send_security_headers() {
    if ( is_admin() ) return;
    if ( rd_get_option( 'enable_security_headers' ) != 1 ) return;

    // Anti-clickjacking — só permite iframes da própria origem
    header( 'X-Frame-Options: SAMEORIGIN' );

    // Browser deve respeitar o Content-Type declarado, sem "adivinhar" pelo conteúdo
    header( 'X-Content-Type-Options: nosniff' );

    // Limita o referer cross-origin: só envia origin (sem path), nada em HTTPS→HTTP
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );

    // Bloqueia features sensíveis que o tema não usa (defesa em profundidade)
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=()' );
}
add_action( 'send_headers', 'rd_send_security_headers' );
