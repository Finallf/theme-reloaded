<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Post Card — generic card renderer used by search, archive, author and any   *
 * other archive-style template. Search-specific concerns (such as highlighting *
 * the search query) layer on top via rd_search_highlight() and is_search().   *
 *******************************************************************************/

/**
 * Escapes the text and applies highlight on the search term when we are on
 * the search results page. In any other context, returns just the escaped
 * text.
 */
function rd_post_card_text( $text ) {
	$escaped = esc_html( $text );

	if ( is_search() && function_exists( 'rd_search_highlight' ) ) {
		return rd_search_highlight( $escaped );
	}

	return $escaped;
}

/**
 * Renders the formatted "views" block (icon + number) for the cards.
 * Returns an empty string when the views module is unavailable.
 */
function rd_get_formatted_views( $post_id ) {
	if ( ! function_exists( 'rd_get_post_views' ) ) {
		return '';
	}
	$views = rd_get_post_views( $post_id );
	// Frontend uses rd_format_views_number (respects the full/compact panel config).
	// Admin (mod-stats, posts column) uses number_format_i18n directly — always exact.
	return '<span class="rd-card-views" aria-label="' . esc_attr__( 'Views', 'reloaded' ) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . rd_format_views_number( $views ) . '</span>';
}

/**
 * Renders a search distribution block (internal wrappers).
 * Consumed by search.php (initial load) and by the AJAX handler
 * (rd_ajax_search_redistribute) — both produce the same HTML.
 *
 * Does NOT wrap with .rd-search-results-containers — the caller is
 * responsible for that outer (so AJAX can swap just the innerHTML).
 *
 * @param array<string, array<int, \WP_Post>> $distribution Output of rd_search_distribute_posts()
 */
function rd_render_distribution( array $distribution ) {
	global $post;

	foreach ( $distribution as $layout => $posts ) {
		if ( empty( $posts ) ) {
			continue;
		}

		echo '<div id="rd-wrap-' . esc_attr( $layout ) . '" class="rd-search-wrapper rd-wrapper-' . esc_attr( $layout ) . '">';

		foreach ( $posts as $p ) {
			$post = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
			rd_render_post_card( $layout );
		}

		echo '</div>';
	}

	wp_reset_postdata();
}

/**
 * Renders a post card in the chosen layout. Works inside the WordPress loop
 * (depends on get_the_ID(), get_the_title(), etc.).
 *
 * @param string $type 'grid' | 'vertical' | 'compact' | 'google'
 */
