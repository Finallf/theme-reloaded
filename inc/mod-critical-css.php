<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Critical CSS injection + async stylesheet loading                    *
 *                                                                              *
 * Implementa o padrão "Critical CSS inline + defer" recomendado pelo Lighthouse *
 * pra eliminar render-blocking do `<link rel="stylesheet">` principal.         *
 *                                                                              *
 * Fluxo (quando ativado via toggle):                                            *
 *   1. wp_head priority 0 → injeta `<style id="rd-critical-css">{conteúdo}</style>`  *
 *      contendo o critical CSS pré-gerado pro template atual (5-20 KB inline). *
 *   2. style_loader_tag filter → transforma o `<link rel="stylesheet">` do     *
 *      handle `rd-main-style` em `<link rel="preload" as="style"               *
 *      onload="this.rel='stylesheet'">`, carregando assíncrono.                *
 *   3. <noscript> fallback → garante que browsers sem JS continuam pegando     *
 *      o CSS de forma síncrona (acessibilidade + Lynx/curl/etc).               *
 *                                                                              *
 * Critical CSS é gerado offline via `npm run critical:{template}` (script Node *
 * `tools/critical/extract.js` que usa o package `critical` + Puppeteer).       *
 * Arquivos: `assets/css/critical-{template}.css` — pré-versionados no repo.    *
 *                                                                              *
 * Templates suportados:                                                         *
 *   - home (is_home() || is_front_page())                                      *
 *   - single (is_single() && get_post_type() === 'post')                       *
 *   - page (is_page())                                                          *
 *   - archive (is_archive() || is_home() com posts_per_page custom)            *
 *   - search (is_search())                                                      *
 *   - fallback: home                                                            *
 *                                                                              *
 * Toggle: Painel → Performance → "Inline Critical CSS" (default OFF, opt-in).  *
 *                                                                              *
 * Risco de FOUC: se você esquecer de regenerar o critical após mudança grande *
 * de SCSS above-the-fold, vai aparecer flash visual breve quando o CSS         *
 * completo terminar de carregar. Não quebra funcionalidade — só fica feio.    *
 *******************************************************************************/

/**
 * Detecta o template atual e retorna a chave correspondente do critical CSS.
 *
 * @return string Chave do template ('home', 'single', 'page', 'archive', 'search').
 */
function rd_critical_css_template_key() {
	if ( is_singular( 'post' ) ) {
		return 'single';
	}
	if ( is_page() ) {
		return 'page';
	}
	if ( is_search() ) {
		return 'search';
	}
	if ( is_archive() || is_category() || is_tag() || is_author() || is_date() || is_tax() ) {
		return 'archive';
	}
	// is_home() / is_front_page() / qualquer outro contexto cai no fallback de home.
	return 'home';
}

/**
 * Lê o arquivo de critical CSS do template atual e retorna seu conteúdo.
 * Retorna string vazia se o arquivo não existir (degrada pra carga normal).
 *
 * @return string Critical CSS pronto pra injeção inline (já minificado).
 */
function rd_critical_css_get_content() {
	$template = rd_critical_css_template_key();
	$path     = get_template_directory() . '/assets/css/critical-' . $template . '.css';

	if ( ! file_exists( $path ) ) {
		// Fallback: tenta home se o template específico não existe.
		$path = get_template_directory() . '/assets/css/critical-home.css';
		if ( ! file_exists( $path ) ) {
			return '';
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- path controlado por get_template_directory(), arquivo local do tema.
	$content = file_get_contents( $path );

	return false !== $content ? (string) $content : '';
}

/**
 * Injeta o critical CSS inline no <head> em prioridade 0 (antes de tudo,
 * inclusive do preload de fontes em prioridade 1). Garante que regras de
 * above-the-fold já estejam disponíveis assim que o browser parsear o head.
 */
function rd_critical_css_inject_inline() {
	if ( ! rd_get_option_bool( 'inline_critical_css' ) ) {
		return;
	}
	if ( is_admin() ) {
		return;
	}

	$content = rd_critical_css_get_content();
	if ( '' === $content ) {
		return;
	}

	$nonce_attr = rd_csp_nonce_attr();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- conteúdo de CSS pré-gerado pelo extractor confiável (`critical` npm package). Escape HTML quebraria o CSS válido. Nonce attribute already escaped by rd_csp_nonce_attr().
	echo "\n<style id=\"rd-critical-css\"" . $nonce_attr . '>' . $content . "</style>\n";
}
add_action( 'wp_head', 'rd_critical_css_inject_inline', 0 );

/**
 * Transforma o `<link rel="stylesheet">` do handle `rd-main-style` em
 * preload async com fallback noscript. Só atua quando o toggle está ON.
 *
 * Pattern recomendado pela web.dev:
 *   <link rel="preload" href="..." as="style" onload="this.rel='stylesheet'">
 *   <noscript><link rel="stylesheet" href="..."></noscript>
 *
 * @param string $tag    O HTML completo da tag <link>.
 * @param string $handle O handle do enqueue (registrado em `mod-performance.php`).
 * @return string A tag modificada (ou inalterada se handle != rd-main-style).
 */
function rd_critical_css_defer_main_stylesheet( $tag, $handle ) {
	if ( ! rd_get_option_bool( 'inline_critical_css' ) ) {
		return $tag;
	}
	if ( is_admin() ) {
		return $tag;
	}
	if ( 'rd-main-style' !== $handle ) {
		return $tag;
	}
	// Se o critical CSS estiver ausente (template sem arquivo gerado), não
	// faz defer — manter síncrono pra evitar tela sem CSS.
	if ( '' === rd_critical_css_get_content() ) {
		return $tag;
	}

	// Extrai o href da tag original via regex simples (atributo href="...").
	if ( ! preg_match( '/href=([\'"])([^\'"]+)\1/', $tag, $matches ) ) {
		return $tag;
	}
	$href = $matches[2];

	// Reconstroi como preload + script de swap + noscript fallback.
	//
	// Antes usávamos `onload="this.rel='stylesheet'"` inline no <link>, mas
	// inline event handlers ('unsafe-hashes' no CSP) não são cobertos por
	// nonce/strict-dynamic. Refatorado pra um <script nonce> que adiciona o
	// listener via JS — mesmo comportamento, sem inline handler.
	//
	// O script é colocado imediatamente após o <link>, então a chance de o
	// load disparar antes do listener registrar é mínima. O check `l.sheet`
	// cobre o caso de cache HTTP (browser cacheou o CSS e populou sheet antes
	// do script rodar).
	$nonce_attr = rd_csp_nonce_attr();
	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- substituindo via style_loader_tag filter o link já enfileirado por mod-performance.php; este é o pattern oficial web.dev pra critical-CSS deferred loading.
	$preload     = sprintf(
		'<link rel="preload" href="%s" as="style" data-rd-defer-css>',
		esc_url( $href )
	);
	$swap_script = '<script' . $nonce_attr . '>(function(){var l=document.querySelector(\'link[data-rd-defer-css]\');if(!l)return;var done=false;var swap=function(){if(done)return;done=true;l.rel=\'stylesheet\';l.removeAttribute(\'data-rd-defer-css\');};l.addEventListener(\'load\',swap);if(l.sheet)swap();})();</script>';
	$noscript    = sprintf(
		'<noscript><link rel="stylesheet" href="%s"></noscript>',
		esc_url( $href )
	);
	// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet

	return $preload . "\n" . $swap_script . "\n" . $noscript . "\n";
}
add_filter( 'style_loader_tag', 'rd_critical_css_defer_main_stylesheet', 10, 2 );
