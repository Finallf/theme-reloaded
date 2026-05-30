<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: CSP (Content Security Policy) — Report-Only mode                    *
 *                                                                             *
 * Envia o header `Content-Security-Policy-Report-Only` no frontend com uma    *
 * política calculada dinamicamente baseada nas integrações ativas no painel.  *
 * O browser NÃO bloqueia nada — apenas reporta violações pra um endpoint REST *
 * próprio (`/wp-json/rd/v1/csp-report`) que armazena num option do WP.        *
 *                                                                             *
 * Objetivo: visibilidade do que o site realmente carrega (scripts, styles,    *
 * imagens, frames, fontes) — útil pra auditoria de segurança e privacidade.   *
 * Quando a política estiver madura (semanas/meses analisando reports), pode   *
 * promover pra `Content-Security-Policy` (enforce) editando esse arquivo.     *
 *                                                                             *
 * Gate: feature toggleável em Painel → Segurança (`enable_csp_report_only`,   *
 * default OFF). Quando OFF, header não é enviado e endpoint REST permanece    *
 * registrado mas inerte (browser não terá motivo pra chamar).                 *
 *******************************************************************************/

const RD_CSP_REPORTS_OPTION    = 'rd_csp_reports';
const RD_CSP_REPORTS_MAX       = 100; // FIFO — descarta reports antigos
const RD_CSP_RATE_LIMIT_MAX    = 60;  // máx 60 reports por IP
const RD_CSP_RATE_LIMIT_WINDOW = 60;  // janela de 60s

/*
=============================================================================
 *  NONCE — gerado uma vez por request, propagado pra inline scripts/styles
 * ============================================================================= */

/**
 * Retorna o nonce CSP do request atual (cacheado em static).
 *
 * - 16 bytes de entropia → base64url (~22 chars sem padding). Spec CSP exige
 *   ≥ 128 bits de imprevisibilidade; 16 bytes (128 bits) dá exatamente isso.
 * - Calculado SOMENTE quando a feature CSP está ON. Se OFF, retorna ''.
 *   `rd_csp_nonce_attr()` usa isso pra omitir o atributo inteiro — mantém
 *   HTML limpo quando CSP não está em uso.
 *
 * @return string Nonce base64url (~22 chars) ou '' quando CSP está desligado.
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
	// base64url — caracteres seguros pra atributo HTML sem escape extra.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 aqui não é obfuscação; é encoding canônico de bytes aleatórios pra string ASCII segura.
	$nonce = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' );
	return $nonce;
}

/**
 * Atributo `nonce="..."` pronto pra concatenar em `<script>` ou `<style>`.
 *
 * Retorna '' quando CSP está OFF — permite uso unconditional nos templates
 * sem precisar de `<?php if ... ?>` ao redor de cada tag.
 *
 * @return string ` nonce="..."` (com espaço inicial) ou '' se CSP desligado.
 */
function rd_csp_nonce_attr(): string {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return '';
	}
	return ' nonce="' . esc_attr( $nonce ) . '"';
}

/**
 * Injeta `nonce="..."` em todas as tags `<script>` de um HTML arbitrário.
 *
 * Pensado pros campos `ad_*` do painel (AdSense, FB Ads, custom snippets)
 * — admin cola código de tracking direto da documentação da plataforma, e
 * essa função adiciona o nonce automaticamente antes do echo. Sem isso, em
 * Enforce o snippet seria bloqueado.
 *
 * Detalhes:
 * - Só toca em `<script>` que ainda NÃO tenham `nonce=` (idempotente).
 * - Funciona pra `<script>...</script>` e `<script src="...">...</script>`.
 * - Quando CSP está OFF, retorna o HTML inalterado.
 *
 * @param string $html Bloco HTML que pode conter uma ou mais tags `<script>`.
 * @return string HTML com nonce injetado em cada `<script>` aplicável.
 */