function rd_render_post_card( $type ) {
	global $wp_query;
	$post_id  = get_the_ID();
	$title    = rd_post_card_text( get_the_title() );
	$excerpt  = rd_post_card_text( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 20, '...' ) );
	$link     = get_the_permalink();
	$views    = rd_get_formatted_views( $post_id );
	$overline = function_exists( 'rd_get_post_overline_html' ) ? rd_get_post_overline_html( $post_id, 'card' ) : '';

	// The first 2 posts in the loop are LCP candidates. In 2-col layouts
	// (search grid, compact) they sit side-by-side above-the-fold; in 1-col
	// layouts (vertical, google) the 2nd one sits right below. Lighthouse may
	// pick either one as the metric — marking both eager + high priority
	// guarantees coverage. Cost: 1 extra eager image in single-col layouts
	// (minimal overhead).
	$is_lcp_candidate = isset( $wp_query->current_post ) && $wp_query->current_post < 2;
	$thumb_attrs      = $is_lcp_candidate
		? array(
			'loading'       => 'eager',
			'fetchpriority' => 'high',
		)
		: array( 'loading' => 'lazy' );

	// Per-layout `sizes` hint. The global rd_calculate_card_sizes filter
	// (mod-performance.php) describes the index post-grid (~461px columns),
	// but these card layouts have their own geometry — without the override,
	// desktop browsers picked 600w for ~300px slots (PageSpeed flagged ~60
	// KiB of waste). Both wrappers are multi-column grids whose cards land at
	// ~280-340px on desktop (grid: auto-fill minmax(280px,1fr); vertical:
	// measured 280-304px in the 2026-06-12 audit — the earlier 600px guess
	// overshot and made browsers grab 600w for ~300px slots).
	// Compact/google use other thumbnail sizes.
	$layout_sizes = array(
		'grid'     => '(min-width: 769px) 320px, 100vw',
		'vertical' => '(min-width: 769px) 320px, 100vw',
	);
	if ( isset( $layout_sizes[ $type ] ) ) {
		$thumb_attrs['sizes'] = $layout_sizes[ $type ];
	}

	// Empty Media Library alt → post title (see rd_thumb_alt_fallback).
	$thumb_attrs = rd_thumb_alt_fallback( $post_id, $thumb_attrs );

	// rd-card (600x338 hardcrop, 16:9) is the size designed for these cards —
	// WP's 'medium' (300px soft crop) was a leftover: too small for the
	// ~460-700px slots, and when an attachment lacked the medium file WP fell
	// back to serving the ORIGINAL (PageSpeed flagged 1365px-wide originals on
	// home cards). Falls back to 'medium' when custom sizes aren't registered.
	$thumb_size = rd_get_option_bool( 'image_resizing' ) ? 'rd-card' : 'medium';

	// Image fallback.
	$thumb = has_post_thumbnail() ? get_the_post_thumbnail( $post_id, $thumb_size, $thumb_attrs ) : '<div class="rd-thumb-fallback"></div>';

	// $title, $excerpt come from rd_post_card_text() (which applies esc_html + optional
	// search <mark> via regex that skips tags/entities). $overline comes from
	// rd_get_post_overline_html() (esc_attr + esc_html internally). $views comes from
	// rd_get_formatted_views() (HTML pre-escaped in mod-views.php). $thumb/$thumb_small
	// come from get_the_post_thumbnail() (WP). All pre-escaped; PHPCS does not track
	// through the wrappers.
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	switch ( $type ) {
		case 'grid':
			echo '<article class="rd-search-card layout-grid">';
			echo '<a href="' . esc_url( $link ) . '" class="rd-card-link">';
			echo '<div class="rd-card-thumb">' . $thumb . '</div>';
			echo '<div class="rd-card-body">';
			echo $overline; // empty string if feature off or post has no overline
			echo '<h3 class="rd-card-title">' . $title . '</h3>';
			echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
			echo '<div class="rd-card-meta">' . $views . '</div>';
			echo '</div></a></article>';
			break;

		case 'vertical':
			// Whole card wrapped in <a> so the entire card is clickable (same strategy as grid/compact).
			// HTML5 allows <a> wrapping flow content (block-level); only nested <a> is forbidden.
			echo '<article class="rd-search-card layout-vertical">';
			echo '<a href="' . esc_url( $link ) . '" class="rd-card-link">';
			echo '<div class="rd-card-thumb">' . $thumb . '</div>';
			echo '<div class="rd-card-body">';
			echo $overline; // empty string if feature off or post has no overline
			echo '<h3 class="rd-card-title">' . $title . '</h3>';
			echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
			echo '<div class="rd-card-meta">' . $views . '</div>';
			echo '</div></a></article>';
			break;

		case 'compact':
			// rd-micro (150x84, 16:9) instead of WP's square 'thumbnail' (150x150):
			// matches the compact box's aspect-ratio — no wasted square crop.
			$thumb_small = has_post_thumbnail() ? get_the_post_thumbnail( $post_id, 'rd-micro', $thumb_attrs ) : '<div class="rd-thumb-fallback-small"></div>';
			echo '<article class="rd-search-card layout-compact">';
			echo '<a href="' . esc_url( $link ) . '" class="rd-card-link-flex">';
			echo '<div class="rd-card-thumb-small">' . $thumb_small . '</div>';
			echo '<div class="rd-card-data">';
			echo '<h3 class="rd-card-title">' . $title . '</h3>';
			echo '<div class="rd-card-meta-compact">';
			echo '<span class="rd-card-date">' . get_the_date() . '</span>';
			echo $views;
			echo '</div>';
			echo '</div>';
			echo '</a></article>';
			break;

		case 'google':
			echo '<article class="rd-search-card layout-google">';
			echo '<div class="rd-card-url">' . esc_url( $link ) . '</div>';
			echo '<h3 class="rd-card-title"><a href="' . esc_url( $link ) . '">' . $title . '</a></h3>';
			echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
			echo '</article>';
			break;
	}
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}
