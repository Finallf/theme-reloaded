<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Image Formats (WebP/AVIF) — Next-gen image delivery                 *
 *                                                                             *
 * Gera automaticamente versões WebP e/ou AVIF de cada imagem JPEG/PNG no      *
 * upload (todos os tamanhos do WP + tamanhos custom do tema). Entrega via    *
 * <picture> com fallback transparente pro original.                           *
 *                                                                             *
 * Server requirements (degrada graciosamente — sem dependência forte):       *
 *   - Imagick + ImageMagick com WEBP/AVIF (preferencial, qualidade superior) *
 *   - GD com WebP (PHP 7.1+) — fallback                                       *
 *   - GD com AVIF (PHP 8.1+) — fallback (só se ImageMagick não tiver)         *
 *   Nenhum dos dois com WebP/AVIF → módulo dormente, mostra aviso no painel  *
 *                                                                             *
 * Filosofia:                                                                  *
 *   - Original (JPEG/PNG) sempre preservado — next-gen são acréscimos        *
 *   - Browser pega <source type> compatível, cai pro <img> original          *
 *   - AVIF antes do WebP no <picture> (maior compressão pra quem suporta)    *
 *                                                                             *
 * Cobertura (Fase 1):                                                         *
 *   ✅ Featured images, post thumbnails, post-card, galleries via              *
 *      wp_get_attachment_image                                                *
 *   ⏳ Imagens inline do editor Gutenberg (block core/image) — Fase 2 futura  *
 *******************************************************************************/

const RD_IMG_REGEN_CHUNK = 10;

/**
 * Wrapper centralizado dos defaults do módulo — passa default explícito em
 * cada chamada do helper genérico rd_get_option. Necessário pra opções novas
 * adicionadas DEPOIS da primeira ativação do tema (rd_set_default_options só
 * roda em after_switch_theme), evitando que features fiquem dormentes
 * silenciosamente porque a key não existe ainda no rd_settings do banco.
 */
function rd_img_is_enabled(): bool {
	return (int) rd_get_option( 'enable_next_gen_images', 1 ) === 1;
}

function rd_img_get_mode(): string {
	$mode = rd_get_option( 'image_format_mode', 'avif' );
	return in_array( $mode, array( 'avif', 'webp', 'both' ), true ) ? $mode : 'avif';
}

/**
 * Qualidade unificada com a config global do painel (`jpeg_quality` em
 * Recursos Gerais). Mesmo valor é aplicado a JPEG (via filter do WP em
 * mod-general.php) e ao WebP/AVIF que geramos aqui — coerência total: se o
 * admin mudar pra 85, todos os formatos ficam 85. Default 80 se a key não
 * estiver no rd_settings ou se o valor for inválido. Clamp 1-100 defensivo.
 */
function rd_img_get_quality(): int {
	$quality = (int) rd_get_option( 'jpeg_quality', 80 );
	return max( 1, min( 100, $quality ) );
}

/*
=============================================================================
 *  CAPABILITY DETECTION
 * ============================================================================= */

/**
 * Detecta o que o servidor suporta. Cache static dentro da request.
 */
function rd_img_get_capabilities(): array {
	static $caps = null;
	if ( $caps !== null ) {
		return $caps;
	}

	$caps = array(
		'imagick'      => extension_loaded( 'imagick' ),
		'webp_imagick' => false,
		'avif_imagick' => false,
		'webp_gd'      => function_exists( 'imagewebp' ),
		'avif_gd'      => function_exists( 'imageavif' ),
	);

	if ( $caps['imagick'] ) {
		try {
			$formats              = ( new Imagick() )->queryFormats();
			$caps['webp_imagick'] = in_array( 'WEBP', $formats, true );
			$caps['avif_imagick'] = in_array( 'AVIF', $formats, true );
		} catch ( Exception $e ) {
			unset( $e ); // Imagick disponível mas não conseguiu listar formatos — fica em fallback (GD).
		}
	}

	return $caps;
}

function rd_img_can_generate( string $format ): bool {
	$caps = rd_img_get_capabilities();
	if ( $format === 'webp' ) {
		return $caps['webp_imagick'] || $caps['webp_gd'];
	}
	if ( $format === 'avif' ) {
		return $caps['avif_imagick'] || $caps['avif_gd'];
	}
	return false;
}

