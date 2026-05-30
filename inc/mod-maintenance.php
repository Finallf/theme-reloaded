<?php
defined( 'ABSPATH' ) || exit;
/**
 * Module: Maintenance - Modo de Manutenção do Portal (Versão Segura)
 *
 * Implementa autenticação via formulário POST, hash de senha,
 * tokens de sessão e rate limiting para proteger contra brute force.
 */

/**
 * Configurações do módulo
 */
const RD_MAINT_COOKIE_NAME       = 'rd_maint_token';
const RD_MAINT_COOKIE_LIFETIME   = DAY_IN_SECONDS;        // 24 horas (era 7 dias)
const RD_MAINT_RATE_LIMIT_MAX    = 5;                     // 5 tentativas
const RD_MAINT_RATE_LIMIT_WINDOW = 900;                  // a cada 15 minutos
const RD_MAINT_LOGIN_SLUG        = 'rd-dev-login';        // URL: /?rd-dev-login

/**
 * Hook principal — verifica se deve mostrar manutenção
 */
function rd_maintenance_mode() {

	// Se manutenção não está ativa, não faz nada
	if ( ! rd_get_option_bool( 'maintenance_mode' ) ) {
		return;
	}

	// Trata requisição de login (formulário do dev)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- dispatcher: só checa presença da query string pra rotear; o handler verifica nonce no POST.
	if ( isset( $_GET[ RD_MAINT_LOGIN_SLUG ] ) ) {
		rd_maintenance_handle_login();
		// handle_login sempre faz exit, então não chega aqui
	}

	// Permite acesso pra: admin logado, dev com token válido, página de login WP
	if ( rd_maintenance_user_is_allowed() ) {
		return;
	}

	// Caso contrário, exibe a tela de manutenção
	rd_maintenance_render_screen();
}
add_action( 'template_redirect', 'rd_maintenance_mode' );

/**
 * Verifica se o usuário atual tem permissão pra burlar a manutenção
 */
function rd_maintenance_user_is_allowed() {

	// Admin do WordPress logado (current_user_can já verifica sessão segura do WP)
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	// Permite acesso à tela de login do WP, pra admins poderem entrar
	if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
		return true;
	}

	// Verifica token de dev no cookie
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
 * Processa o formulário de login do dev
 */
