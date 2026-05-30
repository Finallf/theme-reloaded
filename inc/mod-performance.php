<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Performance - Otimizações e Limpeza do WordPress
 *******************************************************************************/

/*******************************************************************************
 * Suporte a Markdown, Alertas (GFM), Âncoras Dinâmicas        - (Performance) *
 *******************************************************************************/
function rd_markdown_support( $content ) {

	if ( ! is_admin() && rd_get_option_bool( 'markdown_enabled' ) ) {

		// OTIMIZAÇÃO: Carrega a biblioteca pesada apenas quando realmente for usar
		if ( ! class_exists( 'Parsedown' ) ) {
			require_once get_template_directory() . '/lib/Parsedown.php';
		}

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( false );
		$html = $parsedown->text( $content ); // Converte o Markdown básico

		// 1. Intercepta os blocos de citação que contêm os alertas
		$html = preg_replace(
			'/<blockquote>\s*<p>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(?:<br\s*\/?>|<\/p>)?/i',
			'<blockquote class="gh-alert gh-alert-$1"><p>',
			$html
		);

		// 2. Adiciona IDs automáticos e limpos a todos os cabeçalhos (h1 até h6)
		$html = preg_replace_callback(
			'/<h([1-6])>(.*?)<\/h\1>/is',
			function ( $matches ) {
				$level          = $matches[1];
				$texto_original = $matches[2];

				// 1. Remove tags HTML de dentro do título
				$texto_puro = wp_strip_all_tags( $texto_original );

				// 2. Converte para minúsculas (suportando acentuação)
				$id = mb_strtolower( $texto_puro, 'UTF-8' );

				// 3. A REFINAÇÃO FINAL DO GITHUB:
				// Mantém Letras (\p{L}), Marcadores de cor (\p{M}), Formatadores/Colas invisíveis (\p{Cf}),
				// Números (\p{Nd}), Espaços (\s) e Hifens (-). Apaga os Símbolos base.
				$id = preg_replace( '/[^\p{L}\p{M}\p{Cf}\p{Nd}\s-]/u', '', $id );

				// 4. O SEGREDO DO GITHUB: Troca CADA espaço individual por um hífen.
				$id = preg_replace( '/\s/u', '-', $id );

				// 5. Codifica para URL (Transforma a "cola" e o "fantasma" invisíveis em %E2...%EF...)
				$id = rawurlencode( $id );

				// Proteção extra: se o título sumir completamente
				if ( empty( $id ) || $id === '-' ) {
					$id = 'secao-' . uniqid();
				}

				// Reconstrói a tag HTML com o novo ID inserido
				return "<h{$level} id=\"{$id}\">{$texto_original}</h{$level}>";
			},
			$html
		);

		// 3. Remove as tags <p> extras em volta de <br> isolados
		$html = preg_replace( '/<p>\s*(<br\s*\/?>)\s*<\/p>/i', '$1', $html );

		return $html;
	}
	return $content;
}
add_filter( 'the_content', 'rd_markdown_support', 6 );

/*******************************************************************************
 * Enfileira scripts e estilos (CSS e JS)                      - (Performance) *
 *******************************************************************************/
