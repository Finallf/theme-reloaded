<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Breadcrumbs — Contextual navigation trail + JSON-LD                 *
 *                                                                             *
 * Computes the "trail" (array of [name, url]) once and feeds TWO              *
 * consumers from the same source:                                             *
 *                                                                             *
 *   1. `rd_render_breadcrumbs()`  — visible HTML markup, called from header.php
 *      right after the <header> closes. Gated by the `enable_breadcrumbs` toggle.
 *                                                                             *
 *   2. `rd_add_breadcrumb_jsonld()` — Schema.org BreadcrumbList in <head>,    *
 *      always active (SEO baseline, independent of the visual toggle).        *
 *                                                                             *
 * The SCSS decides the appearance (`components/_breadcrumbs.scss`). This      *
 * file only handles logic + semantic markup.                                  *
 *******************************************************************************/

/*******************************************************************************
 * Computes the breadcrumb's contextual trail                  - (Breadcrumbs) *
 *                                                                             *
 * Returns an array of items `['name' => string, 'url' => string|null]`. The   *
 * last item represents the current page and comes with `url = null` by convention.
 *                                                                             *
 * Returns an empty array on the front page (a breadcrumb on home makes no sense).
 *******************************************************************************/
function rd_get_breadcrumb_trail(): array {
	// Front page: no breadcrumb (you're already at the root)
	if ( is_front_page() ) {
		return array();
	}

	$trail   = array();
	$trail[] = array(
		'name' => __( 'Home', 'reloaded' ),
		'url'  => home_url( '/' ),
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $trail;
		}

		// For posts (not pages), try to insert the primary category as the
		// parent. Reuses the `_rd_primary_category` meta when the admin chose
		// explicitly; falls back to the first assigned category.
		if ( is_single() && get_post_type( $post ) === 'post' ) {
			$cats = get_the_category( $post->ID );
			$cat  = null;

			if ( ! empty( $cats ) ) {
				$primary_id = (int) get_post_meta( $post->ID, '_rd_primary_category', true );
				if ( $primary_id ) {
					foreach ( $cats as $c ) {
						if ( (int) $c->term_id === $primary_id ) {
							$cat = $c;
							break;
						}
					}
				}
				if ( ! $cat ) {
					$cat = $cats[0];
				}
			}

			if ( $cat ) {
				$trail[] = array(
					'name' => $cat->name,
					'url'  => get_category_link( $cat->term_id ),
				);
			}
		}

		$trail[] = array(
			'name' => get_the_title( $post ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_category() ) {
		$term = get_queried_object();
		// Include ancestors (nested cats) in root → leaf order
		$ancestors = array_reverse( get_ancestors( $term->term_id, 'category' ) );
		foreach ( $ancestors as $aid ) {
			$a = get_term( $aid, 'category' );
			if ( $a && ! is_wp_error( $a ) ) {
				$trail[] = array(
					'name' => $a->name,
					'url'  => get_category_link( $a ),
				);
			}
		}
		$trail[] = array(
			'name' => $term->name,
			'url'  => null,
		);
		return $trail;
	}

	if ( is_tag() ) {
		$term    = get_queried_object();
		$trail[] = array(
			/* translators: %s: tag name */
			'name' => sprintf( __( 'Tag: %s', 'reloaded' ), $term->name ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_tax() ) {
		$term    = get_queried_object();
		$trail[] = array(
			'name' => $term->name,
			'url'  => null,
		);
		return $trail;
	}

	if ( is_author() ) {
		$author  = get_queried_object();
		$trail[] = array(
			/* translators: %s: author display name */
			'name' => sprintf( __( 'Author: %s', 'reloaded' ), $author->display_name ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_day() ) {
		$year     = (int) get_query_var( 'year' );
		$monthnum = (int) get_query_var( 'monthnum' );
		$day      = (int) get_query_var( 'day' );
		$ts       = mktime( 0, 0, 0, $monthnum, $day, $year );
		$trail[]  = array(
			'name' => (string) $year,
			'url'  => get_year_link( $year ),
		);
		$trail[]  = array(
			'name' => date_i18n( 'F', $ts ),
			'url'  => get_month_link( $year, $monthnum ),
		);
		$trail[]  = array(
			'name' => date_i18n( get_option( 'date_format' ), $ts ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_month() ) {
		$year     = (int) get_query_var( 'year' );
		$monthnum = (int) get_query_var( 'monthnum' );
		$ts       = mktime( 0, 0, 0, $monthnum, 1, $year );
		$trail[]  = array(
			'name' => (string) $year,
			'url'  => get_year_link( $year ),
		);
		$trail[]  = array(
			'name' => date_i18n( 'F', $ts ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_year() ) {
		$trail[] = array(
			'name' => (string) get_query_var( 'year' ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_search() ) {
		$trail[] = array(
			/* translators: %s: search query */
			'name' => sprintf( __( 'Search results for: %s', 'reloaded' ), get_search_query() ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_404() ) {
		$trail[] = array(
			'name' => __( 'Page not found', 'reloaded' ),
			'url'  => null,
		);
		return $trail;
	}

	return $trail;
}

/*******************************************************************************
 * Renders the breadcrumb's visible markup                     - (Breadcrumbs) *
 *                                                                             *
 * Called from header.php right after </header>. Semantic markup (<nav> +      *
 * <ol>) with `aria-label` and `aria-current="page"` on the current item.      *
 *******************************************************************************/
function rd_render_breadcrumbs(): void {
	if ( ! rd_get_option_bool( 'enable_breadcrumbs' ) ) {
		return;
	}

	$trail = rd_get_breadcrumb_trail();
	if ( empty( $trail ) ) {
		return;
	}

	$count = count( $trail );

	echo '<nav class="rd-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'reloaded' ) . '">';
	echo '<div class="container">';
	echo '<ol class="rd-breadcrumbs-list">';

	foreach ( $trail as $i => $crumb ) {
		$is_last = ( $i === $count - 1 );
		echo '<li class="rd-breadcrumbs-item">';
		if ( ! $is_last && ! empty( $crumb['url'] ) ) {
			echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
		} else {
			echo '<span aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
		}
		echo '</li>';
	}

	echo '</ol>';
	echo '</div>';
	echo '</nav>';
}

/*******************************************************************************
 * BreadcrumbList Schema.org JSON-LD                           - (Breadcrumbs) *
 *                                                                             *
 * Emits the BreadcrumbList in <wp_head>, independent of the visual toggle.    *
 * Doesn't generate on search/404 (Google doesn't want a breadcrumb for        *
 * non-indexable pages) nor when the trail has fewer than 2 items (Home alone  *
 * isn't a breadcrumb).                                                        *
 *                                                                             *
 * The last item (current page) omits the `item` key by convention — it tells  *
 * Google that's the current position without needing a duplicate URL.         *
 *******************************************************************************/
function rd_add_breadcrumb_jsonld(): void {
	if ( is_search() || is_404() ) {
		return;
	}

	$trail = rd_get_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return;
	}

	$items = array();
	foreach ( $trail as $i => $crumb ) {
		$entry = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $crumb['name'],
		);
		if ( ! empty( $crumb['url'] ) ) {
			$entry['item'] = $crumb['url'];
		}
		$items[] = $entry;
	}

	rd_seo_print_jsonld(
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		)
	);
}
add_action( 'wp_head', 'rd_add_breadcrumb_jsonld', 6 );
