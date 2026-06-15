<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Performance - WordPress Optimizations and Cleanup
 *******************************************************************************/

/*******************************************************************************
 * Markdown Support, Alerts (GFM), Dynamic Anchors             - (Performance) *
 *******************************************************************************/
function rd_markdown_support( $content ) {

	if ( ! is_admin() && rd_get_option_bool( 'markdown_enabled' ) ) {

		// OPTIMIZATION: Load the heavy library only when it will actually be used
		if ( ! class_exists( 'Parsedown' ) ) {
			require_once get_template_directory() . '/lib/Parsedown.php';
		}

		// Gutenberg's code editor stores '>' as &gt; in freeform content, and
		// whether the core decodes it back before the_content varies between
		// WP versions. Parsedown only recognizes a blockquote with a REAL '>'
		// at line start — so [!TIP]/[!NOTE] alerts written as "&gt; [!TIP]"
		// silently degraded into inline text. Decode the line-start markers
		// (including nested "&gt; &gt;") before parsing; content that already
		// has real '>' is untouched.
		$content = preg_replace_callback(
			'/^(\s*(?:&gt;\s*)+)/m',
			static function ( $m ) {
				return str_replace( '&gt;', '>', $m[1] );
			},
			$content
		);

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( false );
		$html = $parsedown->text( $content ); // Converts the basic Markdown

		// 1. Intercept the blockquotes that contain the alerts
		$html = preg_replace(
			'/<blockquote>\s*<p>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(?:<br\s*\/?>|<\/p>)?/i',
			'<blockquote class="gh-alert gh-alert-$1"><p>',
			$html
		);

		// 2. Add automatic, clean IDs to all headings (h1 through h6)
		$html = preg_replace_callback(
			'/<h([1-6])>(.*?)<\/h\1>/is',
			function ( $matches ) {
				$level          = $matches[1];
				$texto_original = $matches[2];

				// 1. Strip HTML tags from inside the title
				$texto_puro = wp_strip_all_tags( $texto_original );

				// 2. Convert to lowercase (with accent support)
				$id = mb_strtolower( $texto_puro, 'UTF-8' );

				// 3. GITHUB'S FINAL REFINEMENT:
				// Keep Letters (\p{L}), combining Marks (\p{M}), invisible Formatters/joiners (\p{Cf}),
				// Numbers (\p{Nd}), Spaces (\s) and Hyphens (-). Drop the base Symbols.
				$id = preg_replace( '/[^\p{L}\p{M}\p{Cf}\p{Nd}\s-]/u', '', $id );

				// 4. GITHUB'S SECRET: Replace EACH individual space with a hyphen.
				$id = preg_replace( '/\s/u', '-', $id );

				// 5. URL-encode (turns the invisible "joiner" and "ghost" chars into %E2...%EF...)
				$id = rawurlencode( $id );

				// Extra safety: if the title disappears entirely
				if ( empty( $id ) || $id === '-' ) {
					$id = 'secao-' . uniqid();
				}

				// Rebuild the HTML tag with the new ID inserted
				return "<h{$level} id=\"{$id}\">{$texto_original}</h{$level}>";
			},
			$html
		);

		// 3. Remove the extra <p> tags around isolated <br> elements
		$html = preg_replace( '/<p>\s*(<br\s*\/?>)\s*<\/p>/i', '$1', $html );

		return $html;
	}
	return $content;
}
add_filter( 'the_content', 'rd_markdown_support', 6 );

/*******************************************************************************
 * Enqueues scripts and styles (CSS and JS)                    - (Performance) *
 *******************************************************************************/
