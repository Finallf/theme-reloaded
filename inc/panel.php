<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Painel de Opções Avançado - ReloadeD
 * Organizado por Abas (Tabs) para LGPD, Performance, SEO e Integrações.
 *******************************************************************************/

/*******************************************************************************
 * Configurações padrão na ativação do tema                      - (Hardcoded) *
 *******************************************************************************/
function rd_set_default_options() {
    // Verifica se as configurações já existem no banco
    $options = get_option('rd_settings');

    // Se for falso, significa que é a primeira vez que o tema está sendo ativado
    if ( false === $options ) {
        $defaults = array(
            'back_to_top'           => 1, // Ligado
            'enable_top_bar'        => 1,
            'image_resizing'        => 1,
            'enable_thumb_control'  => 1,
            'jpeg_quality'          => 90, // Valor padrão
            'comment_a11y'          => 1,
            'excerpt_text'          => '',
            'comments_separator'    => '',
            'date_format'           => '',
            'enable_views_tracking' => 1,

            'search_layout_grid'     => 1,
            'search_layout_vertical' => 0,
            'search_layout_compact'  => 1,
            'search_layout_google'   => 0,

            'enable_lgpd'           => 1,
            'lgpd_text'             => 'Nós usamos cookies e tecnologias semelhantes para melhorar a sua experiência. Ao continuar navegando, você concorda com a nossa <a href="/politica-de-privacidade">Política de Privacidade</a>.',

            'ga_id'                 => '',
            'discord_widget'        => 1,
            'discord_id'            => '',

            'markdown_enabled'      => 1,
            'prism_js'              => 1,
            'disable_emojis'        => 1,
            'hide_wp_ver'           => 1,
            'facades_enabled'       => 1,
            'disable_gutenberg_css' => 1,
            'enable_security_headers' => 1,

            'social_discord'        => '',
            'social_telegram'       => '',
            'social_youtube'        => '',
            'social_instagram'      => '',
            'social_steam'          => '',
            'social_twitter'        => '',
            'social_facebook'       => '',
            'social_whatsapp'       => '',

            'enable_open_graph'     => 1,
            'og_fallback_image'     => '',

            'github_sponsors'       => '',
            'paypal_url'            => '',
            'paypal_qrcode'         => '',
            'pix_url'               => '',
            'pix_qrcode'            => '',
            'pix_chave'             => '',

            'ad_global'             => '',
            'ad_topo_desktop'       => '',
            'ad_topo_mobile'        => '',
            'ad_sidebar_top'        => '',
            'ad_sidebar_sticky'     => '',

            'maintenance_mode'      => 0,
            'maintenance_pass'      => '',
            'maintenance_text'      => '',
        );
        // Salva os padrões no banco de dados
        update_option('rd_settings', $defaults);
    }
}
add_action('after_switch_theme', 'rd_set_default_options');

/***********************************************************************************
 * Busca uma opção do painel de forma segura (Defensive Programming) - (Hardcoded) *
 ***********************************************************************************/
function rd_get_option( string $key, $default = false ) {
    $opt = get_option('rd_settings');

    if ( ! isset( $opt ) || ! isset( $opt[$key] ) ) {
        return $default;
    }

    return $opt[$key];
}

/***********************************************************************************
 * Versão booleana — devolve true/false aceitando qualquer formato histórico       *
 * de armazenamento (1, '1', 0, '0', false, null, ausente).                        *
 * Use em todos os call sites de toggles do tipo checkbox.                         *
 ***********************************************************************************/
function rd_get_option_bool( string $key ): bool {
    return (int) rd_get_option( $key ) === 1;
}

/*******************************************************************************
 * Cria o Menu no Painel
 *******************************************************************************/
function rd_add_admin_menu() {
    add_menu_page(
        __('ReloadeD Options', 'reloaded'),
        'ReloadeD',
        'manage_options',
        'rd_options',
        'rd_options_render',
        get_template_directory_uri() . '/assets/img/logo-reloaded-20x20.webp',
        3
    );
}
add_action('admin_menu', 'rd_add_admin_menu');

