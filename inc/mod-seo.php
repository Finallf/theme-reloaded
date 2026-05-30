<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: SEO - Open Graph, Twitter Cards e Meta Tags
 *******************************************************************************/

// Mínimo aceito pelo Facebook (200x200). Imagens menores são rejeitadas.
const RD_SEO_OG_IMAGE_MIN = 200;

/*******************************************************************************
 * Validação interna de imagens para og:image                          - (SEO) *
 *******************************************************************************/

/**
 * Valida um attachment do Media Library para uso como og:image.
 * Rejeita SVGs (não suportados por redes sociais) e imagens abaixo de 200x200.
 *
 * @param int $attachment_id
 * @return array|false ['url' => string, 'width' => int, 'height' => int] ou false se inválido
 */
function rd_seo_validate_attachment_image( int $attachment_id ) {
	// Rejeita SVG (Facebook, Twitter/X, WhatsApp e Discord não renderizam)
	if ( get_post_mime_type( $attachment_id ) === 'image/svg+xml' ) {
		return false;
	}

	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $src ) {
		return false;
	}

	list( $url, $width, $height ) = $src;

	// Rejeita imagens menores que o mínimo aceito pelo Facebook
	if ( $width < RD_SEO_OG_IMAGE_MIN || $height < RD_SEO_OG_IMAGE_MIN ) {
		return false;
	}

	return array(
		'url'    => $url,
		'width'  => (int) $width,
		'height' => (int) $height,
	);
}

/**
 * Valida uma URL de imagem (do painel, possivelmente externa) para og:image.
 * Se a URL pertencer ao Media Library, delega à validação completa (com dimensões).
 * Para URLs externas, confia na extensão e omite as dimensões — sem fazer HTTP
 * request a cada page load.
 *
 * @param string $url
 * @return array|false ['url' => string, 'width' => int|null, 'height' => int|null] ou false se inválido
 */
function rd_seo_validate_url_image( string $url ) {
	if ( empty( $url ) ) {
		return false;
	}

	$path = wp_parse_url( $url, PHP_URL_PATH );
	$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

	// Rejeita SVG e qualquer formato não-raster
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
		return false;
	}

	// Se a URL for de um attachment local, usa a validação completa
	// (respeita seu veredito; se falhar, NÃO cai pra modo "externa pura")
	$attachment_id = attachment_url_to_postid( $url );
	if ( $attachment_id ) {
		return rd_seo_validate_attachment_image( $attachment_id );
	}

	// URL externa — extensão raster válida, dimensões desconhecidas
	return array(
		'url'    => $url,
		'width'  => null,
		'height' => null,
	);
}

/**
 * Resolve a melhor imagem disponível para og:image, em ordem de preferência:
 *   1. Featured image do post (validada)
 *   2. Imagem fallback configurada no painel (validada)
 *   3. Logo do tema (controlado pelo dev, sempre considerado válido)
 *
 * @param int $post_id
 * @return array ['url' => string, 'width' => int|null, 'height' => int|null]
 */
function rd_seo_resolve_og_image( int $post_id ) {
	// 1. Featured image
	if ( has_post_thumbnail( $post_id ) ) {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		$resolved = rd_seo_validate_attachment_image( $thumb_id );
		if ( $resolved ) {
			return $resolved;
		}
	}

	// 2. og_fallback_image do painel (pode ser interna ou externa)
	$fallback = rd_get_option( 'og_fallback_image' );
	if ( ! empty( $fallback ) ) {
		$resolved = rd_seo_validate_url_image( $fallback );
		if ( $resolved ) {
			return $resolved;
		}
	}

	// 3. Fallback final: logo do tema
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp',
		'width'  => null,
		'height' => null,
	);
}

