<?php
defined( 'ABSPATH' ) || exit;
/**
 * Module: Maintenance - Portal Maintenance Mode (Secure Version)
 *
 * Implements authentication via a POST form, password hashing,
 * session tokens and rate limiting to protect against brute force.
 */

/**
 * Module settings
 */
const RD_MAINT_COOKIE_NAME       = 'rd_maint_token';
const RD_MAINT_COOKIE_LIFETIME   = DAY_IN_SECONDS;        // 24 hours (was 7 days)
const RD_MAINT_RATE_LIMIT_MAX    = 5;                     // 5 attempts
const RD_MAINT_RATE_LIMIT_WINDOW = 900;                  // every 15 minutes
const RD_MAINT_LOGIN_SLUG        = 'rd-dev-login';        // URL: /?rd-dev-login

/**
 * Main hook — checks whether to show maintenance
 */
function rd_maintenance_mode() {

	// If maintenance isn't active, do nothing
	if ( ! rd_get_option_bool( 'maintenance_mode' ) ) {
		return;
	}

	// Handle the login request (dev form)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- dispatcher: only checks the query string presence to route; the handler verifies the nonce on POST.
	if ( isset( $_GET[ RD_MAINT_LOGIN_SLUG ] ) ) {
		rd_maintenance_handle_login();
		// handle_login always exits, so this is never reached
	}

	// Allow access for: logged-in admin, dev with a valid token, WP login page
	if ( rd_maintenance_user_is_allowed() ) {
		return;
	}

	// Otherwise, show the maintenance screen
	rd_maintenance_render_screen();
}
add_action( 'template_redirect', 'rd_maintenance_mode' );

/**
 * Checks whether the current user is allowed to bypass maintenance
 */
function rd_maintenance_user_is_allowed() {

	// Logged-in WordPress admin (current_user_can already checks WP's secure session)
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	// Allow access to the WP login screen, so admins can get in
	if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
		return true;
	}

	// Check the dev token in the cookie
	if ( isset( $_COOKIE[ RD_MAINT_COOKIE_NAME ] ) ) {
		$token  = sanitize_text_field( wp_unslash( $_COOKIE[ RD_MAINT_COOKIE_NAME ] ) );
		$stored = get_transient( 'rd_maint_token_' . $token );

		if ( $stored !== false ) {
			return true;
		}
	}

	return false;
}

/**
 * Processes the dev login form
 */