function rd_csp_inject_nonce( string $html ): string {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce || '' === $html ) {
		return $html;
	}
	// Substitui `<script` por `<script nonce="..."` SOMENTE quando a tag não
	// tem nonce ainda. (?!...) é lookahead negativo: rejeita se vir `nonce=`
	// nos próximos chars antes do `>`.
	$pattern     = '/<script(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<script nonce="' . esc_attr( $nonce ) . '"$1';
	$result      = preg_replace( $pattern, $replacement, $html );
	return null !== $result ? $result : $html;
}

/*
=============================================================================
 *  CUSTOM ORIGINS — parser/validador de origens extras do painel
 * ============================================================================= */

/**
 * Faz parse de um textarea (1 origem por linha) e retorna array de origens
 * válidas pra entrar no CSP.
 *
 * Regras de aceitação:
 *   - https://hostname[:port][/path...] — path é ignorado pelo CSP, host
 *     entra inteiro. Aceita wildcard de subdomínio: https://*.foo.com
 *   - https:                              — schema-only, libera QUALQUER host
 *                                           HTTPS. Permitido mas perigoso
 *                                           (admin que sabe o que faz).
 *
 * Regras de rejeição (linha descartada silenciosamente):
 *   - HTTP puro                     — força HTTPS, alinhado com infra moderna
 *   - Keywords com aspas            — 'unsafe-inline', 'self', 'none' etc.
 *                                     anulariam o ponto da CSP nonce
 *   - Wildcard puro `*`             — derrota o propósito
 *   - data:, blob:, filesystem:     — schemes não-padrão exigem edição PHP
 *   - Qualquer coisa que não bata
 *     com os padrões acima          — inclui linhas vazias e comentários
 *
 * @param string|null $raw Conteúdo do textarea (separado por \n).
 * @return string[] Origens válidas, prontas pra concatenar em directives.
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

		// Schema-only token (libera tudo num scheme) — só HTTPS aceito.
		if ( 'https:' === $line ) {
			$origins[] = 'https:';
			continue;
		}

		// https://hostname[:port][/path] com wildcard opcional no subdomínio.
		// Hostname tem que ter ponto OU ser literal (mas literal é raro/local).
		if ( preg_match( '#^https://(\*\.)?([a-z0-9.\-]+)(:[0-9]+)?(/.*)?$#i', $line, $m ) ) {
			$wildcard = $m[1] ?? '';
			$host     = $m[2];
			$port     = $m[3] ?? '';

			// Rejeita host malformado: começa/termina com hífen, duplo ponto, etc.
			if ( preg_match( '/(^[-.]|[-.]$|\.\.|--)/', $host ) ) {
				continue;
			}

			// Reconstrói só o origin (host + port), sem path — CSP ignora path.
			$origins[] = 'https://' . $wildcard . $host . $port;
			continue;
		}

		// Tudo o mais é rejeitado (HTTP, keywords com aspas, data:, etc.).
	}

	return array_values( array_unique( $origins ) );
}

/*
=============================================================================
 *  POLICY BUILDER — monta a CSP string conforme integrações ativas
 * ============================================================================= */

/**
 * Monta a CSP completa baseada em quais integrações estão ativas no painel.
 * Começa com baseline restrito (self) e adiciona origens conforme features ON.
 *
 * @return string Header CSP pronto pra mandar
 */
