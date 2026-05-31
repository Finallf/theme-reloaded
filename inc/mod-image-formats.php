<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Image Formats (WebP/AVIF) — Next-gen image delivery                 *
 *                                                                             *
 * Automatically generates WebP and/or AVIF versions of every JPEG/PNG image on*
 * upload (all WP sizes + the theme's custom sizes). Delivers via              *
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
 * Coverage (Phase 1):                                                         *
 *   ✅ Featured images, post thumbnails, post-card, galleries via              *
 *      wp_get_attachment_image                                                *
 *   ⏳ Inline images from the Gutenberg editor (block core/image) — Phase 2    *
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
 * mod-general.php) and to the WebP/AVIF we generate here — full coherence: if
 * the admin changes it to 85, all formats become 85. Default 80 if the key
 * isn't in rd_settings or if the value is invalid. Defensive 1-100 clamp.
 */
function rd_img_get_quality(): int {
	$quality = (int) rd_get_option( 'jpeg_quality', 80 );
	return max( 1, min( 100, $quality ) );
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
	// Quality default: read from the panel (same config as JPEGs) — full coherence.
	// Override via parameter allows programmatic use with a specific value.
	if ( $quality === null ) {
		$quality = rd_img_get_quality();
	}
	if ( ! file_exists( $source_path ) ) {
		return false;
	}
	if ( ! in_array( $target_format, array( 'webp', 'avif' ), true ) ) {
		return false;
	}

	$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return false;
	}

	$target_path = preg_replace( '/\.(jpe?g|png)$/i', '.' . $target_format, $source_path );
	if ( $target_path === $source_path ) {
		return false;
	}

	$caps = rd_img_get_capabilities();

	// Engine decision by format + source:
	// - WebP from any format → Imagick (superior quality, alpha OK)
	// - AVIF from JPEG → Imagick (superior quality, JPEG has no alpha)
	// - AVIF from PNG → SKIP Imagick, go straight to GD
	// Reason: ImageMagick 6 + libheif has a historical bug that replaces
	// transparent backgrounds with black when generating AVIF. ImageMagick 7 may
	// fix it but it depends on the exact server version. Portable solution: for PNG
	// (which potentially has alpha) use GD, which has native + correct support for
	// AVIF with transparency (PHP 8.1+).
	// Ref: https://alexwlchan.net/2023/check-for-transparency/
	$force_gd_for_avif = ( $target_format === 'avif' && $ext === 'png' );

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
		}
		if ( ! $img ) {
			return false;
		}

		$func = 'image' . $target_format;
		if ( ! function_exists( $func ) ) {
			imagedestroy( $img );
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- imagewebp/imageavif emit a warning on encode/write failure; we check $success in the `return` below.
		$success = @$func( $img, $target_path, $quality );
		imagedestroy( $img );
		return $success ? $target_path : false;
	}

	return false;
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
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			continue;
		}

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
	if ( '' === $url ) {
		return array();
	}

	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return array();
	}

	// Resolve absolute path to check existence of the next-gen files
	$upload_dir = wp_upload_dir();
	$base_url   = trailingslashit( $upload_dir['baseurl'] );
	$base_dir   = trailingslashit( $upload_dir['basedir'] );

	// URL outside uploads (external CDN, etc) — passes through transparently
	if ( strpos( $url, $base_url ) !== 0 ) {
		return array();
	}

	$relative = substr( $url, strlen( $base_url ) );
	$abs_path = $base_dir . $relative;

	$mode    = rd_img_get_mode();
	$sources = array();

	// AVIF first — better compression for browsers that support it
	if ( ( $mode === 'both' || $mode === 'avif' ) && rd_img_can_generate( 'avif' ) ) {
		$avif_path = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $abs_path );
		if ( file_exists( $avif_path ) ) {
			$sources[] = array(
				'url'  => preg_replace( '/\.(jpe?g|png)$/i', '.avif', $url ),
				'type' => 'image/avif',
			);
		}
	}

	// WebP second
	if ( ( $mode === 'both' || $mode === 'webp' ) && rd_img_can_generate( 'webp' ) ) {
		$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $abs_path );
		if ( file_exists( $webp_path ) ) {
			$sources[] = array(
				'url'  => preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url ),
				'type' => 'image/webp',
			);
		}
	}

	return $sources;
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

	$src = wp_get_attachment_image_src( $attachment_id, $size );
	if ( ! $src ) {
		return $html;
	}

	return rd_img_wrap_url_in_picture( $src[0], $html );
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

		$sources = rd_img_get_nextgen_sources_for_url( $src );
		if ( empty( $sources ) ) {
			continue;
		}

		// Create <picture> and populate it with <source> via direct createElement.
		// (We tried appendXML before but DOMDocumentFragment requires strict XML —
		// HTML5 `<source>` without self-closing produced a "Document Fragment is empty"
		// warning. createElement + setAttribute is the correct API to build
		// new elements in the existing DOM tree.)
		$picture = $dom->createElement( 'picture' );
		foreach ( $sources as $source ) {
			$source_el = $dom->createElement( 'source' );
			$source_el->setAttribute( 'srcset', $source['url'] );
			$source_el->setAttribute( 'type', $source['type'] );
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
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			continue;
		}

		foreach ( array( 'webp', 'avif' ) as $fmt ) {
			$next_gen = preg_replace( '/\.(jpe?g|png)$/i', '.' . $fmt, $file );
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
 * Counts total JPEG/PNG attachments in the Media Library (for the progress bar).
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
         AND post_mime_type IN (%s, %s)",
			'attachment',
			'image/jpeg',
			'image/png'
		)
	);
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
			'post_mime_type' => array( 'image/jpeg', 'image/png' ),
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

	foreach ( $attachments as $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
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
	}

	$processed_so_far = $offset + count( $attachments );
	$done             = $processed_so_far >= $total || empty( $attachments );

	wp_send_json_success(
		array(
			'processed' => $processed_so_far,
			'total'     => $total,
			'done'      => $done,
		)
	);
}
add_action( 'wp_ajax_rd_img_regenerate', 'rd_img_ajax_regenerate' );