/*******************************************************************************
 * Extrai o handle (`@usuario`) de uma URL do Twitter/X                - (SEO) *
 *                                                                             *
 * Aceita formatos comuns:                                                     *
 *   https://x.com/usuario                                                     *
 *   https://twitter.com/usuario                                               *
 *   https://www.x.com/usuario/                                                *
 *                                                                             *
 * Retorna string vazia se a URL não bater no padrão (ex.: o admin colou um    *
 * link de tweet específico, ou outro domínio).                                *
 *******************************************************************************/
function rd_seo_extract_twitter_handle( string $url ): string {
	if ( empty( $url ) ) {
		return '';
	}

	if ( preg_match( '#https?://(?:www\.)?(?:twitter|x)\.com/([A-Za-z0-9_]{1,15})/?$#i', trim( $url ), $m ) ) {
		return '@' . $m[1];
	}

	return '';
}

/*******************************************************************************
 * Resolve a descrição "punchy" de um post singular                    - (SEO) *
 *                                                                             *
 * Usado pelo og:description e pelo <meta name="description"> em singulares.   *
 * Ordem: excerpt manual → trim de 25 palavras do post_content.                *
 *******************************************************************************/
function rd_seo_resolve_post_description( WP_Post $post ): string {
	$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
	if ( empty( $excerpt ) ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' );
	}
	return $excerpt;
}

/*******************************************************************************
 * Meta Tags Open Graph                                                - (SEO) *
 *******************************************************************************/
function rd_add_open_graph_tags() {
	if ( ! ( is_single() || is_page() ) ) {
		return;
	}
	if ( ! rd_get_option_bool( 'enable_open_graph' ) ) {
		return;
	}

	global $post;

	$title = get_the_title();
	$url   = get_permalink();

	$excerpt = rd_seo_resolve_post_description( $post );

	$image = rd_seo_resolve_og_image( (int) $post->ID );

	// --- IMPRESSÃO DAS META TAGS NA TELA ---
	echo "\n\n";
	echo '<meta property="og:type" content="article" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

	echo '<meta property="og:image" content="' . esc_url( $image['url'] ) . '" />' . "\n";

	// Só declara dimensões se realmente as conhecermos — evita mentir
	// pra rede social (que ajusta o crop com base nelas)
	if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
		echo '<meta property="og:image:width" content="' . (int) $image['width'] . '" />' . "\n";
		echo '<meta property="og:image:height" content="' . (int) $image['height'] . '" />' . "\n";
	}

	// --- Twitter Cards ---
	// O Twitter/X cai pra og:* quando não acha twitter:*, mas declarar
	// explicitamente garante render consistente e desbloqueia `summary_large_image`
	// como tipo de card (Twitter não infere isso do og:image).
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '" />' . "\n";

	// twitter:site — handle global do site, extraído da URL configurada em
	// Painel → Redes Sociais → Twitter (X). Aceita x.com/usuario ou
	// twitter.com/usuario. Omite se a URL não bater no padrão.
	$site_handle = rd_seo_extract_twitter_handle( (string) rd_get_option( 'social_twitter' ) );
	if ( ! empty( $site_handle ) ) {
		echo '<meta name="twitter:site" content="' . esc_attr( $site_handle ) . '" />' . "\n";
	}

	// twitter:creator — handle pessoal do autor do post. Lê de um user meta
	// `twitter_handle` que ainda não tem UI no perfil do WP (feature futura).
	// Quando o campo for adicionado, esse bloco passa a emitir a tag
	// automaticamente sem precisar tocar aqui.
	//
	// Fallback: se o autor não tem handle próprio, usa o `twitter:site` global.
	// Pra blog single-author (ou time pequeno) isso é correto na prática — o
	// handle do site E o autor são a mesma pessoa.
	$author_handle = trim( (string) get_the_author_meta( 'twitter_handle', (int) $post->post_author ) );
	if ( ! empty( $author_handle ) ) {
		// Garante o @ no começo (admin pode preencher "usuario" ou "@usuario")
		if ( strpos( $author_handle, '@' ) !== 0 ) {
			$author_handle = '@' . ltrim( $author_handle, '@' );
		}
	} elseif ( ! empty( $site_handle ) ) {
		$author_handle = $site_handle;
	}

	if ( ! empty( $author_handle ) ) {
		echo '<meta name="twitter:creator" content="' . esc_attr( $author_handle ) . '" />' . "\n";
	}

	echo "\n";
}
add_action( 'wp_head', 'rd_add_open_graph_tags', 5 );

