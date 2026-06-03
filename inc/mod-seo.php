<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: SEO - Open Graph, Twitter Cards and Meta Tags
 *******************************************************************************/

// Minimum accepted by Facebook (200x200). Smaller images are rejected.
const RD_SEO_OG_IMAGE_MIN = 200;

/*******************************************************************************
 * Internal image validation for og:image                              - (SEO) *
 *******************************************************************************/

/**
 * Validates a Media Library attachment for use as og:image.
 * Rejects SVGs (not supported by social networks) and images below 200x200.
 *
 * @param int $attachment_id
 * @return array|false ['url' => string, 'width' => int, 'height' => int] or false if invalid
 */
function rd_seo_validate_attachment_image( int $attachment_id ) {
	// Reject SVG (Facebook, Twitter/X, WhatsApp and Discord don't render it)
	if ( get_post_mime_type( $attachment_id ) === 'image/svg+xml' ) {
		return false;
	}

	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $src ) {
		return false;
	}

	list( $url, $width, $height ) = $src;

	// Reject images smaller than the minimum accepted by Facebook
	if ( $width < RD_SEO_OG_IMAGE_MIN || $height < RD_SEO_OG_IMAGE_MIN ) {
		return false;
	}

	return array(
		'url'    => $url,
		'width'  => (int) $width,
		'height' => (int) $height,
	);
}

/**
 * Validates an image URL (from the panel, possibly external) for og:image.
 * If the URL belongs to the Media Library, delegates to the full validation (with dimensions).
 * For external URLs, trusts the extension and omits the dimensions — without making an HTTP
 * request on each page load.
 *
 * @param string $url
 * @return array|false ['url' => string, 'width' => int|null, 'height' => int|null] or false if invalid
 */
function rd_seo_validate_url_image( string $url ) {
	if ( empty( $url ) ) {
		return false;
	}

	$path = wp_parse_url( $url, PHP_URL_PATH );
	$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

	// Reject SVG and any non-raster format
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
		return false;
	}

	// If the URL is from a local attachment, use the full validation
	// (respect its verdict; if it fails, do NOT fall to "pure external" mode)
	$attachment_id = attachment_url_to_postid( $url );
	if ( $attachment_id ) {
		return rd_seo_validate_attachment_image( $attachment_id );
	}

	// External URL — valid raster extension, unknown dimensions
	return array(
		'url'    => $url,
		'width'  => null,
		'height' => null,
	);
}

/**
 * Resolves the best available image for og:image, in order of preference:
 *   1. The post's featured image (validated)
 *   2. Fallback image configured in the panel (validated)
 *   3. The theme logo (dev-controlled, always considered valid)
 *
 * @param int $post_id
 * @return array ['url' => string, 'width' => int|null, 'height' => int|null]
 */
function rd_seo_resolve_og_image( int $post_id ) {
	// 1. Featured image
	if ( has_post_thumbnail( $post_id ) ) {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		$resolved = rd_seo_validate_attachment_image( $thumb_id );
		if ( $resolved ) {
			return $resolved;
		}
	}

	// 2. og_fallback_image from the panel (may be internal or external)
	$fallback = rd_get_option( 'og_fallback_image' );
	if ( ! empty( $fallback ) ) {
		$resolved = rd_seo_validate_url_image( $fallback );
		if ( $resolved ) {
			return $resolved;
		}
	}

	// 3. Final fallback: the theme logo
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp',
		'width'  => null,
		'height' => null,
	);
}

/*******************************************************************************
 * Extracts the handle (`@user`) from a Twitter/X URL                  - (SEO) *
 *                                                                             *
 * Accepts common formats:                                                     *
 *   https://x.com/user                                                        *
 *   https://twitter.com/user                                                  *
 *   https://www.x.com/user/                                                   *
 *                                                                             *
 * Returns an empty string if the URL doesn't match the pattern (e.g. the      *
 * admin pasted a link to a specific tweet, or another domain).                *
 *******************************************************************************/
function rd_seo_extract_twitter_handle( string $url ): string {
	if ( empty( $url ) ) {
		return '';
	}

	if ( preg_match( '#https?://(?:www\.)?(?:twitter|x)\.com/([A-Za-z0-9_]{1,15})/?$#i', trim( $url ), $m ) ) {
		return '@' . $m[1];
	}

	return '';
}

