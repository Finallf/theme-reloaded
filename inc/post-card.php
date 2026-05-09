<?php
defined('ABSPATH') || exit;

/*******************************************************************************
 * Post Card — generic card renderer used by search, archive, author and any   *
 * other archive-style template. Search-specific concerns (such as highlighting *
 * the search query) layer on top via rd_search_highlight() and is_search().   *
 *******************************************************************************/

/**
 * Escapa o texto e aplica highlight do termo buscado quando estamos na
 * página de resultados de busca. Em qualquer outro contexto, retorna apenas
 * o texto escapado.
 */
function rd_post_card_text( $text ) {
    $escaped = esc_html( $text );

    if ( is_search() && function_exists('rd_search_highlight') ) {
        return rd_search_highlight( $escaped );
    }

    return $escaped;
}

/**
 * Renderiza o bloco de "views" formatado (ícone + número) para os cards.
 * Retorna string vazia quando o módulo de views não está disponível.
 */
function rd_get_formatted_views( $post_id ) {
    if ( ! function_exists('rd_get_post_views') ) return '';
    $views = rd_get_post_views( $post_id );
    return '<span class="rd-card-views" aria-label="' . esc_attr__( 'Views', 'reloaded' ) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . number_format_i18n($views) . '</span>';
}

/**
 * Renderiza um card de post no layout escolhido. Funciona dentro do loop do
 * WordPress (depende de get_the_ID(), get_the_title(), etc.).
 *
 * @param string $type 'grid' | 'vertical' | 'compact' | 'google'
 */
function rd_render_post_card( $type ) {
    $post_id = get_the_ID();
    $title   = rd_post_card_text( get_the_title() );
    $excerpt = rd_post_card_text( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 20, '...' ) );
    $link    = get_the_permalink();
    $views   = rd_get_formatted_views( $post_id );

    // Fallback de imagem
    $thumb = has_post_thumbnail() ? get_the_post_thumbnail( $post_id, 'medium', ['loading' => 'lazy'] ) : '<div class="rd-thumb-fallback"></div>';

    switch ( $type ) {
        case 'grid':
            echo '<article class="rd-search-card layout-grid">';
            echo '<a href="' . esc_url($link) . '" class="rd-card-link">';
            echo '<div class="rd-card-thumb">' . $thumb . '</div>';
            echo '<div class="rd-card-body">';
            echo '<h3 class="rd-card-title">' . $title . '</h3>';
            echo '<div class="rd-card-meta">' . $views . '</div>';
            echo '</div></a></article>';
            break;

        case 'vertical':
            echo '<article class="rd-search-card layout-vertical">';
            echo '<div class="rd-card-thumb"><a href="' . esc_url($link) . '">' . $thumb . '</a></div>';
            echo '<div class="rd-card-body">';
            echo '<h3 class="rd-card-title"><a href="' . esc_url($link) . '">' . $title . '</a></h3>';
            echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
            echo '<div class="rd-card-meta">' . $views . '</div>';
            echo '</div></article>';
            break;

        case 'compact':
            $thumb_small = has_post_thumbnail() ? get_the_post_thumbnail( $post_id, 'thumbnail', ['loading' => 'lazy'] ) : '<div class="rd-thumb-fallback-small"></div>';
            echo '<article class="rd-search-card layout-compact">';
            echo '<a href="' . esc_url($link) . '" class="rd-card-link-flex">';
            echo '<div class="rd-card-thumb-small">' . $thumb_small . '</div>';
            echo '<div class="rd-card-data"><h3 class="rd-card-title">' . $title . '</h3><span class="rd-card-date">' . get_the_date() . '</span></div>';
            echo '</a></article>';
            break;

        case 'google':
            echo '<article class="rd-search-card layout-google">';
            echo '<div class="rd-card-url">' . esc_url($link) . '</div>';
            echo '<h3 class="rd-card-title"><a href="' . esc_url($link) . '">' . $title . '</a></h3>';
            echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
            echo '</article>';
            break;
    }
}
