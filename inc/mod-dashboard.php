<?php
/**
 * Module: Dashboard — Visão Geral do tema (Wave 11 Fase F).
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Dashboard — Aba "Visão Geral" do painel admin (Wave 11 Fase F)       *
 *                                                                              *
 * Read-only landing tab. Vira a PRIMEIRA aba (default quando admin abre o      *
 * painel). Mostra estado das features principais, métricas chave e atalhos    *
 * rápidos pras outras abas.                                                    *
 *                                                                              *
 * Renderização 100% via componentes `rd-p*` do design system (Wave 11 Fase C).*
 * Sem JS — toda atualização vem do page reload (dados são instantâneos).      *
 *                                                                              *
 * Estrutura:                                                                   *
 *   1. Site Status     — 6 cards com badge ON/OFF de features-chave           *
 *   2. Quick Metrics   — 3 cards big-number (views 24h, posts/mês, comments) *
 *   3. Quick Actions   — botões pra atalhar pras outras abas relevantes      *
 *   4. Footer Info     — versão tema · WP · PHP                                *
 *******************************************************************************/

/**
 * Callback principal — renderiza a aba inteira.
 * Chamado pelo `add_settings_section('sec_dashboard', ...)` em panel.php.
 */
function rd_dashboard_render(): void {
	rd_panel_dash_open();

	// ============ Section 1: Site Status ============
	rd_panel_section_header(
		array(
			'icon'  => 'admin-site',
			'title' => __( 'Site Status', 'reloaded' ),
			'desc'  => __( 'Current state of key features. Click any tab on the navigation above to change a setting.', 'reloaded' ),
		)
	);

	echo '<div class="rd-pgrid rd-pgrid--five-cols">';
	$toggle_nonce = wp_create_nonce( 'rd_dashboard_toggle' );
	foreach ( rd_dashboard_get_status_data() as $item ) {
		rd_panel_card_open( array( 'title' => $item['title'] ) );

		/*
		 * Linha de status: 2 grupos lado a lado com justify-content: space-between.
		 *   Esquerda: .rd-dashboard-status-controls (switch + gear, qualquer combinação)
		 *   Direita:  .rd-dashboard-status-info     (badge + detail)
		 *
		 * Ordem invertida do natural pra estabilizar a posição do switch/gear —
		 * quando o badge muda de "ON" pra "OFF" (largura ~5px maior), o ajuste
		 * acontece no gap central, não na posição do controle. Sem isso, o
		 * switch/gear "pula" 1px lateralmente em cada toggle.
		 *
		 * O wrapper de controles também garante que switch + gear ficam juntos
		 * à esquerda quando ambos estão presentes — sem wrapper, space-between
		 * espalharia os 3 elementos (switch, gear, info) em 3 colunas iguais.
		 */
		echo '<p class="rd-dashboard-status-line">';
		echo '<span class="rd-dashboard-status-controls">';

		// Toggle inline
		if ( ! empty( $item['toggle'] ) ) {
			$confirm_attr = ! empty( $item['confirm'] )
				? ' data-rd-confirm="' . esc_attr( $item['confirm'] ) . '"'
				: '';
			// Tooltip dinâmico — mostra a próxima ação disponível conforme estado atual.
			// Ambos labels passados como data-attrs pra JS swap após flipar (sem reload).
			$tooltip_on      = esc_attr__( 'Disable', 'reloaded' );
			$tooltip_off     = esc_attr__( 'Enable', 'reloaded' );
			$current_tooltip = $item['value'] ? $tooltip_on : $tooltip_off;
			printf(
				'<button type="button" class="rd-pswitch" role="switch" aria-checked="%1$s" data-rd-toggle="%2$s" data-rd-nonce="%3$s" data-tooltip="%6$s" data-tooltip-on="%7$s" data-tooltip-off="%8$s"%4$s><span class="rd-pswitch__track"></span><span class="rd-pswitch__thumb"></span><span class="screen-reader-text">%5$s</span></button>',
				$item['value'] ? 'true' : 'false',
				esc_attr( $item['toggle'] ),
				esc_attr( $toggle_nonce ),
				$confirm_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $confirm_attr already escaped above via esc_attr().
				esc_html( $item['title'] ),
				$current_tooltip, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above via esc_attr__().
				$tooltip_on, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above via esc_attr__().
				$tooltip_off // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above via esc_attr__().
			);
		}

		// Gear link (deep link pra section configurável). Pode coexistir com toggle —
		// admin usa o switch pra liga/desliga rápido, gear pra config detalhada.
		if ( ! empty( $item['link'] ) ) {
			$tooltip_label = esc_attr__( 'Configure', 'reloaded' );
			printf(
				'<a href="%1$s" class="rd-dashboard-card-link" data-tooltip="%2$s" aria-label="%2$s"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span></a>',
				esc_url( $item['link'] ),
				$tooltip_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above via esc_attr__.
			);
		}

		echo '</span>'; // .rd-dashboard-status-controls

		// Info group (direita): badge + detail. Wrapper inline-flex pra
		// preservar o gap de 10px entre badge e detail (ex: badge "ON" + <code>slug</code>).
		echo '<span class="rd-dashboard-status-info">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pré-escapado por rd_panel_badge(); detail é HTML controlado (esc_html aplicado nos valores dinâmicos).
		echo $item['badge'] . $item['detail'];
		echo '</span>';

		echo '</p>';

		rd_panel_card_close();
	}
	echo '</div>';

	// ============ Section 2: Quick Metrics ============
	rd_panel_section_header(
		array(
			'icon'  => 'chart-bar',
			'title' => __( 'Quick Metrics', 'reloaded' ),
			'desc'  => __( 'Snapshot of recent activity. Full breakdown in the Statistics tab.', 'reloaded' ),
		)
	);

	echo '<div class="rd-pgrid rd-pgrid--three-cols">';
	foreach ( rd_dashboard_get_metrics_data() as $metric ) {
		rd_panel_card_open( array( 'title' => $metric['title'] ) );
		echo '<div class="rd-pcard__big-number">' . esc_html( $metric['value'] ) . '</div>';
		if ( '' !== $metric['hint'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hint é HTML controlado (link com esc_url aplicado abaixo).
			echo '<div class="rd-pcard__big-hint">' . $metric['hint'] . '</div>';
		}
		rd_panel_card_close();
	}
	echo '</div>';

	// ============ Section 3: Activity Trend (Wave 11 Fase G) ============
	// Bar chart de views por dia nos últimos 7 dias. Sempre renderiza —
	// quando não há dados (tracking nunca ativo ou recém-instalado), a
	// função get_views_7d retorna array zerado e o chart mostra todas as
	// barras em zero (preview do layout futuro).
	rd_panel_section_header(
		array(
			'icon'  => 'chart-bar',
			'title' => __( 'Activity Trend', 'reloaded' ),
			'desc'  => __( 'Views per day over the last 7 days. Useful to spot weekly patterns and traffic spikes.', 'reloaded' ),
		)
	);

	$views_7d = rd_dashboard_get_views_7d();

	rd_panel_card_open();
	?>
	<div class="rd-dashboard-chart-wrapper">
		<canvas id="rd-dashboard-views-7d-chart"
			data-rd-chart-type="bar"
			data-label="<?php esc_attr_e( 'Views', 'reloaded' ); ?>"
			data-labels="<?php echo esc_attr( wp_json_encode( array_keys( $views_7d ) ) ); ?>"
			data-values="<?php echo esc_attr( wp_json_encode( array_values( $views_7d ) ) ); ?>"></canvas>
	</div>
	<?php
	rd_panel_card_close();

	// ============ Section 4: Theme Updates ============
	rd_dashboard_render_updates_card();

	// ============ Section 5: Quick Actions ============
	rd_panel_section_header(
		array(
			'icon'  => 'admin-tools',
			'title' => __( 'Quick Actions', 'reloaded' ),
			'desc'  => __( 'Jump straight to the most-used screens.', 'reloaded' ),
		)
	);

	rd_panel_card_open();
	echo '<p class="rd-dashboard-actions">';
	foreach ( rd_dashboard_get_quick_actions() as $action ) {
		printf(
			'<a href="%1$s" class="button"><span class="dashicons %2$s" aria-hidden="true"></span> %3$s</a> ',
			esc_url( $action['url'] ),
			esc_attr( $action['icon'] ),
			esc_html( $action['label'] )
		);
	}
	echo '</p>';
	rd_panel_card_close();

	// ============ Section 4: Footer Info ============
	$theme       = wp_get_theme();
	$theme_ver   = $theme->get( 'Version' );
	$wp_ver      = get_bloginfo( 'version' );
	$php_ver     = PHP_VERSION;
	$footer_text = sprintf(
		/* translators: 1: theme version, 2: WordPress version, 3: PHP version */
		esc_html__( 'Theme %1$s · WordPress %2$s · PHP %3$s', 'reloaded' ),
		'<code>' . esc_html( $theme_ver ) . '</code>',
		'<code>' . esc_html( $wp_ver ) . '</code>',
		'<code>' . esc_html( $php_ver ) . '</code>'
	);
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $footer_text contém só esc_html() em cada valor dinâmico; o resto é literal HTML (<code>).
	echo '<p class="rd-dashboard-footer-info">' . $footer_text . '</p>';

	rd_panel_dash_close();
}

/**
 * Renderiza o card "Theme Updates" no Dashboard.
 *
 * Lê a versão local (style.css) + tenta ler o cache da última checagem de
 * releases (sem disparar fetch novo — fetch só roda no botão "Check now" ou
 * na expiração natural do transient 24h). Botão no canto superior direito
 * dispara AJAX pra re-checar imediatamente.
 *
 * Server-side só renderiza estado FROZEN. JS (admin-self-update.js) atualiza
 * o conteúdo após resposta AJAX — DOM ids usados pra alvo:
 *   #rd-self-update-latest, #rd-self-update-status, #rd-self-update-last-check,
 *   #rd-self-update-action.
 */
function rd_dashboard_render_updates_card(): void {
	rd_panel_section_header(
		array(
			'icon'  => 'update',
			'title' => __( 'Theme Updates', 'reloaded' ),
			'desc'  => __( 'The theme checks GitHub Releases every 24h. Click the button to check immediately.', 'reloaded' ),
		)
	);

	$current = (string) wp_get_theme( RD_SELF_UPDATE_SLUG )->get( 'Version' );

	// Lê cache sem disparar fetch (server-side render barato).
	$cached      = get_transient( RD_SELF_UPDATE_TRANSIENT );
	$latest      = ( is_array( $cached ) && ! empty( $cached['version'] ) ) ? $cached['version'] : '';
	$checked_at  = ( is_array( $cached ) && ! empty( $cached['checked_at'] ) ) ? (int) $cached['checked_at'] : 0;
	$release_url = ( is_array( $cached ) && ! empty( $cached['release_url'] ) ) ? (string) $cached['release_url'] : '';

	if ( '' === $latest ) {
		$status_html      = rd_panel_badge( 'neutral', __( 'Never checked', 'reloaded' ) );
		$last_check_human = __( 'never', 'reloaded' );
		$has_update       = false;
	} else {
		$has_update = version_compare( $latest, $current, '>' );
		if ( $has_update ) {
			$status_html = rd_panel_badge( 'warning', __( 'Update available', 'reloaded' ) );
		} else {
			$status_html = rd_panel_badge( 'success', __( 'Up to date', 'reloaded' ) );
		}
		$last_check_human = $checked_at > 0
			/* translators: %s: human-readable time-ago (e.g. "2 hours") */
			? sprintf( __( '%s ago', 'reloaded' ), human_time_diff( $checked_at, time() ) )
			: __( 'unknown', 'reloaded' );
	}

	$nonce = wp_create_nonce( 'rd_self_update_check' );

	rd_panel_card_open( array( 'class' => 'rd-self-update' ) );
	?>
	<div class="rd-self-update__header">
		<h3 class="rd-self-update__title"><?php esc_html_e( 'Release status', 'reloaded' ); ?></h3>
		<button type="button"
				id="rd-self-update-check"
				class="button"
				data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Check for updates', 'reloaded' ); ?>
		</button>
	</div>

	<dl class="rd-self-update__grid">
		<dt><?php esc_html_e( 'Current version', 'reloaded' ); ?></dt>
		<dd><code><?php echo esc_html( $current ); ?></code></dd>

		<dt><?php esc_html_e( 'Latest version', 'reloaded' ); ?></dt>
		<dd>
			<code id="rd-self-update-latest"><?php echo esc_html( '' !== $latest ? $latest : '—' ); ?></code>
			<span id="rd-self-update-status">
				<?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_panel_badge() escapa internamente. ?>
			</span>
		</dd>

		<dt><?php esc_html_e( 'Last check', 'reloaded' ); ?></dt>
		<dd id="rd-self-update-last-check"><?php echo esc_html( $last_check_human ); ?></dd>
	</dl>

	<p class="rd-self-update__action" id="rd-self-update-action">
		<?php if ( $has_update ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>">
				<?php esc_html_e( 'Go to Themes → Update Now', 'reloaded' ); ?>
			</a>
			<?php if ( '' !== $release_url ) : ?>
				<a class="button-link" href="<?php echo esc_url( $release_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View release on GitHub', 'reloaded' ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</p>
	<?php
	rd_panel_card_close();
}

/**
 * Coleta dados de status das features principais.
 *
 * Cada item retornado tem:
 *   - title  : string traduzida do nome da feature
 *   - badge  : HTML do badge (gerado por rd_panel_badge, já escapado)
 *   - detail : HTML adicional opcional (vazio ou com info contextual)
 *
 * @return array[] Array de cards de status, na ordem de exibição.
 */
function rd_dashboard_get_status_data(): array {
	$data = array();

	// --- Content Security Policy ---
	$csp_on      = rd_get_option_bool( 'enable_csp_report_only' );
	$csp_enforce = rd_get_option_bool( 'csp_enforce_mode' );
	if ( ! $csp_on ) {
		$csp_badge = rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) );
	} elseif ( $csp_enforce ) {
		$csp_badge = rd_panel_badge( 'danger', __( 'ENFORCE', 'reloaded' ) );
	} else {
		$csp_badge = rd_panel_badge( 'info', __( 'REPORT-ONLY', 'reloaded' ) );
	}
	$data[] = array(
		'title'  => __( 'CSP', 'reloaded' ),
		'badge'  => $csp_badge,
		'detail' => '',
		'toggle' => 'enable_csp_report_only',
		'value'  => $csp_on,
		'link'   => admin_url( 'admin.php?page=rd_options&tab=security#sec_seg_csp' ),
	);

	// --- Login Protection ---
	// Master switch (enable_login_protection) controla TODAS as features do módulo.
	// Slug salvo é preservado quando OFF — visível como detail pra contexto, mas
	// inativo. Badge mostra estado da feature mestra (ON/OFF).
	$login_active = rd_get_option_bool( 'enable_login_protection' );
	$slug         = trim( (string) rd_get_option( 'login_secret_slug' ) );
	if ( ! $login_active ) {
		$login_badge  = rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) );
		$login_detail = '';
	} else {
		$login_badge  = rd_panel_badge( 'success', __( 'ON', 'reloaded' ) );
		$login_detail = '' !== $slug ? ' <code>/' . esc_html( $slug ) . '</code>' : '';
	}
	$data[] = array(
		'title'  => __( 'Login Protection', 'reloaded' ),
		'badge'  => $login_badge,
		'detail' => $login_detail,
		'toggle' => 'enable_login_protection',
		'value'  => $login_active,
		'link'   => admin_url( 'admin.php?page=rd_options&tab=security#sec_seg_login' ),
	);

	// --- Maintenance Mode (toggle inline) ---
	// Badge ON usa variant `success` (verde) pra consistência com outros toggles,
	// + ⚠️ prefix pra reforço visual de "estado anormal/temporário" (site
	// bloqueado pra visitantes). Bypassa rd_panel_badge() helper pra inserir o
	// emoji num <span> com vertical-align próprio — o helper escapa HTML via
	// esc_html() e quebraria o markup.
	$maint  = rd_get_option_bool( 'maintenance_mode' );
	$data[] = array(
		'title'   => __( 'Maintenance Mode', 'reloaded' ),
		'badge'   => $maint
			? '<span class="rd-pbadge rd-pbadge--success"><span class="rd-pbadge__emoji">⚠️</span>' . esc_html__( 'ON', 'reloaded' ) . '</span>'
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail'  => '',
		'toggle'  => 'maintenance_mode',
		'value'   => $maint,
		'link'    => admin_url( 'admin.php?page=rd_options&tab=maintenance#sec_manut_access' ),
		'confirm' => __( 'Maintenance Mode blocks ALL visitors of the site. Are you sure?', 'reloaded' ),
	);

	// --- Statistics Tracking (toggle inline) ---
	// Controla a coleta real de views (enable_views_tracking). Quando OFF,
	// o tracker para de registrar novas visualizações — mas o histórico
	// fica preservado e a aba Statistics continua acessível.
	$tracking = rd_get_option_bool( 'enable_views_tracking' );
	$data[]   = array(
		'title'  => __( 'Statistics Tracking', 'reloaded' ),
		'badge'  => $tracking
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_views_tracking',
		'value'  => $tracking,
		'link'   => admin_url( 'admin.php?page=rd_options&tab=statistics#sec_stats_tracking' ),
	);

	// --- Critical CSS Inline (toggle inline) ---
	$cc     = rd_get_option_bool( 'inline_critical_css' );
	$data[] = array(
		'title'  => __( 'Critical CSS Inline', 'reloaded' ),
		'badge'  => $cc
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'inline_critical_css',
		'value'  => $cc,
	);

	// --- Next-gen Images (WebP/AVIF) ---
	$img = rd_get_option_bool( 'enable_next_gen_images' );
	if ( ! $img ) {
		$img_badge  = rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) );
		$img_detail = '';
	} else {
		$img_mode   = strtoupper( (string) rd_get_option( 'image_format_mode', 'avif' ) );
		$img_badge  = rd_panel_badge( 'success', __( 'ON', 'reloaded' ) );
		$img_detail = ' <code>' . esc_html( $img_mode ) . '</code>';
	}
	$data[] = array(
		'title'  => __( 'Next-gen Images', 'reloaded' ),
		'badge'  => $img_badge,
		'detail' => $img_detail,
		'toggle' => 'enable_next_gen_images',
		'value'  => $img,
		'link'   => admin_url( 'admin.php?page=rd_options&tab=media#sec_media_upload' ),
	);

	// --- Row 2 (Wave 11 — 6 cards adicionais pra grid 12-cards 3×4) ---

	// Top Bar (toggle inline)
	$top_bar = rd_get_option_bool( 'enable_top_bar' );
	$data[]  = array(
		'title'  => __( 'Top Bar', 'reloaded' ),
		'badge'  => $top_bar
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_top_bar',
		'value'  => $top_bar,
	);

	// Comments (toggle inline)
	$comments = rd_get_option_bool( 'enable_comments_globally' );
	$data[]   = array(
		'title'  => __( 'Comments', 'reloaded' ),
		'badge'  => $comments
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_comments_globally',
		'value'  => $comments,
	);

	// Markdown (toggle inline)
	$markdown = rd_get_option_bool( 'markdown_enabled' );
	$data[]   = array(
		'title'  => __( 'Markdown', 'reloaded' ),
		'badge'  => $markdown
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'markdown_enabled',
		'value'  => $markdown,
	);

	// Discord Widget (toggle inline)
	$discord = rd_get_option_bool( 'discord_widget' );
	$data[]  = array(
		'title'  => __( 'Discord Widget', 'reloaded' ),
		'badge'  => $discord
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'discord_widget',
		'value'  => $discord,
		'link'   => admin_url( 'admin.php?page=rd_options&tab=integrations#sec_int_discord' ),
	);

	// YouTube Facade (toggle inline)
	$yt_facade = rd_get_option_bool( 'facade_youtube' );
	$data[]    = array(
		'title'  => __( 'YouTube Facade', 'reloaded' ),
		'badge'  => $yt_facade
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'facade_youtube',
		'value'  => $yt_facade,
	);

	// Breadcrumbs (toggle inline)
	$breadcrumbs = rd_get_option_bool( 'enable_breadcrumbs' );
	$data[]      = array(
		'title'  => __( 'Breadcrumbs', 'reloaded' ),
		'badge'  => $breadcrumbs
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_breadcrumbs',
		'value'  => $breadcrumbs,
	);

	// --- Row 3: SEO + Compliance (Wave 12 — 3 cards adicionais pra grid 5×3 = 15-cards) ---

	// Cookie Banner (toggle inline) — banner de consent granular no footer (LGPD/GDPR)
	$lgpd   = rd_get_option_bool( 'enable_lgpd' );
	$data[] = array(
		'title'  => __( 'Cookie Banner', 'reloaded' ),
		'badge'  => $lgpd
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_lgpd',
		'value'  => $lgpd,
	);

	// Sitemap (toggle inline) — /wp-sitemap.xml nativo WP 5.5+
	$sitemap = rd_get_option_bool( 'enable_sitemap' );
	$data[]  = array(
		'title'  => __( 'Sitemap', 'reloaded' ),
		'badge'  => $sitemap
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_sitemap',
		'value'  => $sitemap,
	);

	// Open Graph — switch ON/OFF + gear pra config detalhada (og_fallback_image).
	$og     = rd_get_option_bool( 'enable_open_graph' );
	$data[] = array(
		'title'  => __( 'Open Graph', 'reloaded' ),
		'badge'  => $og
			? rd_panel_badge( 'success', __( 'ON', 'reloaded' ) )
			: rd_panel_badge( 'neutral', __( 'OFF', 'reloaded' ) ),
		'detail' => '',
		'toggle' => 'enable_open_graph',
		'value'  => $og,
		'link'   => admin_url( 'admin.php?page=rd_options&tab=seo#sec_seo_og' ),
	);

	return $data;
}

