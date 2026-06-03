<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Backup — Export/Import of theme settings (Wave 7 L)                 *
 *                                                                             *
 * Allows exporting the theme configuration as JSON for download and (in       *
 * future stages) importing it back on another install. Useful for:            *
 *   - Migrate sandbox → production configs without redoing everything by hand *
 *   - Snapshot before touching risky config (1-click rollback)                *
 *   - Replicate a configured theme on another site                            *
 *   - Version config in git (readable, diff-friendly JSON)                    *
 *                                                                             *
 * Location: sub-section inside the panel's Maintenance tab.                   *
 *                                                                             *
 * Roadmap:                                                                    *
 *   Stage 1 (current): L1 — Simple export of settings only                    *
 *   Stage 2: Export includes category_colors (term meta)                      *
 *   Stage 3: L2 — Import with preview + pre-import auto-backup                *
 *   Stage 4: Restore from auto-backup (1-click rollback)                      *
 *   Stage 5: L3 — Optional export of ad_banners                               *
 *******************************************************************************/

const RD_BACKUP_SCHEMA_VERSION = 1;
const RD_BACKUP_AUTO_OPTION    = 'rd_settings_backup_before_import'; // used in Stage 4

/*
=============================================================================
 *  COLLECT — builds the data structure to export
 * ============================================================================= */

/**
 * Collects data for the requested sections in JSON-ready format with a _meta envelope.
 *
 * Supported sections:
 *   - 'settings'        → everything in rd_settings EXCEPT keys with the `ad_` prefix
 *   - 'category_colors' → custom category colors (term meta)
 *   - 'ad_banners'      → ONLY keys with the `ad_` prefix from rd_settings
 *
 * Why separate settings/ad_banners?
 *   - Allows "export ads only" (to replicate banners on another site)
 *   - Allows "export everything EXCEPT ads" (to migrate the theme without the dev's banners)
 *   - Checking both = export equivalent to the full rd_settings
 *
 * @param array $sections List of sections to include
 * @return array Structure { _meta, settings?, category_colors?, ad_banners? }
 */
function rd_backup_collect_data( array $sections = array( 'settings' ) ): array {
	global $wp_version;

	$included    = array();
	$payload     = array();
	$rd_settings = get_option( 'rd_settings', array() );
	if ( ! is_array( $rd_settings ) ) {
		$rd_settings = array();
	}

	if ( in_array( 'settings', $sections, true ) ) {
		// Settings = everything EXCEPT ad_* keys
		$payload['settings'] = array_filter(
			$rd_settings,
			fn( $k ) => strpos( $k, 'ad_' ) !== 0,
			ARRAY_FILTER_USE_KEY
		);
		$included[]          = 'settings';
	}

	if ( in_array( 'category_colors', $sections, true ) ) {
		$payload['category_colors'] = rd_backup_collect_category_colors();
		$included[]                 = 'category_colors';
	}

	if ( in_array( 'ad_banners', $sections, true ) ) {
		// Ad banners = ONLY ad_* keys
		$payload['ad_banners'] = array_filter(
			$rd_settings,
			fn( $k ) => strpos( $k, 'ad_' ) === 0,
			ARRAY_FILTER_USE_KEY
		);
		$included[]            = 'ad_banners';
	}

	// _meta always present — eases validation on future import
	$meta = array(
		'schema_version'    => RD_BACKUP_SCHEMA_VERSION,
		'theme_version'     => wp_get_theme()->get( 'Version' ),
		'exported_at'       => gmdate( 'c' ), // ISO 8601 UTC
		'exported_from'     => home_url(),
		'wordpress_version' => $wp_version,
		'sections_included' => $included,
	);

	// _meta goes on top for easy visual inspection when someone opens the JSON
	return array_merge( array( '_meta' => $meta ), $payload );
}

/**
 * Collects custom colors from ALL categories that have `rd_category_color`
 * set (term meta from `mod-category-colors.php`).
 *
 * The identifier is `slug` (not `term_id`) — slugs are portable across installs,
 * term_ids are internal to each site's DB. On import, the slug is used to
 * find the matching category at the destination.
 *
 * @return array Array of { slug, name, color } — only categories WITH a color configured
 */