/*******************************************************************************
 * Canonical URLs                                                      - (SEO) *
 *                                                                             *
 * O WP nativo (`rel_canonical`) só imprime <link rel="canonical"> em páginas  *
 * singulares (post, page, CPT). Nossa versão estende pra todas as superfícies *
 * indexáveis:                                                                 *
 *                                                                             *
 *   - Home / blog principal                                                   *
 *   - Archives de categoria, tag, taxonomia custom                            *
 *   - Archive de autor                                                        *
 *   - Archives de data (ano / mês / dia)                                      *
 *   - Página de busca (`?s=`)                                                 *
 *   - Páginas paginadas (`/page/N/`) preservadas em qualquer contexto         *
 *                                                                             *
 * Pula 404 e feeds — eles não precisam (e não devem ter) canonical próprio.   *
 *                                                                             *
 * Sem opção no painel: canonical é um sinal padrão de SEO sem trade-off,      *
 * ligar/desligar não faz sentido.                                             *
 *******************************************************************************/
function rd_add_canonical_url() {
	// 404 e feeds não recebem canonical
	if ( is_404() || is_feed() ) {
		return;
	}

	$canonical = '';

	if ( is_singular() ) {
		// wp_get_canonical_url() já trata pagination interna (?cpage=N) e o
		// protocolo correto. Falha graciosamente fora do loop.
		$canonical = wp_get_canonical_url();
	} elseif ( is_front_page() || is_home() ) {
		$canonical = home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$canonical = get_term_link( $term );
		}
	} elseif ( is_author() ) {
		$canonical = get_author_posts_url( (int) get_queried_object_id() );
	} elseif ( is_year() ) {
		$canonical = get_year_link( get_query_var( 'year' ) );
	} elseif ( is_month() ) {
		$canonical = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
	} elseif ( is_day() ) {
		$canonical = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
	} elseif ( is_search() ) {
		$canonical = home_url( '/?s=' . rawurlencode( get_search_query() ) );
	}

	// Paginação em archives: anexa /page/N/ (não se aplica a singular —
	// `wp_get_canonical_url` já fez isso lá em cima).
	if ( ! empty( $canonical ) && ! is_singular() && ! is_search() ) {
		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
		}
	}

	if ( empty( $canonical ) || is_wp_error( $canonical ) ) {
		return;
	}

	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
}

// Tira o canonical nativo do WP (cobre só singular) pra usar o nosso (cobre tudo)
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', 'rd_add_canonical_url', 1 );

/*******************************************************************************
 * Meta Description default                                            - (SEO) *
 *                                                                             *
 * Imprime <meta name="description"> em todas as superfícies indexáveis. O     *
 * Google ainda lê essa tag pra montar o snippet quando ela é mais relevante   *
 * que o conteúdo extraído da página.                                          *
 *                                                                             *
 *   - Singular: excerpt do post (helper compartilhado com OG)                 *
 *   - Home/Blog: tagline do site (Configurações Gerais → Descrição)           *
 *   - Category/Tag/Tax: descrição do termo, com fallback genérico             *
 *   - Author: bio do autor (campo "description"), com fallback genérico       *
 *   - Date archives: rótulo formatado tipo "Posts de Maio 2026"               *
 *   - Search e 404: pula (página dinâmica/inexistente)                        *
 *                                                                             *
 * Sem opção no painel: meta description é baseline de SEO, sem trade-off.     *
 *******************************************************************************/
