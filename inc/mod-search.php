<?php
defined('ABSPATH') || exit;

/**
 * Aplica Highlight na palavra buscada (ignora HTML tags)
 */
function rd_search_highlight( $text ) {
    $query = get_search_query();
    if ( empty( $query ) ) return $text;

    $termos = explode( ' ', preg_quote( $query, '/' ) );
    $pattern = '/(?![^<]+>)(?![^&;]+;)(' . implode( '|', $termos ) . ')/iu';

    return preg_replace( $pattern, '<mark class="rd-highlight">$1</mark>', $text );
}

/**
 * Retorna as views formatadas para os cards
 */
function rd_get_formatted_views( $post_id ) {
    if ( ! function_exists('rd_get_post_views') ) return '';
    $views = rd_get_post_views( $post_id );
    return '<span class="rd-card-views" aria-label="Visualizações"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . number_format_i18n($views) . '</span>';
}

/**
 * Templates HTML de cada layout
 */
function rd_render_search_layout( $type ) {
    $post_id = get_the_ID();
    // Escapa ANTES do highlight para que o <mark> seja a única tag injetada.
    $title   = rd_search_highlight( esc_html( get_the_title() ) );
    $excerpt = rd_search_highlight( esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 20, '...' ) ) );
    $link    = get_the_permalink();
    $views   = rd_get_formatted_views( $post_id );

    // Fallback de Imagem
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
