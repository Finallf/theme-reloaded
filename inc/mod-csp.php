<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: CSP (Content Security Policy) — Report-Only mode                    *
 *                                                                             *
 * Sends the `Content-Security-Policy-Report-Only` header on the frontend with *
 * a policy computed dynamically based on the active integrations in the panel.*
 * The browser does NOT block anything — it just reports violations to an own  *
 * REST endpoint (`/wp-json/rd/v1/csp-report`) that stores them in a WP option.*
 *                                                                             *
 * Goal: visibility into what the site actually loads (scripts, styles,        *
 * images, frames, fonts) — useful for security and privacy auditing.          *
 * Once the policy is mature (weeks/months analyzing reports), you can         *
 * promote it to `Content-Security-Policy` (enforce) by editing this file.     *
 *                                                                             *
 * Gate: feature toggleable in Panel → Security (`enable_csp_report_only`,     *
 * default OFF). When OFF, the header isn't sent and the REST endpoint stays   *
 * registered but inert (the browser has no reason to call it).                *
 *******************************************************************************/

const RD_CSP_REPORTS_OPTION    = 'rd_csp_reports';
const RD_CSP_REPORTS_MAX       = 100; // FIFO — discards old reports
const RD_CSP_RATE_LIMIT_MAX    = 60;  // max 60 reports per IP
const RD_CSP_RATE_LIMIT_WINDOW = 60;  // 60s window

/*
=============================================================================
 *  NONCE — generated once per request, propagated to inline scripts/styles
 * ============================================================================= */

/**
 * Returns the CSP nonce for the current request (cached in static).
 *
 * - 16 bytes of entropy → base64url (~22 chars without padding). The CSP spec
 *   requires ≥ 128 bits of unpredictability; 16 bytes (128 bits) gives exactly that.
 * - Computed ONLY when the CSP feature is ON. If OFF, returns ''.
 *   `rd_csp_nonce_attr()` uses this to omit the whole attribute — keeps the
 *   HTML clean when CSP isn't in use.
 *
 * @return string base64url nonce (~22 chars) or '' when CSP is off.
 */
function rd_csp_nonce(): string {
	static $nonce = null;
	if ( null !== $nonce ) {
		return $nonce;
	}
	if ( ! rd_get_option_bool( 'enable_csp_report_only' ) ) {
		$nonce = '';
		return $nonce;
	}
	// base64url — characters safe for an HTML attribute without extra escaping.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 here isn't obfuscation; it's canonical encoding of random bytes into a safe ASCII string.
	$nonce = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' );
	return $nonce;
}

/**
 * `nonce="..."` attribute ready to concatenate into `<script>` or `<style>`.
 *
 * Returns '' when CSP is OFF — allows unconditional use in templates
 * without needing `<?php if ... ?>` around each tag.
 *
 * @return string ` nonce="..."` (with leading space) or '' if CSP is off.
 */
function rd_csp_nonce_attr(): string {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return '';
	}
	return ' nonce="' . esc_attr( $nonce ) . '"';
}

/**
 * Injects `nonce="..."` into all `<script>` tags of arbitrary HTML.
 *
 * Designed for the panel's `ad_*` fields (AdSense, FB Ads, custom snippets)
 * — the admin pastes tracking code straight from the platform docs, and
 * this function adds the nonce automatically before the echo. Without it, in
 * Enforce the snippet would be blocked.
 *
 * Details:
 * - Only touches `<script>` that does NOT yet have `nonce=` (idempotent).
 * - Works for `<script>...</script>` and `<script src="...">...</script>`.
 * - When CSP is OFF, returns the HTML unchanged.
 *
 * @param string $html HTML block that may contain one or more `<script>` tags.
 * @return string HTML with nonce injected into each applicable `<script>`.
 */
function rd_csp_inject_nonce( string $html ): string {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce || '' === $html ) {
		return $html;
	}
	// Replaces `<script` with `<script nonce="..."` ONLY when the tag doesn't
	// have a nonce yet. (?!...) is a negative lookahead: rejects if it sees `nonce=`
	// in the next chars before the `>`.
	$pattern     = '/<script(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<script nonce="' . esc_attr( $nonce ) . '"$1';
	$result      = preg_replace( $pattern, $replacement, $html );
	return null !== $result ? $result : $html;
}

/*
=============================================================================
 *  CUSTOM ORIGINS — parser/validator for extra origins from the panel
 * ============================================================================= */