function rd_scripts() {
	wp_enqueue_style( 'rd-main-style', get_template_directory_uri() . '/assets/css/style.css', array(), rd_asset_version( '/assets/css/style.css' ) );
	wp_enqueue_script( 'rd-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), rd_asset_version( '/assets/js/navigation.js' ), true ); // Load in the footer

	// INJECT THE JS TRANSLATIONS RIGHT BELOW THE MAIN SCRIPT
	wp_localize_script(
		'rd-navigation',
		'reloaded_i18n',
		array(
			'copied'          => esc_html__( 'Key Copied!', 'reloaded' ),
			'copy_error'      => esc_html__( 'Error copying: ', 'reloaded' ),
			// menu_close/menu_open removed: the hamburger no longer morphs into an X
			// (the floating .menu-close carries a static PHP-translated aria-label).
			'comment_sending' => esc_html__( 'Sending…', 'reloaded' ),
			'comment_sent'    => esc_html__( 'Comment sent! Loading…', 'reloaded' ),
			'comment_error'   => esc_html__( 'Unexpected error submitting the comment. Reload the page and try again.', 'reloaded' ),
			'network_error'   => esc_html__( 'Network error: ', 'reloaded' ),
		)
	);

	// Panel gate: only load Prism.js if the toggle is enabled
	if ( rd_get_option_bool( 'prism_js' ) ) {
		// Load Prism.js only on article pages.
		if ( is_single() || is_page() ) {
			wp_enqueue_script( 'rd-prism-js', get_template_directory_uri() . '/lib/prism.js', array(), '1.30.0', true );
		}
	}

	// AJAX comment submit — only where a comment form can render. Used to live
	// inside navigation.js, shipping dead code to every commentless page.
	if ( is_singular() && comments_open() ) {
		wp_enqueue_script( 'rd-comments', get_template_directory_uri() . '/assets/js/comments.js', array(), rd_asset_version( '/assets/js/comments.js' ), true );
	}
}

/*******************************************************************************
 * Metric-matched font fallback faces — inline in <head>       - (Performance) *
 *                                                                             *
 * The Inter-fallback/Poppins-fallback @font-face (local Arial + size-adjust)  *
 * live in style.css, but style.css loads ASYNC behind the inline critical     *
 * CSS — and the critical extractor ignores @font-face on purpose (real faces  *
 * carry relative url() that would break when inlined). Without this block the *
 * first paint used RAW sans-serif and the page re-rendered with the +7.4%     *
 * size-adjusted Arial when style.css landed — a brand-new mid-load shift that *
 * REGRESSED CLS to 0.16 (measured 2026-06-12). Printing the faces inline at   *
 * priority 1 (right after the critical CSS at 0) makes the very first paint   *
 * already use the matched metrics: one geometry from first paint to the woff2 *
 * swap. ~350 bytes, local() only, no requests.                                *
 *                                                                             *
 * NOTE: keep these values in sync with sass/base/_variables.scss.             *
 *******************************************************************************/
function rd_print_font_fallback_faces() {
	if ( is_admin() ) {
		return;
	}
	$nonce_attr = function_exists( 'rd_csp_nonce_attr' ) ? rd_csp_nonce_attr() : '';
	// Values calibrated against the shipped variable fonts + real Arial via
	// frequency-weighted advance widths (fontTools, 2026-06-12) — keep in
	// sync with sass/base/_variables.scss.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS literal; nonce attribute pre-escaped by rd_csp_nonce_attr().
	echo '<style' . $nonce_attr . '>@font-face{font-family:"Inter-fallback";src:local("Arial");size-adjust:107.48%;ascent-override:90.14%;descent-override:22.44%;line-gap-override:0%}@font-face{font-family:"Poppins-fallback";src:local("Arial");size-adjust:114.11%;ascent-override:92.02%;descent-override:30.67%;line-gap-override:8.76%}</style>' . "\n";
}
add_action( 'wp_head', 'rd_print_font_fallback_faces', 1 );

/*******************************************************************************
 * LCP image preload                                           - (Performance) *
 *                                                                             *
 * The LCP audits showed 880ms (mobile) / 1.040ms (desktop) of "resource load  *
 * delay": the browser only discovers the hero image after parsing a good      *
 * chunk of HTML. The theme KNOWS the LCP element server-side — carousel       *
 * slide 1 on the home, the featured banner on singles — so it announces it    *
 * in <head> via <link rel="preload" as="image">.                              *
 *                                                                             *
 * Correctness rules (both prevent a DOUBLE download):                         *
 *   1. imagesrcset/imagesizes mirror EXACTLY what the rendered <img>/<source> *
 *      carries (same WP srcset APIs + the same next-gen mapping from          *
 *      mod-image-formats + the same sizes string), so the candidate the      *
 *      browser picks from the preload matches the one the markup picks.       *
 *   2. `type` makes browsers without AVIF/WebP support skip the preload       *
 *      entirely (they'd fall back to the <img> JPEG anyway).                  *
 *******************************************************************************/
function rd_preload_lcp_image() {
	if ( is_admin() ) {
		return;
	}

	$preloads = array();

	if ( function_exists( 'rd_carousel_should_render' ) && rd_carousel_should_render() ) {
		// The LCP element differs per viewport (measured 2026-06-12): on
		// desktop it's carousel slide 1 (~1130px painted), on phones the
		// first main-grid card outpaints the 88vw slide. `media` on the
		// preload links makes each device fetch ONLY its own LCP image.
		$rd_posts   = rd_carousel_get_posts();
		$preloads[] = array(
			'id'    => (int) get_post_thumbnail_id( $rd_posts[0] ),
			'size'  => 'rd-full-banner',
			'sizes' => rd_carousel_img_sizes(),
			'media' => '(min-width: 769px)',
		);

		global $wp_query;
		if ( isset( $wp_query->posts[0] ) && has_post_thumbnail( $wp_query->posts[0] ) ) {
			$card_id    = (int) get_post_thumbnail_id( $wp_query->posts[0] );
			$card_sizes = wp_get_attachment_image_sizes( $card_id, 'rd-card' );
			$preloads[] = array(
				'id'    => $card_id,
				'size'  => 'rd-card',
				'sizes' => is_string( $card_sizes ) ? $card_sizes : '',
				'media' => '(max-width: 768px)',
			);
		}
	} elseif ( is_singular( 'post' ) && has_post_thumbnail() ) {
		$banner_id  = (int) get_post_thumbnail_id();
		$wp_sizes   = wp_get_attachment_image_sizes( $banner_id, 'rd-full-banner' );
		$preloads[] = array(
			'id'    => $banner_id,
			'size'  => 'rd-full-banner',
			'sizes' => is_string( $wp_sizes ) ? $wp_sizes : '',
			'media' => '',
		);
	}

	foreach ( $preloads as $preload ) {
		if ( ! $preload['id'] ) {
			continue;
		}

		$srcset     = wp_get_attachment_image_srcset( $preload['id'], $preload['size'] );
		$sizes_attr = $preload['sizes'];
		if ( ! is_string( $srcset ) || '' === $srcset ) {
			// Tiny original without intermediate sizes — preload the single file.
			$src = wp_get_attachment_image_src( $preload['id'], $preload['size'] );
			if ( ! $src ) {
				continue;
			}
			$srcset     = $src[0];
			$sizes_attr = '';
		}

		// Same next-gen mapping the <picture> sources use — the preload must
		// point at the very URLs the markup will request.
		$type = '';
		if ( function_exists( 'rd_img_get_nextgen_srcsets' ) ) {
			$nextgen = rd_img_get_nextgen_srcsets( $srcset, '' );
			if ( isset( $nextgen['image/avif'] ) ) {
				$srcset = $nextgen['image/avif'];
				$type   = 'image/avif';
			} elseif ( isset( $nextgen['image/webp'] ) ) {
				$srcset = $nextgen['image/webp'];
				$type   = 'image/webp';
			}
		}

		printf(
			'<link rel="preload" as="image" imagesrcset="%s"%s%s%s fetchpriority="high">' . "\n",
			esc_attr( $srcset ),
			'' !== $sizes_attr ? ' imagesizes="' . esc_attr( $sizes_attr ) . '"' : '',
			'' !== $type ? ' type="' . esc_attr( $type ) . '"' : '',
			'' !== $preload['media'] ? ' media="' . esc_attr( $preload['media'] ) . '"' : ''
		);
	}
}
add_action( 'wp_head', 'rd_preload_lcp_image', 2 );

/*******************************************************************************
 * Adds `defer` to the theme scripts                           - (Performance) *
 *                                                                             *
 * Defer = downloads in parallel with HTML, runs only after the DOM is parsed  *
 * and BEFORE DOMContentLoaded. Since our scripts run on DOMContentLoaded      *
 * anyway, it's safe. Lighthouse detects the attribute and lowers the download *
 * priority (frees bandwidth for critical resources first).                    *
 *                                                                             *
 * Uses the `script_loader_tag` filter instead of the modern `'strategy' =>    *
 * 'defer'` (WP 6.3+) to keep compatibility with the minimum version.          *
 *******************************************************************************/
function rd_defer_theme_scripts( $tag, $handle ) {
	// Every handle here must be defer-safe: no top-level DOM reads outside a
	// DOMContentLoaded listener, and no inline script depending on it having
	// run. All current theme scripts gate their work on DOMContentLoaded.
	$deferred = array(
		'rd-navigation',
		'rd-prism-js',
		'rd-carousel',
		// rd-search-suggestions is no longer enqueued — it is lazy-injected on
		// the first focus of a search field (mod-search-suggestions.php).
		'rd-views-tracker',
		'rd-toc',
		'rd-comments',
		'rd-search-layout',
	);
	if ( in_array( $handle, $deferred, true ) && strpos( $tag, ' defer' ) === false ) {
		$tag = str_replace( '<script ', '<script defer ', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'rd_defer_theme_scripts', 10, 2 );

/*******************************************************************************
 * Optimizes the Gutenberg "Latest Posts" block thumbnails     - (Performance) *
 *                                                                             *
 * The `core/latest-posts` block uses WP's `thumbnail` size by default         *
 * (150x150 cropped), which in our widget layout renders ~128x78 — off         *
 * aspect ratio, and depending on the source WP serves the ORIGINAL image      *
 * (414x622, 514x512 etc.) because no nearby size matches.                     *
 *                                                                             *
 * We inject the `featuredImageSizeSlug` attribute into the block render       *
 * forcing use of our `rd-micro` (150x84, 16:9 hardcrop) — matches the         *
 * widget's aspect ratio, stays lightweight.                                   *
 *                                                                             *
 * Only applies if `image_resizing` is active (custom sizes registered).       *
 *******************************************************************************/
function rd_override_latest_posts_size( $parsed_block ) {
	if ( ! rd_get_option_bool( 'image_resizing' ) ) {
		return $parsed_block;
	}

	if ( isset( $parsed_block['blockName'] ) && $parsed_block['blockName'] === 'core/latest-posts' ) {
		$parsed_block['attrs']['featuredImageSizeSlug'] = 'rd-micro';
	}
	return $parsed_block;
}
add_filter( 'render_block_data', 'rd_override_latest_posts_size' );

/*******************************************************************************
 * Override the `sizes` attribute for the `rd-card` size       - (Performance) *
 *                                                                             *
 * WP generates `sizes="(max-width: 600px) 100vw, 600px"` by default, telling  *
 * the browser "I'll take 600px on desktop". But in our 2-col grid with a      *
 * sidebar, each card measures ~461px on desktop ≥1025px. The browser ends     *
 * up picking the 600px variant instead of something smaller.                  *
 *                                                                             *
 * We fix it so the browser knows the real width per breakpoint:               *
 *   - Desktop (≥1025px with sidebar): ~461px per card                         *
 *   - Tablet (769-1024px): 50vw (2 cards per row, no sidebar)                 *
 *   - Mobile (≤768px): 100vw (1 column)                                       *
 *******************************************************************************/
function rd_calculate_card_sizes( $sizes, $size ) {
	// $size may arrive as a slug string ('rd-card') OR as an array
	// [width, height] — WP converts the slug to dimensions in some paths
	// before firing this filter. We detect both formats.
	$is_card = ( $size === 'rd-card' )
		|| ( is_array( $size ) && isset( $size[0], $size[1] ) && (int) $size[0] === 600 && (int) $size[1] === 338 );

	if ( $is_card ) {
		return '(min-width: 1025px) 461px, (min-width: 769px) 50vw, 100vw';
	}
	return $sizes;
}
add_filter( 'wp_calculate_image_sizes', 'rd_calculate_card_sizes', 10, 2 );

/*******************************************************************************
 * Strip WP 6.7+ auto-sizes from lazy post thumbnails          - (Performance) *
 *                                                                             *
 * WP 6.7+ prepends `auto, ` to the `sizes` of every lazy-loaded image. The    *
 * `auto` keyword tells Chrome to resolve the candidate from the laid-out      *
 * width — handy when no good hint exists, but it OVERRIDES the hand-tuned      *
 * per-layout `sizes` we set in post-card.php. On the desktop 3-col home        *
 * sections it consistently over-resolves and grabs the 600w variant for the   *
 * ~304px card slots (PageSpeed flagged ~66 KiB desktop, 2026-06-13), undoing  *
 * the `(min-width: 769px) 320px` hint that correctly maps to 400w. Mobile is  *
 * unaffected: those sections are 1-col there, so `100vw` and `auto` resolve   *
 * to the same full-width candidate.                                           *
 *                                                                             *
 * We post-process the FINAL thumbnail HTML (after core injected `auto`) and   *
 * drop the leading `auto, `, restoring our explicit hint — version-proof, no  *
 * dependency on core internals. The eager hero/LCP cards never get `auto`     *
 * (it's lazy-only), and the YouTube facade cover keeps its own intentional    *
 * `sizes="auto, …"` (raw markup, not a post thumbnail, so this never sees it).*
 *******************************************************************************/
function rd_strip_thumbnail_auto_sizes( $html ) {
	return preg_replace( '/(\ssizes=(["\']))auto,\s*/i', '$1', $html );
}
add_filter( 'post_thumbnail_html', 'rd_strip_thumbnail_auto_sizes' );

// In-content images (the_content): keep our explicit/derived sizes, skip the
// auto keyword. Also preserves the YouTube cover's own hardcoded sizes="auto".
add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );

add_action( 'wp_enqueue_scripts', 'rd_scripts' );

/*******************************************************************************
 * Disables WP's automatic emojis and styles                   - (Performance) *
 *******************************************************************************/
function rd_disable_emojis() {

	if ( ! rd_get_option_bool( 'disable_emojis' ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'rd_disable_emojis' );

/*******************************************************************************
 * Improves security, removes the WP version                   - (Performance) *
 */
add_filter(
	'the_generator',
	function ( $gen ) {
		return rd_get_option_bool( 'hide_wp_ver' ) ? '' : $gen;
	}
);

/*******************************************************************************
 * Intercepts iframes (YouTube Facade)                         - (Performance) *
 *******************************************************************************/

/**
 * Parses a YouTube timestamp value and returns an integer in seconds.
 *
 * Accepted formats (all valid on YouTube):
 *   "30"        → 30s
 *   "30s"       → 30s
 *   "1m30s"     → 90s
 *   "1h2m30s"   → 3750s
 *   ""/null/invalid → 0
 */
function rd_youtube_parse_timestamp( $t ) {
	if ( $t === '' || $t === null ) {
		return 0;
	}
	// "Pure number" case (e.g. "?t=90") — YouTube interprets it as seconds
	if ( ctype_digit( (string) $t ) ) {
		return (int) $t;
	}
	// Case with h/m/s suffixes (e.g. "1h2m30s") — sum each present component
	$seconds = 0;
	if ( preg_match( '/(\d+)h/i', $t, $m ) ) {
		$seconds += (int) $m[1] * 3600;
	}
	if ( preg_match( '/(\d+)m/i', $t, $m ) ) {
		$seconds += (int) $m[1] * 60;
	}
	if ( preg_match( '/(\d+)s/i', $t, $m ) ) {
		$seconds += (int) $m[1];
	}
	return $seconds;
}

/**
 * Extracts the first YouTube video ID found in a string — a single oEmbed URL
 * or a whole post content. Returns '' when there is none.
 *
 * Shared by the facade (oEmbed filter) and the Latest Video widget, which
 * scans post_content for the first embedded video.
 */
function rd_youtube_extract_id( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return '';
	}
	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|v/)|youtu\.be/|youtube-nocookie\.com/embed/)([a-zA-Z0-9_-]{11})~', $text, $matches ) ) {
		return $matches[1];
	}
	return '';
}

