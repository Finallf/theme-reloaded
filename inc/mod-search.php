<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Módulo de Busca — funções específicas do contexto de busca.                 *
 * O renderer de card vive em inc/post-card.php (genérico, reusável por        *
 * archive, author, widgets etc.).                                              *
 *                                                                              *
 * Sistema de distribuição (Fase 5.5):                                          *
 * Em vez de duplicar os resultados em cada layout, distribui posts            *
 * sequencialmente entre os layouts ativos (6 por layout, ordem grid →         *
 * vertical → compact → google). Compact funciona como bucket de overflow e    *
 * safety net.                                                                  *
 *******************************************************************************/

const RD_SEARCH_LAYOUT_CAP   = 6;
const RD_SEARCH_LAYOUT_ORDER = array( 'grid', 'vertical', 'compact', 'google' );

/**
 * Aplica highlight (`<mark>`) no termo buscado dentro do texto.
 * Ignora trechos que estejam dentro de tags HTML ou entidades.
 *
 * Consumida pelo helper rd_post_card_text() em inc/post-card.php
 * quando is_search() é verdadeiro.
 */
function rd_search_highlight( string $text ) {
	$query = get_search_query();
	if ( empty( $query ) ) {
		return $text;
	}

	$termos  = explode( ' ', preg_quote( $query, '/' ) );
	$pattern = '/(?![^<]+>)(?![^&;]+;)(' . implode( '|', $termos ) . ')/iu';

	return preg_replace( $pattern, '<mark class="rd-highlight">$1</mark>', $text );
}

/**
 * Retorna a lista de layouts habilitados pelo admin no painel.
 * Fallback: ['compact'] se nenhum estiver ativo — consistente com o
 * comportamento de compact como safety net em todas as outras regras.
 *
 * @return array<int, string>
 */
function rd_search_get_admin_active_layouts(): array {
	$layouts = array(
		'grid'     => rd_get_option_bool( 'search_layout_grid' ),
		'vertical' => rd_get_option_bool( 'search_layout_vertical' ),
		'compact'  => rd_get_option_bool( 'search_layout_compact' ),
		'google'   => rd_get_option_bool( 'search_layout_google' ),
	);
	$active  = array_keys( array_filter( $layouts ) );
	return ! empty( $active ) ? $active : array( 'compact' );
}

/**
 * Distribui posts entre os layouts ativos seguindo regras (em ordem):
 *
 *   1. Posts vazios → distribuição vazia (search.php cuida do "no results")
 *   2. Visitor tem só 1 layout ativo → ele pega TUDO (respeita preferência)
 *   3. Visitor desativou tudo → compact pega tudo (safety net catastrófico)
 *   4. Poucos resultados (< CAP) com múltiplos layouts ativos → compact
 *      (visual limpo — não compensa distribuir tão poucos)
 *   5. Distribuição normal: grid → vertical → compact → google, CAP por layout
 *   6. Overflow → vai pro ÚLTIMO LAYOUT ATIVO (em ordem fixa), respeitando
 *      a preferência do visitor em vez de forçar compact
 *
 * @param array<int, \WP_Post> $posts
 * @param array<int, string>   $effective Layouts efetivos (admin ∩ visitor)
 * @return array<string, array<int, \WP_Post>>
 */
function rd_search_distribute_posts( array $posts, array $effective ): array {
	$cap          = RD_SEARCH_LAYOUT_CAP;
	$distribution = array();

	// Regra 1: vazio
	if ( empty( $posts ) ) {
		return $distribution;
	}

	// Regra 2: só 1 layout ativo → respeita 100% (recebe tudo)
	if ( count( $effective ) === 1 ) {
		$distribution[ $effective[0] ] = $posts;
		return $distribution;
	}

	// Regra 3: nenhum layout ativo → compact safety net
	if ( empty( $effective ) ) {
		$distribution['compact'] = $posts;
		return $distribution;
	}

	// Regra 4: poucos resultados com múltiplos ativos → compact (visual limpo)
	if ( count( $posts ) < $cap ) {
		$distribution['compact'] = $posts;
		return $distribution;
	}

	// Regra 5: distribuição normal
	$remaining   = $posts;
	$last_active = null;

	foreach ( RD_SEARCH_LAYOUT_ORDER as $layout ) {
		if ( empty( $remaining ) ) {
			break;
		}

		if ( in_array( $layout, $effective, true ) ) {
			$distribution[ $layout ] = array_slice( $remaining, 0, $cap );
			$remaining               = array_slice( $remaining, $cap );
			$last_active             = $layout;
		}
	}

	// Regra 6: overflow → último layout ativo (preserva preferência do visitor)
	if ( ! empty( $remaining ) && $last_active !== null ) {
		$distribution[ $last_active ] = array_merge( $distribution[ $last_active ], $remaining );
	}

	return $distribution;
}

