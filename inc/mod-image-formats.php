<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Image Formats (WebP/AVIF) — Next-gen image delivery                 *
 *                                                                             *
 * Automatically generates WebP and/or AVIF versions of every JPEG/PNG/WebP    *
 * image on upload (all WP sizes + the theme's custom sizes). Delivers via     *
 * <picture> with transparent fallback to the original.                        *
 *                                                                             *
 * Server requirements (degrades gracefully — no hard dependency):             *
 *   - Imagick + ImageMagick with WEBP/AVIF (preferred, superior quality)      *
 *   - GD with WebP (PHP 7.1+) — fallback                                      *
 *   - GD with AVIF (PHP 8.1+) — fallback (only if ImageMagick lacks it)       *
 *   Neither one with WebP/AVIF → module dormant, shows a notice in the panel  *
 *                                                                             *
 * Philosophy:                                                                 *
 *   - Original (JPEG/PNG) always preserved — next-gen are additions           *
 *   - Browser picks a compatible <source type>, falls back to original <img>  *
 *   - AVIF before WebP in <picture> (better compression for those who support)*
 *                                                                             *
 * Coverage:                                                                   *
 *   ✅ Featured images, post thumbnails, post-card, galleries via              *
 *      wp_get_attachment_image (rd_img_wrap_in_picture)                       *
 *   ✅ Inline images in post content — Gutenberg core/image, Markdown, raw     *
 *      HTML (rd_img_wrap_content_images)                                      *
 ******************************************************************************/

const RD_IMG_REGEN_CHUNK = 10;

/**
 * Centralized wrapper for the module defaults — passes an explicit default on
 * each call to the generic rd_get_option helper. Needed for new options
 * added AFTER the theme's first activation (rd_set_default_options only
 * runs on after_switch_theme), preventing features from silently going
 * dormant because the key doesn't exist yet in the database's rd_settings.
 */
function rd_img_is_enabled(): bool {
	return (int) rd_get_option( 'enable_next_gen_images', 1 ) === 1;
}

function rd_img_get_mode(): string {
	$mode = rd_get_option( 'image_format_mode', 'avif' );
	return in_array( $mode, array( 'avif', 'webp', 'both' ), true ) ? $mode : 'avif';
}

/**
 * Quality unified with the panel's global config (`jpeg_quality` in
 * General Settings). The same value is applied to JPEG (via WP filter in
 * mod-general.php) and to the WebP we generate here. Default 80 if the key
 * isn't in rd_settings or if the value is invalid. Defensive 1-100 clamp.
 */
function rd_img_get_quality(): int {
	$quality = (int) rd_get_option( 'jpeg_quality', 80 );
	return max( 1, min( 100, $quality ) );
}

/**
 * Per-format quality. The 0-100 scale is NOT portable across codecs: AVIF at
 * the JPEG's q80 produces bloated files (PageSpeed flagged a 139 KiB AVIF
 * that q55-60 encodes visually identical at ~50-60 KiB). JPEG/WebP keep the
 * panel value; AVIF gets a -20 offset with a floor of 45 — tracks the admin's
 * quality intent while staying in AVIF's efficient range.
 */
function rd_img_get_quality_for( string $format ): int {
	$quality = rd_img_get_quality();
	if ( 'avif' === $format ) {
		return max( 45, $quality - 20 );
	}
	return $quality;
}

/*
=============================================================================
 *  CAPABILITY DETECTION
 * ============================================================================= */

/**
 * Detects what the server supports. Static cache within the request.
 */
function rd_img_get_capabilities(): array {
	static $caps = null;
	if ( $caps !== null ) {
		return $caps;
	}

	$caps = array(
		'imagick'      => extension_loaded( 'imagick' ),
		'webp_imagick' => false,
		'avif_imagick' => false,
		'webp_gd'      => function_exists( 'imagewebp' ),
		'avif_gd'      => function_exists( 'imageavif' ),
	);

	if ( $caps['imagick'] ) {
		try {
			$formats              = ( new Imagick() )->queryFormats();
			$caps['webp_imagick'] = in_array( 'WEBP', $formats, true );
			$caps['avif_imagick'] = in_array( 'AVIF', $formats, true );
		} catch ( Exception $e ) {
			unset( $e ); // Imagick available but couldn't list formats — stays on fallback (GD).
		}
	}

	return $caps;
}

function rd_img_can_generate( string $format ): bool {
	$caps = rd_img_get_capabilities();
	if ( $format === 'webp' ) {
		return $caps['webp_imagick'] || $caps['webp_gd'];
	}
	if ( $format === 'avif' ) {
		return $caps['avif_imagick'] || $caps['avif_gd'];
	}
	return false;
}

/*
=============================================================================
 *  CONVERSION ENGINE
 * ============================================================================= */

/**
 * Converts 1 file (JPEG/PNG) to WebP or AVIF.
 * Prefers Imagick (superior quality), GD fallback.
 *
 * @return string|false Path of the generated file or false on failure
 */
function rd_img_convert( string $source_path, string $target_format, ?int $quality = null ) {
	// Quality default: per-format value derived from the panel config (AVIF
	// gets its own scale — see rd_img_get_quality_for). Override via parameter
	// allows programmatic use with a specific value.
	if ( $quality === null ) {
		$quality = rd_img_get_quality_for( $target_format );
	}
	if ( ! file_exists( $source_path ) ) {
		return false;
	}
	if ( ! in_array( $target_format, array( 'webp', 'avif' ), true ) ) {
		return false;
	}

	$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
		return false;
	}

	$target_path = preg_replace( '/\.(jpe?g|png|webp)$/i', '.' . $target_format, $source_path );
	if ( $target_path === $source_path ) {
		return false; // same-format conversion (webp → webp) — nothing to do
	}

	$caps = rd_img_get_capabilities();

	// Engine decision by format + source:
	// - WebP from JPEG/PNG → Imagick (superior quality, alpha OK)
	// - AVIF from JPEG → Imagick (superior quality, JPEG has no alpha)
	// - AVIF from PNG or WebP → SKIP Imagick, go straight to GD
	// Reason: ImageMagick 6 + libheif has a historical bug that replaces
	// transparent backgrounds with black when generating AVIF. ImageMagick 7 may
	// fix it but it depends on the exact server version. Portable solution: for
	// sources that potentially carry alpha (PNG, WebP) use GD, which has native +
	// correct support for AVIF with transparency (PHP 8.1+). Animated WebP is a
	// non-issue here: GD can't load it, the conversion fails gracefully and the
	// original keeps being served as-is.
	// Ref: https://alexwlchan.net/2023/check-for-transparency/
	$force_gd_for_avif = ( $target_format === 'avif' && in_array( $ext, array( 'png', 'webp' ), true ) );

	// 1st attempt: Imagick (skip if PNG → AVIF)
	if ( ! $force_gd_for_avif && $caps['imagick'] && $caps[ $target_format . '_imagick' ] ) {
		try {
			$img = new Imagick( $source_path );

			// Preserve alpha channel (transparency) — needed for WebP from a
			// transparent PNG. Without it Imagick drops the alpha and the background
			// turns black/white (non-deterministic behavior).
			$img->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
			$img->setBackgroundColor( new ImagickPixel( 'transparent' ) );

			if ( defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
				$img->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
			}

			$img->setImageFormat( strtoupper( $target_format ) );
			$img->setImageCompressionQuality( $quality );

			// AVIF: heic:speed balances speed vs quality (0=slow+better, 10=fast+worse)
			if ( $target_format === 'avif' ) {
				$img->setOption( 'heic:speed', '6' );
			}

			$img->writeImage( $target_path );
			$img->clear();
			$img->destroy();
			rd_img_conversion_stats( 'converted' );
			return $target_path;
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- encoder failure log for debugging, gated by WP_DEBUG per the wp.org guideline.
				error_log( 'rd_img_convert: Imagick failed for ' . $source_path . ' → ' . $target_format . ': ' . $e->getMessage() );
			}
			// continue to the GD fallback
		}
	}

	// 2nd attempt: GD fallback
	if ( $caps[ $target_format . '_gd' ] ) {
		$img = null;
		if ( $ext === 'jpg' || $ext === 'jpeg' ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- source file may be corrupt/partial; we prefer a silent null over the warning (we check `! $img` below).
			$img = @imagecreatefromjpeg( $source_path );
		} elseif ( $ext === 'png' ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- same reason as above.
			$img = @imagecreatefrompng( $source_path );
			if ( $img ) {
				// Transparent PNG — preserve the alpha channel in WebP/AVIF.
				// imagepalettetotruecolor: converts paletted PNG-8 to TrueColor
				// (needed for the alpha channel to work).
				// imagealphablending(false): REPLACE mode (colors don't blend with alpha) —
				// essential to PRESERVE alpha on save. If `true` (default
				// blending mode), GD would flatten against the current background.
				// imagesavealpha(true): tells GD to serialize the alpha channel
				// in the output. Without it, alpha is lost on save.
				imagepalettetotruecolor( $img );
				imagealphablending( $img, false );
				imagesavealpha( $img, true );
			}
		} elseif ( $ext === 'webp' && function_exists( 'imagecreatefromwebp' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- animated WebP makes imagecreatefromwebp fail with a warning; we prefer the silent null (checked below) and keep serving the original.
			$img = @imagecreatefromwebp( $source_path );
			if ( $img ) {
				// WebP may carry alpha — same preservation dance as PNG
				// (already TrueColor, no palette conversion needed).
				imagealphablending( $img, false );
				imagesavealpha( $img, true );
			}
		}
		if ( ! $img ) {
			rd_img_conversion_stats( 'failed' );
			return false;
		}

		$func = 'image' . $target_format;
		if ( ! function_exists( $func ) ) {
			imagedestroy( $img );
			rd_img_conversion_stats( 'failed' );
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- imagewebp/imageavif emit a warning on encode/write failure; we check $success in the `return` below.
		$success = @$func( $img, $target_path, $quality );
		imagedestroy( $img );
		rd_img_conversion_stats( $success ? 'converted' : 'failed' );
		return $success ? $target_path : false;
	}

	// No capable engine for this format — counts as a failure so the regen
	// summary surfaces it (otherwise it dies silently in production).
	rd_img_conversion_stats( 'failed' );
	return false;
}

/**
 * Per-request conversion counters — gives the regeneration flow (and the
 * panel's "last run" summary) visibility into silent failures that previously
 * only surfaced in the debug log with WP_DEBUG on.
 *
 * @param string $op 'get' (default) returns the counters; 'reset' zeroes
 *                   them; 'converted'/'failed' bumps that counter.
 * @return array{converted:int,failed:int} Current counters.
 */
function rd_img_conversion_stats( string $op = 'get' ): array {
	static $stats = array(
		'converted' => 0,
		'failed'    => 0,
	);

	if ( 'reset' === $op ) {
		$stats = array(
			'converted' => 0,
			'failed'    => 0,
		);
	} elseif ( isset( $stats[ $op ] ) ) {
		++$stats[ $op ];
	}

	return $stats;
}

/*
=============================================================================
 *  AUTO-GENERATION ON UPLOAD
 * ============================================================================= */

/**
 * Lists absolute paths of all sizes of an attachment (original + sizes).
 */
function rd_img_get_attachment_paths( array $metadata ): array {
	$upload_dir = wp_upload_dir();
	$base_dir   = trailingslashit( $upload_dir['basedir'] );
	$paths      = array();

	if ( empty( $metadata['file'] ) ) {
		return $paths;
	}

	$main_path = $base_dir . $metadata['file'];
	$paths[]   = $main_path;

	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		$base_path = trailingslashit( dirname( $main_path ) );
		foreach ( $metadata['sizes'] as $size_data ) {
			if ( ! empty( $size_data['file'] ) ) {
				$paths[] = $base_path . $size_data['file'];
			}
		}
	}

	return $paths;
}