/**
 * Thumbnail + play button used inside the facade. Extracted so the Latest
 * Video widget can reuse the exact same cover for its "facade off" poster.
 * `alt` stays a literal to keep the facade output byte-for-byte unchanged.
 *
 * Responsive cover: the sidebar widget slot renders at ~310 CSS px but used
 * to always download sddefault (640x480, ~33 KiB) — PageSpeed flagged it.
 * hqdefault (480x360) joins the srcset and covers the small slots at roughly
 * half the bytes. Both candidates are YouTube's 4:3 family on purpose: the
 * 16:9 ones (mqdefault etc.) would change the cover's intrinsic ratio and
 * reshape the facade box depending on which candidate the browser picks.
 * sizes="auto" (valid on loading=lazy) lets Chrome match the real slot width;
 * the static list is the fallback for engines without auto support.
 */
function rd_youtube_cover_html( $video_id ) {
	$base = 'https://img.youtube.com/vi/' . esc_attr( $video_id );
	return '<img src="' . $base . '/sddefault.jpg"'
		. ' srcset="' . $base . '/hqdefault.jpg 480w, ' . $base . '/sddefault.jpg 640w"'
		. ' sizes="auto, (max-width: 768px) 100vw, 640px"'
		. ' alt="Video cover" loading="lazy" width="640" height="480">
                        <div class="play-button">
                            <svg viewBox="0 0 68 48">
                                <path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55c-2.93.78-4.64 3.26-5.42 6.19C.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="#FF0000"/>
                                <path d="M27.26 33.15V14.85L44.5 24z" fill="#fff"/>
                            </svg>
                        </div>';
}