/*
=============================================================================
 *  CONVERSION ENGINE
 * ============================================================================= */

/**
 * Converte 1 arquivo (JPEG/PNG) pra WebP ou AVIF.
 * Prefere Imagick (qualidade superior), fallback GD.
 *
 * @return string|false Path do arquivo gerado ou false em caso de falha
 */
function rd_img_convert( string $source_path, string $target_format, ?int $quality = null ) {
	// Quality default: lê do painel (mesma config dos JPEGs) — coerência total.
	// Override via parâmetro permite uso programático com valor específico.
	if ( $quality === null ) {
		$quality = rd_img_get_quality();
	}
	if ( ! file_exists( $source_path ) ) {
		return false;
	}
	if ( ! in_array( $target_format, array( 'webp', 'avif' ), true ) ) {
		return false;
	}

	$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return false;
	}

	$target_path = preg_replace( '/\.(jpe?g|png)$/i', '.' . $target_format, $source_path );
	if ( $target_path === $source_path ) {
		return false;
	}

	$caps = rd_img_get_capabilities();

	// Decisão de engine por formato + source:
	// - WebP a partir de qualquer formato → Imagick (qualidade superior, alpha OK)
	// - AVIF a partir de JPEG → Imagick (qualidade superior, JPEG não tem alpha)
	// - AVIF a partir de PNG → SKIP Imagick, vai direto pra GD
	// Razão: ImageMagick 6 + libheif tem bug histórico que substitui fundos
	// transparentes por preto ao gerar AVIF. ImageMagick 7 pode resolver mas
	// depende da versão exata do servidor. Solução portátil: pra PNG (que
	// potencialmente tem alpha) usa GD que tem suporte nativo + correto pra
	// AVIF com transparência (PHP 8.1+).
	// Ref: https://alexwlchan.net/2023/check-for-transparency/
	$force_gd_for_avif = ( $target_format === 'avif' && $ext === 'png' );

	// 1ª tentativa: Imagick (skip se PNG → AVIF)
	if ( ! $force_gd_for_avif && $caps['imagick'] && $caps[ $target_format . '_imagick' ] ) {
		try {
			$img = new Imagick( $source_path );

			// Preserva alpha channel (transparência) — necessário pra WebP a partir
			// de PNG transparente. Sem isso Imagick descarta o alpha e o background
			// fica preto/branco (comportamento não-determinístico).
			$img->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
			$img->setBackgroundColor( new ImagickPixel( 'transparent' ) );

			if ( defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
				$img->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
			}

			$img->setImageFormat( strtoupper( $target_format ) );
			$img->setImageCompressionQuality( $quality );

			// AVIF: heic:speed balanceia velocidade vs qualidade (0=lento+melhor, 10=rápido+pior)
			if ( $target_format === 'avif' ) {
				$img->setOption( 'heic:speed', '6' );
			}

			$img->writeImage( $target_path );
			$img->clear();
			$img->destroy();
			return $target_path;
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- log de falha do encoder pra debug, gated por WP_DEBUG conforme guideline do wp.org.
				error_log( 'rd_img_convert: Imagick failed for ' . $source_path . ' → ' . $target_format . ': ' . $e->getMessage() );
			}
			// continua pro GD fallback
		}
	}

	// 2ª tentativa: GD fallback
	if ( $caps[ $target_format . '_gd' ] ) {
		$img = null;
		if ( $ext === 'jpg' || $ext === 'jpeg' ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- arquivo de origem pode ser corrompido/parcial; preferimos null silencioso ao warning (verificamos `! $img` abaixo).
			$img = @imagecreatefromjpeg( $source_path );
		} elseif ( $ext === 'png' ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- mesmo motivo acima.
			$img = @imagecreatefrompng( $source_path );
			if ( $img ) {
				// PNG com transparência — preserva canal alpha no WebP/AVIF.
				// imagepalettetotruecolor: converte PNG-8 paletted pra TrueColor
				// (necessário pra alpha channel funcionar).
				// imagealphablending(false): modo REPLACE (cores não misturam com alpha) —
				// essencial pra PRESERVAR alpha ao salvar. Se for `true` (default
				// blending mode), o GD faria flatten contra o background atual.
				// imagesavealpha(true): instrui o GD a serializar o canal alpha
				// no output. Sem isso, alpha some no save.
				imagepalettetotruecolor( $img );
				imagealphablending( $img, false );
				imagesavealpha( $img, true );
			}
		}
		if ( ! $img ) {
			return false;
		}

		$func = 'image' . $target_format;
		if ( ! function_exists( $func ) ) {
			imagedestroy( $img );
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- imagewebp/imageavif emitem warning em falha de encode/write; checamos $success no `return` abaixo.
		$success = @$func( $img, $target_path, $quality );
		imagedestroy( $img );
		return $success ? $target_path : false;
	}

	return false;
}

