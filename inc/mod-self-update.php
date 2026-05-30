<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Self-Update — Auto-update via GitHub Releases (custom)              *
 *                                                                             *
 * Conecta o tema às releases publicadas no GitHub do projeto pra que o WP    *
 * detecte novas versões e ofereça update 1-clique no admin, igual qualquer  *
 * tema do wp.org. Sem deps externas — reusa o Parsedown já vendored pra      *
 * renderizar release notes no modal "View Details".                          *
 *                                                                             *
 * Fluxo end-to-end:                                                          *
 *   1. Push commits convencionais (`feat:`, `fix:`) pra `master`             *
 *   2. Workflow CI roda → semantic-release calcula nova versão `v0.5.0`     *
 *   3. CI gera `reloaded.zip` (estrutura interna `reloaded/...`)             *
 *   4. Asset binário anexado ao GitHub Release                               *
 *   5. WP do usuário fetcha `/releases/latest` (cache 24h) → encontra v0.5.0 *
 *   6. Compara semver vs `Version:` local → injeta no transient WP           *
 *   7. Admin vê "Update available" + clica → WP baixa o ZIP e descompacta    *
 *                                                                             *
 * APIs WordPress usadas (estáveis desde WP 2.8+):                            *
 *   - pre_set_site_transient_update_themes  → injetar update no transient    *
 *   - themes_api                            → alimentar modal "View Details" *
 *   - upgrader_source_selection             → defense in depth pro nome de   *
 *                                              pasta do ZIP extraído         *
 *   - wp_ajax_rd_check_update                → endpoint do botão "Check now" *
 *                                                                             *
 * Tracked: 1 transient (`rd_self_update_release`) com TTL 24h (1h em erro).  *
 *                                                                             *
 * Filosofia:                                                                  *
 *   - Sem deps externas (PUC foi descartado — bloated + duplicava Parsedown) *
 *   - Reusa lib/Parsedown.php pra renderizar release notes                   *
 *   - APIs WP estáveis há ~15 anos                                            *
 *   - Repo público (sem token auth) — releases acessíveis anônimas           *
 *   - Branch `beta` gera `prerelease: true` → API ignora por construção      *
 *******************************************************************************/

const RD_SELF_UPDATE_REPO        = 'Finallf/theme-reloaded';
const RD_SELF_UPDATE_SLUG        = 'reloaded';
const RD_SELF_UPDATE_TRANSIENT   = 'rd_self_update_release';
const RD_SELF_UPDATE_CACHE_HOURS = 24;
const RD_SELF_UPDATE_API_TIMEOUT = 8;

/*
=============================================================================
 *  FETCH — GitHub /releases/latest com cache de 24h
 * ============================================================================= */

/**
 * Busca o latest release do GitHub. Cache em transient 24h (1h em erro pra
 * não martelar GitHub em caso de outage). Retorna array normalizado ou null.
 *
 * Estrutura retornada:
 *   [
 *     'version'      => '0.5.0',
 *     'download_url' => 'https://.../reloaded.zip',
 *     'release_url'  => 'https://github.com/.../releases/tag/v0.5.0',
 *     'body'         => '## Features\n...',
 *     'published_at' => '2026-06-15T...',
 *     'checked_at'   => 1748534400,
 *   ]
 *
 * @param bool $force_refresh Invalida cache e re-fetcha imediato (botão "Check now").
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
		// Cache curto de "vazio" em erro pra não bombardear GitHub em outage.
		set_transient( RD_SELF_UPDATE_TRANSIENT, array(), HOUR_IN_SECONDS );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		return null;
	}

	// Procura o asset .zip (nosso CI sobe `reloaded.zip`). Se não encontrar,
	// release não é instalável — retorna null.
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
 * Retorna a versão local lida do `style.css`.
 */
function rd_self_update_get_local_version(): string {
	return (string) wp_get_theme( RD_SELF_UPDATE_SLUG )->get( 'Version' );
}

/*
=============================================================================
 *  WP UPDATE TRANSIENT INJECTION
 * ============================================================================= */

/**
 * Hook em pre_set_site_transient_update_themes — quando WP guarda o transient
 * que lista quais updates estão disponíveis, injetamos o nosso se houver.
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
 *  "VIEW DETAILS" MODAL (themes_api) — render via nosso Parsedown
 * ============================================================================= */

/**
 * Hook em themes_api — quando admin clica "View version details" no card
 * do tema (modal popup), WP chama esse filter pra montar o conteúdo.
 *
 * Render do markdown via NOSSO Parsedown — zero dep externa.
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
	$parsedown->setSafeMode( true ); // strip HTML perigoso do markdown do release.

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
 *  DEFENSE IN DEPTH — força pasta extraída pro slug certo
 * ============================================================================= */

/**
 * Hook em upgrader_source_selection — depois do WP descompactar o ZIP, esse
 * filter recebe o path da pasta extraída. Se vier com nome diferente de
 * 'reloaded/' (ex.: alguém anexou source tarball auto do GitHub em vez do
 * nosso `reloaded.zip`), renomeia.
 *
 * Nosso CI já gera estrutura `reloaded/...` interna ao ZIP — então esse hook
 * raramente atua na prática. Mas garante que mesmo se um release manual for
 * feito errado, o usuário não fica brickado.
 */
function rd_self_update_fix_source( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['theme'] ) || RD_SELF_UPDATE_SLUG !== $hook_extra['theme'] ) {
		return $source;
	}

	$expected = trailingslashit( $remote_source ) . RD_SELF_UPDATE_SLUG;
	if ( untrailingslashit( $source ) === $expected ) {
		return $source; // já tá certo.
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
 *  AJAX — botão "Check for updates" no Dashboard
 * ============================================================================= */

/**
 * Handler AJAX do botão "Check for updates" no card do Dashboard.
 *
 * Invalida o transient, re-fetcha imediato, devolve JSON com status atualizado.
 * Capability + nonce checks defensive.
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

	// Invalida transient do WP também — pra que o card do tema em Appearance →
	// Themes reflita o update na próxima carga, sem esperar 12h do cron WP.
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