function rd_scripts() {
	wp_enqueue_style( 'rd-main-style', get_template_directory_uri() . '/assets/css/style.css', array(), rd_asset_version( '/assets/css/style.css' ) );
	wp_enqueue_script( 'rd-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), rd_asset_version( '/assets/js/navigation.js' ), true ); // Carrega no footer

	// INJETA AS TRADUÇÕES DO JS LOGO ABAIXO DO SCRIPT PRINCIPAL
	wp_localize_script(
		'rd-navigation',
		'reloaded_i18n',
		array(
			'copied'     => esc_html__( 'Key Copied!', 'reloaded' ),
			'copy_error' => esc_html__( 'Error copying: ', 'reloaded' ),
		)
	);

	// Trava do Painel: Só carrega o Prism.js se a chave estiver ligada
	if ( rd_get_option_bool( 'prism_js' ) ) {
		// Carrega o Prism.js apenas nas páginas de artigo.
		if ( is_single() || is_page() ) {
			wp_enqueue_script( 'rd-prism-js', get_template_directory_uri() . '/lib/prism.js', array(), '1.30.0', true );
		}
	}
}

/*******************************************************************************
 * Adiciona `defer` aos scripts do tema                        - (Performance) *
 *                                                                             *
 * Defer = baixa em paralelo com HTML, executa só depois do DOM parsed e ANTES *
 * do DOMContentLoaded. Como nossos scripts rodam em DOMContentLoaded mesmo,   *
 * é seguro. Lighthouse detecta o atributo e ajusta a prioridade de download   *
 * (mais baixa, libera bandwidth pra recursos críticos primeiro).              *
 *                                                                             *
 * Usa o filter `script_loader_tag` em vez da API moderna `'strategy' =>       *
 * 'defer'` (WP 6.3+) pra manter compatibilidade com a versão mínima.          *
 *******************************************************************************/
function rd_defer_theme_scripts( $tag, $handle ) {
	$deferred = array( 'rd-navigation', 'rd-prism-js' );
	if ( in_array( $handle, $deferred, true ) && strpos( $tag, ' defer' ) === false ) {
		$tag = str_replace( '<script ', '<script defer ', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'rd_defer_theme_scripts', 10, 2 );

/*******************************************************************************
 * Otimiza thumbnails do bloco "Últimos Posts" do Gutenberg    - (Performance) *
 *                                                                              *
 * O `core/latest-posts` block usa por default o size `thumbnail` do WP        *
 * (150x150 cropado), que no nosso layout do widget é exibido ~128x78 — fora  *
 * de aspect ratio, e dependendo do source o WP serve a imagem ORIGINAL        *
 * (414x622, 514x512 etc.) porque nenhum size próximo bate.                    *
 *                                                                             *
 * Injetamos o atributo `featuredImageSizeSlug` na renderização do bloco       *
 * forçando uso do nosso `rd-micro` (150x84, 16:9 hardcrop) — bate com o       *
 * aspect ratio do widget, fica leve.                                          *
 *                                                                             *
 * Só vale se `image_resizing` está ativo (sizes custom registrados).          *
 *******************************************************************************/
function rd_override_latest_posts_size( $parsed_block ) {
	if ( ! rd_get_option_bool( 'image_resizing' ) ) {
		return $parsed_block;
	}

	if ( isset( $parsed_block['blockName'] ) && $parsed_block['blockName'] === 'core/latest-posts' ) {
		$parsed_block['attrs']['featuredImageSizeSlug'] = 'rd-micro';
	}
	return $parsed_block;
}
add_filter( 'render_block_data', 'rd_override_latest_posts_size' );

/*******************************************************************************
 * Override do `sizes` attribute pro size `rd-card`            - (Performance) *
 *                                                                              *
 * O WP gera `sizes="(max-width: 600px) 100vw, 600px"` por default, dizendo   *
 * pro browser "vou ocupar 600px em desktop". Mas no nosso grid 2-col com      *
 * sidebar, cada card mede ~461px em desktop ≥1025px. Browser acaba pegando o *
 * variant 600px ao invés de algo menor.                                       *
 *                                                                             *
 * Corrigimos pro browser saber a largura real por breakpoint:                  *
 *   - Desktop (≥1025px com sidebar): ~461px por card                          *
 *   - Tablet (769-1024px): 50vw (2 cards por linha sem sidebar)               *
 *   - Mobile (≤768px): 100vw (1 coluna)                                       *
 *******************************************************************************/
function rd_calculate_card_sizes( $sizes, $size ) {
	// $size pode chegar como string slug ('rd-card') OU como array
	// [width, height] — o WP converte a slug pras dimensões em alguns paths
	// antes de disparar esse filter. Detectamos os dois formatos.
	$is_card = ( $size === 'rd-card' )
		|| ( is_array( $size ) && isset( $size[0], $size[1] ) && (int) $size[0] === 600 && (int) $size[1] === 338 );

	if ( $is_card ) {
		return '(min-width: 1025px) 461px, (min-width: 769px) 50vw, 100vw';
	}
	return $sizes;
}
add_filter( 'wp_calculate_image_sizes', 'rd_calculate_card_sizes', 10, 2 );
add_action( 'wp_enqueue_scripts', 'rd_scripts' );

/*******************************************************************************
 * Desativa emojis e estilos automáticos do WP                 - (Performance) *
 *******************************************************************************/
function rd_disable_emojis() {

	if ( ! rd_get_option_bool( 'disable_emojis' ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'rd_disable_emojis' );

/*******************************************************************************
 * Melhora a segurança, remove a versão do WP                  - (Performance) *
 */
add_filter(
	'the_generator',
	function ( $gen ) {
		return rd_get_option_bool( 'hide_wp_ver' ) ? '' : $gen;
	}
);

/*******************************************************************************
 * Intercepta iframes (Youtube Facade)                         - (Performance) *
 *******************************************************************************/

/**
 * Parseia um valor de timestamp do YouTube e retorna inteiro em segundos.
 *
 * Formatos aceitos (todos válidos no YouTube):
 *   "30"        → 30s
 *   "30s"       → 30s
 *   "1m30s"     → 90s
 *   "1h2m30s"   → 3750s
 *   ""/null/inválido → 0
 */
function rd_youtube_parse_timestamp( $t ) {
	if ( $t === '' || $t === null ) {
		return 0;
	}
	// Caso "número puro" (ex: "?t=90") — YouTube interpreta como segundos
	if ( ctype_digit( (string) $t ) ) {
		return (int) $t;
	}
	// Caso com sufixos h/m/s (ex: "1h2m30s") — soma cada componente presente
	$seconds = 0;
	if ( preg_match( '/(\d+)h/i', $t, $m ) ) {
		$seconds += (int) $m[1] * 3600;
	}
	if ( preg_match( '/(\d+)m/i', $t, $m ) ) {
		$seconds += (int) $m[1] * 60;
	}
	if ( preg_match( '/(\d+)s/i', $t, $m ) ) {
		$seconds += (int) $m[1];
	}
	return $seconds;
}

// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $attr faz parte da assinatura do filter `embed_oembed_html`, mesmo quando não usado.
function rd_youtube_facade( $cache, $url, $attr ) {

	if ( ! rd_get_option_bool( 'facade_youtube' ) ) {
		return $cache; }

	if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false || strpos( $url, 'youtube-nocookie.com' ) !== false ) {
		preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|v/)|youtu\.be/|youtube-nocookie\.com/embed/)([a-zA-Z0-9_-]{11})~', $url, $matches );
		$video_id = isset( $matches[1] ) ? $matches[1] : '';

		// Timestamp: tenta `t=` primeiro (formato user-facing); cai pra `start=` (formato embed)
		$timestamp = 0;
		if ( preg_match( '/[?&]t=([^&]+)/', $url, $t_matches ) ) {
			$timestamp = rd_youtube_parse_timestamp( $t_matches[1] );
		}
		if ( $timestamp === 0 && preg_match( '/[?&]start=(\d+)/', $url, $s_matches ) ) {
			$timestamp = (int) $s_matches[1];
		}

		if ( $video_id ) {
			$data_t = $timestamp > 0 ? ' data-t="' . esc_attr( $timestamp ) . '"' : '';
			return '<div class="rd-facade" data-type="youtube" data-id="' . esc_attr( $video_id ) . '"' . $data_t . '>
                        <img src="https://img.youtube.com/vi/' . esc_attr( $video_id ) . '/sddefault.jpg" alt="Video cover" loading="lazy" width="640" height="480">
                        <div class="play-button">
                            <svg viewBox="0 0 68 48">
                                <path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55c-2.93.78-4.64 3.26-5.42 6.19C.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="#FF0000"/>
                                <path d="M27.26 33.15V14.85L44.5 24z" fill="#fff"/>
                            </svg>
                        </div>
                    </div>';
		}
	}
	return $cache;
}
add_filter( 'embed_oembed_html', 'rd_youtube_facade', 10, 3 );

/*******************************************************************************
 * Desativa o CSS do Gutenberg e Global Syles                  - (Performance) *
 *******************************************************************************/
function rd_disable_gutenberg_assets() {
	if ( ! rd_get_option_bool( 'disable_gutenberg_css' ) ) {
		return;
	}

	add_filter( 'should_load_separate_core_block_assets', '__return_false' );

	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

	add_action(
		'wp_enqueue_scripts',
		function () {
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'classic-theme-styles' );
			wp_dequeue_style( 'wc-blocks-style' );
		},
		100
	);
}
add_action( 'after_setup_theme', 'rd_disable_gutenberg_assets' );

/*******************************************************************************
 * Limita revisões de posts                                    - (Performance) *
 *                                                                             *
 * WP guarda revisões ilimitadas por padrão (cada salvamento + autosave).      *
 * Em sites antigos, a tabela wp_posts incha com centenas de revisões por      *
 * post. Esse filter cap'a o número configurado pelo admin.                    *
 *                                                                             *
 * Comportamento por valor:                                                    *
 *   - 0: nenhuma revisão é guardada (saves direto no post sem histórico)      *
 *   - 1+: mantém apenas as N revisões mais recentes; antigas são removidas    *
 *         automaticamente quando o post é salvo                               *
 *                                                                             *
 * Nota: revisões antigas que já existem no banco NÃO são apagadas             *
 * retroativamente. O cap só vale dos próximos saves em diante.                *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $num e $post fazem parte da assinatura do filter `wp_revisions_to_keep`, mesmo quando o valor final só depende da config do painel.
function rd_limit_post_revisions( $num, $post ) {
	$configured = (int) rd_get_option( 'post_revisions_limit', 5 );
	return max( 0, min( 50, $configured ) );
}
add_filter( 'wp_revisions_to_keep', 'rd_limit_post_revisions', 10, 2 );

/*******************************************************************************
 * Otimiza o Heartbeat API do WordPress                        - (Performance) *
 *                                                                             *
 * Heartbeat dispara requests AJAX pra `admin-ajax.php` em background:         *
 *   - Editor de post: 15s base, acelera pra 5s durante autosave ativo         *
 *   - Demais telas admin: 60s (default)                                       *
 *   - Frontend: NÃO é enqueue por default — só quando plugins pedem           *
 *     (WooCommerce mini-cart, BuddyPress notifications, etc.)                 *
 *                                                                             *
 * Otimização (toggle ON):                                                     *
 *   - Editor de post: intocado (autosave + lock de edição preservados)        *
 *   - Demais admin: 60s → 120s (máximo permitido pelo WP, -50% requests)     *
 *     Trade-off: notificações em tempo real (locks, contadores) chegam mais  *
 *     lentas, mas é aceitável pra blog/portal típico                          *
 *   - Frontend: deregister total (defensivo — se um plugin futuro adicionar  *
 *     heartbeat lá, fica bloqueado de antemão)                                *
 *                                                                             *
 * Toggle default OFF — não introduz mudança sem opt-in do admin.              *
 *******************************************************************************/
function rd_optimize_heartbeat_frontend() {
	if ( ! rd_get_option_bool( 'optimize_heartbeat' ) ) {
		return;
	}
	wp_deregister_script( 'heartbeat' );
}
add_action( 'wp_enqueue_scripts', 'rd_optimize_heartbeat_frontend', 1 );

function rd_optimize_heartbeat_settings( $settings ) {
	if ( ! rd_get_option_bool( 'optimize_heartbeat' ) ) {
		return $settings;
	}

	// No editor de post mantém o default (autosave depende do interval curto)
	global $pagenow;
	if ( isset( $pagenow ) && in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
		return $settings;
	}

	// 120s é o máximo permitido pelo WP (qualquer valor maior é capado)
	$settings['interval'] = 120;
	return $settings;
}
add_filter( 'heartbeat_settings', 'rd_optimize_heartbeat_settings' );

/*******************************************************************************
 * DNS Prefetch + Preconnect pra domínios externos             - (Performance) *
 *                                                                             *
 * Usa o filter nativo `wp_resource_hints` pra injetar hints no <head>:        *
 *                                                                             *
 *   - preconnect: resolve DNS + handshake TCP + TLS antecipadamente. Custa    *
 *     ~1-2KB de banda mas economiza ~300ms quando o recurso é solicitado.    *
 *     Usado pra domínios que sabemos que vão carregar (ex: YouTube quando    *
 *     user clica no facade).                                                  *
 *                                                                             *
 *   - dns-prefetch: resolve só o DNS antecipadamente, mais conservador.       *
 *     Custa quase nada e economiza ~150ms. Usado pra domínios "talvez"       *
 *     (thumbnails CDN, Gravatar, GA).                                         *
 *                                                                             *
 * Cada hint só é injetado se a feature correspondente estiver ativa — não    *
 * vale a pena anunciar domínio que o tema não usa naquela instalação.        *
 *                                                                             *
 * Sem opção no painel: é otimização baseline, zero downside.                  *
 *******************************************************************************/
function rd_add_resource_hints( $hints, $relation_type ) {

	if ( $relation_type === 'preconnect' ) {
		// YouTube iframe — carregado quando user clica no facade. Só vale
		// pré-conectar quando há chance real de ter embed na página: singular
		// (post/page com conteúdo) e search (resultados podem ter posts com YT).
		// Em home/archive de listagem o preconnect fica "wasted" e Lighthouse
		// reclama — então condicionamos.
		if ( rd_get_option_bool( 'facade_youtube' ) && ( is_singular() || is_search() ) ) {
			$hints[] = array(
				'href'        => 'https://www.youtube.com',
				'crossorigin' => 'anonymous',
			);
			$hints[] = array(
				'href'        => 'https://www.youtube-nocookie.com',
				'crossorigin' => 'anonymous',
			);
		}
	}

	if ( $relation_type === 'dns-prefetch' ) {
		// YouTube thumbnails — usadas no markup do facade (carregam logo)
		if ( rd_get_option_bool( 'facade_youtube' ) ) {
			$hints[] = 'https://img.youtube.com';
			$hints[] = 'https://i.ytimg.com';
		}

		// Discord widget — iframe carregado direto na sidebar quando widget
		// está habilitado (mesmo sem facade, o domínio é o mesmo)
		if ( rd_get_option_bool( 'discord_widget' ) ) {
			$hints[] = 'https://ptb.discord.com';
		}

		// Google Tag Manager — só quando GA está configurado no painel
		$ga_id = rd_get_option( 'ga_id' );
		if ( ! empty( $ga_id ) ) {
			$hints[] = 'https://www.googletagmanager.com';
		}

		// Gravatar — avatars dos comentários (parte do WP padrão)
		$hints[] = 'https://secure.gravatar.com';
	}

	return $hints;
}
add_filter( 'wp_resource_hints', 'rd_add_resource_hints', 10, 2 );

/*******************************************************************************
 * Preload de fontes críticas                                  - (Performance) *
 *                                                                             *
 * O browser só descobre as @font-face depois de parsear o CSS. `<link         *
 * rel="preload" as="font">` no <head> inicia o download da fonte em paralelo  *
 * com o stylesheet, eliminando o gap entre "CSS chegou" e "fonte chegou".    *
 *                                                                             *
 * Preload é caro — bloqueia recursos críticos por proritizar essas fontes.    *
 * Por isso só pre-loadamos as **duas mais usadas em above-the-fold**:         *
 *                                                                             *
 *   - Inter Regular (400): texto base — parágrafos, sidebar, body geral       *
 *   - Poppins Bold (700): cabeçalhos — títulos de post, logo, navegação      *
 *                                                                             *
 * As outras 14 variantes (Inter italic/500/600/700, Poppins 500/600/800/900, *
 * todas Mono) carregam normalmente via @font-face conforme necessárias.       *
 *                                                                             *
 * `crossorigin="anonymous"` é obrigatório pra preload de fonte (CORS spec).  *
 * Prioridade 1 no wp_head pra sair antes do stylesheet — embora o browser    *
 * processe preload em paralelo, posição precoce ajuda em navegadores antigos.*
 *******************************************************************************/
function rd_preload_critical_fonts() {
	if ( ! rd_get_option_bool( 'preload_critical_fonts' ) ) {
		return;
	}

	$base = get_template_directory_uri() . '/assets/fonts/';
	// Lista validada por auditoria do Network (DevTools, cache desabilitado):
	// todas essas variantes aparecem em prioridade "Highest" assim que o browser
	// parseia o style.css. Preload antecipa o download em paralelo com o CSS,
	// reduzindo FOIT/FOUT no first paint. Itens que sobraram em prioridade
	// "Mais Alta" no parse (poppins-500/800, inter-600/italic, jetbrains-mono)
	// ficam de fora — item de auditoria no Background do BACKLOG vai decidir
	// se são realmente usadas above-the-fold ou se o SCSS pode reduzir o uso.
	$fonts = array(
		'inter-regular.woff2',  // texto base — Inter 400
		'inter-500.woff2',      // ênfase / links — Inter Medium
		'inter-700.woff2',      // bold inline — Inter Bold
		'poppins-600.woff2',    // subtítulos / headings secundários — Poppins SemiBold
		'poppins-700.woff2',    // títulos principais — Poppins Bold
	);

	foreach ( $fonts as $file ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
			esc_url( $base . $file )
		);
	}
}
add_action( 'wp_head', 'rd_preload_critical_fonts', 1 );