/*******************************************************************************
 * Resolves the "punchy" description of a singular post                - (SEO) *
 *                                                                             *
 * Used by og:description and by <meta name="description"> on singulars.       *
 * Order: manual excerpt → 25-word trim of post_content.                       *
 *******************************************************************************/
function rd_seo_resolve_post_description( WP_Post $post ): string {
	$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
	if ( empty( $excerpt ) ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' );
	}
	return $excerpt;
}

/*******************************************************************************
 * Meta Tags Open Graph                                                - (SEO) *
 *******************************************************************************/
function rd_add_open_graph_tags() {
	if ( ! ( is_single() || is_page() ) ) {
		return;
	}
	if ( ! rd_get_option_bool( 'enable_open_graph' ) ) {
		return;
	}

	global $post;

	$title = get_the_title();
	$url   = get_permalink();

	$excerpt = rd_seo_resolve_post_description( $post );

	$image = rd_seo_resolve_og_image( (int) $post->ID );

	// --- PRINTING THE META TAGS TO THE PAGE ---
	echo "\n\n";
	echo '<meta property="og:type" content="article" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

	echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '" />' . "\n";

	// Only declare dimensions if we actually know them — avoids lying
	// to the social network (which adjusts the crop based on them)
	if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
		echo '<meta property="og:image:width" content="' . (int) $image['width'] . '" />' . "\n";
		echo '<meta property="og:image:height" content="' . (int) $image['height'] . '" />' . "\n";
	}

	// --- Twitter Cards ---
	// Twitter/X falls back to og:* when it can't find twitter:*, but declaring
	// it explicitly ensures consistent render and unlocks `summary_large_image`
	// as the card type (Twitter doesn't infer this from og:image).
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '" />' . "\n";

	// twitter:site — the site's global handle, extracted from the URL configured in
	// Panel → Social Networks → Twitter (X). Accepts x.com/user or
	// twitter.com/user. Omits it if the URL doesn't match the pattern.
	$site_handle = rd_seo_extract_twitter_handle( (string) rd_get_option( 'social_twitter' ) );
	if ( ! empty( $site_handle ) ) {
		echo '<meta name="twitter:site" content="' . esc_attr( $site_handle ) . '" />' . "\n";
	}

	// twitter:creator — the post author's personal handle. Read from a user meta
	// `twitter_handle` that doesn't yet have a UI in the WP profile (future feature).
	// When the field is added, this block starts emitting the tag
	// automatically without needing to touch anything here.
	//
	// Fallback: if the author has no own handle, use the global `twitter:site`.
	// For a single-author blog (or small team) this is correct in practice — the
	// site handle AND the author are the same person.
	$author_handle = trim( (string) get_the_author_meta( 'twitter_handle', (int) $post->post_author ) );
	if ( ! empty( $author_handle ) ) {
		// Ensure the @ at the start (admin may fill in "user" or "@user")
		if ( strpos( $author_handle, '@' ) !== 0 ) {
			$author_handle = '@' . ltrim( $author_handle, '@' );
		}
	} elseif ( ! empty( $site_handle ) ) {
		$author_handle = $site_handle;
	}

	if ( ! empty( $author_handle ) ) {
		echo '<meta name="twitter:creator" content="' . esc_attr( $author_handle ) . '" />' . "\n";
	}

	echo "\n";
}
add_action( 'wp_head', 'rd_add_open_graph_tags', 5 );

/*******************************************************************************
 * Canonical URLs                                                      - (SEO) *
 *                                                                             *
 * Native WP (`rel_canonical`) only prints <link rel="canonical"> on singular  *
 * pages (post, page, CPT). Our version extends it to all indexable            *
 * surfaces:                                                                   *
 *                                                                             *
 *   - Home / main blog                                                        *
 *   - Category, tag, custom taxonomy archives                                 *
 *   - Author archive                                                          *
 *   - Date archives (year / month / day)                                      *
 *   - Search page (`?s=`)                                                     *
 *   - Paginated pages (`/page/N/`) preserved in any context                   *
 *                                                                             *
 * Skips 404 and feeds — they don't need their own canonical.                  *
 *                                                                             *
 * No panel option: canonical is a standard SEO signal with no trade-off,      *
 * turning it on/off makes no sense.                                           *
 *******************************************************************************/