/**
 * Wraps the cover in the .rd-facade click-to-load container (navigation.js
 * swaps in the real iframe on click). Used by the oEmbed filter and the
 * Latest Video widget when the facade is enabled.
 */
function rd_youtube_facade_markup( $video_id, $timestamp = 0 ) {
	$data_t = $timestamp > 0 ? ' data-t="' . esc_attr( $timestamp ) . '"' : '';
	return '<div class="rd-facade" data-type="youtube" data-id="' . esc_attr( $video_id ) . '"' . $data_t . '>
                        ' . rd_youtube_cover_html( $video_id ) . '
                    </div>';
}

// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $attr is part of the `embed_oembed_html` filter signature, even when unused.
function rd_youtube_facade( $cache, $url, $attr ) {

	if ( ! rd_get_option_bool( 'facade_youtube' ) ) {
		return $cache; }

	if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false || strpos( $url, 'youtube-nocookie.com' ) !== false ) {
		$video_id = rd_youtube_extract_id( $url );

		// Timestamp: try `t=` first (user-facing format); fall back to `start=` (embed format)
		$timestamp = 0;
		if ( preg_match( '/[?&]t=([^&]+)/', $url, $t_matches ) ) {
			$timestamp = rd_youtube_parse_timestamp( $t_matches[1] );
		}
		if ( $timestamp === 0 && preg_match( '/[?&]start=(\d+)/', $url, $s_matches ) ) {
			$timestamp = (int) $s_matches[1];
		}

		if ( $video_id ) {
			return rd_youtube_facade_markup( $video_id, $timestamp );
		}
	}
	return $cache;
}
add_filter( 'embed_oembed_html', 'rd_youtube_facade', 10, 3 );