function rd_maintenance_handle_login() {

	// Only accept the POST method to process login
	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
	if ( 'POST' !== $request_method ) {
		rd_maintenance_render_login_form();
		exit;
	}

	// Verify the nonce (CSRF protection)
	if ( ! isset( $_POST['rd_maint_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rd_maint_nonce'] ) ), 'rd_maint_login' ) ) {
		rd_maintenance_render_login_form( __( 'Session expired. Please try again.', 'reloaded' ) );
	}

	// Per-IP rate limiting — `rd_get_client_ip` validates REMOTE_ADDR before
	// trusting proxy headers (avoids bypass via a spoofed CF-Connecting-IP).
	$ip       = rd_get_client_ip();
	$rate_key = 'rd_maint_rate_' . md5( $ip );
	$attempts = (int) get_transient( $rate_key );

	if ( $attempts >= RD_MAINT_RATE_LIMIT_MAX ) {
		rd_maintenance_render_login_form( __( 'Too many attempts. Please wait a few minutes.', 'reloaded' ) );
		exit;
	}

	// Get the submitted password.
	// The password must NOT be sanitized — sanitize_text_field would destroy
	// valid password characters (whitespace, control chars). password_verify accepts
	// a raw string and bcrypt truncates at 72 bytes. Nonce + capability + rate-limit +
	// length cap (line below) applied as defense in depth.
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$submitted = isset( $_POST['rd_maint_password'] ) ? wp_unslash( $_POST['rd_maint_password'] ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	// Defensive length cap. bcrypt truncates at 72 bytes internally,
	// so accepting giant payloads only serves to force PHP to allocate memory
	// before password_verify (marginal DoS). Treat it as a failed attempt to
	// increment the rate-limit normally — the attacker doesn't get "free
	// attempts" by sending a large string.
	if ( strlen( $submitted ) > 200 ) {
		set_transient( $rate_key, $attempts + 1, RD_MAINT_RATE_LIMIT_WINDOW );
		rd_maintenance_render_login_form( __( 'Incorrect password.', 'reloaded' ) );
		exit;
	}

	// Get the stored hash (in rd_get_option)
	$stored_hash = rd_get_option( 'maintenance_pass_hash' );

	// No hash configured: block (secure by default)
	if ( empty( $stored_hash ) ) {
		rd_maintenance_render_login_form( __( 'Developer access not configured.', 'reloaded' ) );
		exit;
	}

	// Verify the password with password_verify (resistant to timing attacks)
	if ( ! password_verify( $submitted, $stored_hash ) ) {
		// Increment the attempts counter
		set_transient( $rate_key, $attempts + 1, RD_MAINT_RATE_LIMIT_WINDOW );

		rd_maintenance_render_login_form( __( 'Incorrect password.', 'reloaded' ) );
		exit;
	}

	// Correct password: clear rate limit, generate token, set cookie
	delete_transient( $rate_key );

	$token = bin2hex( random_bytes( 32 ) );  // 64 hex chars, cryptographically secure
	set_transient( 'rd_maint_token_' . $token, '1', RD_MAINT_COOKIE_LIFETIME );

	// Cookie with ALL the security flags
	setcookie(
		RD_MAINT_COOKIE_NAME,
		$token,
		array(
			'expires'  => time() + RD_MAINT_COOKIE_LIFETIME,
			'path'     => '/',
			'domain'   => '',
			'secure'   => is_ssl(),    // HTTPS only
			'httponly' => true,         // Inaccessible to JavaScript
			'samesite' => 'Lax',        // Defense against CSRF
		)
	);

	// Redirect to home (without exposing anything in the URL)
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

/**
 * Renders the maintenance screen (public)
 */
function rd_maintenance_render_screen() {
	$custom_text = rd_get_option( 'maintenance_text' );
	$mensagem    = ! empty( $custom_text )
		? wp_kses_post( $custom_text )
		: sprintf(
			/* translators: %s: site name from WordPress general settings */
			__( 'The %s portal is undergoing scheduled maintenance to bring you new features. Thank you for your patience.', 'reloaded' ),
			get_bloginfo( 'name' )
		);

	// Uses WP's Custom Logo if set, falls back to the theme's hardcoded logo.
	$logo_data = rd_get_site_logo( 'medium' );
	$logo_url  = $logo_data['url'];
	$html      = rd_maintenance_get_template_html( $logo_url, $mensagem );

	/*
	 * CSP: wp_die() calls WP core's _default_wp_die_handler() which injects a
	 * native <style type="text/css"> WITHOUT a nonce — it escapes our mod-csp
	 * output buffer (which operates in wp_head, and wp_die cuts the flow). Open a
	 * LOCAL output buffer here to intercept the wp_die output and add a
	 * nonce to the core <style> before the response goes out. PHP closes the buffer on
	 * shutdown and the callback applies the nonce to any nonce-less <style>.
	 */
	if ( function_exists( 'rd_csp_nonce' ) && '' !== rd_csp_nonce() ) {
		ob_start( 'rd_maintenance_nonce_filter' );
	}

	wp_die(
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html built by rd_maintenance_get_template_html() where all dynamic values are esc_url/esc_attr/esc_html/wp_kses_post'd internally.
		$html,
		esc_html(
			sprintf(
				/* translators: %s: site name from WordPress general settings */
				__( 'Scheduled Maintenance - %s', 'reloaded' ),
				get_bloginfo( 'name' )
			)
		),
		array( 'response' => 503 )
	);
}

/**
 * Output buffer callback — injects a nonce into nonce-less <style> in the wp_die HTML.
 * Idempotent (negative lookahead skips already-nonce'd tags).
 *
 * @param string $html HTML accumulated in the buffer.
 * @return string HTML with a nonce on every <style>.
 */
function rd_maintenance_nonce_filter( $html ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $html;
	}
	$pattern     = '/<style(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<style nonce="' . esc_attr( $nonce ) . '"$1';
	$result      = preg_replace( $pattern, $replacement, $html );
	return null !== $result ? $result : $html;
}

