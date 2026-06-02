<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Social & SEO - Open Graph, Social Networks
 *******************************************************************************/

/*******************************************************************************
 * Top Bar modules (Date, News Ticker and Social Networks) (Social Networks)
 *******************************************************************************/
/**
 * 1. Date module — uses the format configured in Settings → General.
 */
function rd_render_date() {
	// Uses WP's native date format (Settings → General → Date Format).
	// wp_date() translates month/day names automatically to the active locale.
	echo '<span class="rd-date-display">' . esc_html( wp_date( get_option( 'date_format' ) ) ) . '</span>';
}

/**
 * 2. Ticker module — lists the 5 most recent posts.
 */
function rd_render_news_ticker() {
	$ticker_query = new WP_Query(
		array(
			'post_type'      => 'post',
			'posts_per_page' => 5, // Gets the latest 5
			'post_status'    => 'publish',
		)
	);

	if ( $ticker_query->have_posts() ) {
		echo '<div class="rd-ticker-viewport">';
		echo '  <ul class="rd-ticker-list">';
		while ( $ticker_query->have_posts() ) {
			$ticker_query->the_post();
			echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		echo '  </ul>';
		echo '</div>';
		wp_reset_postdata();
	}
}

/**
 * 3. Social Networks module.
 *
 * $user_id (optional): when provided, pulls the network URLs of the specific user
 * via get_the_author_meta('social_X', $user_id) — fields added by the
 * rd_add_user_social_fields filter below, which are editable in Users → Profile.
 * When null (default), keeps using the portal's global URLs defined in the
 * panel (rd_get_option('social_X')). Same set of networks in both modes.
 */
function rd_render_social_icons( $user_id = null ) {
	$redes = array( 'discord', 'telegram', 'whatsapp', 'youtube', 'instagram', 'steam', 'twitter', 'facebook' );

	// Library of Mathematical Vectors (Inline SVGs)
	$svgs = array(
		'discord'   => '<svg viewBox="0 0 640 512" width="16" height="16"><path fill="currentColor" d="M524.531,69.836a1.5,1.5,0,0,0-.764-.7A485.065,485.065,0,0,0,404.081,32.03a1.816,1.816,0,0,0-1.923.91,337.461,337.461,0,0,0-14.9,30.6,447.848,447.848,0,0,0-134.426,0,309.541,309.541,0,0,0-15.135-30.6,1.89,1.89,0,0,0-1.924-.91A483.689,483.689,0,0,0,116.085,69.137a1.712,1.712,0,0,0-.788.676C39.068,183.651,18.186,294.69,28.43,404.354a2.016,2.016,0,0,0,.765,1.375A487.666,487.666,0,0,0,176.02,479.918a1.9,1.9,0,0,0,2.063-.276c8.3-11.232,16.068-22.951,23.235-35.1a1.884,1.884,0,0,0-1.026-2.658,309.117,309.117,0,0,1-40.428-19.167,1.821,1.821,0,0,1-.186-3.085c3.2-2.356,6.38-4.786,9.458-7.262a1.831,1.831,0,0,1,1.916-.276c83.562,38.163,173.812,38.163,256.334,0a1.848,1.848,0,0,1,1.924.276c3.085,2.476,6.265,4.906,9.489,7.262a1.825,1.825,0,0,1-.17,3.085,312.2,312.2,0,0,1-40.459,19.167,1.886,1.886,0,0,0-1.011,2.658c7.167,12.149,14.934,23.868,23.235,35.1a1.9,1.9,0,0,0,2.063.276A488.528,488.528,0,0,0,611.365,405.729a1.854,1.854,0,0,0,.765-1.375C624.167,280.9,595.6,170.835,524.531,69.836ZM222.491,337.58c-28.972,0-52.844-26.587-52.844-59.239S193.056,219.1,222.491,219.1c29.665,0,53.306,26.82,52.843,59.239C275.334,310.993,251.924,337.58,222.491,337.58Zm195.38,0c-28.971,0-52.843-26.587-52.843-59.239S388.437,219.1,417.871,219.1c29.667,0,53.307,26.82,52.844,59.239C470.715,310.993,447.538,337.58,417.871,337.58Z"/></svg>',
		'telegram'  => '<svg viewBox="0 0 496 512" width="16" height="16"><path fill="currentColor" d="M248 8C111.033 8 0 119.033 0 256s111.033 248 248 248 248-111.033 248-248S384.967 8 248 8Zm114.952 168.66c-3.732 39.215-19.881 134.378-28.1 178.3-3.476 18.584-10.322 24.816-16.948 25.425-14.4 1.326-25.338-9.517-39.287-18.661-21.827-14.308-34.158-23.215-55.346-37.177-24.485-16.135-8.612-25 5.342-39.5 3.652-3.793 67.107-61.51 68.335-66.746.153-.655.3-3.1-1.154-4.384s-3.59-.849-5.135-.5q-3.283.746-104.608 69.142-14.845 10.194-26.894 9.934c-8.855-.191-25.888-5.006-38.551-9.123-15.531-5.048-27.875-7.717-26.8-16.291q.84-6.7 18.45-13.7 108.446-47.248 144.628-62.3c68.872-28.647 83.183-33.623 92.511-33.789 2.052-.034 6.639.474 9.61 2.885a10.452 10.452 0 0 1 3.53 6.716 43.765 43.765 0 0 1 .417 9.769Z"/></svg>',
		'whatsapp'  => '<svg viewBox="0 0 448 512" width="16" height="16"><path fill="currentColor" d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157.1zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>',
		'youtube'   => '<svg viewBox="0 0 576 512" width="16" height="16"><path fill="currentColor" d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"/></svg>',
		'instagram' => '<svg viewBox="0 0 448 512" width="16" height="16"><path fill="currentColor" d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg>',
		'steam'     => '<svg viewBox="0 0 256 259" width="16" height="16"><path fill="currentColor" d="M127.779 0C60.42 0 5.24 52.412 0 119.014l68.724 28.674a35.812 35.812 0 0 1 20.426-6.366c.682 0 1.356.019 2.02.056l30.566-44.71v-.626c0-26.903 21.69-48.796 48.353-48.796 26.662 0 48.352 21.893 48.352 48.796 0 26.902-21.69 48.804-48.352 48.804-.37 0-.73-.009-1.098-.018l-43.593 31.377c.028.582.046 1.163.046 1.735 0 20.204-16.283 36.636-36.294 36.636-17.566 0-32.263-12.658-35.584-29.412L4.41 164.654c15.223 54.313 64.673 94.132 123.369 94.132 70.818 0 128.221-57.938 128.221-129.393C256 57.93 198.597 0 127.779 0zM80.352 196.332l-15.749-6.568c2.787 5.867 7.621 10.775 14.033 13.47 13.857 5.83 29.836-.803 35.612-14.799a27.555 27.555 0 0 0 .046-21.035c-2.768-6.79-7.999-12.086-14.706-14.909-6.67-2.795-13.811-2.694-20.085-.304l16.275 6.79c10.222 4.3 15.056 16.145 10.794 26.46-4.253 10.314-15.998 15.195-26.22 10.895zm121.957-100.29c0-17.925-14.457-32.52-32.217-32.52-17.769 0-32.226 14.595-32.226 32.52 0 17.926 14.457 32.512 32.226 32.512 17.76 0 32.217-14.586 32.217-32.512zm-56.37-.055c0-13.488 10.84-24.42 24.2-24.42 13.368 0 24.208 10.932 24.208 24.42 0 13.488-10.84 24.421-24.209 24.421-13.359 0-24.2-10.933-24.2-24.42z"/></svg>',
		'twitter'   => '<svg viewBox="0 0 512 512" width="16" height="16"><path fill="currentColor" d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/></svg>',
		'facebook'  => '<svg viewBox="0 0 320 512" width="16" height="16"><path fill="currentColor" d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg>',
	);

	echo '<div class="rd-social-icons">';
	foreach ( $redes as $rede ) {
		// User-level (on /author/{slug}/) or global (footer, page-sobre, top bar)
		$url = $user_id
			? get_the_author_meta( 'social_' . $rede, $user_id )
			: rd_get_option( 'social_' . $rede );

		if ( ! empty( $url ) && isset( $svgs[ $rede ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rede comes from the hardcoded $redes array above (no user input); ucfirst() of a literal string is safe.
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" class="rd-social-link rd-' . $rede . '" aria-label="' . ucfirst( $rede ) . '">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup from hardcoded $svgs array (no user input).
			echo $svgs[ $rede ];
			echo '</a>';
		}
	}
	echo '</div>';
}

/*******************************************************************************
 * Per-user social network fields (Users → Profile)                            *
 *                                                                             *
 * Adds the same 8 network fields from the global panel to each user profile   *
 * via the `user_contactmethods` filter. They're accessible in                 *
 * get_the_author_meta('social_X', $user_id) and used by author.php to         *
 * show each writer's personal networks (separate from the portal's networks). *
 *******************************************************************************/
function rd_add_user_social_fields( $methods ) {
	$methods['social_discord']   = __( 'Discord (URL)', 'reloaded' );
	$methods['social_telegram']  = __( 'Telegram (URL)', 'reloaded' );
	$methods['social_whatsapp']  = __( 'WhatsApp (URL)', 'reloaded' );
	$methods['social_youtube']   = __( 'YouTube (URL)', 'reloaded' );
	$methods['social_instagram'] = __( 'Instagram (URL)', 'reloaded' );
	$methods['social_steam']     = __( 'Steam (URL)', 'reloaded' );
	$methods['social_twitter']   = __( 'Twitter / X (URL)', 'reloaded' );
	$methods['social_facebook']  = __( 'Facebook (URL)', 'reloaded' );
	return $methods;
}
add_filter( 'user_contactmethods', 'rd_add_user_social_fields' );