/**
 * Hook on wp_generate_attachment_metadata — generates next-gen for all sizes
 * automatically at the end of the upload.
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $attachment_id is part of the `wp_generate_attachment_metadata` filter signature, even when we only consume $metadata.
function rd_img_generate_on_upload( $metadata, $attachment_id ) {
	if ( ! rd_img_is_enabled() ) {
		return $metadata;
	}
	if ( ! is_array( $metadata ) ) {
		return $metadata;
	}

	$mode = rd_img_get_mode();

	$do_webp = ( $mode === 'both' || $mode === 'webp' ) && rd_img_can_generate( 'webp' );
	$do_avif = ( $mode === 'both' || $mode === 'avif' ) && rd_img_can_generate( 'avif' );

	if ( ! $do_webp && ! $do_avif ) {
		return $metadata;
	}

	$files = rd_img_get_attachment_paths( $metadata );

	foreach ( $files as $file ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			continue;
		}

		// webp → webp is a no-op (rd_img_convert's same-path guard), so WebP
		// uploads only produce the AVIF twin — exactly what we want.
		if ( $do_webp ) {
			rd_img_convert( $file, 'webp' );
		}
		if ( $do_avif ) {
			rd_img_convert( $file, 'avif' );
		}
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'rd_img_generate_on_upload', 10, 2 );

/*
=============================================================================
 *  PICTURE WRAP — <img> → <picture><source>...<img></picture>
 *
 *  2 coverage scenarios:
 *
 *  1. wp_get_attachment_image() — used by featured images, post-thumbnails,
 *     WP galleries, custom-logo, etc. Filter rd_img_wrap_in_picture() captures it.
 *
 *  2. <img> directly in post content (Gutenberg block core/image, processed
 *     Markdown, raw HTML). Filter rd_img_wrap_content_images() captures it via
 *     DOMDocument parsing of the_content.
 *
 *  Shared helper rd_img_get_nextgen_sources_for_url() does the heavy
 *  lifting: checks extension, resolves path, finds next-gen on the filesystem,
 *  returns the HTML of the <source> tags. Single source of truth.
 * ============================================================================= */

