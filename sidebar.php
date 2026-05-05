<aside id="secondary" class="widget-area">
    <?php
    // Bloco do Discord (vem do mod-integrations.php)
    if ( function_exists('rd_render_discord_widget') ) {
        rd_render_discord_widget();
    }

    // Banner de anúncio do topo da sidebar (vem do mod-ads.php)
    if ( function_exists('rd_render_ad_sidebar_top') ) {
        rd_render_ad_sidebar_top();
    }

    // Bloco "Apoie o Projeto" (vem do mod-donations.php)
    if ( function_exists('rd_render_support_block') ) {
        rd_render_support_block();
    }

    // Widgets do WordPress (Aparência → Widgets) — só renderiza se houver
    if ( is_active_sidebar( 'sidebar-1' ) ) {
        dynamic_sidebar( 'sidebar-1' );
    }

    // Banner sticky no final (vem do mod-ads.php)
    if ( function_exists('rd_render_ad_sidebar_sticky') ) {
        rd_render_ad_sidebar_sticky();
    }
    ?>
</aside>