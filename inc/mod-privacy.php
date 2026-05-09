<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Módulo Privacidade - Consentimento e LGPD
 *******************************************************************************/

/*******************************************************************************
 * Banner de Privacidade e Cookies (LGPD)                      - (Privacidade) *
 *******************************************************************************/
function rd_render_lgpd_banner() {
    $opt = get_option('rd_settings');

    if ( isset($_COOKIE['rd_lgpd_accepted']) ) {
        return;
    }

    if ( ! rd_get_option_bool('enable_lgpd') ) {
        return;
    }

    $banner_text = !empty($opt['lgpd_text']) ? $opt['lgpd_text'] : __( 'We use cookies and similar technologies to improve your experience. By continuing to browse, you agree to our <a href="/privacy-policy">Privacy Policy</a>.', 'reloaded' );
    ?>

    <div id="rd-lgpd-banner" class="rd-cookie-banner">
        <div class="cookie-text">
            <?php echo wp_kses_post( $banner_text ); ?>
        </div>
        <button id="rd-cookie-accept" class="cookie-btn"><?php esc_html_e( 'Understood and Accepted', 'reloaded' ); ?></button>
    </div>
    <?php
}
add_action('wp_footer', 'rd_render_lgpd_banner');