/**
 * Shared helper — for an image URL, finds which next-gen formats
 * exist on the filesystem and returns structured data.
 *
 * Returns an empty array if:
 *   - URL is external (outside wp_upload_dir)
 *   - Extension isn't jpg/jpeg/png
 *   - No next-gen file exists on disk
 *
 * Structured return (instead of an HTML string) so that two consumers
 * can build output appropriate to their context:
 *   - rd_img_wrap_in_picture()       → string concat (HTML5 void <source>)
 *   - rd_img_wrap_content_images()   → DOMDocument createElement
 *
 * An HTML string didn't work for both — DOMNode's appendXML requires strict XML
 * (self-closing `<source />`), incompatible with HTML5 (`<source>` without `/>`).
 *
 * @param string $url Absolute URL of the original image.
 * @return array List of sources [['url' => '...', 'type' => 'image/avif'], ...]
 *               ordered AVIF → WebP (browser picks the first compatible one).
 */
function rd_img_get_nextgen_sources_for_url( string $url ): array {
	$mode    = rd_img_get_mode();
	$sources = array();

	// AVIF first — better compression for browsers that support it
	if ( ( $mode === 'both' || $mode === 'avif' ) && rd_img_can_generate( 'avif' ) ) {
		$avif_url = rd_img_get_variant_url( $url, 'avif' );
		if ( null !== $avif_url ) {
			$sources[] = array(
				'url'  => $avif_url,
				'type' => 'image/avif',
			);
		}
	}

	// WebP second
	if ( ( $mode === 'both' || $mode === 'webp' ) && rd_img_can_generate( 'webp' ) ) {
		$webp_url = rd_img_get_variant_url( $url, 'webp' );
		if ( null !== $webp_url ) {
			$sources[] = array(
				'url'  => $webp_url,
				'type' => 'image/webp',
			);
		}
	}

	return $sources;
}

/**
 * Resolves the next-gen variant URL for ONE original image URL.
 *
 * Returns null when the URL is empty/external (outside wp_upload_dir), the
 * extension isn't jpg/jpeg/png, or the variant file doesn't exist on disk —
 * callers drop the candidate and the browser falls back to the original.
 *
 * @param string $url    Absolute URL of the original image.
 * @param string $format 'avif' or 'webp'.
 * @return string|null URL of the existing variant, or null.
 */
function rd_img_get_variant_url( string $url, string $format ): ?string {
	if ( '' === $url ) {
		return null;
	}

	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
		return null;
	}

	// Resolve absolute path to check existence of the next-gen file
	$upload_dir = wp_upload_dir();
	$base_url   = trailingslashit( $upload_dir['baseurl'] );
	$base_dir   = trailingslashit( $upload_dir['basedir'] );

	// URL outside uploads (external CDN, etc) — passes through transparently
	if ( strpos( $url, $base_url ) !== 0 ) {
		return null;
	}

	$abs_path     = $base_dir . substr( $url, strlen( $base_url ) );
	$variant_path = preg_replace( '/\.(jpe?g|png|webp)$/i', '.' . $format, $abs_path );
	if ( $variant_path === $abs_path ) {
		return null; // webp "variant" of a webp original — the <img> already serves it
	}
	if ( ! file_exists( $variant_path ) ) {
		return null;
	}

	return preg_replace( '/\.(jpe?g|png|webp)$/i', '.' . $format, $url );
}

