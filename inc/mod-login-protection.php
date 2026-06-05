<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Login Protection — hide login URL + rate limit failed attempts      *
 *                                                                             *
 * Two defensive layers against brute-force and automated attempts:            *
 *                                                                             *
 *   1. HIDE URL (opt-in via non-empty slug): /wp-login.php returns 404,       *
 *      login only works via /{slug}. Stops the ~95% of bots that only know    *
 *      /wp-login.php as the endpoint. Automated bots hit it, get 404,         *
 *      leave. Drastically reduces noise in the logs.                          *
 *                                                                             *
 *   2. RATE LIMIT: per-IP failure tracking via transient. After N attempts    *
 *      within an M-minute window, blocks the next ones with WP_Error. Reuses  *
 *      `rd_get_client_ip()` (Wave 9 A) — protection against header spoofing   *
 *      via the panel's Trusted Proxy whitelist.                               *
 *                                                                             *
 * Philosophy: own theme, no external plugins. Recovery hatch via WP-CLI or    *
 * direct SQL if the admin locks themselves out — `wp option patch update      *
 * rd_settings login_secret_slug ""` unlocks (slug "" disables hide URL, keeps *
 * login accessible at /wp-login.php).                                         *
 ******************************************************************************/

/*******************************************************************************
 * Slug helpers                                                                *
 ******************************************************************************/

/**
 * Sanitizes a login slug.
 * Allowed: a-z, 0-9, hyphen. Length 3-50. Lowercased.
 * Returns '' if invalid or in the reserved list (gracefully disables).
 *
 * @param mixed $raw Raw value from the form or option.
 * @return string Sanitized slug or '' if the feature should stay dormant.
 */
function rd_login_sanitize_slug( $raw ) {
	$slug = strtolower( trim( (string) $raw ) );
	if ( '' === $slug ) {
		return '';
	}
	if ( ! preg_match( '/^[a-z0-9-]{3,50}$/', $slug ) ) {
		return '';
	}
	// Reserved — forbidden to avoid conflict with WP core / our routes
	$reserved = array(
		'wp-admin',
		'wp-login',
		'wp-content',
		'wp-includes',
		'wp-json',
		'admin',
		'login',
		'feed',
		'rss',
		'sitemap',
		'sitemap_index',
		'robots',
		'favicon',
		'index',
		'search',
		'page',
		'author',
		'category',
		'tag',
		'date',
	);
	if ( in_array( $slug, $reserved, true ) ) {
		return '';
	}
	return $slug;
}

/**
 * Returns the configured, sanitized slug, or '' if the feature is dormant.
 *
 * @return string
 */
function rd_login_get_slug() {
	// Master switch: when enable_login_protection is OFF, returns '' so
	// all dependent features (hide URL, 4xx on /wp-login.php,
	// link rewriting) treat it as dormant. The saved slug is preserved in
	// the database — the admin can re-enable without loss.
	if ( ! rd_get_option_bool( 'enable_login_protection', true ) ) {
		return '';
	}
	$raw = rd_get_option( 'login_secret_slug', '' );
	return rd_login_sanitize_slug( $raw );
}

/**
 * Returns the full login URL (for display in the panel).
 *
 * @return string Absolute URL with slug, or wp-login.php if the feature is dormant.
 */
function rd_login_get_full_url() {
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return wp_login_url();
	}
	return home_url( '/' . $slug );
}

/*******************************************************************************
 * Request interception — implements the "Hide URL" layer                      *
 *                                                                             *
 * Hook on `wp_loaded` priority 1. When the slug is set:                       *
 *   - /SLUG  → require_once of wp-login.php, exit (login renders)             *
 *   - /wp-login.php directly (SCRIPT_NAME) → 404 + theme template             *
 *                                                                             *
 * Why `wp_loaded` and not `plugins_loaded`:                                   *
 * `plugins_loaded` fires BEFORE `functions.php` is loaded, so                 *
 * our `add_action` isn't even registered yet when that action passes —        *
 * result: the hook never fired. `wp_loaded` is the LAST action fired by       *
 * wp-settings.php, a guarantee that theme + plugins + init already ran.       *
 *                                                                             *
 * Note: SCRIPT_NAME is set by Nginx, not by PHP. Trustworthy.                 *
 ******************************************************************************/

/**
 * Intercepts the request on `wp_loaded` (last action before the template).
 * Decides between: serving login via slug, blocking direct access to wp-login,
 * or letting it pass (any other URL).
 */
