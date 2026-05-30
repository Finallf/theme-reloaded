<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Backup — Export/Import de configurações do tema (Wave 7 L)          *
 *                                                                             *
 * Permite exportar a configuração do tema em JSON pra download e (em etapas   *
 * futuras) importar de volta em outro install ou no mesmo. Útil pra:          *
 *   - Migrar configs sandbox → produção sem refazer tudo na mão               *
 *   - Snapshot antes de mexer em config arriscada (rollback em 1 click)       *
 *   - Replicar tema configurado em outro site                                 *
 *   - Versionar config no git (JSON legível diff-friendly)                    *
 *                                                                             *
 * Localização: sub-seção dentro da aba Manutenção do painel.                  *
 *                                                                             *
 * Roadmap:                                                                    *
 *   Etapa 1 (atual): L1 — Export simples só de settings                       *
 *   Etapa 2: Export inclui category_colors (term meta)                        *
 *   Etapa 3: L2 — Import com preview + auto-backup pre-import                 *
 *   Etapa 4: Restore do auto-backup (1-click rollback)                        *
 *   Etapa 5: L3 — Export opcional de ad_banners                               *
 *******************************************************************************/

const RD_BACKUP_SCHEMA_VERSION = 1;
const RD_BACKUP_AUTO_OPTION    = 'rd_settings_backup_before_import'; // usado na Etapa 4

/*
=============================================================================
 *  COLLECT — monta a estrutura de dados pra exportar
 * ============================================================================= */

/**
 * Coleta dados das seções solicitadas em formato JSON-ready com _meta envelope.
 *
 * Seções suportadas:
 *   - 'settings'        → tudo de rd_settings EXCETO keys com prefixo `ad_`
 *   - 'category_colors' → cores customizadas das categorias (term meta)
 *   - 'ad_banners'      → APENAS keys com prefixo `ad_` de rd_settings
 *
 * Por que separar settings/ad_banners?
 *   - Permite "exportar só ads" (pra replicar banners em outro site)
 *   - Permite "exportar tudo MENOS ads" (pra migrar tema sem banners do dev)
 *   - Marcando ambos = export equivalente ao rd_settings completo
 *
 * @param array $sections Lista de seções a incluir
 * @return array Estrutura { _meta, settings?, category_colors?, ad_banners? }
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
		// Settings = tudo MENOS keys ad_*
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
		// Ad banners = APENAS keys ad_*
		$payload['ad_banners'] = array_filter(
			$rd_settings,
			fn( $k ) => strpos( $k, 'ad_' ) === 0,
			ARRAY_FILTER_USE_KEY
		);
		$included[]            = 'ad_banners';
	}

	// _meta sempre presente — facilita validação na importação futura
	$meta = array(
		'schema_version'    => RD_BACKUP_SCHEMA_VERSION,
		'theme_version'     => wp_get_theme()->get( 'Version' ),
		'exported_at'       => gmdate( 'c' ), // ISO 8601 UTC
		'exported_from'     => home_url(),
		'wordpress_version' => $wp_version,
		'sections_included' => $included,
	);

	// _meta vai no topo pra inspeção visual fácil quando alguém abre o JSON
	return array_merge( array( '_meta' => $meta ), $payload );
}

/**
 * Coleta cores customizadas de TODAS as categorias que têm `rd_category_color`
 * definido (term meta do `mod-category-colors.php`).
 *
 * Identificador é `slug` (não `term_id`) — slugs são portáveis entre instalações,
 * term_ids são internos do DB de cada site. Na importação, o slug é usado pra
 * encontrar a categoria correspondente no destino.
 *
 * @return array Array de { slug, name, color } — só categorias COM cor configurada
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
 *  EXPORT HANDLER — serve o JSON como download via admin-post.php
 * ============================================================================= */