/*
=============================================================================
 *  AUTO-GENERATION ON UPLOAD
 * ============================================================================= */

/**
 * Lista paths absolutos de todos os tamanhos de um attachment (original + sizes).
 */
function rd_img_get_attachment_paths( array $metadata ): array {
	$upload_dir = wp_upload_dir();
	$base_dir   = trailingslashit( $upload_dir['basedir'] );
	$paths      = array();

	if ( empty( $metadata['file'] ) ) {
		return $paths;
	}

	$main_path = $base_dir . $metadata['file'];
	$paths[]   = $main_path;

	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		$base_path = trailingslashit( dirname( $main_path ) );
		foreach ( $metadata['sizes'] as $size_data ) {
			if ( ! empty( $size_data['file'] ) ) {
				$paths[] = $base_path . $size_data['file'];
			}
		}
	}

	return $paths;
}

/**
 * Hook em wp_generate_attachment_metadata — gera next-gen pra todos os sizes
 * automaticamente ao final do upload.
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $attachment_id faz parte da assinatura do filter `wp_generate_attachment_metadata`, mesmo quando só consumimos $metadata.
function rd_img_generate_on_upload( $metadata, $attachment_id ) {
	if ( ! rd_img_is_enabled() ) {
		return $metadata;
	}
	if ( ! is_array( $metadata ) ) {
		return $metadata;
	}

	$mode = rd_img_get_mode();

	$do_webp = ( $mode === 'both' || $mode === 'webp' ) && rd_img_can_generate( 'webp' );
	$do_avif = ( $mode === 'both' || $mode === 'avif' ) && rd_img_can_generate( 'avif' );

	if ( ! $do_webp && ! $do_avif ) {
		return $metadata;
	}

	$files = rd_img_get_attachment_paths( $metadata );

	foreach ( $files as $file ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			continue;
		}

		if ( $do_webp ) {
			rd_img_convert( $file, 'webp' );
		}
		if ( $do_avif ) {
			rd_img_convert( $file, 'avif' );
		}
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'rd_img_generate_on_upload', 10, 2 );

/*
=============================================================================
 *  PICTURE WRAP — <img> → <picture><source>...<img></picture>
 *
 *  2 cenários de cobertura:
 *
 *  1. wp_get_attachment_image() — usado por featured images, post-thumbnails,
 *     galleries WP, custom-logo, etc. Filter rd_img_wrap_in_picture() captura.
 *
 *  2. <img> diretos no conteúdo do post (Gutenberg block core/image, Markdown
 *     processado, HTML cru). Filter rd_img_wrap_content_images() captura via
 *     parsing DOMDocument de the_content.
 *
 *  Helper compartilhado rd_img_get_nextgen_sources_for_url() faz o trabalho
 *  pesado: checa extensão, resolve path, busca next-gen no filesystem,
 *  retorna HTML das <source> tags. Single source of truth.
 * ============================================================================= */