/**
 * Coleta métricas rápidas pra cards big-number.
 *
 * Só é chamado quando enable_stats está ON (gating em rd_dashboard_render()).
 * Reusa rd_stats_total_views() pra views (cacheado em transient pelo mod-stats).
 *
 * @return array[] Array de métricas, cada uma com title/value/hint.
 */
function rd_dashboard_get_metrics_data(): array {
	$metrics = array();

	// --- Views nas últimas 24h ---
	// Bypass do cache do mod-stats: a função rd_stats_total_views() guarda
	// resultado em transient com TTL 1h, o que deixa o Dashboard mostrando
	// dados até 1h velhos. Aqui forçamos refresh deletando o transient
	// específico antes de chamar — custo: 1 query SQL extra por render do
	// Dashboard, insignificante. Outros transients de stats continuam
	// cacheados pra performance do Stats Dashboard (aba Estatísticas).
	if ( function_exists( 'rd_stats_total_views' ) && defined( 'RD_STATS_CACHE_PREFIX' ) ) {
		delete_transient( RD_STATS_CACHE_PREFIX . 'total_day' );
		$views_24h = (int) rd_stats_total_views( 'day' );
	} else {
		$views_24h = 0;
	}
	$metrics[] = array(
		'title' => __( 'Views Last 24h', 'reloaded' ),
		'value' => number_format_i18n( $views_24h ),
		'hint'  => '',
	);

	// --- Posts publicados nos últimos 30 dias (rolling) ---
	// Janela rolling (alinha com "Views Last 24h" — ambas rolling, evita o
	// confuso "no dia 1 do mês mostra zero" do calendar month.
	// Query rápida: só IDs, sem found_rows, e limit -1 pra contar todos.
	$cutoff_30d  = time() - ( 30 * DAY_IN_SECONDS );
	$month_posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => gmdate( 'Y-m-d H:i:s', $cutoff_30d ),
					'inclusive' => true,
				),
			),
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	$metrics[]   = array(
		'title' => __( 'Posts Last 30 Days', 'reloaded' ),
		'value' => number_format_i18n( count( $month_posts ) ),
		'hint'  => '',
	);

	// --- Comentários pendentes ---
	$comments_count = wp_count_comments();
	$pending        = isset( $comments_count->moderated ) ? (int) $comments_count->moderated : 0;
	if ( $pending > 0 ) {
		$pending_hint = sprintf(
			/* translators: %s is the URL to the WordPress comments moderation queue */
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ),
			esc_html__( 'Review queue', 'reloaded' )
		);
	} else {
		$pending_hint = esc_html__( 'Inbox zero ✓', 'reloaded' );
	}
	$metrics[] = array(
		'title' => __( 'Pending Comments', 'reloaded' ),
		'value' => number_format_i18n( $pending ),
		'hint'  => $pending_hint,
	);

	return $metrics;
}