function rd_login_handle_request() {
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return; // dormant feature — pass
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] )
		? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: '';

	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( ! is_string( $request_path ) ) {
		return;
	}
	$request_path_trimmed = trim( $request_path, '/' );

	// Case 1: access via /SLUG → loads wp-login.php manually
	if ( $request_path_trimmed === $slug ) {
		define( 'RD_LOGIN_VIA_SLUG', true );
		rd_login_serve_via_slug();
	}

	// Case 2: direct access to /wp-login.php without going through the slug → 404
	// Checks SCRIPT_NAME (set by Nginx, not spoofable via PHP)
	$script_name = isset( $_SERVER['SCRIPT_NAME'] )
		? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) )
		: '';

	if ( 'wp-login.php' === basename( $script_name ) && ! defined( 'RD_LOGIN_VIA_SLUG' ) ) {
		// Allows legitimate admin actions (logout, etc.) when the user is already logged in
		// and accesses via a valid session cookie. Logged-in user = not a brute-forcer.
		if ( is_user_logged_in() ) {
			return;
		}

		// Anonymous direct hit on wp-login.php — blocks with the theme's branded 404
		status_header( 404 );
		nocache_headers();
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}
		if ( file_exists( get_template_directory() . '/404.php' ) ) {
			include get_template_directory() . '/404.php';
		} else {
			wp_die( 'Not found', 'Not found', array( 'response' => 404 ) );
		}
		exit;
	}
}
add_action( 'wp_loaded', 'rd_login_handle_request', 1 );

/*******************************************************************************
 * Anti-leak: blocks /wp-admin/* for anonymous users                           *
 *                                                                             *
 * Without this hook, an anonymous hit on /wp-admin/ → WP detects "not logged  *
 * in" → `auth_redirect()` → `wp_login_url()` → our filter rewrites to /SLUG → *
 * response `302 Location: /SLUG?...` → bot reads header and finds the slug.   *
 *                                                                             *
 * Fix: intercept before auth_redirect and redirect to home (not to            *
 * login). Bot only sees `Location: /` in the header — slug stays preserved.   *
 *                                                                             *
 * Exceptions (endpoints with legitimate public actions, we let WP handle):    *
 *   - `admin-ajax.php` (detected via `wp_doing_ajax()` — nopriv actions)      *
 *   - `admin-post.php`  (admin_post_nopriv_* actions)                         *
 *                                                                             *
 * Hook on `init` priority 1 — fires after wp-settings.php but before the      *
 * admin page loads / outputs content. Headers not sent yet, redirect OK.      *
 ******************************************************************************/

/**
 * Blocks anonymous access to /wp-admin/, redirecting to home instead of
 * allowing WP's default redirect to login (which would leak the slug).
 *
 * @return void
 */
function rd_login_block_wpadmin_for_anonymous() {
	if ( '' === rd_login_get_slug() ) {
		return; // dormant feature
	}
	if ( ! is_admin() ) {
		return; // not the admin area
	}
	if ( is_user_logged_in() ) {
		return; // logged-in user is entitled to /wp-admin/
	}
	if ( wp_doing_ajax() ) {
		return; // admin-ajax.php (public actions via wp_ajax_nopriv_*)
	}

	$script_name = isset( $_SERVER['SCRIPT_NAME'] )
		? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) )
		: '';
	if ( 'admin-post.php' === basename( $script_name ) ) {
		return; // admin_post_nopriv_* actions
	}

	// Anonymous on any other /wp-admin/ route → redirect to home
	// (NOT to login — that would leak the slug via the Location header)
	wp_safe_redirect( home_url( '/' ) );
	exit;
}
add_action( 'init', 'rd_login_block_wpadmin_for_anonymous', 1 );

/**
 * Serves wp-login.php from the `wp_loaded` hook when the user accesses /SLUG.
 *
 * Wp-login.php was written assuming it runs as an entry script (variables in
 * global scope). When we load it via require_once from inside a function,
 * those variables become local — and WP 7.0 reports `Undefined variable` for
 * `$user_login`, `$error`, `$errors`, etc., leaking warnings inside the form
 * fields. Solution: declare them as `global` before the require so its
 * assignments propagate to the expected scope, and pre-initialize the 2 that
 * WP 7.0 complains about on the first GET request.
 */