/**
 * Parses a textarea (1 origin per line) and returns an array of origins
 * valid for entering the CSP.
 *
 * Acceptance rules:
 *   - https://hostname[:port][/path...] — path is ignored by CSP, the host
 *     enters whole. Accepts subdomain wildcard: https://*.foo.com
 *   - https:                              — schema-only, allows ANY HTTPS
 *                                           host. Permitted but dangerous
 *                                           (admin who knows what they do).
 *
 * Rejection rules (line discarded silently):
 *   - Plain HTTP                    — forces HTTPS, aligned with modern infra
 *   - Quoted keywords               — 'unsafe-inline', 'self', 'none' etc.
 *                                     would defeat the point of CSP nonce
 *   - Bare wildcard `*`             — defeats the purpose
 *   - data:, blob:, filesystem:     — non-standard schemes require PHP edits
 *   - Anything that doesn't match
 *     the patterns above            — includes blank lines and comments
 *
 * @param string|null $raw Textarea content (separated by \n).
 * @return string[] Valid origins, ready to concatenate into directives.
 */
function rd_csp_parse_custom_origins( $raw ): array {
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return array();
	}

	$origins = array();
	$lines   = preg_split( '/\r\n|\r|\n/', $raw );
	if ( ! is_array( $lines ) ) {
		return array();
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		// Schema-only token (allows everything on a scheme) — only HTTPS accepted.
		if ( 'https:' === $line ) {
			$origins[] = 'https:';
			continue;
		}

		// https://hostname[:port][/path] with optional wildcard in the subdomain.
		// Hostname must have a dot OR be a literal (but literal is rare/local).
		if ( preg_match( '#^https://(\*\.)?([a-z0-9.\-]+)(:[0-9]+)?(/.*)?$#i', $line, $m ) ) {
			$wildcard = $m[1] ?? '';
			$host     = $m[2];
			$port     = $m[3] ?? '';

			// Reject a malformed host: starts/ends with a hyphen, double dot, etc.
			if ( preg_match( '/(^[-.]|[-.]$|\.\.|--)/', $host ) ) {
				continue;
			}

			// Rebuild only the origin (host + port), no path — CSP ignores the path.
			$origins[] = 'https://' . $wildcard . $host . $port;
			continue;
		}

		// Everything else is rejected (HTTP, quoted keywords, data:, etc.).
	}

	return array_values( array_unique( $origins ) );
}

/*
=============================================================================
 *  POLICY BUILDER — builds the CSP string per active integrations
 * ============================================================================= */

/**
 * Builds the full CSP based on which integrations are active in the panel.
 * Starts with a restricted baseline (self) and adds origins as features turn ON.
 *
 * @return string CSP header ready to send
 */