/**
 * Builds next-gen srcset strings mirroring an <img>'s srcset attribute.
 *
 * THE key piece of responsive next-gen delivery: a <source> with a single
 * URL (the old behavior) makes every AVIF/WebP-capable browser ignore the
 * <img>'s responsive srcset entirely and download that one fixed size —
 * PageSpeed flagged 1200x675 AVIFs being served into ~630px slots. Mirroring
 * the full candidate list (each URL swapped to the next-gen extension, same
 * width descriptors) restores the browser's size selection.
 *
 * Candidates whose next-gen file doesn't exist on disk are dropped from that
 * format's list (partial regeneration degrades gracefully).
 *
 * @param string $srcset       The <img> srcset attribute value ('' if absent).
 * @param string $fallback_url Used as a single candidate when $srcset is empty.
 * @return array Map of MIME type => srcset string ('image/avif' first).
 *               Formats with no existing variant are omitted.
 */
function rd_img_get_nextgen_srcsets( string $srcset, string $fallback_url ): array {
	$candidates = array();
	if ( '' !== trim( $srcset ) ) {
		foreach ( explode( ',', $srcset ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( '' === $candidate ) {
				continue;
			}
			// "url 600w" → URL + width descriptor (descriptor may be absent)
			$parts        = preg_split( '/\s+/', $candidate, 2 );
			$candidates[] = array(
				'url'  => $parts[0],
				'desc' => isset( $parts[1] ) ? $parts[1] : '',
			);
		}
	} elseif ( '' !== $fallback_url ) {
		$candidates[] = array(
			'url'  => $fallback_url,
			'desc' => '',
		);
	}
	if ( empty( $candidates ) ) {
		return array();
	}

	$mode    = rd_img_get_mode();
	$formats = array();
	if ( ( $mode === 'both' || $mode === 'avif' ) && rd_img_can_generate( 'avif' ) ) {
		$formats['image/avif'] = 'avif';
	}
	if ( ( $mode === 'both' || $mode === 'webp' ) && rd_img_can_generate( 'webp' ) ) {
		$formats['image/webp'] = 'webp';
	}

	$srcsets = array();
	foreach ( $formats as $type => $format ) {
		$entries = array();
		foreach ( $candidates as $candidate ) {
			$variant = rd_img_get_variant_url( $candidate['url'], $format );
			if ( null !== $variant ) {
				$entries[] = trim( $variant . ' ' . $candidate['desc'] );
			}
		}
		if ( ! empty( $entries ) ) {
			$srcsets[ $type ] = implode( ', ', $entries );
		}
	}

	return $srcsets;
}

/**
 * Generic helper — wraps a raw <img> HTML in <picture> if there are next-gen
 * versions on the filesystem. For contexts that render <img> directly instead
 * of via wp_get_attachment_image() (custom logos in the Discord facade,
 * maintenance screen, WSOD, etc.).
 *
 * The caller is responsible for building $img_html — this helper only decides
 * whether to wrap it in <picture> or return it as-is.
 *
 * @param string $url      Absolute URL of the original image (used to resolve
 *                         the path and find next-gen files on disk).
 * @param string $img_html Full HTML of the <img> tag to wrap.
 * @return string Original $img_html OR '<picture>...<source>...$img_html</picture>'.
 */
function rd_img_wrap_url_in_picture( string $url, string $img_html ): string {
	if ( ! rd_img_is_enabled() ) {
		return $img_html;
	}

	$sources = rd_img_get_nextgen_sources_for_url( $url );
	if ( empty( $sources ) ) {
		return $img_html;
	}

	$sources_html = '';
	foreach ( $sources as $source ) {
		$sources_html .= '<source srcset="' . esc_url( $source['url'] ) . '" type="' . esc_attr( $source['type'] ) . '">';
	}

	return '<picture>' . $sources_html . $img_html . '</picture>';
}

/**
 * Filter on wp_get_attachment_image — wraps <img> in <picture> with a <source>
 * for each available next-gen format.
 *
 * AVIF comes before WebP — browsers use the first compatible <source>.
 * A browser without support falls back to the original <img> (JPEG/PNG).
 *
 * Coverage: featured images, post thumbnails, post-card, galleries.
 * Inline content images are covered by rd_img_wrap_content_images().
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $icon and $attr are part of the `wp_get_attachment_image` filter signature, even when unused.
function rd_img_wrap_in_picture( $html, $attachment_id, $size, $icon, $attr ) {
	if ( ! rd_img_is_enabled() ) {
		return $html;
	}
	if ( empty( $html ) ) {
		return $html;
	}

	// Mirror the <img>'s responsive attributes into the <source> tags. WP
	// builds the tag with double-quoted attributes — safe to capture directly.
	$srcset = '';
	$sizes  = '';
	if ( preg_match( '/\ssrcset="([^"]*)"/', $html, $m ) ) {
		$srcset = wp_specialchars_decode( $m[1] );
	}
	if ( preg_match( '/\ssizes="([^"]*)"/', $html, $m ) ) {
		$sizes = wp_specialchars_decode( $m[1] );
	}

	$src      = wp_get_attachment_image_src( $attachment_id, $size );
	$fallback = $src ? $src[0] : '';

	$srcsets = rd_img_get_nextgen_srcsets( $srcset, $fallback );
	if ( empty( $srcsets ) ) {
		return $html;
	}

	$sources_html = '';
	foreach ( $srcsets as $type => $set ) {
		$sources_html .= '<source srcset="' . esc_attr( $set ) . '"'
			. ( '' !== $sizes ? ' sizes="' . esc_attr( $sizes ) . '"' : '' )
			. ' type="' . esc_attr( $type ) . '">';
	}

	return '<picture>' . $sources_html . $html . '</picture>';
}
add_filter( 'wp_get_attachment_image', 'rd_img_wrap_in_picture', 10, 5 );

/**
 * Filter on the_content — wraps inline <img> in <picture> with next-gen sources.
 *
 * Complements rd_img_wrap_in_picture() (which covers wp_get_attachment_image)
 * to cover DIRECT images in post content:
 *   - Gutenberg block core/image
 *   - raw <img> HTML in the editor
 *   - Markdown ![]() processed into <img> (Parsedown runs at priority 6)
 *
 * Priority 20: after Markdown (6) and wpautop (10). <img> tags are already
 * in final form when we get here.
 *
 * Performance: DOMDocument parsing is ~ms per typical post. Runs 1× per render.
 * In production with Redis Object Cache + page cache, cost amortized to zero.
 *
 * @param string $content HTML of the post content.
 * @return string Content with eligible <img> wrapped in <picture>.
 */
function rd_img_wrap_content_images( $content ) {
	if ( ! rd_img_is_enabled() ) {
		return $content;
	}

	// Fast path: no <img> in the content, avoids DOMDocument overhead
	if ( strpos( (string) $content, '<img' ) === false ) {
		return $content;
	}

	// DOMDocument for safe parsing — regex on HTML is far too fragile
	// (mixed-quote attributes, multi-line, encoding edge cases).
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();

	// UTF-8 trick: prepending an XML declaration forces loadHTML to treat input as UTF-8.
	// LIBXML_HTML_NOIMPLIED + LIBXML_HTML_NODEFDTD avoid wrapping in <html><body>.
	$loaded = $dom->loadHTML(
		'<?xml encoding="UTF-8">' . $content,
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();

	if ( ! $loaded ) {
		return $content;
	}

	$images       = $dom->getElementsByTagName( 'img' );
	$changes_made = false;

	// iterator_to_array for a snapshot — we'll modify the tree during iteration
	$images_array = iterator_to_array( $images );

	foreach ( $images_array as $img ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode property, not renameable.
		$parent = $img->parentNode;

		// Skip if already inside <picture> — avoids double-wrap (some blocks
		// or future plugins may produce <picture> manually).
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode property, not renameable.
		if ( $parent && 'picture' === $parent->nodeName ) {
			continue;
		}

		$src = $img->getAttribute( 'src' );
		if ( '' === $src ) {
			continue;
		}

		// Mirror the <img>'s srcset/sizes into each <source> — same rationale
		// as rd_img_wrap_in_picture(): a single-URL source disables the
		// browser's responsive size selection for next-gen formats.
		$srcsets = rd_img_get_nextgen_srcsets( $img->getAttribute( 'srcset' ), $src );
		if ( empty( $srcsets ) ) {
			continue;
		}
		$sizes = $img->getAttribute( 'sizes' );

		// Create <picture> and populate it with <source> via direct createElement.
		// (We tried appendXML before but DOMDocumentFragment requires strict XML —
		// HTML5 `<source>` without self-closing produced a "Document Fragment is empty"
		// warning. createElement + setAttribute is the correct API to build
		// new elements in the existing DOM tree.)
		$picture = $dom->createElement( 'picture' );
		foreach ( $srcsets as $type => $set ) {
			$source_el = $dom->createElement( 'source' );
			$source_el->setAttribute( 'srcset', $set );
			if ( '' !== $sizes ) {
				$source_el->setAttribute( 'sizes', $sizes );
			}
			$source_el->setAttribute( 'type', $type );
			$picture->appendChild( $source_el );
		}

		// Replace <img> with <picture>, then move <img> inside the <picture>
		$parent->replaceChild( $picture, $img );
		$picture->appendChild( $img );

		$changes_made = true;
	}

	if ( ! $changes_made ) {
		return $content;
	}

	// Extract the modified HTML. LIBXML_HTML_NOIMPLIED avoided wrapping in <html>/<body>,
	// but the XML declaration we prepended comes in the output — strip it.
	$new_content = $dom->saveHTML();
	$new_content = preg_replace( '/^<\?xml encoding="UTF-8">\s*/', '', $new_content );

	return $new_content;
}
add_filter( 'the_content', 'rd_img_wrap_content_images', 20 );

/*
=============================================================================
 *  FORCE CORRECT SRC — defensive fix for stale metadata
 * ============================================================================= */

/**
 * Defensive filter — forces the <img> `src` to point to the requested size when
 * the corresponding file EXISTS on the filesystem but the attachment metadata
 * doesn't list it in `image_meta['sizes']`.
 *
 * Scenario detected on 2026-05-21 (PageSpeed audit): after adding a
 * new `add_image_size` + running regenerate via wp_generate_attachment_metadata,
 * in some cases WP keeps serving the ORIGINAL (635x635) in the `<img src>`
 * instead of the requested size (240x240 of rd-qr) — even with the file generated
 * on the filesystem. The `srcset` is correct (lists the 240x240), but the `src`
 * falls back to the original, and the browser uses the `src` instead of computing
 * the best srcset candidate in some scenarios (especially with WP 6.7+ sizes="auto").
 *
 * How the filter works:
 *   1. Checks whether the requested size is a custom one registered via add_image_size
 *   2. Gets the expected width/height of that size
 *   3. Builds the expected file path (`-WxH.ext`)
 *   4. If the file exists on the filesystem BUT the current src points to the original,
 *      forces the src + dimensions to the correct size
 *
 * Only acts when the file exists — if the size really wasn't generated, lets
 * WP serve the original (fail-safe).
 */
function rd_img_force_correct_src( $attr, $attachment, $size ) {
	// Only acts on named sizes (string), not arrays [w, h] or 'full'
	if ( ! is_string( $size ) || $size === 'full' ) {
		return $attr;
	}
	if ( ! is_array( $attr ) || empty( $attr['src'] ) ) {
		return $attr;
	}

	// Gets the registered width/height for this size
	$registered = wp_get_registered_image_subsizes();
	if ( ! isset( $registered[ $size ] ) ) {
		return $attr;
	}

	$expected_w = (int) $registered[ $size ]['width'];
	$expected_h = (int) $registered[ $size ]['height'];

	// If the src already has the correct suffix, it's OK (WP served the right size)
	$src_filename    = pathinfo( $attr['src'], PATHINFO_FILENAME );
	$expected_suffix = '-' . $expected_w . 'x' . $expected_h;
	if ( substr( $src_filename, -strlen( $expected_suffix ) ) === $expected_suffix ) {
		return $attr;
	}

	// Builds the expected size URL (same pattern WP uses for crops)
	$size_url = preg_replace(
		'/(\.[a-zA-Z0-9]+)$/',
		$expected_suffix . '$1',
		$attr['src']
	);
	if ( ! $size_url || $size_url === $attr['src'] ) {
		return $attr;
	}

	// Confirms the file exists on the filesystem before forcing (fail-safe)
	$upload_dir = wp_upload_dir();
	$size_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $size_url );
	if ( ! file_exists( $size_path ) ) {
		return $attr;
	}

	// Defensive override — correct src + dimensions
	$attr['src']    = $size_url;
	$attr['width']  = (string) $expected_w;
	$attr['height'] = (string) $expected_h;

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'rd_img_force_correct_src', 10, 3 );

/*
=============================================================================
 *  CLEANUP ON DELETE
 * ============================================================================= */

/**
 * Deletes orphaned WebP/AVIF when the attachment is deleted from the Media Library.
 */
function rd_img_cleanup_on_delete( $attachment_id ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $metadata ) ) {
		return;
	}

	$files = rd_img_get_attachment_paths( $metadata );

	foreach ( $files as $file ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			continue;
		}

		foreach ( array( 'webp', 'avif' ) as $fmt ) {
			$next_gen = preg_replace( '/\.(jpe?g|png|webp)$/i', '.' . $fmt, $file );
			if ( $next_gen !== $file && file_exists( $next_gen ) ) {
				wp_delete_file( $next_gen );
			}
		}
	}
}
add_action( 'delete_attachment', 'rd_img_cleanup_on_delete' );

