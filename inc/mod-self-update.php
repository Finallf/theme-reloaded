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
 *   - admin_post_rd_self_update_changelog   → our own "View Details" page     *
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
 *   - Channels: stable (default) uses /releases/latest, where prereleases     *
 *     are excluded by design; the opt-in beta channel (Dashboard switch,      *
 *     `update_beta_channel`) follows the newest release including             *
 *     prereleases — see rd_self_update_channel()                              *
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
	$channel = rd_self_update_channel();

	if ( ! $force_refresh ) {
		$cached = get_transient( RD_SELF_UPDATE_TRANSIENT );
		// Cache only counts when it belongs to the CURRENT channel — switching
		// channels on the Dashboard invalidates the view immediately instead of
		// serving the other channel's release for up to 24h.
		if ( is_array( $cached ) && ! empty( $cached['version'] ) && ( $cached['channel'] ?? 'stable' ) === $channel ) {
			return $cached;
		}
	}

	// stable → /releases/latest (GitHub excludes prereleases by design).
	// beta   → the releases LIST (newest first, prereleases included): we take
	// the tip whatever it is — when a stable ships after the betas it IS the
	// newest entry, so beta-channel installs get promoted to stable automatically.
	$url = 'https://api.github.com/repos/' . RD_SELF_UPDATE_REPO
		. ( 'beta' === $channel ? '/releases?per_page=15' : '/releases/latest' );

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
	if ( 'beta' === $channel ) {
		// GitHub's /releases order is NOT reliably newest-first, so don't trust
		// [0] — pick the highest semver tag. A stable release outranks the betas
		// (version_compare: 1.11.0 > 1.11.0-beta.1), so beta installs auto-promote.
		$newest = null;
		foreach ( (array) $data as $rel ) {
			if ( ! is_array( $rel ) || empty( $rel['tag_name'] ) || ! empty( $rel['draft'] ) ) {
				continue;
			}
			if ( null === $newest
				|| version_compare( ltrim( (string) $rel['tag_name'], 'v' ), ltrim( (string) $newest['tag_name'], 'v' ), '>' ) ) {
				$newest = $rel;
			}
		}
		$data = $newest;
	}
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
		'channel'      => $channel, // which channel produced this payload (cache validity check)
	);

	set_transient( RD_SELF_UPDATE_TRANSIENT, $release, RD_SELF_UPDATE_CACHE_HOURS * HOUR_IN_SECONDS );
	return $release;
}

/**
 * Update channel: 'beta' when the Dashboard "Beta channel" switch is ON,
 * 'stable' otherwise (the default — /releases/latest, prereleases excluded).
 * Note: no downgrade on beta → stable switch; the install stays on the beta
 * version until a NEWER stable ships (version_compare handles -beta suffixes).
 */
function rd_self_update_channel(): string {
	return rd_get_option_bool( 'update_beta_channel' ) ? 'beta' : 'stable';
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
			// WP iframes this URL for the theme's "View version details" link.
			// Point it at OUR own same-origin changelog page (the admin-post
			// handler below) — NOT the GitHub release page (github.com sends
			// X-Frame-Options: deny → "connection refused"), and NOT WP's
			// theme-information modal (the THEME modal is screenshot/install-only
			// and ignores our `sections`, so it renders blank).
			'url'         => admin_url( 'admin-post.php?action=rd_self_update_changelog' ),
			'package'     => $release['download_url'],
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'rd_self_update_inject' );

/*
=============================================================================
 *  "VIEW DETAILS" MODAL — our own changelog page (thickbox iframe)
 * ============================================================================= */

/**
 * Renders the release changelog inside the theme-update "View version details"
 * thickbox. WP iframes the update's `url` (set to this admin-post action in
 * rd_self_update_inject()), so we serve a small same-origin page with the
 * changelog — instead of the GitHub release page (X-Frame-Options: deny) or WP's
 * theme-information modal (which is screenshot/install-only and ignores our
 * sections, rendering blank).
 *
 * Markdown → HTML via our bundled Parsedown (safe mode), then wp_kses_post.
 */
function rd_self_update_changelog_iframe() {
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'You are not allowed to view this.', 'reloaded' ) );
	}

	$release   = rd_self_update_fetch_release();
	$changelog = '';
	if ( $release && '' !== (string) $release['body'] ) {
		if ( ! class_exists( 'Parsedown' ) ) {
			require_once get_template_directory() . '/lib/Parsedown.php';
		}
		// semantic-release appends a trailing "<br>" + "---" separator. Parsedown's
		// safe mode escapes the "<br>" into literal text and turns "---" into a
		// stray rule, so drop those standalone separator lines first.
		$body = (string) preg_replace( '/^[ \t]*(?:<br\s*\/?>|-{3,}|\*{3,}|_{3,})[ \t]*$/mi', '', (string) $release['body'] );

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true ); // strip dangerous HTML from the release markdown.
		$changelog = wp_kses_post( $parsedown->text( $body ) );
	}

	// A small SELF-CONTAINED page — deliberately NOT iframe_header(), which boots
	// the whole admin_enqueue_scripts / admin_head machinery in this screenless
	// admin-post context (no current screen, null hook suffix) and trips other
	// callbacks (e.g. a plugin enqueue typed `string $hook` fatals on the null).
	// We control every byte here, so the modal can't be broken by other plugins.
	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Changelog', 'reloaded' ); ?></title>
	<?php printf( '<link rel="stylesheet" href="%s" media="all">', esc_url( admin_url( 'css/common.min.css' ) ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone admin-post page; there is no wp_head to enqueue into, so a direct link is the only option. ?>
</head>
<body class="wp-core-ui rd-changelog-modal">
	<div class="wrap">
		<?php if ( '' !== $changelog ) : ?>
			<h1><?php echo esc_html( 'ReloadeD ' . (string) ( $release['version'] ?? '' ) ); ?></h1>
			<?php echo $changelog; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() applied above. ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No changelog available for this release.', 'reloaded' ); ?></p>
		<?php endif; ?>
	</div>
</body>
</html>
	<?php
	exit;
}
add_action( 'admin_post_rd_self_update_changelog', 'rd_self_update_changelog_iframe' );

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