function rd_add_meta_description() {
	if ( is_404() || is_search() || is_feed() ) {
		return;
	}

	$description = '';

	if ( is_singular() ) {
		global $post;
		if ( $post instanceof WP_Post ) {
			$description = rd_seo_resolve_post_description( $post );
		}
	} elseif ( is_front_page() || is_home() ) {
		$description = get_bloginfo( 'description' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$term_desc = wp_strip_all_tags( term_description( $term ) );
			if ( ! empty( $term_desc ) ) {
				$description = $term_desc;
			} else {
				/* translators: %s: category/tag/taxonomy term name */
				$description = sprintf( __( 'Posts in %s.', 'reloaded' ), $term->name );
			}
		}
	} elseif ( is_author() ) {
		$author_id  = (int) get_queried_object_id();
		$author_bio = wp_strip_all_tags( get_the_author_meta( 'description', $author_id ) );
		if ( ! empty( $author_bio ) ) {
			$description = $author_bio;
		} else {
			/* translators: %s: author display name */
			$description = sprintf( __( 'Posts by %s.', 'reloaded' ), get_the_author_meta( 'display_name', $author_id ) );
		}
	} elseif ( is_year() || is_month() || is_day() ) {
		// Mesma string "Archive of posts from %s." usada em 3 contextos com
		// formatos de data diferentes — comentário translators único e
		// genérico cobre os 3 (evita warning de "different translator comments"
		// do `wp i18n make-pot` quando a mesma string tem hints divergentes).
		if ( is_year() ) {
			$period = get_query_var( 'year' );
		} elseif ( is_month() ) {
			// Monta "F Y" localizado a partir das query vars (single_month_title
			// do WP usa o arg como prefix duplicado, gerando espaço extra).
			$ts     = mktime( 0, 0, 0, (int) get_query_var( 'monthnum' ), 1, (int) get_query_var( 'year' ) );
			$period = date_i18n( 'F Y', $ts );
		} else { // is_day()
			// Mesma lógica do month — query vars em vez de get_the_date() (que
			// pegaria a data do primeiro post no loop, não a do archive).
			$ts     = mktime( 0, 0, 0, (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ), (int) get_query_var( 'year' ) );
			$period = date_i18n( get_option( 'date_format' ), $ts );
		}
		/* translators: %s: archive period (year like "2026", month like "May 2026", or full date like "May 15, 2026") */
		$description = sprintf( __( 'Archive of posts from %s.', 'reloaded' ), $period );
	}

	$description = trim( wp_strip_all_tags( $description ) );
	if ( empty( $description ) ) {
		return;
	}

	// Trunca em ~160 caracteres pra ficar dentro do limite do snippet do Google
	if ( mb_strlen( $description ) > 160 ) {
		$description = mb_substr( $description, 0, 157 ) . '...';
	}

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'rd_add_meta_description', 2 );

/*******************************************************************************
 * Resolve o logo do site pra uso em Schema.org (publisher.logo)       - (SEO) *
 *                                                                             *
 * Ordem de preferência:                                                       *
 *   1. Custom Logo (Aparência → Personalizar → Identidade do Site)            *
 *   2. Logo padrão do tema (assets/img/logo-reloaded-panel.webp)             *
 *                                                                             *
 * Sempre retorna ['url', 'width', 'height']. Pro fallback do tema as          *
 * dimensões vão null (Google aceita imagem sem dimensões pra publisher).      *
 *******************************************************************************/
function rd_seo_resolve_site_logo(): array {
	// Delega pro helper centralizado em core.php (DRY com mod-maintenance,
	// mod-security e mod-integrations). Schema.org Organization logo usa 'full'
	// pra ter o tamanho original (Google prefere imagens grandes pro Knowledge
	// Panel — recomendação é mínimo 112x112, ideal 600x600+).
	if ( function_exists( 'rd_get_site_logo' ) ) {
		return rd_get_site_logo( 'full' );
	}

	// Fallback defensivo: se core.php não carregou (improvável), serve direto
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp',
		'width'  => 430,
		'height' => 100,
	);
}