/**
 * Lista de atalhos rápidos pras abas mais usadas.
 *
 * Ordem otimizada por frequência esperada de uso. "Statistics" só aparece
 * se a feature estiver ativada.
 *
 * @return array[] Array de actions com url/icon/label.
 */
function rd_dashboard_get_quick_actions(): array {
	$base    = admin_url( 'admin.php?page=rd_options' );
	$actions = array(
		array(
			'url'   => $base . '&tab=general',
			'icon'  => 'dashicons-admin-settings',
			'label' => __( 'General Settings', 'reloaded' ),
		),
		array(
			'url'   => $base . '&tab=security',
			'icon'  => 'dashicons-shield-alt',
			'label' => __( 'Security & CSP', 'reloaded' ),
		),
		array(
			'url'   => $base . '&tab=media',
			'icon'  => 'dashicons-format-image',
			'label' => __( 'Images & Media', 'reloaded' ),
		),
		array(
			'url'   => $base . '&tab=backup',
			'icon'  => 'dashicons-backup',
			'label' => __( 'Backup', 'reloaded' ),
		),
	);

	$actions[] = array(
		'url'   => $base . '&tab=statistics',
		'icon'  => 'dashicons-chart-line',
		'label' => __( 'Statistics', 'reloaded' ),
	);

	return $actions;
}