/*******************************************************************************
 * Disables the Gutenberg CSS and Global Styles                - (Performance) *
 *******************************************************************************/
function rd_disable_gutenberg_assets() {
	if ( ! rd_get_option_bool( 'disable_gutenberg_css' ) ) {
		return;
	}

	add_filter( 'should_load_separate_core_block_assets', '__return_false' );

	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

	add_action(
		'wp_enqueue_scripts',
		function () {
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'classic-theme-styles' );
			wp_dequeue_style( 'wc-blocks-style' );
		},
		100
	);
}
add_action( 'after_setup_theme', 'rd_disable_gutenberg_assets' );

/*******************************************************************************
 * Limits post revisions                                       - (Performance) *
 *                                                                             *
 * WP stores unlimited revisions by default (each save + autosave).            *
 * On old sites, the wp_posts table bloats with hundreds of revisions per      *
 * post. This filter caps the number configured by the admin.                  *
 *                                                                             *
 * Behavior by value:                                                          *
 *   - 0: no revision is stored (saves straight to the post, no history)       *
 *   - 1+: keeps only the N most recent revisions; older ones are removed      *
 *         automatically when the post is saved                                *
 *                                                                             *
 * Note: old revisions already in the database are NOT deleted                 *
 * retroactively. The cap only applies to future saves onward.                 *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $num and $post are part of the `wp_revisions_to_keep` filter signature, even though the final value only depends on the panel config.
function rd_limit_post_revisions( $num, $post ) {
	$configured = (int) rd_get_option( 'post_revisions_limit', 5 );
	return max( 0, min( 50, $configured ) );
}
add_filter( 'wp_revisions_to_keep', 'rd_limit_post_revisions', 10, 2 );

/*******************************************************************************
 * Optimizes the WordPress Heartbeat API                       - (Performance) *
 *                                                                             *
 * Heartbeat fires AJAX requests to `admin-ajax.php` in the background:        *
 *   - Post editor: 15s base, ramps to 5s during active autosave               *
 *   - Other admin screens: 60s (default)                                      *
 *   - Frontend: NOT enqueued by default — only when plugins ask               *
 *     (WooCommerce mini-cart, BuddyPress notifications, etc.)                 *
 *                                                                             *
 * Optimization (toggle ON):                                                   *
 *   - Post editor: untouched (autosave + edit lock preserved)                 *
 *   - Other admin: 60s → 120s (max allowed by WP, -50% requests)              *
 *     Trade-off: real-time notifications (locks, counters) arrive             *
 *     slower, but it's acceptable for a typical blog/portal                   *
 *   - Frontend: full deregister (defensive — if a future plugin adds          *
 *     heartbeat there, it's blocked ahead of time)                            *
 *                                                                             *
 * Toggle default OFF — introduces no change without admin opt-in.             *
 *******************************************************************************/
