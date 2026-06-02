<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Views - Per-post view counting system                               *
 *                                                                             *
 * Features:                                                                   *
 * - Tracking via AJAX (escapes page caching)                                  *
 * - Per-IP deduplication (1 view every 30min per IP)                          *
 * - Known-bot filtering via User-Agent                                        *
 * - Time windows: all-time, month, week, day                                  *
 * - Public API: rd_get_post_views() / rd_get_popular_posts()                  *
 *******************************************************************************/

const RD_VIEWS_META_KEY      = '_rd_post_views';
const RD_VIEWS_META_KEY_LOG  = '_rd_post_views_log';
const RD_VIEWS_DEDUP_WINDOW  = 1800;  // 30 minutes
const RD_VIEWS_LOG_RETENTION = 31536000; // 1 year (cleans up old entries)

/**
 * Hook: enqueues the tracking script on singular pages
 */
function rd_views_enqueue_tracker() {
	if ( ! is_singular( array( 'post', 'page' ) ) ) {
		return;
	}

	if ( ! rd_get_option_bool( 'enable_views_tracking' ) ) {
		return;
	}

	// Don't track admins (avoids inflating the count during editing)
	if ( current_user_can( 'edit_posts' ) ) {
		return;
	}

	$post_id = get_the_ID();

	// SoC: static JS in assets/js/views-tracker.js, dynamic data
	// (post_id, nonce, ajaxurl) injected via wp_localize_script. The browser
	// caches the .js between requests; only the localize data is unique per page.
	wp_enqueue_script(
		'rd-views-tracker',
		get_template_directory_uri() . '/assets/js/views-tracker.js',
		array(),
		rd_asset_version( '/assets/js/views-tracker.js' ),
		true
	);

	wp_localize_script(
		'rd-views-tracker',
		'rd_views_data',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'post_id' => $post_id,
			'nonce'   => wp_create_nonce( 'rd_track_view_' . $post_id ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rd_views_enqueue_tracker' );

/**
 * AJAX endpoint: records a view (accessible by non-logged-in users)
 */
function rd_views_ajax_track() {
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	// Basic validation
	if ( ! $post_id || ! wp_verify_nonce( $nonce, 'rd_track_view_' . $post_id ) ) {
		wp_send_json_error( 'invalid', 400 );
	}

	if ( get_post_status( $post_id ) !== 'publish' ) {
		wp_send_json_error( 'not_published', 400 );
	}

	// Blocks admins / editors on the backend too (defense in depth)
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		wp_send_json_success( 'admin_ignored' );
	}

	// Filter bots by User-Agent
	if ( rd_views_is_bot() ) {
		wp_send_json_success( 'bot_ignored' );
	}

	// Deduplicate by IP — `rd_get_client_ip` validates REMOTE_ADDR before
	// trusting proxy headers (avoids artificial view inflation via
	// a spoofed CF-Connecting-IP in a loop).
	$ip        = rd_get_client_ip();
	$dedup_key = 'rd_view_' . md5( $post_id . '_' . $ip );

	if ( get_transient( $dedup_key ) ) {
		wp_send_json_success( 'already_counted' );
	}

	// Record the view
	rd_views_increment( $post_id );

	// Mark the IP as counted for this post
	set_transient( $dedup_key, 1, RD_VIEWS_DEDUP_WINDOW );

	wp_send_json_success( 'counted' );
}
add_action( 'wp_ajax_rd_track_view', 'rd_views_ajax_track' );
add_action( 'wp_ajax_nopriv_rd_track_view', 'rd_views_ajax_track' );

/**
 * Increments counters: total + temporal log
 *
 * Uses update_post_meta directly without triggering save_post,
 * preventing the editor from detecting a "modified post".
 */
function rd_views_increment( $post_id ) {

	// Total counter (all-time)
	$total = (int) get_post_meta( $post_id, RD_VIEWS_META_KEY, true );
	update_post_meta( $post_id, RD_VIEWS_META_KEY, $total + 1 );

	// Temporal log (timestamps of the last N views, for windowed queries)
	$log = get_post_meta( $post_id, RD_VIEWS_META_KEY_LOG, true );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$now   = time();
	$log[] = $now;

	// Clean up entries older than retention (avoids blowing up the database)
	$cutoff = $now - RD_VIEWS_LOG_RETENTION;
	$log    = array_filter( $log, fn( $t ) => $t >= $cutoff );

	update_post_meta( $post_id, RD_VIEWS_META_KEY_LOG, array_values( $log ) );
}

/**
 * Detects bots by User-Agent (list of the main ones)
 */
function rd_views_is_bot() {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		return true; // no UA = suspicious
	}

	$ua   = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
	$bots = array(
		'bot',
		'spider',
		'crawler',
		'scraper',
		'slurp',
		'mediapartners',
		'facebookexternalhit',
		'twitterbot',
		'discordbot',
		'telegrambot',
		'whatsapp',
		'linkedinbot',
		'embedly',
		'quora',
		'pinterest',
		'redditbot',
		'applebot',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'bingbot',
		'googlebot',
		'gptbot',
		'chatgpt-user',
		'ccbot',
		'claudebot',
		'anthropic-ai',
		'perplexitybot',
		'wget',
		'curl',
		'headless',
	);

	foreach ( $bots as $bot ) {
		if ( strpos( $ua, $bot ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Hides our meta keys from the editor's Custom Fields UI
 * (they start with "_" so WP already hides them by convention,
 *  but we reinforce it here to be sure)
 */
function rd_views_hide_meta_keys( $is_protected, $meta_key ) {
	if ( in_array( $meta_key, array( RD_VIEWS_META_KEY, RD_VIEWS_META_KEY_LOG ), true ) ) {
		return true;
	}
	return $is_protected;
}
add_filter( 'is_protected_meta', 'rd_views_hide_meta_keys', 10, 2 );

/*
=============================================================================
 *  PUBLIC API (use in templates)
 * ============================================================================= */

/**
 * Returns the number of views of a post (all-time or window)
 *
 * @param int    $post_id
 * @param string $window 'all', 'day', 'week', 'month', 'year'
 * @return int
 */
function rd_get_post_views( $post_id, $window = 'all' ) {
	if ( $window === 'all' ) {
		return (int) get_post_meta( $post_id, RD_VIEWS_META_KEY, true );
	}

	$log = get_post_meta( $post_id, RD_VIEWS_META_KEY_LOG, true );
	if ( ! is_array( $log ) ) {
		return 0;
	}

	$cutoff_map = array(
		'day'   => DAY_IN_SECONDS,
		'week'  => WEEK_IN_SECONDS,
		'month' => MONTH_IN_SECONDS,
		'year'  => YEAR_IN_SECONDS,
	);

	if ( ! isset( $cutoff_map[ $window ] ) ) {
		return 0;
	}

	$cutoff = time() - $cutoff_map[ $window ];
	return count( array_filter( $log, fn( $t ) => $t >= $cutoff ) );
}

/**
 * Formats a view count according to the panel config.
 *
 * Modes (option `views_number_format`):
 *   - 'full' (default) → "1,234" via number_format_i18n (respects locale)
 *   - 'compact'        → "1.2k" / "1.2M" social-media style (YouTube/Reddit/GitHub)
 *
 * Compact algorithm (chosen to avoid misleading rounding):
 *   < 1,000           → exact number
 *   < 10,000          → "X.Yk" (1 decimal, truncated via floor)
 *   < 1,000,000       → "Nk"   (no decimal)
 *   < 10,000,000      → "X.YM" (1 decimal)
 *   < 1,000,000,000   → "NM"
 *   ≥ 1B              → "NB"   (defensive, unlikely)
 *
 * Truncate (floor) instead of round: 1999 becomes "1.9k", not "2k" (which would lie).
 *
 * Applied ONLY on the frontend (cards/single). The admin (posts column,
 * Statistics panel in mod-stats.php) uses number_format_i18n directly — admin
 * always wants exact numbers.
 */
function rd_format_views_number( $n ) {
	$n = (int) $n;

	if ( rd_get_option( 'views_number_format', 'full' ) !== 'compact' ) {
		return number_format_i18n( $n );
	}

	if ( $n < 1000 ) {
		return (string) $n;
	}

	if ( $n < 1000000 ) {
		if ( $n < 10000 ) {
			$k = floor( $n / 100 ) / 10;
			return ( fmod( $k, 1 ) === 0.0 ) ? sprintf( '%dk', $k ) : sprintf( '%.1fk', $k );
		}
		return sprintf( '%dk', floor( $n / 1000 ) );
	}

	if ( $n < 1000000000 ) {
		if ( $n < 10000000 ) {
			$m = floor( $n / 100000 ) / 10;
			return ( fmod( $m, 1 ) === 0.0 ) ? sprintf( '%dM', $m ) : sprintf( '%.1fM', $m );
		}
		return sprintf( '%dM', floor( $n / 1000000 ) );
	}

	return sprintf( '%dB', floor( $n / 1000000000 ) );
}

/**
 * Returns the most popular posts (all-time)
 *
 * @param int   $limit Amount
 * @param array $args  Additional args for WP_Query
 * @return WP_Query
 */
function rd_get_popular_posts( $limit = 3, $args = array() ) {
	$defaults = array(
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- public API used a few times per request (sidebar widget, 404 fallback, etc); orderby on meta is the only way to list posts by views.
		'meta_key'       => RD_VIEWS_META_KEY,
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	);

	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Adds a "Views" column to the admin posts list
 */
function rd_views_admin_column( $columns ) {
	$columns['rd_views'] = __( '👁️ Views', 'reloaded' );
	return $columns;
}
add_filter( 'manage_post_posts_columns', 'rd_views_admin_column' );

function rd_views_admin_column_content( $column, $post_id ) {
	if ( $column === 'rd_views' ) {
		$views = rd_get_post_views( $post_id );
		echo esc_html( number_format_i18n( $views ) );
	}
}
add_action( 'manage_post_posts_custom_column', 'rd_views_admin_column_content', 10, 2 );

function rd_views_admin_column_sortable( $columns ) {
	$columns['rd_views'] = 'rd_views';
	return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', 'rd_views_admin_column_sortable' );

function rd_views_admin_column_orderby( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->get( 'orderby' ) === 'rd_views' ) {
		$query->set( 'meta_key', RD_VIEWS_META_KEY );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'rd_views_admin_column_orderby' );
