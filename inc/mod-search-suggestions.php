<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Search Suggestions / Autocomplete                                    *
 *                                                                              *
 * AJAX endpoint que recebe uma query parcial e retorna até 5 posts relevantes *
 * pra mostrar como dropdown de sugestões abaixo dos search inputs.            *
 *                                                                              *
 * Triggered por assets/js/search-suggestions.js (vanilla fetch, debounce      *
 * 250ms, mínimo 3 chars). Cobre 2 inputs do tema:                             *
 *   - .search-field (busca expansível do header desktop)                      *
 *   - .menu-search-field (busca dentro do painel hambúrguer)                  *
 *                                                                              *
 * Cache: transient `rd_sugg_{md5(query)}` TTL 15min. Queries comuns repetem   *
 * muito — cache torna custo de servidor desprezível em alto tráfego.          *
 *                                                                              *
 * Gate: feature controlada por `enable_search_suggestions` (default ON).      *
 *******************************************************************************/

const RD_SUGG_MIN_CHARS    = 3;
const RD_SUGG_MAX_CHARS    = 50;
const RD_SUGG_LIMIT        = 5;
const RD_SUGG_CACHE_TTL    = 15 * MINUTE_IN_SECONDS;
const RD_SUGG_CACHE_PREFIX = 'rd_sugg_';

/**
 * AJAX handler — retorna JSON com até 5 posts relevantes pra query.
 * Disponível pra users anônimos + logados (nopriv + priv).
 */
function rd_search_suggestions_ajax() {
	// Feature gate
	if ( ! rd_get_option_bool( 'enable_search_suggestions', true ) ) {
		wp_send_json_success( array( 'results' => array() ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only endpoint público pra autocomplete; nenhum side effect destrutivo. Resultado já é cacheado por query.
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

	// WP_Query nativo com s= — relevance ordering do WP é razoável pra
	// portal de tamanho médio. Pra Reuters-scale, integraria Algolia/Meili.
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
 * Enqueue do JS — só no frontend, fora do admin.
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

	// Strings i18n + dados pro JS
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
 * Limpa o cache de sugestões quando um post é salvo/editado/deletado.
 * Não tenta ser cirúrgico (qual query mudou) — deleta TODO o prefixo.
 * Em alto tráfego com Redis Object Cache, isto é trivial.
 */
function rd_search_suggestions_flush_cache() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- batch delete de transients por prefixo: não há WP API equivalente; queries esparsas em save_post não afetam performance.
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
