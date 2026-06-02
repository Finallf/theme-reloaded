<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Menu - Highlight behavior of the nav menu                          *
 *                                                                            *
 * Solves the "multiple highlighted items" problem: when a post has           *
 * more than one category, WordPress adds `current-menu-*` to all the         *
 * matching menu items. Here we filter to keep only the primary               *
 * category (chosen in the editor via the `_rd_primary_category` meta box) and*
 * its menu ancestors as "current".                                           *
 *******************************************************************************/

/**
 * Returns the post's primary category ID.
 *
 * Cascade:
 *   1. Meta `_rd_primary_category` (author's choice) — if the category still
 *      belongs to the post
 *   2. First category via get_the_category() (WP's default ID order)
 *
 * @param int $post_id
 * @return int 0 if the post has no categories
 */
function rd_get_primary_category_id( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return 0;
	}

	$cats = get_the_category( $post_id );
	if ( empty( $cats ) ) {
		return 0;
	}

	$chosen = (int) get_post_meta( $post_id, '_rd_primary_category', true );

	if ( $chosen ) {
		// Confirm the chosen category is still on the post
		foreach ( $cats as $cat ) {
			if ( (int) $cat->term_id === $chosen ) {
				return $chosen;
			}
		}
		// Stale choice — fall back to the first
	}

	return (int) $cats[0]->term_id;
}

/**
 * Filters the menu objects before rendering to keep only the primary
 * category (and its menu ancestors) with `current-*` classes. Other items
 * pointing to non-primary categories lose the highlight.
 *
 * Uses `wp_nav_menu_objects` (instead of `nav_menu_css_class`) because it needs
 * the full tree to compute ancestors.
 *
 * @param array  $items List of menu objects (WP_Post-like)
 * @param object $args  wp_nav_menu arguments
 * @return array
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is part of the `wp_nav_menu_objects` filter signature, even when we don't consult the menu args.
function rd_filter_menu_primary_category( $items, $args ) {
	// Main gate — the option disabled in the panel turns off the whole feature
	if ( ! rd_get_option_bool( 'enable_primary_category' ) ) {
		return $items;
	}

	// Only makes sense on a single post
	if ( ! is_singular( 'post' ) ) {
		return $items;
	}

	$primary_cat_id = rd_get_primary_category_id( get_the_ID() );
	if ( ! $primary_cat_id ) {
		return $items;
	}

	// 1) Find the menu item that points to the primary category
	$primary_item_id = 0;
	foreach ( $items as $item ) {
		if ( $item->object === 'category' && (int) $item->object_id === $primary_cat_id ) {
			$primary_item_id = (int) $item->ID;
			break;
		}
	}

	if ( ! $primary_item_id ) {
		// The primary category isn't in the menu — don't filter (let WP highlight
		// whatever it can, something is better than nothing)
		return $items;
	}

	// 2) Build the "keep" set: primary category item + ancestors
	// in the menu (climbing via menu_item_parent)
	$keep_ids    = array( $primary_item_id );
	$items_by_id = array();
	foreach ( $items as $item ) {
		$items_by_id[ (int) $item->ID ] = $item;
	}

	$current_id = $primary_item_id;
	while ( true ) {
		$current = $items_by_id[ $current_id ] ?? null;
		if ( ! $current ) {
			break;
		}
		$parent_id = (int) $current->menu_item_parent;
		if ( ! $parent_id || isset( $keep_ids_lookup[ $parent_id ] ) ) {
			break;
		}
		$keep_ids[] = $parent_id;
		$current_id = $parent_id;
	}
	$keep_ids_lookup = array_flip( $keep_ids );

	// 3) For each item NOT in the keep set, remove `current-*` classes (and the
	// underscore variants that some WP versions / themes use)
	foreach ( $items as &$item ) {
		if ( isset( $keep_ids_lookup[ (int) $item->ID ] ) ) {
			continue;
		}
		if ( ! is_array( $item->classes ) ) {
			continue;
		}
		$item->classes = array_filter(
			$item->classes,
			function ( $css_class ) {
				return strpos( $css_class, 'current-' ) !== 0
				&& strpos( $css_class, 'current_' ) !== 0;
			}
		);
		// array_filter preserves keys; reset so WP renders cleanly
		$item->classes = array_values( $item->classes );
	}
	unset( $item ); // good practice after a foreach by reference

	return $items;
}
add_filter( 'wp_nav_menu_objects', 'rd_filter_menu_primary_category', 20, 2 );
