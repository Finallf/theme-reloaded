<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Stats — Dashboard de Estatísticas (aba Statistics do painel)        *
 *                                                                             *
 * Consome dados coletados pelo mod-views.php e agrega num dashboard read-only *
 * pro admin. Não substitui Google Analytics — foca em CONTEÚDO (que post      *
 * performa, que gera discussão, tendência mensal).                            *
 *                                                                             *
 * Widgets (Wave 7, bloco K):                                                  *
 *   K1 — Top posts mais lidos (com filtro de janela temporal)                 *
 *   K2 — Total de views do site (all-time + breakdown por janela)             *
 *   K3 — Top posts por comentários (+ engagement ratio comments/views)        *
 *   K4 — Gráfico de crescimento mensal (últimos 12 meses, Chart.js)           *
 *                                                                             *
 * Coleta de dados: independente, gateada por `enable_views_tracking` (toggle  *
 * no Dashboard tab + Statistics → Tracking Settings). Quando OFF, novas       *
 * views não são contadas, mas o histórico permanece visível neste dashboard.  *
 *******************************************************************************/

/*
=============================================================================
 *  CONSTANTES
 * ============================================================================= */

const RD_STATS_CACHE_TTL    = 3600;  // 1 hora — TTL dos transients agregados
const RD_STATS_CACHE_PREFIX = 'rd_stats_';

/*
=============================================================================
 *  HELPERS DE QUERY — agregam dados pros widgets
 * ============================================================================= */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
// Justificativa do bloco inteiro:
// - DirectQuery: dashboard de stats agregadas — não há equivalente nativo do WP (WP_Query
// não suporta SUM/COUNT/aggregations sobre post meta). Queries são intencionais.
// - NoCaching: TODAS as funções deste arquivo já usam transients próprios (prefixo
// `rd_stats_*`, TTL 1h) — cache aplicado no nível da função, não da query.
// - PreparedSQL.NotPrepared: os `$wpdb->get_results( $sql )` recebem strings que JÁ
// foram processadas por `$wpdb->prepare()` na linha anterior. PHPCS não rastreia
// atribuição em variável intermediária; aceitamos o false positive.

/**
 * Total de views do site (soma de todos os posts).
 *
 * @param string $window 'all', 'week', 'month', 'year'
 * @return int
 */
function rd_stats_total_views( $window = 'all' ) {
	global $wpdb;

	// All-time: SUM direto na meta key — query única, rápida, indexada
	if ( $window === 'all' ) {
		$cache_key = RD_STATS_CACHE_PREFIX . 'total_all';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return (int) $cached;
		}

		$sql   = $wpdb->prepare(
			"SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = %s",
			RD_VIEWS_META_KEY
		);
		$total = (int) $wpdb->get_var( $sql );

		set_transient( $cache_key, $total, RD_STATS_CACHE_TTL );
		return $total;
	}

	// Janelas (week/month/year): precisa parsear os logs em PHP — usa cache
	$cache_key = RD_STATS_CACHE_PREFIX . 'total_' . $window;
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return (int) $cached;
	}

	$logs = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			RD_VIEWS_META_KEY_LOG
		)
	);

	$cutoff = rd_stats_get_cutoff_timestamp( $window );
	$total  = 0;

	foreach ( $logs as $log_serialized ) {
		$log = maybe_unserialize( $log_serialized );
		if ( ! is_array( $log ) ) {
			continue;
		}
		$total += count( array_filter( $log, fn( $t ) => $t >= $cutoff ) );
	}

	set_transient( $cache_key, $total, RD_STATS_CACHE_TTL );
	return $total;
}

/**
 * Top N posts por número de views numa janela.
 *
 * @param int    $limit  Quantidade (default 10)
 * @param string $window 'all', 'week', 'month', 'year'
 * @return array Array de objetos { post_id, post_title, views, category_color }
 */
