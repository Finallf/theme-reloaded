<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Advanced Options Panel - ReloadeD
 * Organized by Tabs for LGPD, Performance, SEO and Integrations.
 *******************************************************************************/

/*******************************************************************************
 * Default settings on theme activation                          - (Hardcoded) *
 *******************************************************************************/
/**
 * Source of truth for the panel defaults.
 *
 * Extracted as a separate function to be consumed both by the first-activation
 * (rd_set_default_options) and by the missing-keys migration
 * (rd_migrate_missing_options) — DRY between the two flows.
 */
function rd_get_default_options(): array {
	return array(
		'back_to_top'                 => 1, // ON
		'enable_top_bar'              => 1,
		'enable_breadcrumbs'          => 1,
		'sidebar_position'            => 'right',
		'image_resizing'              => 1,
		'enable_thumb_control'        => 1,
		'enable_primary_category'     => 1,
		'enable_post_kicker'          => 1,
		'jpeg_quality'                => 80, // Default value — balance between file size and visual quality (WP core default is 82).
		'enable_comments_globally'    => 1,
		'comment_a11y'                => 1,
		'excerpt_length'              => 55,
		'excerpt_text'                => '',
		'comments_separator'          => '',
		'footer_subline'              => '',
		'enable_views_tracking'       => 1,
		'views_number_format'         => 'full', // 'full' (1,234) or 'compact' (1.2k) on the frontend.
		'stats_top_limit'             => '5', // 5/10/15/20/25/30 — used in K1 and K3.
		'enable_theme_switch'         => 1,
		'default_theme_mode'          => 'system',

		'enable_carousel'             => 0,  // featured carousel between header and main (home page 1).
		'carousel_source'             => 'sticky', // sticky (w/ latest fallback) | category | latest.
		'carousel_category'           => '', // category ID when carousel_source = category.
		'carousel_count'              => '5', // 3-8 slides.
		'carousel_interval'           => '6', // autoplay seconds; 0 = off.

		'home_layout_grid'            => '3', // 0 (off) / 3 / 6 / 9 — hero + section showcase on the home.
		'home_layout_vertical'        => '3', // 0 (off) / 1 / 2 / 3.
		'home_layout_compact'         => '6', // 0 (off) / 2 / 4 / 6.

		'search_layout_grid'          => 1,
		'search_layout_vertical'      => 0,
		'search_layout_compact'       => 1,
		'search_layout_google'        => 0,

		'enable_lgpd'                 => 1,
		'lgpd_text'                   => '',

		'disable_xmlrpc'              => 1,
		'trusted_proxy_ips'           => '', // CIDR one per line — admin fills this if using a proxy/CDN other than Cloudflare (which is already the default).
		'enable_login_protection'     => 1, // Master flag for Login Protection — when OFF, slug is preserved but ignored + rate limit disabled.
		'login_secret_slug'           => '', // empty = dormant feature (/wp-login.php works normally).
		'login_rate_limit_max'        => 5,
		'login_rate_limit_window'     => 15, // minutes
		'login_hide_user_enumeration' => 1,  // default ON — hides "user does not exist" vs "wrong password" messages.
		'enable_csp_report_only'      => 0, // CSP OFF by default (opt-in, requires active monitoring).
		'csp_enforce_mode'            => 0, // enforce mode OFF — only promote after 30+ days in report-only.
		'csp_custom_scripts'          => '', // extra origins for script-src + connect-src (1 per line)
		'csp_custom_frames'           => '', // extra origins for frame-src (1 per line)
		'csp_custom_styles'           => '', // extra origins for style-src + font-src (1 per line)
		'csp_report_denylist'         => '', // blocked-uri hosts to drop from CSP reports (noise filter, 1 per line)

		'ga_id'                       => '',
		'google_site_verification'    => '',
		'bing_site_verification'      => '',
		'custom_verification_meta'    => '',
		'clarity_id'                  => '',
		'facebook_pixel_id'           => '',
		'tiktok_pixel_id'             => '',
		'plausible_domain'            => '',
		'umami_website_id'            => '',
		'umami_script_url'            => '',

		'markdown_enabled'            => 1,
		'prism_js'                    => 1,
		'enable_related_posts'        => 1,
		'enable_author_bio'           => 1,
		'enable_reading_time'         => 1,
		'enable_table_of_contents'    => 1,
		'enable_search_suggestions'   => 1,
		'disable_emojis'              => 1,
		'hide_wp_ver'                 => 1,
		'facade_youtube'              => 1,
		'disable_gutenberg_css'       => 1,
		'enable_security_headers'     => 1,
		'post_revisions_limit'        => 5,
		'optimize_heartbeat'          => 0,
		'preload_critical_fonts'      => 1,
		'inline_critical_css'         => 1, // ON by default — 5 critical-{template}.css files shipped in the repo (regenerate via npm run critical:all after major above-the-fold SCSS changes). Graceful degradation: if the template's file doesn't exist, falls back to synchronous load with no FOUC.
		'enable_next_gen_images'      => 1, // generates WebP/AVIF on upload + wraps <img> in <picture>
		'image_format_mode'           => 'avif', // accepted values: avif (default, best compression), webp, or both

		'social_discord'              => '',
		'social_telegram'             => '',
		'social_youtube'              => '',
		'social_instagram'            => '',
		'social_steam'                => '',
		'social_twitter'              => '',
		'social_facebook'             => '',
		'social_whatsapp'             => '',

		'enable_open_graph'           => 1,
		'og_fallback_image'           => '',
		'custom_robots_txt'           => '',
		'enable_indexnow'             => 0,
		'indexnow_key'                => '', // auto-generated on save when the toggle is on and this is empty.
		'enable_sitemap'              => 1,
		'sitemap_include_authors'     => 0,
		'sitemap_include_cpt'         => 1,

		'ad_global'                   => '',
		'ads_txt_content'             => '', // virtual /ads.txt content (served by mod-ads.php; empty = dormant).
		'ad_topo_desktop'             => '',
		'ad_topo_mobile'              => '',
		'ad_sidebar_sticky'           => '',

		'maintenance_mode'            => 0,
		'maintenance_pass'            => '',
		'maintenance_text'            => '',
	);
}

/**
 * First theme activation — populates the entire rd_settings with defaults.
 * Runs on after_switch_theme. Only does anything if the option doesn't exist yet.
 */
function rd_set_default_options() {
	$options = get_option( 'rd_settings' );

	if ( false === $options ) {
		update_option( 'rd_settings', rd_get_default_options() );
	}
}
add_action( 'after_switch_theme', 'rd_set_default_options' );

/**
 * Automatic migration of missing keys.
 *
 * Scenario: the theme is updated (git pull / FTP / release) and the code adds
 * new panel options via rd_get_default_options(). rd_settings already exists
 * in the database (from the first after_switch_theme) but WITHOUT those new keys.
 * Result without this migration: features stay silently dormant because
 * rd_get_option_bool() returns false (generic default) instead of the theme default.
 *
 * This function runs on admin_init (on every admin page load):
 *   1. Compares the current defaults with the saved rd_settings
 *   2. Detects missing keys via array_diff_key
 *   3. If there are new keys, merges (preserving existing values) and saves
 *   4. If there aren't, returns early without touching the database (fast path)
 *
 * Cost: 1 get_option (autoloaded, WP cache) + 1 array_diff_key. Imperceptible.
 */
function rd_migrate_missing_options() {
	$current = get_option( 'rd_settings', array() );
	if ( ! is_array( $current ) ) {
		$current = array();
	}

	$defaults = rd_get_default_options();
	$missing  = array_diff_key( $defaults, $current );

	if ( empty( $missing ) ) {
		return; // fast path — nothing missing
	}

	// Merge keeps existing values; only adds the missing keys.
	update_option( 'rd_settings', array_merge( $current, $missing ) );
}
add_action( 'admin_init', 'rd_migrate_missing_options' );

/***********************************************************************************
 * Safely fetches an option from the panel (Defensive Programming)    - (Hardcoded) *
 ***********************************************************************************/
function rd_get_option( string $key, $fallback = false ) {
	$opt = get_option( 'rd_settings' );

	if ( ! isset( $opt ) || ! isset( $opt[ $key ] ) ) {
		return $fallback;
	}

	return $opt[ $key ];
}

/***********************************************************************************
 * Boolean variant — returns true/false accepting any historical storage format    *
 * (1, '1', 0, '0', false, null, missing).                                         *
 * Use at every call site for checkbox-style toggles.                              *
 ***********************************************************************************/
function rd_get_option_bool( string $key ): bool {
	return (int) rd_get_option( $key ) === 1;
}

/*******************************************************************************
 * Creates the Panel Menu
 *
 * The panel is reachable via TWO paths in the admin:
 *   1. Top-level "ReloadeD" item in the sidebar (right below Dashboard, position 3)
 *   2. "ReloadeD" submenu inside "Appearance" (WP convention for theme options)
 *
 * Both open the same page (`?page=rd_options`).
 *******************************************************************************/
function rd_add_admin_menu() {
	// Top-level item — more visible, with the logo icon.
	add_menu_page(
		__( 'ReloadeD Options', 'reloaded' ),
		'ReloadeD',
		'manage_options',
		'rd_options',
		'rd_options_render',
		get_template_directory_uri() . '/assets/img/logo-reloaded-20x20.webp',
		3
	);

	// "Appearance" submenu — follows the WordPress convention of hosting theme
	// options under Themes/Appearance. Uses the same slug to point to the same
	// page rendered by the callback above.
	add_theme_page(
		__( 'ReloadeD Options', 'reloaded' ),
		__( 'ReloadeD', 'reloaded' ),
		'manage_options',
		'rd_options',
		'rd_options_render'
	);
}
add_action( 'admin_menu', 'rd_add_admin_menu' );

