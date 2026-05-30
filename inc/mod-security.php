<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Security — Security headers (clickjacking, MIME sniffing, etc.)     *
 *******************************************************************************/

/**
 * Lista canônica dos headers de segurança aplicados pelo tema.
 *
 * Centralizada em uma única função pra garantir consistência entre o frontend
 * (action `send_headers`) e o REST API (filter `rest_pre_serve_request`) —
 * os dois code paths do WP que precisam emitir headers separadamente.
 *
 * @return array<string,string> Mapa cabeçalho → valor.
 */
function rd_get_security_headers_map() {
	return array(
		// Anti-clickjacking — só permite iframes da própria origem
		'X-Frame-Options'        => 'SAMEORIGIN',
		// Browser deve respeitar o Content-Type declarado, sem "adivinhar" pelo conteúdo
		'X-Content-Type-Options' => 'nosniff',
		// Limita o referer cross-origin: só envia origin (sem path), nada em HTTPS→HTTP
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		// Bloqueia features sensíveis que o tema não usa (defesa em profundidade)
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=()',
	);
}

/**
 * Envia os headers de segurança no frontend.
 *
 * Aplica apenas no frontend pra não interferir com iframes internos,
 * inline scripts pesados e AJAX que o painel admin do WP usa intensamente.
 *
 * O toggle no painel segue o padrão de migração seguro: instalações
 * existentes ficam OFF até o admin habilitar conscientemente; novas
 * instalações ganham ON por default via rd_set_default_options().
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
 * Aplica os mesmos headers de segurança nas respostas do REST API.
 *
 * REST API usa code path próprio (WP_REST_Server::serve_request()) que NÃO
 * dispara a action `send_headers`. Sem este hook, endpoints `/wp-json/*` ficam
 * sem X-Frame-Options, Referrer-Policy e Permissions-Policy — só X-Content-Type
 * (que o WP core já adiciona) e HSTS (set pelo Nginx) restavam.
 *
 * Achado da Wave 9 C (OWASP ZAP scan 2026-05-22).
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
 * Desabilita o endpoint XML-RPC                                               *
 *                                                                             *
 * XML-RPC é a API legada do WordPress, usada por apps mobile antigos e pelo   *
 * sistema de pingbacks/trackbacks. Como praticamente ninguém usa hoje, ela    *
 * costuma ser alvo de brute-force e amplificação de DDoS (pingback.ping).     *
 *                                                                             *
 * O filtro `xmlrpc_enabled` faz o WP responder com 405 Method Not Allowed     *
 * em /xmlrpc.php, mas mantém o arquivo acessível (alguns hosts roteiam por    *
 * lá pra healthchecks). Pra fechar tudo, bloqueia também o header de          *
 * descoberta (`X-Pingback`) e o link no <head>.                               *
 */
if ( rd_get_option_bool( 'disable_xmlrpc' ) ) {

	// 1. Bloqueia o acesso direto ao /xmlrpc.php (GET e POST) — devolve 403.
	// Sem isso, um GET no arquivo mostra "XML-RPC server accepts POST
	// requests only." (mensagem hardcoded do WP antes dos filtros).
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

	// 2. Camada extra: caso alguma rota chegue no servidor XML-RPC sem
	// passar pelo arquivo, o filtro retorna false em todos os métodos.
	add_filter( 'xmlrpc_enabled', '__return_false' );

	// 3. Remove o header X-Pingback que o WP envia em todas as páginas.
	add_filter(
		'wp_headers',
		function ( $headers ) {
			unset( $headers['X-Pingback'] );
			return $headers;
		}
	);

	// 4. Remove o <link rel="EditURI"> (RSD) do <head> — pista de descoberta.
	remove_action( 'wp_head', 'rsd_link' );
}

/*******************************************************************************
 * White Screen of Death (WSOD) — customização da mensagem de erro fatal       *
 *                                                                             *
 * Quando o PHP fatala (out of memory, exception não tratada, etc), o          *
 * WordPress 5.2+ mostra uma página "There has been a critical error on this   *
 * website."                                                                   *
 *                                                                             *
 * Não dá pra customizar o LAYOUT (HTML/CSS) sem risco — em fatal o tema pode  *
 * não estar funcional, então o WP intencionalmente usa renderer minimalista.  *
 * Mas dá pra customizar o TEXTO via filters, deixando branded e em pt-BR.    *
 *                                                                             *
 * Pra uma página visualmente rica em erro 500 seria preciso configurar        *
 * `php_value display_errors Off` no servidor + página HTML estática separada *
 * apontada por ErrorDocument no .htaccess. Isso fica fora do escopo do tema. *
 */

// Customiza o título da aba do navegador na tela de erro fatal
add_filter(
	'wp_php_error_args',
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $error faz parte da assinatura do filter `wp_php_error_args`, mesmo quando só editamos o título.
	function ( $args, $error ) {
		/* translators: %s: site name from WordPress general settings */
		$args['title'] = sprintf( __( 'Critical Error - %s', 'reloaded' ), get_bloginfo( 'name' ) );
		return $args;
	},
	10,
	2
);

/**
 * Mensagem da WSOD totalmente brandeada — replica o visual do card de
 * manutenção (`mod-maintenance.php`) injetando <style> inline que sobrescreve
 * o template grayscale padrão do `_default_wp_die_handler`.
 *
 * Por que inline e não enqueue: em fatal o tema pode estar quebrado, então
 * o handler renderiza ANTES do `wp_head` ter chance de rodar. CSS injetado
 * direto no body funciona em qualquer cenário.
 *
 * Por que não usar o helper do mod-maintenance: aquele tem markup específico
 * (h1 com pulse animation, formulário de login). WSOD precisa de algo
 * estático e isolado pra não introduzir dependência cross-module em código
 * que roda durante crash.
 */
add_filter(
	'wp_php_error_message',
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $message e $error fazem parte da assinatura do filter `wp_php_error_message`, mas reescrevemos a mensagem do zero (não usamos a default).
	function ( $message, $error ) {
		// Logo: tenta usar Custom Logo do WP, mas com fallback DEFENSIVO porque
		// WSOD roda quando o PHP fatalou — o WP pode estar parcialmente quebrado.
		// Se rd_get_site_logo() não estiver disponível (core.php não carregou
		// ou helper indisponível), cai pro hardcoded sem quebrar.
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

		// CSP nonce — WSOD handler roda via _default_wp_die_handler, fora do
		// fluxo normal de wp_head, então escapa do output buffer do mod-csp.
		$nonce_attr = function_exists( 'rd_csp_nonce_attr' ) ? rd_csp_nonce_attr() : '';

		// Logo HTML envolvido em <picture> se houver next-gen sources no disco.
		// Fallback defensivo pro <img> cru se mod-image-formats não carregou
		// (cenário relevante — WSOD roda quando PHP fatalou, pode ser exatamente
		// no mod-image-formats).
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