function rd_add_canonical_url() {
	// 404 and feeds don't get a canonical
	if ( is_404() || is_feed() ) {
		return;
	}

	$canonical = '';

	if ( is_singular() ) {
		// wp_get_canonical_url() already handles internal pagination (?cpage=N) and the
		// correct protocol. Degrades gracefully outside the loop.
		$canonical = wp_get_canonical_url();
	} elseif ( is_front_page() || is_home() ) {
		$canonical = home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$canonical = get_term_link( $term );
		}
	} elseif ( is_author() ) {
		$canonical = get_author_posts_url( (int) get_queried_object_id() );
	} elseif ( is_year() ) {
		$canonical = get_year_link( get_query_var( 'year' ) );
	} elseif ( is_month() ) {
		$canonical = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
	} elseif ( is_day() ) {
		$canonical = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
	} elseif ( is_search() ) {
		$canonical = home_url( '/?s=' . rawurlencode( get_search_query() ) );
	}

	// Pagination on archives: appends /page/N/ (doesn't apply to singular —
	// `wp_get_canonical_url` already did that above).
	if ( ! empty( $canonical ) && ! is_singular() && ! is_search() ) {
		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
		}
	}

	if ( empty( $canonical ) || is_wp_error( $canonical ) ) {
		return;
	}

	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
}

// Remove WP's native canonical (covers singular only) to use ours (covers everything)
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', 'rd_add_canonical_url', 1 );

/*******************************************************************************
 * Meta Description default                                            - (SEO) *
 *                                                                             *
 * Prints <meta name="description"> on all indexable surfaces.                 *
 * Google still reads this tag to build the snippet when it's more relevant    *
 * than the content extracted from the page.                                   *
 *                                                                             *
 *   - Singular: the post's excerpt (helper shared with OG)                    *
 *   - Home/Blog: the site tagline (General Settings → Tagline)                *
 *   - Category/Tag/Tax: the term description, with a generic fallback         *
 *   - Author: the author's bio ("description" field), with a generic fallback *
 *   - Date archives: a formatted label like "Posts from May 2026"             *
 *   - Search and 404: skipped (dynamic/non-existent page)                     *
 *                                                                             *
 * No panel option: meta description is an SEO baseline, no trade-off.         *
 *******************************************************************************/
function rd_add_meta_description() {
	if ( is_404() || is_search() || is_feed() ) {
		return;
	}

	$description = '';

	if ( is_singular() ) {
		global $post;
		if ( $post instanceof WP_Post ) {
			$description = rd_seo_resolve_post_description( $post );
		}
	} elseif ( is_front_page() || is_home() ) {
		$description = get_bloginfo( 'description' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$term_desc = wp_strip_all_tags( term_description( $term ) );
			if ( ! empty( $term_desc ) ) {
				$description = $term_desc;
			} else {
				/* translators: %s: category/tag/taxonomy term name */
				$description = sprintf( __( 'Posts in %s.', 'reloaded' ), $term->name );
			}
		}
	} elseif ( is_author() ) {
		$author_id  = (int) get_queried_object_id();
		$author_bio = wp_strip_all_tags( get_the_author_meta( 'description', $author_id ) );
		if ( ! empty( $author_bio ) ) {
			$description = $author_bio;
		} else {
			/* translators: %s: author display name */
			$description = sprintf( __( 'Posts by %s.', 'reloaded' ), get_the_author_meta( 'display_name', $author_id ) );
		}
	} elseif ( is_year() || is_month() || is_day() ) {
		// The same "Archive of posts from %s." string used in 3 contexts with
		// different date formats — a single, generic translators comment
		// covers all 3 (avoids the "different translator comments" warning
		// from `wp i18n make-pot` when the same string has divergent hints).
		if ( is_year() ) {
			$period = get_query_var( 'year' );
		} elseif ( is_month() ) {
			// Builds a localized "F Y" from the query vars (WP's single_month_title
			// uses the arg as a duplicated prefix, generating an extra space).
			$ts     = mktime( 0, 0, 0, (int) get_query_var( 'monthnum' ), 1, (int) get_query_var( 'year' ) );
			$period = date_i18n( 'F Y', $ts );
		} else { // is_day()
			// Same logic as month — query vars instead of get_the_date() (which
			// would take the first post's date in the loop, not the archive's).
			$ts     = mktime( 0, 0, 0, (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ), (int) get_query_var( 'year' ) );
			$period = date_i18n( get_option( 'date_format' ), $ts );
		}
		/* translators: %s: archive period (year like "2026", month like "May 2026", or full date like "May 15, 2026") */
		$description = sprintf( __( 'Archive of posts from %s.', 'reloaded' ), $period );
	}

	$description = trim( wp_strip_all_tags( $description ) );
	if ( empty( $description ) ) {
		return;
	}

	// Truncate at ~160 characters to stay within Google's snippet limit
	if ( mb_strlen( $description ) > 160 ) {
		$description = mb_substr( $description, 0, 157 ) . '...';
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'rd_add_meta_description', 2 );

/*******************************************************************************
 * Resolve o logo do site pra uso em Schema.org (publisher.logo)       - (SEO) *
 *                                                                             *
 * Order of preference:                                                        *
 *   1. Custom Logo (Appearance → Customize → Site Identity)                   *
 *   2. The theme's default logo (assets/img/logo-reloaded-panel.webp)         *
 *                                                                             *
 * Always returns ['url', 'width', 'height']. For the theme fallback the       *
 * dimensions go null (Google accepts a dimensionless image for publisher).    *
 *******************************************************************************/
function rd_seo_resolve_site_logo(): array {
	// Delega pro helper centralizado em core.php (DRY com mod-maintenance,
	// mod-security e mod-integrations). Schema.org Organization logo usa 'full'
	// pra ter o tamanho original (Google prefere imagens grandes pro Knowledge
	// Panel — recommendation is minimum 112x112, ideal 600x600+).
	if ( function_exists( 'rd_get_site_logo' ) ) {
		return rd_get_site_logo( 'full' );
	}

	// Defensive fallback: if core.php didn't load (unlikely), serve directly
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp',
		'width'  => 430,
		'height' => 100,
	);
}