/*******************************************************************************
 * Renders the Visual Interface (HTML)
 *******************************************************************************/
function rd_options_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Defines the active tab (default is 'dashboard' — Wave 11 Phase F).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI render: $active_tab only decides which tab to highlight and which content to show; the actual save goes through Settings API (options.php) with its own nonce.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

	// Gets the absolute path of the theme folder to fetch the image.
	$theme_dir = get_template_directory_uri();

	// Master list of tabs for the loop (Wave 11 — reorganized taxonomy).
	// Dashboard is the first (default landing); the rest ordered by expected
	// frequency of use, with operational ones (Maintenance/Backup) at the end.
	$tabs = array(
		'dashboard'    => __( 'Dashboard', 'reloaded' ),
		'general'      => __( 'General', 'reloaded' ),
		'media'        => __( 'Images & Media', 'reloaded' ),
		'performance'  => __( 'Performance', 'reloaded' ),
		'security'     => __( 'Security & Privacy', 'reloaded' ),
		'integrations' => __( 'Integrations', 'reloaded' ),
		'seo'          => __( 'SEO', 'reloaded' ),
		'monetization' => __( 'Monetization', 'reloaded' ),
		'statistics'   => __( 'Statistics', 'reloaded' ),
		'maintenance'  => __( 'Maintenance', 'reloaded' ),
		'backup'       => __( 'Backup', 'reloaded' ),
	);

	// Defensive: invalid slug (typo, bookmark to a removed tab, etc.) falls
	// back to Dashboard instead of rendering a blank page.
	if ( ! isset( $tabs[ $active_tab ] ) ) {
		$active_tab = 'dashboard';
	}
	?>
	<div class="wrap">
		<div class="rd-panel-header">
			<h1 class="rd-panel-title"><?php esc_html_e( 'ReloadeD - Control Panel', 'reloaded' ); ?></h1>
			<img class="rd-panel-logo" src="<?php echo esc_url( $theme_dir ); ?>/assets/img/logo-reloaded-panel.webp" alt="ReloadeD Logo">
		</div>

			<?php settings_errors(); ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $id => $name ) : ?>
				<a href="?page=rd_options&tab=<?php echo esc_attr( $id ); ?>"
					class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $name ); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<form action="options.php" method="post" class="rd-panel-form">
			<?php
			settings_fields( 'rd_options_group' );

			foreach ( $tabs as $id => $name ) {
				$display = ( $active_tab === $id ) ? '' : 'display:none;';
				echo '<div class="rd-tab-content" id="tab-' . esc_attr( $id ) . '" style="' . esc_attr( $display ) . '">';
				do_settings_sections( 'rd_options_' . $id );
				echo '</div>';
			}

			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Builds the options array for a category <select> field: '' (none) + every
 * category (id => name). Used by the Featured Carousel section. Runs at
 * admin_init time only (settings registration), so the query never touches
 * the front-end.
 *
 * @return array<string,string>
 */
function rd_panel_category_options(): array {
	$options    = array( '' => __( '— Select —', 'reloaded' ) );
	$categories = get_categories( array( 'hide_empty' => false ) );
	foreach ( $categories as $category ) {
		$options[ (string) $category->term_id ] = $category->name;
	}
	return $options;
}

/*******************************************************************************
 * Registers the Fields and Sections in the Database
 *******************************************************************************/
