<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Self-Update — Auto-update via GitHub Releases (custom)              *
 *                                                                             *
 * Connects the theme to the releases published on the project's GitHub so WP  *
 * detects new versions and offers a 1-click update in admin, like any         *
 * wp.org theme. No external deps — reuses the already-vendored Parsedown to   *
 * render release notes in the "View Details" modal.                           *
 *                                                                             *
 * End-to-end flow:                                                            *
 *   1. Push conventional commits (`feat:`, `fix:`) to `master`                *
 *   2. CI workflow runs → semantic-release computes new version `v0.5.0`      *
 *   3. CI builds `reloaded.zip` (internal structure `reloaded/...`)           *
 *   4. Binary asset attached to the GitHub Release                            *
 *   5. User's WP fetches `/releases/latest` (24h cache) → finds v0.5.0        *
 *   6. Compares semver vs local `Version:` → injects into the WP transient    *
 *   7. Admin sees "Update available", clicks → WP downloads & unpacks the ZIP *
 *                                                                             *
 * WordPress APIs used (stable since WP 2.8+):                                 *
 *   - pre_set_site_transient_update_themes  → inject the update into transient*
 *   - themes_api                            → feed the "View Details" modal   *
 *   - upgrader_source_selection             → defense in depth for the        *
 *                                              extracted ZIP's folder name    *
 *   - wp_ajax_rd_check_update                → endpoint for "Check now" button*
 *                                                                             *
 * Tracked: 1 transient (`rd_self_update_release`) with 24h TTL (1h on error). *
 *                                                                             *
 * Philosophy:                                                                 *
 *   - No external deps (PUC was dropped — bloated + duplicated Parsedown)     *
 *   - Reuses lib/Parsedown.php to render release notes                        *
 *   - WP APIs stable for ~15 years                                            *
 *   - Public repo (no token auth) — releases accessible anonymously           *
 *   - `beta` branch produces `prerelease: true` → API ignores by design       *
 ******************************************************************************/

const RD_SELF_UPDATE_REPO        = 'Finallf/theme-reloaded';
const RD_SELF_UPDATE_SLUG        = 'reloaded';
const RD_SELF_UPDATE_TRANSIENT   = 'rd_self_update_release';
const RD_SELF_UPDATE_CACHE_HOURS = 24;
const RD_SELF_UPDATE_API_TIMEOUT = 8;

/*
=============================================================================
 *  FETCH — GitHub /releases/latest with 24h cache
 * ============================================================================= */

/**
 * Fetches the latest release from GitHub. Cached in a transient for 24h (1h on
 * error to avoid hammering GitHub during an outage). Returns a normalized array or null.
 *
 * Returned structure:
 *   [
 *     'version'      => '0.5.0',
 *     'download_url' => 'https://.../reloaded.zip',
 *     'release_url'  => 'https://github.com/.../releases/tag/v0.5.0',
 *     'body'         => '## Features\n...',
 *     'published_at' => '2026-06-15T...',
 *     'checked_at'   => 1748534400,
 *   ]
 *
 * @param bool $force_refresh Invalidates cache and re-fetches immediately ("Check now" button).
 */
function rd_self_update_fetch_release( bool $force_refresh = false ): ?array {
	if ( ! $force_refresh ) {
		$cached = get_transient( RD_SELF_UPDATE_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}
	}

	$url      = 'https://api.github.com/repos/' . RD_SELF_UPDATE_REPO . '/releases/latest';
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => RD_SELF_UPDATE_API_TIMEOUT,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		// Short "empty" cache on error to avoid bombarding GitHub during an outage.
		set_transient( RD_SELF_UPDATE_TRANSIENT, array(), HOUR_IN_SECONDS );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		return null;
	}

	// Look for the .zip asset (our CI uploads `reloaded.zip`). If not found,
	// the release isn't installable — return null.
	$zip_url = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		$name = $asset['name'] ?? '';
		if ( ! empty( $asset['browser_download_url'] ) && str_ends_with( strtolower( $name ), '.zip' ) ) {
			$zip_url = $asset['browser_download_url'];
			break;
		}
	}

	if ( '' === $zip_url ) {
		return null;
	}

	$release = array(
		'version'      => ltrim( (string) $data['tag_name'], 'v' ),
		'download_url' => $zip_url,
		'release_url'  => (string) ( $data['html_url'] ?? '' ),
		'body'         => (string) ( $data['body'] ?? '' ),
		'published_at' => (string) ( $data['published_at'] ?? '' ),
		'checked_at'   => time(),
	);

	set_transient( RD_SELF_UPDATE_TRANSIENT, $release, RD_SELF_UPDATE_CACHE_HOURS * HOUR_IN_SECONDS );
	return $release;
}

/**
 * Returns the local version read from `style.css`.
 */
function rd_self_update_get_local_version(): string {
	return (string) wp_get_theme( RD_SELF_UPDATE_SLUG )->get( 'Version' );
}

/*
=============================================================================
 *  WP UPDATE TRANSIENT INJECTION
 * ============================================================================= */

/**
 * Hook on pre_set_site_transient_update_themes — when WP stores the transient
 * listing which updates are available, we inject ours if any.
 */