/*******************************************************************************
 * Imprime um bloco <script type="application/ld+json">                - (SEO) *
 *                                                                             *
 * Internal helper: serializes to JSON with HTML-safe flags and wraps it in    *
 * a <script>. Uses JSON_UNESCAPED_SLASHES and JSON_UNESCAPED_UNICODE to       *
 * preserve URLs and accents readable in the source — Google parses either,    *
 * but readable is easier to debug.                                            *
 *                                                                             *
 *
 * @param array $data Schema.org structure ready to serialize.                 *
 *******************************************************************************/
function rd_seo_print_jsonld( array $data ): void {
	$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return;
	}
	$nonce_attr = rd_csp_nonce_attr();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD pattern: wp_json_encode produces safe JSON output for <script type="application/ld+json"> blocks (HTML entity escaping would break valid JSON parsing by search engines). Nonce attribute already escaped by rd_csp_nonce_attr().
	echo '<script type="application/ld+json"' . $nonce_attr . '>' . $json . '</script>' . "\n";
}

/*******************************************************************************
 * Schema.org JSON-LD                                                  - (SEO) *
 *                                                                             *
 * Emits Schema.org structures in <head> so search engines understand          *
 * the page content in a structured way. Implemented per context:              *
 *                                                                             *
 *   - Singles (is_single): Article with headline, description, image, dates,  *
 *     author (Person), publisher (Organization w/ logo) and mainEntityOfPage. *
 *                                                                             *
 *   - Home/Front (is_front_page): WebSite with SearchAction — enables the     *
 *     Google "sitelinks search box" in search results.                        *
 *                                                                             *
 * BreadcrumbList will come along with H7 (visible breadcrumbs).               *
 *                                                                             *
 * No panel option — Schema is a technical SEO baseline, no trade-off.         *
 *******************************************************************************/
