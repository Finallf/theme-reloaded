<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Related Posts                                                        *
 *                                                                              *
 * Renderiza um bloco de 3 posts relacionados no final de cada single, entre o *
 * footer do artigo e a seção de comentários. Aumenta time-on-site e reduz    *
 * bounce rate — métrica SEO importante.                                       *
 *                                                                              *
 * Algoritmo (busca 2-estágios):                                                *
 *   1. Stage 1: busca até 10 candidatos da mesma categoria primária do post   *
 *      (via meta `_rd_primary_category`, fallback pra primeira categoria).    *
 *      Excluí o próprio post + somente status `publish`.                      *
 *   2. Stage 2: pra cada candidato, conta tags em comum com o post atual e    *
 *      ordena por overlap DESC (mais relevante primeiro). Sort estável        *
 *      preserva ordem por data DESC do query original.                        *
 *   3. Retorna top 3 IDs.                                                     *
 *                                                                              *
 * Cache: transient `rd_related_{post_id}` com TTL 1h. Invalidado em save_post *
 * do próprio post — outros posts esperam o TTL natural pra refresh.           *
 *                                                                              *
 * Gate: feature controlada por `enable_related_posts` (default ON). Quando    *
 * OFF, função de render retorna early — zero overhead.                        *
 *******************************************************************************/

/**
 * Retorna array de IDs de posts relacionados (cacheado).
 *
 * @param int $post_id ID do post de referência (geralmente get_the_ID()).
 * @param int $limit   Quantidade máxima de relacionados (default 3).
 * @return int[] Array de IDs ordenado por relevância (overlap de tags DESC, depois data DESC).
 */
function rd_get_related_posts( $post_id, $limit = 3 ) {
	$post_id = (int) $post_id;
	$limit   = max( 1, (int) $limit );

	$cache_key = 'rd_related_' . $post_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : array();
	}

	// 1. Resolve categoria primária — fallback pra primeira categoria do post
	$primary_cat_id = (int) get_post_meta( $post_id, '_rd_primary_category', true );
	if ( ! $primary_cat_id ) {
		$cats = get_the_category( $post_id );
		if ( ! empty( $cats ) && isset( $cats[0]->term_id ) ) {
			$primary_cat_id = (int) $cats[0]->term_id;
		}
	}

	if ( ! $primary_cat_id ) {
		// Post sem nenhuma categoria — sem dados pra algoritmo
		set_transient( $cache_key, array(), HOUR_IN_SECONDS );
		return array();
	}

	// 2. Stage 1: pega até 10 candidatos da mesma categoria, ordem por data DESC
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__not_in'        => array( $post_id ),
			'posts_per_page'      => 10,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'cat'                 => $primary_cat_id,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'fields'              => 'ids',
		)
	);

	if ( empty( $query->posts ) ) {
		set_transient( $cache_key, array(), HOUR_IN_SECONDS );
		return array();
	}

	// 3. Stage 2: conta tag overlap pra cada candidato e ordena
	$source_tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
	$with_score  = array();
	foreach ( $query->posts as $candidate_id ) {
		$candidate_tags = wp_get_post_tags( $candidate_id, array( 'fields' => 'ids' ) );
		$overlap        = ( ! empty( $source_tags ) && ! empty( $candidate_tags ) )
			? count( array_intersect( $source_tags, $candidate_tags ) )
			: 0;
		$with_score[]   = array(
			'id'      => $candidate_id,
			'overlap' => $overlap,
		);
	}

	// usort estável (PHP 8+) — preserva ordem por data DESC quando overlap empata
	usort(
		$with_score,
		function ( $a, $b ) {
			return $b['overlap'] - $a['overlap'];
		}
	);

	$ids = array_slice( array_column( $with_score, 'id' ), 0, $limit );

	set_transient( $cache_key, $ids, HOUR_IN_SECONDS );
	return $ids;
}

/**
 * Renderiza o bloco de related posts no final do single. Sai cedo se feature
 * OFF, se post não tem categoria, ou se algoritmo não achou candidatos.
 *
 * Reusa rd_render_post_card('grid') do post-card.php existente — visual
 * idêntico aos cards do search e home, zero CSS novo por card.
 *
 * @param int $post_id ID do post atual.
 */
function rd_render_related_posts( $post_id ) {
	if ( ! rd_get_option_bool( 'enable_related_posts', true ) ) {
		return;
	}

	$ids = rd_get_related_posts( $post_id, 3 );
	if ( empty( $ids ) ) {
		return;
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in', // preserva ordem do algoritmo
			'posts_per_page'      => count( $ids ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	if ( ! $query->have_posts() ) {
		return;
	}

	echo '<aside class="rd-related-posts" aria-labelledby="rd-related-posts-title">';
	echo '<h2 id="rd-related-posts-title" class="rd-related-posts__title">' . esc_html__( 'Related posts', 'reloaded' ) . '</h2>';
	// Wrapper combina:
	// - .rd-wrapper-grid → ativa todos os estilos de .rd-search-card.layout-grid
	// (background glass, border, aspect-ratio 16/9 da thumb, hover, ellipsis)
	// - .rd-related-posts__grid → override do grid-template-columns pra
	// EXATAMENTE 3/2/1 cols (o default do .rd-wrapper-grid é auto-fill, que
	// deixaria células vazias num container largo com só 3 cards).
	echo '<div class="rd-wrapper-grid rd-related-posts__grid">';

	while ( $query->have_posts() ) {
		$query->the_post();
		rd_render_post_card( 'grid' );
	}

	echo '</div>';
	echo '</aside>';

	wp_reset_postdata();
}

/**
 * Invalida o cache de related do post quando ele é salvo.
 * Não invalida cache de OUTROS posts que possam referenciar este — o TTL de 1h
 * cobre eventual consistency (custo: até 1h de delay pra reflect changes em
 * related de posts terceiros).
 *
 * @param int $post_id ID do post salvo.
 */
function rd_related_posts_invalidate_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	delete_transient( 'rd_related_' . (int) $post_id );
}
add_action( 'save_post', 'rd_related_posts_invalidate_cache' );