/**
 * Helper compartilhado — pra uma URL de imagem, descobre quais formatos
 * next-gen existem no filesystem e retorna dados estruturados.
 *
 * Retorna array vazio se:
 *   - URL é externa (fora de wp_upload_dir)
 *   - Extensão não é jpg/jpeg/png
 *   - Nenhum arquivo next-gen existe no disco
 *
 * Retorno estruturado (em vez de HTML string) pra que dois consumers
 * possam construir output apropriado pro contexto deles:
 *   - rd_img_wrap_in_picture()       → concat de strings (HTML5 void <source>)
 *   - rd_img_wrap_content_images()   → createElement do DOMDocument
 *
 * String HTML não servia pros dois — appendXML do DOMNode exige XML strict
 * (self-closing `<source />`), incompatível com HTML5 (`<source>` sem `/>`).
 *
 * @param string $url URL absoluta da imagem original.
 * @return array Lista de sources [['url' => '...', 'type' => 'image/avif'], ...]
 *               ordenada AVIF → WebP (browser pega o primeiro compatível).
 */
function rd_img_get_nextgen_sources_for_url( string $url ): array {
	if ( '' === $url ) {
		return array();
	}

	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return array();
	}

	// Resolve absolute path pra checar existência dos arquivos next-gen
	$upload_dir = wp_upload_dir();
	$base_url   = trailingslashit( $upload_dir['baseurl'] );
	$base_dir   = trailingslashit( $upload_dir['basedir'] );

	// URL fora do uploads (CDN externa, etc) — passa transparente
	if ( strpos( $url, $base_url ) !== 0 ) {
		return array();
	}

	$relative = substr( $url, strlen( $base_url ) );
	$abs_path = $base_dir . $relative;

	$mode    = rd_img_get_mode();
	$sources = array();

	// AVIF primeiro — maior compressão pra browsers que suportam
	if ( ( $mode === 'both' || $mode === 'avif' ) && rd_img_can_generate( 'avif' ) ) {
		$avif_path = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $abs_path );
		if ( file_exists( $avif_path ) ) {
			$sources[] = array(
				'url'  => preg_replace( '/\.(jpe?g|png)$/i', '.avif', $url ),
				'type' => 'image/avif',
			);
		}
	}

	// WebP segundo
	if ( ( $mode === 'both' || $mode === 'webp' ) && rd_img_can_generate( 'webp' ) ) {
		$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $abs_path );
		if ( file_exists( $webp_path ) ) {
			$sources[] = array(
				'url'  => preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url ),
				'type' => 'image/webp',
			);
		}
	}

	return $sources;
}

/**
 * Helper genérico — envolve um <img> HTML cru em <picture> se houver versões
 * next-gen no filesystem. Pra contextos que renderizam <img> diretamente em
 * vez de via wp_get_attachment_image() (logos custom no Discord facade, tela
 * de manutenção, WSOD, etc.).
 *
 * Caller é responsável por construir o $img_html — esse helper só decide se
 * envolve em <picture> ou retorna como veio.
 *
 * @param string $url      URL absoluta da imagem original (usada pra resolver
 *                         path e buscar arquivos next-gen no disco).
 * @param string $img_html HTML completo da tag <img> a envolver.
 * @return string Original $img_html OU '<picture>...<source>...$img_html</picture>'.
 */
function rd_img_wrap_url_in_picture( string $url, string $img_html ): string {
	if ( ! rd_img_is_enabled() ) {
		return $img_html;
	}

	$sources = rd_img_get_nextgen_sources_for_url( $url );
	if ( empty( $sources ) ) {
		return $img_html;
	}

	$sources_html = '';
	foreach ( $sources as $source ) {
		$sources_html .= '<source srcset="' . esc_url( $source['url'] ) . '" type="' . esc_attr( $source['type'] ) . '">';
	}

	return '<picture>' . $sources_html . $img_html . '</picture>';
}