function rd_login_serve_via_slug() {
	global $action, $user_login, $error, $errors, $interim_login;
	global $customize_login, $user, $redirect_to, $secure_cookie, $reauth;

	// Pre-init to suppress the 2 recurring warnings on the first GET
	// (there's no POST yet, so wp-login.php doesn't pass through the branch that
	// declares these variables). Empty strings = neutral behavior.
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional: wp-login.php expects these variables in the scope where we require it, pre-init avoids warnings leaking inside the form fields.
	$user_login = '';
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- same.
	$error = '';

	require_once ABSPATH . 'wp-login.php';
	exit;
}

/*******************************************************************************
 * URL filters — make WP generate URLs with the slug                           *
 *                                                                             *
 * When the slug is set, every place in WP that generates a login URL must use *
 * the slug. Covers: wp_login_url, wp_logout_url, wp_lostpassword_url,         *
 * notification emails, wp_safe_redirect redirects, etc.                       *
 ******************************************************************************/

/**
 * Rewrites the login URL to use the slug.
 *
 * @param string $login_url    URL generated by WP (usually wp-login.php).
 * @param string $redirect     Redirect_to param, if any.
 * @param bool   $force_reauth Flag to force re-auth.
 * @return string
 */
function rd_login_filter_login_url( $login_url, $redirect, $force_reauth ) {
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return $login_url;
	}
	$url = home_url( '/' . $slug );
	if ( ! empty( $redirect ) ) {
		$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
	}
	if ( $force_reauth ) {
		$url = add_query_arg( 'reauth', '1', $url );
	}
	return $url;
}
add_filter( 'login_url', 'rd_login_filter_login_url', 10, 3 );

/**
 * Rewrites `site_url('wp-login.php')` that WP uses in notification emails,
 * registrations, lost password, etc.
 *
 * @param string      $url     Generated URL.
 * @param string      $path    Path passed to site_url().
 * @param string|null $scheme  Scheme (https/http/login/etc).
 * @return string
 */
function rd_login_filter_site_url( $url, $path, $scheme = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $scheme required by the `site_url`/`network_site_url` filter signature, we don't use it.
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return $url;
	}
	if ( is_string( $path ) && 0 === strpos( $path, 'wp-login.php' ) ) {
		$url = str_replace( 'wp-login.php', $slug, $url );
	}
	return $url;
}
add_filter( 'site_url', 'rd_login_filter_site_url', 10, 3 );
add_filter( 'network_site_url', 'rd_login_filter_site_url', 10, 3 );

/**
 * Rewrites the logout URL (preserves _wpnonce and action).
 *
 * @param string $logout_url URL generated by WP.
 * @param string $redirect   Redirect_to param, if any.
 * @return string
 */
function rd_login_filter_logout_url( $logout_url, $redirect = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $redirect required by the `logout_url` filter signature, we preserve it via parse_str of the original query.
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return $logout_url;
	}
	// Extract the original query args (action=logout, _wpnonce, redirect_to)
	$parts = wp_parse_url( $logout_url );
	if ( empty( $parts['query'] ) ) {
		return home_url( '/' . $slug );
	}
	parse_str( $parts['query'], $args );
	$new_url = home_url( '/' . $slug );
	foreach ( $args as $key => $val ) {
		$new_url = add_query_arg( $key, rawurlencode( (string) $val ), $new_url );
	}
	return $new_url;
}
add_filter( 'logout_url', 'rd_login_filter_logout_url', 10, 2 );

/**
 * Rewrites the lost password URL.
 *
 * @param string $url Generated URL.
 * @return string
 */
function rd_login_filter_lostpassword_url( $url ) {
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return $url;
	}
	return add_query_arg( 'action', 'lostpassword', home_url( '/' . $slug ) );
}
add_filter( 'lostpassword_url', 'rd_login_filter_lostpassword_url' );

/*******************************************************************************
 * Rate limit — blocks IPs with too many failed attempts                       *
 *                                                                             *
 * Storage: transients (WP_Cache backend if Redis active, else options table). *
 * Reuses rd_get_client_ip() for protection against header spoofing (Wave 9A). *
 ******************************************************************************/

/**
 * Checks whether the current IP is rate-limited.
 *
 * @return bool
 */
function rd_login_is_rate_limited() {
	$ip = function_exists( 'rd_get_client_ip' ) ? rd_get_client_ip() : '';
	if ( '' === $ip ) {
		return false; // no IP = we can't limit, better to allow
	}
	$max      = max( 1, (int) rd_get_option( 'login_rate_limit_max', 5 ) );
	$key      = 'rd_login_attempts_' . md5( $ip );
	$attempts = (int) get_transient( $key );
	return $attempts >= $max;
}