function rd_backup_collect_category_colors(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'all',
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$colors = array();
	foreach ( $terms as $term ) {
		$color = get_term_meta( $term->term_id, 'rd_category_color', true );
		if ( ! empty( $color ) ) {
			$colors[] = array(
				'slug'  => $term->slug,
				'name'  => $term->name,
				'color' => $color,
			);
		}
	}

	return $colors;
}

/*
=============================================================================
 *  EXPORT HANDLER — serves the JSON as a download via admin-post.php
 * ============================================================================= */

/**
 * Handler for the admin-post.php JSON backup download.
 * Triggered by the POST form in the panel with section checkboxes.
 */
function rd_backup_handle_export() {
	// Permission + nonce checks
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'You do not have permission to export theme settings.', 'reloaded' ),
			403
		);
	}
	check_admin_referer( 'rd_backup_export' );

	// Read the selected sections (accepts GET or POST — we use GET via <a> link to
	// avoid nested-form conflicts in the panel's section callback).
	// Defensive whitelist. Falls back to 'settings' if nothing valid was passed.
	$allowed_sections = array( 'settings', 'category_colors', 'ad_banners' );

	if ( isset( $_GET['sections'] ) && is_array( $_GET['sections'] ) ) {
		$raw_sections = array_map( 'sanitize_key', wp_unslash( $_GET['sections'] ) );
	} elseif ( isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ) {
		$raw_sections = array_map( 'sanitize_key', wp_unslash( $_POST['sections'] ) );
	} else {
		$raw_sections = array( 'settings' );
	}

	$sections = array_values( array_intersect( $raw_sections, $allowed_sections ) );
	if ( empty( $sections ) ) {
		$sections = array( 'settings' ); // defensive fallback
	}

	$data = rd_backup_collect_data( $sections );

	// Filename: reloaded-backup-{host}-{date}.json
	// Clean host (no dots/ports) to avoid issues on various filesystems
	$host     = wp_parse_url( home_url(), PHP_URL_HOST );
	$host     = $host ? sanitize_file_name( $host ) : 'site';
	$filename = 'reloaded-backup-' . $host . '-' . gmdate( 'Y-m-d' ) . '.json';

	// Send headers + body
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'admin_post_rd_backup_export', 'rd_backup_handle_export' );

/*
=============================================================================
 *  IMPORT — validate, preview, apply
 * ============================================================================= */

/**
 * Validates the imported JSON structure. Returns sanitized $data or WP_Error.
 *
 * Checks:
 *   - is an array (json_decode with assoc=true)
 *   - has _meta with schema_version
 *   - schema_version <= RD_BACKUP_SCHEMA_VERSION (can't import from a future version)
 *   - has at least one known section
 *   - each section has the expected structure
 *
 * @param mixed $data Already-decoded data (array). Raw strings/JSON must be decoded first
 * @return array|WP_Error Validated/sanitized data or a descriptive error
 */