function rd_self_update_inject( $transient ) {
	if ( empty( $transient ) || ! is_object( $transient ) ) {
		return $transient;
	}

	$release = rd_self_update_fetch_release();
	if ( ! $release ) {
		return $transient;
	}

	$local = rd_self_update_get_local_version();
	if ( version_compare( $release['version'], $local, '>' ) ) {
		$transient->response[ RD_SELF_UPDATE_SLUG ] = array(
			'theme'       => RD_SELF_UPDATE_SLUG,
			'new_version' => $release['version'],
			'url'         => $release['release_url'],
			'package'     => $release['download_url'],
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'rd_self_update_inject' );

/*
=============================================================================
 *  "VIEW DETAILS" MODAL (themes_api) — render via our Parsedown
 * ============================================================================= */

/**
 * Hook on themes_api — when the admin clicks "View version details" on the
 * theme card (modal popup), WP calls this filter to build the content.
 *
 * Markdown rendering via OUR Parsedown — zero external dep.
 */
function rd_self_update_themes_api( $result, $action, $args ) {
	if ( 'theme_information' !== $action || empty( $args->slug ) || RD_SELF_UPDATE_SLUG !== $args->slug ) {
		return $result;
	}

	$release = rd_self_update_fetch_release();
	if ( ! $release ) {
		return $result;
	}

	if ( ! class_exists( 'Parsedown' ) ) {
		require_once get_template_directory() . '/lib/Parsedown.php';
	}
	$parsedown = new Parsedown();
	$parsedown->setSafeMode( true ); // strip dangerous HTML from the release markdown.

	$theme = wp_get_theme( RD_SELF_UPDATE_SLUG );

	return (object) array(
		'name'          => (string) $theme->get( 'Name' ),
		'slug'          => RD_SELF_UPDATE_SLUG,
		'version'       => $release['version'],
		'author'        => (string) $theme->get( 'Author' ),
		'homepage'      => 'https://github.com/' . RD_SELF_UPDATE_REPO,
		'download_link' => $release['download_url'],
		'sections'      => array(
			'changelog' => $parsedown->text( $release['body'] ),
		),
	);
}
add_filter( 'themes_api', 'rd_self_update_themes_api', 10, 3 );

/*
=============================================================================
 *  DEFENSE IN DEPTH — force extracted folder to the right slug
 * ============================================================================= */

/**
 * Hook on upgrader_source_selection — after WP unpacks the ZIP, this
 * filter receives the extracted folder's path. If it has a name other than
 * 'reloaded/' (e.g. someone attached GitHub's auto source tarball instead of
 * our `reloaded.zip`), it renames it.
 *
 * Our CI already generates a `reloaded/...` structure inside the ZIP — so this
 * hook rarely acts in practice. But it guarantees that even if a manual release
 * is done wrong, the user doesn't get bricked.
 */
function rd_self_update_fix_source( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['theme'] ) || RD_SELF_UPDATE_SLUG !== $hook_extra['theme'] ) {
		return $source;
	}

	$expected = trailingslashit( $remote_source ) . RD_SELF_UPDATE_SLUG;
	if ( untrailingslashit( $source ) === $expected ) {
		return $source; // already correct.
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		return $source;
	}

	if ( $wp_filesystem->move( $source, $expected ) ) {
		return trailingslashit( $expected );
	}

	return new WP_Error( 'rd_rename_failed', __( 'Failed to rename theme directory during update.', 'reloaded' ) );
}
add_filter( 'upgrader_source_selection', 'rd_self_update_fix_source', 10, 4 );

/*
=============================================================================
 *  AJAX — "Check for updates" button on the Dashboard
 * ============================================================================= */

/**
 * AJAX handler for the "Check for updates" button on the Dashboard card.
 *
 * Invalidates the transient, re-fetches immediately, returns JSON with updated status.
 * Defensive capability + nonce checks.
 *
 * Response shape:
 *   { ok: bool, current: string, latest: string, status: string,
 *     last_check_human: string, has_update: bool, release_url?: string }
 */
function rd_self_update_handle_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'rd_self_update_check' ) ) {
		wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
	}

	// Invalidate the WP transient too — so the theme card in Appearance →
	// Themes reflects the update on next load, without waiting for WP's 12h cron.
	delete_site_transient( 'update_themes' );

	$release = rd_self_update_fetch_release( true );
	$current = rd_self_update_get_local_version();

	if ( ! $release ) {
		wp_send_json_success(
			array(
				'ok'               => false,
				'current'          => $current,
				'latest'           => '',
				'status'           => __( 'Could not reach GitHub. Try again later.', 'reloaded' ),
				'last_check_human' => __( 'just now', 'reloaded' ),
				'has_update'       => false,
			)
		);
	}

	$has_update = version_compare( $release['version'], $current, '>' );

	wp_send_json_success(
		array(
			'ok'               => true,
			'current'          => $current,
			'latest'           => $release['version'],
			'status'           => $has_update
				? __( 'Update available', 'reloaded' )
				: __( 'Up to date', 'reloaded' ),
			'last_check_human' => __( 'just now', 'reloaded' ),
			'has_update'       => $has_update,
			'release_url'      => $release['release_url'],
		)
	);
}
add_action( 'wp_ajax_rd_check_update', 'rd_self_update_handle_ajax' );