/**
 * Increments the failure counter for the current IP.
 *
 * @return void
 */
function rd_login_record_failure() {
	// Master switch: rate limit is disabled when login protection is OFF.
	if ( ! rd_get_option_bool( 'enable_login_protection', true ) ) {
		return;
	}
	$ip = function_exists( 'rd_get_client_ip' ) ? rd_get_client_ip() : '';
	if ( '' === $ip ) {
		return;
	}
	$window_minutes = max( 1, (int) rd_get_option( 'login_rate_limit_window', 15 ) );
	$window_seconds = $window_minutes * 60;
	$key            = 'rd_login_attempts_' . md5( $ip );
	$attempts       = (int) get_transient( $key );
	set_transient( $key, $attempts + 1, $window_seconds );
}
add_action( 'wp_login_failed', 'rd_login_record_failure' );

/**
 * Clears the counter on a successful login.
 */
function rd_login_clear_attempts() {
	$ip = function_exists( 'rd_get_client_ip' ) ? rd_get_client_ip() : '';
	if ( '' === $ip ) {
		return;
	}
	delete_transient( 'rd_login_attempts_' . md5( $ip ) );
}
add_action( 'wp_login', 'rd_login_clear_attempts' );

/**
 * Filters authentication to block when rate-limited.
 * Runs BEFORE the password check — the attacker doesn't even get to try a password.
 *
 * @param mixed  $user     WP_User|WP_Error|null from previous filters.
 * @param string $username Submitted username.
 * @param string $password Submitted password (not validated here — rate check only).
 * @return WP_User|WP_Error|null
 */
function rd_login_authenticate_check( $user, $username, $password ) {
	// Master switch: rate-limit blocking is disabled when OFF.
	if ( ! rd_get_option_bool( 'enable_login_protection', true ) ) {
		return $user;
	}
	// Only intervenes on real login attempts (not on empty auth chain calls)
	if ( empty( $username ) && empty( $password ) ) {
		return $user;
	}
	if ( rd_login_is_rate_limited() ) {
		$window = (int) rd_get_option( 'login_rate_limit_window', 15 );
		return new WP_Error(
			'rd_rate_limited',
			sprintf(
				/* translators: %d: number of minutes */
				__( '<strong>Error:</strong> Too many failed login attempts. Try again in %d minutes.', 'reloaded' ),
				$window
			)
		);
	}
	return $user;
}
add_filter( 'authenticate', 'rd_login_authenticate_check', 30, 3 );

/*******************************************************************************
 * Anti user enumeration                                                       *
 *                                                                             *
 * WP by default shows distinct messages for "user doesn't exist" vs "wrong    *
 * password", revealing to the attacker which usernames are valid. OWASP has   *
 * recommended a generic message for decades. This implementation replaces the *
 * 3 compromising error codes (`incorrect_password`, `invalid_username`,       *
 * `invalid_email`) with a single "Invalid username or password" message —     *
 * the attacker can't distinguish.                                             *
 *                                                                             *
 * Toggle (`login_hide_user_enumeration`) default ON. Override to OFF if you   *
 * want more "friendly" error messages for forgetful legitimate users.         *
 ******************************************************************************/

/**
 * Rewrites login errors that reveal user existence, replacing them with
 * a generic message. Runs on the `wp_login_errors` filter that receives the
 * aggregated WP_Error — we have access to the error codes (not just formatted text).
 *
 * @param WP_Error|mixed $errors       Aggregated errors from the login form.
 * @param string         $redirect_to  Redirect destination after login (unused).
 * @return WP_Error|mixed Possibly replaced errors.
 */
function rd_login_hide_user_enumeration( $errors, $redirect_to ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $redirect_to required by the wp_login_errors filter signature.
	if ( ! rd_get_option_bool( 'login_hide_user_enumeration' ) ) {
		return $errors;
	}
	if ( ! is_wp_error( $errors ) ) {
		return $errors;
	}

	// Codes that reveal user existence via a distinct message.
	$enumeration_codes = array(
		'incorrect_password',
		'invalid_username',
		'invalid_email',
		'invalidcombo', // some plugins / old WP versions
	);

	$codes = $errors->get_error_codes();
	if ( array_intersect( $codes, $enumeration_codes ) ) {
		return new WP_Error(
			'rd_invalid_credentials',
			__( '<strong>Error:</strong> Invalid username or password.', 'reloaded' )
		);
	}

	return $errors;
}
add_filter( 'wp_login_errors', 'rd_login_hide_user_enumeration', 100, 2 );
