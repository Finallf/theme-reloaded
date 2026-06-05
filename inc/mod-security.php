<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Security — Security headers (clickjacking, MIME sniffing, etc.)     *
 *******************************************************************************/

/**
 * Canonical list of the security headers applied by the theme.
 *
 * Centralized in a single function to ensure consistency between the frontend
 * (action `send_headers`) and the REST API (filter `rest_pre_serve_request`) —
 * the two WP code paths that need to emit headers separately.
 *
 * @return array<string,string> Header → value map.
 */
function rd_get_security_headers_map() {
	return array(
		// Anti-clickjacking — only allows iframes from the same origin
		'X-Frame-Options'        => 'SAMEORIGIN',
		// Browser must respect the declared Content-Type, no "guessing" from content
		'X-Content-Type-Options' => 'nosniff',
		// Limits the cross-origin referer: sends origin only (no path), nothing on HTTPS→HTTP
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		// Blocks sensitive features the theme doesn't use (defense in depth)
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=()',
	);
}

/**
 * Sends the security headers on the frontend.
 *
 * Applies only on the frontend so it doesn't interfere with internal iframes,
 * heavy inline scripts and AJAX that the WP admin panel uses heavily.
 *
 * The panel toggle follows the safe migration pattern: existing
 * installations stay OFF until the admin consciously enables it; new
 * installations get ON by default via rd_set_default_options().
 */
function rd_send_security_headers() {
	if ( is_admin() ) {
		return;
	}
	if ( ! rd_get_option_bool( 'enable_security_headers' ) ) {
		return;
	}

	foreach ( rd_get_security_headers_map() as $name => $value ) {
		header( "{$name}: {$value}" );
	}
}
add_action( 'send_headers', 'rd_send_security_headers' );

/**
 * Applies the same security headers to REST API responses.
 *
 * The REST API uses its own code path (WP_REST_Server::serve_request()) that does
 * NOT fire the `send_headers` action. Without this hook, `/wp-json/*` endpoints are
 * left without X-Frame-Options, Referrer-Policy and Permissions-Policy — only X-Content-Type
 * (which WP core already adds) and HSTS (set by Nginx) remained.
 *
 * Finding from Wave 9 C (OWASP ZAP scan 2026-05-22).
 *
 * @param bool $served Whether the request has already been served.
 * @return bool Unchanged.
 */
function rd_send_security_headers_to_rest( $served ) {
	if ( ! rd_get_option_bool( 'enable_security_headers' ) ) {
		return $served;
	}

	foreach ( rd_get_security_headers_map() as $name => $value ) {
		header( "{$name}: {$value}" );
	}

	return $served;
}
add_filter( 'rest_pre_serve_request', 'rd_send_security_headers_to_rest' );

/*******************************************************************************
 * Disables the XML-RPC endpoint                                               *
 *                                                                             *
 * XML-RPC is WordPress's legacy API, used by old mobile apps and by the       *
 * pingback/trackback system. Since almost nobody uses it today, it tends      *
 * to be a target for brute-force and DDoS amplification (pingback.ping).      *
 *                                                                             *
 * The `xmlrpc_enabled` filter makes WP respond with 405 Method Not Allowed    *
 * on /xmlrpc.php, but keeps the file accessible (some hosts route through     *
 * it for healthchecks). To close everything, it also blocks the discovery     *
 * header (`X-Pingback`) and the link in <head>.                               *
 */
if ( rd_get_option_bool( 'disable_xmlrpc' ) ) {

	// 1. Block direct access to /xmlrpc.php (GET and POST) — returns 403.
	// Without this, a GET on the file shows "XML-RPC server accepts POST
	// requests only." (WP's hardcoded message before the filters).
	add_action(
		'init',
		function () {
			if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) &&
			basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) ) === 'xmlrpc.php' ) {
				status_header( 403 );
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo 'XML-RPC disabled.';
				exit;
			}
		}
	);

	// 2. Extra layer: in case some route reaches the XML-RPC server without
	// going through the file, the filter returns false on all methods.
	add_filter( 'xmlrpc_enabled', '__return_false' );

	// 3. Remove the X-Pingback header that WP sends on every page.
	add_filter(
		'wp_headers',
		function ( $headers ) {
			unset( $headers['X-Pingback'] );
			return $headers;
		}
	);

	// 4. Remove the <link rel="EditURI"> (RSD) from <head> — discovery hint.
	remove_action( 'wp_head', 'rsd_link' );
}

/*******************************************************************************
 * White Screen of Death (WSOD) — customizing the fatal error message          *
 *                                                                             *
 * When PHP fatals (out of memory, unhandled exception, etc), WordPress        *
 * 5.2+ shows a "There has been a critical error on this website." page.       *
 *                                                                             *
 *                                                                             *
 * The LAYOUT (HTML/CSS) can't be customized safely — on a fatal the theme     *
 * may not be functional, so WP intentionally uses a minimalist renderer.      *
 * But the TEXT can be customized via filters, making it branded.              *
 *                                                                             *
 * For a visually rich 500 error page you'd need to configure                  *
 * `php_value display_errors Off` on the server + a separate static HTML       *
 * page pointed to by ErrorDocument in .htaccess. That's outside theme scope.  *
 */

