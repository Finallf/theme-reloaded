<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Backup — ReloadeD Backup plugin installer / launcher                *
 *                                                                             *
 * The Backup tab no longer ships a settings export/import. Full-site backups  *
 * belong in the standalone ReloadeD Backup plugin, which captures the whole   *
 * database (these panel settings included) plus the uploads folder in a       *
 * single, portable .zip. This module turns the tab into a state-aware         *
 * launcher for that plugin:                                                    *
 *                                                                             *
 *   - missing  → 1-click install (latest STABLE GitHub release) + auto-activate
 *   - inactive → 1-click activate                                             *
 *   - active   → link to Tools → ReloadeD Backup                              *
 *                                                                             *
 * The install reuses the same GitHub-release source as the theme self-updater *
 * (mod-self-update.php), driving WP's core Plugin_Upgrader server-side.        *
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
 * admin-panel.js bundle, gated to the Backup tab.
 *
 * @param string $hook Current admin page hook.
 */
function rd_backup_admin_enqueue( $hook ): void {
	if ( 'toplevel_page_rd_options' !== $hook ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab gate in admin_enqueue_scripts: decides whether to localize the installer data, doesn't process a form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( 'backup' !== $active_tab ) {
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

/*
=============================================================================
 *  RENDER — the Backup tab UI (callback for sec_backup in panel.php)
 * ============================================================================= */

/**
 * Renders the Backup tab: a state-aware launcher for the ReloadeD Backup plugin.
 */
function rd_backup_render_panel(): void {
	rd_panel_section_header(
		array(
			'icon'  => 'backup',
			'title' => __( 'Backup', 'reloaded' ),
		)
	);

	$state = rd_backup_plugin_state();

	echo '<div class="rd-pdash">';
	echo '<div class="rd-pgrid">';

	rd_panel_card_open(
		array(
			'title' => __( 'ReloadeD Backup', 'reloaded' ),
			'desc'  => esc_html__( 'Complete, portable backups of this site — the full database (these panel settings included) plus the uploads folder, in a single .zip restorable on any host. Provided by the standalone ReloadeD Backup plugin.', 'reloaded' ),
		)
	);

	if ( 'active' === $state ) {
		rd_panel_status(
			'success',
			'<strong>' . esc_html__( 'ReloadeD Backup is installed and active.', 'reloaded' ) . '</strong>'
		);
		printf(
			'<p><a class="button button-primary" href="%1$s"><span class="dashicons dashicons-backup" aria-hidden="true"></span> %2$s</a></p>',
			esc_url( admin_url( RD_BACKUP_PLUGIN_PAGE ) ),
			esc_html__( 'Open ReloadeD Backup', 'reloaded' )
		);
	} elseif ( 'inactive' === $state ) {
		rd_panel_status(
			'info',
			'<strong>' . esc_html__( 'ReloadeD Backup is installed but not active.', 'reloaded' ) . '</strong>'
		);
		printf(
			'<p><a class="button button-primary" href="%1$s">%2$s</a></p>',
			esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . RD_BACKUP_PLUGIN_FILE ), 'activate-plugin_' . RD_BACKUP_PLUGIN_FILE ) ),
			esc_html__( 'Activate ReloadeD Backup', 'reloaded' )
		);
	} else {
		$release = rd_backup_fetch_plugin_release();
		?>
		<p>
			<button type="button" class="button button-primary" id="rd-backup-install" data-nonce="<?php echo esc_attr( wp_create_nonce( 'rd_backup_install' ) ); ?>">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Install ReloadeD Backup', 'reloaded' ); ?>
			</button>
			<span id="rd-backup-install-status" class="rd-backup-install-status" aria-live="polite"></span>
		</p>
		<?php
		if ( is_array( $release ) && ! empty( $release['version'] ) ) {
			printf(
				'<p class="rd-pcard__hint">%s</p>',
				sprintf(
					/* translators: %s: version number, e.g. 1.1.0 */
					esc_html__( 'Installs the latest stable release (v%s) from GitHub and activates it.', 'reloaded' ),
					esc_html( $release['version'] )
				)
			);
		} else {
			rd_panel_status(
				'warning',
				esc_html__( 'Could not reach GitHub to check the latest release — you can still try installing.', 'reloaded' )
			);
		}
	}

	rd_panel_card_close();

	echo '</div>'; // .rd-pgrid
	echo '</div>'; // .rd-pdash
}
