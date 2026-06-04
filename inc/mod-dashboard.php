<?php
/**
 * Module: Dashboard — Theme Overview (Wave 11 Phase F).
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Dashboard — "Overview" tab of the admin panel (Wave 11 Phase F)      *
 *                                                                              *
 * Read-only landing tab. Becomes the FIRST tab (default when the admin opens   *
 * the panel). Shows the state of the main features, key metrics and quick      *
 * shortcuts to the other tabs.                                                 *
 *                                                                              *
 * Rendered 100% via the design system's `rd-p*` components (Wave 11 Phase C).  *
 * No JS — every update comes from a page reload (data is instantaneous).       *
 *                                                                              *
 * Structure:                                                                   *
 *   1. Site Status     — 6 cards with ON/OFF badge of key features             *
 *   2. Quick Metrics   — 3 big-number cards (views 24h, posts/month, comments) *
 *   3. Quick Actions   — buttons to shortcut to the other relevant tabs        *
 *   4. Footer Info     — theme version · WP · PHP                              *
 *******************************************************************************/

/**
 * Main callback — renders the entire tab.
 * Called by `add_settings_section('sec_dashboard', ...)` in panel.php.
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
	$status_tips  = rd_dashboard_get_status_tooltips();
	foreach ( rd_dashboard_get_status_data() as $item ) {
		rd_panel_card_open(
			array(
				'title'   => $item['title'],
				'tooltip' => $status_tips[ $item['toggle'] ] ?? '',
			)
		);

		/*
		 * Status line: 2 groups side by side with justify-content: space-between.
		 *   Left:  .rd-dashboard-status-controls (switch + gear, any combination)
		 *   Right: .rd-dashboard-status-info     (badge + detail)
		 *
		 * Order reversed from the natural one to stabilize the switch/gear position —
		 * when the badge changes from "ON" to "OFF" (~5px wider), the adjustment
		 * happens in the central gap, not in the control's position. Without this, the
		 * switch/gear "jumps" 1px sideways on each toggle.
		 *
		 * The controls wrapper also ensures switch + gear stay together
		 * on the left when both are present — without the wrapper, space-between
		 * would spread the 3 elements (switch, gear, info) into 3 equal columns.
		 */
		echo '<p class="rd-dashboard-status-line">';
		echo '<span class="rd-dashboard-status-controls">';

		// Inline toggle
		if ( ! empty( $item['toggle'] ) ) {
			$confirm_attr = ! empty( $item['confirm'] )
				? ' data-rd-confirm="' . esc_attr( $item['confirm'] ) . '"'
				: '';
			// Dynamic tooltip — shows the next available action based on current state.
			// Both labels passed as data-attrs for the JS to swap after flipping (no reload).
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

		// Gear link (deep link to a configurable section). Can coexist with the toggle —
		// admin uses the switch for quick on/off, the gear for detailed config.
		if ( ! empty( $item['link'] ) ) {
			$tooltip_label = esc_attr__( 'Configure', 'reloaded' );
			printf(
				'<a href="%1$s" class="rd-dashboard-card-link" data-tooltip="%2$s" aria-label="%2$s"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span></a>',
				esc_url( $item['link'] ),
				$tooltip_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above via esc_attr__.
			);
		}

		echo '</span>'; // .rd-dashboard-status-controls

		// Info group (right): badge + detail. inline-flex wrapper to
		// preserve the 10px gap between badge and detail (e.g. badge "ON" + <code>slug</code>).
		echo '<span class="rd-dashboard-status-info">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pre-escaped by rd_panel_badge(); detail is controlled HTML (esc_html applied to dynamic values).
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
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hint is controlled HTML (link with esc_url applied below).
			echo '<div class="rd-pcard__big-hint">' . $metric['hint'] . '</div>';
		}
		rd_panel_card_close();
	}
	echo '</div>';

	// ============ Section 3: Activity Trend (Wave 11 Phase G) ============
	// Bar chart of views per day over the last 7 days. Always renders —
	// when there's no data (tracking never enabled or freshly installed), the
	// get_views_7d function returns a zeroed array and the chart shows all
	// bars at zero (preview of the future layout).
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
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $footer_text contains only esc_html() on each dynamic value; the rest is literal HTML (<code>).
	echo '<p class="rd-dashboard-footer-info">' . $footer_text . '</p>';

	rd_panel_dash_close();
}