/*
=============================================================================
 *  REGENERATE EXISTING ATTACHMENTS (AJAX in chunks)
 * ============================================================================= */

/**
 * Counts total JPEG/PNG/WebP attachments in the Media Library (for the progress bar).
 */
function rd_img_count_attachments(): int {
	global $wpdb;
	// prepare() used even with hardcoded values — theme convention:
	// all queries go through prepare, no exceptions (Wave 9 A audit).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-shot count for the admin "Regenerate" button (rarely clicked); caching would be overkill.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = %s
         AND post_mime_type IN (%s, %s, %s)",
			'attachment',
			'image/jpeg',
			'image/png',
			'image/webp'
		)
	);
}

/**
 * Time budget (seconds) for one regeneration chunk. AVIF encoding is slow
 * (1-3s per large image × all sizes × formats), so a fixed-count chunk can
 * blow past max_execution_time on constrained hosts. Half the PHP limit,
 * capped at 20s (the AJAX loop prefers many quick round-trips), floor of 5s
 * so at least some work happens per request. ini value 0/absent = unlimited
 * → use the 20s cap.
 */
function rd_img_regen_time_budget(): int {
	$max_exec = (int) ini_get( 'max_execution_time' );
	if ( $max_exec <= 0 ) {
		return 20;
	}
	return max( 5, min( 20, (int) floor( $max_exec / 2 ) ) );
}

