<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Login Protection — hide login URL + rate limit failed attempts      *
 *                                                                              *
 * Duas camadas defensivas contra brute-force e tentativas automáticas:        *
 *                                                                              *
 *   1. HIDE URL (opt-in via slug não-vazio): /wp-login.php retorna 404,       *
 *      login só funciona via /{slug}. Para os ~95% de bots que só conhecem    *
 *      /wp-login.php como endpoint. Bots automatizados batem, recebem 404,    *
 *      vão embora. Reduz drasticamente noise nos logs.                        *
 *                                                                              *
 *   2. RATE LIMIT: tracking de falhas por IP via transient. Após N tentativas *
 *      em janela de M minutos, bloqueia próximas com WP_Error. Reusa          *
 *      `rd_get_client_ip()` (Wave 9 A) — proteção contra header spoofing      *
 *      via whitelist de Trusted Proxy do painel.                              *
 *                                                                              *
 * Filosofia: tema próprio, sem plugins externos. Recovery hatch via WP-CLI ou *
 * SQL direto se admin se locar fora — `wp option patch update rd_settings     *
 * login_secret_slug ""` desbloqueia (slug "" desativa hide URL, mantém login  *
 * acessível em /wp-login.php).                                                *
 ******************************************************************************/

/*******************************************************************************
 * Slug helpers                                                                 *
 ******************************************************************************/

/**
 * Sanitiza um slug de login.
 * Permitido: a-z, 0-9, hífen. Length 3-50. Lowercased.
 * Retorna '' se inválido ou em lista de reservados (graciosamente desativa).
 *
 * @param mixed $raw Valor cru vindo do form ou option.
 * @return string Slug saneado ou '' se feature deve ficar dormente.
 */
function rd_login_sanitize_slug( $raw ) {
	$slug = strtolower( trim( (string) $raw ) );
	if ( '' === $slug ) {
		return '';
	}
	if ( ! preg_match( '/^[a-z0-9-]{3,50}$/', $slug ) ) {
		return '';
	}
	// Reservados — proibidos pra evitar conflito com WP core / nossas rotas
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
 * Retorna o slug configurado e saneado, ou '' se feature está dormente.
 *
 * @return string
 */
function rd_login_get_slug() {
	// Master switch: quando enable_login_protection é OFF, retorna '' pra
	// que todas as features dependentes (hide URL, 4xx em /wp-login.php,
	// link rewriting) tratem como dormentes. O slug salvo é preservado no
	// banco — admin pode reativar sem perda.
	if ( ! rd_get_option_bool( 'enable_login_protection', true ) ) {
		return '';
	}
	$raw = rd_get_option( 'login_secret_slug', '' );
	return rd_login_sanitize_slug( $raw );
}

/**
 * Retorna a URL completa do login (pro display no painel).
 *
 * @return string URL absoluta com slug, ou wp-login.php se feature dormente.
 */
function rd_login_get_full_url() {
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return wp_login_url();
	}
	return home_url( '/' . $slug );
}

/*******************************************************************************
 * Request interception — implementa a camada "Hide URL"                       *
 *                                                                              *
 * Hook em `wp_loaded` priority 1. Quando o slug está set:                     *
 *   - /SLUG  → faz require_once de wp-login.php, exit (login renderiza)      *
 *   - /wp-login.php direto (SCRIPT_NAME) → 404 + template do tema            *
 *                                                                              *
 * Por que `wp_loaded` e não `plugins_loaded`:                                 *
 * `plugins_loaded` dispara ANTES de `functions.php` ser carregado, então      *
 * nosso `add_action` ainda nem foi registrado quando essa action passa —      *
 * resultado: hook nunca chamava. `wp_loaded` é a LAST action firada por       *
 * wp-settings.php, garantia de que tema + plugins + init já rodaram.          *
 *                                                                              *
 * Nota: SCRIPT_NAME é setado pelo Nginx, não por PHP. Confiável.              *
 ******************************************************************************/

/**
 * Intercepta o request em `wp_loaded` (última action antes do template).
 * Decide entre: servir login via slug, bloquear acesso direto a wp-login,
 * ou deixar passar (qualquer outra URL).
 */