function rd_csp_build_policy(): string {
	$self  = "'self'";
	$nonce = rd_csp_nonce();

	/*
	 * nonce + strict-dynamic tokens — only appear when the helper returned
	 * a valid nonce (i.e. CSP is ON and random_bytes worked). Pattern
	 * recommended by Google CSP Evaluator / OWASP:
	 *   - 'nonce-XXX' enables only the <script>/<style> that carry that nonce
	 *   - 'strict-dynamic' propagates trust to scripts that a nonce'd
	 *     script creates dynamically (essential for GA, Clarity, AdSense, etc.
	 *     that load child scripts via document.createElement)
	 *   - 'unsafe-inline' stays as a fallback for pre-CSP3 browsers (Safari
	 *     < 15.4). In modern browsers, the presence of a nonce/hash in a
	 *     directive automatically NULLIFIES 'unsafe-inline' (CSP3 spec).
	 */
	$nonce_token = '' !== $nonce ? "'nonce-{$nonce}'" : '';

	// Baseline directives (always applied).
	$d = array(
		'default-src'    => array( $self ),
		// script-src: nonce + strict-dynamic in modern browsers, unsafe-inline
		// as graceful degradation for old browsers.
		'script-src'     => array_filter(
			array(
				$self,
				$nonce_token,
				'' !== $nonce_token ? "'strict-dynamic'" : '',
				"'unsafe-inline'",
			)
		),
		// style-src: governs <style> tags (and <link rel=stylesheet>). Nonce enables
		// enforce in modern browsers; 'unsafe-inline' stays as a fallback for
		// pre-CSP3 browsers. strict-dynamic doesn't apply to styles (CSP3 spec).
		'style-src'      => array_filter(
			array(
				$self,
				$nonce_token,
				"'unsafe-inline'",
			)
		),

		/*
		 * style-src-attr: governs ONLY style="..." attributes on HTML elements
		 * (and element.style.X = ... via JS). Must be declared separately
		 * because CSP3 nullifies 'unsafe-inline' when there's a nonce in the same
		 * directive, and nonces don't apply to attributes (CSP3 spec) — the only way
		 * not to block legitimate style attrs (WP admin bar, mod-archive-header
		 * accent_color, post content with inline HTML, Gutenberg blocks)
		 * is to keep 'unsafe-inline' explicit here, isolated from the style-src nonce.
		 *
		 * Accepted trade-off (industry-standard pattern for nonce-based CSP):
		 * a style="..." injected via XSS can do visual defacing or clickjacking
		 * (invisible overlay), but does NOT execute JS — CSS expression() died with
		 * IE; modern CSS has no execution capability. Injected <style> tags
		 * remain blocked by the style-src nonce.
		 *
		 * Pre-CSP3 browsers ignore style-src-attr (unknown directive) and
		 * fall back to style-src — which has 'unsafe-inline' as a fallback. No regression.
		 */
		'style-src-attr' => array( "'unsafe-inline'" ),
		// permissive img-src: accepts any HTTPS image. Conscious decision for a
		// blog/portal that pastes external markdown (GitHub camo, shields.io, imgur, etc.).
		// Since `https:` covers any secure origin, it dispenses with a specific whitelist
		// for Gravatar and the integrations below — they only add origins to
		// script-src/connect-src/frame-src (where the restriction really matters).
		'img-src'        => array( $self, 'data:', 'https:' ),
		'font-src'       => array( $self, 'data:' ),
		'connect-src'    => array( $self ),
		'frame-src'      => array( $self ),
		'media-src'      => array( $self ),
		'object-src'     => array( "'none'" ), // blocks <object>/<embed> (legacy Flash).
		'base-uri'       => array( $self ),
		'form-action'    => array( $self ),
	);

	// === Conditional integrations ===

	// Google Analytics (GA4)
	if ( trim( (string) rd_get_option( 'ga_id' ) ) !== '' ) {
		$d['script-src'][]  = 'https://www.googletagmanager.com';
		$d['script-src'][]  = 'https://www.google-analytics.com';
		$d['connect-src'][] = 'https://www.google-analytics.com';
		$d['connect-src'][] = 'https://*.analytics.google.com';
		$d['connect-src'][] = 'https://*.google-analytics.com';
		// img-src: GA uses a beacon image (collect endpoint) — covered by the `https:` baseline
	}

	// Microsoft Clarity
	if ( trim( (string) rd_get_option( 'clarity_id' ) ) !== '' ) {
		$d['script-src'][]  = 'https://www.clarity.ms';
		$d['script-src'][]  = 'https://*.clarity.ms';
		$d['connect-src'][] = 'https://*.clarity.ms';
	}

	// Facebook Pixel
	if ( trim( (string) rd_get_option( 'facebook_pixel_id' ) ) !== '' ) {
		$d['script-src'][]  = 'https://connect.facebook.net';
		$d['connect-src'][] = 'https://www.facebook.com';
		// img-src: Pixel <img> tracking — covered by the `https:` baseline
	}

	// TikTok Pixel
	if ( trim( (string) rd_get_option( 'tiktok_pixel_id' ) ) !== '' ) {
		$d['script-src'][]  = 'https://analytics.tiktok.com';
		$d['connect-src'][] = 'https://analytics.tiktok.com';
		// img-src: TikTok beacon — covered by the `https:` baseline
	}

	// Plausible Analytics
	if ( trim( (string) rd_get_option( 'plausible_domain' ) ) !== '' ) {
		$d['script-src'][]  = 'https://plausible.io';
		$d['connect-src'][] = 'https://plausible.io';
	}

	// Umami Analytics — configurable URL, extracts the origin
	$umami_url = trim( (string) rd_get_option( 'umami_script_url' ) );
	if ( $umami_url !== '' && filter_var( $umami_url, FILTER_VALIDATE_URL ) ) {
		$parsed = wp_parse_url( $umami_url );
		if ( ! empty( $parsed['host'] ) ) {
			$origin             = ( $parsed['scheme'] ?? 'https' ) . '://' . $parsed['host'];
			$d['script-src'][]  = $origin;
			$d['connect-src'][] = $origin;
		}
	}

	// Discord widget (iframe)
	if ( rd_get_option_bool( 'discord_widget' ) ) {
		$d['frame-src'][] = 'https://ptb.discord.com';
		$d['frame-src'][] = 'https://discord.com';
	}

	// YouTube embed — always allowed for frame-src.
	// It used to be gated by `facade_youtube` (a PERFORMANCE toggle to render
	// a static thumbnail before the iframe), but that blocked posts with a direct
	// YouTube embed (Gutenberg embed block, [embed] shortcode, raw iframe in
	// markdown) when the facade was OFF. YouTube is a universally trusted origin
	// for a tech/games portal — always allowing it is the correct pragmatism.
	// img-src: thumbnails (img.youtube.com, i.ytimg.com) — covered by the `https:` baseline.
	$d['frame-src'][] = 'https://www.youtube.com';
	$d['frame-src'][] = 'https://www.youtube-nocookie.com';

	/*
	 * === Custom origins (admin defines them in the panel for new integrations) ===
	 * 3 textarea fields in Panel → Security, grouped by typical use:
	 *   - Scripts & APIs (script-src + connect-src)
	 *   - Iframes & Embeds (frame-src)
	 *   - Styles & Fonts (style-src + font-src)
	 * Each accepts 1 origin per line. The parser validates the format (HTTPS only,
	 * no quoted keywords, no bare wildcard) and discards invalid lines.
	 */
	$custom_scripts = rd_csp_parse_custom_origins( rd_get_option( 'csp_custom_scripts' ) );
	if ( ! empty( $custom_scripts ) ) {
		$d['script-src']  = array_merge( $d['script-src'], $custom_scripts );
		$d['connect-src'] = array_merge( $d['connect-src'], $custom_scripts );
	}

	$custom_frames = rd_csp_parse_custom_origins( rd_get_option( 'csp_custom_frames' ) );
	if ( ! empty( $custom_frames ) ) {
		$d['frame-src'] = array_merge( $d['frame-src'], $custom_frames );
	}

	$custom_styles = rd_csp_parse_custom_origins( rd_get_option( 'csp_custom_styles' ) );
	if ( ! empty( $custom_styles ) ) {
		$d['style-src'] = array_merge( $d['style-src'], $custom_styles );
		$d['font-src']  = array_merge( $d['font-src'], $custom_styles );
	}

	// === Reporting endpoints ===
	// report-uri is legacy (CSP 2) but universally supported.
	// report-to (CSP 3 / Reporting API) is the future, but requires a Reporting-Endpoints header.
	// For simplicity we start with report-uri only — works in all current browsers.
	$d['report-uri'] = array( esc_url_raw( rest_url( 'rd/v1/csp-report' ) ) );

	// Build the final string
	$parts = array();
	foreach ( $d as $directive => $sources ) {
		$parts[] = $directive . ' ' . implode( ' ', array_unique( $sources ) );
	}
	return implode( '; ', $parts );
}

