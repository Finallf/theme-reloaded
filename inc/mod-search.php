<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Search Module — functions specific to the search context.                    *
 * The card renderer lives in inc/post-card.php (generic, reusable by           *
 * archive, author, widgets etc.).                                              *
 *                                                                              *
 * Distribution system (Phase 5.5):                                             *
 * Instead of duplicating the results in each layout, it distributes posts      *
 * sequentially across the active layouts (6 per layout, order grid →           *
 * vertical → compact → google). Compact acts as the overflow bucket and        *
 * safety net.                                                                  *
 *******************************************************************************/

const RD_SEARCH_LAYOUT_CAP   = 6;
const RD_SEARCH_LAYOUT_ORDER = array( 'grid', 'vertical', 'compact', 'google' );

/**
 * Applies a highlight (`<mark>`) to the searched term within the text.
 * Ignores stretches that are inside HTML tags or entities.
 *
 * Consumed by the rd_post_card_text() helper in inc/post-card.php
 * when is_search() is true.
 */
function rd_search_highlight( string $text ) {
	$query = get_search_query();
	if ( empty( $query ) ) {
		return $text;
	}

	$termos  = explode( ' ', preg_quote( $query, '/' ) );
	$pattern = '/(?![^<]+>)(?![^&;]+;)(' . implode( '|', $termos ) . ')/iu';

	return preg_replace( $pattern, '<mark class="rd-highlight">$1</mark>', $text );
}

/**
 * Returns the list of layouts enabled by the admin in the panel.
 * Fallback: ['compact'] if none is active — consistent with
 * compact's behavior as a safety net in all the other rules.
 *
 * @return array<int, string>
 */
function rd_search_get_admin_active_layouts(): array {
	$layouts = array(
		'grid'     => rd_get_option_bool( 'search_layout_grid' ),
		'vertical' => rd_get_option_bool( 'search_layout_vertical' ),
		'compact'  => rd_get_option_bool( 'search_layout_compact' ),
		'google'   => rd_get_option_bool( 'search_layout_google' ),
	);
	$active  = array_keys( array_filter( $layouts ) );
	return ! empty( $active ) ? $active : array( 'compact' );
}

/**
 * Distributes posts across the active layouts following rules (in order):
 *
 *   1. Empty posts → empty distribution (search.php handles "no results")
 *   2. Visitor has only 1 active layout → it gets EVERYTHING (respects preference)
 *   3. Visitor disabled everything → compact gets everything (catastrophic safety net)
 *   4. Few results (< CAP) with multiple active layouts → compact
 *      (clean visuals — not worth distributing so few)
 *   5. Normal distribution: grid → vertical → compact → google, CAP per layout
 *   6. Overflow → goes to the LAST ACTIVE LAYOUT (in fixed order), respecting
 *      the visitor's preference instead of forcing compact
 *
 * @param array<int, \WP_Post> $posts
 * @param array<int, string>   $effective Effective layouts (admin ∩ visitor)
 * @return array<string, array<int, \WP_Post>>
 */
function rd_search_distribute_posts( array $posts, array $effective ): array {
	$cap          = RD_SEARCH_LAYOUT_CAP;
	$distribution = array();

	// Rule 1: empty
	if ( empty( $posts ) ) {
		return $distribution;
	}

	// Rule 2: only 1 active layout → respects it 100% (receives everything)
	if ( count( $effective ) === 1 ) {
		$distribution[ $effective[0] ] = $posts;
		return $distribution;
	}

	// Rule 3: no active layout → compact safety net
	if ( empty( $effective ) ) {
		$distribution['compact'] = $posts;
		return $distribution;
	}

	// Rule 4: few results with multiple active → compact (clean visuals)
	if ( count( $posts ) < $cap ) {
		$distribution['compact'] = $posts;
		return $distribution;
	}

	// Rule 5: normal distribution
	$remaining   = $posts;
	$last_active = null;

	foreach ( RD_SEARCH_LAYOUT_ORDER as $layout ) {
		if ( empty( $remaining ) ) {
			break;
		}

		if ( in_array( $layout, $effective, true ) ) {
			$distribution[ $layout ] = array_slice( $remaining, 0, $cap );
			$remaining               = array_slice( $remaining, $cap );
			$last_active             = $layout;
		}
	}

	// Rule 6: overflow → last active layout (preserves the visitor's preference)
	if ( ! empty( $remaining ) && $last_active !== null ) {
		$distribution[ $last_active ] = array_merge( $distribution[ $last_active ], $remaining );
	}

	return $distribution;
}