function rd_settings_init() {
	register_setting( 'rd_options_group', 'rd_settings', 'rd_options_sanitize' );

	// --- DASHBOARD --- (Wave 11 Phase F).
	// Read-only tab (no editable fields). Single section with a custom callback
	// in mod-dashboard.php that renders status/metrics/actions.
	add_settings_section( 'sec_dashboard', '', 'rd_dashboard_render', 'rd_options_dashboard' );

	/*
	 * --- GENERAL --- (Wave 11: sub-divided into 5 sections by context.
	 * Received markdown_enabled + prism_js from Performance. Lost image_resizing,
	 * jpeg_quality, enable_views_tracking, views_number_format to other tabs.)
	 */

	// Section A: Visual Shell — site's visual layout (not content).
	rd_panel_register_section( 'sec_geral_shell', __( 'Visual Shell', 'reloaded' ), 'admin-appearance', 'rd_options_general' );
	add_settings_field(
		'enable_top_bar',
		__( 'Enable Top Bar', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'   => 'enable_top_bar',
			'type' => 'checkbox',
			'desc' => __( 'Displays a small bar at the top with date, latest news, and social networks.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_breadcrumbs',
		__( 'Show Breadcrumbs', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'   => 'enable_breadcrumbs',
			'type' => 'checkbox',
			'desc' => __( 'Displays a breadcrumb trail (Home › Category › Post) below the header on every page except the front page. The Schema.org BreadcrumbList for SEO is always generated, regardless of this toggle.', 'reloaded' ),
		)
	);
	add_settings_field(
		'sidebar_position',
		__( 'Sidebar Position', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'      => 'sidebar_position',
			'type'    => 'select',
			'options' => array(
				'right' => __( 'Right (default)', 'reloaded' ),
				'left'  => __( 'Left', 'reloaded' ),
			),
			'desc'    => __( 'Side where the sidebar appears on desktop layouts. On mobile (≤1024px) the sidebar always stacks below the main content regardless of this setting.', 'reloaded' ),
		)
	);
	add_settings_field(
		'back_to_top',
		__( 'Back to Top Button', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'   => 'back_to_top',
			'type' => 'checkbox',
			'desc' => __( 'Enables a floating button in the bottom right corner for the user to quickly return to the top of the page.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_theme_switch',
		__( 'Dark/Light Switcher', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'   => 'enable_theme_switch',
			'type' => 'checkbox',
			'desc' => __( 'Enables the theme mode switcher (Dark/Light) in the header.', 'reloaded' ),
		)
	);
	add_settings_field(
		'default_theme_mode',
		__( 'Default Theme Mode', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'      => 'default_theme_mode',
			'type'    => 'select',
			'options' => array(
				'system' => __( 'System (follow OS)', 'reloaded' ),
				'dark'   => __( 'Dark', 'reloaded' ),
				'light'  => __( 'Light', 'reloaded' ),
			),
			'desc'    => __( 'Initial theme mode for first-time visitors. Visitors who clicked the switcher keep their choice in localStorage.', 'reloaded' ),
		)
	);
	add_settings_field(
		'footer_subline',
		__( 'Footer Subline', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_shell',
		array(
			'id'          => 'footer_subline',
			'type'        => 'text',
			'placeholder' => __( 'Ex: Hosted on private infrastructure.', 'reloaded' ),
			'desc'        => __( 'Optional extra line below the copyright in the footer. Accepts basic HTML (links, strong, em). Leave empty to hide.', 'reloaded' ),
		)
	);

	// Section B: Content — editor + post rendering.
	rd_panel_register_section( 'sec_geral_content', __( 'Content', 'reloaded' ), 'editor-paragraph', 'rd_options_general' );
	add_settings_field(
		'enable_thumb_control',
		__( 'Featured Image', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_thumb_control',
			'type' => 'checkbox',
			'desc' => __( 'Adds an option in the post editor sidebar to hide the featured image when reading the article (ideal for posts with videos at the top).', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_primary_category',
		__( 'Primary Category', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_primary_category',
			'type' => 'checkbox',
			'desc' => __( 'Adds a dropdown in the post editor to choose which category highlights in the menu when the post belongs to multiple categories. Without this, all matching menu items light up at once.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_post_kicker',
		__( 'Post Overline', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_post_kicker',
			'type' => 'checkbox',
			'desc' => __( 'Adds a free-text "overline" field in the post editor (journalism-style label like INTERVIEW, EXCLUSIVE, ANALYSIS). When set, it renders above the title on single posts and above the title on grid cards (smaller, uppercase).', 'reloaded' ),
		)
	);
	add_settings_field(
		'excerpt_length',
		__( 'Excerpt Length (words)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'      => 'excerpt_length',
			'type'    => 'number',
			'min'     => 10,
			'max'     => 200,
			'default' => 55,
			'desc'    => __( 'Number of words shown in automatic excerpts on archives, search and category pages. The WordPress default is 55. Posts with manually written excerpts are not affected.', 'reloaded' ),
		)
	);
	add_settings_field(
		'excerpt_text',
		__( 'Read More Button Text', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'          => 'excerpt_text',
			'type'        => 'text',
			'placeholder' => __( 'Ex: Continue Reading &rarr;', 'reloaded' ),
			'desc'        => __( 'Customizes the excerpt button text. Leave blank to use the theme default.', 'reloaded' ),
		)
	);
	add_settings_field(
		'markdown_enabled',
		__( 'Markdown', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'markdown_enabled',
			'type' => 'checkbox',
			'desc' => __( 'Enables support for Markdown syntax, allowing you to write articles natively (like GitHub or Docker Hub).', 'reloaded' ),
		)
	);
	add_settings_field(
		'prism_js',
		__( 'Code Highlight', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'prism_js',
			'type' => 'checkbox',
			'desc' => __( 'Enables Syntax Highlight support, which colors programming codes. It is only loaded on posts for performance.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_related_posts',
		__( 'Related Posts', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_related_posts',
			'type' => 'checkbox',
			'desc' => __( 'Shows a block of 3 related posts at the end of each single post (between the article footer and the comments). Picks posts from the same primary category, ordered by tag overlap. Helps with time-on-site and reduces bounce rate.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_author_bio',
		__( 'Author Bio Box', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_author_bio',
			'type' => 'checkbox',
			'desc' => __( 'Shows a box with avatar, name, bio and social links of the post author at the end of each single. Reinforces E-E-A-T signals (the same data also feeds Schema.org Person on the author archive). If the author has no bio and no social links, the box auto-hides to avoid noise.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_reading_time',
		__( 'Reading Time', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_reading_time',
			'type' => 'checkbox',
			'desc' => __( 'Shows an estimated reading time (e.g. "5 min read") in the single post meta line, right-aligned next to the date/author/views. Computed at 200 words per minute (international average) and cached when the post is saved.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_table_of_contents',
		__( 'Table of Contents', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_content',
		array(
			'id'   => 'enable_table_of_contents',
			'type' => 'checkbox',
			'desc' => __( 'Adds a floating action button at the top-right of single posts that opens a collapsible panel with auto-generated links to each section (H2 and H3 headings). Only renders when the post has 3 or more headings — short posts stay clean. Click outside, press ESC, or click any heading to close the panel.', 'reloaded' ),
		)
	);

	// Section C: Comments — dedicated grouping for the 3 comment fields.
	rd_panel_register_section( 'sec_geral_comments', __( 'Comments', 'reloaded' ), 'admin-comments', 'rd_options_general' );
	add_settings_field(
		'enable_comments_globally',
		__( 'Enable Comments Globally', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_comments',
		array(
			'id'   => 'enable_comments_globally',
			'type' => 'checkbox',
			'desc' => __( 'Master switch that controls comments site-wide. When OFF, no comment form, list, count or Recent Comments widget appears on any post or page — regardless of per-post settings or the WordPress Discussion settings. Useful to quickly kill comments on the whole site (e.g. after migrating to Discourse, Disqus or another platform) without bulk-editing every post. When ON, normal WordPress behavior applies (each post can still be opened/closed individually). External comment plugins (WP-Discourse, Disqus, etc.) that override the comments template continue to work regardless of this toggle. <strong>Note:</strong> manually added widget blocks (e.g. a separate Heading "Comments" above the Recent Comments widget) may need to be removed manually in <em>Appearance → Widgets</em> to avoid orphaned titles.', 'reloaded' ),
		)
	);
	add_settings_field(
		'comment_a11y',
		__( 'Comment Accessibility', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_comments',
		array(
			'id'   => 'comment_a11y',
			'type' => 'checkbox',
			'desc' => __( 'Adds labels and autocomplete attributes to the comment form.', 'reloaded' ),
		)
	);
	add_settings_field(
		'comments_separator',
		__( 'Comments Separator', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_comments',
		array(
			'id'   => 'comments_separator',
			'type' => 'text',
			'desc' => __( 'Text between Author and Post (e.g., "commented on post:"). Leave <strong>empty</strong> for WP default or type <strong>&amp;nbsp;</strong> to hide.', 'reloaded' ),
		)
	);

	// Section: Featured Carousel — full-width band between header and main (home page 1).
	rd_panel_register_section( 'sec_geral_carousel', __( 'Featured Carousel', 'reloaded' ), 'images-alt2', 'rd_options_general' );
	add_settings_field(
		'enable_carousel',
		__( 'Enable Carousel', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_carousel',
		array(
			'id'   => 'enable_carousel',
			'type' => 'checkbox',
			'desc' => __( 'Shows a full-width featured carousel between the header and the home content (home page 1 only). Slides swipe natively on touch; autoplay pauses on hover, background tabs and off-screen.', 'reloaded' ),
		)
	);
	add_settings_field(
		'carousel_source',
		__( 'Content Source', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_carousel',
		array(
			'id'      => 'carousel_source',
			'type'    => 'select',
			'options' => array(
				'sticky'   => __( 'Sticky posts (falls back to latest)', 'reloaded' ),
				'category' => __( 'Category', 'reloaded' ),
				'latest'   => __( 'Latest posts', 'reloaded' ),
			),
			'desc'    => __( '<strong>Sticky posts</strong> = editorial curation: mark posts with "Stick to the top of the blog" in the editor and they enter the carousel (newest first). With none stickied, the latest posts fill in. Only posts with a featured image qualify. Carousel posts are automatically excluded from the home grids below (no duplicates).', 'reloaded' ),
		)
	);
	add_settings_field(
		'carousel_category',
		__( 'Category (if source = Category)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_carousel',
		array(
			'id'      => 'carousel_category',
			'type'    => 'select',
			'options' => rd_panel_category_options(),
			'desc'    => __( 'Used only when the Content Source above is set to Category.', 'reloaded' ),
		)
	);
	add_settings_field(
		'carousel_count',
		__( 'Slides', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_carousel',
		array(
			'id'      => 'carousel_count',
			'type'    => 'select',
			'options' => array(
				'3' => '3',
				'4' => '4',
				'5' => '5',
				'6' => '6',
				'7' => '7',
				'8' => '8',
			),
			'desc'    => __( 'How many slides the carousel holds.', 'reloaded' ),
		)
	);
	add_settings_field(
		'carousel_interval',
		__( 'Autoplay Interval', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_carousel',
		array(
			'id'      => 'carousel_interval',
			'type'    => 'select',
			'options' => array(
				'0'  => __( 'Off (manual only)', 'reloaded' ),
				'4'  => __( '4 seconds', 'reloaded' ),
				'6'  => __( '6 seconds', 'reloaded' ),
				'8'  => __( '8 seconds', 'reloaded' ),
				'10' => __( '10 seconds', 'reloaded' ),
			),
			'desc'    => __( 'Time between automatic slides. Respects the visitor\'s reduced-motion preference (autoplay stays off for them).', 'reloaded' ),
		)
	);

	// Section: Home Page — configurable showcase layout (hero + optional sections).
	rd_panel_register_section( 'sec_geral_home', __( 'Home Page', 'reloaded' ), 'admin-home', 'rd_options_general' );
	add_settings_field(
		'home_layout_grid',
		__( 'Grid Section', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_home',
		array(
			'id'      => 'home_layout_grid',
			'type'    => 'select',
			'options' => array(
				'0' => __( 'Off', 'reloaded' ),
				'3' => '3',
				'6' => '6',
				'9' => '9',
			),
			'desc'    => __( 'Number of Grid cards (3 per row) shown below the 2 hero cards on the home.', 'reloaded' ),
		)
	);
	add_settings_field(
		'home_layout_vertical',
		__( 'Vertical Section', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_home',
		array(
			'id'      => 'home_layout_vertical',
			'type'    => 'select',
			'options' => array(
				'0' => __( 'Off', 'reloaded' ),
				'1' => '1',
				'2' => '2',
				'3' => '3',
			),
			'desc'    => __( 'Number of Vertical cards (large image + excerpt) shown after the Grid section.', 'reloaded' ),
		)
	);
	add_settings_field(
		'home_layout_compact',
		__( 'Compact Section', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_home',
		array(
			'id'      => 'home_layout_compact',
			'type'    => 'select',
			'options' => array(
				'0' => __( 'Off', 'reloaded' ),
				'2' => '2',
				'4' => '4',
				'6' => '6',
			),
			'desc'    => __( 'Number of Compact cards (thumbnail + title, 2 per row) shown last. <strong>Note:</strong> with all three sections set to Off the home falls back to the classic grid of hero cards with pagination.', 'reloaded' ),
		)
	);

	// Section D: Search Page (already existed as a sub-section).
	rd_panel_register_section( 'sec_geral_search', __( 'Search Page', 'reloaded' ), 'search', 'rd_options_general' );
	add_settings_field(
		'search_layout_grid',
		__( 'Grid Layout', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_search',
		array(
			'id'   => 'search_layout_grid',
			'type' => 'checkbox',
			'desc' => __( 'Enables Grid layout (Cards side by side).', 'reloaded' ),
		)
	);
	add_settings_field(
		'search_layout_vertical',
		__( 'Vertical List', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_search',
		array(
			'id'   => 'search_layout_vertical',
			'type' => 'checkbox',
			'desc' => __( 'Enables Vertical List layout (Large image + excerpt).', 'reloaded' ),
		)
	);
	add_settings_field(
		'search_layout_compact',
		__( 'Compact List', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_search',
		array(
			'id'   => 'search_layout_compact',
			'type' => 'checkbox',
			'desc' => __( 'Enables Compact layout (Thumbnail + title inline). <strong>Note:</strong> Compact also acts as a safety net — if the visitor disables every chip on the frontend, all results fall back here regardless of this setting.', 'reloaded' ),
		)
	);
	add_settings_field(
		'search_layout_google',
		__( 'Google Style', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_search',
		array(
			'id'   => 'search_layout_google',
			'type' => 'checkbox',
			'desc' => __( 'Enables minimalist text-focused layout, similar to search engines.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_search_suggestions',
		__( 'Search Suggestions (autocomplete)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_general',
		'sec_geral_search',
		array(
			'id'   => 'enable_search_suggestions',
			'type' => 'checkbox',
			'desc' => __( 'Shows a dropdown of up to 5 matching posts as visitors type in the search input (after 3 characters, 250ms debounce). Speeds up content discovery — visitors can jump to a post without leaving the form. Results cached for 15 minutes server-side; cache auto-flushes when posts are saved or deleted.', 'reloaded' ),
		)
	);

	// (Section "Feature Flags" removed — the only field it had (enable_stats)
	// was redundant: it only hid the Statistics tab, with no effect on data or
	// frontend. Removed — admins who don't want the tab simply don't click it.)

	// ("Privacy" tab discontinued in Wave 11 — LGPD fields migrated to
	// Security & Privacy as sec_seg_lgpd.)

	/*
	 * --- SECURITY & PRIVACY --- (Wave 11: unified tab — LGPD came from the
	 * discontinued Privacy tab; hardening absorbed hide_wp_ver + enable_security_headers
	 * which were mis-fitted under Performance.)
	 */
	rd_panel_register_section( 'sec_seg_lgpd', __( 'Cookie Consent', 'reloaded' ), 'privacy', 'rd_options_security' );
	add_settings_field(
		'enable_lgpd',
		__( 'Cookie Banner', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_lgpd',
		array(
			'id'   => 'enable_lgpd',
			'type' => 'checkbox',
			'desc' => __( 'Enables the cookie consent banner in the site footer for legal compliance.', 'reloaded' ),
		)
	);
	/* translators: the literal "%s" inside <strong>...</strong> is shown AS-IS to the admin as an instruction (it's the placeholder they should type in the cookie banner template, not a sprintf substitution here) — keep the literal "%s" in the translation */
	add_settings_field(
		'lgpd_text',
		__( 'Cookie Banner Text', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_lgpd',
		array(
			'id'   => 'lgpd_text',
			'type' => 'textarea',
			/* translators: the literal "%s" inside <strong>...</strong> is shown AS-IS to the admin as an instruction (it's the placeholder they should type in the cookie banner template, not a sprintf substitution here) — keep the literal "%s" in the translation */
			'desc' => __( 'Customize the cookie banner message. Use <strong>%s</strong> where you want the Privacy Policy link to appear — the URL comes from <em>Settings → Privacy → Privacy Policy Page</em>. Leave empty to use the default translatable text.', 'reloaded' ),
		)
	);

	rd_panel_register_section( 'sec_seg_hardening', __( 'Hardening', 'reloaded' ), 'shield-alt', 'rd_options_security' );
	add_settings_field(
		'disable_xmlrpc',
		__( 'Disable XML-RPC', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_hardening',
		array(
			'id'   => 'disable_xmlrpc',
			'type' => 'checkbox',
			'desc' => __( 'Disables the WordPress XML-RPC endpoint, the legacy API used by old mobile apps and the Jetpack/pingback ecosystem. Common target for brute-force and DDoS amplification attacks. Keep <strong>enabled</strong> unless you actually use a remote app or pingbacks.', 'reloaded' ),
		)
	);
	add_settings_field(
		'hide_wp_ver',
		__( 'Hide WP Version', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_hardening',
		array(
			'id'   => 'hide_wp_ver',
			'type' => 'checkbox',
			'desc' => __( 'Removes the WordPress generator meta tag from the source code. A basic security best practice.', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_security_headers',
		__( 'Security Headers', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_hardening',
		array(
			'id'   => 'enable_security_headers',
			'type' => 'checkbox',
			'desc' => __( 'Sends defensive HTTP headers on the frontend (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) to mitigate clickjacking and MIME sniffing. Does not affect the admin panel.', 'reloaded' ),
		)
	);
	add_settings_field(
		'trusted_proxy_ips',
		__( 'Trusted Proxy IPs', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_hardening',
		array(
			'id'   => 'trusted_proxy_ips',
			'type' => 'textarea',
			'desc' => __( 'IP ranges (CIDR, one per line) of reverse proxies or CDNs in front of your site. When a request arrives from one of these IPs, the theme trusts the <code>CF-Connecting-IP</code> and <code>X-Forwarded-For</code> headers to identify the real visitor — used internally for rate limiting and abuse protection. <strong>Cloudflare ranges are already included by default</strong>, so leave this empty if you use only Cloudflare or no proxy at all. Add custom ranges only for other setups (custom Nginx reverse-proxy, AWS ALB, Sucuri, BunnyCDN, etc). Example:<br><code>203.0.113.0/24<br>198.51.100.0/24<br>2001:db8::/32</code>', 'reloaded' ),
		)
	);

	// Login Protection — custom slug + rate limit.
	rd_panel_register_section( 'sec_seg_login', __( 'Login Protection', 'reloaded' ), 'lock', 'rd_options_security' );

	add_settings_field(
		'enable_login_protection',
		__( 'Enable Login Protection', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_login',
		array(
			'id'   => 'enable_login_protection',
			'type' => 'checkbox',
			'desc' => __( 'Master switch for all Login Protection features (hide URL + rate limit + anti-enumeration). When OFF, the custom slug is preserved in storage but ignored, and the rate limit is disabled — login behaves like a standard WordPress install. Re-enable to restore all protections without losing your saved slug.', 'reloaded' ),
		)
	);

	add_settings_field(
		'login_secret_slug',
		__( 'Hide Login URL (custom slug)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_login',
		array(
			'id'   => 'login_secret_slug',
			'type' => 'text',
			'desc' => sprintf(
				/* translators: %s: current full login URL */
				__( 'Optional custom slug to hide the login page. When set, <code>/wp-login.php</code> returns 404 to anonymous visitors (logged-in users keep access for logout), and login is reachable only via <code>/{slug}</code>. Blocks the ~95%% of bots that only know <code>/wp-login.php</code>. Leave empty to disable. 3–50 chars, lowercase letters/numbers/hyphens. Reserved words (<code>wp-admin</code>, <code>login</code>, <code>feed</code>, etc) are rejected.<br><br><strong>Current login URL:</strong> <code>%s</code><br><br>⚠️ <strong>Save this URL in a secure place.</strong> If you lose it, see the theme documentation for recovery steps.', 'reloaded' ),
				function_exists( 'rd_login_get_full_url' ) ? esc_url( rd_login_get_full_url() ) : esc_url( wp_login_url() )
			),
		)
	);

	add_settings_field(
		'login_rate_limit_max',
		__( 'Max Failed Login Attempts', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_login',
		array(
			'id'   => 'login_rate_limit_max',
			'type' => 'number',
			'desc' => __( 'Maximum failed login attempts per IP within the window below before further attempts are blocked. Default: 5. The client IP is detected reliably and resistant to header spoofing via the Trusted Proxy IPs whitelist above.', 'reloaded' ),
		)
	);

	add_settings_field(
		'login_rate_limit_window',
		__( 'Lockout Window (minutes)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_login',
		array(
			'id'   => 'login_rate_limit_window',
			'type' => 'number',
			'desc' => __( 'Time window for counting failed login attempts. After this duration without new failures, the counter resets and the IP can try again. Default: 15 minutes.', 'reloaded' ),
		)
	);

	add_settings_field(
		'login_hide_user_enumeration',
		__( 'Hide User Enumeration', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_login',
		array(
			'id'   => 'login_hide_user_enumeration',
			'type' => 'checkbox',
			'desc' => __( 'Replaces WordPress\'s default login errors that reveal whether a username exists (<em>"User does not exist"</em> vs <em>"Password is incorrect for user X"</em>) with a generic <em>"Invalid username or password"</em>. Stops attackers from enumerating valid usernames before brute-forcing. <strong>Recommended:</strong> keep enabled. Disable only if you want friendlier error messages for legitimate users who forgot their username — they can still use the "Lost your password?" link.', 'reloaded' ),
		)
	);

	// CSP Report-Only: feature toggle + view of received reports.
	// Two sections in sequence: the first holds the field, the second renders
	// the table via custom callback (must be separated because section callbacks
	// print BEFORE fields).
	rd_panel_register_section( 'sec_seg_csp', __( 'Content Security Policy (Report-Only)', 'reloaded' ), 'editor-code', 'rd_options_security' );
	add_settings_field(
		'enable_csp_report_only',
		__( 'Enable CSP', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_csp',
		array(
			'id'   => 'enable_csp_report_only',
			'type' => 'checkbox',
			'desc' => __( 'Sends the <code>Content-Security-Policy</code> header on the frontend with a policy calculated from your active integrations (GA, Clarity, FB Pixel, Discord, YouTube, etc.). By default uses <strong>Report-Only mode</strong> — does not block anything, only reports violations to the browser console and to an internal endpoint (<code>/wp-json/rd/v1/csp-report</code>). Useful for auditing what your site loads from external sources. Use the <strong>Enforce Mode</strong> toggle below to promote to actual blocking. <strong>Note:</strong> generates a POST request per violation, so disable in heavy traffic if not actively monitoring.', 'reloaded' ),
		)
	);

	add_settings_field(
		'csp_enforce_mode',
		__( '⚠️ Enforce Mode', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_csp',
		array(
			'id'   => 'csp_enforce_mode',
			'type' => 'checkbox',
			'desc' => __( '<strong style="color:#d63638">DANGER ZONE.</strong> Promotes CSP from <em>Report-Only</em> (passive monitoring) to <em>Enforce</em> mode — the browser will <strong>actually block</strong> resources that violate the policy. <strong>Only enable this after 30+ days monitoring reports without unexpected violations</strong> — turning on prematurely will break legitimate functionality (images, embeds, scripts). Requires the toggle above to be ON. Easy rollback: just uncheck this. See <code>docs/pt-BR/10-content-security-policy.md</code> for the full promotion checklist.', 'reloaded' ),
		)
	);

	// Custom Allowed Origins — 3 textareas for the admin to add extra origins
	// when a new integration appears and is not in the code. Each field covers
	// a typical grouping of directives (design decision to avoid requiring the
	// admin to know CSP in depth).
	add_settings_field(
		'csp_custom_scripts',
		__( 'Custom Origins — Scripts & APIs', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_csp',
		array(
			'id'   => 'csp_custom_scripts',
			'type' => 'textarea',
			'desc' => __( 'Adds origins to <code>script-src</code> and <code>connect-src</code>. Use for new trackers, SDKs, JS libraries or any external API your frontend calls. <strong>One per line.</strong> Examples: <code>https://cdn.mixpanel.com</code>, <code>https://api.mixpanel.com</code>, <code>https://*.example.com</code>. Only HTTPS origins accepted; keywords with quotes (<code>\'self\'</code>, <code>\'unsafe-inline\'</code>) and the bare wildcard (<code>*</code>) are rejected for safety.', 'reloaded' ),
		)
	);

	add_settings_field(
		'csp_custom_frames',
		__( 'Custom Origins — Iframes & Embeds', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_csp',
		array(
			'id'   => 'csp_custom_frames',
			'type' => 'textarea',
			'desc' => __( 'Adds origins to <code>frame-src</code>. Use for video players, music embeds, third-party widgets that load in <code>&lt;iframe&gt;</code>. <strong>One per line.</strong> Examples: <code>https://player.vimeo.com</code>, <code>https://open.spotify.com</code>, <code>https://player.twitch.tv</code>. Only HTTPS origins accepted.', 'reloaded' ),
		)
	);

	add_settings_field(
		'csp_custom_styles',
		__( 'Custom Origins — Styles & Fonts', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_csp',
		array(
			'id'   => 'csp_custom_styles',
			'type' => 'textarea',
			'desc' => __( 'Adds origins to <code>style-src</code> and <code>font-src</code>. Use for external font CDNs (Bunny Fonts, Google Fonts mirror) or external stylesheets. <strong>One per line.</strong> Examples: <code>https://fonts.bunny.net</code>, <code>https://fonts.googleapis.com</code>. Only HTTPS origins accepted.', 'reloaded' ),
		)
	);

	// Report Noise Filter — admin-managed host denylist (Layer B of the noise
	// filter in mod-csp.php). Layer A (browser-extension schemes) is automatic in
	// code; this list only covers noise that arrives without an extension source-file.
	add_settings_field(
		'csp_report_denylist',
		__( 'Report Noise Filter — Ignored Hosts', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_security',
		'sec_seg_csp',
		array(
			'id'   => 'csp_report_denylist',
			'type' => 'textarea',
			'desc' => __( 'Hosts whose violations are <strong>dropped before being stored</strong>, to keep the reports below focused on real, actionable issues. Violations injected by browser extensions are already filtered automatically (by their <code>chrome-extension://</code> / <code>moz-extension://</code> scheme); use this list only for noise that slips through without an extension source — e.g. fonts injected by the Adobe Acrobat extension (<code>use.typekit.net</code>). <strong>One host per line, no scheme.</strong> Example: <code>use.typekit.net</code>.', 'reloaded' ),
		)
	);

	// Empty title — callback rd_csp_render_reports_panel emits its own section_header with icon.
	add_settings_section( 'sec_seg_csp_reports', '', 'rd_csp_render_reports_panel', 'rd_options_security' );

	/*
	 * --- INTEGRATIONS --- (Wave 11: sub-divided into 3 contexts. Site verifications
	 * moved to SEO where they fit better.)
	 */
	rd_panel_register_section( 'sec_int_analytics', __( 'Analytics', 'reloaded' ), 'chart-line', 'rd_options_integrations' );
	add_settings_field(
		'ga_id',
		__( 'Google Analytics ID', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_analytics',
		array(
			'id'          => 'ga_id',
			'type'        => 'text',
			'placeholder' => 'G-XXXXXXX',
			'desc'        => __( 'Insert only the tracking code (Tag ID). Leave empty to disable.', 'reloaded' ),
		)
	);
	add_settings_field(
		'clarity_id',
		__( 'Microsoft Clarity ID', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_analytics',
		array(
			'id'          => 'clarity_id',
			'type'        => 'text',
			'placeholder' => 'abc123xyz',
			'desc'        => __( 'Project ID from <a href="https://clarity.microsoft.com" target="_blank" rel="noopener">clarity.microsoft.com</a> → your project → Settings → Setup. Free heatmaps + session recordings. Leave empty to disable.', 'reloaded' ),
		)
	);
	add_settings_field(
		'plausible_domain',
		__( 'Plausible Domain', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_analytics',
		array(
			'id'          => 'plausible_domain',
			'type'        => 'text',
			'placeholder' => 'example.com',
			'desc'        => __( 'Domain as registered in <a href="https://plausible.io" target="_blank" rel="noopener">plausible.io</a> (e.g. example.com, without https://). Loads the Plausible cloud script. Privacy-friendly alternative to Google Analytics. Leave empty to disable.', 'reloaded' ),
		)
	);
	add_settings_field(
		'umami_website_id',
		__( 'Umami Website ID', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_analytics',
		array(
			'id'          => 'umami_website_id',
			'type'        => 'text',
			'placeholder' => '00000000-0000-0000-0000-000000000000',
			'desc'        => __( 'Website ID (UUID) from your Umami dashboard → Settings → Websites. Requires the script URL below to render. Leave both fields empty to disable.', 'reloaded' ),
		)
	);
	add_settings_field(
		'umami_script_url',
		__( 'Umami Script URL', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_analytics',
		array(
			'id'          => 'umami_script_url',
			'type'        => 'text',
			'placeholder' => 'https://analytics.example.com/script.js',
			'desc'        => __( 'Full URL to your self-hosted Umami script file (typically <code>/script.js</code> on your Umami instance). Used together with the Website ID above.', 'reloaded' ),
		)
	);

	rd_panel_register_section( 'sec_int_marketing', __( 'Marketing & Conversion Pixels', 'reloaded' ), 'megaphone', 'rd_options_integrations' );
	add_settings_field(
		'facebook_pixel_id',
		__( 'Facebook Pixel ID', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_marketing',
		array(
			'id'          => 'facebook_pixel_id',
			'type'        => 'text',
			'placeholder' => '1234567890123456',
			'desc'        => __( 'Numeric Pixel ID from <a href="https://business.facebook.com/events_manager" target="_blank" rel="noopener">Meta Events Manager</a> → Data Sources → Settings. Enables retargeting and conversion tracking. Leave empty to disable.', 'reloaded' ),
		)
	);
	add_settings_field(
		'tiktok_pixel_id',
		__( 'TikTok Pixel ID', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_marketing',
		array(
			'id'          => 'tiktok_pixel_id',
			'type'        => 'text',
			'placeholder' => 'CXXXXXXXXXXXXXXXX',
			'desc'        => __( 'Pixel ID from <a href="https://ads.tiktok.com" target="_blank" rel="noopener">TikTok Ads Manager</a> → Assets → Events → Web Events. Leave empty to disable.', 'reloaded' ),
		)
	);

	/*
	 * --- PERFORMANCE --- (Wave 11: split into Loading + Bloat. Moved to
	 * other tabs: markdown/prism → General; hide_wp_ver/security_headers →
	 * Security; image fields → Images & Media.)
	 */
	rd_panel_register_section( 'sec_perf_loading', __( 'Loading Optimization', 'reloaded' ), 'performance', 'rd_options_performance' );
	add_settings_field(
		'preload_critical_fonts',
		__( 'Preload Critical Fonts', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_loading',
		array(
			'id'   => 'preload_critical_fonts',
			'type' => 'checkbox',
			'desc' => __( 'Tells the browser to start downloading the two most-used fonts (Inter Regular + Poppins Bold) in parallel with the stylesheet, instead of waiting until the CSS is parsed. Reduces FOIT/FOUT (the blank-text flash) and improves LCP/FCP scores on speed tests. The other font variants (italic, light, mono) are NOT preloaded — they are only used in specific contexts and preloading all 16 files would hurt more than help.', 'reloaded' ),
		)
	);
	add_settings_field(
		'inline_critical_css',
		__( 'Inline Critical CSS', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_loading',
		array(
			'id'   => 'inline_critical_css',
			'type' => 'checkbox',
			'desc' => __( 'Eliminates render-blocking by inlining the above-the-fold CSS in the <code>&lt;head&gt;</code> and loading the full stylesheet asynchronously. Lighthouse estimates ~1 second LCP improvement on mobile. <strong>Requires pre-generated critical CSS files</strong> in <code>assets/css/critical-{template}.css</code> — run <code>npm run critical:all</code> from the theme root after major SCSS changes to above-the-fold rules. If the critical file for the current template is missing, the theme gracefully degrades to synchronous loading. Off by default — opt-in only after you confirm the critical files are generated.', 'reloaded' ),
		)
	);
	add_settings_field(
		'facade_youtube',
		__( 'YouTube Facade', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_loading',
		array(
			'id'   => 'facade_youtube',
			'type' => 'checkbox',
			'desc' => __( 'Replaces YouTube embeds with a lightweight thumbnail image, loading the player iframe only when the user clicks. Drastically reduces initial page weight on posts with videos.', 'reloaded' ),
		)
	);
	rd_panel_register_section( 'sec_perf_bloat', __( 'Bloat Reducers', 'reloaded' ), 'trash', 'rd_options_performance' );
	add_settings_field(
		'disable_emojis',
		__( 'Disable Native Emojis', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_bloat',
		array(
			'id'   => 'disable_emojis',
			'type' => 'checkbox',
			'desc' => __( 'Removes the WP emojis script. Modern browsers already render emojis natively, enable this to save requests.', 'reloaded' ),
		)
	);
	add_settings_field(
		'disable_gutenberg_css',
		__( 'Disable WP CSS (Bloat)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_bloat',
		array(
			'id'   => 'disable_gutenberg_css',
			'type' => 'checkbox',
			'desc' => __( 'Removes global and block CSS from Gutenberg. Makes the site lighter, ideal for those using Markdown.', 'reloaded' ),
		)
	);
	add_settings_field(
		'post_revisions_limit',
		__( 'Post Revisions Limit', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_bloat',
		array(
			'id'      => 'post_revisions_limit',
			'type'    => 'number',
			'min'     => 0,
			'max'     => 50,
			'default' => 5,
			'desc'    => __( 'Maximum number of revisions WordPress keeps per post. WP default is unlimited — over years that bloats the database. Recommended: 5 (enough to recover from accidental edits without storing dozens of stale copies). Set to 0 to disable revisions entirely.', 'reloaded' ),
		)
	);
	add_settings_field(
		'optimize_heartbeat',
		__( 'Optimize Heartbeat', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_performance',
		'sec_perf_bloat',
		array(
			'id'   => 'optimize_heartbeat',
			'type' => 'checkbox',
			'desc' => __( 'Reduces WordPress Heartbeat API frequency: bumps admin pages from 60 to 120 seconds (WP max, halves admin-ajax.php requests), keeps the default on post editor screens (preserves autosave and edit locking), and defensively deregisters Heartbeat on the frontend (in case any plugin enqueues it there). Useful on shared hosting or to reduce server load.', 'reloaded' ),
		)
	);

	/*
	 * --- IMAGES & MEDIA --- (new in Wave 11)
	 * Consolidates 4 previously scattered fields: 2 from General (image_resizing, jpeg_quality)
	 * + 2 from Performance (enable_next_gen_images, image_format_mode). Performance's
	 * custom renderer (rd_img_render_panel_section) also migrated here.
	 */
	rd_panel_register_section( 'sec_media_upload', __( 'Upload & Quality', 'reloaded' ), 'upload', 'rd_options_media' );
	add_settings_field(
		'image_resizing',
		__( 'Resize Images', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_media',
		'sec_media_upload',
		array(
			'id'   => 'image_resizing',
			'type' => 'checkbox',
			'desc' => __( 'Enables exact cropping (Hard Crop) of uploaded images to ensure banners and cards are always aligned.', 'reloaded' ),
		)
	);
	add_settings_field(
		'jpeg_quality',
		__( 'Image Quality (%)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_media',
		'sec_media_upload',
		array(
			'id'      => 'jpeg_quality',
			'type'    => 'number',
			'default' => '80',
			'desc'    => __( 'Quality used when WordPress re-encodes images on upload (JPEG, WebP, AVIF). Range 1-100. WP core default is 82; the theme defaults to 80 for a better size/quality tradeoff. <strong>Tip on file formats:</strong> upload featured images as <strong>WebP</strong> or <strong>JPEG</strong> for photos; avoid PNG unless you need transparency (PNG files are typically 3-5x larger than the equivalent WebP).', 'reloaded' ),
		)
	);
	add_settings_field(
		'enable_next_gen_images',
		__( 'Enable WebP/AVIF Delivery', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_media',
		'sec_media_upload',
		array(
			'id'   => 'enable_next_gen_images',
			'type' => 'checkbox',
			'desc' => __( 'Automatically generates WebP/AVIF versions of every uploaded JPEG/PNG (all sizes) and wraps <img> tags in <picture> with <source> elements. Browsers that support next-gen formats get the smaller file (AVIF ~50%, WebP ~30% smaller than JPEG), older browsers fall back transparently to the original. Original files are always preserved.', 'reloaded' ),
		)
	);
	add_settings_field(
		'image_format_mode',
		__( 'Format Mode', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_media',
		'sec_media_upload',
		array(
			'id'      => 'image_format_mode',
			'type'    => 'select',
			'options' => array(
				'avif' => __( 'AVIF only', 'reloaded' ),
				'webp' => __( 'WebP only', 'reloaded' ),
				'both' => __( 'AVIF + WebP', 'reloaded' ),
			),
			'desc'    => __( 'Choose which next-gen format(s) to generate. <strong>AVIF only</strong> (default): ~50% smaller than JPEG, ~80% browser support (Chrome 85+, Firefox 86+, Safari 16+). Best compression but reduces fallback chain. <strong>WebP only</strong>: ~30% smaller than JPEG, ~95% browser support. <strong>AVIF + WebP</strong>: maximum compatibility — browser picks AVIF if supported, falls back to WebP for older browsers, falls back to original for very old browsers. Trade-off: each image generates 2 extra files on disk (~2x extra space).', 'reloaded' ),
		)
	);

	add_settings_section( 'sec_media_regenerate', __( 'Regenerate Library', 'reloaded' ), 'rd_img_render_panel_section', 'rd_options_media' );

	// --- SOCIAL NETWORKS (moved into Integrations) ---
	rd_panel_register_section( 'sec_int_social', __( 'Social Networks', 'reloaded' ), 'share', 'rd_options_integrations' );
	add_settings_field(
		'social_discord',
		__( 'Discord', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_discord',
			'type'        => 'text',
			'placeholder' => 'https://discord.gg/...',
			'desc'        => __( 'Link to your server or permanent invite to the community.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_telegram',
		__( 'Telegram', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_telegram',
			'type'        => 'text',
			'placeholder' => 'https://t.me/...',
			'desc'        => __( 'Link to your official channel or group.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_youtube',
		__( 'YouTube', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_youtube',
			'type'        => 'text',
			'placeholder' => 'https://youtube.com/@...',
			'desc'        => __( 'URL to your channel to display videos and streams.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_instagram',
		__( 'Instagram', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_instagram',
			'type'        => 'text',
			'placeholder' => 'https://instagram.com/...',
			'desc'        => __( 'Link to your profile for photos and visual updates.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_steam',
		__( 'Steam', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_steam',
			'type'        => 'text',
			'placeholder' => 'https://steamcommunity.com/groups/...',
			'desc'        => __( 'Link to your Steam group or curator profile.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_twitter',
		__( 'Twitter (X)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_twitter',
			'type'        => 'text',
			'placeholder' => 'https://x.com/...',
			'desc'        => __( 'Link to your official profile on X.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_facebook',
		__( 'Facebook', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_facebook',
			'type'        => 'text',
			'placeholder' => 'https://facebook.com/...',
			'desc'        => __( 'Link to your official page or community.', 'reloaded' ),
		)
	);
	add_settings_field(
		'social_whatsapp',
		__( 'WhatsApp', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_integrations',
		'sec_int_social',
		array(
			'id'          => 'social_whatsapp',
			'type'        => 'text',
			'placeholder' => 'https://wa.me/5511999999999',
			'desc'        => __( 'Direct link to your WhatsApp number or group (use international format).', 'reloaded' ),
		)
	);

	/*
	 * --- SEO --- (Wave 11: split into 4 sections + receives the verifications
	 * from Integrations where they were poorly placed.)
	 */
	rd_panel_register_section( 'sec_seo_verify', __( 'Site Verification', 'reloaded' ), 'yes-alt', 'rd_options_seo' );
	add_settings_field(
		'google_site_verification',
		__( 'Google Search Console Verification', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_verify',
		array(
			'id'          => 'google_site_verification',
			'type'        => 'text',
			'placeholder' => 'abc123XYZ_DEF456...',
			'desc'        => __( 'Verification code from Search Console → Settings → Ownership verification → HTML tag method. Paste only the <strong>content value</strong> (the part between quotes), not the full meta tag. Leave empty to skip.', 'reloaded' ),
		)
	);
	add_settings_field(
		'bing_site_verification',
		__( 'Bing Webmaster Verification', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_verify',
		array(
			'id'          => 'bing_site_verification',
			'type'        => 'text',
			'placeholder' => 'A1B2C3D4E5F6...',
			'desc'        => __( 'Verification code from Bing Webmaster Tools → Site → Ownership verification → HTML tag method. Paste only the <strong>content value</strong>, not the full meta tag. Leave empty to skip.', 'reloaded' ),
		)
	);
	add_settings_field(
		'custom_verification_meta',
		__( 'Other Verification Meta Tags', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_verify',
		array(
			'id'          => 'custom_verification_meta',
			'type'        => 'textarea',
			'placeholder' => "<meta name=\"p:domain_verify\" content=\"pinterest123abc\">\n<meta name=\"yandex-verification\" content=\"yandex456def\">",
			'desc'        => __( 'Paste full <code>&lt;meta&gt;</code> tags from other verification services (one per line). Useful for: Pinterest (<code>p:domain_verify</code>), Facebook Domain (<code>facebook-domain-verification</code>), Yandex, Naver, Norton SafeWeb, Apple News, etc. Only <code>&lt;meta&gt;</code> tags with <code>name</code> + <code>content</code> attributes are accepted — anything else is stripped at save.', 'reloaded' ),
		)
	);

	rd_panel_register_section( 'sec_seo_og', __( 'Open Graph & Sharing', 'reloaded' ), 'share-alt2', 'rd_options_seo' );
	add_settings_field(
		'enable_open_graph',
		__( 'Open Graph Meta Tags', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_og',
		array(
			'id'   => 'enable_open_graph',
			'type' => 'checkbox',
			'desc' => __( 'Enables the OG Tags system. Generates the necessary tags for social networks (Facebook, Discord, WhatsApp).', 'reloaded' ),
		)
	);
	add_settings_field(
		'og_fallback_image',
		__( 'Fallback Image (Open Graph)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_og',
		array(
			'id'   => 'og_fallback_image',
			'type' => 'media',
			'desc' => __( 'Select an image from your library to be the default on social networks.', 'reloaded' ),
		)
	);

	rd_panel_register_section( 'sec_seo_sitemap', __( 'Sitemap', 'reloaded' ), 'networking', 'rd_options_seo' );
	add_settings_field(
		'enable_sitemap',
		__( 'Enable WordPress Sitemap', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_sitemap',
		array(
			'id'   => 'enable_sitemap',
			'type' => 'checkbox',
			'desc' => __( 'Generates <code>/wp-sitemap.xml</code> with sub-sitemaps for posts, pages, taxonomies and authors (WP 5.5+ native). Disable only if you use a third-party SEO plugin (Yoast, RankMath) that provides its own sitemap.', 'reloaded' ),
		)
	);
	add_settings_field(
		'sitemap_include_authors',
		__( 'Include Authors in Sitemap', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_sitemap',
		array(
			'id'   => 'sitemap_include_authors',
			'type' => 'checkbox',
			'desc' => __( 'Adds <code>/wp-sitemap-users-1.xml</code> with author archive URLs. Useful for multi-author blogs. <strong>Leave off for solo blogs</strong> — the author archive duplicates the home page and creates near-duplicate content for crawlers.', 'reloaded' ),
		)
	);
	add_settings_field(
		'sitemap_include_cpt',
		__( 'Include Custom Post Types in Sitemap', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_sitemap',
		array(
			'id'   => 'sitemap_include_cpt',
			'type' => 'checkbox',
			'desc' => __( 'Includes any public Custom Post Types registered by the theme or plugins (alongside the standard <code>post</code> and <code>page</code>). Turn off if you have CPTs you don\'t want indexed (e.g. portfolio items used only as widgets).', 'reloaded' ),
		)
	);

	// Section: IndexNow — push indexing notifications (Bing/Yandex & friends).
	rd_panel_register_section( 'sec_seo_indexnow', __( 'IndexNow', 'reloaded' ), 'controls-forward', 'rd_options_seo' );
	add_settings_field(
		'enable_indexnow',
		__( 'Enable IndexNow', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_indexnow',
		array(
			'id'   => 'enable_indexnow',
			'type' => 'checkbox',
			'desc' => __( 'Notifies Bing, Yandex and other participating engines the moment a post or page is published, updated or unpublished — push indexing in minutes instead of waiting for the crawler. One ping is shared between all participating engines. <strong>Google does not join IndexNow</strong>, so keep the sitemap on. The key file is served automatically at <code>/{key}.txt</code> — nothing to upload.', 'reloaded' ),
		)
	);
	add_settings_field(
		'indexnow_key',
		__( 'IndexNow Key', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_indexnow',
		array(
			'id'          => 'indexnow_key',
			'type'        => 'text',
			'placeholder' => __( 'auto-generated on save when empty', 'reloaded' ),
			'desc'        => rd_indexnow_key_field_desc(),
		)
	);

	rd_panel_register_section( 'sec_seo_robots', __( 'robots.txt', 'reloaded' ), 'media-text', 'rd_options_seo' );
	add_settings_field(
		'custom_robots_txt',
		__( 'Custom robots.txt', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_seo',
		'sec_seo_robots',
		array(
			'id'   => 'custom_robots_txt',
			'type' => 'textarea',
			'desc' => sprintf(
				/* translators: %s = the site's sitemap URL like https://site.com/wp-sitemap.xml — appears inside a <pre> code block showing the WP default robots.txt */
				__( 'Override the WordPress default <code>/robots.txt</code> entirely. Leave empty to keep the WP-generated content. When filled, this <strong>replaces</strong> the output completely — you must include all directives you want crawlers to see.<br><br><strong>WP defaults (what you are overriding):</strong><pre class="rd-robots-defaults">User-agent: *%1$sDisallow: /wp-admin/%1$sAllow: /wp-admin/admin-ajax.php%1$s%1$sSitemap: %2$s</pre>Copy any defaults you want to keep into your custom output. Common additions: block LLM scrapers (<code>GPTBot</code>, <code>ClaudeBot</code>, <code>CCBot</code>), block search/pagination URLs, declare multiple sitemaps.', 'reloaded' ),
				"\n",
				esc_html( home_url( '/wp-sitemap.xml' ) )
			),
		)
	);

	/*
	 * --- MONETIZATION — ADS sub-sections ---
	 * Third-party ads (AdSense, etc): a global script that goes in <head>,
	 * plus banner zones rendered in fixed positions on the site.
	 */
	rd_panel_register_section( 'sec_ads_global', __( 'Ads — Global Script', 'reloaded' ), 'editor-code', 'rd_options_monetization' );
	add_settings_field(
		'ad_global',
		__( 'Global Ad Script', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_monetization',
		'sec_ads_global',
		array(
			'id'   => 'ad_global',
			'type' => 'textarea',
			'desc' => __( 'Paste here the global &lt;head&gt; tag (e.g. AdSense Auto Ads).', 'reloaded' ),
		)
	);
	add_settings_field(
		'ads_txt_content',
		__( 'ads.txt', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_monetization',
		'sec_ads_global',
		array(
			'id'          => 'ads_txt_content',
			'type'        => 'textarea',
			'placeholder' => 'google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0',
			'desc'        => __( 'Content served <strong>virtually</strong> at <code>/ads.txt</code> — no file to upload via SFTP, survives migrations and is included in the settings backup. Paste the lines your ad networks give you (AdSense shows yours under <em>Sites → ads.txt</em>), one record per line. Empty = no ads.txt is served. <strong>Note:</strong> a physical ads.txt file in the web root takes precedence — delete it to use this field.', 'reloaded' ),
		)
	);

	rd_panel_register_section( 'sec_ads_zones', __( 'Ads — Banners by Position', 'reloaded' ), 'format-image', 'rd_options_monetization' );
	add_settings_field(
		'ad_topo_desktop',
		__( 'Top Banner - Desktop (728x90)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_monetization',
		'sec_ads_zones',
		array(
			'id'   => 'ad_topo_desktop',
			'type' => 'textarea',
			'desc' => __( 'Rendered in the header only on large screens (PCs and Laptops).', 'reloaded' ),
		)
	);
	add_settings_field(
		'ad_topo_mobile',
		__( 'Top Banner - Mobile (320x100)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_monetization',
		'sec_ads_zones',
		array(
			'id'   => 'ad_topo_mobile',
			'type' => 'textarea',
			'desc' => __( 'Rendered in the header only on smartphone screens.', 'reloaded' ),
		)
	);
	add_settings_field(
		'ad_sidebar_sticky',
		__( 'Sidebar Banner - Sticky (300x600)', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_monetization',
		'sec_ads_zones',
		array(
			'id'   => 'ad_sidebar_sticky',
			'type' => 'textarea',
			'desc' => __( 'Rendered at the bottom of the sidebar. Follows the screen scroll.', 'reloaded' ),
		)
	);

	/*
	 * --- MAINTENANCE ---
	 * Backup & Restore was promoted to its own tab in Wave 11. What remains here
	 * is just Access Control (maintenance mode + dev password).
	 */
	rd_panel_register_section( 'sec_manut_access', __( 'Access Control', 'reloaded' ), 'admin-tools', 'rd_options_maintenance' );
	add_settings_field(
		'maintenance_mode',
		__( 'Enable Maintenance', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_maintenance',
		'sec_manut_access',
		array(
			'id'   => 'maintenance_mode',
			'type' => 'checkbox',
			'desc' => __( 'Blocks access from regular visitors and displays a "We\'ll be right back" screen (Returns HTTP 503 to Google).', 'reloaded' ),
		)
	);
	add_settings_field(
		'maintenance_pass',
		__( 'Dev Password', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_maintenance',
		'sec_manut_access',
		array(
			'id'   => 'maintenance_pass',
			'type' => 'password',
			'desc' => __( 'Password for developers to bypass maintenance. Access: <strong>yourdomain.com/?rd-dev-login</strong> and enter the password in the form (do not pass it through the URL). The password is stored as a cryptographic hash — after saving, this field will appear empty (this is normal and secure).', 'reloaded' ),
		)
	);
	add_settings_field(
		'maintenance_text',
		__( 'Maintenance Text', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_maintenance',
		'sec_manut_access',
		array(
			'id'   => 'maintenance_text',
			'type' => 'textarea',
			'desc' => __( 'Customize the message visitors will see on the block screen. Accepts basic HTML (e.g. &lt;strong&gt;, &lt;br&gt;).', 'reloaded' ),
		)
	);

	/*
	 * --- STATISTICS ---
	 * The "Tracking" section consolidates: the views counter configuration (moved
	 * from the General tab in Wave 11) + the rankings item limit. Dashboard stays
	 * as a separate section rendered by a custom callback.
	 */
	rd_panel_register_section( 'sec_stats_tracking', __( 'Tracking Settings', 'reloaded' ), 'admin-settings', 'rd_options_statistics' );
	add_settings_field(
		'enable_views_tracking',
		__( 'Views Counter', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_statistics',
		'sec_stats_tracking',
		array(
			'id'   => 'enable_views_tracking',
			'type' => 'checkbox',
			'desc' => __( 'Enables the post views counting system. A single IP counts only once every 30 minutes. Known bots are automatically ignored.', 'reloaded' ),
		)
	);
	add_settings_field(
		'views_number_format',
		__( 'Views Display Format', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_statistics',
		'sec_stats_tracking',
		array(
			'id'      => 'views_number_format',
			'type'    => 'select',
			'options' => array(
				'full'    => __( 'Full (1.234)', 'reloaded' ),
				'compact' => __( 'Compact (1.2k / 1.2M)', 'reloaded' ),
			),
			'desc'    => __( 'How view counts are displayed on the frontend (post cards and single posts). The admin panel always shows exact numbers.', 'reloaded' ),
		)
	);
	add_settings_field(
		'stats_top_limit',
		__( 'Items per Ranking', 'reloaded' ),
		'rd_master_field_cb',
		'rd_options_statistics',
		'sec_stats_tracking',
		array(
			'id'      => 'stats_top_limit',
			'type'    => 'select',
			'options' => array(
				'5'  => '5',
				'10' => '10',
				'15' => '15',
				'20' => '20',
				'25' => '25',
				'30' => '30',
			),
			'desc'    => __( 'Number of posts shown in the Top Views and Top Comments rankings. Default 5 (keeps the dashboard compact).', 'reloaded' ),
		)
	);

	// Empty title + custom callback: emits the theme-styled header (icon + hash
	// anchor for the Dashboard gear deep link) instead of WP's plain <h2>.
	// Renamed from "Dashboard" to "Statistics" — inside the Statistics tab,
	// "Dashboard" (pt: "Painel") read like a stray panel reference.
	add_settings_section(
		'sec_stats_dashboard',
		'',
		static function () {
			rd_panel_section_header(
				array(
					'id'    => 'sec_stats_dashboard',
					'icon'  => 'chart-bar',
					'title' => __( 'Statistics', 'reloaded' ),
				)
			);
			rd_stats_render_dashboard();
		},
		'rd_options_statistics'
	);

	/*
	 * --- BACKUP & RESTORE --- (Wave 11)
	 * Promoted from a sub-section of Maintenance to its own tab. The custom
	 * renderer is intact — only the page where it is registered changed.
	 */
	// Empty title — callback rd_backup_render_panel emits its own section_header with icon.
	add_settings_section( 'sec_backup', '', 'rd_backup_render_panel', 'rd_options_backup' );
}
add_action( 'admin_init', 'rd_settings_init' );

/*******************************************************************************
 * Field rendering functions (Reusable)
 *******************************************************************************/
function rd_master_field_cb( array $args ) {
	// 1. Pulls the options from the database only once.
	$opt = get_option( 'rd_settings' );

	// 2. Sets the current value or an empty fallback.
	$val = isset( $opt[ $args['id'] ] ) ? $opt[ $args['id'] ] : '';

	// 3. Sets the field type (if not provided, defaults to 'text').
	$type = isset( $args['type'] ) ? $args['type'] : 'text';

	// 4. Builds the 'name' attribute dynamically.
	$name = 'rd_settings[' . esc_attr( $args['id'] ) . ']';

	// 5. The Switch: renders the correct HTML based on the requested 'type'.
	// $name, $autocomplete, $placeholder and $display are PRE-BUILT HTML fragments
	// where dynamic values already passed through esc_attr() above. PHPCS cannot
	// track escaping through prior concatenation, so we suppress the rule inside
	// the switch (each echo is safe by construction of the variable).
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	switch ( $type ) {

		case 'media':
			echo '<div class="rd-media-container">';
			// The hidden input that actually stores the URL in the database.
			echo '<input type="hidden" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '">';

			// The preview div where the image thumbnail will appear.
			echo '<div id="' . esc_attr( $args['id'] ) . '_preview" class="rd-media-preview">';
			if ( $val ) {
				echo '<img src="' . esc_url( $val ) . '" alt="">';
			}
			echo '</div>';

			// Control buttons — inline-flex wrapper so they sit side-by-side
			// below the preview (parent container is flex column).
			echo '<div class="rd-media-actions">';
			echo '<button type="button" class="button rd-upload-button" data-input-id="' . esc_attr( $args['id'] ) . '">' . esc_html__( 'Select Image', 'reloaded' ) . '</button>';

			$display = $val ? '' : 'display:none;';
			echo '<button type="button" class="button rd-remove-button" data-input-id="' . esc_attr( $args['id'] ) . '" style="' . $display . '">' . esc_html__( 'Remove', 'reloaded' ) . '</button>';
			echo '</div>';
			echo '</div>';
			break;

		case 'checkbox':
			$val = isset( $opt[ $args['id'] ] ) ? $opt[ $args['id'] ] : 0;

			// Fallback hidden: ensures an UNCHECKED checkbox sends an explicit '0'
			// instead of omitting the key from POST. Without this, unchecking
			// does not reach the sanitizer.
			echo '<input type="hidden" name="' . $name . '" value="0">';

			// Wraps everything in a <label> tag so the text stays on the same line and is clickable.
			echo '<label for="' . esc_attr( $args['id'] ) . '">';
			echo '<input type="checkbox" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( 1, $val, false ) . '>';

			// If there's a description, print it inside the label, right after the checkbox.
			if ( isset( $args['desc'] ) ) {
				echo ' ' . wp_kses_post( $args['desc'] );

				// The trick: we drop the description from the variable so the
				// code at the end of the function does not print it twice.
				unset( $args['desc'] );
			}
			echo '</label>';
			break;

		case 'textarea':
			echo '<textarea name="' . $name . '" rows="5" class="large-text">' . esc_textarea( $val ) . '</textarea>';
			break;

		case 'number':
			$min = isset( $args['min'] ) ? $args['min'] : 1;
			$max = isset( $args['max'] ) ? $args['max'] : 100;
			// IMPORTANT: strict comparison with '' instead of `empty()` —
			// `empty(0)` returns true and would convert a legitimate zero
			// (e.g. "0 revisions") into a fallback to the default, corrupting the save.
			$val = ( $val === '' || $val === null ) && isset( $args['default'] ) ? $args['default'] : $val;

			// Renders the input with ID and the native WP class to keep the standard.
			echo '<input type="number" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" class="small-text">';

			// If there's a description, print it right after the field inside a label.
			if ( isset( $args['desc'] ) ) {
				echo ' <label for="' . esc_attr( $args['id'] ) . '">' . wp_kses_post( $args['desc'] ) . '</label>';

				// Drops the description so it isn't duplicated at the end of the function.
				unset( $args['desc'] );
			}
			break;

		case 'select':
			echo '<select name="' . $name . '" id="' . esc_attr( $args['id'] ) . '">';
			if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
				foreach ( $args['options'] as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( $val, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
			}
			echo '</select>';

			// Same pattern as the number type: inline description after the select
			// (selects are compact, putting the description below wastes space).
			if ( isset( $args['desc'] ) ) {
				echo ' <label for="' . esc_attr( $args['id'] ) . '">' . wp_kses_post( $args['desc'] ) . '</label>';
				unset( $args['desc'] ); // avoid duplicating in the generic block below
			}
			break;

		case 'password':
			// Same principle as the text type: autocomplete="new-password" as default
			// (standard for new/internal passwords that should NOT be autofilled
			// with browser-saved credentials). Can override if needed.
			$autocomplete_val = isset( $args['autocomplete'] ) ? $args['autocomplete'] : 'new-password';
			$autocomplete     = "autocomplete='" . esc_attr( $autocomplete_val ) . "'";
			echo '<input type="password" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '" class="regular-text" ' . $autocomplete . '>';
			break;

		case 'text':
		default:
			$placeholder = isset( $args['placeholder'] ) ? "placeholder='" . esc_attr( $args['placeholder'] ) . "'" : '';
			// The admin panel is not a sign-up/login form — autocomplete="off" as
			// default prevents Chrome from autofilling fields whose `name` contains
			// "id"/"url" (browser heuristic mistakes them for saved usernames/URLs).
			// Override via 'autocomplete' => 'on' on add_settings_field if any field
			// ever needs the default behaviour.
			$autocomplete_val = isset( $args['autocomplete'] ) ? $args['autocomplete'] : 'off';
			$autocomplete     = "autocomplete='" . esc_attr( $autocomplete_val ) . "'";
			echo '<input type="text" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '" class="regular-text" ' . $placeholder . ' ' . $autocomplete . '>';
			break;
	}
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

	// The description is rendered cleanly here for every type.
	if ( isset( $args['desc'] ) ) {
		echo '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
	}
}

/*******************************************************************************
 * DATA SANITIZATION (Security)
 *******************************************************************************/
function rd_options_sanitize( array $input ) {
	$new_input = array();
	foreach ( $input as $key => $value ) {

		if ( strpos( $key, 'ad_' ) === 0 ) {
			// 1. Ad zones: allows raw HTML and scripts (AdSense, etc).
			$new_input[ $key ] = $value;
		} elseif ( in_array( $key, array( 'lgpd_text', 'footer_subline', 'maintenance_text' ), true ) ) {
			// 2. Fields that allow safe basic HTML (links, bold, emphasis) — but block scripts.
			$new_input[ $key ] = wp_kses_post( $value );
		} elseif ( in_array( $key, array( 'custom_robots_txt', 'trusted_proxy_ips', 'csp_custom_scripts', 'csp_custom_frames', 'csp_custom_styles', 'csp_report_denylist', 'ads_txt_content' ), true ) ) {
			// 3. Plain-text textarea fields — strip tags but PRESERVE line breaks.
			// sanitize_text_field() would collapse all lines into a single one.
			$new_input[ $key ] = sanitize_textarea_field( $value );
		} elseif ( 'custom_verification_meta' === $key ) {
			/*
			 * 3c. Custom verification meta tags — 2-step pipeline:
			 *
			 *   Step 1: wp_kses() removes disallowed TAGS (keeps only <meta>
			 *   with name/content/property/http-equiv/charset attributes). But
			 *   wp_kses default PRESERVES the INNER text of removed tags — if
			 *   admin pastes <script>alert('xss')</script>, "alert('xss')" is
			 *   left as loose text. No security risk (text in <head> does not
			 *   execute), but pollutes the output.
			 *
			 *   Step 2: regex extracts ONLY <meta> tags from the output and
			 *   discards orphan text. Final result: only valid meta tags, one
			 *   per line.
			 */
			$allowed_html = array(
				'meta' => array(
					'name'       => true,
					'content'    => true,
					'property'   => true,
					'http-equiv' => true,
					'charset'    => true,
				),
			);
			$kses_output  = wp_kses( (string) $value, $allowed_html );
			preg_match_all( '/<meta\b[^>]*\/?>/i', $kses_output, $matches );
			$new_input[ $key ] = isset( $matches[0] ) ? implode( "\n", $matches[0] ) : '';
		} elseif ( 'login_secret_slug' === $key ) {
			// 3b. Login slug: uses the dedicated sanitizer from mod-login-protection
			// (a-z 0-9 hyphen, 3-50 chars, list of reserved words). Invalid = ''.
			$new_input[ $key ] = function_exists( 'rd_login_sanitize_slug' )
				? rd_login_sanitize_slug( $value )
				: '';
		} elseif ( $value === '0' || $value === '1' || $value === 0 || $value === 1 ) {
			// 4. Checkboxes: arrive as '0'/'1' (string, from HTML form via hidden fallback)
			// OR 0/1 (int, coming from JSON in mod-backup after json_decode).
			// Coerced to int to standardize storage and enable strict comparisons (=== 1).
			$new_input[ $key ] = (int) $value;
		} else {
			// 5. Other fields: strict cleanup, destroys any HTML and scripts.
			$new_input[ $key ] = sanitize_text_field( $value );
		}
	}
	return $new_input;
}