function rd_csp_build_policy(): string {
	$self  = "'self'";
	$nonce = rd_csp_nonce();

	/*
	 * Tokens nonce + strict-dynamic — só aparecem quando o helper retornou
	 * um nonce válido (i.e. CSP está ON e random_bytes funcionou). Pattern
	 * recomendado por Google CSP Evaluator / OWASP:
	 *   - 'nonce-XXX' habilita só os <script>/<style> que carregam esse nonce
	 *   - 'strict-dynamic' propaga confiança pros scripts que um nonce'd
	 *     script criar dinamicamente (essencial pra GA, Clarity, AdSense, etc.
	 *     que carregam scripts filhos via document.createElement)
	 *   - 'unsafe-inline' fica como fallback pra browsers pré-CSP3 (Safari
	 *     < 15.4). Em browsers modernos, presença de nonce/hash em uma
	 *     directive ANULA 'unsafe-inline' automaticamente (CSP3 spec).
	 */
	$nonce_token = '' !== $nonce ? "'nonce-{$nonce}'" : '';

	// Diretivas baseline (sempre aplicadas).
	$d = array(
		'default-src'    => array( $self ),
		// script-src: nonce + strict-dynamic em browsers modernos, unsafe-inline
		// como graceful degradation pra browsers velhos.
		'script-src'     => array_filter(
			array(
				$self,
				$nonce_token,
				'' !== $nonce_token ? "'strict-dynamic'" : '',
				"'unsafe-inline'",
			)
		),
		// style-src: governa <style> tags (e <link rel=stylesheet>). Nonce ativa
		// enforce em modernos; 'unsafe-inline' fica como fallback pra browsers
		// pré-CSP3. strict-dynamic não se aplica a styles (CSP3 spec).
		'style-src'      => array_filter(
			array(
				$self,
				$nonce_token,
				"'unsafe-inline'",
			)
		),

		/*
		 * style-src-attr: governa SOMENTE atributos style="..." em elementos HTML
		 * (e element.style.X = ... via JS). Necessário declarar separadamente
		 * porque CSP3 anula 'unsafe-inline' quando há nonce na mesma directive,
		 * e nonces não se aplicam a attributes (CSP3 spec) — única forma de não
		 * bloquear style attrs legítimos (WP admin bar, mod-archive-header
		 * accent_color, conteúdo do post com HTML inline, blocos Gutenberg)
		 * é manter 'unsafe-inline' aqui explícito, isolado do nonce do style-src.
		 *
		 * Trade-off aceito (pattern padrão da indústria pra CSP nonce-based):
		 * style="..." injetado via XSS pode fazer defacing visual ou clickjacking
		 * (overlay invisível), mas NÃO executa JS — CSS expression() morreu com
		 * IE; CSS moderno não tem capacidade de execução. <style> tags injetadas
		 * continuam bloqueadas pelo nonce do style-src.
		 *
		 * Browsers pré-CSP3 ignoram style-src-attr (directive desconhecida) e
		 * caem no style-src — que tem 'unsafe-inline' como fallback. Sem regressão.
		 */
		'style-src-attr' => array( "'unsafe-inline'" ),
		// img-src permissivo: aceita qualquer imagem HTTPS. Decisão consciente pra
		// blog/portal que cola markdown externo (GitHub camo, shields.io, imgur, etc.).
		// Como `https:` engloba qualquer origem segura, dispensa whitelist específica
		// pra Gravatar e pras integrações abaixo — elas só adicionam origens em
		// script-src/connect-src/frame-src (onde a restrição realmente importa).
		'img-src'        => array( $self, 'data:', 'https:' ),
		'font-src'       => array( $self, 'data:' ),
		'connect-src'    => array( $self ),
		'frame-src'      => array( $self ),
		'media-src'      => array( $self ),
		'object-src'     => array( "'none'" ), // bloqueia <object>/<embed> (Flash legado).
		'base-uri'       => array( $self ),
		'form-action'    => array( $self ),
	);

	// === Integrações condicionais ===

	// Google Analytics (GA4)
	if ( trim( (string) rd_get_option( 'ga_id' ) ) !== '' ) {
		$d['script-src'][]  = 'https://www.googletagmanager.com';
		$d['script-src'][]  = 'https://www.google-analytics.com';
		$d['connect-src'][] = 'https://www.google-analytics.com';
		$d['connect-src'][] = 'https://*.analytics.google.com';
		$d['connect-src'][] = 'https://*.google-analytics.com';
		// img-src: GA usa beacon image (collect endpoint) — coberto pelo `https:` baseline
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
		// img-src: Pixel <img> tracking — coberto pelo `https:` baseline
	}

	// TikTok Pixel
	if ( trim( (string) rd_get_option( 'tiktok_pixel_id' ) ) !== '' ) {
		$d['script-src'][]  = 'https://analytics.tiktok.com';
		$d['connect-src'][] = 'https://analytics.tiktok.com';
		// img-src: TikTok beacon — coberto pelo `https:` baseline
	}

	// Plausible Analytics
	if ( trim( (string) rd_get_option( 'plausible_domain' ) ) !== '' ) {
		$d['script-src'][]  = 'https://plausible.io';
		$d['connect-src'][] = 'https://plausible.io';
	}

	// Umami Analytics — URL configurável, extrai o origin
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

	// YouTube embed — sempre liberado pra frame-src.
	// Antes era gated por `facade_youtube` (toggle de PERFORMANCE pra renderizar
	// thumbnail estático antes do iframe), mas isso bloqueava posts com YouTube
	// embed direto (Gutenberg embed block, shortcode [embed], iframe puro no
	// markdown) quando o facade tava OFF. YouTube é origem confiável universal
	// pra um portal de tech/games — liberar sempre é o pragmatismo correto.
	// img-src: thumbnails (img.youtube.com, i.ytimg.com) — cobertos pelo `https:` baseline.
	$d['frame-src'][] = 'https://www.youtube.com';
	$d['frame-src'][] = 'https://www.youtube-nocookie.com';

	/*
	 * === Custom origins (admin define no painel pra integrações novas) ===
	 * 3 campos textarea no Painel → Segurança, agrupados por uso típico:
	 *   - Scripts & APIs (script-src + connect-src)
	 *   - Iframes & Embeds (frame-src)
	 *   - Styles & Fonts (style-src + font-src)
	 * Cada um aceita 1 origem por linha. Parser valida formato (só HTTPS,
	 * sem keywords com aspas, sem wildcard puro) e descarta linhas inválidas.
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
	// report-uri é legado (CSP 2) mas universalmente suportado.
	// report-to (CSP 3 / Reporting API) é o futuro, mas requer Reporting-Endpoints header.
	// Pra simplicidade começamos só com report-uri — funciona em todos browsers atuais.
	$d['report-uri'] = array( esc_url_raw( rest_url( 'rd/v1/csp-report' ) ) );

	// Monta string final
	$parts = array();
	foreach ( $d as $directive => $sources ) {
		$parts[] = $directive . ' ' . implode( ' ', array_unique( $sources ) );
	}
	return implode( '; ', $parts );
}

/*
=============================================================================
 *  HEADER INJECTION — manda o CSP-Report-Only no frontend
 * ============================================================================= */

/**
 * Injeta o header CSP via filter wp_headers (frontend only).
 *
 * Dois modos controlados pelo painel:
 *   - Report-Only (default): `Content-Security-Policy-Report-Only` — browser
 *     reporta mas NÃO bloqueia. Pra monitoramento/auditoria.
 *   - Enforce: `Content-Security-Policy` — browser BLOQUEIA violações. Pra
 *     produção, depois de pelo menos 30 dias em report-only sem surpresas.
 *
 * Admin tem seus próprios scripts/inline styles do WP — sem CSP no admin
 * pra evitar quebrar a UI nativa.
 */
function rd_csp_inject_header( $headers ) {
	if ( ! rd_get_option_bool( 'enable_csp_report_only' ) ) {
		return $headers;
	}
	if ( is_admin() ) {
		return $headers;
	}

	// Decide nome do header conforme o modo configurado no painel
	$header_name = rd_get_option_bool( 'csp_enforce_mode' )
		? 'Content-Security-Policy'
		: 'Content-Security-Policy-Report-Only';

	$headers[ $header_name ] = rd_csp_build_policy();
	return $headers;
}
add_filter( 'wp_headers', 'rd_csp_inject_header' );

/*
=============================================================================
 *  REST ENDPOINT — recebe relatórios do browser
 * ============================================================================= */

/**
 * Registra endpoint /wp-json/rd/v1/csp-report. permission_callback retorna
 * true porque o browser não tem como autenticar — o endpoint é público mas
 * só armazena dados estruturados (validados via json_decode + whitelist
 * de campos abaixo).
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
 * Recebe relatório CSP do browser. Espera body JSON com estrutura padrão CSP-2:
 *   { "csp-report": { "document-uri", "violated-directive", "blocked-uri", ... } }
 *
 * Armazena em option `rd_csp_reports` com FIFO de RD_CSP_REPORTS_MAX entries.
 * autoload=no porque pode crescer e não precisamos sempre na memória.
 */
function rd_csp_receive_report( WP_REST_Request $request ) {
	// Feature OFF = ignora silenciosamente (não cria spam de reports se
	// alguém desabilita e o browser ainda tenta entregar pendências)
	if ( ! rd_get_option_bool( 'enable_csp_report_only' ) ) {
		return new WP_REST_Response( null, 204 );
	}

	// Rate limit por IP. Endpoint é necessariamente público (browsers não
	// autenticam reports CSP), então sem teto um atacante pode floodar 100+
	// POSTs JSON minimalistas pra preencher o FIFO de reports e ocultar
	// violações legítimas. Reusa `rd_get_client_ip` (valida REMOTE_ADDR antes
	// de confiar em CF-Connecting-IP, mesma defesa de mod-maintenance/mod-views).
	$ip       = rd_get_client_ip();
	$rate_key = 'rd_csp_rate_' . md5( $ip );
	$attempts = (int) get_transient( $rate_key );
	if ( $attempts >= RD_CSP_RATE_LIMIT_MAX ) {
		// 429 com Retry-After alerta o browser pra desacelerar; em prática
		// browser não respeita pra CSP reports, mas semanticamente correto.
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

	// Whitelist defensiva — só salva campos conhecidos (evita poluir option
	// se browser/extensão mandar lixo extra)
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
	array_unshift( $reports, $entry ); // mais novo no topo
	$reports = array_slice( $reports, 0, RD_CSP_REPORTS_MAX );

	// autoload=no pra não inflar carregamento do WP — só lemos no admin
	update_option( RD_CSP_REPORTS_OPTION, $reports, false );

	return new WP_REST_Response( null, 204 );
}

/*
=============================================================================
 *  HELPERS PÚBLICOS — pra consumo do painel
 * ============================================================================= */

/**
 * Retorna reports armazenados (mais novo primeiro).
 */
function rd_csp_get_reports(): array {
	$reports = get_option( RD_CSP_REPORTS_OPTION, array() );
	return is_array( $reports ) ? $reports : array();
}

/**
 * Limpa todos os reports armazenados. Chamado pelo botão "Clear reports"
 * no painel via handler em admin_init.
 */
function rd_csp_clear_reports(): void {
	update_option( RD_CSP_REPORTS_OPTION, array(), false );
}

/**
 * Agrega reports armazenados por `violated_directive`.
 *
 * Usado pelo doughnut chart de "Violations by Directive" (Wave 11 Fase G).
 * Retorna array associativo `[ 'script-src-elem' => 12, 'style-src-attr' => 7, ... ]`
 * já ordenado do maior pro menor.
 *
 * @return int[] Map de directive -> count, ordenado por count desc.
 */
function rd_csp_get_violations_by_directive(): array {
	$reports = rd_csp_get_reports();
	$counts  = array();

	foreach ( $reports as $r ) {
		$dir = isset( $r['violated_directive'] ) ? (string) $r['violated_directive'] : '';
		if ( '' === $dir ) {
			$dir = 'unknown';
		}
		// Normaliza: WP às vezes anexa o blocked-uri à directive ("style-src-elem inline").
		// Pra o gráfico, queremos só a directive raiz — split no primeiro espaço.
		$dir            = strtok( $dir, ' ' );
		$counts[ $dir ] = ( $counts[ $dir ] ?? 0 ) + 1;
	}

	arsort( $counts ); // ordem decrescente — maior violação primeiro.
	return $counts;
}

/*
=============================================================================
 *  NONCE PROPAGATION — adiciona nonce automaticamente em recursos WP-managed
 * ============================================================================= */

/**
 * Adiciona `nonce="..."` em todo `<script src="...">` que o WP renderiza via
 * `wp_enqueue_script()`. Roda no filter `script_loader_tag`.
 *
 * Necessário pro 'strict-dynamic' funcionar: o script raiz precisa ter nonce
 * pra "trust" propagar pros scripts que ele carrega dinamicamente.
 *
 * @param string $tag HTML da tag `<script>`.
 * @return string Tag com nonce injetado, ou inalterada se CSP OFF.
 */
function rd_csp_add_nonce_to_script_tag( $tag ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $tag;
	}
	// rd_csp_inject_nonce é idempotente — se já tem nonce, não duplica.
	return rd_csp_inject_nonce( $tag );
}
add_filter( 'script_loader_tag', 'rd_csp_add_nonce_to_script_tag', 99 );

/**
 * Adiciona `nonce="..."` em todo `<link rel="stylesheet">` enqueued via WP.
 * Apesar de `<link>` ser external (não-inline), browsers honram nonce em
 * stylesheets quando style-src tem 'strict-dynamic' não disponível — alguns
 * scanners também esperam nonce em todas as stylesheets pra dar nota cheia.
 *
 * Inline styles (`wp_add_inline_style`) não passam por filter próprio antes
 * do WP 6.x — em versões modernas, o WP usa `wp_get_inline_style_tag()` que
 * NÃO tem filter público. Mantemos 'unsafe-inline' no style-src como fallback
 * pra cobrir esses casos.
 *
 * @param string $tag HTML da tag `<link>` ou `<style>`.
 * @return string Tag com nonce, ou inalterada se CSP OFF.
 */
function rd_csp_add_nonce_to_style_tag( $tag ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $tag;
	}
	// Injeta nonce em <link> e <style> que ainda não têm o atributo.
	$pattern     = '/<(link|style)(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<$1 nonce="' . esc_attr( $nonce ) . '"$2';
	$result      = preg_replace( $pattern, $replacement, $tag );
	return null !== $result ? $result : $tag;
}
add_filter( 'style_loader_tag', 'rd_csp_add_nonce_to_style_tag', 99 );

