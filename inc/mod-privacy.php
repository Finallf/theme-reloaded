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

    if ( !isset($opt['enable_lgpd']) || $opt['enable_lgpd'] != 1 ) {
        return;
    }

    $banner_text = !empty($opt['lgpd_text']) ? $opt['lgpd_text'] : 'Nós usamos cookies e tecnologias semelhantes para melhorar a sua experiência. Ao continuar navegando, você concorda com a nossa <a href="/politica-de-privacidade">Política de Privacidade</a>.';
    ?>
    
    <div id="rd-lgpd-banner" class="rd-cookie-banner">
        <div class="cookie-text">
            <?php echo wp_kses_post( $banner_text ); ?>
        </div>
        <button id="rd-cookie-accept" class="cookie-btn">Entendi e Aceito</button>
    </div>
    <?php
}
add_action('wp_footer', 'rd_render_lgpd_banner');