/**
 * Filter em wp_get_attachment_image — envolve <img> em <picture> com <source>
 * pra cada formato next-gen disponível.
 *
 * AVIF vem antes do WebP — browsers usam o primeiro <source> compatível.
 * Browser sem suporte cai no <img> original (JPEG/PNG).
 *
 * Cobertura: featured images, post thumbnails, post-card, galleries.
 * Imagens inline do conteúdo são cobertas por rd_img_wrap_content_images().
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $icon e $attr fazem parte da assinatura do filter `wp_get_attachment_image`, mesmo quando não usados.
function rd_img_wrap_in_picture( $html, $attachment_id, $size, $icon, $attr ) {
	if ( ! rd_img_is_enabled() ) {
		return $html;
	}
	if ( empty( $html ) ) {
		return $html;
	}

	$src = wp_get_attachment_image_src( $attachment_id, $size );
	if ( ! $src ) {
		return $html;
	}

	return rd_img_wrap_url_in_picture( $src[0], $html );
}
add_filter( 'wp_get_attachment_image', 'rd_img_wrap_in_picture', 10, 5 );

/**
 * Filter em the_content — envolve <img> inline em <picture> com next-gen sources.
 *
 * Complementa rd_img_wrap_in_picture() (que cobre wp_get_attachment_image)
 * pra cobrir imagens DIRETAS no conteúdo do post:
 *   - Bloco Gutenberg core/image
 *   - <img> HTML cru no editor
 *   - Markdown ![]() processado pra <img> (Parsedown roda em priority 6)
 *
 * Priority 20: depois de Markdown (6) e wpautop (10). <img> tags já estão
 * em forma final quando chegamos aqui.
 *
 * Performance: DOMDocument parsing é ~ms por post típico. Roda 1× por render.
 * Em produção com Redis Object Cache + page cache, custo amortizado pra zero.
 *
 * @param string $content HTML do conteúdo do post.
 * @return string Conteúdo com <img> elegíveis envolvidos em <picture>.
 */