function rd_optimize_heartbeat_frontend() {
	if ( ! rd_get_option_bool( 'optimize_heartbeat' ) ) {
		return;
	}
	wp_deregister_script( 'heartbeat' );
}
add_action( 'wp_enqueue_scripts', 'rd_optimize_heartbeat_frontend', 1 );

function rd_optimize_heartbeat_settings( $settings ) {
	if ( ! rd_get_option_bool( 'optimize_heartbeat' ) ) {
		return $settings;
	}

	// In the post editor keep the default (autosave relies on the short interval)
	global $pagenow;
	if ( isset( $pagenow ) && in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
		return $settings;
	}

	// 120s is the maximum allowed by WP (any higher value is capped)
	$settings['interval'] = 120;
	return $settings;
}
add_filter( 'heartbeat_settings', 'rd_optimize_heartbeat_settings' );

/*******************************************************************************
 * DNS Prefetch + Preconnect for external domains              - (Performance) *
 *                                                                             *
 * Uses the native `wp_resource_hints` filter to inject hints in <head>:       *
 *                                                                             *
 *   - preconnect: resolves DNS + TCP + TLS handshake ahead of time. Costs     *
 *     ~1-2KB of bandwidth but saves ~300ms when the resource is requested.    *
 *     Used for domains we know will load (e.g. YouTube when the               *
 *     user clicks the facade).                                                *
 *                                                                             *
 *   - dns-prefetch: resolves only the DNS ahead of time, more conservative.   *
 *     Costs almost nothing and saves ~150ms. Used for "maybe" domains         *
 *     (CDN thumbnails, Gravatar, GA).                                         *
 *                                                                             *
 * Each hint is only injected if its matching feature is active — we don't     *
 * want to advertise a domain the theme doesn't use on that install.           *
 *                                                                             *
 * No panel option: it's a baseline optimization, zero downside.               *
 *******************************************************************************/