/**
 * Enqueue the regeneration JS — only on the Performance tab of the rd_options panel.
 * Conditional loading avoids polluting other admin screens.
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

	wp_enqueue_script(
		'rd-img-regen',
		get_template_directory_uri() . '/assets/js/admin-img-regen.js',
		array(),
		rd_asset_version( '/assets/js/admin-img-regen.js' ),
		true
	);

	wp_localize_script(
		'rd-img-regen',
		'rd_img_regen',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => array(
				'no_images' => __( 'No JPEG/PNG attachments to process.', 'reloaded' ),
				/* translators: %d: number of JPEG/PNG attachments to process */
				'confirm'   => __( 'Start processing %d images? This may take several minutes.', 'reloaded' ),
				'starting'  => __( 'Starting...', 'reloaded' ),
				'progress'  => __( 'Processed %p of %t (%pct%)', 'reloaded' ),
				'done'      => __( 'Done! Processed %t images.', 'reloaded' ),
				'error'     => __( 'Error: ', 'reloaded' ),
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
				'desc'  => __( 'New uploads are processed automatically. Use this button after: (a) enabling the module on a site with existing images; (b) switching the Format Mode above; or (c) adding/changing a custom image size in the theme (re-crops all sizes via WP\'s wp_generate_attachment_metadata, then generates WebP/AVIF for each). The action is idempotent: re-running overwrites existing files with current settings. May take several minutes on a large Media Library — runs in chunks of 10 via AJAX to avoid timeouts.', 'reloaded' ),
			)
		);
		?>
		<p class="rd-pcard__action-row rd-img-regen-row">
			<span class="rd-img-regen-count">
				<?php
				printf(
					/* translators: 1: number of JPEG/PNG attachments in the Media Library */
					esc_html( _n( '%d JPEG/PNG attachment in the Media Library', '%d JPEG/PNG attachments in the Media Library', (int) $total, 'reloaded' ) ),
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

		<div id="rd-img-regen-progress" class="rd-img-regen-progress" hidden>
			<progress id="rd-img-regen-bar" value="0" max="100"></progress>
			<p id="rd-img-regen-status"></p>
		</div>
		<?php rd_panel_card_close(); ?>

	</div>
	<?php
}