/**
 * Agrega views dos últimos 7 dias, agrupados por dia.
 *
 * Itera todos os logs de views (`_rd_post_views_log` em postmeta), separa
 * cada timestamp por bucket de dia, retorna array `[ 'DD/MM' => count, ... ]`
 * ordenado cronologicamente (dia mais antigo → mais recente).
 *
 * Sem cache (mesma filosofia do "Views Last 24h" — Dashboard mostra dados
 * frescos a cada render; custo aceitável porque admin não carrega Dashboard
 * milhares de vezes/hora).
 *
 * Wave 11 Fase G — alimenta o bar chart "Activity Trend" no Dashboard.
 *
 * @return int[] Map `'DD/MM' => count`, 7 entradas (cronológico).
 */
function rd_dashboard_get_views_7d(): array {
	global $wpdb;

	// Bucket inicial: 7 dias, hoje incluso, ordem cronológica.
	// Format `DD/MM` pra label compacto no eixo X do gráfico.
	$buckets = array();
	for ( $i = 6; $i >= 0; $i-- ) {
		$ts                = strtotime( "-{$i} days", time() );
		$label             = wp_date( 'd/m', $ts );
		$buckets[ $label ] = 0;
	}

	// Cutoff: meia-noite de "6 dias atrás" (inclusivo) — qualquer view antes
	// disso não importa pro gráfico de 7d.
	$cutoff = strtotime( '-6 days 00:00:00', time() );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- query single-purpose pro Dashboard (read-only render); volume baixo (1 chamada por load do dashboard); resultado não precisa de cache porque o Dashboard sempre quer fresh data nessa seção.
	$logs = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			defined( 'RD_VIEWS_META_KEY_LOG' ) ? RD_VIEWS_META_KEY_LOG : '_rd_post_views_log'
		)
	);

	foreach ( $logs as $log_serialized ) {
		$log = maybe_unserialize( $log_serialized );
		if ( ! is_array( $log ) ) {
			continue;
		}
		foreach ( $log as $timestamp ) {
			$ts = (int) $timestamp;
			if ( $ts < $cutoff ) {
				continue;
			}
			$label = wp_date( 'd/m', $ts );
			if ( isset( $buckets[ $label ] ) ) {
				++$buckets[ $label ];
			}
		}
	}

	return $buckets;
}

