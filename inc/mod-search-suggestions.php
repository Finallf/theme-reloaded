<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Search Suggestions / Autocomplete                                    *
 *                                                                              *
 * AJAX endpoint that receives a partial query and returns up to 5 relevant     *
 * posts to show as a suggestions dropdown below the search inputs.             *
 *                                                                              *
 * Triggered by assets/js/search-suggestions.js (vanilla fetch, 250ms           *
 * debounce, minimum 3 chars). Covers 2 theme inputs:                           *
 *   - .search-field (expandable search of the desktop header)                  *
 *   - .menu-search-field (search inside the hamburger panel)                   *
 *                                                                              *
 * Cache: transient `rd_sugg_{md5(query)}` TTL 15min. Common queries repeat     *
 * a lot — caching makes the server cost negligible under high traffic.         *
 *                                                                              *
 * Gate: feature controlled by `enable_search_suggestions` (default ON).        *
 *******************************************************************************/

const RD_SUGG_MIN_CHARS    = 3;
const RD_SUGG_MAX_CHARS    = 50;
const RD_SUGG_LIMIT        = 5;
const RD_SUGG_CACHE_TTL    = 15 * MINUTE_IN_SECONDS;
const RD_SUGG_CACHE_PREFIX = 'rd_sugg_';

/**
 * AJAX handler — returns JSON with up to 5 relevant posts for the query.
 * Available for anonymous + logged-in users (nopriv + priv).
 */
function rd_search_suggestions_ajax() {
	// Feature gate
	if ( ! rd_get_option_bool( 'enable_search_suggestions', true ) ) {
		wp_send_json_success( array( 'results' => array() ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public endpoint for autocomplete; no destructive side effect. The result is already cached per query.
	$raw_query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$raw_query = trim( $raw_query );

	$len = mb_strlen( $raw_query );
	if ( $len < RD_SUGG_MIN_CHARS ) {
		wp_send_json_success( array( 'results' => array() ) );
	}
	if ( $len > RD_SUGG_MAX_CHARS ) {
		$raw_query = mb_substr( $raw_query, 0, RD_SUGG_MAX_CHARS );
	}

	// Cache hit?
	$cache_key = RD_SUGG_CACHE_PREFIX . md5( mb_strtolower( $raw_query ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		wp_send_json_success( array( 'results' => $cached ) );
	}

	// Native WP_Query with s= — WP's relevance ordering is reasonable for a
	// medium-sized portal. For Reuters-scale, you'd integrate Algolia/Meili.
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => RD_SUGG_LIMIT,
			's'                   => $raw_query,
			'orderby'             => 'relevance',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	$results = array();
	foreach ( $query->posts as $post ) {
		$thumb_url = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$thumb_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'rd-micro' );
			if ( $thumb_src && isset( $thumb_src[0] ) ) {
				$thumb_url = $thumb_src[0];
			}
		}

		$results[] = array(
			'title' => get_the_title( $post ),
			'url'   => get_permalink( $post ),
			'thumb' => $thumb_url,
		);
	}

	wp_reset_postdata();

	set_transient( $cache_key, $results, RD_SUGG_CACHE_TTL );

	wp_send_json_success( array( 'results' => $results ) );
}
add_action( 'wp_ajax_rd_search_suggestions', 'rd_search_suggestions_ajax' );
add_action( 'wp_ajax_nopriv_rd_search_suggestions', 'rd_search_suggestions_ajax' );

/**
 * Enqueue the JS — frontend only, outside the admin.
 */
function rd_search_suggestions_enqueue() {
	if ( is_admin() ) {
		return;
	}
	if ( ! rd_get_option_bool( 'enable_search_suggestions', true ) ) {
		return;
	}

	wp_enqueue_script(
		'rd-search-suggestions',
		get_template_directory_uri() . '/assets/js/search-suggestions.js',
		array(),
		rd_asset_version( '/assets/js/search-suggestions.js' ),
		true
	);

	// i18n strings + data for the JS
	wp_localize_script(
		'rd-search-suggestions',
		'rdSearchSugg',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'searchUrl'  => home_url( '/?s=' ),
			'minChars'   => RD_SUGG_MIN_CHARS,
			'debounceMs' => 250,
			'i18n'       => array(
				'noResults' => __( 'No matches for', 'reloaded' ),
				'seeAll'    => __( 'See all results for', 'reloaded' ),
				'loading'   => __( 'Searching…', 'reloaded' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rd_search_suggestions_enqueue' );

/**
 * Clears the suggestions cache when a post is saved/edited/deleted.
 * Doesn't try to be surgical (which query changed) — deletes the WHOLE prefix.
 * Under high traffic with Redis Object Cache, this is trivial.
 */
function rd_search_suggestions_flush_cache() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- batch delete of transients by prefix: there's no equivalent WP API; sparse queries on save_post don't affect performance.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_' . $wpdb->esc_like( RD_SUGG_CACHE_PREFIX ) . '%',
			'_transient_timeout_' . $wpdb->esc_like( RD_SUGG_CACHE_PREFIX ) . '%'
		)
	);
}
add_action( 'save_post', 'rd_search_suggestions_flush_cache' );
add_action( 'deleted_post', 'rd_search_suggestions_flush_cache' );