function rd_img_wrap_content_images( $content ) {
	if ( ! rd_img_is_enabled() ) {
		return $content;
	}

	// Fast path: nenhum <img> no conteúdo, evita overhead de DOMDocument
	if ( strpos( (string) $content, '<img' ) === false ) {
		return $content;
	}

	// DOMDocument pra parsing seguro — regex em HTML é frágil demais
	// (atributos com aspas misturadas, multi-linha, encoding edge cases).
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();

	// Trick UTF-8: prepend XML declaration força loadHTML a tratar input como UTF-8.
	// LIBXML_HTML_NOIMPLIED + LIBXML_HTML_NODEFDTD evitam wrapping em <html><body>.
	$loaded = $dom->loadHTML(
		'<?xml encoding="UTF-8">' . $content,
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();

	if ( ! $loaded ) {
		return $content;
	}

	$images       = $dom->getElementsByTagName( 'img' );
	$changes_made = false;

	// iterator_to_array pra snapshot — vamos modificar a árvore durante iteração
	$images_array = iterator_to_array( $images );

	foreach ( $images_array as $img ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- propriedade nativa de DOMNode, não renomeável.
		$parent = $img->parentNode;

		// Skip se já dentro de <picture> — evita double-wrap (alguns blocos
		// ou plugins futuros podem produzir <picture> manualmente).
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- propriedade nativa de DOMNode, não renomeável.
		if ( $parent && 'picture' === $parent->nodeName ) {
			continue;
		}

		$src = $img->getAttribute( 'src' );
		if ( '' === $src ) {
			continue;
		}

		$sources = rd_img_get_nextgen_sources_for_url( $src );
		if ( empty( $sources ) ) {
			continue;
		}

		// Cria <picture> e popula com <source> via createElement direto.
		// (Tentamos appendXML antes mas DOMDocumentFragment exige XML strict —
		// `<source>` HTML5 sem self-closing gerava "Document Fragment is empty"
		// warning. createElement + setAttribute é a API correta pra construir
		// elementos novos na árvore DOM existente.)
		$picture = $dom->createElement( 'picture' );
		foreach ( $sources as $source ) {
			$source_el = $dom->createElement( 'source' );
			$source_el->setAttribute( 'srcset', $source['url'] );
			$source_el->setAttribute( 'type', $source['type'] );
			$picture->appendChild( $source_el );
		}

		// Replace <img> com <picture>, depois move <img> pra dentro do <picture>
		$parent->replaceChild( $picture, $img );
		$picture->appendChild( $img );

		$changes_made = true;
	}

	if ( ! $changes_made ) {
		return $content;
	}

	// Extrai HTML modificado. LIBXML_HTML_NOIMPLIED evitou wrapping em <html>/<body>,
	// mas a XML declaration que prependamos vem no output — strip ela.
	$new_content = $dom->saveHTML();
	$new_content = preg_replace( '/^<\?xml encoding="UTF-8">\s*/', '', $new_content );

	return $new_content;
}
add_filter( 'the_content', 'rd_img_wrap_content_images', 20 );

/*
=============================================================================
 *  FORCE CORRECT SRC — defensive fix pra metadata desatualizada
 * ============================================================================= */

/**
 * Filter defensivo — força o `src` do <img> apontar pro size requested quando
 * o arquivo correspondente EXISTE no filesystem mas a metadata do attachment
 * não tem ele listado em `image_meta['sizes']`.
 *
 * Cenário detectado em 2026-05-21 (auditoria PageSpeed): após adicionar um
 * `add_image_size` novo + rodar regenerate via wp_generate_attachment_metadata,
 * em alguns casos o WP continua servindo o ORIGINAL (635x635) no `<img src>`
 * em vez do size requested (240x240 do rd-qr) — mesmo com o arquivo gerado
 * no filesystem. A `srcset` fica correta (lista o 240x240), mas o `src` cai
 * pro original, e o browser usa o `src` em vez de calcular o melhor candidato
 * do srcset em alguns cenários (especialmente com sizes="auto" do WP 6.7+).
 *
 * Como o filter funciona:
 *   1. Verifica se o size requested é um custom registrado via add_image_size
 *   2. Pega width/height esperados desse size
 *   3. Constrói o path esperado do arquivo (`-WxH.ext`)
 *   4. Se o arquivo existe no filesystem MAS o src atual aponta pro original,
 *      força o src + dimensions pro size correto
 *
 * Só atua quando o file existe — se realmente o size não foi gerado, deixa
 * o WP servir o original (fail-safe).
 */
function rd_img_force_correct_src( $attr, $attachment, $size ) {
	// Só atua em sizes nomeados (string), não em arrays [w, h] ou 'full'
	if ( ! is_string( $size ) || $size === 'full' ) {
		return $attr;
	}
	if ( ! is_array( $attr ) || empty( $attr['src'] ) ) {
		return $attr;
	}

	// Pega width/height registrados pra esse size
	$registered = wp_get_registered_image_subsizes();
	if ( ! isset( $registered[ $size ] ) ) {
		return $attr;
	}

	$expected_w = (int) $registered[ $size ]['width'];
	$expected_h = (int) $registered[ $size ]['height'];

	// Se o src já tem o sufixo correto, está OK (WP serviu o size certo)
	$src_filename    = pathinfo( $attr['src'], PATHINFO_FILENAME );
	$expected_suffix = '-' . $expected_w . 'x' . $expected_h;
	if ( substr( $src_filename, -strlen( $expected_suffix ) ) === $expected_suffix ) {
		return $attr;
	}

	// Constrói a URL esperada do size (mesmo padrão que o WP usa pra crops)
	$size_url = preg_replace(
		'/(\.[a-zA-Z0-9]+)$/',
		$expected_suffix . '$1',
		$attr['src']
	);
	if ( ! $size_url || $size_url === $attr['src'] ) {
		return $attr;
	}

	// Confirma que o arquivo existe no filesystem antes de forçar (fail-safe)
	$upload_dir = wp_upload_dir();
	$size_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $size_url );
	if ( ! file_exists( $size_path ) ) {
		return $attr;
	}

	// Override defensivo — src + dimensions corretos
	$attr['src']    = $size_url;
	$attr['width']  = (string) $expected_w;
	$attr['height'] = (string) $expected_h;

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'rd_img_force_correct_src', 10, 3 );

/*
=============================================================================
 *  CLEANUP ON DELETE
 * ============================================================================= */

/**
 * Apaga WebP/AVIF órfãos quando o attachment é deletado da Media Library.
 */
function rd_img_cleanup_on_delete( $attachment_id ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $metadata ) ) {
		return;
	}

	$files = rd_img_get_attachment_paths( $metadata );

	foreach ( $files as $file ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			continue;
		}

		foreach ( array( 'webp', 'avif' ) as $fmt ) {
			$next_gen = preg_replace( '/\.(jpe?g|png)$/i', '.' . $fmt, $file );
			if ( $next_gen !== $file && file_exists( $next_gen ) ) {
				wp_delete_file( $next_gen );
			}
		}
	}
}
add_action( 'delete_attachment', 'rd_img_cleanup_on_delete' );