/**
 * Ajusta posts_per_page do main query de busca pra ter posts suficientes
 * pra distribuir entre os layouts ativos.
 *
 * No initial load não conhecemos a preferência do visitor (ela vive em
 * localStorage no client), então usamos a config do admin. O JS dispara
 * AJAX depois pra re-renderizar se o visitor tiver toggles diferentes.
 */
function rd_search_modify_query( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_search() ) {
		return;
	}

	$admin_active = rd_search_get_admin_active_layouts();
	$query->set( 'posts_per_page', count( $admin_active ) * RD_SEARCH_LAYOUT_CAP );
}
add_action( 'pre_get_posts', 'rd_search_modify_query' );

/**
 * Expõe ao JS os dados pra fazer AJAX redistribuição.
 * Carrega só na página de busca.
 */
function rd_search_localize_script() {
	if ( ! is_search() ) {
		return;
	}

	wp_localize_script(
		'rd-navigation',
		'rd_search_data',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rd_search_redistribute' ),
			'query'   => get_search_query(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rd_search_localize_script', 20 );

/**
 * Handler AJAX que recebe os layouts ativos do visitor, refaz a query,
 * redistribui e retorna o HTML das wrappers internas do
 * .rd-search-results-containers.
 *
 * Posts_per_page é fixo baseado no admin (não muda quando visitor toggla)
 * pra paginação continuar consistente em todos os cenários.
 */
function rd_ajax_search_redistribute() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'rd_search_redistribute' ) ) {
		wp_send_json_error( array( 'reason' => 'invalid_nonce' ), 400 );
	}

	$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
	$active_csv   = isset( $_POST['active_layouts'] ) ? sanitize_text_field( wp_unslash( $_POST['active_layouts'] ) ) : '';
	$paged        = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

	// Parse + valida layouts requested
	$requested = $active_csv ? explode( ',', $active_csv ) : array();
	$requested = array_intersect( $requested, RD_SEARCH_LAYOUT_ORDER );

	// Effective = admin enabled ∩ visitor requested
	$admin_active = rd_search_get_admin_active_layouts();
	$effective    = array_values( array_intersect( $admin_active, $requested ) );

	// posts_per_page CONSISTENTE (baseado em admin, não em effective)
	// — paginação mantém estrutura mesmo quando visitor toggla
	$posts_per_page = count( $admin_active ) * RD_SEARCH_LAYOUT_CAP;

	$wp_query = new \WP_Query(
		array(
			's'              => $search_query,
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'post_status'    => 'publish',
		)
	);

	$distribution = rd_search_distribute_posts( $wp_query->posts, $effective );

	ob_start();
	if ( ! empty( $wp_query->posts ) ) {
		rd_render_distribution( $distribution );
	}
	$html = ob_get_clean();

	wp_reset_postdata();

	wp_send_json_success(
		array(
			'html'         => $html,
			'has_posts'    => ! empty( $wp_query->posts ),
			'max_pages'    => (int) $wp_query->max_num_pages,
			'current_page' => $paged,
			'total_posts'  => (int) $wp_query->found_posts,
		)
	);
}
add_action( 'wp_ajax_rd_search_redistribute', 'rd_ajax_search_redistribute' );
add_action( 'wp_ajax_nopriv_rd_search_redistribute', 'rd_ajax_search_redistribute' );