function rd_backup_validate( $data ) {
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'invalid_format', __( 'Backup file is not a valid JSON object.', 'reloaded' ) );
	}

	if ( empty( $data['_meta'] ) || ! is_array( $data['_meta'] ) ) {
		return new WP_Error( 'missing_meta', __( 'Backup file is missing the _meta envelope (not a ReloadeD backup?).', 'reloaded' ) );
	}

	$schema = isset( $data['_meta']['schema_version'] ) ? (int) $data['_meta']['schema_version'] : 0;
	if ( $schema < 1 || $schema > RD_BACKUP_SCHEMA_VERSION ) {
		return new WP_Error(
			'incompatible_schema',
			sprintf(
				/* translators: 1: schema version found in the file; 2: schema version this theme supports */
				__( 'Backup schema version %1$d is not supported by this theme (max: %2$d). Maybe the file is from a newer version of ReloadeD?', 'reloaded' ),
				$schema,
				RD_BACKUP_SCHEMA_VERSION
			)
		);
	}

	$known_sections = array( 'settings', 'category_colors', 'ad_banners' );
	$present        = array_intersect( $known_sections, array_keys( $data ) );
	if ( empty( $present ) ) {
		return new WP_Error( 'empty_payload', __( 'Backup file contains no recognized sections (settings, category_colors, ad_banners).', 'reloaded' ) );
	}

	// Sanitize section by section
	$clean = array( '_meta' => $data['_meta'] );

	if ( isset( $data['settings'] ) ) {
		if ( ! is_array( $data['settings'] ) ) {
			return new WP_Error( 'invalid_settings', __( 'Settings section must be an object.', 'reloaded' ) );
		}
		// Reuse the panel's sanitize — ensures full consistency with the normal save
		$clean['settings'] = rd_options_sanitize( $data['settings'] );
	}

	if ( isset( $data['category_colors'] ) ) {
		if ( ! is_array( $data['category_colors'] ) ) {
			return new WP_Error( 'invalid_category_colors', __( 'Category colors section must be a list.', 'reloaded' ) );
		}
		$clean['category_colors'] = array();
		foreach ( $data['category_colors'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['slug'] ) || empty( $item['color'] ) ) {
				continue; // skip malformed items instead of failing everything
			}
			$color = sanitize_hex_color( $item['color'] );
			if ( ! $color ) {
				continue; // invalid hex, skip
			}
			$clean['category_colors'][] = array(
				'slug'  => sanitize_title( $item['slug'] ),
				'name'  => isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '',
				'color' => $color,
			);
		}
	}

	if ( isset( $data['ad_banners'] ) ) {
		if ( ! is_array( $data['ad_banners'] ) ) {
			return new WP_Error( 'invalid_ad_banners', __( 'Ad banners section must be an object.', 'reloaded' ) );
		}
		// Sanitize via rd_options_sanitize (ad zones fall under rule 1 — preserves HTML)
		// Force the ad_ prefix to avoid contamination from other keys passed by mistake
		$ad_only             = array_filter( $data['ad_banners'], fn( $k ) => strpos( $k, 'ad_' ) === 0, ARRAY_FILTER_USE_KEY );
		$clean['ad_banners'] = rd_options_sanitize( $ad_only );
	}

	return $clean;
}

/**
 * Computes the diff between the current state and the imported state.
 * Does NOT apply anything — just builds the report for preview.
 *
 * @param array $data Already-validated data (output of rd_backup_validate)
 * @return array {
 *   settings: { will_update: [...], will_add: [...], will_keep: [...], total_in_file: int },
 *   category_colors: { will_update: [...], will_add: [...], skipped_no_match: [...], total_in_file: int },
 * }
 */