/*
=============================================================================
 *  REGENERATE EXISTING ATTACHMENTS (AJAX em chunks)
 * ============================================================================= */

/**
 * Conta total de attachments JPEG/PNG na Media Library (pra progress bar).
 */
function rd_img_count_attachments(): int {
	global $wpdb;
	// prepare() usado mesmo com valores hardcoded — convenção do tema:
	// todas as queries passam por prepare, sem exceção (audit Wave 9 A).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- count one-shot pro botão "Regenerate" no admin (clicado raramente); cachear seria overkill.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = %s
         AND post_mime_type IN (%s, %s)",
			'attachment',
			'image/jpeg',
			'image/png'
		)
	);
}

/**
 * AJAX handler: regenera 1 chunk de attachments.
 *
 * POST: offset (int), nonce (string)
 * JSON response: { processed, total, done }
 */
function rd_img_ajax_regenerate() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rd_img_regenerate' ) ) {
		wp_send_json_error( 'bad_nonce', 403 );
	}

	$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$total  = rd_img_count_attachments();

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png' ),
			'posts_per_page' => RD_IMG_REGEN_CHUNK,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	// Carrega dependências de admin pra wp_generate_attachment_metadata funcionar
	// (image.php tem wp_create_image_subsizes, file.php tem helpers de filesystem)
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	foreach ( $attachments as $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			continue; // attachment órfão (DB tem registro mas arquivo sumiu) — pula
		}

		// wp_generate_attachment_metadata regenera TODOS os sizes registrados via
		// add_image_size (incluindo rd-popular-thumb que acabou de ser adicionado)
		// e dispara o filter wp_generate_attachment_metadata no final — que aciona
		// rd_img_generate_on_upload e gera WebP/AVIF dos sizes recém-regenerados.
		// Resolve em uma volta: sizes faltantes + next-gen formats.
		$new_metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $new_metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $new_metadata );
		}
	}

	$processed_so_far = $offset + count( $attachments );
	$done             = $processed_so_far >= $total || empty( $attachments );

	wp_send_json_success(
		array(
			'processed' => $processed_so_far,
			'total'     => $total,
			'done'      => $done,
		)
	);
}
add_action( 'wp_ajax_rd_img_regenerate', 'rd_img_ajax_regenerate' );

/**
 * Enqueue do JS de regeneração — só na aba Performance do painel rd_options.
 * Carregamento condicional evita poluir outras telas do admin.
 */