/**
 * AJAX handler: regenerates 1 chunk of attachments.
 *
 * POST: offset (int), nonce (string)
 * JSON response: { processed, total, done }
 */
function rd_img_ajax_regenerate() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rd_img_regenerate' ) ) {
		wp_send_json_error( 'bad_nonce', 403 );
	}

	$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$total  = rd_img_count_attachments();

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'posts_per_page' => RD_IMG_REGEN_CHUNK,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	// Load admin dependencies so wp_generate_attachment_metadata works
	// (image.php has wp_create_image_subsizes, file.php has filesystem helpers)
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	rd_img_conversion_stats( 'reset' );

	// RD_IMG_REGEN_CHUNK is the upper bound per request; the time budget is
	// the real limiter — the loop stops early (after finishing the current
	// attachment) when the budget is spent, and the JS resumes from the actual
	// count processed. At least 1 attachment per request → always progresses.
	$budget    = rd_img_regen_time_budget();
	$started   = microtime( true );
	$processed = 0;

	foreach ( $attachments as $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			++$processed;
			continue; // orphaned attachment (DB has a record but the file is gone) — skip
		}

		// wp_generate_attachment_metadata regenerates ALL sizes registered via
		// add_image_size (including rd-popular-thumb that was just added)
		// and fires the wp_generate_attachment_metadata filter at the end — which triggers
		// rd_img_generate_on_upload and generates WebP/AVIF of the freshly regenerated sizes.
		// Resolves in one pass: missing sizes + next-gen formats.
		$new_metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $new_metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $new_metadata );
		}

		++$processed;
		if ( ( microtime( true ) - $started ) > $budget ) {
			break;
		}
	}

	$stats            = rd_img_conversion_stats();
	$processed_so_far = $offset + $processed;
	$done             = $processed_so_far >= $total || empty( $attachments );

	// Cumulative "last run" summary shown in the panel. offset 0 = a fresh run
	// starts the counters; later chunks accumulate on top.
	$summary = ( 0 === $offset ) ? array(
		'converted' => 0,
		'failed'    => 0,
	) : (array) get_option( 'rd_img_last_regen', array() );

	$summary = array(
		'time'      => time(),
		'converted' => (int) ( $summary['converted'] ?? 0 ) + $stats['converted'],
		'failed'    => (int) ( $summary['failed'] ?? 0 ) + $stats['failed'],
		'done'      => $done,
	);
	update_option( 'rd_img_last_regen', $summary, false );

	wp_send_json_success(
		array(
			'processed' => $processed_so_far,
			'total'     => $total,
			'done'      => $done,
			'converted' => $summary['converted'],
			'failed'    => $summary['failed'],
		)
	);
}
add_action( 'wp_ajax_rd_img_regenerate', 'rd_img_ajax_regenerate' );