/*
=============================================================================
 *  AJAX TOGGLE — endpoint pra switches inline do Dashboard (Wave 11 Cat B)
 * ============================================================================= */

/**
 * Whitelist de option keys que podem ser flipadas pelo toggle inline do Dashboard.
 *
 * Defesa em profundidade: mesmo com nonce + capability check, restringimos
 * EXPLICITAMENTE quais options o endpoint aceita. Se algum admin malicioso ou
 * bug futuro tentar passar outro key, retorna erro silencioso.
 *
 * Ampliar SÓ pra options claramente binárias (0/1) que façam sentido como
 * toggle de status — não pra options complexas tipo strings ou textareas.
 */
const RD_DASHBOARD_TOGGLE_WHITELIST = array(
	'maintenance_mode',
	'enable_views_tracking',
	'inline_critical_css',
	// Switches dos 4 cards "gear-only" — Fase 3:
	'enable_csp_report_only',
	'enable_login_protection',
	'enable_next_gen_images',
	'enable_open_graph',
	// 6 toggles da segunda fileira de cards do Site Status — todas features
	// safe pra flipar (sem efeito destrutivo). Maintenance é o único que
	// bloqueia visitantes, e tem confirm dialog próprio no JS.
	'enable_top_bar',
	'enable_comments_globally',
	'markdown_enabled',
	'discord_widget',
	'facade_youtube',
	'enable_breadcrumbs',
	// Wave 12 — 3 cards adicionais (Row 3 do grid 5×3): SEO + Compliance.
	'enable_lgpd',
	'enable_sitemap',
	// (Open Graph não entra aqui — é deep link/gear, não switch)
);

