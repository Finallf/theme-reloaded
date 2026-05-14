<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Module: Integrations - Analytics, Discord, etc.
 *******************************************************************************/

/*******************************************************************************
 * Scripts no Head - (Analytics e Ads)                         - (Integrações) *
 *******************************************************************************/
add_action('wp_head', function() {
    $ga_id = rd_get_option('ga_id');

    if ( !empty($ga_id) && preg_match('/^(G-|UA-)[A-Z0-9-]+$/i', $ga_id) ) {
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '<?php echo esc_attr($ga_id); ?>');
        </script>
        <?php
    }

    $ad_global = rd_get_option('ad_global');
    if ( !empty($ad_global) ) {
        echo $ad_global . "\n";
    }
});

/*******************************************************************************
 * Renderiza o Widget do Discord (Sidebar)                     - (Integrações) *
 *******************************************************************************/
function rd_render_discord_widget() {
    // 1. Verificação Mestre: O widget está ativado no painel?
    $show_discord = rd_get_option_bool('discord_widget');

    // Se estiver desligado, a função morre aqui e não renderiza nada
    if ( ! $show_discord ) {
        return;
    }

    // 2. Define qual ID usar (Painel ou Padrão ReloadeD)
    $id_discord = rd_get_option('discord_id') ? rd_get_option('discord_id') : '408089552759029788';
    
    // 3. Verifica o modo de performance (Fachada ou Iframe)
    $use_facade = rd_get_option_bool('facades_enabled');

    if ( $use_facade ) : ?>
        <div class="rd-facade discord-style" data-type="discord" data-id="<?php echo esc_attr($id_discord); ?>">
            <div class="discord-placeholder">
                <div class="logo-ext">
                    <?php 
                    $svg_path = get_template_directory() . '/assets/img/discord.svg';
                    if ( file_exists($svg_path) ) { echo file_get_contents($svg_path); } 
                    ?>
                </div>
                <div class="logo-int">
                    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/img/logo-reloaded-painel.webp" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </div>
            </div>
        </div>
    <?php else : ?>
        <iframe src="https://ptb.discord.com/widget?id=<?php echo esc_attr($id_discord); ?>&theme=dark" width="100%" height="500" allowtransparency="true" frameborder="0"></iframe>
    <?php endif; 
}