/**
 * Adiciona nonce ao array de atributos de inline scripts gerados via
 * `wp_add_inline_script()` (WP 5.7+). Esse é o caminho canônico — quando
 * um plugin ou tema chama `wp_add_inline_script('handle', $code)`, o WP
 * renderiza com `wp_print_inline_script_tag()` que respeita esse filter.
 *
 * @param array $attributes Mapa de atributos da tag inline.
 * @return array Mapa com `nonce` adicionado se CSP estiver ON.
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
// Equivalentes pra <style> — WP/Gutenberg usa wp_get_inline_style_tag() em
// alguns block supports modernos. Esses filters cobrem o caminho "novo".
add_filter( 'wp_inline_style_attributes', 'rd_csp_add_nonce_to_inline_attrs' );
add_filter( 'wp_style_attributes', 'rd_csp_add_nonce_to_inline_attrs' );

/**
 * Output buffer no wp_head: cobre os <style> que escapam de todos os filters.
 *
 * Caso real (WP 6.x): `WP_Styles::print_inline_style()` faz `printf( "<style id='%s-inline-css'...>" )`
 * direto sem passar por nenhum filter. Atinge inline styles registrados via
 * `wp_add_inline_style($handle, $data)` — pattern usado pelo WP core pra
 * "wp-img-auto-sizes-contain-inline-css", "core-block-supports-inline-css",
 * e por vários plugins/temas. Filters não pegam esses casos.
 *
 * Solução robusta: abre um output buffer no início do wp_head com callback
 * que adiciona nonce em qualquer <style> sem nonce no HTML resultante.
 * Buffer fica aberto até o fim do request (PHP autofecha) e a regex é
 * idempotente (lookahead negativo pula tags já-nonce'adas).
 *
 * Overhead: 1 regex sobre o HTML completo da página por request. Pra páginas
 * típicas (~100KB de HTML) é microsegundos. Aceitável.
 *
 * @param string $html HTML acumulado no buffer (geralmente o response completo).
 * @return string HTML com nonce adicionado em <style> sem nonce.
 */