/*******************************************************************************
 * Imprime um bloco <script type="application/ld+json">                - (SEO) *
 *                                                                             *
 * Helper interno: serializa em JSON com flags seguras pra HTML e envolve no   *
 * <script>. Usa JSON_UNESCAPED_SLASHES e JSON_UNESCAPED_UNICODE pra preservar *
 * URLs e acentos legíveis no source — Google parsa qualquer um, mas legível  *
 * é mais fácil de debugar.                                                    *
 *                                                                             *
 *
 * @param array $data Estrutura Schema.org pronta pra serializar.              *
 *******************************************************************************/
function rd_seo_print_jsonld( array $data ): void {
	$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return;
	}
	$nonce_attr = rd_csp_nonce_attr();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD pattern: wp_json_encode produces safe JSON output for <script type="application/ld+json"> blocks (HTML entity escaping would break valid JSON parsing by search engines). Nonce attribute already escaped by rd_csp_nonce_attr().
	echo '<script type="application/ld+json"' . $nonce_attr . '>' . $json . '</script>' . "\n";
}

/*******************************************************************************
 * Schema.org JSON-LD                                                  - (SEO) *
 *                                                                             *
 * Emite estruturas Schema.org no <head> pra que mecanismos de busca entendam *
 * o conteúdo da página de forma estruturada. Implementado por contexto:      *
 *                                                                             *
 *   - Singles (is_single): Article com headline, descrição, imagem, datas,   *
 *     autor (Person), publisher (Organization com logo) e mainEntityOfPage.  *
 *                                                                             *
 *   - Home/Front (is_front_page): WebSite com SearchAction — habilita o      *
 *     "sitelinks search box" do Google nos resultados de pesquisa.           *
 *                                                                             *
 * BreadcrumbList virá junto com o H7 (breadcrumbs visíveis).                 *
 *                                                                             *
 * Sem opção no painel — Schema é baseline de SEO técnico, sem trade-off.     *
 *******************************************************************************/