function rd_add_schema_jsonld() {
	// --- Article (posts only, not static pages) ---
	if ( is_single() ) {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$image       = rd_seo_resolve_og_image( (int) $post->ID );
		$logo        = rd_seo_resolve_site_logo();
		$author_id   = (int) $post->post_author;
		$description = rd_seo_resolve_post_description( $post );

		// Person with an @id that matches the Person declared in author.php — Google
		// ties the two schemas as the SAME Person (E-E-A-T between Article and
		// author profile). Includes image (Gravatar avatar) to feed the
		// "author info" in the Rich Results panel. Falls back to
		// user_nicename/user_login if display_name is empty.
		$author_display  = get_the_author_meta( 'display_name', $author_id );
		$author_nicename = get_the_author_meta( 'user_nicename', $author_id );
		$author_name     = ! empty( $author_display )
			? $author_display
			: ( ! empty( $author_nicename ) ? $author_nicename : get_the_author_meta( 'user_login', $author_id ) );

		$article = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
			'description'      => $description,
			'image'            => $image['url'],
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => array_filter(
				array(
					'@type' => 'Person',
					'@id'   => get_author_posts_url( $author_id ) . '#person',
					'name'  => $author_name,
					'url'   => get_author_posts_url( $author_id ),
					'image' => get_avatar_url( $author_id, array( 'size' => 240 ) ),
				)
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'logo'  => array_filter(
					array(
						'@type'  => 'ImageObject',
						'url'    => $logo['url'],
						'width'  => $logo['width'],
						'height' => $logo['height'],
					)
				),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
		);

		rd_seo_print_jsonld( $article );
		return;
	}

	// --- WebSite (home/front page only) ---
	if ( is_front_page() || is_home() ) {
		$website = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'description'     => get_bloginfo( 'description' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		rd_seo_print_jsonld( $website );
	}
}
add_action( 'wp_head', 'rd_add_schema_jsonld', 5 );

/*******************************************************************************
 * Custom robots.txt                                                   - (SEO) *
 *                                                                             *
 * Completely replaces the output generated by WordPress's `do_robots()`       *
 * when the admin fills the field in the panel. Empty = WP default behavior    *
 * preserved (User-agent, Disallow /wp-admin/, Allow admin-ajax, Sitemap).     *
 *                                                                             *
 * Uses the native `robots_txt($output, $public)` filter:                      *
 *   - $output: current content generated by WP                                *
 *   - $is_public: true if Settings → Reading → "Discourage search engines" OFF*
 *                                                                             *
 * Defensive sanitization: strip HTML tags (admins shouldn't put HTML          *
 * in robots.txt, but wp_kses_post on save already ensures this). Trims an     *
 * invisible BOM and residual \r to avoid "invalid syntax" in strict parsers.  *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $is_public is part of the `robots_txt` filter signature, even when the custom output is independent of the "Discourage search engines" config.
function rd_custom_robots_txt( $output, $is_public ) {
	$custom = (string) rd_get_option( 'custom_robots_txt' );

	if ( trim( $custom ) === '' ) {
		return $output; // keeps WP's default
	}

	// Remove the invisible UTF-8 BOM (some editors save with \xEF\xBB\xBF at the start)
	$custom = preg_replace( '/^\xEF\xBB\xBF/', '', $custom );

	// Normalize line endings (CRLF/CR → LF) — some validators complain about \r
	$custom = str_replace( array( "\r\n", "\r" ), "\n", $custom );

	// Defensive trim + ensures a trailing newline (strict parsers require it)
	$custom = rtrim( $custom ) . "\n";

	return $custom;
}
add_filter( 'robots_txt', 'rd_custom_robots_txt', 10, 2 );

/*******************************************************************************
 * Control of the native WP 5.5+ Sitemap                               - (SEO) *
 *                                                                             *
 * 3 levels of control, all via native WP filters:                             *
 *                                                                             *
 *   - enable_sitemap (global toggle): disables the entire /wp-sitemap.xml     *
 *     for those using an external SEO plugin (Yoast, RankMath) with its own   *
 *                                                                             *
 *   - sitemap_include_authors: removes the /wp-sitemap-users-*.xml sub-sitemap*
 *     Default OFF — solo blogs rarely expose /author/* (duplicates home)      *
 *                                                                             *
 *   - sitemap_include_cpt: keeps only post + page in the sitemap when OFF.    *
 *     Useful to hide internal-use CPTs (widgets, settings) from the index     *
 */

// 1. Global toggle — disables the entire system
add_filter(
	'wp_sitemaps_enabled',
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $enabled is part of the filter signature; we ignore the default and return only what's in the panel.
	function ( $enabled ) {
		return rd_get_option_bool( 'enable_sitemap' );
	}
);

// 2. Exclude the Authors provider when disabled
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		if ( $name === 'users' && ! rd_get_option_bool( 'sitemap_include_authors' ) ) {
			return false; // removes the entire sub-sitemap
		}
		return $provider;
	},
	10,
	2
);

// 3. Filter post types — when CPT is OFF, keep only the builtin ones
add_filter(
	'wp_sitemaps_post_types',
	function ( $post_types ) {
		if ( rd_get_option_bool( 'sitemap_include_cpt' ) ) {
			return $post_types; // keeps all (post, page, and CPTs)
		}
		// Keeps only post + page (the builtin ones), discards CPTs
		$allowed = array( 'post', 'page' );
		return array_intersect_key( $post_types, array_flip( $allowed ) );
	}
);