function rd_csp_late_style_nonce_filter( $html ) {
	$nonce = rd_csp_nonce();
	if ( '' === $nonce ) {
		return $html;
	}
	// Lookahead negativo (?!...) pula <style> que já tem nonce= — idempotente.
	$pattern     = '/<style(?![^>]*\snonce=)([\s>])/i';
	$replacement = '<style nonce="' . esc_attr( $nonce ) . '"$1';
	$result      = preg_replace( $pattern, $replacement, $html );
	return null !== $result ? $result : $html;
}

/**
 * Abre o output buffer late-stage (priority muito baixa) em wp_head, registrando
 * o callback acima. PHP fecha o buffer automaticamente no fim do request,
 * aplicando o filter sobre todo o HTML acumulado.
 */
function rd_csp_start_late_buffer() {
	if ( '' === rd_csp_nonce() ) {
		return;
	}
	ob_start( 'rd_csp_late_style_nonce_filter' );
}
// Priority muito baixa pra abrir o buffer ANTES de qualquer outro hook em wp_head.
add_action( 'wp_head', 'rd_csp_start_late_buffer', -PHP_INT_MAX );

/**
 * Intercepta o link "Clear reports" do painel. Hook admin_init pra rodar
 * ANTES de qualquer output (precisa pra wp_safe_redirect funcionar).
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
 *  RENDER — callback da section "CSP Reports" no painel Segurança
 * ============================================================================= */

