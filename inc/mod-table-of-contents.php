<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Table of Contents (TOC)                                              *
 *                                                                              *
 * Auto-gera TOC no final do `the_content` do single. Renderiza como FAB        *
 * flutuante top-right (debaixo do header) que expande um painel glass         *
 * pra esquerda+baixo quando clicado.                                          *
 *                                                                              *
 * Algoritmo (no filter the_content priority 25 — após Markdown @6,             *
 * wpautop @10, picture wrap @20):                                              *
 *   1. Parse via DOMDocument, encontra todos h2/h3                            *
 *   2. Se < RD_TOC_MIN_HEADINGS, sai sem injetar TOC                          *
 *   3. Adiciona id="slug" único em cada heading (sluggify + collision counter)*
 *   4. Constrói lista nested (h2 = top level, h3 = nested)                    *
 *   5. Anexa TOC ao final do conteúdo (position:fixed = flutua livre)         *
 *                                                                              *
 * Gate: feature controlada por `enable_table_of_contents` (default ON).       *
 *******************************************************************************/

const RD_TOC_MIN_HEADINGS = 3;

/**
 * Parse + adiciona IDs únicos em h2/h3 do HTML. Retorna [HTML modificado, entries].
 *
 * @param string $html HTML do conteúdo já processado (após Markdown/wpautop/etc).
 * @return array{0:string,1:array} [HTML com IDs, lista de entries].
 */
