<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Backup — ReloadeD Backup plugin installer / launcher                *
 *                                                                             *
 * Full-site backups belong in the standalone ReloadeD Backup plugin, which    *
 * captures the whole database (these panel settings included) plus the        *
 * uploads folder in a single, portable .zip. This module provides the         *
 * plumbing the Dashboard's Backup card uses to install / activate / launch    *
 * that plugin (there is no Backup tab — the card is its only home):           *
 *                                                                             *
 *   - state  : missing / inactive / active (rd_backup_plugin_state)           *
 *   - fetch  : latest STABLE GitHub release, 12h cached                       *
 *   - install: 1-click AJAX (Plugin_Upgrader + auto-activate)                 *
 *                                                                             *
 * The install reuses the same GitHub-release source as the theme self-updater *
 * (mod-self-update.php), driving WP's core Plugin_Upgrader server-side. The    *
 * card rendering itself lives in mod-dashboard.php.                           *
 *******************************************************************************/

const RD_BACKUP_PLUGIN_FILE       = 'rd-backup/rd-backup.php';
const RD_BACKUP_PLUGIN_REPO       = 'Finallf/rd-backup';
const RD_BACKUP_PLUGIN_PAGE       = 'tools.php?page=rd-backup';
const RD_BACKUP_RELEASE_TRANSIENT = 'rd_backup_plugin_release';

/*
=============================================================================
 *  STATE — is the plugin missing / installed-inactive / active?
 * ============================================================================= */

/**
 * Returns the install state of the ReloadeD Backup plugin.
 *
 * @return string One of 'missing', 'inactive', 'active'.
 */
function rd_backup_plugin_state(): string {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . RD_BACKUP_PLUGIN_FILE ) ) {
		return 'missing';
	}
	return is_plugin_active( RD_BACKUP_PLUGIN_FILE ) ? 'active' : 'inactive';
}

/*
=============================================================================
 *  FETCH — latest STABLE release of the plugin from GitHub (12h cache)
 * ============================================================================= */

/**
 * Fetches the plugin's latest stable release from GitHub and finds its .zip asset.
 * Mirrors rd_self_update_fetch_release() but is hard-wired to the stable channel
 * (the beta channel lives inside the plugin once it is installed).
 *
 * @param bool $force Bypass the cache.
 * @return array|null { version, download_url, release_url } or null on failure.
 */
function rd_backup_fetch_plugin_release( bool $force = false ): ?array {
	$cached = get_transient( RD_BACKUP_RELEASE_TRANSIENT );
	if ( ! $force && is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . RD_BACKUP_PLUGIN_REPO . '/releases/latest',
		array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		return null;
	}

	$zip_url = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		$name = isset( $asset['name'] ) ? strtolower( (string) $asset['name'] ) : '';
		if ( ! empty( $asset['browser_download_url'] ) && str_ends_with( $name, '.zip' ) ) {
			$zip_url = (string) $asset['browser_download_url'];
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
	);

	set_transient( RD_BACKUP_RELEASE_TRANSIENT, $release, 12 * HOUR_IN_SECONDS );
	return $release;
}

/*
=============================================================================
 *  INSTALL — 1-click: download the release .zip, install, auto-activate
 * ============================================================================= */

/**
 * AJAX: installs the latest stable ReloadeD Backup release and activates it.
 * Action: wp_ajax_rd_backup_install.
 */
function rd_backup_ajax_install(): void {
	if ( ! current_user_can( 'install_plugins' ) ) {
		wp_send_json_error( __( 'You are not allowed to install plugins.', 'reloaded' ), 403 );
	}
	check_ajax_referer( 'rd_backup_install', 'nonce' );

	$release = rd_backup_fetch_plugin_release( true );
	if ( null === $release || empty( $release['download_url'] ) ) {
		wp_send_json_error( __( 'Could not reach GitHub to fetch the latest release. Try again later.', 'reloaded' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $release['download_url'] );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}
	if ( is_wp_error( $skin->result ) ) {
		wp_send_json_error( $skin->result->get_error_message() );
	}
	if ( true !== $result ) {
		$errors = $skin->get_errors();
		wp_send_json_error(
			$errors->has_errors() ? $errors->get_error_message() : __( 'Installation failed.', 'reloaded' )
		);
	}

	$activated = activate_plugin( RD_BACKUP_PLUGIN_FILE );
	if ( is_wp_error( $activated ) ) {
		wp_send_json_error(
			sprintf(
				/* translators: %s: the WordPress activation error message. */
				__( 'Installed, but activation failed: %s', 'reloaded' ),
				$activated->get_error_message()
			)
		);
	}

	delete_transient( RD_BACKUP_RELEASE_TRANSIENT );
	wp_send_json_success( array( 'redirect' => admin_url( RD_BACKUP_PLUGIN_PAGE ) ) );
}
add_action( 'wp_ajax_rd_backup_install', 'rd_backup_ajax_install' );

/*
=============================================================================
 *  ENQUEUE — attach installer data to the panel bundle (Backup tab only)
 * ============================================================================= */

/**
 * Localizes the installer's ajax URL + nonce + strings onto the consolidated
 * admin-panel.js bundle, gated to the Dashboard tab (where the Backup card —
 * with its 1-click install button — now lives).
 *
 * @param string $hook Current admin page hook.
 */
function rd_backup_admin_enqueue( $hook ): void {
	if ( 'toplevel_page_rd_options' !== $hook ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab gate in admin_enqueue_scripts: decides whether to localize the installer data, doesn't process a form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
	if ( 'dashboard' !== $active_tab ) {
		return;
	}

	wp_localize_script(
		'rd-admin-panel',
		'rdBackupInstaller',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rd_backup_install' ),
			'i18n'    => array(
				'installing' => __( 'Installing & activating… this can take a moment.', 'reloaded' ),
				'failed'     => __( 'Install failed:', 'reloaded' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rd_backup_admin_enqueue' );
