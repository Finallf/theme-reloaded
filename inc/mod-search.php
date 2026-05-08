<?php
defined('ABSPATH') || exit;

/*******************************************************************************
 * Módulo de Busca — apenas as funções específicas do contexto de busca.       *
 * O renderer de card vive em inc/post-card.php (genérico, reusável por        *
 * archive, author, widgets etc.).                                              *
 *******************************************************************************/

/**
 * Aplica highlight (`<mark>`) no termo buscado dentro do texto.
 * Ignora trechos que estejam dentro de tags HTML ou entidades.
 *
 * Esta função é consumida pelo helper rd_post_card_text() em
 * inc/post-card.php quando is_search() é verdadeiro.
 */
function rd_search_highlight( string $text ) {
    $query = get_search_query();
    if ( empty( $query ) ) return $text;

    $termos = explode( ' ', preg_quote( $query, '/' ) );
    $pattern = '/(?![^<]+>)(?![^&;]+;)(' . implode( '|', $termos ) . ')/iu';

    return preg_replace( $pattern, '<mark class="rd-highlight">$1</mark>', $text );
}