function rd_stats_top_posts_by_views( $limit = 10, $window = 'all' ) {
	global $wpdb;

	$cache_key = RD_STATS_CACHE_PREFIX . 'top_views_' . $window . '_' . (int) $limit;
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return $cached;
	}

	// All-time: query SQL direta ordenada por meta_value
	if ( $window === 'all' ) {
		$sql     = $wpdb->prepare(
			"SELECT p.ID as post_id, p.post_title, CAST(pm.meta_value AS UNSIGNED) as views
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE pm.meta_key = %s
               AND p.post_status = 'publish'
               AND p.post_type IN ('post', 'page')
             ORDER BY views DESC
             LIMIT %d",
			RD_VIEWS_META_KEY,
			(int) $limit
		);
		$results = $wpdb->get_results( $sql );
	} else {
		// Janelas: precisa parsear logs em PHP
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value, p.post_title
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s
               AND p.post_status = 'publish'
               AND p.post_type IN ('post', 'page')",
				RD_VIEWS_META_KEY_LOG
			)
		);

		$cutoff = rd_stats_get_cutoff_timestamp( $window );
		$counts = array();

		foreach ( $logs as $row ) {
			$log = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $log ) ) {
				continue;
			}
			$count = count( array_filter( $log, fn( $t ) => $t >= $cutoff ) );
			if ( $count > 0 ) {
				$counts[] = (object) array(
					'post_id'    => (int) $row->post_id,
					'post_title' => $row->post_title,
					'views'      => $count,
				);
			}
		}

		usort( $counts, fn( $a, $b ) => $b->views - $a->views );
		$results = array_slice( $counts, 0, (int) $limit );
	}

	set_transient( $cache_key, $results, RD_STATS_CACHE_TTL );
	return $results;
}

/**
 * Top N posts por comentários (all-time, query nativa WP).
 * Inclui engagement ratio (comments/views * 100).
 *
 * @param int    $limit Quantidade (default 10)
 * @param string $sort  'comments' (absoluto) ou 'ratio' (engagement). Default 'comments'.
 * @return array Array de objetos { post_id, post_title, comment_count, views, ratio }
 */
function rd_stats_top_posts_by_comments( $limit = 10, $sort = 'comments' ) {
	global $wpdb;

	// Whitelist defensiva
	if ( ! in_array( $sort, array( 'comments', 'ratio' ), true ) ) {
		$sort = 'comments';
	}

	$cache_key = RD_STATS_CACHE_PREFIX . 'top_comments_' . $sort . '_' . (int) $limit;
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return $cached;
	}

	if ( $sort === 'comments' ) {
		// Ordenação por número absoluto: SQL faz o ORDER BY + LIMIT direto (rápido)
		$sql     = $wpdb->prepare(
			"SELECT ID as post_id, post_title, comment_count
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post', 'page')
               AND comment_count > 0
             ORDER BY comment_count DESC
             LIMIT %d",
			(int) $limit
		);
		$results = $wpdb->get_results( $sql );

		// Enriquece com views + ratio
		foreach ( $results as $row ) {
			$row->views = rd_get_post_views( $row->post_id, 'all' );
			$row->ratio = $row->views > 0
				? round( ( (int) $row->comment_count / (int) $row->views ) * 100, 2 )
				: 0;
		}
	} else {
		// Ordenação por ratio: precisa calcular pra TODOS antes de ordenar
		// (ratio não tá armazenado, é derivado). Cache de 1h absorve o custo.
		// prepare() usado mesmo com valores hardcoded — convenção do tema:
		// todas as queries passam por prepare, sem exceção (audit Wave 9 A).
		$all_commented = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID as post_id, post_title, comment_count
             FROM {$wpdb->posts}
             WHERE post_status = %s
               AND post_type IN (%s, %s)
               AND comment_count > 0",
				'publish',
				'post',
				'page'
			)
		);

		foreach ( $all_commented as $row ) {
			$row->views = rd_get_post_views( $row->post_id, 'all' );
			$row->ratio = $row->views > 0
				? round( ( (int) $row->comment_count / (int) $row->views ) * 100, 2 )
				: 0;
		}

		// Filtra posts sem views (ratio 0 não é interessante pro ranking de engagement)
		$all_commented = array_filter( $all_commented, fn( $r ) => $r->views > 0 );

		// Ordena por ratio DESC; tiebreaker por comment_count DESC pra resultados estáveis
		usort(
			$all_commented,
			function ( $a, $b ) {
				if ( $a->ratio !== $b->ratio ) {
					return $b->ratio <=> $a->ratio;
				}
				return $b->comment_count <=> $a->comment_count;
			}
		);

		$results = array_slice( $all_commented, 0, (int) $limit );
	}

	set_transient( $cache_key, $results, RD_STATS_CACHE_TTL );
	return $results;
}