/*
=============================================================================
 *  CLEANUP OF UNUSED FORMATS (mode switch leftovers)
 * ============================================================================= */

/**
 * AJAX handler: deletes next-gen files of formats NOT covered by the current
 * Format Mode. Switching e.g. 'both' → 'avif' used to leave every .webp on
 * disk forever (they were only removed when the attachment itself was
 * deleted). Walks all image attachments and unlinks the stale twins.
 *
 * Single-shot (no chunking): unlink + file_exists are filesystem-cheap even
 * for thousands of attachments — the expensive part of regen is encoding,
 * which doesn't happen here.
 *
 * POST: nonce (string)
 * JSON response: { deleted, freed_kb }
 */
function rd_img_ajax_cleanup_formats() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rd_img_cleanup_formats' ) ) {
		wp_send_json_error( 'bad_nonce', 403 );
	}

	$mode   = rd_img_get_mode();
	$unused = array();
	if ( 'avif' === $mode ) {
		$unused[] = 'webp';
	} elseif ( 'webp' === $mode ) {
		$unused[] = 'avif';
	}
	// mode 'both' → nothing unused (button is disabled in the UI, but guard anyway)
	if ( empty( $unused ) ) {
		wp_send_json_success(
			array(
				'deleted'  => 0,
				'freed_kb' => 0,
			)
		);
	}

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$deleted     = 0;
	$freed_bytes = 0;

	foreach ( $attachments as $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			continue;
		}

		foreach ( rd_img_get_attachment_paths( $metadata ) as $file ) {
			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
				continue;
			}

			foreach ( $unused as $fmt ) {
				$twin = preg_replace( '/\.(jpe?g|png|webp)$/i', '.' . $fmt, $file );
				if ( $twin === $file || ! file_exists( $twin ) ) {
					continue;
				}
				$size = filesize( $twin );
				wp_delete_file( $twin );
				if ( ! file_exists( $twin ) ) {
					++$deleted;
					$freed_bytes += (int) $size;
				}
			}
		}
	}

	wp_send_json_success(
		array(
			'deleted'  => $deleted,
			'freed_kb' => (int) round( $freed_bytes / 1024 ),
		)
	);
}
add_action( 'wp_ajax_rd_img_cleanup_formats', 'rd_img_ajax_cleanup_formats' );

/**
 * Localizes the regeneration JS data — only on the Images & Media tab of the
 * rd_options panel. The JS itself ships in the consolidated admin-panel.js
 * bundle; this only attaches its ajaxurl + i18n strings to that handle.
 */