/*
=============================================================================
 *  HEADER INJECTION — sends the CSP-Report-Only on the frontend
 * ============================================================================= */

/**
 * Injects the CSP header via the wp_headers filter (frontend only).
 *
 * Two modes controlled by the panel:
 *   - Report-Only (default): `Content-Security-Policy-Report-Only` — browser
 *     reports but does NOT block. For monitoring/auditing.
 *   - Enforce: `Content-Security-Policy` — browser BLOCKS violations. For
 *     production, after at least 30 days in report-only without surprises.
 *
 * The admin has its own WP scripts/inline styles — no CSP in admin
 * to avoid breaking the native UI.
 */
function rd_csp_inject_header( $headers ) {
	if ( ! rd_get_option_bool( 'enable_csp_report_only' ) ) {
		return $headers;
	}
	if ( is_admin() ) {
		return $headers;
	}

	// Decide the header name based on the mode configured in the panel
	$header_name = rd_get_option_bool( 'csp_enforce_mode' )
		? 'Content-Security-Policy'
		: 'Content-Security-Policy-Report-Only';

	$headers[ $header_name ] = rd_csp_build_policy();
	return $headers;
}
add_filter( 'wp_headers', 'rd_csp_inject_header' );

/*
=============================================================================
 *  REST ENDPOINT — receives reports from the browser
 * ============================================================================= */

/**
 * Registers the /wp-json/rd/v1/csp-report endpoint. permission_callback returns
 * true because the browser has no way to authenticate — the endpoint is public but
 * only stores structured data (validated via json_decode + field whitelist
 * below).
 */
function rd_csp_register_endpoint() {
	register_rest_route(
		'rd/v1',
		'/csp-report',
		array(
			'methods'             => 'POST',
			'callback'            => 'rd_csp_receive_report',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'rd_csp_register_endpoint' );

/**
 * Parses the admin's "Report Noise Filter" textarea (1 host per line) into a
 * normalized list of lowercase hostnames. Accepts a bare host (use.typekit.net)
 * or a full URL (https://use.typekit.net/...) — in both cases only the host is
 * kept, since CSP blocked-uri matching is host-based.
 *
 * @param string|null $raw Textarea content (separated by \n).
 * @return string[] Lowercase hostnames, deduped.
 */
function rd_csp_parse_report_denylist( $raw ): array {
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return array();
	}
	$hosts = array();
	$lines = preg_split( '/\r\n|\r|\n/', $raw );
	if ( ! is_array( $lines ) ) {
		return array();
	}
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		// Full URL pasted by mistake → extract just the host.
		if ( false !== strpos( $line, '://' ) ) {
			$line = (string) ( wp_parse_url( $line, PHP_URL_HOST ) ?? '' );
		}
		$line = strtolower( ltrim( $line, '.' ) );
		if ( '' !== $line ) {
			$hosts[] = $line;
		}
	}
	return array_values( array_unique( $hosts ) );
}