/**
 * Adjusts the search main query's posts_per_page to have enough posts
 * to distribute across the active layouts.
 *
 * On initial load we don't know the visitor's preference (it lives in
 * client localStorage), so we use the admin config. The JS fires
 * AJAX afterward to re-render if the visitor has different toggles.
 */
function rd_search_modify_query( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_search() ) {
		return;
	}

	$admin_active = rd_search_get_admin_active_layouts();
	$query->set( 'posts_per_page', count( $admin_active ) * RD_SEARCH_LAYOUT_CAP );
}
add_action( 'pre_get_posts', 'rd_search_modify_query' );

/**
 * Enqueues the layout-control JS + its AJAX data.
 * Loads only on the search page — the script used to live inside the global
 * navigation.js, shipping ~7 KiB of dead code to every other page.
 */
function rd_search_localize_script() {
	if ( ! is_search() ) {
		return;
	}

	wp_enqueue_script(
		'rd-search-layout',
		get_template_directory_uri() . '/assets/js/search-layout.js',
		array(),
		rd_asset_version( '/assets/js/search-layout.js' ),
		true
	);

	wp_localize_script(
		'rd-search-layout',
		'rd_search_data',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rd_search_redistribute' ),
			'query'   => get_search_query(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rd_search_localize_script', 20 );

/**
 * AJAX handler that receives the visitor's active layouts, redoes the query,
 * redistributes, and returns the HTML of the inner wrappers of
 * .rd-search-results-containers.
 *
 * Posts_per_page is fixed based on the admin (doesn't change when the visitor toggles)
 * so pagination stays consistent across all scenarios.
 */
function rd_ajax_search_redistribute() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'rd_search_redistribute' ) ) {
		wp_send_json_error( array( 'reason' => 'invalid_nonce' ), 400 );
	}

	$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
	$active_csv   = isset( $_POST['active_layouts'] ) ? sanitize_text_field( wp_unslash( $_POST['active_layouts'] ) ) : '';
	$paged        = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

	// Parse + validate requested layouts
	$requested = $active_csv ? explode( ',', $active_csv ) : array();
	$requested = array_intersect( $requested, RD_SEARCH_LAYOUT_ORDER );

	// Effective = admin enabled ∩ visitor requested
	$admin_active = rd_search_get_admin_active_layouts();
	$effective    = array_values( array_intersect( $admin_active, $requested ) );

	// CONSISTENT posts_per_page (based on admin, not on effective)
	// — pagination keeps its structure even when the visitor toggles
	$posts_per_page = count( $admin_active ) * RD_SEARCH_LAYOUT_CAP;

	$wp_query = new \WP_Query(
		array(
			's'              => $search_query,
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'post_status'    => 'publish',
		)
	);

	$distribution = rd_search_distribute_posts( $wp_query->posts, $effective );

	ob_start();
	if ( ! empty( $wp_query->posts ) ) {
		rd_render_distribution( $distribution );
	}
	$html = ob_get_clean();

	wp_reset_postdata();

	wp_send_json_success(
		array(
			'html'         => $html,
			'has_posts'    => ! empty( $wp_query->posts ),
			'max_pages'    => (int) $wp_query->max_num_pages,
			'current_page' => $paged,
			'total_posts'  => (int) $wp_query->found_posts,
		)
	);
}
add_action( 'wp_ajax_rd_search_redistribute', 'rd_ajax_search_redistribute' );
add_action( 'wp_ajax_nopriv_rd_search_redistribute', 'rd_ajax_search_redistribute' );
