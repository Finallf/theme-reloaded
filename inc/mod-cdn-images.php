<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: CDN Images — URL rewrite extension point                            *
 *                                                                             *
 * Fornece um filter `reloaded_image_url` aplicado em TODAS as URLs de imagem  *
 * geradas pelo WordPress (uploads). Permite reescrever pra um host CDN        *
 * (Cloudflare Images, Bunny, BunnyCDN, KeyCDN, etc.) sem editar core do tema. *
 *                                                                             *
 * Estado: DORMENTE por padrão. Sem callbacks no filter, URLs passam intactas. *
 *                                                                             *
 * Para ativar (ex.: child theme, mu-plugin, ou snippet em wp-config-loaded):  *
 *                                                                             *
 *   add_filter( 'reloaded_image_url', function( $url, $context ) {            *
 *       return str_replace(                                                   *
 *           'https://reloaded.com.br/wp-content/uploads',                     *
 *           'https://reloaded.b-cdn.net',                                     *
 *           $url                                                              *
 *       );                                                                    *
 *   }, 10, 2 );                                                               *
 *                                                                             *
 * Cobertura — pontos onde o filter é aplicado:                                *
 *   1. wp_get_attachment_url           → URL canônica do attachment           *
 *   2. wp_get_attachment_image_src     → src do <img> com size resolvido      *
 *   3. wp_calculate_image_srcset       → cada URL do srcset responsivo        *
 *                                                                             *
 * Não cobre (intencional):                                                    *
 *   - URLs externas (não-uploads) — passam transparente                       *
 *   - URLs hardcoded em templates — se houver, refatorar pra usar helpers WP  *
 *                                                                             *
 * Sinérgico com mod-image-formats: o filter recebe URLs JÁ resolvidas (avif/  *
 * webp se aplicável), então o CDN serve qualquer formato que estiver no disco.*
 *******************************************************************************/

/**
 * Helper interno — aplica o filter `reloaded_image_url` a uma URL.
 *
 * Só atua em URLs dentro do wp_upload_dir (ignora avatares Gravatar, imagens
 * externas, etc.). Falha silenciosa se URL não for de uploads.
 *
 * @param string $url     URL a possivelmente reescrever.
 * @param string $context Hint pro callback: 'attachment_url', 'image_src',
 *                        'srcset'. Permite estratégias diferentes por contexto.
 * @return string URL reescrita (ou original se nenhum callback houver hooked).
 */
function rd_cdn_maybe_rewrite( string $url, string $context = '' ): string {
	if ( '' === $url ) {
		return $url;
	}

	// Só reescreve URLs do próprio uploads. Externas (Gravatar, oEmbed, etc.)
	// passam intactas — caso contrário o CDN serviria 404.
	$upload_dir = wp_upload_dir();
	$base_url   = isset( $upload_dir['baseurl'] ) ? (string) $upload_dir['baseurl'] : '';
	if ( '' === $base_url || strpos( $url, $base_url ) !== 0 ) {
		return $url;
	}

	/**
	 * Reescreve URL de imagem antes de servir.
	 *
	 * @param string $url     URL absoluta dentro de wp-content/uploads.
	 * @param string $context 'attachment_url' | 'image_src' | 'srcset'.
	 */
	return (string) apply_filters( 'reloaded_image_url', $url, $context );
}

/**
 * Filter em wp_get_attachment_url — URL canônica do attachment (sem size).
 */
function rd_cdn_filter_attachment_url( $url ) {
	return rd_cdn_maybe_rewrite( (string) $url, 'attachment_url' );
}
add_filter( 'wp_get_attachment_url', 'rd_cdn_filter_attachment_url', 99 );

/**
 * Filter em wp_get_attachment_image_src — array [url, width, height, is_intermediate].
 * URL é o índice 0; reescrevemos só ela.
 */
function rd_cdn_filter_image_src( $image ) {
	if ( ! is_array( $image ) || empty( $image[0] ) ) {
		return $image;
	}
	$image[0] = rd_cdn_maybe_rewrite( (string) $image[0], 'image_src' );
	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'rd_cdn_filter_image_src', 99 );

/**
 * Filter em wp_calculate_image_srcset — reescreve cada URL do srcset responsivo.
 *
 * Estrutura recebida: [ width => [ 'url' => '...', 'descriptor' => 'w', 'value' => N ] ]
 */
function rd_cdn_filter_srcset( $sources ) {
	if ( ! is_array( $sources ) ) {
		return $sources;
	}
	foreach ( $sources as $width => $source ) {
		if ( ! empty( $source['url'] ) ) {
			$sources[ $width ]['url'] = rd_cdn_maybe_rewrite( (string) $source['url'], 'srcset' );
		}
	}
	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'rd_cdn_filter_srcset', 99 );