/**
 * Renders the "Theme Updates" card on the Dashboard.
 *
 * Reads the local version (style.css) + tries to read the cache of the last
 * release check (without triggering a new fetch — fetch only runs on the "Check now"
 * button or on the natural 24h transient expiration). The button in the top-right
 * corner fires AJAX to re-check immediately.
 *
 * Server-side only renders the FROZEN state. JS (admin-panel.js) updates
 * the content after the AJAX response — DOM ids used as targets:
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

	// Read cache without triggering a fetch (cheap server-side render).
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
				<?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_panel_badge() escapes internally. ?>
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
 * Collects status data for the main features.
 *
 * Each returned item has:
 *   - title  : translated string of the feature name
 *   - badge  : badge HTML (generated by rd_panel_badge, already escaped)
 *   - detail : optional extra HTML (empty or with contextual info)
 *
 * @return array[] Array of status cards, in display order.
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
	// Master switch (enable_login_protection) controls ALL the module's features.
	// The saved slug is preserved when OFF — visible as detail for context, but
	// inactive. The badge shows the master feature's state (ON/OFF).
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
	// The ON badge uses the `success` variant (green) for consistency with other toggles,
	// + a ⚠️ prefix for visual reinforcement of an "abnormal/temporary state" (site
	// blocked to visitors). Bypasses the rd_panel_badge() helper to insert the
	// emoji in a <span> with its own vertical-align — the helper escapes HTML via
	// esc_html() and would break the markup.
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
	// Controls the actual view collection (enable_views_tracking). When OFF,
	// the tracker stops recording new views — but the history
	// is preserved and the Statistics tab stays accessible.
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

	// --- Row 2 (Wave 11 — 6 additional cards for the 12-card 3×4 grid) ---

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

	// --- Row 3: SEO + Compliance (Wave 12 — 3 additional cards for the 5×3 = 15-card grid) ---

	// Cookie Banner (inline toggle) — granular consent banner in the footer (LGPD/GDPR)
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

	// Sitemap (inline toggle) — native /wp-sitemap.xml WP 5.5+
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

	// Open Graph — ON/OFF switch + gear for detailed config (og_fallback_image).
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
 * Short hover explanations for the Site Status cards.
 *
 * Keyed by each card's toggle option name so it maps onto
 * rd_dashboard_get_status_data() without bloating that array (which stays
 * focused on live state). Rendered as a tooltip on the card title via the
 * `tooltip` arg of rd_panel_card_open().
 *
 * @return array<string,string> Option name => one-line explanation.
 */
function rd_dashboard_get_status_tooltips(): array {
	return array(
		'enable_csp_report_only'   => __( 'Browser policy that blocks unauthorized scripts and resources.', 'reloaded' ),
		'enable_login_protection'  => __( 'Hides the login URL behind a secret slug and limits attempts.', 'reloaded' ),
		'maintenance_mode'         => __( 'Shows a holding page to visitors while the site is offline.', 'reloaded' ),
		'enable_views_tracking'    => __( 'Records the page views shown in the Statistics tab.', 'reloaded' ),
		'inline_critical_css'      => __( 'Inlines critical CSS to speed up the first paint.', 'reloaded' ),
		'enable_next_gen_images'   => __( 'Serves WebP/AVIF image versions to supported browsers.', 'reloaded' ),
		'enable_top_bar'           => __( 'Announcement bar shown above the site header.', 'reloaded' ),
		'enable_comments_globally' => __( 'Enables the comment system across the whole site.', 'reloaded' ),
		'markdown_enabled'         => __( 'Lets you write posts using Markdown syntax.', 'reloaded' ),
		'discord_widget'           => __( 'Shows your Discord server widget on the site.', 'reloaded' ),
		'facade_youtube'           => __( 'Replaces YouTube embeds with a light click-to-load preview.', 'reloaded' ),
		'enable_breadcrumbs'       => __( 'Shows the navigation trail above post titles.', 'reloaded' ),
		'enable_lgpd'              => __( 'Granular cookie consent banner (LGPD/GDPR).', 'reloaded' ),
		'enable_sitemap'           => __( 'Enables the native wp-sitemap.xml for search engines.', 'reloaded' ),
		'enable_open_graph'        => __( 'Adds Open Graph tags for rich social previews.', 'reloaded' ),
	);
}

/**
 * Collects quick metrics for the big-number cards.
 *
 * Only called when enable_stats is ON (gated in rd_dashboard_render()).
 * Reuses rd_stats_total_views() for views (cached in a transient by mod-stats).
 *
 * @return array[] Array of metrics, each with title/value/hint.
 */
