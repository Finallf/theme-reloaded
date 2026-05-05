<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Module: SEO - Open Graph, Twitter Cards e Meta Tags
 *******************************************************************************/

/*******************************************************************************
 * Meta Tags Open Graph                                                - (SEO) *
 *******************************************************************************/
function rd_add_open_graph_tags() {
    // Só injeta as tags se estivermos lendo um post individual ou uma página
    if ( ( is_single() || is_page() ) && rd_get_option('enable_open_graph') == 1 ) {
        global $post;

        // Puxa as informações essenciais do post atual
        $title = get_the_title();
        $url   = get_permalink();
        
        // Cria um resumo limpo (sem tags HTML) a partir do conteúdo do post
        $excerpt = strip_tags( get_the_excerpt() );
        if ( empty($excerpt) ) {
            $excerpt = wp_trim_words( strip_tags( $post->post_content ), 25, '...' );
        }

        // Puxa a URL da imagem destacada em resolução máxima (Full)
        $img_url = '';
        if ( has_post_thumbnail( $post->ID ) ) {
            $img_url = get_the_post_thumbnail_url( $post->ID, 'full' );
        } else {
            // Busca a imagem customizada do painel
            $custom_fallback = rd_get_option('og_fallback_image');

            // Lógica: se houver imagem no painel, usa ela. Se não, usa o logo padrão do tema.
            $img_url = !empty($custom_fallback) ? $custom_fallback : get_template_directory_uri() . '/assets/img/logo-reloaded-painel.webp';
        }

        // --- IMPRESSÃO DAS META TAGS NA TELA ---
        echo "\n\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

        if ( !empty($img_url) ) {
            // Esta tag diz ao Facebook/Discord/WhatsApp qual imagem puxar
            echo '<meta property="og:image" content="' . esc_url( $img_url ) . '" />' . "\n";
            // Força a imagem a aparecer no formato de "Card Grande" (Essencial pro Twitter/X)
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta property="og:image:width" content="1200" />' . "\n";
            echo '<meta property="og:image:height" content="630" />' . "\n";
        }
        
        echo "\n";
    }
}
add_action( 'wp_head', 'rd_add_open_graph_tags', 5 );