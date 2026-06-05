<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Theme Core - Structural and Native Functions (Hardcoded)
 */

/*******************************************************************************
 * ReloadeD theme functions and definitions                      - (Hardcoded) *
 */
if ( ! function_exists( 'rd_setup' ) ) :
	function rd_setup() {
		// Loads the theme text domain. WP looks for /languages/{locale}.mo
		// (convention for files INSIDE the theme folder — no text-domain
		// prefix). If the .mo is missing, WP falls back to the source string
		// (en-US).
		// Reference: https://developer.wordpress.org/themes/classic-themes/functionality/internationalization/
		load_theme_textdomain( 'reloaded', get_template_directory() . '/languages' );

		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) ); // Adds HTML5 support.
		add_theme_support( 'title-tag' ); // Adds support for dynamic titles (managed by WP).
		add_theme_support( 'post-thumbnails' ); // Enables Featured Images (essential for portals).
		add_theme_support( 'responsive-embeds' ); // Keeps the correct aspect ratio of videos inserted via the "Video" or "YouTube" block.
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 100,
				'width'       => 400,
				'flex-height' => true,
				'flex-width'  => true,
			)
		); // Enables the custom logo swap.
		add_theme_support( 'align-wide' ); // Enables the editor option for images to span the full screen width (Gutenberg style).
		add_theme_support( 'automatic-feed-links' ); // Automatically adds RSS feed links to <head>.
		add_theme_support( 'customize-selective-refresh-widgets' ); // Allows selective widget refresh in the Customizer (without full page reload).
		add_theme_support( 'editor-styles' ); // Displays the theme fonts and styles inside the Gutenberg editor.
		add_editor_style( 'assets/css/style.css' ); // Points to the main CSS.

		// CUSTOM IMAGE SIZES (HARD CROP)
		// Kept in core because the sizes need to be registered on theme init.
		if ( rd_get_option_bool( 'image_resizing' ) ) {
			add_image_size( 'rd-micro', 150, 84, true );          // Thumbnails for Widgets/Sidebar (16:9).
			add_image_size( 'rd-popular-thumb', 200, 113, true ); // "Most Read" widget — display 100x56 (16:9) with DPR 2x retina = 200x113. Previously used WP's 'medium' (300x300, no fixed aspect-ratio), which served the giant original (up to 2560x1429) when medium did not cover it.
			add_image_size( 'rd-card-half', 400, 225, true );     // Post-grid cards on intermediate viewports (display ~390x220). Leverages WP's auto srcset — browser picks this one when display falls between rd-micro and rd-card. Previously WP served rd-card 600x338 (overkill at DPR 1x) or the original.
			add_image_size( 'rd-card', 600, 338, true );          // Size for the Home cards.
			add_image_size( 'rd-full-banner', 1200, 675, true );  // Size for the banner at the top of the post.
			add_image_size( 'rd-qr', 240, 240, true );            // Donation block QR codes (sidebar). Real display ~160-200px; 240 covers retina without wasting bandwidth like the 635x635 source admins usually upload.
		}

		// Registers menu locations.
		register_nav_menus(
			array(
				'menu-1' => esc_html__( 'Primary Menu', 'reloaded' ),
			)
		);
	}
endif;
add_action( 'after_setup_theme', 'rd_setup' );

/*******************************************************************************
 * Removes hidden/native WordPress image sizes                   - (Hardcoded) *
 */
add_action(
	'init',
	function () {
		remove_image_size( 'medium_large' );
		remove_image_size( '1536x1536' );
		remove_image_size( '2048x2048' );
	}
);

/*******************************************************************************
 * Renders the Logo (Image or Text)                              - (Hardcoded) *
 *******************************************************************************/
function rd_render_logo() {
	if ( has_custom_logo() ) {
		echo '<div class="site-branding-image">';
		the_custom_logo();
		echo '</div>';
	} else {
		?>
		<div class="site-branding-text">
			<h1 class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php bloginfo( 'name' ); ?>
				</a>
			</h1>
		</div>
		<?php
	}
}

/*******************************************************************************
 * Helper: site logo URL with fallback                             - (Hardcoded) *
 *                                                                                *
 * Source of truth for "which logo to use outside the regular frontend".         *
 * Resolves in order:                                                             *
 *   1. WP Custom Logo (Appearance → Customize → Site Identity)                 *
 *   2. assets/img/logo-reloaded-panel.webp (theme hardcoded fallback)          *
 *                                                                                *
 * Used by: mod-maintenance.php (503 screen), mod-security.php (WSOD 500),       *
 * mod-integrations.php (Discord facade when admin did not register a dedicated  *
 * logo via discord_facade_logo), mod-seo.php (Schema.org Organization logo).   *
 *                                                                                *
 * Why not use Custom Logo in the admin panel (panel.php) nor in the OG image    *
 * fallback (mod-seo.php → rd_seo_resolve_og_image): those 2 cases are           *
 * intentionally hardcoded — the first is theme UI (branding of the product     *
 * "ReloadeD theme"), the second is fallback-OF-fallback for OG image (Custom    *
 * Logo is usually horizontal, wrong ratio for the 1.91:1 social preview).       *
 *                                                                                *
 *
 * @param string $size Attachment size when the Custom Logo is used.             *
 *                     'medium' (default), 'large', 'full', etc.                 *
 * @return array { url: string, width: int|null, height: int|null }              *
 *******************************************************************************/