function rd_add_resource_hints( $hints, $relation_type ) {

	if ( $relation_type === 'preconnect' ) {
		// YouTube iframe — loaded when the user clicks the facade. It's only
		// worth preconnecting when there's a real chance of an embed on the page:
		// singular (post/page with content) and search (results may have YT posts).
		// On listing home/archive the preconnect is "wasted" and Lighthouse
		// complains — so we make it conditional.
		if ( rd_get_option_bool( 'facade_youtube' ) && ( is_singular() || is_search() ) ) {
			$hints[] = array(
				'href'        => 'https://www.youtube.com',
				'crossorigin' => 'anonymous',
			);
			$hints[] = array(
				'href'        => 'https://www.youtube-nocookie.com',
				'crossorigin' => 'anonymous',
			);
		}
	}

	if ( $relation_type === 'dns-prefetch' ) {
		// YouTube thumbnails — used in the facade markup (load early)
		if ( rd_get_option_bool( 'facade_youtube' ) ) {
			$hints[] = 'https://img.youtube.com';
			$hints[] = 'https://i.ytimg.com';
		}

		// Discord widget — iframe loaded in the sidebar when the widget is placed
		// (even without a facade, the domain is the same). Gated on widget presence.
		if ( is_active_widget( false, false, 'rd_discord' ) ) {
			$hints[] = 'https://ptb.discord.com';
		}

		// Google Tag Manager — only when GA is configured in the panel
		$ga_id = rd_get_option( 'ga_id' );
		if ( ! empty( $ga_id ) ) {
			$hints[] = 'https://www.googletagmanager.com';
		}

		// Gravatar — comment avatars (part of WP core)
		$hints[] = 'https://secure.gravatar.com';
	}

	return $hints;
}
add_filter( 'wp_resource_hints', 'rd_add_resource_hints', 10, 2 );