/**
 * Handler do admin-post.php pra download do backup JSON.
 * Acionado pelo form POST no painel com checkboxes de seções.
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

	// Lê seções selecionadas (aceita GET ou POST — usamos GET via link <a> pra
	// evitar conflito de forms aninhados no callback de section do painel).
	// Whitelist defensiva. Fallback pra 'settings' se nada válido foi passado.
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
		$sections = array( 'settings' ); // fallback defensivo
	}

	$data = rd_backup_collect_data( $sections );

	// Nome do arquivo: reloaded-backup-{host}-{date}.json
	// Host limpo (sem pontos/portas) pra evitar issues em filesystems variados
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
 * Valida estrutura do JSON importado. Retorna $data sanitizado ou WP_Error.
 *
 * Checks:
 *   - é array (json_decode com assoc=true)
 *   - tem _meta com schema_version
 *   - schema_version <= RD_BACKUP_SCHEMA_VERSION (não pode importar de versão futura)
 *   - tem pelo menos uma seção conhecida
 *   - cada seção tem estrutura esperada
 *
 * @param mixed $data Dado já decodificado (array). Strings/JSON cru devem ser decodificados antes
 * @return array|WP_Error Dado validado/sanitizado ou erro descritivo
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

	// Sanitiza seção por seção
	$clean = array( '_meta' => $data['_meta'] );

	if ( isset( $data['settings'] ) ) {
		if ( ! is_array( $data['settings'] ) ) {
			return new WP_Error( 'invalid_settings', __( 'Settings section must be an object.', 'reloaded' ) );
		}
		// Reusa o sanitize do painel — garante consistência total com o save normal
		$clean['settings'] = rd_options_sanitize( $data['settings'] );
	}

	if ( isset( $data['category_colors'] ) ) {
		if ( ! is_array( $data['category_colors'] ) ) {
			return new WP_Error( 'invalid_category_colors', __( 'Category colors section must be a list.', 'reloaded' ) );
		}
		$clean['category_colors'] = array();
		foreach ( $data['category_colors'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['slug'] ) || empty( $item['color'] ) ) {
				continue; // skip itens malformados em vez de falhar tudo
			}
			$color = sanitize_hex_color( $item['color'] );
			if ( ! $color ) {
				continue; // hex inválido, skip
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
		// Sanitize via rd_options_sanitize (zonas de anúncio caem na regra 1 — preserva HTML)
		// Force prefixo ad_ pra evitar contaminação de outras keys passadas por engano
		$ad_only             = array_filter( $data['ad_banners'], fn( $k ) => strpos( $k, 'ad_' ) === 0, ARRAY_FILTER_USE_KEY );
		$clean['ad_banners'] = rd_options_sanitize( $ad_only );
	}

	return $clean;
}

/**
 * Calcula diff entre o estado atual e o estado importado.
 * NÃO aplica nada — só monta o relatório pra preview.
 *
 * @param array $data Dado já validado (saída de rd_backup_validate)
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

		// Se o arquivo TEM seção ad_banners, ad_* do current são processados
		// lá — não devem aparecer como "will_keep" no diff de settings (seriam
		// double-contadas com o diff de ad_banners). Quando o arquivo NÃO tem
		// ad_banners, ad_* atuais aparecem como "will_keep" (correto: serão
		// preservadas porque o import não toca em ads).
		if ( isset( $data['ad_banners'] ) ) {
			$current = array_filter( $current, fn( $k ) => strpos( $k, 'ad_' ) !== 0, ARRAY_FILTER_USE_KEY );
		}

		$will_update = array();
		$will_add    = array();
		$will_keep   = array_diff_key( $current, $data['settings'] ); // keys atuais não presentes no import

		foreach ( $data['settings'] as $key => $new_val ) {
			if ( ! array_key_exists( $key, $current ) ) {
				$will_add[ $key ] = $new_val;
			} elseif ( $current[ $key ] !== $new_val ) {
				$will_update[ $key ] = array(
					'from' => $current[ $key ],
					'to'   => $new_val,
				);
			}
			// se valor igual, nem aparece no diff (não muda nada)
		}

		$diff['settings'] = array(
			'will_update'   => $will_update,
			'will_add'      => $will_add,
			'will_keep'     => array_keys( $will_keep ),
			'total_in_file' => count( $data['settings'] ),
		);
	}

	// AD_BANNERS — comparação similar a settings, mas só keys ad_*
	if ( isset( $data['ad_banners'] ) ) {
		$current = get_option( 'rd_settings', array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		// Filtra só ad_* do estado atual pra comparar com mesma natureza
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
 * Aplica importação. ANTES de aplicar, salva snapshot do estado atual em
 * RD_BACKUP_AUTO_OPTION pra permitir rollback (etapa 4).
 *
 * @param array $data Dado já validado/sanitizado
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

	// 1. Snapshot atual (auto-rollback) — só pra rd_settings por enquanto.
	// Cores de categoria não entram no snapshot (são term meta, não option).
	// Se quisermos rollback completo na etapa 4, expandimos.
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

	// 2. Settings + Ad banners — ambos vão pro mesmo option rd_settings via merge
	if ( isset( $data['settings'] ) || isset( $data['ad_banners'] ) ) {
		$current = get_option( 'rd_settings', array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		// MERGE: aplica settings + ad_banners por cima do estado atual.
		// Preserva keys atuais não presentes em nenhum dos dois (ex: se importou
		// só "settings" sem ads, ads atuais ficam intactos. Se importou só ads,
		// settings ficam intactos).
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
				continue; // categoria não existe nesse install — skip silencioso
			}
			update_term_meta( $term->term_id, 'rd_category_color', $item['color'] );
			++$result['applied']['category_colors'];
		}
	}

	return $result;
}

/*
=============================================================================
 *  AUTO-SNAPSHOT — gerenciamento do estado salvo antes do último import
 * ============================================================================= */