/**
 * Views agregados por mês nos últimos N meses (pro gráfico K4).
 *
 * @param int $months_back Quantos meses pra trás (default 12)
 * @return array Array associativo [ 'YYYY-MM' => view_count, ... ] do mais antigo pro mais novo
 */
function rd_stats_views_by_month( $months_back = 12 ) {
	global $wpdb;

	$cache_key = RD_STATS_CACHE_PREFIX . 'by_month_' . (int) $months_back;
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return $cached;
	}

	// Inicializa todos os meses com 0 (garante meses sem views aparecerem no gráfico)
	$buckets = array();
	for ( $i = $months_back - 1; $i >= 0; $i-- ) {
		$key             = wp_date( 'Y-m', strtotime( "-{$i} months" ) );
		$buckets[ $key ] = 0;
	}

	$logs = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			RD_VIEWS_META_KEY_LOG
		)
	);

	$cutoff = strtotime( "-{$months_back} months" );

	foreach ( $logs as $log_serialized ) {
		$log = maybe_unserialize( $log_serialized );
		if ( ! is_array( $log ) ) {
			continue;
		}

		foreach ( $log as $timestamp ) {
			if ( $timestamp < $cutoff ) {
				continue;
			}
			$key = wp_date( 'Y-m', $timestamp );
			if ( isset( $buckets[ $key ] ) ) {
				++$buckets[ $key ];
			}
		}
	}

	set_transient( $cache_key, $buckets, RD_STATS_CACHE_TTL );
	return $buckets;
}

/**
 * Total de views numa janela ANTERIOR (pra comparações tipo "vs semana passada").
 * Ex: rd_stats_total_views_previous('week') = views entre 7 e 14 dias atrás.
 *
 * @param string $window 'day', 'week', 'month', 'year'
 * @return int
 */
function rd_stats_total_views_previous( $window = 'week' ) {
	global $wpdb;

	$window_size = match ( $window ) {
		'day'   => DAY_IN_SECONDS,
		'week'  => WEEK_IN_SECONDS,
		'month' => MONTH_IN_SECONDS,
		'year'  => YEAR_IN_SECONDS,
		default => 0,
	};
	if ( $window_size === 0 ) {
		return 0;
	}

	$cache_key = RD_STATS_CACHE_PREFIX . 'total_prev_' . $window;
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return (int) $cached;
	}

	$now   = time();
	$start = $now - ( $window_size * 2 ); // janela anterior começa aqui
	$end   = $now - $window_size;          // termina aqui (= início da janela atual)

	$logs = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			RD_VIEWS_META_KEY_LOG
		)
	);

	$total = 0;
	foreach ( $logs as $log_serialized ) {
		$log = maybe_unserialize( $log_serialized );
		if ( ! is_array( $log ) ) {
			continue;
		}
		$total += count( array_filter( $log, fn( $t ) => $t >= $start && $t < $end ) );
	}

	set_transient( $cache_key, $total, RD_STATS_CACHE_TTL );
	return $total;
}

/**
 * Retorna dados da categoria primária de um post (pra exibir chip colorido
 * nos rankings). Segue o padrão de mod-category-colors.php:
 *   1. Tenta `_rd_primary_category` meta (definida pelo admin no editor)
 *   2. Fallback pra primeira categoria do post
 *
 * @param int $post_id
 * @return array|null { name, slug, color, term_id, link } ou null se sem categoria
 */
function rd_stats_get_post_primary_category( int $post_id ): ?array {
	$cats = get_the_category( $post_id );
	if ( empty( $cats ) ) {
		return null;
	}

	$primary_id = (int) get_post_meta( $post_id, '_rd_primary_category', true );
	$chosen     = null;

	if ( $primary_id ) {
		foreach ( $cats as $c ) {
			if ( (int) $c->term_id === $primary_id ) {
				$chosen = $c;
				break;
			}
		}
	}
	if ( ! $chosen ) {
		$chosen = $cats[0];
	}

	// Cor configurada via mod-category-colors.php (term meta). Fallback: cinza neutro.
	$color = get_term_meta( $chosen->term_id, 'rd_category_color', true );
	if ( empty( $color ) ) {
		$color = '#555555';
	}

	return array(
		'name'    => $chosen->name,
		'slug'    => $chosen->slug,
		'color'   => $color,
		'term_id' => (int) $chosen->term_id,
		'link'    => get_category_link( $chosen->term_id ),
	);
}