/**
 * AJAX handler — flipa uma option booleana via fetch().
 *
 * Espera POST com:
 *   - action: 'rd_dashboard_toggle' (resolvido pelo WP)
 *   - key:    nome da option (deve estar em RD_DASHBOARD_TOGGLE_WHITELIST)
 *   - value:  '0' ou '1' (novo valor desejado)
 *   - _wpnonce: nonce verificado via check_ajax_referer
 *
 * Retorna JSON:
 *   { ok: true, key: '...', value: 1 } — sucesso
 *   { ok: false, error: '...' } — falha
 *
 * Códigos HTTP: 200 OK em sucesso/erro de domínio, 403 em capability fail,
 * 400 em validação fail.
 */
function rd_dashboard_ajax_toggle(): void {
	// Capability — só manage_options. Mesmo gate do painel inteiro.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'ok'    => false,
				'error' => 'insufficient_capability',
			),
			403
		);
	}

	// Nonce — protege CSRF. Falha aqui se nonce ausente/inválido.
	check_ajax_referer( 'rd_dashboard_toggle', '_wpnonce' );

	// Validação do key — deve estar na whitelist.
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	if ( '' === $key || ! in_array( $key, RD_DASHBOARD_TOGGLE_WHITELIST, true ) ) {
		wp_send_json(
			array(
				'ok'    => false,
				'error' => 'key_not_allowed',
			),
			400
		);
	}

	// Value — só aceitamos '0' ou '1' (boolean toggle).
	$value_raw = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
	if ( '0' !== $value_raw && '1' !== $value_raw ) {
		wp_send_json(
			array(
				'ok'    => false,
				'error' => 'invalid_value',
			),
			400
		);
	}
	$value = (int) $value_raw;

	// Update da option dentro do rd_settings array.
	$opt = get_option( 'rd_settings', array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}
	$opt[ $key ] = $value;
	update_option( 'rd_settings', $opt );

	wp_send_json(
		array(
			'ok'    => true,
			'key'   => $key,
			'value' => $value,
		)
	);
}
add_action( 'wp_ajax_rd_dashboard_toggle', 'rd_dashboard_ajax_toggle' );
// Sem wp_ajax_nopriv — endpoint só pra admins logados (capability check já
// rejeitaria, mas registrar apenas no `wp_ajax_` deixa o intent claro).