/**
 * Retorna o snapshot salvo automaticamente antes do último import.
 *
 * @return array|null { saved_at: int, settings: array } ou null se não existe
 */
function rd_backup_get_last_snapshot(): ?array {
	$snap = get_option( RD_BACKUP_AUTO_OPTION, null );
	if ( ! is_array( $snap ) || empty( $snap['settings'] ) ) {
		return null;
	}
	return $snap;
}

/**
 * Restaura o estado salvo no snapshot. Consume o snapshot após uso (delete
 * pra evitar duplo-restore acidental e dar feedback claro de "não há mais
 * undo disponível").
 *
 * @return bool true se restaurou, false se não havia snapshot
 */
function rd_backup_restore_snapshot(): bool {
	$snap = rd_backup_get_last_snapshot();
	if ( ! $snap ) {
		return false;
	}

	update_option( 'rd_settings', $snap['settings'] );
	delete_option( RD_BACKUP_AUTO_OPTION ); // snapshot consumido

	return true;
}

/*
=============================================================================
 *  REST ENDPOINTS — preview e import (consumidos pelo admin-backup.js)
 * ============================================================================= */

/**
 * Registra os endpoints REST do módulo Backup.
 */
function rd_backup_register_endpoints() {
	// Preview: recebe JSON, valida, retorna diff (não aplica nada)
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

	// Import: recebe JSON validado, aplica (com auto-snapshot antes)
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

	// Restore: restaura o último snapshot automático (gerado antes do último import)
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
 * Endpoint POST /wp-json/rd/v1/backup/preview — valida JSON e devolve diff.
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
 * Endpoint POST /wp-json/rd/v1/backup/restore — restaura o último snapshot.
 *
 * @param WP_REST_Request $request Request body (unused — endpoint sem args).
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $request faz parte da assinatura obrigatória do callback REST do WP, mesmo quando o endpoint não consome args.
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
 * Endpoint POST /wp-json/rd/v1/backup/import — aplica importação (com snapshot).
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
 *  ADMIN ENQUEUE — JS do import só na aba Backup do painel
 * ============================================================================= */

/**
 * Enfileira o JS de import só na aba Backup do painel.
 */
function rd_backup_admin_enqueue( $hook ) {
	if ( $hook !== 'toplevel_page_rd_options' ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate em admin_enqueue_scripts: decide se enfileira o JS do backup, não processa form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( $active_tab !== 'backup' ) {
		return;
	}

	wp_enqueue_script(
		'rd-admin-backup',
		get_template_directory_uri() . '/assets/js/admin-backup.js',
		array(),
		rd_asset_version( '/assets/js/admin-backup.js' ),
		true
	);

	// Dados pro JS: URL do REST + nonce (header X-WP-Nonce)
	wp_localize_script(
		'rd-admin-backup',
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
				// Preview UI strings (populadas por admin-backup.js após preview bem-sucedido)
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
 *  RENDER — UI da sub-seção Backup em Manutenção
 * ============================================================================= */

/**
 * Renderiza UI da aba Backup & Restore. Callback de `sec_backup` em panel.php
 * (Wave 11 — promovido de sub-section da Manutenção pra aba própria).
 *
 * Etapa 2: Export com checkboxes pra escolher seções (settings + category_colors).
 * UI do Import vem na Etapa 3.
 */
function rd_backup_render_panel(): void {
	// Section header com ícone (title vazio em add_settings_section evita <h2> duplicado).
	rd_panel_section_header(
		array(
			'icon'  => 'backup',
			'title' => __( 'Backup', 'reloaded' ),
		)
	);

	// Conta cores customizadas + ad banners pra mostrar no hint dos checkboxes.
	$custom_colors_count   = count( rd_backup_collect_category_colors() );
	$rd_settings_for_count = get_option( 'rd_settings', array() );
	$ad_banners_count      = is_array( $rd_settings_for_count )
		? count( array_filter( $rd_settings_for_count, fn( $k ) => strpos( $k, 'ad_' ) === 0, ARRAY_FILTER_USE_KEY ) )
		: 0;

	/*
	 * URL inicial do download (com as 3 seções marcadas por default).
	 * Importante: usamos LINK <a> em vez de <form>+<button> porque este
	 * callback de section roda DENTRO do <form action="options.php"> do
	 * painel — forms HTML aninhados são ignorados pelo browser, fazendo
	 * o submit ir parar no form pai (options.php). Link <a> não tem esse
	 * problema. JS atualiza o href conforme checkboxes mudam.
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
	 * Layout: 2 rows empilhadas dentro do mesmo .rd-pdash.
	 *   Row 1: Export full-width (3 checkboxes lado a lado no desktop)
	 *   Row 2: Import 33% | Restore 67% (Import sozinho ocupa 100% quando não há snapshot)
	 * Spacing entre rows via CSS (.rd-pdash > .rd-pgrid + .rd-pgrid { margin-top: 20px; })
	 */
	echo '<div class="rd-pdash">';

	// === ROW 1: Export full-width ===
	echo '<div class="rd-pgrid">';

	rd_panel_card_open(
		array(
			'title' => __( 'Export', 'reloaded' ),
			'desc'  => esc_html__( 'Download a JSON file with the theme configuration from this install. Useful for migration (sandbox → production), snapshots before risky changes, or replicating the theme on another site.', 'reloaded' ),
			// hint movido pra inline ao lado do botão Download (mais visível e
			// contextualizado — usuário vê o template do nome no momento da ação).
		)
	);

	// Boundary clarity banner — deixa explícito que este NÃO é backup completo do site.
	// Variant info (azul WP) — informacional, não bloqueante.
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
	 * Cards Import e Restore cada um em sua própria row (full-width). Side-by-side
	 * ficava apertado quando o preview do diff carregava. Restore só renderiza se
	 * há snapshot disponível (auto-snapshot criado pelo último import).
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
		<!-- Conteúdo populado por admin-backup.js após preview bem-sucedido. -->
	</div>

	<div id="rd-backup-status" class="rd-pstatus" hidden role="status" aria-live="polite"></div>
	<?php
	rd_panel_card_close();

	echo '</div>'; // .rd-pgrid (Row 2 — Import)

	// === Card: Restore (só aparece se há snapshot disponível) ===
	// Variant "warning" (amber accent). Renderizado em sua própria row,
	// full-width abaixo do Import.
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
