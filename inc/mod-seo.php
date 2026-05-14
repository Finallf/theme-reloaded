<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Module: SEO - Open Graph, Twitter Cards e Meta Tags
 *******************************************************************************/

// Mínimo aceito pelo Facebook (200x200). Imagens menores são rejeitadas.
const RD_SEO_OG_IMAGE_MIN = 200;

/*******************************************************************************
 * Validação interna de imagens para og:image                          - (SEO) *
 *******************************************************************************/

/**
 * Valida um attachment do Media Library para uso como og:image.
 * Rejeita SVGs (não suportados por redes sociais) e imagens abaixo de 200x200.
 *
 * @param int $attachment_id
 * @return array|false ['url' => string, 'width' => int, 'height' => int] ou false se inválido
 */
function rd_seo_validate_attachment_image( int $attachment_id ) {
    // Rejeita SVG (Facebook, Twitter/X, WhatsApp e Discord não renderizam)
    if ( get_post_mime_type( $attachment_id ) === 'image/svg+xml' ) {
        return false;
    }

    $src = wp_get_attachment_image_src( $attachment_id, 'full' );
    if ( ! $src ) return false;

    list( $url, $width, $height ) = $src;

    // Rejeita imagens menores que o mínimo aceito pelo Facebook
    if ( $width < RD_SEO_OG_IMAGE_MIN || $height < RD_SEO_OG_IMAGE_MIN ) {
        return false;
    }

    return [ 'url' => $url, 'width' => (int) $width, 'height' => (int) $height ];
}

/**
 * Valida uma URL de imagem (do painel, possivelmente externa) para og:image.
 * Se a URL pertencer ao Media Library, delega à validação completa (com dimensões).
 * Para URLs externas, confia na extensão e omite as dimensões — sem fazer HTTP
 * request a cada page load.
 *
 * @param string $url
 * @return array|false ['url' => string, 'width' => int|null, 'height' => int|null] ou false se inválido
 */
function rd_seo_validate_url_image( string $url ) {
    if ( empty( $url ) ) return false;

    $path = parse_url( $url, PHP_URL_PATH );
    $ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

    // Rejeita SVG e qualquer formato não-raster
    if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ], true ) ) {
        return false;
    }

    // Se a URL for de um attachment local, usa a validação completa
    // (respeita seu veredito; se falhar, NÃO cai pra modo "externa pura")
    $attachment_id = attachment_url_to_postid( $url );
    if ( $attachment_id ) {
        return rd_seo_validate_attachment_image( $attachment_id );
    }

    // URL externa — extensão raster válida, dimensões desconhecidas
    return [ 'url' => $url, 'width' => null, 'height' => null ];
}

/**
 * Resolve a melhor imagem disponível para og:image, em ordem de preferência:
 *   1. Featured image do post (validada)
 *   2. Imagem fallback configurada no painel (validada)
 *   3. Logo do tema (controlado pelo dev, sempre considerado válido)
 *
 * @param int $post_id
 * @return array ['url' => string, 'width' => int|null, 'height' => int|null]
 */
function rd_seo_resolve_og_image( int $post_id ) {
    // 1. Featured image
    if ( has_post_thumbnail( $post_id ) ) {
        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        $resolved = rd_seo_validate_attachment_image( $thumb_id );
        if ( $resolved ) return $resolved;
    }

    // 2. og_fallback_image do painel (pode ser interna ou externa)
    $fallback = rd_get_option( 'og_fallback_image' );
    if ( ! empty( $fallback ) ) {
        $resolved = rd_seo_validate_url_image( $fallback );
        if ( $resolved ) return $resolved;
    }

    // 3. Fallback final: logo do tema
    return [
        'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-painel.webp',
        'width'  => null,
        'height' => null,
    ];
}

/*******************************************************************************
 * Meta Tags Open Graph                                                - (SEO) *
 *******************************************************************************/
function rd_add_open_graph_tags() {
    if ( ! ( is_single() || is_page() ) ) return;
    if ( ! rd_get_option_bool( 'enable_open_graph' ) ) return;

    global $post;

    $title = get_the_title();
    $url   = get_permalink();

    // Resumo limpo (sem tags HTML) — usa o excerpt manual ou trim do conteúdo
    $excerpt = strip_tags( get_the_excerpt() );
    if ( empty( $excerpt ) ) {
        $excerpt = wp_trim_words( strip_tags( $post->post_content ), 25, '...' );
    }

    $image = rd_seo_resolve_og_image( (int) $post->ID );

    // --- IMPRESSÃO DAS META TAGS NA TELA ---
    echo "\n\n";
    echo '<meta property="og:type" content="article" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

    echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '" />' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";

    // Só declara dimensões se realmente as conhecermos — evita mentir
    // pra rede social (que ajusta o crop com base nelas)
    if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
        echo '<meta property="og:image:width" content="' . (int) $image['width'] . '" />' . "\n";
        echo '<meta property="og:image:height" content="' . (int) $image['height'] . '" />' . "\n";
    }

    echo "\n";
}
add_action( 'wp_head', 'rd_add_open_graph_tags', 5 );