/**
 * Enfileira o JS dos toggles inline (rd-pswitch) — só na aba Dashboard.
 *
 * Independente do Chart.js / mod-stats — vive aqui pra manter responsabilidade
 * clara (toggle é feature do Dashboard, não do Stats).
 *
 * @param string $hook Hook suffix da página atual no admin.
 */
function rd_dashboard_admin_enqueue( $hook ): void {
	if ( 'toplevel_page_rd_options' !== $hook ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate em admin_enqueue_scripts: decide se enfileira o JS dos toggles, não processa form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
	if ( 'dashboard' !== $active_tab ) {
		return;
	}

	wp_enqueue_script(
		'rd-admin-dashboard-toggle',
		get_template_directory_uri() . '/assets/js/admin-dashboard-toggle.js',
		array(),
		rd_asset_version( '/assets/js/admin-dashboard-toggle.js' ),
		true
	);

	wp_enqueue_script(
		'rd-admin-self-update',
		get_template_directory_uri() . '/assets/js/admin-self-update.js',
		array(),
		rd_asset_version( '/assets/js/admin-self-update.js' ),
		true
	);

	wp_localize_script(
		'rd-admin-self-update',
		'rdSelfUpdate',
		array(
			'themes_url' => admin_url( 'themes.php' ),
			'i18n'       => array(
				'checking'         => __( 'Checking…', 'reloaded' ),
				'just_now'         => __( 'just now', 'reloaded' ),
				'up_to_date'       => __( 'Up to date', 'reloaded' ),
				'update_available' => __( 'Update available', 'reloaded' ),
				'go_to_themes'     => __( 'Go to Themes → Update Now', 'reloaded' ),
				'view_release'     => __( 'View release on GitHub', 'reloaded' ),
				'network_error'    => __( 'Could not reach GitHub. Try again later.', 'reloaded' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rd_dashboard_admin_enqueue' );