/*******************************************************************************
 * Garante lazy loading em iframes do conteúdo                 - (Performance) *
 *                                                                             *
 * WP 5.9+ já adiciona `loading="lazy"` automaticamente em iframes do          *
 * `the_content()`, exceto o primeiro iframe acima da dobra (heurística do WP *
 * pra não atrapalhar LCP). Esse filter é DEFENSIVO — caso algum plugin       *
 * desative `wp_lazy_loading_enabled`, garantimos que iframes continuem lazy. *
 *                                                                             *
 * Iframes fora do_the_content() (ex: Discord widget no sidebar) são tratados *
 * caso a caso no markup (atributo `loading="lazy"` direto na tag), porque    *
 * esse filter só atua em conteúdo passado por wp_filter_content_tags().      *
 *                                                                             *
 * Sem opção no painel — lazy iframe é HTML5 nativo, zero downside.           *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $context faz parte da assinatura do filter `wp_lazy_loading_enabled`, mesmo quando só checamos o tag_name.
function rd_force_iframe_lazy( $enabled, $tag_name, $context ) {
	if ( $tag_name === 'iframe' ) {
		return true;
	}
	return $enabled;
}
add_filter( 'wp_lazy_loading_enabled', 'rd_force_iframe_lazy', 10, 3 );
