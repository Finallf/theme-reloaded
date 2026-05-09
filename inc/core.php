<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Core do Tema - Funções Estruturais e Nativas (Hardcoded)
 *******************************************************************************/

/*******************************************************************************
 * Funções e definições do tema ReloadeD                         - (Hardcoded) *
 *******************************************************************************/
if ( ! function_exists( 'rd_setup' ) ) :
    function rd_setup() {
        // Carrega o text domain do tema. Faz o WP procurar por
        // /languages/reloaded-{locale}.mo e habilitar __(), _e() etc.
        // Se o .mo não existir pra um locale, o WP cai pra string-fonte (inglês).
        load_theme_textdomain( 'reloaded', get_template_directory() . '/languages' );

		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) ); // Adicione suporte a HTML5
        add_theme_support( 'title-tag' ); // Adiciona suporte a títulos dinâmicos (gerenciados pelo WP)
        add_theme_support( 'post-thumbnails' ); // Habilita Imagens Destacadas (essencial para portais)
		add_theme_support( 'responsive-embeds' ); // Mantem a proporção correta dos vídeos inseridos via bloco de "Vídeo" ou "YouTube"
        add_theme_support( 'custom-logo', array( 'height' => 100, 'width' => 400, 'flex-height' => true, 'flex-width'  => true, ) ); // Habilita a troca de logotipo personalizada
        add_theme_support( 'align-wide' ); // Habilita opção no editor para que imagens ocupem a largura total da tela (estilo ""Gutenberg"")
		add_theme_support( 'automatic-feed-links' ); // Adiciona links de feeds RSS automaticamente ao <head>
		add_theme_support( 'customize-selective-refresh-widgets' ); // Permite a atualização seletiva de widgets no Customizador (sem recarregar a página toda)
		add_theme_support( 'editor-styles' ); // Exibe as fontes e estilos do tema no editor Gutenberg
		add_editor_style( 'assets/css/style.css' ); // Aponta para o seu CSS principal

        // TAMANHOS DE IMAGEM PERSONALIZADOS (HARD CROP)
        // Mantido no core pois os tamanhos precisam ser registrados na inicialização do tema
        if ( rd_get_option_bool('image_resizing') ) {
            add_image_size( 'rd-micro', 150, 84, true );        // Miniaturas para Widgets/Sidebar (16:9).
            add_image_size( 'rd-card', 600, 338, true );        // Tamanho para os cards da Home.
            add_image_size( 'rd-full-banner', 1200, 675, true );// Tamanho para o banner no topo da notícia.
        }

        // Registra os locais de menu
        register_nav_menus( array(
            'menu-1'      => esc_html__( 'Cabeçalho Principal', 'reloaded' ),
            'menu-footer' => esc_html__( 'Menu Rodapé', 'reloaded' ),
        ) );
    }
endif;
add_action( 'after_setup_theme', 'rd_setup' );

/*******************************************************************************
 * Remove tamanhos de imagem ocultos/nativos do WordPress        - (Hardcoded) *
 *******************************************************************************/
add_action( 'init', function() {
    remove_image_size( 'medium_large' );
    remove_image_size( '1536x1536' );
    remove_image_size( '2048x2048' );
});

/*******************************************************************************
 * Renderiza o Logotipo (Imagem ou Texto)                        - (Hardcoded) *
 *******************************************************************************/
function rd_render_logo() {
    if ( has_custom_logo() ) {
        echo '<div class="site-branding-image">';
        the_custom_logo();
        echo '</div>';
    } else {
        ?>
        <div class="site-branding-text">
            <h1 class="site-title">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php bloginfo( 'name' ); ?>
                </a>
            </h1>
        </div>
        <?php
    }
}

/*******************************************************************************
 * Registro das áreas de Widgets (Sidebar e Rodapé)              - (Hardcoded) *
 *******************************************************************************/
function rd_widgets_init() {

    // Barra Lateral Principal
    register_sidebar( array(
        'name'          => 'Barra Lateral Principal',
        'id'            => 'sidebar-1',
        'description'   => 'Adicione widgets aqui para aparecerem na lateral.',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="wp-block-heading widget-title">',
        'after_title'   => '</h2>',
    ) );

    // Rodapé - Área Dinâmica
    register_sidebar( array(
        'name'          => 'Rodapé - Coluna 3 (Posts Populares)',
        'id'            => 'footer-widget-area',
        'description'   => 'Adicione widgets aqui para aparecerem no rodapé.',
        'before_widget' => '<div id="%1$s" class="widget footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="footer-heading">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'rd_widgets_init' );

/***********************************************************************************
 * Carrega os scripts de mídia e css do Painel de Controle           - (Hardcoded) *
 ***********************************************************************************/
function rd_admin_scripts( $hook ) {

    // Agora sim! Procurando pelo slug correto da página: rd_options
    if ( strpos( $hook, 'rd_options' ) === false ) {
        return;
    }

    // 1. Carrega o motor nativo de mídia do WP
    wp_enqueue_media();

    // 2. Carrega o nosso JavaScript
    wp_enqueue_script( 'rd-admin-js', get_template_directory_uri() . '/assets/js/admin-scripts.js', array('jquery'), '1.0.0', true );

    // 3. Carrega o nosso CSS do painel com versionamento dinâmico
    wp_enqueue_style( 'rd-admin-css', get_template_directory_uri() . '/assets/css/admin-style.css', array(), rd_asset_version('/assets/css/admin-style.css') );
}
add_action( 'admin_enqueue_scripts', 'rd_admin_scripts' );

/*******************************************************************************
 * Helper: Versão de asset com fallback seguro                  - (Performance) *
 * Útil para cache busting em wp_enqueue_*.                                     *
 * Usa hash do conteúdo + mtime para máxima precisão de invalidação de cache.   *
 * Imune a problemas de timestamp causados por uploads atômicos via SFTP.       *
 *******************************************************************************/
function rd_asset_version( $relative_path, $fallback = '1.0.0' ) {
    $full_path = get_template_directory() . $relative_path;
    return file_exists( $full_path ) ? (string) filemtime( $full_path ) : $fallback;
}