function rd_get_site_logo( string $size = 'medium' ): array {
	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$src = wp_get_attachment_image_src( $custom_logo_id, $size );
		if ( $src ) {
			return array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	// Fallback: hardcoded file at assets/img/logo-reloaded-panel.webp (430x100).
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp',
		'width'  => 430,
		'height' => 100,
	);
}

/*******************************************************************************
 * Widget area registration (Sidebar and Footer)                 - (Hardcoded) *
 *******************************************************************************/
function rd_widgets_init() {

	// Main sidebar.
	register_sidebar(
		array(
			'name'          => __( 'Main Sidebar', 'reloaded' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Add widgets here to appear in the sidebar.', 'reloaded' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="wp-block-heading widget-title">',
			'after_title'   => '</h2>',
		)
	);

	// Footer — 3 dynamic columns (columns 2-4 of the footer grid; column 1 is the
	// fixed brand block in footer.php). The first keeps the legacy id
	// `footer-widget-area` so any widget already placed there is preserved.
	$rd_footer_columns = array(
		'footer-widget-area'   => __( 'Footer Column 2', 'reloaded' ),
		'footer-widget-area-2' => __( 'Footer Column 3', 'reloaded' ),
		'footer-widget-area-3' => __( 'Footer Column 4', 'reloaded' ),
	);
	foreach ( $rd_footer_columns as $rd_footer_id => $rd_footer_name ) {
		register_sidebar(
			array(
				'name'          => $rd_footer_name,
				'id'            => $rd_footer_id,
				'description'   => __( 'Add widgets here to appear in this footer column.', 'reloaded' ),
				'before_widget' => '<div id="%1$s" class="widget footer-widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h3 class="footer-heading">',
				'after_title'   => '</h3>',
			)
		);
	}
}
add_action( 'widgets_init', 'rd_widgets_init' );

/***********************************************************************************
 * Loads admin assets                                                 - (Hardcoded) *
 *                                                                                  *
 * CSS:                                                                             *
 *   - rd_options panel                                                             *
 *   - Post editor (post.php / post-new.php) — to style the meta boxes              *
 *                                                                                  *
 * JS + Media uploader:                                                             *
 *   - Only inside the rd_options panel (where media uploads via fields exist)      *
 ***********************************************************************************/
function rd_admin_scripts( $hook ) {

	$is_panel       = ( strpos( $hook, 'rd_options' ) !== false );
	$is_post_editor = ( $hook === 'post.php' || $hook === 'post-new.php' );

	// CSS — loaded in both contexts.
	if ( $is_panel || $is_post_editor ) {
		wp_enqueue_style(
			'rd-admin-css',
			get_template_directory_uri() . '/assets/css/admin-style.css',
			array(),
			rd_asset_version( '/assets/css/admin-style.css' )
		);
	}

	// JS + Media — only inside the rd_options panel.
	if ( $is_panel ) {
		wp_enqueue_media();
		// Single consolidated admin-panel bundle (formerly 7 separate per-tab
		// files). Loaded on every tab; each internal module self-guards. Enqueued
		// at this hook's priority 5 so the per-tab modules (mod-stats, mod-backup,
		// mod-dashboard, mod-image-formats) can attach their wp_localize_script
		// data to the 'rd-admin-panel' handle at the default priority 10.
		wp_enqueue_script(
			'rd-admin-panel',
			get_template_directory_uri() . '/assets/js/admin-panel.js',
			array( 'jquery' ),
			rd_asset_version( '/assets/js/admin-panel.js' ),
			true
		);
		wp_localize_script(
			'rd-admin-panel',
			'rdAdminScripts',
			array(
				'i18n' => array(
					'selectImage' => __( 'Select fallback image', 'reloaded' ),
					'useImage'    => __( 'Use this image', 'reloaded' ),
				),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'rd_admin_scripts', 5 );

/*******************************************************************************
 * Helper: asset version with safe fallback                     - (Performance) *
 * Useful for cache busting in wp_enqueue_*.                                    *
 * Uses content hash + mtime for maximum cache-invalidation precision.          *
 * Immune to timestamp issues caused by atomic SFTP uploads.                    *
 *******************************************************************************/
function rd_asset_version( $relative_path, $fallback = '1.0.0' ) {
	$full_path = get_template_directory() . $relative_path;
	return file_exists( $full_path ) ? (string) filemtime( $full_path ) : $fallback;
}

/*******************************************************************************
 * Client IP — with header-spoofing protection                       - (Security) *
 *                                                                              *
 * Model: REMOTE_ADDR (which comes from TCP, non-spoofable) is the source of    *
 * truth. Proxy headers (CF-Connecting-IP, X-Forwarded-For) are only trusted    *
 * WHEN REMOTE_ADDR sits in a recognized proxy IP range — Cloudflare by         *
 * default + custom ranges configured by the admin in `trusted_proxy_ips`.      *
 *                                                                              *
 * Consumed by mod-maintenance (password rate-limit) and mod-views (view        *
 * dedupe). Canonical function — any module that needs to know "who the user   *
 * is" must call from here instead of reimplementing it.                        *
 *******************************************************************************/
function rd_get_client_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: '0.0.0.0';

	if ( rd_remote_is_trusted_proxy( $remote ) ) {
		// CF-Connecting-IP is a single visitor IP — primary source when trusted.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				return $cf_ip;
			}
		}
		// X-Forwarded-For is a "client, proxy1, proxy2, ..." list — first = original.
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ips       = explode( ',', $forwarded );
			$first     = trim( $ips[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}
	}

	// Default: raw REMOTE_ADDR — does not come from a header, attacker cannot forge it.
	return $remote;
}

/*******************************************************************************
 * Is REMOTE_ADDR in a recognized proxy range?                       - (Security) *
 *                                                                              *
 * Combines:                                                                    *
 *   - Cloudflare hardcoded list (https://www.cloudflare.com/ips/) — covers    *
 *     the most common case out-of-the-box, no admin configuration needed.     *
 *   - Custom panel ranges (`trusted_proxy_ips`, CIDR one per line) — for      *
 *     anyone using another proxy/CDN (Nginx-front, AWS ALB, Sucuri, BunnyCDN). *
 *******************************************************************************/
function rd_remote_is_trusted_proxy( string $ip ): bool {
	static $cf_ranges = array(
		// IPv4 — Cloudflare
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		// IPv6 — Cloudflare
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	if ( rd_ip_in_ranges( $ip, $cf_ranges ) ) {
		return true;
	}

	$custom = (string) rd_get_option( 'trusted_proxy_ips', '' );
	if ( $custom === '' ) {
		return false;
	}

	$lines  = preg_split( '/[\r\n]+/', $custom );
	$ranges = array_filter( array_map( 'trim', (array) $lines ) );
	if ( empty( $ranges ) ) {
		return false;
	}

	return rd_ip_in_ranges( $ip, $ranges );
}

/*******************************************************************************
 * Checks whether an IP falls inside any of the supplied CIDR ranges. - (Security) *
 *                                                                              *
 * Supports IPv4 (`192.168.1.0/24`), IPv6 (`2001:db8::/32`) and single IPs (no `/`). *
 * Malformed ranges are silently skipped — the function never fails on bad     *
 * admin input, just returns false.                                             *
 *******************************************************************************/
function rd_ip_in_ranges( string $ip, array $ranges ): bool {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton emits a warning on malformed IP; the @ + `false === $ip_bin` check below is the recommended pattern for graceful failure.
	$ip_bin = @inet_pton( $ip );
	if ( false === $ip_bin ) {
		return false;
	}
	$ip_is_v6 = strlen( $ip_bin ) === 16;

	foreach ( $ranges as $range ) {
		$range = trim( (string) $range );
		if ( '' === $range ) {
			continue;
		}

		// Single IP (no prefix) — direct binary comparison.
		if ( false === strpos( $range, '/' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- graceful failure on malformed range (admin may type bad input in the panel); validated via `false !== $range_bin` below.
			$range_bin = @inet_pton( $range );
			if ( false !== $range_bin && $range_bin === $ip_bin ) {
				return true;
			}
			continue;
		}

		list( $subnet, $prefix ) = explode( '/', $range, 2 );
		$prefix                  = (int) $prefix;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- graceful failure on malformed subnet; validated via `false === $subnet_bin` below.
		$subnet_bin = @inet_pton( $subnet );
		if ( false === $subnet_bin ) {
			continue;
		}

		// Family mismatch (IPv4 vs IPv6) — cannot match.
		$range_is_v6 = strlen( $subnet_bin ) === 16;
		if ( $range_is_v6 !== $ip_is_v6 ) {
			continue;
		}

		$max_prefix = $ip_is_v6 ? 128 : 32;
		if ( $prefix < 0 || $prefix > $max_prefix ) {
			continue;
		}

		$full_bytes     = intdiv( $prefix, 8 );
		$remaining_bits = $prefix % 8;

		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
			continue;
		}

		if ( $remaining_bits > 0 ) {
			$mask = ( ~( ( 1 << ( 8 - $remaining_bits ) ) - 1 ) ) & 0xff;
			if ( ( ord( $ip_bin[ $full_bytes ] ) & $mask ) !== ( ord( $subnet_bin[ $full_bytes ] ) & $mask ) ) {
				continue;
			}
		}

		return true;
	}

	return false;
}