/**
 * Decides whether a CSP report is "noise" that should be discarded before being
 * stored, so the panel shows only real, actionable violations. Two layers:
 *
 *   A) Browser-extension injections (automatic, in code): when the browser
 *      attributes the violation to an extension via the source-file scheme
 *      (chrome-extension://, moz-extension://, safari-web-extension:// and
 *      Safari's masked webkit-masked-url://). Covers ANY extension, present or
 *      future, with zero maintenance.
 *
 *   B) Admin host denylist (Panel → Security): for noise that slips through
 *      layer A with an empty or page-level source-file — some browsers don't
 *      attribute the extension, so only the blocked-uri host identifies it
 *      (e.g. use.typekit.net injected by the Adobe Acrobat extension).
 *
 * @param array    $report   Decoded csp-report payload.
 * @param string[] $denylist Lowercase hosts from rd_csp_parse_report_denylist().
 * @return bool True when the report is noise and should be dropped.
 */
function rd_csp_report_is_noise( array $report, array $denylist ): bool {
	// --- Layer A: extension-scheme source-file ---
	$source      = isset( $report['source-file'] ) ? strtolower( (string) $report['source-file'] ) : '';
	$ext_schemes = array( 'chrome-extension://', 'moz-extension://', 'safari-web-extension://', 'safari-extension://', 'webkit-masked-url://' );
	foreach ( $ext_schemes as $scheme ) {
		if ( 0 === strpos( $source, $scheme ) ) {
			return true;
		}
	}

	// --- Layer B: blocked-uri host in the admin denylist ---
	if ( ! empty( $denylist ) ) {
		$blocked = isset( $report['blocked-uri'] ) ? (string) $report['blocked-uri'] : '';
		if ( '' !== $blocked && false !== strpos( $blocked, '://' ) ) {
			$host = strtolower( (string) ( wp_parse_url( $blocked, PHP_URL_HOST ) ?? '' ) );
			if ( '' !== $host && in_array( $host, $denylist, true ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Receives a CSP report from the browser. Expects a JSON body with the standard CSP-2 structure:
 *   { "csp-report": { "document-uri", "violated-directive", "blocked-uri", ... } }
 *
 * Stores it in the `rd_csp_reports` option with a FIFO of RD_CSP_REPORTS_MAX entries.
 * autoload=no because it can grow and we don't always need it in memory.
 */
function rd_csp_receive_report( WP_REST_Request $request ) {
	// Feature OFF = silently ignore (avoids report spam if
	// someone disables it and the browser still tries to deliver pending ones)
	if ( ! rd_get_option_bool( 'enable_csp_report_only' ) ) {
		return new WP_REST_Response( null, 204 );
	}

	// Per-IP rate limit. The endpoint is necessarily public (browsers don't
	// authenticate CSP reports), so without a cap an attacker could flood 100+
	// minimal JSON POSTs to fill the reports FIFO and hide
	// legitimate violations. Reuses `rd_get_client_ip` (validates REMOTE_ADDR before
	// trusting CF-Connecting-IP, same defense as mod-maintenance/mod-views).
	$ip       = rd_get_client_ip();
	$rate_key = 'rd_csp_rate_' . md5( $ip );
	$attempts = (int) get_transient( $rate_key );
	if ( $attempts >= RD_CSP_RATE_LIMIT_MAX ) {
		// 429 with Retry-After tells the browser to slow down; in practice
		// the browser doesn't honor it for CSP reports, but it's semantically correct.
		return new WP_REST_Response( null, 429, array( 'Retry-After' => RD_CSP_RATE_LIMIT_WINDOW ) );
	}
	set_transient( $rate_key, $attempts + 1, RD_CSP_RATE_LIMIT_WINDOW );

	$body = $request->get_body();
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || ! isset( $data['csp-report'] ) || ! is_array( $data['csp-report'] ) ) {
		return new WP_REST_Response(
			array(
				'ok'    => false,
				'error' => 'malformed',
			),
			400
		);
	}
	$report = $data['csp-report'];

	// Noise filter — drop browser-extension injections (Layer A) and admin-listed
	// hosts (Layer B) BEFORE storing, so the panel shows only real violations.
	// Returns 204 (same as a stored report) so the browser sees nothing unusual.
	$denylist = rd_csp_parse_report_denylist( rd_get_option( 'csp_report_denylist' ) );
	if ( rd_csp_report_is_noise( $report, $denylist ) ) {
		return new WP_REST_Response( null, 204 );
	}

	// Defensive whitelist — only saves known fields (avoids polluting the option
	// if a browser/extension sends extra junk)
	$entry = array(
		'timestamp'           => time(),
		'document_uri'        => isset( $report['document-uri'] ) ? sanitize_text_field( (string) $report['document-uri'] ) : '',
		'referrer'            => isset( $report['referrer'] ) ? sanitize_text_field( (string) $report['referrer'] ) : '',
		'violated_directive'  => isset( $report['violated-directive'] ) ? sanitize_text_field( (string) $report['violated-directive'] ) : '',
		'effective_directive' => isset( $report['effective-directive'] ) ? sanitize_text_field( (string) $report['effective-directive'] ) : '',
		'blocked_uri'         => isset( $report['blocked-uri'] ) ? sanitize_text_field( (string) $report['blocked-uri'] ) : '',
		'source_file'         => isset( $report['source-file'] ) ? sanitize_text_field( (string) $report['source-file'] ) : '',
		'line_number'         => isset( $report['line-number'] ) ? (int) $report['line-number'] : 0,
		'user_agent'          => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
	);

	$reports = get_option( RD_CSP_REPORTS_OPTION, array() );
	if ( ! is_array( $reports ) ) {
		$reports = array();
	}
	array_unshift( $reports, $entry ); // newest on top
	$reports = array_slice( $reports, 0, RD_CSP_REPORTS_MAX );

	// autoload=no so we don't bloat WP loading — we only read it in admin
	update_option( RD_CSP_REPORTS_OPTION, $reports, false );

	return new WP_REST_Response( null, 204 );
}

/*
=============================================================================
 *  PUBLIC HELPERS — for the panel to consume
 * ============================================================================= */

/**
 * Returns stored reports (newest first).
 */
function rd_csp_get_reports(): array {
	$reports = get_option( RD_CSP_REPORTS_OPTION, array() );
	return is_array( $reports ) ? $reports : array();
}

/**
 * Clears all stored reports. Called by the "Clear reports" button
 * in the panel via a handler on admin_init.
 */
function rd_csp_clear_reports(): void {
	update_option( RD_CSP_REPORTS_OPTION, array(), false );
}

/**
 * Aggregates stored reports by `violated_directive`.
 *
 * Used by the "Violations by Directive" doughnut chart (Wave 11 Phase G).
 * Returns an associative array `[ 'script-src-elem' => 12, 'style-src-attr' => 7, ... ]`
 * already sorted from largest to smallest.
 *
 * @return int[] Map of directive -> count, sorted by count desc.
 */
function rd_csp_get_violations_by_directive(): array {
	$reports = rd_csp_get_reports();
	$counts  = array();

	foreach ( $reports as $r ) {
		$dir = isset( $r['violated_directive'] ) ? (string) $r['violated_directive'] : '';
		if ( '' === $dir ) {
			$dir = 'unknown';
		}
		// Normalize: WP sometimes appends the blocked-uri to the directive ("style-src-elem inline").
		// For the chart, we only want the root directive — split on the first space.
		$dir            = strtok( $dir, ' ' );
		$counts[ $dir ] = ( $counts[ $dir ] ?? 0 ) + 1;
	}

	arsort( $counts ); // descending order — biggest violation first.
	return $counts;
}

/*
=============================================================================
 *  NONCE PROPAGATION — automatically adds nonce to WP-managed resources
 * ============================================================================= */

/**
 * Adds `nonce="..."` to every `<script src="...">` that WP renders via
 * `wp_enqueue_script()`. Runs on the `script_loader_tag` filter.
 *
 * Needed for 'strict-dynamic' to work: the root script must have a nonce
 * for the "trust" to propagate to the scripts it loads dynamically.
 *
 * @param string $tag HTML of the `<script>` tag.
 * @return string Tag with nonce injected, or unchanged if CSP OFF.
 */
function rd_csp_add_nonce_to_script_tag( $tag ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $tag;
	}
	// rd_csp_inject_nonce is idempotent — if it already has a nonce, it doesn't duplicate.
	return rd_csp_inject_nonce( $tag );
}
add_filter( 'script_loader_tag', 'rd_csp_add_nonce_to_script_tag', 99 );

/**
 * Adds `nonce="..."` to every `<link rel="stylesheet">` enqueued via WP.
 * Even though `<link>` is external (not inline), browsers honor a nonce on
 * stylesheets when style-src doesn't have 'strict-dynamic' available — some
 * scanners also expect a nonce on all stylesheets to give a full score.
 *
 * Inline styles (`wp_add_inline_style`) don't go through their own filter before
 * WP 6.x — in modern versions, WP uses `wp_get_inline_style_tag()` which
 * has NO public filter. We keep 'unsafe-inline' in style-src as a fallback
 * to cover those cases.
 *
 * @param string $tag HTML of the `<link>` or `<style>` tag.
 * @return string Tag with nonce, or unchanged if CSP OFF.
 */
function rd_csp_add_nonce_to_style_tag( $tag ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $tag;
	}
	// Inject nonce into <link> and <style> that don't have the attribute yet.
	$pattern     = '/<(link|style)(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<$1 nonce="' . esc_attr( $nonce ) . '"$2';
	$result      = preg_replace( $pattern, $replacement, $tag );
	return null !== $result ? $result : $tag;
}
add_filter( 'style_loader_tag', 'rd_csp_add_nonce_to_style_tag', 99 );

/**
 * Adds a nonce to the attributes array of inline scripts generated via
 * `wp_add_inline_script()` (WP 5.7+). This is the canonical path — when
 * a plugin or theme calls `wp_add_inline_script('handle', $code)`, WP
 * renders with `wp_print_inline_script_tag()` which respects this filter.
 *
 * @param array $attributes Map of inline tag attributes.
 * @return array Map with `nonce` added if CSP is ON.
 */
function rd_csp_add_nonce_to_inline_attrs( $attributes ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $attributes;
	}
	if ( ! is_array( $attributes ) ) {
		return $attributes;
	}
	if ( empty( $attributes['nonce'] ) ) {
		$attributes['nonce'] = $nonce;
	}
	return $attributes;
}
add_filter( 'wp_inline_script_attributes', 'rd_csp_add_nonce_to_inline_attrs' );
add_filter( 'wp_script_attributes', 'rd_csp_add_nonce_to_inline_attrs' );
// Equivalents for <style> — WP/Gutenberg uses wp_get_inline_style_tag() in
// some modern block supports. These filters cover the "new" path.
add_filter( 'wp_inline_style_attributes', 'rd_csp_add_nonce_to_inline_attrs' );
add_filter( 'wp_style_attributes', 'rd_csp_add_nonce_to_inline_attrs' );

/**
 * Output buffer on wp_head: covers the <style> that escape all filters.
 *
 * Real case (WP 6.x): `WP_Styles::print_inline_style()` does `printf( "<style id='%s-inline-css'...>" )`
 * directly without going through any filter. It hits inline styles registered via
 * `wp_add_inline_style($handle, $data)` — a pattern used by WP core for
 * "wp-img-auto-sizes-contain-inline-css", "core-block-supports-inline-css",
 * and by various plugins/themes. Filters don't catch those cases.
 *
 * Robust solution: open an output buffer at the start of wp_head with a callback
 * that adds a nonce to any nonce-less <style> in the resulting HTML.
 * The buffer stays open until the end of the request (PHP auto-closes it) and the regex is
 * idempotent (negative lookahead skips already-nonce'd tags).
 *
 * Overhead: 1 regex over the full page HTML per request. For typical
 * pages (~100KB of HTML) it's microseconds. Acceptable.
 *
 * @param string $html HTML accumulated in the buffer (usually the full response).
 * @return string HTML with a nonce added to nonce-less <style>.
 */
function rd_csp_late_style_nonce_filter( $html ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $html;
	}
	// Negative lookahead (?!...) skips <style> that already has nonce= — idempotent.
	$pattern     = '/<style(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<style nonce="' . esc_attr( $nonce ) . '"$1';
	$result      = preg_replace( $pattern, $replacement, $html );
	return null !== $result ? $result : $html;
}

