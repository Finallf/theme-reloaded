<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Breadcrumbs — Trilha de navegação contextual + JSON-LD              *
 *                                                                             *
 * Calcula uma única vez o "trail" (array de [name, url]) e alimenta DOIS      *
 * consumidores a partir da mesma fonte:                                       *
 *                                                                             *
 *   1. `rd_render_breadcrumbs()`  — markup HTML visível, chamado de header.php*
 *      logo após o fechamento do <header>. Gated pelo toggle `enable_breadcrumbs`.
 *                                                                             *
 *   2. `rd_add_breadcrumb_jsonld()` — Schema.org BreadcrumbList no <head>,    *
 *      sempre ativo (SEO baseline, independe do toggle visual).               *
 *                                                                             *
 * Quem decide a aparência é o SCSS (`components/_breadcrumbs.scss`). Esse     *
 * arquivo cuida só de lógica + markup semântico.                              *
 *******************************************************************************/

/*******************************************************************************
 * Calcula o trail contextual do breadcrumb                    - (Breadcrumbs) *
 *                                                                             *
 * Retorna array de itens `['name' => string, 'url' => string|null]`. O último *
 * item representa a página atual e vem com `url = null` por convenção.        *
 *                                                                             *
 * Retorna array vazio na front page (não faz sentido breadcrumb na home).     *
 *******************************************************************************/
function rd_get_breadcrumb_trail(): array {
	// Front page: sem breadcrumb (você já está na raiz)
	if ( is_front_page() ) {
		return array();
	}

	$trail   = array();
	$trail[] = array(
		'name' => __( 'Home', 'reloaded' ),
		'url'  => home_url( '/' ),
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $trail;
		}

		// Pra posts (não pages), tenta inserir a categoria primária como
		// pai. Reusa o meta `_rd_primary_category` quando o admin escolheu
		// explicitamente; fallback pra primeira categoria atribuída.
		if ( is_single() && get_post_type( $post ) === 'post' ) {
			$cats = get_the_category( $post->ID );
			$cat  = null;

			if ( ! empty( $cats ) ) {
				$primary_id = (int) get_post_meta( $post->ID, '_rd_primary_category', true );
				if ( $primary_id ) {
					foreach ( $cats as $c ) {
						if ( (int) $c->term_id === $primary_id ) {
							$cat = $c;
							break;
						}
					}
				}
				if ( ! $cat ) {
					$cat = $cats[0];
				}
			}

			if ( $cat ) {
				$trail[] = array(
					'name' => $cat->name,
					'url'  => get_category_link( $cat->term_id ),
				);
			}
		}

		$trail[] = array(
			'name' => get_the_title( $post ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_category() ) {
		$term = get_queried_object();
		// Inclui ancestrais (cat aninhadas) na ordem raiz → folha
		$ancestors = array_reverse( get_ancestors( $term->term_id, 'category' ) );
		foreach ( $ancestors as $aid ) {
			$a = get_term( $aid, 'category' );
			if ( $a && ! is_wp_error( $a ) ) {
				$trail[] = array(
					'name' => $a->name,
					'url'  => get_category_link( $a ),
				);
			}
		}
		$trail[] = array(
			'name' => $term->name,
			'url'  => null,
		);
		return $trail;
	}

	if ( is_tag() ) {
		$term    = get_queried_object();
		$trail[] = array(
			/* translators: %s: tag name */
			'name' => sprintf( __( 'Tag: %s', 'reloaded' ), $term->name ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_tax() ) {
		$term    = get_queried_object();
		$trail[] = array(
			'name' => $term->name,
			'url'  => null,
		);
		return $trail;
	}

	if ( is_author() ) {
		$author  = get_queried_object();
		$trail[] = array(
			/* translators: %s: author display name */
			'name' => sprintf( __( 'Author: %s', 'reloaded' ), $author->display_name ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_day() ) {
		$year     = (int) get_query_var( 'year' );
		$monthnum = (int) get_query_var( 'monthnum' );
		$day      = (int) get_query_var( 'day' );
		$ts       = mktime( 0, 0, 0, $monthnum, $day, $year );
		$trail[]  = array(
			'name' => (string) $year,
			'url'  => get_year_link( $year ),
		);
		$trail[]  = array(
			'name' => date_i18n( 'F', $ts ),
			'url'  => get_month_link( $year, $monthnum ),
		);
		$trail[]  = array(
			'name' => date_i18n( get_option( 'date_format' ), $ts ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_month() ) {
		$year     = (int) get_query_var( 'year' );
		$monthnum = (int) get_query_var( 'monthnum' );
		$ts       = mktime( 0, 0, 0, $monthnum, 1, $year );
		$trail[]  = array(
			'name' => (string) $year,
			'url'  => get_year_link( $year ),
		);
		$trail[]  = array(
			'name' => date_i18n( 'F', $ts ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_year() ) {
		$trail[] = array(
			'name' => (string) get_query_var( 'year' ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_search() ) {
		$trail[] = array(
			/* translators: %s: search query */
			'name' => sprintf( __( 'Search results for: %s', 'reloaded' ), get_search_query() ),
			'url'  => null,
		);
		return $trail;
	}

	if ( is_404() ) {
		$trail[] = array(
			'name' => __( 'Page not found', 'reloaded' ),
			'url'  => null,
		);
		return $trail;
	}

	return $trail;
}

/*******************************************************************************
 * Renderiza o markup visível do breadcrumb                  - (Breadcrumbs) *
 *                                                                             *
 * Chamado de header.php logo após o </header>. Markup semântico (<nav> +     *
 * <ol>) com `aria-label` e `aria-current="page"` no item atual.              *
 *******************************************************************************/
function rd_render_breadcrumbs(): void {
	if ( ! rd_get_option_bool( 'enable_breadcrumbs' ) ) {
		return;
	}

	$trail = rd_get_breadcrumb_trail();
	if ( empty( $trail ) ) {
		return;
	}

	$count = count( $trail );

	echo '<nav class="rd-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'reloaded' ) . '">';
	echo '<div class="container">';
	echo '<ol class="rd-breadcrumbs-list">';

	foreach ( $trail as $i => $crumb ) {
		$is_last = ( $i === $count - 1 );
		echo '<li class="rd-breadcrumbs-item">';
		if ( ! $is_last && ! empty( $crumb['url'] ) ) {
			echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
		} else {
			echo '<span aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
		}
		echo '</li>';
	}

	echo '</ol>';
	echo '</div>';
	echo '</nav>';
}

/*******************************************************************************
 * BreadcrumbList Schema.org JSON-LD                         - (Breadcrumbs) *
 *                                                                             *
 * Emite o BreadcrumbList no <wp_head>, independente do toggle visual. Não    *
 * gera em search/404 (Google não quer breadcrumb pra páginas não-indexáveis) *
 * nem quando o trail tem menos de 2 itens (só Home não vira breadcrumb).    *
 *                                                                             *
 * O último item (página atual) omite a chave `item` por convenção — sinaliza *
 * pro Google que aquela é a posição atual sem precisar de URL duplicada.     *
 *******************************************************************************/
function rd_add_breadcrumb_jsonld(): void {
	if ( is_search() || is_404() ) {
		return;
	}

	$trail = rd_get_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return;
	}

	$items = array();
	foreach ( $trail as $i => $crumb ) {
		$entry = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $crumb['name'],
		);
		if ( ! empty( $crumb['url'] ) ) {
			$entry['item'] = $crumb['url'];
		}
		$items[] = $entry;
	}

	rd_seo_print_jsonld(
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		)
	);
}
add_action( 'wp_head', 'rd_add_breadcrumb_jsonld', 6 );