/**
 * Renders the dev login form
 */
function rd_maintenance_render_login_form( $error_message = '' ) {
	// Uses WP's Custom Logo if set, falls back to the theme's hardcoded logo.
	$logo_data = rd_get_site_logo( 'medium' );
	$logo_url  = $logo_data['url'];
	$nonce     = wp_create_nonce( 'rd_maint_login' );

	$error_html = '';
	if ( ! empty( $error_message ) ) {
		$error_html = '<p class="rd-maint-error">' . esc_html( $error_message ) . '</p>';
	}

	$form_html = '
        <form method="POST" action="' . esc_url( home_url( '/?' . RD_MAINT_LOGIN_SLUG ) ) . '" class="rd-maint-form">
            ' . $error_html . '
            <input type="hidden" name="rd_maint_nonce" value="' . esc_attr( $nonce ) . '">
            <label for="rd_maint_password">' . esc_html__( 'Developer Password:', 'reloaded' ) . '</label>
            <input type="password" id="rd_maint_password" name="rd_maint_password" required autocomplete="current-password">
            <button type="submit">' . esc_html__( 'Log In', 'reloaded' ) . '</button>
        </form>
    ';

	$html = rd_maintenance_get_template_html( $logo_url, '', $form_html );

	wp_die(
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html built by rd_maintenance_get_template_html() where all dynamic values are esc_url/esc_attr/esc_html/wp_kses_post'd internally.
		$html,
		esc_html(
			sprintf(
				/* translators: %s: site name from WordPress general settings */
				__( 'Restricted Access - %s', 'reloaded' ),
				get_bloginfo( 'name' )
			)
		),
		array( 'response' => 503 )
	);
}

/**
 * Template HTML compartilhado (com estilos)
 */
function rd_maintenance_get_template_html( $logo_url, $mensagem = '', $extra_html = '' ) {

	$content_html = '';
	if ( ! empty( $mensagem ) ) {
		$content_html .= '<h1 class="pulse-text">' . esc_html__( 'Working behind the scenes...', 'reloaded' ) . '</h1>';
		$content_html .= '<p>' . wp_kses_post( $mensagem ) . '</p>';
	}
	$content_html .= $extra_html;

	// CSP nonce — the template renders via wp_die() outside the normal wp_head
	// flow, so it escapes mod-csp's output buffer. We inject it manually.
	$nonce_attr = function_exists( 'rd_csp_nonce_attr' ) ? rd_csp_nonce_attr() : '';

	return '
    <style' . $nonce_attr . '>
        :root {
            --dark-bg: #151515;
            --brand-blue-dark: #031CFF;
            --brand-blue-light: #00A8FF;
            --text-light: #f0f6fc;
            --text-muted: #8b949e;
            --glass-bg: rgba(255, 255, 255, 0.04);
            --glass-border: rgba(255, 255, 255, 0.08);
            --error-color: #f85149;
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
        .rd-maintenance-container {
            width: 100%; padding: 20px; display: flex; justify-content: center;
        }
        .rd-maintenance-card {
            position: relative; overflow: hidden;
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            border-radius: var(--radius); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
            padding: 50px 40px; max-width: 550px; width: 100%;
            text-align: center; box-sizing: border-box;
        }
        .rd-maintenance-card::before {
            content: ""; position: absolute; top: 0; left: 0;
            width: 100%; height: 3px;
            background: linear-gradient(90deg, var(--brand-blue-dark), var(--brand-blue-light));
        }
        .rd-maintenance-logo { max-width: 250px; height: auto; margin-bottom: 30px; }
        .rd-maintenance-card h1 {
            color: var(--text-light); font-size: 26px; margin: 0 0 15px 0; font-weight: 600;
        }
        .rd-maintenance-card p {
            color: var(--text-muted); font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;
        }
        .rd-maint-form {
            display: flex; flex-direction: column; gap: 12px; margin-top: 20px;
        }
        .rd-maint-form label {
            color: var(--text-muted); font-size: 14px; text-align: left;
        }
        .rd-maint-form input[type="password"] {
            background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border);
            color: var(--text-light); padding: 12px; border-radius: var(--radius);
            font-size: 16px; font-family: inherit;
        }
        .rd-maint-form input[type="password"]:focus {
            outline: none; border-color: var(--brand-blue-light);
        }
        .rd-maint-form button {
            background: linear-gradient(90deg, var(--brand-blue-dark), var(--brand-blue-light));
            color: #fff; border: none; padding: 12px 24px;
            border-radius: var(--radius); font-size: 16px; font-weight: 600;
            cursor: pointer; font-family: inherit;
        }
        .rd-maint-form button:hover { opacity: 0.9; }
        .rd-maint-error {
            background: rgba(248, 81, 73, 0.1); border: 1px solid var(--error-color);
            color: var(--error-color) !important; padding: 10px; border-radius: var(--radius);
            font-size: 14px; margin: 0 !important;
        }
        @keyframes pulse {
            0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; }
        }
        .pulse-text { animation: pulse 2.5s infinite ease-in-out; }
        @keyframes rdMaintFadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .rd-maintenance-card { animation: rdMaintFadeUp 600ms ease-out; }
    </style>

    <div class="rd-maintenance-container">
        <div class="rd-maintenance-card">
            ' . rd_maintenance_render_logo( $logo_url ) . '
            ' . $content_html . '
        </div>
    </div>
    ';
}