/**
 * Renderiza visualização dos reports CSP recebidos. Chamado como callback da
 * `add_settings_section('sec_seg_csp_reports', ...)` no panel.php.
 *
 * Usa o design system rd-p* (Wave 11 Fase E refator) — wrapper, header e
 * card vêm dos helpers em inc/panel-helpers.php. Apenas a tabela mantém
 * classes próprias (`.rd-csp-table*`) por serem específicas da feature.
 */
function rd_csp_render_reports_panel(): void {
	// Section header com ícone (title vazio em add_settings_section evita <h2> duplicado).
	rd_panel_section_header(
		array(
			'icon'  => 'warning',
			'title' => __( 'CSP Violation Reports', 'reloaded' ),
		)
	);

	// Feature OFF: empty state simples.
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

	// Badge do modo ativo (Report-Only vs Enforce) — feedback visual claro.
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

	// Doughnut chart "Violations by Directive" — só renderiza se há reports
	// (sem dados, gráfico vazio polui visualmente). Wave 11 Fase G.
	if ( ! empty( $reports ) ) {
		$by_directive = rd_csp_get_violations_by_directive();
		if ( ! empty( $by_directive ) ) {
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
	}

	rd_panel_card_open();

	if ( empty( $reports ) ) {
		rd_panel_empty( __( 'No reports yet — either nothing violated the policy, or no one has loaded the frontend since the feature was enabled.', 'reloaded' ) );
	} else {
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
	}

	rd_panel_card_close();
	rd_panel_dash_close();
}
