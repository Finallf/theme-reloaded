<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: IndexNow                                                            *
 *                                                                             *
 * Push-based indexing notifications (Bing/Yandex/Seznam/Naver — one ping is   *
 * shared between every participating engine; Google does NOT join, so the     *
 * sitemap remains essential). Instead of waiting for crawlers to re-discover  *
 * content (pull), the site notifies the engines the moment a URL is           *
 * published, updated or unpublished — indexing in minutes instead of days.    *
 *                                                                             *
 * Three pieces (https://www.indexnow.org/documentation):                      *
 *   1. KEY    — random hex string, auto-generated on first save (panel: SEO   *
 *      → IndexNow). No registration anywhere: the key is self-service.        *
 *   2. PROOF  — the key must be reachable at https://host/{key}.txt. Served   *
 *      VIRTUALLY by rd_indexnow_serve_key_file() — no physical file, nothing  *
 *      for the admin to upload, survives theme/site moves.                    *
 *   3. PING   — on publish/update/unpublish, a fire-and-forget POST to        *
 *      api.indexnow.org with the URL. Non-blocking (the editor never waits    *
 *      on a third-party API).                                                 *
 ******************************************************************************/

const RD_INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

/**
 * Master toggle + key present.
 */
function rd_indexnow_is_active(): bool {
	return rd_get_option_bool( 'enable_indexnow' ) && '' !== rd_indexnow_get_key();
}

/**
 * Returns the sanitized key ('' when unset/invalid).
 */
function rd_indexnow_get_key(): string {
	$key = strtolower( trim( (string) rd_get_option( 'indexnow_key', '' ) ) );
	// Protocol allows 8-128 chars [a-z0-9-]; we generate 32-hex ourselves but
	// accept any valid externally-generated key pasted by the admin.
	if ( ! preg_match( '/^[a-z0-9-]{8,128}$/', $key ) ) {
		return '';
	}
	return $key;
}

/**
 * Auto-generates the key on save when the feature is enabled and the field is
 * empty — the admin just flips the toggle and saves; no manual key juggling.
 *
 * Hook: pre_update_option_rd_settings (same pattern as the maintenance hash).
 *
 * @param mixed $new_value New rd_settings array.
 * @return mixed
 */
function rd_indexnow_generate_key_on_save( $new_value ) {
	if ( ! is_array( $new_value ) ) {
		return $new_value;
	}
	$enabled = ! empty( $new_value['enable_indexnow'] );
	$key     = isset( $new_value['indexnow_key'] ) ? trim( (string) $new_value['indexnow_key'] ) : '';
	if ( $enabled && '' === $key ) {
		$new_value['indexnow_key'] = bin2hex( random_bytes( 16 ) ); // 32-hex key
	}
	return $new_value;
}
add_filter( 'pre_update_option_rd_settings', 'rd_indexnow_generate_key_on_save', 20 );

/**
 * Builds the panel description for the key field: explains the auto-generate
 * behavior and, when active, shows the live key-file URL plus the last-ping
 * breadcrumb — quick "is it alive?" feedback without a dedicated log screen.
 *
 * @return string HTML for the field desc (panel renders it via wp_kses_post).
 */
function rd_indexnow_key_field_desc(): string {
	$desc = __( 'Leave empty — a 32-char key is generated automatically when you save with IndexNow enabled. Paste your own only if you already use a key from Bing Webmaster Tools.', 'reloaded' );

	if ( rd_indexnow_is_active() ) {
		$key_url = home_url( '/' . rd_indexnow_get_key() . '.txt' );
		$desc   .= ' ' . sprintf(
			/* translators: %s: the public key-file URL */
			__( 'Key file live at: <a href="%1$s" target="_blank"><code>%1$s</code></a>.', 'reloaded' ),
			esc_url( $key_url )
		);

		$last = get_option( 'rd_indexnow_last_ping' );
		if ( is_array( $last ) && ! empty( $last['time'] ) ) {
			$desc .= ' ' . sprintf(
				/* translators: 1: date/time of the last ping, 2: pinged URL */
				__( 'Last ping: <strong>%1$s</strong> (%2$s).', 'reloaded' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] ) ),
				'<code>' . esc_html( (string) ( $last['url'] ?? '' ) ) . '</code>'
			);
		} else {
			$desc .= ' ' . __( 'No pings sent yet — publish or update a post to fire the first one.', 'reloaded' );
		}
	}

	return $desc;
}

/**
 * Serves the ownership proof virtually: GET /{key}.txt → 200 text/plain with
 * the key as body. Engines fetch this to verify the pinging host owns the
 * site (same idea as a Let's Encrypt HTTP challenge). No physical file.
 */
function rd_indexnow_serve_key_file() {
	if ( ! rd_indexnow_is_active() ) {
		return;
	}
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	$key         = rd_indexnow_get_key();

	if ( '/' . $key . '.txt' !== $path ) {
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' ); // the proof file itself has no search value
	echo $key; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated [a-z0-9-]{8,128} in rd_indexnow_get_key().
	exit;
}
add_action( 'init', 'rd_indexnow_serve_key_file', 2 );

/**
 * Pings IndexNow when a post/page is published, updated while published, or
 * unpublished (trash/draft/private — engines re-crawl and see the 404/410).
 *
 * Fire-and-forget: blocking=false + short timeout, so the editor's save
 * request never waits on the IndexNow API.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function rd_indexnow_on_status_change( $new_status, $old_status, $post ) {
	if ( ! rd_indexnow_is_active() ) {
		return;
	}
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}

	$was_public = 'publish' === $old_status;
	$is_public  = 'publish' === $new_status;
	if ( ! $was_public && ! $is_public ) {
		return; // draft → draft etc. — nothing indexable changed
	}

	$url = get_permalink( $post );
	if ( ! $url ) {
		return;
	}
	// Trashed posts get a "__trashed" suffix on the slug — strip it so the
	// engines re-crawl the URL the content USED to live at.
	$url = str_replace( '__trashed', '', $url );

	rd_indexnow_ping( array( $url ) );
}
add_action( 'transition_post_status', 'rd_indexnow_on_status_change', 10, 3 );

/**
 * Sends the ping. Stores a small "last ping" record (timestamp + URL) so the
 * panel can show the integration is alive.
 *
 * @param string[] $urls Absolute URLs to submit (1-10000 per the protocol).
 */
function rd_indexnow_ping( array $urls ): void {
	$key  = rd_indexnow_get_key();
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( '' === $key || empty( $urls ) || ! $host ) {
		return;
	}

	wp_remote_post(
		RD_INDEXNOW_ENDPOINT,
		array(
			'blocking' => false, // fire-and-forget — never delay the editor
			'timeout'  => 3,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode(
				array(
					'host'    => $host,
					'key'     => $key,
					'urlList' => array_values( $urls ),
				)
			),
		)
	);

	// Non-autoloaded breadcrumb for the panel ("last ping: date — URL").
	update_option(
		'rd_indexnow_last_ping',
		array(
			'time' => time(),
			'url'  => (string) $urls[0],
		),
		false
	);
}
