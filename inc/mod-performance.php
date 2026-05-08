<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Module: Performance - Otimizações e Limpeza do WordPress
 *******************************************************************************/

/*******************************************************************************
 * Suporte a Markdown, Alertas (GFM), Âncoras Dinâmicas        - (Performance) *
 *******************************************************************************/
function rd_markdown_support( $content ) {

    if ( !is_admin() && rd_get_option_bool('markdown_enabled') ) {

        // OTIMIZAÇÃO: Carrega a biblioteca pesada apenas quando realmente for usar
        if ( !class_exists('Parsedown') ) {
            require_once get_template_directory() . '/inc/Parsedown.php';
        }

        $parsedown = new Parsedown();
        $parsedown->setSafeMode(false);
        $html = $parsedown->text( $content ); // Converte o Markdown básico

        // 1. Intercepta os blocos de citação que contêm os alertas
        $html = preg_replace(
            '/<blockquote>\s*<p>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(?:<br\s*\/?>|<\/p>)?/i',
            '<blockquote class="gh-alert gh-alert-$1"><p>',
            $html
        );

        // 2. Adiciona IDs automáticos e limpos a todos os cabeçalhos (h1 até h6)
        $html = preg_replace_callback(
            '/<h([1-6])>(.*?)<\/h\1>/is',
            function( $matches ) {
                $level = $matches[1];
                $texto_original = $matches[2];
                
                // 1. Remove tags HTML de dentro do título
                $texto_puro = strip_tags( $texto_original ); 
                
                // 2. Converte para minúsculas (suportando acentuação)
                $id = mb_strtolower( $texto_puro, 'UTF-8' );
                
                // 3. A REFINAÇÃO FINAL DO GITHUB: 
                // Mantém Letras (\p{L}), Marcadores de cor (\p{M}), Formatadores/Colas invisíveis (\p{Cf}), 
                // Números (\p{Nd}), Espaços (\s) e Hifens (-). Apaga os Símbolos base.
                $id = preg_replace( '/[^\p{L}\p{M}\p{Cf}\p{Nd}\s-]/u', '', $id );
                
                // 4. O SEGREDO DO GITHUB: Troca CADA espaço individual por um hífen.
                $id = preg_replace( '/\s/u', '-', $id );
                
                // 5. Codifica para URL (Transforma a "cola" e o "fantasma" invisíveis em %E2...%EF...)
                $id = urlencode( $id );
                
                // Proteção extra: se o título sumir completamente
                if ( empty( $id ) || $id === '-' ) {
                    $id = 'secao-' . uniqid();
                }

                // Reconstrói a tag HTML com o novo ID inserido
                return "<h{$level} id=\"{$id}\">{$texto_original}</h{$level}>";
            },
            $html
        );
        
        // 3. Remove as tags <p> extras em volta de <br> isolados
        $html = preg_replace('/<p>\s*(<br\s*\/?>)\s*<\/p>/i', '$1', $html);

        return $html;
    }
    return $content;
}
add_filter( 'the_content', 'rd_markdown_support', 6 );

/*******************************************************************************
 * Enfileira scripts e estilos (CSS e JS)                      - (Performance) *
 *******************************************************************************/
function rd_scripts() {
    wp_enqueue_style( 'rd-main-style', get_template_directory_uri() . '/assets/css/style.css', array(), rd_asset_version('/assets/css/style.css') );
    wp_enqueue_script( 'rd-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), rd_asset_version('/assets/js/navigation.js'), true ); // Carrega no footer

    // Trava do Painel: Só carrega o Prism.js se a chave estiver ligada
    if ( rd_get_option_bool('prism_js') ) {
        // Carrega o Prism.js apenas nas páginas de artigo.
        if ( is_single() || is_page() ) {
            wp_enqueue_script( 'rd-prism-js', get_template_directory_uri() . '/assets/js/prism.js', array(), '1.0.0', true );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'rd_scripts' );

/*******************************************************************************
 * Desativa emojis e estilos automáticos do WP                 - (Performance) *
 *******************************************************************************/
function rd_disable_emojis() {

    if ( ! rd_get_option_bool('disable_emojis') ) return;

    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'rd_disable_emojis' );

/*******************************************************************************
 * Melhora a segurança, remove a versão do WP                  - (Performance) *
 *******************************************************************************/
add_filter('the_generator', function($gen) {
    return rd_get_option_bool('hide_wp_ver') ? '' : $gen;
});

/*******************************************************************************
 * Intercepta iframes (Youtube Facade)                         - (Performance) *
 *******************************************************************************/
function rd_youtube_facade($cache, $url, $attr) {

    if ( ! rd_get_option_bool('facades_enabled') ) { return $cache; }

    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false || strpos($url, 'youtube-nocookie.com') !== false) {
        preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|v/)|youtu\.be/|youtube-nocookie\.com/embed/)([a-zA-Z0-9_-]{11})~', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';

        if ($video_id) {
            return '<div class="rd-facade" data-type="youtube" data-id="' . esc_attr($video_id) . '" style="position:relative; cursor:pointer;">
                        <img src="https://img.youtube.com/vi/' . esc_attr($video_id) . '/sddefault.jpg" alt="Video cover" loading="lazy">
                        <div class="play-button">
                            <svg viewBox="0 0 68 48">
                                <path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55c-2.93.78-4.64 3.26-5.42 6.19C.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="#FF0000"/>
                                <path d="M27.26 33.15V14.85L44.5 24z" fill="#fff"/>
                            </svg>
                        </div>
                    </div>';
        }
    }
    return $cache;
}
add_filter('embed_oembed_html', 'rd_youtube_facade', 10, 3);

/*******************************************************************************
 * Desativa o CSS do Gutenberg e Global Syles                  - (Performance) *
 *******************************************************************************/
function rd_disable_gutenberg_assets() {
    if ( ! rd_get_option_bool('disable_gutenberg_css') ) return;

    add_filter( 'should_load_separate_core_block_assets', '__return_false' );

    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
    remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

    add_action( 'wp_enqueue_scripts', function() {
        wp_dequeue_style( 'global-styles' );
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'classic-theme-styles' );
        wp_dequeue_style( 'wc-blocks-style' );
    }, 100 );
}
add_action( 'after_setup_theme', 'rd_disable_gutenberg_assets' );