/*******************************************************************************
 * Renderiza a Interface Visual (HTML)
 *******************************************************************************/
function rd_options_render() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Define a aba ativa (padrão é 'geral')
    $active_tab = isset($_GET['tab']) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'geral';

    // Pega o caminho absoluto da pasta do seu tema para buscar a imagem
    $theme_dir = get_template_directory_uri();

    // Lista mestre de abas para o loop
    $tabs = [
        'geral'       => __( 'General Features', 'reloaded' ),
        'privacidade' => __( 'Privacy (LGPD)', 'reloaded' ),
        'integracoes' => __( 'Integrations', 'reloaded' ),
        'performance' => __( 'Performance', 'reloaded' ),
        'redes'       => __( 'Social Networks', 'reloaded' ),
        'seo'         => __( 'SEO', 'reloaded' ),
        'interface'   => __( 'Donations', 'reloaded' ),
        'ads'         => __( 'Ads', 'reloaded' ),
        'manutencao'  => __( 'Maintenance', 'reloaded' )
    ];
    ?>
    <div class="wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ccd0d4;">
            <h1 style="margin: 0; padding: 0;"><?php esc_html_e( 'ReloadeD - Control Panel', 'reloaded' ); ?></h1>
            <img src="<?php echo esc_url($theme_dir); ?>/assets/img/logo-reloaded-painel.webp" alt="ReloadeD Logo" style="max-height: 50px; width: auto;">
        </div>

            <?php settings_errors(); ?>

        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $id => $name) : ?>
                <a href="?page=rd_options&tab=<?php echo esc_attr( $id ); ?>"
                    class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $name ); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <form action="options.php" method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top:none; margin-top:0;">
            <?php
            settings_fields('rd_options_group');

            foreach ($tabs as $id => $name) {
                $display = ($active_tab === $id) ? '' : 'display:none;';
                echo '<div class="rd-tab-content" id="tab-' . esc_attr( $id ) . '" style="' . esc_attr( $display ) . '">';
                do_settings_sections('rd_options_' . $id);
                echo '</div>';
            }

            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/*******************************************************************************
 * Registra os Campos e Seções no Banco de Dados
 *******************************************************************************/