function rd_add_schema_jsonld() {
	// --- Article (somente posts, não pages estáticas) ---
	if ( is_single() ) {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$image       = rd_seo_resolve_og_image( (int) $post->ID );
		$logo        = rd_seo_resolve_site_logo();
		$author_id   = (int) $post->post_author;
		$description = rd_seo_resolve_post_description( $post );

		// Person com @id que matche o Person declarado em author.php — Google
		// amarra os dois schemas como o MESMO Person (E-E-A-T entre Article e
		// perfil do autor). Inclui image (avatar Gravatar) pra alimentar o
		// "author info" no painel de Rich Results. Fallback name pro
		// user_nicename/user_login se display_name estiver vazio.
		$author_display  = get_the_author_meta( 'display_name', $author_id );
		$author_nicename = get_the_author_meta( 'user_nicename', $author_id );
		$author_name     = ! empty( $author_display )
			? $author_display
			: ( ! empty( $author_nicename ) ? $author_nicename : get_the_author_meta( 'user_login', $author_id ) );

		$article = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
			'description'      => $description,
			'image'            => $image['url'],
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => array_filter(
				array(
					'@type' => 'Person',
					'@id'   => get_author_posts_url( $author_id ) . '#person',
					'name'  => $author_name,
					'url'   => get_author_posts_url( $author_id ),
					'image' => get_avatar_url( $author_id, array( 'size' => 240 ) ),
				)
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'logo'  => array_filter(
					array(
						'@type'  => 'ImageObject',
						'url'    => $logo['url'],
						'width'  => $logo['width'],
						'height' => $logo['height'],
					)
				),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
		);

		rd_seo_print_jsonld( $article );
		return;
	}

	// --- WebSite (somente home/front page) ---
	if ( is_front_page() || is_home() ) {
		$website = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'description'     => get_bloginfo( 'description' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		rd_seo_print_jsonld( $website );
	}
}
add_action( 'wp_head', 'rd_add_schema_jsonld', 5 );

/*******************************************************************************
 * Custom robots.txt                                                    - (SEO) *
 *                                                                              *
 * Substitui completamente o output gerado pelo `do_robots()` do WordPress     *
 * quando o admin preenche o campo no painel. Vazio = comportamento default WP *
 * preservado (User-agent, Disallow /wp-admin/, Allow admin-ajax, Sitemap).    *
 *                                                                              *
 * Usa o filter nativo `robots_txt($output, $public)`:                          *
 *   - $output: conteúdo atual gerado pelo WP                                   *
 *   - $is_public: true se Settings → Reading → "Discourage search engines" OFF
 *                                                                              *
 * Sanitização defensiva: strip de tags HTML (admins não devem colocar HTML    *
 * em robots.txt, mas wp_kses_post no save já garante isso). Trim de BOM       *
 * invisível e \r residuais pra evitar "invalid syntax" em validators strict.  *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $is_public faz parte da assinatura do filter `robots_txt`, mesmo quando o output custom independe da config "Discourage search engines".
function rd_custom_robots_txt( $output, $is_public ) {
	$custom = (string) rd_get_option( 'custom_robots_txt' );

	if ( trim( $custom ) === '' ) {
		return $output; // mantém o default do WP
	}

	// Remove BOM UTF-8 invisível (alguns editores salvam com \xEF\xBB\xBF no início)
	$custom = preg_replace( '/^\xEF\xBB\xBF/', '', $custom );

	// Normaliza line endings (CRLF/CR → LF) — alguns validators reclamam de \r
	$custom = str_replace( array( "\r\n", "\r" ), "\n", $custom );

	// Trim defensivo + garante newline no final (parsers strict pedem)
	$custom = rtrim( $custom ) . "\n";

	return $custom;
}
add_filter( 'robots_txt', 'rd_custom_robots_txt', 10, 2 );

/*******************************************************************************
 * Controle do Sitemap nativo WP 5.5+                                  - (SEO) *
 *                                                                              *
 * 3 níveis de controle, todos via filters nativos do WP:                       *
 *                                                                              *
 *   - enable_sitemap (toggle global): desativa /wp-sitemap.xml inteiro         *
 *     pra quem usa SEO plugin externo (Yoast, RankMath) com sitemap próprio   *
 *                                                                              *
 *   - sitemap_include_authors: remove o sub-sitemap /wp-sitemap-users-*.xml   *
 *     Default OFF — solo blogs raramente querem expor /author/* (duplica home)*
 *                                                                              *
 *   - sitemap_include_cpt: mantém apenas post + page no sitemap quando OFF.   *
 *     Útil pra esconder CPTs de uso interno (widgets, configurações) do índice*
 */

// 1. Toggle global — desativa o sistema inteiro
add_filter(
	'wp_sitemaps_enabled',
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $enabled faz parte da assinatura do filter; ignoramos o default e retornamos só o que está no painel.
	function ( $enabled ) {
		return rd_get_option_bool( 'enable_sitemap' );
	}
);

// 2. Excluir provider de Authors quando desabilitado
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		if ( $name === 'users' && ! rd_get_option_bool( 'sitemap_include_authors' ) ) {
			return false; // remove o sub-sitemap inteiro
		}
		return $provider;
	},
	10,
	2
);

// 3. Filtrar post types — quando CPT é OFF, mantém apenas os builtin
add_filter(
	'wp_sitemaps_post_types',
	function ( $post_types ) {
		if ( rd_get_option_bool( 'sitemap_include_cpt' ) ) {
			return $post_types; // mantém todos (post, page, e CPTs)
		}
		// Mantém apenas post + page (os builtin), descarta CPTs
		$allowed = array( 'post', 'page' );
		return array_intersect_key( $post_types, array_flip( $allowed ) );
	}
);