/*******************************************************************************
 * Preload critical fonts                                      - (Performance) *
 *                                                                             *
 * The browser only discovers the @font-face after parsing the CSS. `<link     *
 * rel="preload" as="font">` in <head> starts the font download in parallel    *
 * with the stylesheet, closing the gap between CSS and font arrival.          *
 *                                                                             *
 * Preload is expensive — it blocks critical resources to prioritize these     *
 * fonts. So we only preload the **two most used above-the-fold**:             *
 *                                                                             *
 *   - Inter Regular (400): base text — paragraphs, sidebar, general body      *
 *   - Poppins Bold (700): headings — post titles, logo, navigation            *
 *                                                                             *
 * The other 14 variants (Inter italic/500/600/700, Poppins 500/600/800/900,   *
 * all Mono) load normally via @font-face as needed.                           *
 *                                                                             *
 * `crossorigin="anonymous"` is required for font preload (CORS spec).         *
 * Priority 1 in wp_head to emit before the stylesheet — although the browser  *
 * processes preload in parallel, an early position helps in old browsers.     *
 *******************************************************************************/
function rd_preload_critical_fonts() {
	if ( ! rd_get_option_bool( 'preload_critical_fonts' ) ) {
		return;
	}

	$base = get_template_directory_uri() . '/assets/fonts/';

	/*
	 * Two variable files now cover the ENTIRE above-the-fold typography
	 * (2026-06-12 variable-font migration, closing the BACKLOG font audit):
	 * Inter variable (47 KB, body weights 400-700, Google latin slice) and
	 * Poppins variable (17.6 KB, heading weights 100-900, custom build by
	 * Finallf — Google doesn't ship a variable Poppins). The per-weight
	 * preload guessing game is gone: the old list shipped ~173 KB across 5
	 * static files and still missed weights (inter-600, poppins-500).
	 * Out on purpose: italics (decorative/synthesized) and jetbrains-mono
	 * (code blocks, below-the-fold posts only).
	 */
	$fonts = array(
		'inter-variable.woff2',   // ALL body text weights — Inter variable
		'poppins-variable.woff2', // ALL heading weights — Poppins variable (custom build)
	);

	foreach ( $fonts as $file ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
			esc_url( $base . $file )
		);
	}
}
add_action( 'wp_head', 'rd_preload_critical_fonts', 1 );

/*******************************************************************************
 * Ensures lazy loading on content iframes                     - (Performance) *
 *                                                                             *
 * WP 5.9+ already adds `loading="lazy"` automatically on iframes from         *
 * `the_content()`, except the first iframe above the fold (WP heuristic       *
 * to avoid hurting LCP). This filter is DEFENSIVE — in case some plugin       *
 * disables `wp_lazy_loading_enabled`, we ensure iframes stay lazy.            *
 *                                                                             *
 * Iframes outside the_content() (e.g. Discord widget in the sidebar) are      *
 * handled case by case in the markup (`loading="lazy"` directly on the tag),  *
 * because this filter only acts on content via wp_filter_content_tags().      *
 *                                                                             *
 * No panel option — lazy iframe is native HTML5, zero downside.               *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $context is part of the `wp_lazy_loading_enabled` filter signature, even when we only check the tag_name.
function rd_force_iframe_lazy( $enabled, $tag_name, $context ) {
	if ( $tag_name === 'iframe' ) {
		return true;
	}
	return $enabled;
}
add_filter( 'wp_lazy_loading_enabled', 'rd_force_iframe_lazy', 10, 3 );
