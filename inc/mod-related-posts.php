<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Related Posts                                                        *
 *                                                                              *
 * Renders a block of 3 related posts at the end of each single, between the    *
 * article footer and the comments section. Increases time-on-site and reduces  *
 * bounce rate — an important SEO metric.                                       *
 *                                                                              *
 * Algorithm (2-stage search):                                                  *
 *   1. Stage 1: fetch up to 10 candidates from the post's primary category     *
 *      (via the `_rd_primary_category` meta, fallback to the first category).  *
 *      Excludes the post itself + only `publish` status.                       *
 *   2. Stage 2: for each candidate, count tags in common with the current      *
 *      post and order by overlap DESC (most relevant first). A stable sort     *
 *      preserves the original query's date DESC order.                         *
 *   3. Returns the top 3 IDs.                                                  *
 *                                                                              *
 * Cache: transient `rd_related_{post_id}` with TTL 1h. Invalidated on the      *
 * post's own save_post — other posts wait for the natural TTL to refresh.      *
 *                                                                              *
 * Gate: feature controlled by `enable_related_posts` (default ON). When        *
 * OFF, the render function returns early — zero overhead.                      *
 *******************************************************************************/

/**
 * Returns an array of related post IDs (cached).
 *
 * @param int $post_id ID of the reference post (usually get_the_ID()).
 * @param int $limit   Maximum number of related posts (default 3).
 * @return int[] Array of IDs ordered by relevance (tag overlap DESC, then date DESC).
 */
function rd_get_related_posts( $post_id, $limit = 3 ) {
	$post_id = (int) $post_id;
	$limit   = max( 1, (int) $limit );

	$cache_key = 'rd_related_' . $post_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : array();
	}

	// 1. Resolve the primary category — fallback to the post's first category
	$primary_cat_id = (int) get_post_meta( $post_id, '_rd_primary_category', true );
	if ( ! $primary_cat_id ) {
		$cats = get_the_category( $post_id );
		if ( ! empty( $cats ) && isset( $cats[0]->term_id ) ) {
			$primary_cat_id = (int) $cats[0]->term_id;
		}
	}

	if ( ! $primary_cat_id ) {
		// Post with no category — no data for the algorithm
		set_transient( $cache_key, array(), HOUR_IN_SECONDS );
		return array();
	}

	// 2. Stage 1: get up to 10 candidates from the same category, ordered by date DESC
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

	// 3. Stage 2: count tag overlap for each candidate and sort
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

	// stable usort (PHP 8+) — preserves date DESC order when overlap ties
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
 * Renders the related posts block at the end of the single. Exits early if the
 * feature is OFF, if the post has no category, or if the algorithm found no candidates.
 *
 * Reuses the existing rd_render_post_card('grid') from post-card.php — visuals
 * identical to the search and home cards, zero new CSS per card.
 *
 * @param int $post_id ID of the current post.
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
			'orderby'             => 'post__in', // preserves the algorithm's order
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
	// The wrapper combines:
	// - .rd-wrapper-grid → enables all the .rd-search-card.layout-grid styles
	// (glass background, border, 16/9 thumb aspect-ratio, hover, ellipsis)
	// - .rd-related-posts__grid → overrides grid-template-columns to
	// EXACTLY 3/2/1 cols (the .rd-wrapper-grid default is auto-fill, which
	// would leave empty cells in a wide container with only 3 cards).
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
 * Invalidates the post's related cache when it is saved.
 * Doesn't invalidate the cache of OTHER posts that may reference this one — the 1h TTL
 * covers eventual consistency (cost: up to 1h of delay to reflect changes in
 * third-party posts' related lists).
 *
 * @param int $post_id ID of the saved post.
 */
function rd_related_posts_invalidate_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	delete_transient( 'rd_related_' . (int) $post_id );
}
add_action( 'save_post', 'rd_related_posts_invalidate_cache' );