function rd_backup_preview_diff( array $data ): array {
	$diff = array();

	// SETTINGS
	if ( isset( $data['settings'] ) ) {
		$current = get_option( 'rd_settings', array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		// If the file HAS an ad_banners section, the current ad_* are processed
		// there — they shouldn't appear as "will_keep" in the settings diff (they'd be
		// double-counted with the ad_banners diff). When the file does NOT have
		// ad_banners, the current ad_* appear as "will_keep" (correct: they'll be
		// preserved because the import doesn't touch ads).
		if ( isset( $data['ad_banners'] ) ) {
			$current = array_filter( $current, fn( $k ) => strpos( $k, 'ad_' ) !== 0, ARRAY_FILTER_USE_KEY );
		}

		$will_update = array();
		$will_add    = array();
		$will_keep   = array_diff_key( $current, $data['settings'] ); // current keys not present in the import

		foreach ( $data['settings'] as $key => $new_val ) {
			if ( ! array_key_exists( $key, $current ) ) {
				$will_add[ $key ] = $new_val;
			} elseif ( $current[ $key ] !== $new_val ) {
				$will_update[ $key ] = array(
					'from' => $current[ $key ],
					'to'   => $new_val,
				);
			}
			// if the value is equal, it doesn't even appear in the diff (nothing changes)
		}

		$diff['settings'] = array(
			'will_update'   => $will_update,
			'will_add'      => $will_add,
			'will_keep'     => array_keys( $will_keep ),
			'total_in_file' => count( $data['settings'] ),
		);
	}

	// AD_BANNERS — comparison similar to settings, but only ad_* keys
	if ( isset( $data['ad_banners'] ) ) {
		$current = get_option( 'rd_settings', array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		// Filter only ad_* from the current state to compare like with like
		$current_ads = array_filter( $current, fn( $k ) => strpos( $k, 'ad_' ) === 0, ARRAY_FILTER_USE_KEY );

		$will_update = array();
		$will_add    = array();
		$will_keep   = array_diff_key( $current_ads, $data['ad_banners'] );

		foreach ( $data['ad_banners'] as $key => $new_val ) {
			if ( ! array_key_exists( $key, $current_ads ) ) {
				$will_add[ $key ] = $new_val;
			} elseif ( $current_ads[ $key ] !== $new_val ) {
				$will_update[ $key ] = array(
					'from' => $current_ads[ $key ],
					'to'   => $new_val,
				);
			}
		}

		$diff['ad_banners'] = array(
			'will_update'   => $will_update,
			'will_add'      => $will_add,
			'will_keep'     => array_keys( $will_keep ),
			'total_in_file' => count( $data['ad_banners'] ),
		);
	}

	// CATEGORY_COLORS
	if ( isset( $data['category_colors'] ) ) {
		$will_update      = array();
		$will_add         = array();
		$skipped_no_match = array();

		foreach ( $data['category_colors'] as $item ) {
			$term = get_term_by( 'slug', $item['slug'], 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				$skipped_no_match[] = $item;
				continue;
			}
			$current_color = get_term_meta( $term->term_id, 'rd_category_color', true );
			if ( empty( $current_color ) ) {
				$will_add[] = $item;
			} elseif ( $current_color !== $item['color'] ) {
				$will_update[] = array_merge( $item, array( 'from' => $current_color ) );
			}
		}

		$diff['category_colors'] = array(
			'will_update'      => $will_update,
			'will_add'         => $will_add,
			'skipped_no_match' => $skipped_no_match,
			'total_in_file'    => count( $data['category_colors'] ),
		);
	}

	return $diff;
}

/**
 * Applies the import. BEFORE applying, saves a snapshot of the current state in
 * RD_BACKUP_AUTO_OPTION to allow rollback (stage 4).
 *
 * @param array $data Already-validated/sanitized data
 * @return array { applied: { settings: bool, category_colors: int }, snapshot_saved: bool }
 */
function rd_backup_apply_import( array $data ): array {
	$result = array(
		'applied'        => array(
			'settings'        => false,
			'category_colors' => 0,
			'ad_banners'      => 0,
		),
		'snapshot_saved' => false,
	);

	// 1. Current snapshot (auto-rollback) — only for rd_settings for now.
	// Category colors don't go into the snapshot (they're term meta, not an option).
	// If we want full rollback in stage 4, we expand it.
	$current_settings = get_option( 'rd_settings', array() );
	update_option(
		RD_BACKUP_AUTO_OPTION,
		array(
			'saved_at' => time(),
			'settings' => is_array( $current_settings ) ? $current_settings : array(),
		),
		false
	);
	$result['snapshot_saved'] = true;

	// 2. Settings + Ad banners — both go to the same rd_settings option via merge
	if ( isset( $data['settings'] ) || isset( $data['ad_banners'] ) ) {
		$current = get_option( 'rd_settings', array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		// MERGE: applies settings + ad_banners on top of the current state.
		// Preserves current keys not present in either (e.g. if you imported
		// only "settings" without ads, current ads stay intact. If you imported only ads,
		// settings stay intact).
		$merged = array_merge(
			$current,
			$data['settings'] ?? array(),
			$data['ad_banners'] ?? array()
		);
		update_option( 'rd_settings', $merged );
		if ( isset( $data['settings'] ) ) {
			$result['applied']['settings'] = true;
		}
		if ( isset( $data['ad_banners'] ) ) {
			$result['applied']['ad_banners'] = count( $data['ad_banners'] );
		}
	}

	// 3. Category colors
	if ( isset( $data['category_colors'] ) ) {
		foreach ( $data['category_colors'] as $item ) {
			$term = get_term_by( 'slug', $item['slug'], 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue; // category doesn't exist on this install — silent skip
			}
			update_term_meta( $term->term_id, 'rd_category_color', $item['color'] );
			++$result['applied']['category_colors'];
		}
	}

	return $result;
}

/*
=============================================================================
 *  AUTO-SNAPSHOT — management of the state saved before the last import
 * ============================================================================= */

/**
 * Returns the snapshot automatically saved before the last import.
 *
 * @return array|null { saved_at: int, settings: array } or null if none exists
 */
function rd_backup_get_last_snapshot(): ?array {
	$snap = get_option( RD_BACKUP_AUTO_OPTION, null );
	if ( ! is_array( $snap ) || empty( $snap['settings'] ) ) {
		return null;
	}
	return $snap;
}

/**
 * Restores the state saved in the snapshot. Consumes the snapshot after use (delete
 * to avoid an accidental double-restore and give clear feedback that "there's no more
 * undo available").
 *
 * @return bool true if restored, false if there was no snapshot
 */
function rd_backup_restore_snapshot(): bool {
	$snap = rd_backup_get_last_snapshot();
	if ( ! $snap ) {
		return false;
	}

	update_option( 'rd_settings', $snap['settings'] );
	delete_option( RD_BACKUP_AUTO_OPTION ); // snapshot consumed

	return true;
}

/*
=============================================================================
 *  REST ENDPOINTS — preview and import (consumed by admin-panel.js, backup module)
 * ============================================================================= */

/**
 * Registers the Backup module's REST endpoints.
 */
function rd_backup_register_endpoints() {
	// Preview: receives JSON, validates, returns diff (applies nothing)
	register_rest_route(
		'rd/v1',
		'/backup/preview',
		array(
			'methods'             => 'POST',
			'callback'            => 'rd_backup_rest_preview',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	// Import: receives validated JSON, applies it (with auto-snapshot before)
	register_rest_route(
		'rd/v1',
		'/backup/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'rd_backup_rest_import',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	// Restore: restores the last automatic snapshot (generated before the last import)
	register_rest_route(
		'rd/v1',
		'/backup/restore',
		array(
			'methods'             => 'POST',
			'callback'            => 'rd_backup_rest_restore',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'rd_backup_register_endpoints' );

/**
 * Endpoint POST /wp-json/rd/v1/backup/preview — validates JSON and returns the diff.
 */
function rd_backup_rest_preview( WP_REST_Request $request ) {
	$data = $request->get_json_params();

	$validated = rd_backup_validate( $data );
	if ( is_wp_error( $validated ) ) {
		return new WP_REST_Response(
			array(
				'ok'    => false,
				'error' => $validated->get_error_message(),
				'code'  => $validated->get_error_code(),
			),
			400
		);
	}

	$diff = rd_backup_preview_diff( $validated );

	return new WP_REST_Response(
		array(
			'ok'   => true,
			'meta' => $validated['_meta'],
			'diff' => $diff,
		),
		200
	);
}

/**
 * Endpoint POST /wp-json/rd/v1/backup/restore — restores the last snapshot.
 *
 * @param WP_REST_Request $request Request body (unused — endpoint takes no args).
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $request is part of WP's mandatory REST callback signature, even when the endpoint consumes no args.
function rd_backup_rest_restore( WP_REST_Request $request ) {
	$restored = rd_backup_restore_snapshot();
	if ( ! $restored ) {
		return new WP_REST_Response(
			array(
				'ok'    => false,
				'error' => __( 'No snapshot available — restore unavailable.', 'reloaded' ),
			),
			404
		);
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Endpoint POST /wp-json/rd/v1/backup/import — applies the import (with snapshot).
 */
function rd_backup_rest_import( WP_REST_Request $request ) {
	$data = $request->get_json_params();

	$validated = rd_backup_validate( $data );
	if ( is_wp_error( $validated ) ) {
		return new WP_REST_Response(
			array(
				'ok'    => false,
				'error' => $validated->get_error_message(),
				'code'  => $validated->get_error_code(),
			),
			400
		);
	}

	$result = rd_backup_apply_import( $validated );

	return new WP_REST_Response(
		array(
			'ok'     => true,
			'result' => $result,
		),
		200
	);
}

/*
=============================================================================
 *  ADMIN ENQUEUE — backup JS data only on the panel's Backup tab
 * ============================================================================= */

/**
 * Localizes the backup import/export data, only on the panel's Backup tab.
 * The JS itself ships in the consolidated admin-panel.js bundle.
 */
function rd_backup_admin_enqueue( $hook ) {
	if ( $hook !== 'toplevel_page_rd_options' ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate in admin_enqueue_scripts: decides whether to enqueue the backup JS, doesn't process a form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( $active_tab !== 'backup' ) {
		return;
	}

	// The backup import/export JS now ships in the consolidated admin-panel.js
	// bundle (enqueued in core.php at priority 5). Here we only attach its data
	// (REST URL + nonce + i18n) to that handle, still gated to the Backup tab.
	wp_localize_script(
		'rd-admin-panel',
		'rdBackup',
		array(
			'restUrl' => esc_url_raw( rest_url( 'rd/v1/backup/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'reading'               => __( 'Reading file...', 'reloaded' ),
				'invalidJson'           => __( 'File is not valid JSON.', 'reloaded' ),
				'previewing'            => __( 'Validating and calculating diff...', 'reloaded' ),
				'importing'             => __( 'Applying import...', 'reloaded' ),
				'importSuccess'         => __( 'Import applied! Reloading page...', 'reloaded' ),
				'importFailed'          => __( 'Import failed:', 'reloaded' ),
				'confirmApply'          => __( 'Confirm import? This will overwrite the current settings (an auto-backup will be saved before).', 'reloaded' ),
				'restoring'             => __( 'Restoring previous state...', 'reloaded' ),
				'restoreSuccess'        => __( 'Previous state restored! Reloading...', 'reloaded' ),
				'restoreFailed'         => __( 'Restore failed:', 'reloaded' ),
				'confirmRestore'        => __( 'Restore the previous state (before the last import)? The current settings will be overwritten. This is a one-shot undo — the snapshot is deleted after restore.', 'reloaded' ),
				// Preview UI strings (populated by admin-panel.js after a successful preview)
				'previewTitle'          => __( 'Preview', 'reloaded' ),
				'previewExportedFrom'   => __( 'Exported from:', 'reloaded' ),
				/* translators: %s = theme version string like "0.4.0-beta.64" */
				'previewThemeVersion'   => __( 'theme %s', 'reloaded' ),
				'sectionSettings'       => __( 'Settings', 'reloaded' ),
				'sectionAdBanners'      => __( 'Ad banners', 'reloaded' ),
				'sectionCategoryColors' => __( 'Category colors', 'reloaded' ),
				/* translators: %d = number of keys/items that will be updated by the import */
				'diffUpdate'            => __( '%d will be UPDATED', 'reloaded' ),
				/* translators: %d = number of keys/items that will be added by the import */
				'diffAdd'               => __( '%d will be ADDED', 'reloaded' ),
				/* translators: %d = number of settings keys currently set but absent from the import file */
				'diffKeepSettings'      => __( '%d current keys NOT in file (will be kept)', 'reloaded' ),
				/* translators: %d = number of ad banner slots currently filled but absent from the import file */
				'diffKeepAds'           => __( '%d current ads NOT in file (will be kept)', 'reloaded' ),
				/* translators: %d = number of category color entries skipped because no category with that slug exists on this site */
				'diffSkipped'           => __( '%d SKIPPED (no matching slug here)', 'reloaded' ),
				'showChangedKeys'       => __( 'Show keys that will change', 'reloaded' ),
				'applyImport'           => __( 'Apply import', 'reloaded' ),
				'cancel'                => __( 'Cancel', 'reloaded' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rd_backup_admin_enqueue' );

/*
=============================================================================
 *  RENDER — UI of the Backup sub-section in Maintenance
 * ============================================================================= */

/**
 * Renders the Backup & Restore tab UI. Callback for `sec_backup` in panel.php
 * (Wave 11 — promoted from a Maintenance sub-section to its own tab).
 *
 * Stage 2: Export with checkboxes to choose sections (settings + category_colors).
 * The Import UI comes in Stage 3.
 */
function rd_backup_render_panel(): void {
	// Section header with icon (empty title in add_settings_section avoids a duplicate <h2>).
	rd_panel_section_header(
		array(
			'icon'  => 'backup',
			'title' => __( 'Backup', 'reloaded' ),
		)
	);

	// Count custom colors + ad banners to show in the checkbox hints.
	$custom_colors_count   = count( rd_backup_collect_category_colors() );
	$rd_settings_for_count = get_option( 'rd_settings', array() );
	$ad_banners_count      = is_array( $rd_settings_for_count )
		? count( array_filter( $rd_settings_for_count, fn( $k ) => strpos( $k, 'ad_' ) === 0, ARRAY_FILTER_USE_KEY ) )
		: 0;

	/*
	 * Initial download URL (with the 3 sections checked by default).
	 * Important: we use an <a> LINK instead of <form>+<button> because this
	 * section callback runs INSIDE the panel's <form action="options.php"> —
	 * nested HTML forms are ignored by the browser, making
	 * the submit land on the parent form (options.php). An <a> link doesn't have that
	 * problem. JS updates the href as the checkboxes change.
	 */
	$admin_post_url = admin_url( 'admin-post.php' );
	$export_nonce   = wp_create_nonce( 'rd_backup_export' );
	$export_url     = add_query_arg(
		array(
			'action'   => 'rd_backup_export',
			'_wpnonce' => $export_nonce,
			'sections' => array( 'settings', 'category_colors', 'ad_banners' ),
		),
		$admin_post_url
	);

	/*
	 * Layout: 2 rows stacked inside the same .rd-pdash.
	 *   Row 1: Export full-width (3 checkboxes side by side on desktop)
	 *   Row 2: Import 33% | Restore 67% (Import alone takes 100% when there's no snapshot)
	 * Spacing between rows via CSS (.rd-pdash > .rd-pgrid + .rd-pgrid { margin-top: 20px; })
	 */
	echo '<div class="rd-pdash">';

	// === ROW 1: Export full-width ===
	echo '<div class="rd-pgrid">';

	rd_panel_card_open(
		array(
			'title' => __( 'Export', 'reloaded' ),
			'desc'  => esc_html__( 'Download a JSON file with the theme configuration from this install. Useful for migration (sandbox → production), snapshots before risky changes, or replicating the theme on another site.', 'reloaded' ),
			// hint moved inline next to the Download button (more visible and
			// contextual — the user sees the name template at the moment of action).
		)
	);

	// Boundary clarity banner — makes explicit that this is NOT a full site backup.
	// Info variant (WP blue) — informational, non-blocking.
	rd_panel_status(
		'info',
		'<strong>' . esc_html__( 'Theme configuration backup only.', 'reloaded' ) . '</strong> '
			. esc_html__( 'This file contains panel options, category colors and ad codes. It does NOT include posts, pages, comments, media files or WordPress core data. For full site backups, use a dedicated plugin (UpdraftPlus, WPVivid, etc.) or your hosting provider\'s tools.', 'reloaded' )
	);
	?>
	<fieldset class="rd-backup-sections" id="rd-backup-export-sections">
		<legend class="screen-reader-text"><?php esc_html_e( 'Sections to include in the export', 'reloaded' ); ?></legend>

		<label class="rd-backup-section">
			<input type="checkbox" class="rd-backup-export-cb" value="settings" checked>
			<span class="rd-backup-section__label">
				<strong><?php esc_html_e( 'Settings', 'reloaded' ); ?></strong>
				<small><?php esc_html_e( 'All panel toggles, IDs and texts (rd_settings option) — single key in the database, fully portable between sites', 'reloaded' ); ?></small>
			</span>
		</label>

		<label class="rd-backup-section">
			<input type="checkbox" class="rd-backup-export-cb" value="category_colors" checked>
			<span class="rd-backup-section__label">
				<strong><?php esc_html_e( 'Category colors', 'reloaded' ); ?></strong>
				<small>
					<?php
					/* translators: %d = number of categories with custom color set */
					printf( esc_html( _n( '%d category with custom color', '%d categories with custom colors', $custom_colors_count, 'reloaded' ) ), (int) $custom_colors_count );
					?>
				</small>
			</span>
		</label>

		<label class="rd-backup-section">
			<input type="checkbox" class="rd-backup-export-cb" value="ad_banners" checked>
			<span class="rd-backup-section__label">
				<strong><?php esc_html_e( 'Ad banners', 'reloaded' ); ?></strong>
				<small>
					<?php
					/* translators: %d = number of ad zones with content (keys starting with ad_) */
					printf( esc_html( _n( '%d ad zone with content', '%d ad zones with content', $ad_banners_count, 'reloaded' ) ), (int) $ad_banners_count );
					?>
					— <?php esc_html_e( 'tip: uncheck if migrating to another site (ad codes are often site-specific)', 'reloaded' ); ?>
				</small>
			</span>
		</label>
	</fieldset>

	<p class="rd-pcard__action-row">
		<span class="rd-backup-export-filename">
			<?php
			printf(
				/* translators: %s = filename template like reloaded-backup-{host}-{YYYY-MM-DD}.json */
				esc_html__( 'Filename: %s', 'reloaded' ),
				'<code>reloaded-backup-{host}-{YYYY-MM-DD}.json</code>'
			);
			?>
		</span>
		<a id="rd-backup-export-link"
			href="<?php echo esc_url( $export_url ); ?>"
			data-base-url="<?php echo esc_attr( $admin_post_url ); ?>"
			data-action="rd_backup_export"
			data-nonce="<?php echo esc_attr( $export_nonce ); ?>"
			class="button button-primary">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Download backup JSON', 'reloaded' ); ?>
		</a>
	</p>
	<?php
	rd_panel_card_close();

	echo '</div>'; // .rd-pgrid (Row 1)

	/*
	 * === ROW 2: Import (full-width) ===
	 * === ROW 3: Restore (full-width, condicional) ===
	 *
	 * Import and Restore cards each in their own row (full-width). Side-by-side
	 * got cramped when the diff preview loaded. Restore only renders if
	 * there's a snapshot available (auto-snapshot created by the last import).
	 */
	$last_snapshot = rd_backup_get_last_snapshot();
	echo '<div class="rd-pgrid">';

	// === Card: Import ===
	rd_panel_card_open(
		array(
			'title' => __( 'Import', 'reloaded' ),
			'desc'  => esc_html__( 'Upload a backup JSON, preview the diff, then apply. An auto-snapshot of the current state is saved before applying — safe to experiment.', 'reloaded' ),
		)
	);
	?>
	<p class="rd-pcard__action-row">
		<span class="rd-backup-import-explainer">
			<?php esc_html_e( 'Use the Restore card (below) to undo if needed.', 'reloaded' ); ?>
		</span>
		<label for="rd-backup-file" class="button">
			<span class="dashicons dashicons-upload"></span>
			<?php esc_html_e( 'Choose backup JSON', 'reloaded' ); ?>
		</label>
		<input type="file" id="rd-backup-file" accept=".json,application/json" style="display:none">
	</p>

	<div class="rd-backup-filename-row">
		<span id="rd-backup-filename" class="rd-backup-filename" aria-live="polite"></span>
	</div>

	<div id="rd-backup-preview" class="rd-backup-preview" hidden>
		<!-- Content populated by admin-panel.js after a successful preview. -->
	</div>

	<div id="rd-backup-status" class="rd-pstatus" hidden role="status" aria-live="polite"></div>
	<?php
	rd_panel_card_close();

	echo '</div>'; // .rd-pgrid (Row 2 — Import)

	// === Card: Restore (only appears if a snapshot is available) ===
	// "warning" variant (amber accent). Rendered in its own row,
	// full-width below Import.
	if ( $last_snapshot ) {
		echo '<div class="rd-pgrid">';
		$when     = (int) ( $last_snapshot['saved_at'] ?? 0 );
		$when_fmt = $when ? wp_date( 'd/m/Y H:i', $when ) : '—';

		rd_panel_card_open(
			array(
				'variant' => 'warning',
				'title'   => __( 'Restore previous state', 'reloaded' ),
				'desc'    => sprintf(
					/* translators: %s = formatted date/time of the last auto-snapshot */
					esc_html__( 'Auto-snapshot saved before the last import: %s. You can restore that exact state in one click. After restoring, the snapshot is deleted (one-shot undo).', 'reloaded' ),
					'<strong>' . esc_html( $when_fmt ) . '</strong>'
				),
			)
		);
		?>
		<p class="rd-pcard__action-row">
			<button type="button" id="rd-backup-restore-btn" class="button">
				<span class="dashicons dashicons-undo"></span>
				<?php esc_html_e( 'Restore previous state', 'reloaded' ); ?>
			</button>
		</p>
		<div id="rd-backup-restore-status" class="rd-pstatus" hidden role="status" aria-live="polite"></div>
		<?php
		rd_panel_card_close();
		echo '</div>'; // .rd-pgrid (Row 3 — Restore)
	}

	echo '</div>'; // .rd-pdash
}