// Customizes the browser tab title on the fatal error screen
add_filter(
	'wp_php_error_args',
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $error is part of the `wp_php_error_args` filter signature, even when we only edit the title.
	function ( $args, $error ) {
		/* translators: %s: site name from WordPress general settings */
		$args['title'] = sprintf( __( 'Critical Error - %s', 'reloaded' ), get_bloginfo( 'name' ) );
		return $args;
	},
	10,
	2
);

/**
 * Fully branded WSOD message — replicates the look of the maintenance
 * card (`mod-maintenance.php`) by injecting an inline <style> that overrides
 * the default grayscale template of `_default_wp_die_handler`.
 *
 * Why inline and not enqueue: on a fatal the theme may be broken, so
 * the handler renders BEFORE `wp_head` has a chance to run. CSS injected
 * directly into the body works in any scenario.
 *
 * Why not use the mod-maintenance helper: that one has specific markup
 * (h1 with pulse animation, login form). WSOD needs something
 * static and isolated to avoid introducing a cross-module dependency in code
 * that runs during a crash.
 */
add_filter(
	'wp_php_error_message',
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $message and $error are part of the `wp_php_error_message` filter signature, but we rewrite the message from scratch (we don't use the default).
	function ( $message, $error ) {
		// Logo: tries to use WP's Custom Logo, but with a DEFENSIVE fallback because
		// WSOD runs when PHP fatalled — WP may be partially broken.
		// If rd_get_site_logo() isn't available (core.php didn't load
		// or the helper is unavailable), falls back to the hardcoded one without breaking.
		$logo_url = esc_url( get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp' );
		if ( function_exists( 'rd_get_site_logo' ) ) {
			$logo_data = rd_get_site_logo( 'medium' );
			if ( ! empty( $logo_data['url'] ) ) {
				$logo_url = esc_url( $logo_data['url'] );
			}
		}
		$site     = esc_attr( get_bloginfo( 'name' ) );
		$headline = esc_html__( 'Something went wrong', 'reloaded' );
		$body1    = esc_html__( 'The server returned an unexpected error. The site administrator has been automatically notified by email and will investigate as soon as possible.', 'reloaded' );
		$body2    = esc_html__( 'In the meantime, please try refreshing the page in a few minutes.', 'reloaded' );

		// CSP nonce — the WSOD handler runs via _default_wp_die_handler, outside
		// the normal wp_head flow, so it escapes mod-csp's output buffer.
		$nonce_attr = function_exists( 'rd_csp_nonce_attr' ) ? rd_csp_nonce_attr() : '';

		// Logo HTML wrapped in <picture> if there are next-gen sources on disk.
		// Defensive fallback to the raw <img> if mod-image-formats didn't load
		// (relevant scenario — WSOD runs when PHP fatalled, which could be exactly
		// in mod-image-formats).
		$logo_img_html = '<img src="' . $logo_url . '" alt="' . $site . '" class="rd-wsod-logo" width="250" height="58">';
		$logo_html     = function_exists( 'rd_img_wrap_url_in_picture' )
			? rd_img_wrap_url_in_picture( $logo_url, $logo_img_html )
			: $logo_img_html;

		return <<<HTML
<style{$nonce_attr}>
    :root {
        --dark-bg: #151515;
        --brand-blue-dark: #031CFF;
        --brand-blue-light: #00A8FF;
        --text-light: #f0f6fc;
        --text-muted: #8b949e;
        --glass-bg: rgba(255, 255, 255, 0.04);
        --glass-border: rgba(255, 255, 255, 0.08);
        --radius: 5px;
    }
    html { background: var(--dark-bg) !important; }
    body#error-page {
        background-color: var(--dark-bg) !important;
        color: var(--text-light) !important;
        font-family: "Inter", "Poppins", sans-serif, Arial !important;
        margin: 0 !important; padding: 0 !important;
        height: 100vh !important;
        display: flex !important; align-items: center !important; justify-content: center !important;
        border: none !important; box-shadow: none !important; max-width: 100% !important;
    }
    body#error-page .wp-die-message {
        border: none !important; background: transparent !important; box-shadow: none !important;
        padding: 0 !important; margin: 0 !important; display: flex; justify-content: center; width: 100%;
    }
    .rd-wsod-container { width: 100%; padding: 20px; display: flex; justify-content: center; }
    .rd-wsod-card {
        position: relative; overflow: hidden;
        background: var(--glass-bg); border: 1px solid var(--glass-border);
        border-radius: var(--radius); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
        padding: 50px 40px; max-width: 550px; width: 100%;
        text-align: center; box-sizing: border-box;
        animation: rdWsodFadeUp 600ms ease-out;
    }
    .rd-wsod-card::before {
        content: ""; position: absolute; top: 0; left: 0;
        width: 100%; height: 3px;
        background: linear-gradient(90deg, var(--brand-blue-dark), var(--brand-blue-light));
    }
    .rd-wsod-logo { max-width: 250px; height: auto; margin-bottom: 30px; }
    .rd-wsod-card h1 {
        color: var(--text-light); font-size: 26px; margin: 0 0 15px 0; font-weight: 600;
    }
    .rd-wsod-card p {
        color: var(--text-muted); font-size: 16px; line-height: 1.6; margin: 0 0 12px 0;
    }
    .rd-wsod-card p:last-child { margin-bottom: 0; }
    @keyframes rdWsodFadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>
<div class="rd-wsod-container">
    <div class="rd-wsod-card">
        {$logo_html}
        <h1>{$headline}</h1>
        <p>{$body1}</p>
        <p>{$body2}</p>
    </div>
</div>
HTML;
	},
	10,
	2
);
