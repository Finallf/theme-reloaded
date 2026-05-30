<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Post Card — generic card renderer used by search, archive, author and any   *
 * other archive-style template. Search-specific concerns (such as highlighting *
 * the search query) layer on top via rd_search_highlight() and is_search().   *
 *******************************************************************************/

/**
 * Escapa o texto e aplica highlight do termo buscado quando estamos na
 * página de resultados de busca. Em qualquer outro contexto, retorna apenas
 * o texto escapado.
 */
function rd_post_card_text( $text ) {
	$escaped = esc_html( $text );

	if ( is_search() && function_exists( 'rd_search_highlight' ) ) {
		return rd_search_highlight( $escaped );
	}

	return $escaped;
}

/**
 * Renderiza o bloco de "views" formatado (ícone + número) para os cards.
 * Retorna string vazia quando o módulo de views não está disponível.
 */
function rd_get_formatted_views( $post_id ) {
	if ( ! function_exists( 'rd_get_post_views' ) ) {
		return '';
	}
	$views = rd_get_post_views( $post_id );
	// Frontend usa rd_format_views_number (respeita config full/compact do painel).
	// Admin (mod-stats, coluna de posts) usa number_format_i18n direto — sempre exato.
	return '<span class="rd-card-views" aria-label="' . esc_attr__( 'Views', 'reloaded' ) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . rd_format_views_number( $views ) . '</span>';
}

/**
 * Renderiza um bloco de distribuição da busca (wrappers internas).
 * Consumido por search.php (initial load) e pelo AJAX handler
 * (rd_ajax_search_redistribute) — ambos geram o mesmo HTML.
 *
 * Não envolve com .rd-search-results-containers — quem chama é responsável
 * por esse outer (pra AJAX poder fazer swap só do innerHTML).
 *
 * @param array<string, array<int, \WP_Post>> $distribution Output de rd_search_distribute_posts()
 */
function rd_render_distribution( array $distribution ) {
	global $post;

	foreach ( $distribution as $layout => $posts ) {
		if ( empty( $posts ) ) {
			continue;
		}

		echo '<div id="rd-wrap-' . esc_attr( $layout ) . '" class="rd-search-wrapper rd-wrapper-' . esc_attr( $layout ) . '">';

		foreach ( $posts as $p ) {
			$post = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
			rd_render_post_card( $layout );
		}

		echo '</div>';
	}

	wp_reset_postdata();
}

/**
 * Renderiza um card de post no layout escolhido. Funciona dentro do loop do
 * WordPress (depende de get_the_ID(), get_the_title(), etc.).
 *
 * @param string $type 'grid' | 'vertical' | 'compact' | 'google'
 */
function rd_render_post_card( $type ) {
	global $wp_query;
	$post_id  = get_the_ID();
	$title    = rd_post_card_text( get_the_title() );
	$excerpt  = rd_post_card_text( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 20, '...' ) );
	$link     = get_the_permalink();
	$views    = rd_get_formatted_views( $post_id );
	$overline = function_exists( 'rd_get_post_overline_html' ) ? rd_get_post_overline_html( $post_id, 'card' ) : '';

	// Os 2 primeiros posts do loop são candidatos a LCP. Em layouts 2-col
	// (search grid, compact) ficam lado a lado above-the-fold; em layouts
	// 1-col (vertical, google) o 2º fica logo abaixo. O Lighthouse pode
	// escolher qualquer um dos dois como métrica — marcando ambos eager
	// + high priority garantimos cobertura. Custo: 1 imagem extra eager
	// em layouts single-col (overhead mínimo).
	$is_lcp_candidate = isset( $wp_query->current_post ) && $wp_query->current_post < 2;
	$thumb_attrs      = $is_lcp_candidate
		? array(
			'loading'       => 'eager',
			'fetchpriority' => 'high',
		)
		: array( 'loading' => 'lazy' );

	// Fallback de imagem
	$thumb = has_post_thumbnail() ? get_the_post_thumbnail( $post_id, 'medium', $thumb_attrs ) : '<div class="rd-thumb-fallback"></div>';

	// $title, $excerpt vêm de rd_post_card_text() (que aplica esc_html + opcional <mark>
	// de busca via regex que pula tags/entities). $overline vem de rd_get_post_overline_html()
	// (esc_attr + esc_html internamente). $views vem de rd_get_formatted_views() (HTML pré-
	// escapado em mod-views.php). $thumb/$thumb_small vêm de get_the_post_thumbnail() (WP).
	// Todos pré-escapados; PHPCS não rastreia através dos wrappers.
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	switch ( $type ) {
		case 'grid':
			echo '<article class="rd-search-card layout-grid">';
			echo '<a href="' . esc_url( $link ) . '" class="rd-card-link">';
			echo '<div class="rd-card-thumb">' . $thumb . '</div>';
			echo '<div class="rd-card-body">';
			echo $overline; // string vazia se feature off ou post sem chapéu
			echo '<h3 class="rd-card-title">' . $title . '</h3>';
			echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
			echo '<div class="rd-card-meta">' . $views . '</div>';
			echo '</div></a></article>';
			break;

		case 'vertical':
			// Wrap completo em <a> pra o card inteiro ser clicável (mesma estratégia do grid/compact).
			// HTML5 permite <a> envolvendo flow content (block-level); proibido só ter <a> aninhado.
			echo '<article class="rd-search-card layout-vertical">';
			echo '<a href="' . esc_url( $link ) . '" class="rd-card-link">';
			echo '<div class="rd-card-thumb">' . $thumb . '</div>';
			echo '<div class="rd-card-body">';
			echo $overline; // string vazia se feature off ou post sem chapéu
			echo '<h3 class="rd-card-title">' . $title . '</h3>';
			echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
			echo '<div class="rd-card-meta">' . $views . '</div>';
			echo '</div></a></article>';
			break;

		case 'compact':
			$thumb_small = has_post_thumbnail() ? get_the_post_thumbnail( $post_id, 'thumbnail', $thumb_attrs ) : '<div class="rd-thumb-fallback-small"></div>';
			echo '<article class="rd-search-card layout-compact">';
			echo '<a href="' . esc_url( $link ) . '" class="rd-card-link-flex">';
			echo '<div class="rd-card-thumb-small">' . $thumb_small . '</div>';
			echo '<div class="rd-card-data">';
			echo '<h3 class="rd-card-title">' . $title . '</h3>';
			echo '<div class="rd-card-meta-compact">';
			echo '<span class="rd-card-date">' . get_the_date() . '</span>';
			echo $views;
			echo '</div>';
			echo '</div>';
			echo '</a></article>';
			break;

		case 'google':
			echo '<article class="rd-search-card layout-google">';
			echo '<div class="rd-card-url">' . esc_url( $link ) . '</div>';
			echo '<h3 class="rd-card-title"><a href="' . esc_url( $link ) . '">' . $title . '</a></h3>';
			echo '<p class="rd-card-excerpt">' . $excerpt . '</p>';
			echo '</article>';
			break;
	}
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}