/**
 * Opens the late-stage output buffer (very low priority) on wp_head, registering
 * the callback above. PHP closes the buffer automatically at the end of the request,
 * applying the filter over all accumulated HTML.
 */
function rd_csp_start_late_buffer() {
	if ( '' === rd_csp_nonce() ) {
		return;
	}
	ob_start( 'rd_csp_late_style_nonce_filter' );
}
// Very low priority to open the buffer BEFORE any other hook on wp_head.
add_action( 'wp_head', 'rd_csp_start_late_buffer', -PHP_INT_MAX );

/**
 * Intercepts the panel's "Clear reports" link. admin_init hook to run
 * BEFORE any output (needed for wp_safe_redirect to work).
 */
function rd_csp_handle_clear_request() {
	if ( empty( $_GET['rd_csp_clear'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rd_csp_clear' ) ) {
		return;
	}
	rd_csp_clear_reports();
	wp_safe_redirect( remove_query_arg( array( 'rd_csp_clear', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'rd_csp_handle_clear_request' );

/*
=============================================================================
 *  RENDER — callback for the "CSP Reports" section in the Security panel
 * ============================================================================= */

/**
 * Renders a view of the received CSP reports. Called as the callback of
 * `add_settings_section('sec_seg_csp_reports', ...)` in panel.php.
 *
 * Uses the rd-p* design system (Wave 11 Phase E refactor) — wrapper, header and
 * card come from the helpers in inc/panel-helpers.php. Only the table keeps
 * its own classes (`.rd-csp-table*`) since they're feature-specific.
 */
function rd_csp_render_reports_panel(): void {
	// Section header with icon (empty title in add_settings_section avoids a duplicate <h2>).
	rd_panel_section_header(
		array(
			'icon'  => 'warning',
			'title' => __( 'CSP Violation Reports', 'reloaded' ),
		)
	);

	// Feature OFF: simple empty state.
	if ( ! rd_get_option_bool( 'enable_csp_report_only' ) ) {
		rd_panel_dash_open();
		rd_panel_card_open();
		rd_panel_empty( __( 'Enable the toggle above to start collecting reports. Then browse the frontend — violations will appear here.', 'reloaded' ) );
		rd_panel_card_close();
		rd_panel_dash_close();
		return;
	}

	$reports    = rd_csp_get_reports();
	$clear_url  = wp_nonce_url( add_query_arg( 'rd_csp_clear', '1' ), 'rd_csp_clear' );
	$is_enforce = rd_get_option_bool( 'csp_enforce_mode' );

	// Active mode badge (Report-Only vs Enforce) — clear visual feedback.
	$mode_badge = $is_enforce
		? rd_panel_badge( 'danger', __( 'ENFORCE MODE', 'reloaded' ) )
		: rd_panel_badge( 'info', __( 'REPORT-ONLY', 'reloaded' ) );

	$info = $mode_badge . ' ' . sprintf(
		/* translators: 1: number of reports stored; 2: max storage capacity (FIFO discards older) */
		esc_html__( '%1$d reports stored (FIFO max %2$d). Open browser DevTools → Console for live violations.', 'reloaded' ),
		count( $reports ),
		(int) RD_CSP_REPORTS_MAX
	);

	$action = '';
	if ( ! empty( $reports ) ) {
		$action = sprintf(
			'<a href="%1$s" class="button button-secondary" onclick="return confirm(\'%2$s\')"><span class="dashicons dashicons-trash"></span> %3$s</a>',
			esc_url( $clear_url ),
			esc_js( __( 'Clear all stored CSP reports?', 'reloaded' ) ),
			esc_html__( 'Clear reports', 'reloaded' )
		);
	}

	rd_panel_dash_open();
	rd_panel_dash_header(
		array(
			'info'   => $info,
			'action' => $action,
		)
	);

	// Reports panel body. No reports → single full-width empty-state card.
	// With reports → "Violations by Directive" doughnut (narrow) + the reports
	// table (wide) side by side via .rd-pgrid--sidebar-main (1fr 2fr).
	if ( empty( $reports ) ) {
		rd_panel_card_open();
		rd_panel_empty( __( 'No reports yet — either nothing violated the policy, or no one has loaded the frontend since the feature was enabled.', 'reloaded' ) );
		rd_panel_card_close();
	} else {
		$by_directive = rd_csp_get_violations_by_directive();
		$has_chart    = ! empty( $by_directive );

		if ( $has_chart ) {
			echo '<div class="rd-pgrid rd-pgrid--sidebar-main">';

			// Chart card — narrow (sidebar) column.
			rd_panel_card_open(
				array(
					'title' => __( 'Violations by Directive', 'reloaded' ),
					'desc'  => __( 'Distribution of recorded violations grouped by CSP directive. Hover slices for exact counts and percentages.', 'reloaded' ),
				)
			);
			?>
			<div class="rd-csp-chart-wrapper">
				<canvas id="rd-csp-violations-chart"
					data-rd-chart-type="doughnut"
					data-labels="<?php echo esc_attr( wp_json_encode( array_keys( $by_directive ) ) ); ?>"
					data-values="<?php echo esc_attr( wp_json_encode( array_values( $by_directive ) ) ); ?>"></canvas>
			</div>
			<?php
			rd_panel_card_close();
		}

		// Reports table card — wide (main) column.
		rd_panel_card_open();
		?>
		<table class="rd-csp-table">
			<thead>
				<tr>
					<th class="rd-csp-table__col-when"><?php esc_html_e( 'When', 'reloaded' ); ?></th>
					<th class="rd-csp-table__col-dir"><?php esc_html_e( 'Directive', 'reloaded' ); ?></th>
					<th><?php esc_html_e( 'Blocked URI', 'reloaded' ); ?></th>
					<th><?php esc_html_e( 'Document', 'reloaded' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $reports as $r ) : ?>
					<tr>
						<td class="rd-csp-table__when"><?php echo esc_html( wp_date( 'd/m H:i', (int) ( $r['timestamp'] ?? 0 ) ) ); ?></td>
						<td><code class="rd-csp-table__directive"><?php echo esc_html( $r['violated_directive'] ?? '' ); ?></code></td>
						<td><code class="rd-csp-table__uri"><?php echo esc_html( $r['blocked_uri'] ?? '' ); ?></code></td>
						<td><code class="rd-csp-table__uri"><?php echo esc_html( $r['document_uri'] ?? '' ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		rd_panel_card_close();

		if ( $has_chart ) {
			echo '</div>'; // .rd-pgrid--sidebar-main
		}
	}
	rd_panel_dash_close();
}