/**
 * Calcula trend (variação % e direção) entre dois valores.
 *
 * @param int $current  Valor da janela atual
 * @param int $previous Valor da janela anterior
 * @return array{ pct: float|null, direction: string, label: string }
 *               direction: 'up' | 'down' | 'flat' | 'new'
 */
function rd_stats_calculate_trend( $current, $previous ) {
	// Caso especial: período anterior teve 0 views. Não dá pra calcular %.
	if ( $previous === 0 ) {
		return $current > 0
			? array(
				'pct'       => null,
				'direction' => 'new',
				'label'     => __( 'new', 'reloaded' ),
			)
			: array(
				'pct'       => 0,
				'direction' => 'flat',
				'label'     => '—',
			);
	}

	$diff = $current - $previous;
	$pct  = round( ( $diff / $previous ) * 100, 1 );

	if ( $pct > 0 ) {
		return array(
			'pct'       => $pct,
			'direction' => 'up',
			'label'     => '+' . $pct . '%',
		);
	} elseif ( $pct < 0 ) {
		return array(
			'pct'       => $pct,
			'direction' => 'down',
			'label'     => $pct . '%',
		);
	}
	return array(
		'pct'       => 0,
		'direction' => 'flat',
		'label'     => '0%',
	);
}

/*
=============================================================================
 *  HELPERS INTERNOS
 * ============================================================================= */

/**
 * Retorna timestamp do início da janela (cutoff pra filtrar logs).
 */
function rd_stats_get_cutoff_timestamp( $window ) {
	$now = time();
	return match ( $window ) {
		'day'   => $now - DAY_IN_SECONDS,
		'week'  => $now - WEEK_IN_SECONDS,
		'month' => $now - MONTH_IN_SECONDS,
		'year'  => $now - YEAR_IN_SECONDS,
		default => 0,
	};
}

/**
 * Força refresh dos transients de stats. Chamado pelo link "Refresh now"
 * no dashboard e por hooks futuros (ex: ao publicar/deletar post).
 */
function rd_stats_refresh_cache() {
	global $wpdb;
	// prepare() + esc_like() pra consistência com o resto do tema (audit Wave 9 A).
	// esc_like é defensivo: RD_STATS_CACHE_PREFIX ('rd_stats_') não tem wildcards
	// hoje, mas se algum dia mudar pra ter `_` ou `%` literais, esc_like preserva.
	$prefix_like  = '_transient_' . $wpdb->esc_like( RD_STATS_CACHE_PREFIX ) . '%';
	$timeout_like = '_transient_timeout_' . $wpdb->esc_like( RD_STATS_CACHE_PREFIX ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
         WHERE option_name LIKE %s
            OR option_name LIKE %s",
			$prefix_like,
			$timeout_like
		)
	);
}

/**
 * Intercepta o link "Refresh now" do dashboard. Hook em admin_init pra rodar
 * ANTES de qualquer output (precisa pra wp_safe_redirect funcionar).
 */