function rd_img_admin_enqueue( $hook ) {
	if ( strpos( $hook, 'rd_options' ) === false ) {
		return;
	}

	// Só na aba Imagens & Mídia.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate em admin_enqueue_scripts: decide se enfileira o JS de regeneração, não processa form.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	if ( $tab !== 'media' ) {
		return;
	}

	wp_enqueue_script(
		'rd-img-regen',
		get_template_directory_uri() . '/assets/js/admin-img-regen.js',
		array(),
		rd_asset_version( '/assets/js/admin-img-regen.js' ),
		true
	);

	wp_localize_script(
		'rd-img-regen',
		'rd_img_regen',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => array(
				'no_images' => __( 'No JPEG/PNG attachments to process.', 'reloaded' ),
				/* translators: %d: number of JPEG/PNG attachments to process */
				'confirm'   => __( 'Start processing %d images? This may take several minutes.', 'reloaded' ),
				'starting'  => __( 'Starting...', 'reloaded' ),
				'progress'  => __( 'Processed %p of %t (%pct%)', 'reloaded' ),
				'done'      => __( 'Done! Processed %t images.', 'reloaded' ),
				'error'     => __( 'Error: ', 'reloaded' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rd_img_admin_enqueue' );

/*
=============================================================================
 *  PANEL UI — section callback (renderiza diagnostico + botão regenerate)
 * ============================================================================= */

/**
 * Callback da section "Image Formats" na aba Performance.
 * Mostra: capabilities do servidor + botão de regeneração com progress.
 *
 * Wave 11 Fase G: refatorado pro design system rd-p* com layout 30/70
 * (Server Capabilities à esquerda, Regenerate action à direita).
 */
function rd_img_render_panel_section() {
	$caps    = rd_img_get_capabilities();
	$webp_ok = $caps['webp_imagick'] || $caps['webp_gd'];
	$avif_ok = $caps['avif_imagick'] || $caps['avif_gd'];
	$total   = rd_img_count_attachments();
	?>
	<div class="rd-pdash rd-pgrid rd-pgrid--sidebar-main">

		<?php // === Card 30%: Server Capabilities === ?>
		<?php
		rd_panel_card_open(
			array(
				'title' => __( 'Server Capabilities', 'reloaded' ),
				'desc'  => __( 'Auto-detected from PHP extensions installed on this server.', 'reloaded' ),
			)
		);
		?>
		<ul class="rd-img-caps-list">
			<li>
				<strong>Imagick</strong>
				<?php
				echo $caps['imagick']
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pré-escapado por rd_panel_badge().
					? rd_panel_badge( 'success', __( 'available', 'reloaded' ) )
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge HTML pré-escapado.
					: rd_panel_badge( 'danger', __( 'not installed', 'reloaded' ) );
				?>
				<small><?php esc_html_e( '(preferred)', 'reloaded' ); ?></small>
			</li>
			<li>
				<strong>WebP</strong>
				<?php
				echo $webp_ok
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pré-escapado.
					? rd_panel_badge( 'success', __( 'available', 'reloaded' ) )
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pré-escapado.
					: rd_panel_badge( 'danger', __( 'unavailable', 'reloaded' ) );
				?>
			</li>
			<li>
				<strong>AVIF</strong>
				<?php
				echo $avif_ok
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pré-escapado.
					? rd_panel_badge( 'success', __( 'available', 'reloaded' ) )
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge pré-escapado.
					: rd_panel_badge( 'danger', __( 'unavailable', 'reloaded' ) );
				?>
			</li>
		</ul>

		<?php if ( ! $webp_ok && ! $avif_ok ) : ?>
			<?php rd_panel_status( 'warning', esc_html__( 'Neither WebP nor AVIF is available — module is dormant. Uploads will not be converted and no <picture> wrapping happens. Original JPEG/PNG continue to work normally.', 'reloaded' ) ); ?>
		<?php endif; ?>
		<?php rd_panel_card_close(); ?>

		<?php // === Card 70%: Regenerate Action === ?>
		<?php
		rd_panel_card_open(
			array(
				'title' => __( 'Regenerate sizes and next-gen formats for existing attachments', 'reloaded' ),
				'desc'  => __( 'New uploads are processed automatically. Use this button after: (a) enabling the module on a site with existing images; (b) switching the Format Mode above; or (c) adding/changing a custom image size in the theme (re-crops all sizes via WP\'s wp_generate_attachment_metadata, then generates WebP/AVIF for each). The action is idempotent: re-running overwrites existing files with current settings. May take several minutes on a large Media Library — runs in chunks of 10 via AJAX to avoid timeouts.', 'reloaded' ),
			)
		);
		?>
		<p class="rd-pcard__action-row rd-img-regen-row">
			<span class="rd-img-regen-count">
				<?php
				printf(
					/* translators: 1: number of JPEG/PNG attachments in the Media Library */
					esc_html( _n( '%d JPEG/PNG attachment in the Media Library', '%d JPEG/PNG attachments in the Media Library', (int) $total, 'reloaded' ) ),
					(int) $total
				);
				?>
			</span>
			<button type="button"
					id="rd-img-regen-start"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'rd_img_regenerate' ) ); ?>"
					data-total="<?php echo (int) $total; ?>"
					<?php disabled( ! $webp_ok && ! $avif_ok ); ?>>
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Start regeneration', 'reloaded' ); ?>
			</button>
		</p>

		<div id="rd-img-regen-progress" class="rd-img-regen-progress" hidden>
			<progress id="rd-img-regen-bar" value="0" max="100"></progress>
			<p id="rd-img-regen-status"></p>
		</div>
		<?php rd_panel_card_close(); ?>

	</div>
	<?php
}
