<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Reading Time Estimate                                               *
 *                                                                             *
 * Calculates and shows an "X min read" indicator in the single entry-meta,    *
 * based on word count / 200 WPM (international standard used by Medium,       *
 * Substack, dev.to, etc).                                                     *
 *                                                                             *
 * Cache: the value is calculated and saved in the `_rd_reading_time` post     *
 * meta on save_post (1x per edit), read instantly on renders. Posts from      *
 * before the module recalculate on-the-fly and cache on first read.           *
 *                                                                             *
 * Gate: feature controlled by `enable_reading_time` (default ON).             *
 *                                                                             *
 * No omission for short posts — always shows (minimum 1 min).                 *
 *******************************************************************************/

const RD_READING_WPM = 200;

/**
 * Calculates a post's reading time (always on-the-fly, no cache).
 * Uses preg_match_all with the /u flag (UTF-8) — str_word_count fails with accents.
 *
 * @param int $post_id Post ID.
 * @return int Minutes (minimum 1).
 */
function rd_calculate_reading_time( $post_id ) {
	$content    = (string) get_post_field( 'post_content', $post_id );
	$text       = wp_strip_all_tags( strip_shortcodes( $content ) );
	$word_count = preg_match_all( '/\S+/u', $text );
	if ( false === $word_count || $word_count < 1 ) {
		return 1;
	}
	return max( 1, (int) ceil( $word_count / RD_READING_WPM ) );
}

/**
 * Returns the reading time (cached in post meta).
 *
 * @param int $post_id Post ID.
 * @return int Minutes.
 */
function rd_get_reading_time( $post_id ) {
	$cached = get_post_meta( $post_id, '_rd_reading_time', true );
	if ( '' !== $cached && is_numeric( $cached ) ) {
		return (int) $cached;
	}
	$minutes = rd_calculate_reading_time( $post_id );
	update_post_meta( $post_id, '_rd_reading_time', $minutes );
	return $minutes;
}

/**
 * Renders the reading time span to insert into the single's .entry-meta.
 * Exits early if the feature is OFF — zero overhead.
 *
 * @param int $post_id Post ID.
 */
function rd_render_reading_time( $post_id ) {
	if ( ! rd_get_option_bool( 'enable_reading_time', true ) ) {
		return;
	}
	$minutes = rd_get_reading_time( $post_id );
	?>
	<span class="reading-time">
		<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
		<?php
		printf(
			esc_html(
				/* translators: %d = number of minutes to read the post */
				_n( '%d min read', '%d min read', $minutes, 'reloaded' )
			),
			(int) $minutes
		);
		?>
	</span>
	<?php
}

/**
 * Recalculates and updates the cache on save_post. Only for post_type=post.
 *
 * @param int $post_id ID of the saved post.
 */
function rd_reading_time_refresh_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_rd_reading_time', rd_calculate_reading_time( $post_id ) );
}
add_action( 'save_post', 'rd_reading_time_refresh_cache' );