function rd_dashboard_get_metrics_data(): array {
	$metrics = array();

	// --- Views in the last 24h ---
	// Bypass the mod-stats cache: the rd_stats_total_views() function stores its
	// result in a transient with a 1h TTL, which leaves the Dashboard showing
	// data up to 1h stale. Here we force a refresh by deleting the specific
	// transient before calling — cost: 1 extra SQL query per Dashboard
	// render, insignificant. Other stats transients stay
	// cached for the Stats Dashboard's performance (Statistics tab).
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

	// --- Posts published in the last 30 days (rolling) ---
	// Rolling window (aligns with "Views Last 24h" — both rolling, avoids the
	// confusing "on day 1 of the month it shows zero" of a calendar month.
	// Fast query: IDs only, no found_rows, and limit -1 to count them all.
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

	// --- Pending comments ---
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
 * List of quick shortcuts to the most-used tabs.
 *
 * Order optimized by expected frequency of use. "Statistics" only appears
 * if the feature is enabled.
 *
 * @return array[] Array of actions with url/icon/label.
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
 * Aggregates views from the last 7 days, grouped by day.
 *
 * Iterates over all view logs (`_rd_post_views_log` in postmeta), buckets
 * each timestamp by day, returns array `[ 'DD/MM' => count, ... ]`
 * sorted chronologically (oldest day → most recent).
 *
 * No cache (same philosophy as "Views Last 24h" — the Dashboard shows fresh
 * data on each render; acceptable cost because the admin doesn't load the Dashboard
 * thousands of times/hour).
 *
 * Wave 11 Phase G — feeds the "Activity Trend" bar chart on the Dashboard.
 *
 * @return int[] Map `'DD/MM' => count`, 7 entries (chronological).
 */
function rd_dashboard_get_views_7d(): array {
	global $wpdb;

	// Initial buckets: 7 days, today included, chronological order.
	// Format `DD/MM` for a compact label on the chart's X axis.
	$buckets = array();
	for ( $i = 6; $i >= 0; $i-- ) {
		$ts                = strtotime( "-{$i} days", time() );
		$label             = wp_date( 'd/m', $ts );
		$buckets[ $label ] = 0;
	}

	// Cutoff: midnight of "6 days ago" (inclusive) — any view before
	// that doesn't matter for the 7d chart.
	$cutoff = strtotime( '-6 days 00:00:00', time() );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- single-purpose query for the Dashboard (read-only render); low volume (1 call per dashboard load); result doesn't need caching because the Dashboard always wants fresh data in this section.
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
 *  AJAX TOGGLE — endpoint for the Dashboard's inline switches (Wave 11 Cat B)
 * ============================================================================= */

/**
 * Whitelist of option keys that can be flipped by the Dashboard's inline toggle.
 *
 * Defense in depth: even with nonce + capability check, we EXPLICITLY
 * restrict which options the endpoint accepts. If some malicious admin or
 * future bug tries to pass another key, it returns a silent error.
 *
 * Expand ONLY for clearly binary options (0/1) that make sense as a
 * status toggle — not for complex options like strings or textareas.
 */
const RD_DASHBOARD_TOGGLE_WHITELIST = array(
	'maintenance_mode',
	'enable_views_tracking',
	'inline_critical_css',
	// Switches of the 4 "gear-only" cards — Phase 3:
	'enable_csp_report_only',
	'enable_login_protection',
	'enable_next_gen_images',
	'enable_open_graph',
	// 6 toggles from the second row of Site Status cards — all features
	// safe to flip (no destructive effect). Maintenance is the only one that
	// blocks visitors, and it has its own confirm dialog in the JS.
	'enable_top_bar',
	'enable_comments_globally',
	'markdown_enabled',
	'discord_widget',
	'facade_youtube',
	'enable_breadcrumbs',
	// Wave 12 — 3 additional cards (Row 3 of the 5×3 grid): SEO + Compliance.
	'enable_lgpd',
	'enable_sitemap',
	// (Open Graph doesn't go here — it's a deep link/gear, not a switch)
);

/**
 * AJAX handler — flipa uma option booleana via fetch().
 *
 * Espera POST com:
 *   - action: 'rd_dashboard_toggle' (resolved by WP)
 *   - key:    option name (must be in RD_DASHBOARD_TOGGLE_WHITELIST)
 *   - value:  '0' or '1' (new desired value)
 *   - _wpnonce: nonce verified via check_ajax_referer
 *
 * Returns JSON:
 *   { ok: true, key: '...', value: 1 } — success
 *   { ok: false, error: '...' } — failure
 *
 * HTTP codes: 200 OK on success/domain error, 403 on capability fail,
 * 400 on validation fail.
 */
function rd_dashboard_ajax_toggle(): void {
	// Capability — manage_options only. Same gate as the whole panel.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'ok'    => false,
				'error' => 'insufficient_capability',
			),
			403
		);
	}

	// Nonce — protects against CSRF. Fails here if nonce missing/invalid.
	check_ajax_referer( 'rd_dashboard_toggle', '_wpnonce' );

	// Key validation — must be in the whitelist.
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

	// Value — we only accept '0' or '1' (boolean toggle).
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

	// Update the option inside the rd_settings array.
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
// No wp_ajax_nopriv — endpoint for logged-in admins only (the capability check
// would already reject it, but registering only on `wp_ajax_` makes the intent clear).

/**
 * Localizes the self-update data for the Dashboard tab.
 *
 * The toggle (rd-pswitch) and self-update JS now ship in the consolidated
 * admin-panel.js bundle; this function only attaches the rdSelfUpdate localized
 * data to that handle, gated to the Dashboard tab.
 *
 * @param string $hook Hook suffix of the current admin page.
 */
function rd_dashboard_admin_enqueue( $hook ): void {
	if ( 'toplevel_page_rd_options' !== $hook ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate in admin_enqueue_scripts: decides whether to enqueue the toggles JS, doesn't process a form.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
	if ( 'dashboard' !== $active_tab ) {
		return;
	}

	// The toggle + self-update JS now ship in the consolidated admin-panel.js
	// bundle (enqueued in core.php at priority 5). Here we only attach the
	// self-update localized data to that handle, still gated to the Dashboard tab.
	wp_localize_script(
		'rd-admin-panel',
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