function rd_toc_parse_and_add_ids( $html ) {
	if ( strpos( $html, '<h2' ) === false && strpos( $html, '<h3' ) === false ) {
		return array( $html, array() );
	}

	libxml_use_internal_errors( true );
	$dom    = new DOMDocument();
	$loaded = $dom->loadHTML(
		'<?xml encoding="UTF-8">' . $html,
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	if ( ! $loaded ) {
		return array( $html, array() );
	}

	$xpath    = new DOMXPath( $dom );
	$headings = $xpath->query( '//h2 | //h3' );

	$used_slugs = array();
	// Cada entry: level (2 ou 3), text (string) e slug (string).
	$entries = array();

	foreach ( $headings as $heading ) {
		$text = trim( $heading->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native prop.
		if ( '' === $text ) {
			continue;
		}

		$base_slug = sanitize_title( $text );
		if ( '' === $base_slug ) {
			$base_slug = 'section';
		}
		$slug    = $base_slug;
		$counter = 2;
		while ( isset( $used_slugs[ $slug ] ) ) {
			$slug = $base_slug . '-' . $counter;
			++$counter;
		}
		$used_slugs[ $slug ] = true;

		if ( ! $heading->getAttribute( 'id' ) ) {
			$heading->setAttribute( 'id', $slug );
		} else {
			$slug = $heading->getAttribute( 'id' );
		}

		$entries[] = array(
			'level' => 'h2' === $heading->nodeName ? 2 : 3, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native prop.
			'text'  => $text,
			'slug'  => $slug,
		);
	}

	$modified = $dom->saveHTML();
	$modified = preg_replace( '/^<\?xml encoding="UTF-8">\s*/', '', $modified );

	return array( $modified, $entries );
}

/**
 * Cache estático de dados pre-processados por post_id.
 * Evita re-parsear conteúdo entre rd_render_table_of_contents() e o filter.
 *
 * @param int $post_id ID do post.
 * @return array{entries:array,modified_content:string}
 */
function rd_toc_get_cached_data( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	// Aplica TODOS os filtros the_content (markdown, wpautop, picture wrap...)
	// EXCETO o nosso (rd_toc_filter_the_content), pra obter HTML "renderizado"
	// sem recursão. Depois parseamos + adicionamos IDs.
	$raw = get_post_field( 'post_content', $post_id );
	remove_filter( 'the_content', 'rd_toc_filter_the_content', 25 );
	$processed = apply_filters( 'the_content', $raw );
	add_filter( 'the_content', 'rd_toc_filter_the_content', 25 );

	list( $modified, $entries ) = rd_toc_parse_and_add_ids( $processed );

	$cache[ $post_id ] = array(
		'entries'          => $entries,
		'modified_content' => $modified,
	);
	return $cache[ $post_id ];
}

/**
 * Template helper — renderiza o TOC HTML no markup do single.
 * Chamado de single.php DENTRO de .entry-title-row depois do post-tag.
 *
 * O HTML que renderiza é estruturado como:
 *   <div class="rd-toc-anchor">     ← absolute, scope sticky = entire <article>
 *     <div class="rd-toc">          ← sticky, segue scroll
 *       [FAB + panel]
 *     </div>
 *   </div>
 */
function rd_render_table_of_contents() {
	if ( ! rd_get_option_bool( 'enable_table_of_contents', true ) ) {
		return;
	}
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$data = rd_toc_get_cached_data( get_the_ID() );

	if ( count( $data['entries'] ) < RD_TOC_MIN_HEADINGS ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado internamente em rd_toc_render_html via esc_attr/esc_html.
	echo rd_toc_render_html( $data['entries'] );
}

/**
 * Filter em the_content — retorna o conteúdo COM IDs adicionados nas headings.
 * Reusa cache populado por rd_toc_get_cached_data() — zero re-parse.
 *
 * @param string $content HTML do conteúdo (vem após Markdown/wpautop/etc).
 * @return string Conteúdo com IDs em h2/h3.
 */
function rd_toc_filter_the_content( $content ) {
	if ( ! rd_get_option_bool( 'enable_table_of_contents', true ) ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$data = rd_toc_get_cached_data( get_the_ID() );
	if ( count( $data['entries'] ) < RD_TOC_MIN_HEADINGS ) {
		return $content;
	}

	return $data['modified_content'];
}
add_filter( 'the_content', 'rd_toc_filter_the_content', 25 );

/**
 * Renderiza o HTML do TOC (FAB + painel collapsible).
 *
 * @param array $entries Lista de [ 'level' => 2|3, 'text' => '...', 'slug' => '...' ]
 * @return string HTML escapado pronto pra concat.
 */
function rd_toc_render_html( $entries ) {
	$title_text = esc_html__( 'Table of contents', 'reloaded' );
	$fab_label  = esc_attr__( 'Open table of contents', 'reloaded' );
	$nav_label  = esc_attr__( 'Article navigation', 'reloaded' );

	// Constrói a lista nested. Estratégia simples: h2 abre <li>, h3 fica
	// dentro de <ul> aninhada no <li> do h2 anterior. Se h3 vier sem h2
	// antes, vira top-level (raro, mas defensivo).
	$list_html      = '';
	$h2_open        = false;
	$nested_ul_open = false;

	foreach ( $entries as $entry ) {
		$link_html = sprintf(
			'<a href="#%1$s">%2$s</a>',
			esc_attr( $entry['slug'] ),
			esc_html( $entry['text'] )
		);

		if ( 2 === $entry['level'] ) {
			// Fecha h3 nested se estava aberto
			if ( $nested_ul_open ) {
				$list_html     .= '</ul>';
				$nested_ul_open = false;
			}
			// Fecha h2 anterior
			if ( $h2_open ) {
				$list_html .= '</li>';
			}
			$list_html .= '<li class="rd-toc__item rd-toc__item--h2">' . $link_html;
			$h2_open    = true;
		} else { // level 3
			if ( ! $nested_ul_open ) {
				if ( ! $h2_open ) {
					// Orfão: h3 sem h2 — abre <li> top-level pra ele
					$list_html .= '<li class="rd-toc__item rd-toc__item--h2">';
					$h2_open    = true;
				}
				$list_html     .= '<ul class="rd-toc__sublist">';
				$nested_ul_open = true;
			}
			$list_html .= '<li class="rd-toc__item rd-toc__item--h3">' . $link_html . '</li>';
		}
	}
	// Fecha qualquer aberto
	if ( $nested_ul_open ) {
		$list_html .= '</ul>';
	}
	if ( $h2_open ) {
		$list_html .= '</li>';
	}

	$svg_list = '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>';

	// Estrutura: anchor wrapper (position absolute, escopa o sticky pro
	// <article> inteiro) + .rd-toc (sticky dentro do anchor).
	//
	// Anchor permite que sticky tenha scope = artigo inteiro sem deslocar
	// o conteúdo (vs float:right que espremia o kicker do .entry-title-row).
	//
	// Padding-tracking: o anchor usa CSS custom property `--rd-single-padding`
	// pra o `right`, definida em .single-post-content e compartilhada com o
	// `padding` do article. Mudanças de padding no _single.scss são seguidas
	// automaticamente pelo TOC sem alteração no _toc.scss.
	// Sentinel = elemento invisível 1×1 px posicionado no top do anchor (= natural
	// position do FAB antes do sticky kickar). IntersectionObserver no toc.js
	// observa esse sentinel pra detectar quando o sticky ativa — quando o sentinel
	// passa do top da viewport, adiciona .is-stuck no .rd-toc (que ativa box-shadow).
	return sprintf(
		'<div class="rd-toc-anchor">
			<div class="rd-toc__sentinel" aria-hidden="true"></div>
			<div class="rd-toc" data-rd-toc>
				<button type="button" class="rd-toc__fab" aria-controls="rd-toc-panel" aria-expanded="false" aria-label="%1$s">%2$s</button>
				<nav id="rd-toc-panel" class="rd-toc__panel" aria-label="%3$s">
					<h2 class="rd-toc__title">%4$s</h2>
					<ul class="rd-toc__list">%5$s</ul>
				</nav>
			</div>
		</div>',
		$fab_label,
		$svg_list,
		$nav_label,
		$title_text,
		$list_html
	);
}

/**
 * Enqueue do JS — só em singles de post.
 */
function rd_toc_enqueue_scripts() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	if ( ! rd_get_option_bool( 'enable_table_of_contents', true ) ) {
		return;
	}
	wp_enqueue_script(
		'rd-toc',
		get_template_directory_uri() . '/assets/js/toc.js',
		array(),
		rd_asset_version( '/assets/js/toc.js' ),
		true // footer
	);
}
add_action( 'wp_enqueue_scripts', 'rd_toc_enqueue_scripts' );