function rd_maintenance_handle_login() {

	// Só aceita método POST pra processar login
	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
	if ( 'POST' !== $request_method ) {
		rd_maintenance_render_login_form();
		exit;
	}

	// Verifica nonce (proteção CSRF)
	if ( ! isset( $_POST['rd_maint_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rd_maint_nonce'] ) ), 'rd_maint_login' ) ) {
		rd_maintenance_render_login_form( __( 'Session expired. Please try again.', 'reloaded' ) );
	}

	// Rate limiting por IP — `rd_get_client_ip` valida REMOTE_ADDR antes de
	// confiar em headers de proxy (evita bypass via CF-Connecting-IP spoofado).
	$ip       = rd_get_client_ip();
	$rate_key = 'rd_maint_rate_' . md5( $ip );
	$attempts = (int) get_transient( $rate_key );

	if ( $attempts >= RD_MAINT_RATE_LIMIT_MAX ) {
		rd_maintenance_render_login_form( __( 'Too many attempts. Please wait a few minutes.', 'reloaded' ) );
		exit;
	}

	// Pega senha enviada.
	// Senha NÃO pode ser sanitizada — sanitize_text_field destruiria caracteres
	// válidos em senhas (whitespace, control chars). password_verify aceita
	// string raw e bcrypt trunca em 72 bytes. Nonce + capability + rate-limit +
	// length cap (linha abaixo) aplicados como defesa em profundidade.
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$submitted = isset( $_POST['rd_maint_password'] ) ? wp_unslash( $_POST['rd_maint_password'] ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	// Cap defensivo de comprimento. bcrypt trunca em 72 bytes internamente,
	// então aceitar payloads gigantes só serve pra forçar PHP a alocar memória
	// antes do password_verify (DoS marginal). Trata como tentativa falha pra
	// incrementar o rate-limit normalmente — atacante não ganha "tentativas
	// grátis" mandando string grande.
	if ( strlen( $submitted ) > 200 ) {
		set_transient( $rate_key, $attempts + 1, RD_MAINT_RATE_LIMIT_WINDOW );
		rd_maintenance_render_login_form( __( 'Incorrect password.', 'reloaded' ) );
		exit;
	}

	// Pega hash armazenado (em rd_get_option)
	$stored_hash = rd_get_option( 'maintenance_pass_hash' );

	// Sem hash configurado: bloqueia (segurança por padrão)
	if ( empty( $stored_hash ) ) {
		rd_maintenance_render_login_form( __( 'Developer access not configured.', 'reloaded' ) );
		exit;
	}

	// Verifica senha com password_verify (resistente a timing attack)
	if ( ! password_verify( $submitted, $stored_hash ) ) {
		// Incrementa contador de tentativas
		set_transient( $rate_key, $attempts + 1, RD_MAINT_RATE_LIMIT_WINDOW );

		rd_maintenance_render_login_form( __( 'Incorrect password.', 'reloaded' ) );
		exit;
	}

	// Senha correta: limpa rate limit, gera token, seta cookie
	delete_transient( $rate_key );

	$token = bin2hex( random_bytes( 32 ) );  // 64 chars hex, criptograficamente seguro
	set_transient( 'rd_maint_token_' . $token, '1', RD_MAINT_COOKIE_LIFETIME );

	// Cookie com TODAS as flags de segurança
	setcookie(
		RD_MAINT_COOKIE_NAME,
		$token,
		array(
			'expires'  => time() + RD_MAINT_COOKIE_LIFETIME,
			'path'     => '/',
			'domain'   => '',
			'secure'   => is_ssl(),    // Só por HTTPS
			'httponly' => true,         // Inacessível ao JavaScript
			'samesite' => 'Lax',        // Defesa contra CSRF
		)
	);

	// Redireciona pra home (sem expor nada na URL)
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

/**
 * Renderiza tela de manutenção (público)
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

	// Usa Custom Logo do WP se cadastrado, fallback pro logo hardcoded do tema.
	$logo_data = rd_get_site_logo( 'medium' );
	$logo_url  = $logo_data['url'];
	$html      = rd_maintenance_get_template_html( $logo_url, $mensagem );

	/*
	 * CSP: wp_die() chama _default_wp_die_handler() do WP core que injeta um
	 * <style type="text/css"> nativo SEM nonce — escapa do nosso output buffer
	 * do mod-csp (que opera em wp_head, e wp_die corta o fluxo). Abrir um
	 * output buffer LOCAL aqui pra interceptar o output do wp_die e adicionar
	 * nonce no <style> do core antes do response sair. PHP fecha o buffer no
	 * shutdown e o callback aplica o nonce em qualquer <style> sem nonce.
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
 * Output buffer callback — injeta nonce em <style> sem nonce no HTML do wp_die.
 * Idempotente (lookahead negativo pula tags já-nonce'adas).
 *
 * @param string $html HTML acumulado no buffer.
 * @return string HTML com nonce em todos os <style>.
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
 * Renderiza formulário de login do dev
 */
function rd_maintenance_render_login_form( $error_message = '' ) {
	// Usa Custom Logo do WP se cadastrado, fallback pro logo hardcoded do tema.
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

	// CSP nonce — template renderiza via wp_die() fora do fluxo normal de
	// wp_head, então escapa do output buffer do mod-csp. Injetamos manual.
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
 * Helper: renderiza a tag <img> do logo, envolta em <picture> com next-gen
 * sources se o helper de imagens estiver disponível. Defensivo — fallback pro
 * <img> cru se mod-image-formats não carregou (cenário edge case).
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
 * Salva senha como hash quando o admin atualiza no painel
 *
 * Hook: pre_update_option_rd_settings (roda antes de salvar opções)
 *
 * Comportamento:
 * - Senha digitada (nova): gera hash, limpa campo de texto puro
 * - Senha vazia (admin não mexeu): preserva hash existente do banco
 */
function rd_maintenance_hash_password_on_save( $new_value, $old_value ) {

	// Garante que $new_value seja um array (defensive)
	if ( ! is_array( $new_value ) ) {
		return $new_value;
	}

	// Recupera hash existente do banco (se houver)
	$existing_hash = is_array( $old_value ) && isset( $old_value['maintenance_pass_hash'] )
		? $old_value['maintenance_pass_hash']
		: '';

	// Pega senha digitada (texto puro, se houver)
	$new_password = isset( $new_value['maintenance_pass'] ) ? trim( $new_value['maintenance_pass'] ) : '';

	if ( ! empty( $new_password ) ) {
		// Admin digitou nova senha → gera hash novo
		$new_value['maintenance_pass_hash'] = password_hash( $new_password, PASSWORD_DEFAULT );
	} else {
		// Admin não digitou → preserva hash existente
		$new_value['maintenance_pass_hash'] = $existing_hash;
	}

	// SEMPRE limpa o campo de texto puro (nunca armazenar texto plano)
	$new_value['maintenance_pass'] = '';

	return $new_value;
}
add_filter( 'pre_update_option_rd_settings', 'rd_maintenance_hash_password_on_save', 10, 2 );