function rd_stats_handle_refresh_request() {
	if ( empty( $_GET['rd_stats_refresh'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rd_stats_refresh' ) ) {
		return;
	}

	rd_stats_refresh_cache();

	// Remove os params do query string e redireciona — evita refresh duplicado se o user der F5
	wp_safe_redirect( remove_query_arg( array( 'rd_stats_refresh', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'rd_stats_handle_refresh_request' );

/*
=============================================================================
 *  ENQUEUE — Chart.js só na aba Statistics do painel
 * ============================================================================= */

/**
 * Enfileira Chart.js nas abas que precisam (Stats / Dashboard / Security CSP).
 *
 * Wave 11 Fase G expandiu o gate: além da aba Statistics original, Dashboard
 * (gráfico 7d de views) e Security (doughnut CSP por directive) também
 * carregam Chart.js + admin-charts.js (auto-render genérico via data attrs).
 * O admin-stats.js específico do K4 fica restrito à aba Statistics.
 *
 * Hook: admin_enqueue_scripts.
 *
 * @param string $hook Hook suffix da página atual no admin.
 */
function rd_stats_admin_enqueue( $hook ) {
	// Só roda na página do painel ReloadeD.
	if ( $hook !== 'toplevel_page_rd_options' ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate em admin_enqueue_scripts: decide se enfileira Chart.js, não processa form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

	// Decisão por aba: qual gate (feature flag) + qual JS específico carregar.
	$load_chartjs   = false;
	$load_stats_js  = false; // admin-stats.js — só pra aba Estatísticas (K4 chart específico).
	$load_charts_js = false; // admin-charts.js — auto-render via data attrs (Wave 11 Fase G).

	if ( 'statistics' === $active_tab ) {
		$load_chartjs  = true;
		$load_stats_js = true;
	} elseif ( 'dashboard' === $active_tab ) {
		// Dashboard mostra "Views per Day (7d)" — Chart.js sempre carregado nesta aba.
		$load_chartjs   = true;
		$load_charts_js = true;
	} elseif ( 'security' === $active_tab && rd_get_option_bool( 'enable_csp_report_only' ) ) {
		// Security CSP doughnut só se feature CSP ativa (sem reports = sem gráfico).
		$load_chartjs   = true;
		$load_charts_js = true;
	}

	if ( ! $load_chartjs ) {
		return;
	}

	wp_enqueue_script(
		'rd-chartjs',
		get_template_directory_uri() . '/lib/chartjs.min.js',
		array(),
		'4.5.1',
		true
	);

	if ( $load_stats_js ) {
		// Inicialização do gráfico K4 — depende de Chart.js já estar carregado.
		wp_enqueue_script(
			'rd-admin-stats',
			get_template_directory_uri() . '/assets/js/admin-stats.js',
			array( 'rd-chartjs' ),
			rd_asset_version( '/assets/js/admin-stats.js' ),
			true
		);
	}

	if ( $load_charts_js ) {
		// Auto-render de canvas via data-rd-chart-type (Wave 11 Fase G).
		wp_enqueue_script(
			'rd-admin-charts',
			get_template_directory_uri() . '/assets/js/admin-charts.js',
			array( 'rd-chartjs' ),
			rd_asset_version( '/assets/js/admin-charts.js' ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'rd_stats_admin_enqueue' );

/*
=============================================================================
 *  RENDER — Dashboard inteiro (chamado pelo callback da section no panel.php)
 * ============================================================================= */

/**
 * Renderiza o dashboard completo (K1, K2, K3, K4).
 * Implementado por etapas: K2 ✓ | K1 ⏳ | K3 ⏳ | K4 ⏳
 */
function rd_stats_render_dashboard() {
	// URL do link "Refresh now" — nonce protegido, interceptado por rd_stats_handle_refresh_request()
	$refresh_url = wp_nonce_url(
		add_query_arg( 'rd_stats_refresh', '1' ),
		'rd_stats_refresh'
	);

	// Wave 11 Fase E: wrappers/header rebrandados pro design system rd-p*
	// (helpers em panel-helpers.php). Cards internos mantêm classes específicas
	// (.rd-stats-card__title, __big-number, ranking, tabs, chart) — são patterns
	// únicos do dashboard sem equivalente generalizável em rd-p*.
	$info_text = sprintf(
		/* translators: %s = cache TTL em minutos (ex: "60") */
		esc_html__( 'Aggregated data cached for %s minutes.', 'reloaded' ),
		(int) ( RD_STATS_CACHE_TTL / 60 )
	);
	$action_btn = sprintf(
		'<a href="%1$s" class="button button-secondary"><span class="dashicons dashicons-update"></span> %2$s</a>',
		esc_url( $refresh_url ),
		esc_html__( 'Refresh now', 'reloaded' )
	);

	rd_panel_dash_open();
	rd_panel_dash_header(
		array(
			'info'   => $info_text,
			'action' => $action_btn,
		)
	);

	/*
	 * Onboarding banner — aparece em 2 cenários:
	 *   A) Tracking OFF → variant warning, SEMPRE mostra (independente de ter
	 *      dados passados). Sinaliza ao admin "você desligou, nada novo entra".
	 *   B) Tracking ON mas total_views === 0 → variant default, "aguardando
	 *      primeiros visitantes" (pré-launch ou logo após ligar).
	 * Tracking ON com dados → sem banner (operação normal).
	 *
	 * Cards K1/K2/K3/K4 abaixo continuam aparecendo com seus empty states
	 * próprios (preview do layout futuro). Spacing entre este .rd-pgrid e o
	 * seguinte é coberto pela regra genérica .rd-pdash > .rd-pgrid + .rd-pgrid
	 * { margin-top: 20px; } em admin-style.css.
	 */
	$tracking_on = rd_get_option_bool( 'enable_views_tracking' );

	if ( ! $tracking_on ) {
		// Estado A — Tracking OFF (sempre mostra, ignora dados passados)
		echo '<div class="rd-pgrid">';
		rd_panel_card_open(
			array(
				'variant' => 'warning',
				'title'   => __( 'Tracking is currently disabled', 'reloaded' ),
				'desc'    => esc_html__( 'No new views are being recorded. Enable the "Enable views tracking" toggle in the Tracking Settings section above to resume data collection. Past data (if any) is preserved and still shown in the cards below.', 'reloaded' ),
			)
		);
		rd_panel_card_close();
		echo '</div>';
	} elseif ( 0 === (int) rd_stats_total_views( 'all' ) ) {
		// Estado B — Tracking ON mas zero dados
		echo '<div class="rd-pgrid">';
		rd_panel_card_open(
			array(
				'title' => __( 'Waiting for first visitors', 'reloaded' ),
				'desc'  => esc_html__( 'Tracking is active. Stats will populate here as visitors browse the site. Allow 24-48h for the first data points to show up.', 'reloaded' ),
				'hint'  => esc_html__( 'Bots, admins and duplicate visits (same IP within 30 minutes) are filtered out automatically — only real human visits count.', 'reloaded' ),
			)
		);
		rd_panel_card_close();
		echo '</div>';
	}
	?>

		<div class="rd-pgrid rd-pgrid--sidebar-main">

			<?php // ================ K2 — Total Views ================ ?>
			<div class="rd-stats-card rd-stats-card--total">
				<div class="rd-stats-card__title"><?php esc_html_e( 'Total Views', 'reloaded' ); ?></div>
				<div class="rd-stats-card__big-number">
					<?php echo esc_html( number_format_i18n( rd_stats_total_views( 'all' ) ) ); ?>
				</div>
				<div class="rd-stats-card__breakdown">
					<?php
					$windows = array(
						'day'   => __( 'Today', 'reloaded' ),
						'week'  => __( 'This week', 'reloaded' ),
						'month' => __( 'This month', 'reloaded' ),
						'year'  => __( 'This year', 'reloaded' ),
					);
					foreach ( $windows as $window_key => $window_label ) {
						$current  = rd_stats_total_views( $window_key );
						$previous = rd_stats_total_views_previous( $window_key );
						$trend    = rd_stats_calculate_trend( $current, $previous );
						?>
						<div class="rd-stats-row">
							<span class="rd-stats-row__label"><?php echo esc_html( $window_label ); ?>:</span>
							<span class="rd-stats-row__value"><?php echo esc_html( number_format_i18n( $current ) ); ?></span>
							<span class="rd-stats-row__trend rd-stats-row__trend--<?php echo esc_attr( $trend['direction'] ); ?>" title="
							<?php
								/* translators: %s is the views count for the previous period (e.g. last week, last month), already locale-formatted with number_format_i18n. Shown as tooltip on the trend pill. */
								echo esc_attr( sprintf( __( 'Previous period: %s', 'reloaded' ), number_format_i18n( $previous ) ) );
							?>
							">
								<?php echo esc_html( $trend['label'] ); ?>
							</span>
						</div>
						<?php
					}
					?>
				</div>
			</div>

			<?php // ================ K1 — Top Posts by Views ================ ?>
			<?php
			// Janela ativa via querystring (whitelist defensiva — só 4 valores aceitos)
			$allowed_windows = array( 'all', 'year', 'month', 'week' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter pra UI do dashboard (visualização read-only de stats); whitelist defensiva via in_array logo abaixo.
			$k1_window = isset( $_GET['k1_window'] ) ? sanitize_key( wp_unslash( $_GET['k1_window'] ) ) : 'all';
			if ( ! in_array( $k1_window, $allowed_windows, true ) ) {
				$k1_window = 'all';
			}

			// Quantos itens mostrar (config do painel)
			$limit = (int) rd_get_option( 'stats_top_limit', 10 );
			$limit = max( 1, min( 30, $limit ) ); // defensivo: clamp 1-30

			$top_posts = rd_stats_top_posts_by_views( $limit, $k1_window );

			// Labels traduzíveis das janelas pro tab nav
			$window_labels = array(
				'all'   => __( 'All-time', 'reloaded' ),
				'year'  => __( 'Year', 'reloaded' ),
				'month' => __( 'Month', 'reloaded' ),
				'week'  => __( 'Week', 'reloaded' ),
			);
			?>
			<div class="rd-stats-card rd-stats-card--ranking">
				<div class="rd-stats-card__title"><?php esc_html_e( 'Top Posts by Views', 'reloaded' ); ?></div>

				<nav class="rd-stats-tabs" role="tablist">
					<?php
					foreach ( $window_labels as $key => $label ) :
						$tab_url   = add_query_arg( 'k1_window', $key );
						$is_active = ( $k1_window === $key );
						?>
						<a href="<?php echo esc_url( $tab_url ); ?>"
							class="rd-stats-tabs__item <?php echo $is_active ? 'is-active' : ''; ?>"
							role="tab"
							aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<?php if ( empty( $top_posts ) ) : ?>
					<p class="rd-stats-empty"><?php esc_html_e( 'No data in this window yet — give it time.', 'reloaded' ); ?></p>
				<?php else : ?>
					<ol class="rd-stats-ranking">
						<?php
						foreach ( $top_posts as $i => $post ) :
							$cat       = rd_stats_get_post_primary_category( $post->post_id );
							$edit_link = get_edit_post_link( $post->post_id );
							$view_link = get_permalink( $post->post_id );
							?>
							<li class="rd-stats-ranking__item">
								<span class="rd-stats-ranking__rank"><?php echo (int) ( $i + 1 ); ?></span>
								<?php if ( $cat ) : ?>
									<span class="rd-stats-ranking__chip" style="background-color: <?php echo esc_attr( $cat['color'] ); ?>; color: <?php echo esc_attr( rd_get_contrast_text_color( $cat['color'] ) ); ?>;">
										<?php echo esc_html( $cat['name'] ); ?>
									</span>
								<?php endif; ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" class="rd-stats-ranking__title" title="<?php esc_attr_e( 'Edit this post', 'reloaded' ); ?>">
									<?php echo esc_html( $post->post_title ); ?>
								</a>
								<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener" class="rd-stats-ranking__view-link" title="<?php esc_attr_e( 'Open on the frontend', 'reloaded' ); ?>">
									<span class="dashicons dashicons-external"></span>
								</a>
								<span class="rd-stats-ranking__metric">
									<?php echo esc_html( number_format_i18n( $post->views ) ); ?>
									<small><?php esc_html_e( 'views', 'reloaded' ); ?></small>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>

			<?php // ================ K3 — Top Posts by Comments ================ ?>
			<?php
			// Sort ativo via querystring (whitelist defensiva — só 2 valores aceitos)
			$allowed_sorts = array( 'comments', 'ratio' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter pra UI do dashboard (visualização read-only de stats); whitelist defensiva via in_array logo abaixo.
			$k3_sort = isset( $_GET['k3_sort'] ) ? sanitize_key( wp_unslash( $_GET['k3_sort'] ) ) : 'comments';
			if ( ! in_array( $k3_sort, $allowed_sorts, true ) ) {
				$k3_sort = 'comments';
			}

			$top_commented = rd_stats_top_posts_by_comments( $limit, $k3_sort );

			$sort_labels = array(
				'comments' => __( 'By Comments', 'reloaded' ),
				'ratio'    => __( 'By Engagement Ratio', 'reloaded' ),
			);
			?>
			<div class="rd-stats-card rd-stats-card--ranking rd-stats-card--full">
				<div class="rd-stats-card__title"><?php esc_html_e( 'Top Posts by Comments', 'reloaded' ); ?></div>

				<nav class="rd-stats-tabs" role="tablist">
					<?php
					foreach ( $sort_labels as $key => $label ) :
						$tab_url   = add_query_arg( 'k3_sort', $key );
						$is_active = ( $k3_sort === $key );
						?>
						<a href="<?php echo esc_url( $tab_url ); ?>"
							class="rd-stats-tabs__item <?php echo $is_active ? 'is-active' : ''; ?>"
							role="tab"
							aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<?php if ( empty( $top_commented ) ) : ?>
					<p class="rd-stats-empty"><?php esc_html_e( 'No comments yet — the discussion is just starting.', 'reloaded' ); ?></p>
				<?php else : ?>
					<ol class="rd-stats-ranking">
						<?php
						foreach ( $top_commented as $i => $post ) :
							$cat       = rd_stats_get_post_primary_category( $post->post_id );
							$edit_link = get_edit_post_link( $post->post_id );
							$view_link = get_permalink( $post->post_id );
							?>
							<li class="rd-stats-ranking__item">
								<span class="rd-stats-ranking__rank"><?php echo (int) ( $i + 1 ); ?></span>
								<?php if ( $cat ) : ?>
									<span class="rd-stats-ranking__chip" style="background-color: <?php echo esc_attr( $cat['color'] ); ?>; color: <?php echo esc_attr( rd_get_contrast_text_color( $cat['color'] ) ); ?>;">
										<?php echo esc_html( $cat['name'] ); ?>
									</span>
								<?php endif; ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" class="rd-stats-ranking__title" title="<?php esc_attr_e( 'Edit this post', 'reloaded' ); ?>">
									<?php echo esc_html( $post->post_title ); ?>
								</a>
								<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener" class="rd-stats-ranking__view-link" title="<?php esc_attr_e( 'Open on the frontend', 'reloaded' ); ?>">
									<span class="dashicons dashicons-external"></span>
								</a>
								<span class="rd-stats-ranking__metric" title="
								<?php
									/* translators: 1: views count, locale-formatted (e.g. "1.205"); 2: engagement ratio as percentage (e.g. "2.8"). The literal %% in the string is an escaped percent sign that renders as a single % in the final tooltip. */
									echo esc_attr( sprintf( __( '%1$s views • %2$s%% engagement ratio (comments / views)', 'reloaded' ), number_format_i18n( $post->views ), $post->ratio ) );
								?>
								">
									<?php echo esc_html( number_format_i18n( $post->comment_count ) ); ?>
									<small><?php esc_html_e( 'comments', 'reloaded' ); ?></small>
									<?php if ( $post->ratio > 0 ) : ?>
										<span class="rd-stats-ranking__ratio <?php echo $k3_sort === 'ratio' ? 'is-highlighted' : ''; ?>"><?php echo esc_html( $post->ratio ); ?>%</span>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>

			<?php // ================ K4 — Monthly Growth Chart ================ ?>
			<?php
			$monthly_data = rd_stats_views_by_month( 12 );

			// Labels traduzíveis e curtas pros eixos (ex: "Jan/26").
			// date_i18n respeita o locale carregado pelo WP — funciona pra pt-BR.
			$chart_labels = array();
			$chart_values = array();
			foreach ( $monthly_data as $year_month => $count ) {
				$timestamp      = strtotime( $year_month . '-01' );
				$chart_labels[] = date_i18n( 'M/y', $timestamp );
				$chart_values[] = (int) $count;
			}
			?>
			<div class="rd-stats-card rd-stats-card--full rd-stats-card--chart">
				<div class="rd-stats-card__title"><?php esc_html_e( 'Monthly Growth (last 12 months)', 'reloaded' ); ?></div>

				<?php if ( array_sum( $chart_values ) === 0 ) : ?>
					<p class="rd-stats-empty"><?php esc_html_e( 'No views in the last 12 months yet — the chart will fill up as data arrives.', 'reloaded' ); ?></p>
				<?php else : ?>
					<div class="rd-stats-chart-wrapper">
						<canvas id="rd-stats-monthly-chart"
								data-labels="<?php echo esc_attr( wp_json_encode( $chart_labels ) ); ?>"
								data-values="<?php echo esc_attr( wp_json_encode( $chart_values ) ); ?>"
								data-label-views="<?php esc_attr_e( 'Views', 'reloaded' ); ?>"></canvas>
					</div>
				<?php endif; ?>
			</div>

		</div>
	<?php
	rd_panel_dash_close();
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
