<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Módulo Privacidade - Consentimento e LGPD
 *******************************************************************************/

/*******************************************************************************
 * Banner de Privacidade e Cookies (LGPD)                      - (Privacidade) *
 *******************************************************************************/
function rd_render_lgpd_banner() {
    if ( isset($_COOKIE['rd_lgpd_accepted']) ) {
        return;
    }

    if ( ! rd_get_option_bool('enable_lgpd') ) {
        return;
    }

    // Build the privacy policy link using WordPress's native setting
    // (Settings → Privacy → Privacy Policy Page). Falls back to a plain
    // label (no anchor) when no privacy page is configured.
    $privacy_url  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

    $link_label   = __( 'Privacy Policy', 'reloaded' );
    $privacy_link = $privacy_url
        ? '<a href="' . esc_url( $privacy_url ) . '">' . esc_html( $link_label ) . '</a>'
        : esc_html( $link_label );

    // Use the admin's custom text (if set in the panel), otherwise the
    // translatable default. Both expect a %s placeholder where the privacy
    // policy link will be injected.
    $custom_text = rd_get_option( 'lgpd_text' );
    $template    = ! empty( $custom_text )
        ? $custom_text
        : __( 'We use cookies and similar technologies to improve your experience. By continuing to browse, you agree to our %s.', 'reloaded' );

    // Inject the link into the template; wp_kses_post sanitizes the final output.
    $banner_text = sprintf( $template, $privacy_link );
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