function rd_img_admin_enqueue( $hook ) {
	if ( strpos( $hook, 'rd_options' ) === false ) {
		return;
	}

	// Only on the Images & Media tab.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate in admin_enqueue_scripts: decides whether to enqueue the regeneration JS, doesn't process a form.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	if ( $tab !== 'media' ) {
		return;
	}

	// The regeneration JS now ships in the consolidated admin-panel.js bundle
	// (enqueued in core.php at priority 5). Here we only attach its data
	// (ajaxurl + i18n) to that handle, still gated to the Images & Media tab.
	wp_localize_script(
		'rd-admin-panel',
		'rd_img_regen',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => array(
				'no_images'       => __( 'No JPEG/PNG/WebP attachments to process.', 'reloaded' ),
				/* translators: %d: number of JPEG/PNG/WebP attachments to process */
				'confirm'         => __( 'Start processing %d images? This may take several minutes.', 'reloaded' ),
				'starting'        => __( 'Starting...', 'reloaded' ),
				'progress'        => __( 'Processed %p of %t (%pct%)', 'reloaded' ),
				'done'            => __( 'Done! Processed %t images.', 'reloaded' ),
				/* translators: %f: number of failed conversions */
				'failures'        => __( '%f conversions failed — check file permissions and server capabilities.', 'reloaded' ),
				'error'           => __( 'Error: ', 'reloaded' ),
				'cleanup_confirm' => __( 'Delete all files of the format not covered by the current Format Mode? The originals and the active format are kept.', 'reloaded' ),
				'cleanup_busy'    => __( 'Cleaning…', 'reloaded' ),
				/* translators: 1: number of deleted files, 2: freed disk space in KB */
				'cleanup_done'    => __( 'Removed %1$d files (%2$s KB freed).', 'reloaded' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rd_img_admin_enqueue' );

/*
=============================================================================
 *  PANEL UI — section callback (renders diagnostics + regenerate button)
 * ============================================================================= */

/**
 * Callback for the "Image Formats" section on the Performance tab.
 * Shows: server capabilities + regeneration button with progress.
 *
 * Wave 11 Phase G: refactored to the rd-p* design system with a 30/70 layout
 * (Server Capabilities on the left, Regenerate action on the right).
 */
function rd_img_render_panel_section() {
	$caps    = rd_img_get_capabilities();
	$webp_ok = $caps['webp_imagick'] || $caps['webp_gd'];
	$avif_ok = $caps['avif_imagick'] || $caps['avif_gd'];
	$total   = rd_img_count_attachments();
	?>
	<div class="rd-pdash rd-pgrid rd-pgrid--sidebar-main">

		<?php // === Card 30%: Server Capabilities === ?>
		<?php
		rd_panel_card_open(
			array(
				'title' => __( 'Server Capabilities', 'reloaded' ),
				'desc'  => __( 'Auto-detected from PHP extensions installed on this server.', 'reloaded' ),
			)
		);
		?>
		<ul class="rd-img-caps-list">
			<li>
				<strong>Imagick</strong>
				<?php
				echo $caps['imagick']
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pre-escaped by rd_panel_badge().
					? rd_panel_badge( 'success', __( 'available', 'reloaded' ) )
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pre-escaped.
					: rd_panel_badge( 'danger', __( 'not installed', 'reloaded' ) );
				?>
				<small><?php esc_html_e( '(preferred)', 'reloaded' ); ?></small>
			</li>
			<li>
				<strong>WebP</strong>
				<?php
				echo $webp_ok
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pre-escaped.
					? rd_panel_badge( 'success', __( 'available', 'reloaded' ) )
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pre-escaped.
					: rd_panel_badge( 'danger', __( 'unavailable', 'reloaded' ) );
				?>
			</li>
			<li>
				<strong>AVIF</strong>
				<?php
				echo $avif_ok
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pre-escaped.
					? rd_panel_badge( 'success', __( 'available', 'reloaded' ) )
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pre-escaped.
					: rd_panel_badge( 'danger', __( 'unavailable', 'reloaded' ) );
				?>
			</li>
		</ul>

		<?php if ( ! $webp_ok && ! $avif_ok ) : ?>
			<?php rd_panel_status( 'warning', esc_html__( 'Neither WebP nor AVIF is available — module is dormant. Uploads will not be converted and no <picture> wrapping happens. Original JPEG/PNG continue to work normally.', 'reloaded' ) ); ?>
		<?php endif; ?>
		<?php rd_panel_card_close(); ?>

		<?php // === Card 70%: Regenerate Action === ?>
		<?php
		rd_panel_card_open(
			array(
				'title' => __( 'Regenerate sizes and next-gen formats for existing attachments', 'reloaded' ),
				'desc'  => __( 'New uploads are processed automatically. Use this button after: (a) enabling the module on a site with existing images; (b) switching the Format Mode above; (c) changing the image quality; or (d) adding/changing a custom image size in the theme (re-crops all sizes via WP\'s wp_generate_attachment_metadata, then generates WebP/AVIF for each). The action is idempotent: re-running overwrites existing files with current settings. May take several minutes on a large Media Library — runs in time-budgeted AJAX chunks to avoid timeouts.', 'reloaded' ),
			)
		);

		$mode      = rd_img_get_mode();
		$last_run  = (array) get_option( 'rd_img_last_regen', array() );
		$has_stats = ! empty( $last_run['time'] );
		?>
		<p class="rd-pcard__action-row rd-img-regen-row">
			<span class="rd-img-regen-count">
				<?php
				printf(
					/* translators: 1: number of JPEG/PNG/WebP attachments in the Media Library */
					esc_html( _n( '%d JPEG/PNG/WebP attachment in the Media Library', '%d JPEG/PNG/WebP attachments in the Media Library', (int) $total, 'reloaded' ) ),
					(int) $total
				);
				?>
			</span>
			<button type="button"
					id="rd-img-regen-start"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'rd_img_regenerate' ) ); ?>"
					data-total="<?php echo (int) $total; ?>"
					<?php disabled( ! $webp_ok && ! $avif_ok ); ?>>
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Start regeneration', 'reloaded' ); ?>
			</button>
		</p>

		<?php if ( $has_stats ) : ?>
			<p class="rd-img-regen-last">
				<?php
				printf(
					/* translators: 1: date/time of the last regeneration, 2: number of converted files, 3: number of failed conversions */
					esc_html__( 'Last regeneration: %1$s — %2$d files converted, %3$d failures.', 'reloaded' ),
					esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_run['time'] ) ),
					(int) ( $last_run['converted'] ?? 0 ),
					(int) ( $last_run['failed'] ?? 0 )
				);
				if ( ! empty( $last_run['failed'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pre-escaped by rd_panel_badge().
					echo ' ' . rd_panel_badge( 'warning', __( 'check failures', 'reloaded' ) );
				}
				?>
			</p>
		<?php endif; ?>

		<div id="rd-img-regen-progress" class="rd-img-regen-progress" hidden>
			<progress id="rd-img-regen-bar" value="0" max="100"></progress>
			<p id="rd-img-regen-status"></p>
		</div>

		<?php // === Cleanup of stale formats after a mode switch === ?>
		<p class="rd-pcard__action-row rd-img-cleanup-row">
			<span>
				<?php
				if ( 'both' === $mode ) {
					esc_html_e( 'Format Mode is "both" — every generated file is in use, nothing to clean.', 'reloaded' );
				} else {
					printf(
						/* translators: %s: the file format NOT covered by the current mode (webp or avif) */
						esc_html__( 'Format Mode leaves .%s files unused — they linger on disk after a mode switch.', 'reloaded' ),
						esc_html( 'avif' === $mode ? 'webp' : 'avif' )
					);
				}
				?>
			</span>
			<button type="button"
					id="rd-img-cleanup-start"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'rd_img_cleanup_formats' ) ); ?>"
					<?php disabled( 'both' === $mode ); ?>>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php esc_html_e( 'Remove unused format', 'reloaded' ); ?>
			</button>
		</p>
		<p id="rd-img-cleanup-status" class="rd-img-cleanup-status"></p>
		<?php rd_panel_card_close(); ?>

	</div>
	<?php
}