function rd_login_handle_request() {
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return; // feature dormente — passa
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] )
		? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: '';

	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( ! is_string( $request_path ) ) {
		return;
	}
	$request_path_trimmed = trim( $request_path, '/' );

	// Case 1: acesso via /SLUG → carrega wp-login.php manualmente
	if ( $request_path_trimmed === $slug ) {
		define( 'RD_LOGIN_VIA_SLUG', true );
		rd_login_serve_via_slug();
	}

	// Case 2: acesso direto a /wp-login.php sem passar pelo slug → 404
	// Verifica SCRIPT_NAME (setado pelo Nginx, não-spoofável via PHP)
	$script_name = isset( $_SERVER['SCRIPT_NAME'] )
		? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) )
		: '';

	if ( 'wp-login.php' === basename( $script_name ) && ! defined( 'RD_LOGIN_VIA_SLUG' ) ) {
		// Permite ações administrativas legítimas (logout, etc.) quando o user já está logado
		// e acessa via cookie de sessão válido. Logged-in user = não brute-forcer.
		if ( is_user_logged_in() ) {
			return;
		}

		// Hit anônimo direto em wp-login.php — bloqueia com 404 brandeado do tema
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
 * Anti-leak: bloqueia /wp-admin/* pra anônimos                                *
 *                                                                              *
 * Sem este hook, anônimo hit em /wp-admin/ → WP detecta "not logged in" →     *
 * `auth_redirect()` → `wp_login_url()` → nosso filter reescreve pra /SLUG →   *
 * response `302 Location: /SLUG?...` → bot lê header e descobre o slug.       *
 *                                                                              *
 * Fix: interceptar antes do auth_redirect e redirecionar pra home (não pra    *
 * login). Bot vê só `Location: /` no header — slug fica preservado.           *
 *                                                                              *
 * Exceções (endpoints com actions públicas legítimas, deixamos WP gerir):    *
 *   - `admin-ajax.php` (detectado via `wp_doing_ajax()` — actions nopriv)     *
 *   - `admin-post.php`  (actions admin_post_nopriv_*)                          *
 *                                                                              *
 * Hook em `init` priority 1 — fires após wp-settings.php mas antes da admin   *
 * page carregar / outputar conteúdo. Headers ainda não enviados, redirect OK. *
 ******************************************************************************/

/**
 * Bloqueia acesso anônimo ao /wp-admin/, redirecionando pra home em vez de
 * permitir o redirect default do WP pra login (que vazaria o slug).
 *
 * @return void
 */
function rd_login_block_wpadmin_for_anonymous() {
	if ( '' === rd_login_get_slug() ) {
		return; // feature dormente
	}
	if ( ! is_admin() ) {
		return; // não é admin area
	}
	if ( is_user_logged_in() ) {
		return; // user logado tem direito ao /wp-admin/
	}
	if ( wp_doing_ajax() ) {
		return; // admin-ajax.php (actions públicas via wp_ajax_nopriv_*)
	}

	$script_name = isset( $_SERVER['SCRIPT_NAME'] )
		? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) )
		: '';
	if ( 'admin-post.php' === basename( $script_name ) ) {
		return; // actions admin_post_nopriv_*
	}

	// Anônimo em qualquer outra rota de /wp-admin/ → redirect pra home
	// (NÃO pra login — isso vazaria o slug via Location header)
	wp_safe_redirect( home_url( '/' ) );
	exit;
}
add_action( 'init', 'rd_login_block_wpadmin_for_anonymous', 1 );

/**
 * Serve wp-login.php a partir do hook `wp_loaded` quando o user acessa /SLUG.
 *
 * Wp-login.php foi escrito assumindo que roda como entry script (variáveis em
 * scope global). Quando carregamos via require_once de dentro de uma função,
 * essas variáveis viram locais — e WP 7.0 reporta `Undefined variable` em
 * `$user_login`, `$error`, `$errors`, etc., vazando warnings dentro dos campos
 * do form. Solução: declarar como `global` antes do require pra que as
 * assignments dele propaguem pro scope esperado, e pré-inicializar as 2 que
 * o WP 7.0 reclama no primeiro GET request.
 */
function rd_login_serve_via_slug() {
	global $action, $user_login, $error, $errors, $interim_login;
	global $customize_login, $user, $redirect_to, $secure_cookie, $reauth;

	// Pre-init pra suprimir os 2 warnings recorrentes no primeiro GET
	// (não há POST ainda, então wp-login.php não passa pelo branch que
	// declara essas variáveis). Strings vazias = comportamento neutro.
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intencional: wp-login.php espera essas variáveis no escopo onde nós o requeremos, pre-init evita warnings vazando dentro dos campos do form.
	$user_login = '';
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- idem.
	$error = '';

	require_once ABSPATH . 'wp-login.php';
	exit;
}

/*******************************************************************************
 * URL filters — faz o WP gerar URLs com o slug                                *
 *                                                                              *
 * Quando o slug está set, todo lugar do WP que gera URL de login deve usar    *
 * o slug. Cobre: wp_login_url, wp_logout_url, wp_lostpassword_url, emails de  *
 * notificação, redirects de wp_safe_redirect, etc.                            *
 ******************************************************************************/