/**
 * Helper: renders the logo's <img> tag, wrapped in <picture> with next-gen
 * sources if the images helper is available. Defensive — falls back to the
 * raw <img> if mod-image-formats didn't load (edge case scenario).
 */
function rd_maintenance_render_logo( string $logo_url ): string {
	$img_html = sprintf(
		'<img src="%1$s" alt="%2$s" class="rd-maintenance-logo" width="250" height="58">',
		esc_url( $logo_url ),
		esc_attr( get_bloginfo( 'name' ) )
	);
	if ( function_exists( 'rd_img_wrap_url_in_picture' ) ) {
		return rd_img_wrap_url_in_picture( $logo_url, $img_html );
	}
	return $img_html;
}

/**
 * Saves the password as a hash when the admin updates it in the panel
 *
 * Hook: pre_update_option_rd_settings (runs before saving options)
 *
 * Behavior:
 * - Password typed (new): generates a hash, clears the plain-text field
 * - Empty password (admin didn't touch it): preserves the existing hash from the DB
 */
function rd_maintenance_hash_password_on_save( $new_value, $old_value ) {

	// Ensure $new_value is an array (defensive)
	if ( ! is_array( $new_value ) ) {
		return $new_value;
	}

	// Retrieve the existing hash from the DB (if any)
	$existing_hash = is_array( $old_value ) && isset( $old_value['maintenance_pass_hash'] )
		? $old_value['maintenance_pass_hash']
		: '';

	// Get the typed password (plain text, if any)
	$new_password = isset( $new_value['maintenance_pass'] ) ? trim( $new_value['maintenance_pass'] ) : '';

	if ( ! empty( $new_password ) ) {
		// Admin typed a new password → generate a new hash
		$new_value['maintenance_pass_hash'] = password_hash( $new_password, PASSWORD_DEFAULT );
	} else {
		// Admin didn't type → preserve the existing hash
		$new_value['maintenance_pass_hash'] = $existing_hash;
	}

	// ALWAYS clear the plain-text field (never store plain text)
	$new_value['maintenance_pass'] = '';

	return $new_value;
}
add_filter( 'pre_update_option_rd_settings', 'rd_maintenance_hash_password_on_save', 10, 2 );