function rd_settings_init() {
    register_setting('rd_options_group', 'rd_settings', 'rd_options_sanitize');

    // --- GERAL ---
    add_settings_section('sec_geral', __( 'Theme Features', 'reloaded' ), '__return_false', 'rd_options_geral');
    add_settings_field('back_to_top', __( 'Back to Top Button', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'back_to_top', 'type' => 'checkbox', 'desc' => __( 'Enables a floating button in the bottom right corner for the user to quickly return to the top of the page.', 'reloaded' )]);
    add_settings_field('enable_top_bar', __( 'Enable Top Bar', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'enable_top_bar', 'type' => 'checkbox', 'desc' => __( 'Displays a small bar at the top with date, latest news, and social networks.', 'reloaded' )]);
    add_settings_field('image_resizing', __( 'Resize Images', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'image_resizing', 'type' => 'checkbox', 'desc' => __( 'Enables exact cropping (Hard Crop) of uploaded images to ensure banners and cards are always aligned.', 'reloaded' )]);
    add_settings_field('enable_thumb_control', __( 'Featured Image', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'enable_thumb_control', 'type' => 'checkbox', 'desc' => __( 'Adds an option in the post editor sidebar to hide the featured image when reading the article (ideal for posts with videos at the top).', 'reloaded' )]);
    add_settings_field('jpeg_quality', __( 'Image Quality (%)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'jpeg_quality', 'type' => 'number', 'default' => '90', 'desc' => __( 'Changes image quality. WP default is 82. Lower values make the site faster but reduce visual quality.', 'reloaded' )]);
    add_settings_field('comment_a11y', __( 'Comment Accessibility', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'comment_a11y', 'type' => 'checkbox', 'desc' => __( 'Adds labels and autocomplete attributes to the comment form.', 'reloaded' )]);
    add_settings_field('excerpt_text', __( 'Read More Button Text', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'excerpt_text', 'type' => 'text', 'placeholder' => __( 'Ex: Continue Reading &rarr;', 'reloaded' ), 'desc' => __( 'Customizes the excerpt button text. Leave blank to use the theme default.', 'reloaded' )]);
    add_settings_field('comments_separator', __( 'Comments Separator', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'comments_separator', 'type' => 'text', 'desc' => __( 'Text between Author and Post (e.g., \"commented on post:\").<br>Leave <strong>empty</strong> for WP default or type <strong>&amp;nbsp;</strong> to hide.', 'reloaded' )]);
    add_settings_field('date_format', __( 'Date Format', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'date_format', 'type' => 'text', 'default' => 'l, j \d\e F \d\e Y', 'desc' => __( 'Ex: l, F j, Y (Returns: Monday, April 29, 2026). <a href=\"https://wordpress.org/documentation/article/customize-date-and-time-format/\" target=\"_blank\">See WP documentation</a>.', 'reloaded' )]);
    add_settings_field('enable_views_tracking', __( 'Views Counter', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'enable_views_tracking', 'type' => 'checkbox', 'desc' => __( 'Enables the post views counting system. A single IP counts only once every 30 minutes. Known bots are automatically ignored.', 'reloaded' )]);

    // --- PÁGINA DE BUSCA (Sub-seção dentro da aba Recursos Gerais) ---
    add_settings_section('sec_geral_search', __( 'Search Page', 'reloaded' ), '__return_false', 'rd_options_geral');
    add_settings_field('search_layout_grid', __( 'Grid Layout', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral_search', ['id' => 'search_layout_grid', 'type' => 'checkbox', 'desc' => __( 'Enables Grid layout (Cards side by side).', 'reloaded' )]);
    add_settings_field('search_layout_vertical', __( 'Vertical List', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral_search', ['id' => 'search_layout_vertical', 'type' => 'checkbox', 'desc' => __( 'Enables Vertical List layout (Large image + excerpt).', 'reloaded' )]);
    add_settings_field('search_layout_compact', __( 'Compact List', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral_search', ['id' => 'search_layout_compact', 'type' => 'checkbox', 'desc' => __( 'Enables Compact layout (Thumbnail + title inline).', 'reloaded' )]);
    add_settings_field('search_layout_google', __( 'Google Style', 'reloaded' ), 'rd_master_field_cb', 'rd_options_geral', 'sec_geral_search', ['id' => 'search_layout_google', 'type' => 'checkbox', 'desc' => __( 'Enables minimalist text-focused layout, similar to search engines.', 'reloaded' )]);

    // --- PRIVACIDADE ---
    add_settings_section('sec_priv', __( 'LGPD and Cookies', 'reloaded' ), '__return_false', 'rd_options_privacidade');
    add_settings_field('enable_lgpd', __( 'LGPD - Cookie Banner', 'reloaded' ), 'rd_master_field_cb', 'rd_options_privacidade', 'sec_priv', ['id' => 'enable_lgpd', 'type' => 'checkbox', 'desc' => __( 'Enables the cookie consent banner in the site footer for legal compliance.', 'reloaded' )]);
    add_settings_field('lgpd_text', __( 'Cookie Banner Text', 'reloaded' ), 'rd_master_field_cb', 'rd_options_privacidade', 'sec_priv', ['id' => 'lgpd_text', 'type' => 'textarea', 'desc' => __( 'Customize the message shown to the user. You can use HTML tags like &lt;a&gt; for links.', 'reloaded' )]);

    // --- INTEGRAÇÕES ---
    add_settings_section('sec_int', __( 'Scripts and IDs', 'reloaded' ), '__return_false', 'rd_options_integracoes');
    add_settings_field('ga_id', __( 'Google Analytics ID', 'reloaded' ), 'rd_master_field_cb', 'rd_options_integracoes', 'sec_int', ['id' => 'ga_id', 'type' => 'text', 'placeholder' => 'G-XXXXXXX', 'desc' => __( 'Insert only the tracking code (Tag ID). Leave empty to disable.', 'reloaded' )]);
    add_settings_field('discord_widget', __( 'Enable Discord Widget', 'reloaded' ), 'rd_master_field_cb', 'rd_options_integracoes', 'sec_int', ['id' => 'discord_widget', 'type' => 'checkbox', 'desc' => __( 'Enables displaying the Discord server in the sidebar.', 'reloaded' )]);
    add_settings_field('discord_id', __( 'Discord ID', 'reloaded' ), 'rd_master_field_cb', 'rd_options_integracoes', 'sec_int', ['id' => 'discord_id', 'type' => 'text', 'desc' => __( 'Server ID to activate communication with the official widget in the sidebar.', 'reloaded' )]);

    // --- PERFORMANCE ---
    add_settings_section('sec_perf', __( 'Technical Optimization', 'reloaded' ), '__return_false', 'rd_options_performance');
    add_settings_field('markdown_enabled', __( 'Markdown', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'markdown_enabled', 'type' => 'checkbox', 'desc' => __( 'Enables support for Markdown syntax, allowing you to write articles natively (like GitHub or Docker Hub).', 'reloaded' )]);
    add_settings_field('prism_js', __( 'Code Highlight', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'prism_js', 'type' => 'checkbox', 'desc' => __( 'Enables Syntax Highlight support, which colors programming codes. It is only loaded on posts for performance.', 'reloaded' )]);
    add_settings_field('disable_emojis', __( 'Disable Native Emojis', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'disable_emojis', 'type' => 'checkbox', 'desc' => __( 'Removes the WP emojis script. Modern browsers already render emojis natively, enable this to save requests.', 'reloaded' )]);
    add_settings_field('hide_wp_ver', __( 'Hide WP Version', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'hide_wp_ver', 'type' => 'checkbox', 'desc' => __( 'Removes the WordPress generator meta tag from the source code. A basic security best practice.', 'reloaded' )]);
    add_settings_field('enable_security_headers', __( 'Security Headers', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'enable_security_headers', 'type' => 'checkbox', 'desc' => __( 'Sends defensive HTTP headers on the frontend (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) to mitigate clickjacking and MIME sniffing. Does not affect the admin panel.', 'reloaded' )]);
    add_settings_field('facades_enabled', __( 'Facades System', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'facades_enabled', 'type' => 'checkbox', 'desc' => __( 'Replaces heavy iframes (e.g. YouTube) with a lightweight image, loading the player only when the user clicks.', 'reloaded' )]);
    add_settings_field('disable_gutenberg_css', __( 'Disable WP CSS (Bloat)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'disable_gutenberg_css', 'type' => 'checkbox', 'desc' => __( 'Removes global and block CSS from Gutenberg. Makes the site lighter, ideal for those using Markdown.', 'reloaded' )]);

    // --- REDES SOCIAIS ---
    add_settings_section('sec_redes', __( 'Your Social Network Links', 'reloaded' ), '__return_false', 'rd_options_redes');
    add_settings_field('social_discord', __( 'Discord', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_discord', 'type' => 'text', 'placeholder' => 'https://discord.gg/...', 'desc' => __( 'Link to your server or permanent invite to the community.', 'reloaded' )]);
    add_settings_field('social_telegram', __( 'Telegram', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_telegram', 'type' => 'text', 'placeholder' => 'https://t.me/...', 'desc' => __( 'Link to your official channel or group.', 'reloaded' )]);
    add_settings_field('social_youtube', __( 'YouTube', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_youtube', 'type' => 'text', 'placeholder' => 'https://youtube.com/@...', 'desc' => __( 'URL to your channel to display videos and streams.', 'reloaded' )]);
    add_settings_field('social_instagram', __( 'Instagram', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_instagram', 'type' => 'text', 'placeholder' => 'https://instagram.com/...', 'desc' => __( 'Link to your profile for photos and visual updates.', 'reloaded' )]);
    add_settings_field('social_steam', __( 'Steam', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_steam', 'type' => 'text', 'placeholder' => 'https://steamcommunity.com/groups/...', 'desc' => __( 'Link to your Steam group or curator profile.', 'reloaded' )]);
    add_settings_field('social_twitter', __( 'Twitter (X)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_twitter', 'type' => 'text', 'placeholder' => 'https://x.com/...', 'desc' => __( 'Link to your official profile on X.', 'reloaded' )]);
    add_settings_field('social_facebook', __( 'Facebook', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_facebook', 'type' => 'text', 'placeholder' => 'https://facebook.com/...', 'desc' => __( 'Link to your official page or community.', 'reloaded' )]);
    add_settings_field('social_whatsapp', __( 'WhatsApp', 'reloaded' ), 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_whatsapp', 'type' => 'text', 'placeholder' => 'https://wa.me/5511999999999', 'desc' => __( 'Direct link to your WhatsApp number or group (use international format).', 'reloaded' )]);

    // --- SEO ---
    add_settings_section('sec_seo', __( 'SEO', 'reloaded' ), '__return_false', 'rd_options_seo');
    add_settings_field('enable_open_graph', __( 'Open Graph Meta Tags', 'reloaded' ), 'rd_master_field_cb', 'rd_options_seo', 'sec_seo', ['id' => 'enable_open_graph', 'type' => 'checkbox', 'desc' => __( 'Enables the OG Tags system. Generates the necessary tags for social networks (Facebook, Discord, WhatsApp).', 'reloaded' )]);
    add_settings_field('og_fallback_image', __( 'Fallback Image (Open Graph)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_seo', 'sec_seo', ['id' => 'og_fallback_image', 'type' => 'media', 'desc' => __( 'Select an image from your library to be the default on social networks.', 'reloaded' )]);

    // --- DOAÇÕES ---
    add_settings_section('sec_inter', __( 'Donation System', 'reloaded' ), '__return_false', 'rd_options_interface');
    add_settings_field('github_sponsors', __( 'GitHub Sponsors', 'reloaded' ), 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'github_sponsors', 'type' => 'text', 'placeholder' => 'https://github.com/sponsors/seu-usuario', 'desc' => __( 'Link to your official GitHub sponsorship page for global supporters.', 'reloaded' )]);
    add_settings_field('paypal_url', __( 'PayPal Donation Link', 'reloaded' ), 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'paypal_url', 'type' => 'text', 'placeholder' => 'https://www.paypal.com/donate?hosted_button_id=XXXX', 'desc' => __( 'Direct URL to your PayPal donation page.', 'reloaded' )]);
    add_settings_field('paypal_qrcode', __( 'PayPal QR Code', 'reloaded' ), 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'paypal_qrcode', 'type' => 'media', 'desc' => __( 'Upload the PayPal QR Code. When clicked on the site, it will open the link configured above.', 'reloaded' )]);
    add_settings_field('pix_url', __( 'PIX Link (Copy and Paste)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'pix_url', 'type' => 'text', 'placeholder' => 'https://nubank.com.br/pagar/xxx', 'desc' => __( 'Direct URL for PIX payment (if your bank provides a link). The QR Code will be clickable.', 'reloaded' )]);
    add_settings_field('pix_qrcode', __( 'PIX QR Code', 'reloaded' ), 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'pix_qrcode', 'type' => 'media', 'desc' => __( 'Upload your PIX QR Code image.', 'reloaded' )]);
    add_settings_field('pix_chave', __( 'PIX Key', 'reloaded' ), 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'pix_chave', 'type' => 'text', 'placeholder' => __( 'email@domain.com or CPF/CNPJ', 'reloaded' ), 'desc' => __( 'Your direct PIX key for supporters from Brazil.', 'reloaded' )]);

    // --- ADS ---
    add_settings_section('sec_ads', __( 'Advertising Zones', 'reloaded' ), '__return_false', 'rd_options_ads');
    add_settings_field('ad_global', __( 'Global Ad Script', 'reloaded' ), 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_global', 'type' => 'textarea', 'desc' => __( 'Paste here the global &lt;head&gt; tag (e.g. AdSense Auto Ads).', 'reloaded' )]);
    add_settings_field('ad_topo_desktop', __( 'Top Banner - Desktop (728x90)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_topo_desktop', 'type' => 'textarea', 'desc' => __( 'Rendered in the header only on large screens (PCs and Laptops).', 'reloaded' )]);
    add_settings_field('ad_topo_mobile', __( 'Top Banner - Mobile (320x100)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_topo_mobile', 'type' => 'textarea', 'desc' => __( 'Rendered in the header only on smartphone screens.', 'reloaded' )]);
    add_settings_field('ad_sidebar_top', __( 'Sidebar Banner - Top (300x250)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_sidebar_top', 'type' => 'textarea', 'desc' => __( 'Rendered in the sidebar, right below integrations (e.g. Discord).', 'reloaded' )]);
    add_settings_field('ad_sidebar_sticky', __( 'Sidebar Banner - Sticky (300x600)', 'reloaded' ), 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_sidebar_sticky', 'type' => 'textarea', 'desc' => __( 'Rendered at the bottom of the sidebar. Follows the screen scroll.', 'reloaded' )]);

    // --- MANUTENÇÃO ---
    add_settings_section('sec_manut', __( 'Access Control', 'reloaded' ), '__return_false', 'rd_options_manutencao');
    add_settings_field('maintenance_mode', __( 'Enable Maintenance', 'reloaded' ), 'rd_master_field_cb', 'rd_options_manutencao', 'sec_manut', ['id' => 'maintenance_mode', 'type' => 'checkbox', 'desc' => __( 'Blocks access from regular visitors and displays a "We\'ll be right back" screen (Returns HTTP 503 to Google).', 'reloaded' )]);
    add_settings_field('maintenance_pass', __( 'Dev Password', 'reloaded' ), 'rd_master_field_cb', 'rd_options_manutencao', 'sec_manut', ['id' => 'maintenance_pass', 'type' => 'password', 'desc' => __( 'Password for developers to bypass maintenance. Access: <strong>yourdomain.com/?rd-dev-login</strong> and enter the password in the form (do not pass it through the URL). The password is stored as a cryptographic hash — after saving, this field will appear empty (this is normal and secure).', 'reloaded' )]);
    add_settings_field('maintenance_text', __( 'Maintenance Text', 'reloaded' ), 'rd_master_field_cb', 'rd_options_manutencao', 'sec_manut', ['id' => 'maintenance_text', 'type' => 'textarea', 'desc' => __( 'Customize the message visitors will see on the block screen. Accepts basic HTML (e.g. &lt;strong&gt;, &lt;br&gt;).', 'reloaded' )]);
}
add_action('admin_init', 'rd_settings_init');

/*******************************************************************************
 * Funções de renderização dos campos (Reutilizáveis)
 *******************************************************************************/
function rd_master_field_cb( array $args ) {
    // 1. Puxa as opções do banco de dados apenas uma vez
    $opt = get_option('rd_settings');

    // 2. Define o valor atual ou um fallback vazio
    $val = isset( $opt[$args['id']] ) ? $opt[$args['id']] : '';

    // 3. Define o tipo do campo (se não for passado, assume 'text' como padrão)
    $type = isset( $args['type'] ) ? $args['type'] : 'text';

    // 4. Monta o atributo 'name' dinamicamente
    $name = 'rd_settings[' . esc_attr( $args['id'] ) . ']';

    // 5. O Switch: Renderiza o HTML correto dependendo do 'type' solicitado
    switch ( $type ) {

        case 'media':
            echo '<div class="rd-media-container">';
            // O input oculto que realmente guarda a URL para o banco de dados
            echo '<input type="hidden" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '">';

            // A div de preview onde a miniatura da imagem vai aparecer
            echo '<div id="' . esc_attr( $args['id'] ) . '_preview" class="rd-media-preview" style="margin-bottom: 10px;">';
            if ( $val ) {
                echo '<img src="' . esc_url( $val ) . '" style="max-width: 200px; height: auto; border: 1px solid #ccc; display: block; border-radius: 4px;">';
            }
            echo '</div>';

            // Os botões de controle
            echo '<button type="button" class="button rd-upload-button" data-input-id="' . esc_attr( $args['id'] ) . '">' . esc_html__( 'Select Image', 'reloaded' ) . '</button> ';

            $display = $val ? '' : 'display:none;';
            echo '<button type="button" class="button rd-remove-button" data-input-id="' . esc_attr( $args['id'] ) . '" style="' . $display . '">' . esc_html__( 'Remove', 'reloaded' ) . '</button>';
            echo '</div>';
        break;

        case 'checkbox':
            $val = isset( $opt[$args['id']] ) ? $opt[$args['id']] : 0;

            // Hidden de fallback: garante que checkbox DESMARCADO envie '0' explícito em vez
            // de omitir a key do POST. Sem isso, desmarcar não viaja pro sanitizer.
            echo '<input type="hidden" name="' . $name . '" value="0">';

            // Envolvemos tudo em uma tag <label> para que o texto fique na mesma linha e seja clicável
            echo '<label for="' . esc_attr( $args['id'] ) . '">';
            echo '<input type="checkbox" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( 1, $val, false ) . '>';

            // Se houver descrição, imprime ela aqui dentro da label, logo após o quadradinho
            if ( isset( $args['desc'] ) ) {
                echo ' ' . wp_kses_post( $args['desc'] );

                // O pulo do gato: removemos a descrição da variável para que
                // o código lá no final da função não imprima ela duplicada.
                unset( $args['desc'] );
            }
            echo '</label>';
        break;

        case 'textarea':
            echo '<textarea name="' . $name . '" rows="5" class="large-text">' . esc_textarea( $val ) . '</textarea>';
        break;

        case 'number':
            $min = isset( $args['min'] ) ? $args['min'] : 1;
            $max = isset( $args['max'] ) ? $args['max'] : 100;
            $val = empty($val) && isset($args['default']) ? $args['default'] : $val;

            // Renderiza o input com ID e a classe nativa do WP para manter o padrão
            echo '<input type="number" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" class="small-text">';

            // Se houver descrição, imprime logo à frente do campo dentro de uma label
            if ( isset( $args['desc'] ) ) {
                echo ' <label for="' . esc_attr( $args['id'] ) . '">' . wp_kses_post( $args['desc'] ) . '</label>';

                // Remove a descrição da memória para não duplicar no final da função
                unset( $args['desc'] );
            }
        break;

        case 'select':
            echo '<select name="' . $name . '">';
            if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
                foreach ( $args['options'] as $key => $label ) {
                    echo '<option value="' . esc_attr( $key ) . '" ' . selected( $val, $key, false ) . '>' . esc_html( $label ) . '</option>';
                }
            }
            echo '</select>';
        break;

        case 'password':
            echo '<input type="password" name="' . $name . '" value="' . esc_attr( $val ) . '" class="regular-text">';
        break;

        case 'text':
        default:
            $placeholder = isset( $args['placeholder'] ) ? "placeholder='" . esc_attr( $args['placeholder'] ) . "'" : '';
            echo '<input type="text" name="' . $name . '" value="' . esc_attr( $val ) . '" class="regular-text" ' . $placeholder . '>';
        break;
    }

    // A descrição já é impressa perfeitamente aqui para todos os tipos!
    if ( isset( $args['desc'] ) ) {
        echo '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

/*******************************************************************************
 * SANITIZAÇÃO DOS DADOS (Segurança)
 *******************************************************************************/
function rd_options_sanitize( array $input ) {
    $new_input = array();
    foreach($input as $key => $value) {

        // 1. Zonas de Anúncios: Permite HTML e Scripts puros (AdSense, etc)
        if ( strpos($key, 'ad_') === 0 ) {
            $new_input[$key] = $value;
        }
        // 2. Campo LGPD: Permite HTML básico seguro (Links, negrito), mas bloqueia scripts
        elseif ( $key === 'lgpd_text' ) {
            $new_input[$key] = wp_kses_post($value);
        }
        // 3. Checkboxes: chegam como '0' ou '1' (graças ao hidden de fallback no form).
        //    Coage pra int pra padronizar o storage e habilitar comparações strict (=== 1).
        elseif ( $value === '0' || $value === '1' ) {
            $new_input[$key] = (int) $value;
        }
        // 4. Demais campos: Limpeza rigorosa, destrói qualquer HTML e script
        else {
            $new_input[$key] = sanitize_text_field($value);
        }
    }
    return $new_input;
}
