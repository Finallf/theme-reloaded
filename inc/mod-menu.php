<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Menu - Comportamento de destaque do menu nav                       *
 *                                                                            *
 * Resolve o problema do "múltiplos itens destacados": quando um post tem     *
 * mais de uma categoria, o WordPress adiciona `current-menu-*` em todos os   *
 * itens do menu correspondentes. Aqui filtramos pra deixar só a categoria    *
 * principal (escolhida no editor via meta box `_rd_primary_category`) e      *
 * seus ancestrais no menu como "current".                                    *
 *******************************************************************************/

/**
 * Retorna o ID da categoria principal do post.
 *
 * Cascata:
 *   1. Meta `_rd_primary_category` (escolha do autor) — se a categoria ainda
 *      pertencer ao post
 *   2. Primeira categoria via get_the_category() (ordem ID padrão do WP)
 *
 * @param int $post_id
 * @return int 0 se o post não tem categorias
 */
function rd_get_primary_category_id( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return 0;
	}

	$cats = get_the_category( $post_id );
	if ( empty( $cats ) ) {
		return 0;
	}

	$chosen = (int) get_post_meta( $post_id, '_rd_primary_category', true );

	if ( $chosen ) {
		// Confirma que a categoria escolhida ainda está no post
		foreach ( $cats as $cat ) {
			if ( (int) $cat->term_id === $chosen ) {
				return $chosen;
			}
		}
		// Escolha obsoleta — fallback pra primeira
	}

	return (int) $cats[0]->term_id;
}

/**
 * Filtra os objetos do menu antes de renderizar pra deixar só a categoria
 * principal (e seus ancestrais no menu) com classes `current-*`. Demais itens
 * apontando pra categorias não-principais perdem o destaque.
 *
 * Usa `wp_nav_menu_objects` (em vez de `nav_menu_css_class`) porque precisa
 * da árvore completa pra calcular ancestrais.
 *
 * @param array  $items Lista de objetos de menu (WP_Post-like)
 * @param object $args  Argumentos do wp_nav_menu
 * @return array
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args faz parte da assinatura do filter `wp_nav_menu_objects`, mesmo quando não consultamos os args do menu.
function rd_filter_menu_primary_category( $items, $args ) {
	// Gate principal — opção desativada no painel desliga toda a feature
	if ( ! rd_get_option_bool( 'enable_primary_category' ) ) {
		return $items;
	}

	// Só faz sentido em single de post
	if ( ! is_singular( 'post' ) ) {
		return $items;
	}

	$primary_cat_id = rd_get_primary_category_id( get_the_ID() );
	if ( ! $primary_cat_id ) {
		return $items;
	}

	// 1) Acha o item de menu que aponta pra categoria principal
	$primary_item_id = 0;
	foreach ( $items as $item ) {
		if ( $item->object === 'category' && (int) $item->object_id === $primary_cat_id ) {
			$primary_item_id = (int) $item->ID;
			break;
		}
	}

	if ( ! $primary_item_id ) {
		// Categoria principal não está no menu — não filtra (deixa WP destacar
		// o que conseguir, melhor algo que nada)
		return $items;
	}

	// 2) Monta o conjunto "keep": item da categoria principal + ancestrais
	// no menu (subindo via menu_item_parent)
	$keep_ids    = array( $primary_item_id );
	$items_by_id = array();
	foreach ( $items as $item ) {
		$items_by_id[ (int) $item->ID ] = $item;
	}

	$current_id = $primary_item_id;
	while ( true ) {
		$current = $items_by_id[ $current_id ] ?? null;
		if ( ! $current ) {
			break;
		}
		$parent_id = (int) $current->menu_item_parent;
		if ( ! $parent_id || isset( $keep_ids_lookup[ $parent_id ] ) ) {
			break;
		}
		$keep_ids[] = $parent_id;
		$current_id = $parent_id;
	}
	$keep_ids_lookup = array_flip( $keep_ids );

	// 3) Pra cada item NÃO no keep set, remove classes `current-*` (e variantes
	// com underscore que algumas versões do WP / temas usam)
	foreach ( $items as &$item ) {
		if ( isset( $keep_ids_lookup[ (int) $item->ID ] ) ) {
			continue;
		}
		if ( ! is_array( $item->classes ) ) {
			continue;
		}
		$item->classes = array_filter(
			$item->classes,
			function ( $css_class ) {
				return strpos( $css_class, 'current-' ) !== 0
				&& strpos( $css_class, 'current_' ) !== 0;
			}
		);
		// array_filter preserva chaves; reseta pro WP renderizar limpo
		$item->classes = array_values( $item->classes );
	}
	unset( $item ); // boa prática após foreach por referência

	return $items;
}
add_filter( 'wp_nav_menu_objects', 'rd_filter_menu_primary_category', 20, 2 );