/**
 * Reescreve a URL de login pra usar o slug.
 *
 * @param string $login_url    URL gerada por WP (geralmente wp-login.php).
 * @param string $redirect     Redirect_to param, se houver.
 * @param bool   $force_reauth Flag pra forçar re-auth.
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
 * Reescreve `site_url('wp-login.php')` que o WP usa em emails de notificação,
 * registros, lost password, etc.
 *
 * @param string      $url     URL gerada.
 * @param string      $path    Path passado pro site_url().
 * @param string|null $scheme  Scheme (https/http/login/etc).
 * @return string
 */
function rd_login_filter_site_url( $url, $path, $scheme = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $scheme exigido pela assinatura do filter `site_url`/`network_site_url`, não usamos.
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
 * Reescreve URL de logout (preserva o _wpnonce e action).
 *
 * @param string $logout_url URL gerada por WP.
 * @param string $redirect   Redirect_to param, se houver.
 * @return string
 */
function rd_login_filter_logout_url( $logout_url, $redirect = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $redirect exigido pela assinatura do filter `logout_url`, preservamos via parse_str do query original.
	$slug = rd_login_get_slug();
	if ( '' === $slug ) {
		return $logout_url;
	}
	// Extrai query args originais (action=logout, _wpnonce, redirect_to)
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
 * Reescreve URL de lost password.
 *
 * @param string $url URL gerada.
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
 * Rate limit — bloqueia IPs com muitas tentativas falhas                      *
 *                                                                              *
 * Storage: transients (WP_Cache backend se Redis ativo, senão options table). *
 * Reusa rd_get_client_ip() pra proteção contra header spoofing (Wave 9 A).    *
 ******************************************************************************/

/**
 * Verifica se IP atual está rate-limited.
 *
 * @return bool
 */
function rd_login_is_rate_limited() {
	$ip = function_exists( 'rd_get_client_ip' ) ? rd_get_client_ip() : '';
	if ( '' === $ip ) {
		return false; // sem IP = não conseguimos limitar, melhor liberar
	}
	$max      = max( 1, (int) rd_get_option( 'login_rate_limit_max', 5 ) );
	$key      = 'rd_login_attempts_' . md5( $ip );
	$attempts = (int) get_transient( $key );
	return $attempts >= $max;
}

/**
 * Incrementa contador de falhas pro IP atual.
 *
 * @return void
 */
function rd_login_record_failure() {
	// Master switch: rate limit fica desativado quando login protection está OFF.
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
 * Limpa contador no login bem-sucedido.
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
 * Filtra a autenticação pra bloquear quando rate-limited.
 * Roda ANTES do password check — atacante nem chega a tentar senha.
 *
 * @param mixed  $user     WP_User|WP_Error|null vindo dos filters anteriores.
 * @param string $username Username submetido.
 * @param string $password Password submetido (não é validada aqui — só rate check).
 * @return WP_User|WP_Error|null
 */
function rd_login_authenticate_check( $user, $username, $password ) {
	// Master switch: bloqueio por rate limit fica desativado quando OFF.
	if ( ! rd_get_option_bool( 'enable_login_protection', true ) ) {
		return $user;
	}
	// Só intervém em tentativas reais de login (não em chamadas vazias do auth chain)
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
 * Anti user enumeration                                                        *
 *                                                                              *
 * WP default mostra mensagens distintas pra "user não existe" vs "senha        *
 * errada", revelando ao atacante quais usernames são válidos. OWASP recomenda  *
 * mensagem genérica há décadas. Implementação aqui substitui os 3 error codes *
 * comprometedores (`incorrect_password`, `invalid_username`, `invalid_email`)  *
 * por uma única mensagem "Invalid username or password" — atacante não         *
 * consegue distinguir.                                                         *
 *                                                                              *
 * Toggle (`login_hide_user_enumeration`) default ON. Override pra OFF se você  *
 * quer mensagens de erro mais "amigáveis" pra usuários legítimos esquecidos.   *
 ******************************************************************************/

/**
 * Reescreve erros de login que revelam existência do user, substituindo por
 * mensagem genérica. Roda no filter `wp_login_errors` que recebe o WP_Error
 * agregado — temos acesso aos error codes (não só ao texto formatado).
 *
 * @param WP_Error|mixed $errors       Erros agregados do form de login.
 * @param string         $redirect_to  Redirect destino após login (não usado).
 * @return WP_Error|mixed Erros possivelmente substituídos.
 */
function rd_login_hide_user_enumeration( $errors, $redirect_to ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $redirect_to exigido pela assinatura do filter wp_login_errors.
	if ( ! rd_get_option_bool( 'login_hide_user_enumeration' ) ) {
		return $errors;
	}
	if ( ! is_wp_error( $errors ) ) {
		return $errors;
	}

	// Códigos que revelam existência do user via mensagem distinta.
	$enumeration_codes = array(
		'incorrect_password',
		'invalid_username',
		'invalid_email',
		'invalidcombo', // alguns plugins / WP versões antigas
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
