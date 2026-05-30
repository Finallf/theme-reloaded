<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Archive Header — Cabeçalho contextual do template archive.php       *
 *                                                                             *
 * Renderiza um header rico pra páginas de archive (categoria, tag, taxonomia, *
 * data) com 3 features sobre o cabeçalho original:                            *
 *                                                                             *
 *   1. Ícone contextual SVG (folder pra cat, tag pra tag, calendário pra data)*
 *   2. Contador de posts encontrados (`$wp_query->found_posts`)               *
 *   3. Borda esquerda colorida — quando archive de categoria, usa a cor       *
 *      configurada em term_meta `rd_category_color` (mod-category-colors)     *
 *                                                                             *
 * O título e a descrição vêm dos helpers nativos do WP (the_archive_title,    *
 * the_archive_description), preservando i18n e o `<span>` interno do título.  *
 *                                                                             *
 * Chamado de archive.php (e templates derivados) via                          *
 * `rd_render_archive_header()`. Sem hook automático — quem chama é o template *
 * porque o markup precisa ficar no lugar certo do DOM.                        *
 *******************************************************************************/

/*******************************************************************************
 * SVG icons em estilo feather/lucide (stroke 2, no fill).                     *
 * Inline pra evitar request HTTP extra. `aria-hidden` no wrapper externo.     *
 *******************************************************************************/
function rd_archive_header_get_icon( string $context ): string {
	$icons = array(
		'category' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
		'tag'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
		'date'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
		'author'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		'generic'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
	);
	return $icons[ $context ] ?? $icons['generic'];
}

/*******************************************************************************
 * Identifica o contexto do archive atual em uma string única                  *
 *******************************************************************************/
function rd_archive_header_get_context(): string {
	if ( is_category() ) {
		return 'category';
	}
	if ( is_tag() ) {
		return 'tag';
	}
	if ( is_tax() ) {
		return 'tag';   // taxonomias custom seguem o ícone de tag
	}
	if ( is_author() ) {
		return 'author';
	}
	if ( is_year() || is_month() || is_day() ) {
		return 'date';
	}
	return 'generic';
}

/*******************************************************************************
 * Resolve a cor de acento da borda esquerda do header.                        *
 * Só categorias têm cor própria (term meta `rd_category_color`). Outros       *
 * contextos retornam string vazia → CSS usa o fallback brand-blue do tema.    *
 *******************************************************************************/
function rd_archive_header_get_accent_color(): string {
	if ( ! is_category() ) {
		return '';
	}

	$term = get_queried_object();
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$color = get_term_meta( $term->term_id, 'rd_category_color', true );
	return is_string( $color ) ? trim( $color ) : '';
}

/*******************************************************************************
 * Renderiza o cabeçalho completo do archive.                                  *
 * Chamado direto do template (archive.php).                                   *
 *******************************************************************************/
function rd_render_archive_header(): void {
	global $wp_query;

	$context      = rd_archive_header_get_context();
	$icon_svg     = rd_archive_header_get_icon( $context );
	$accent_color = rd_archive_header_get_accent_color();
	$count        = (int) $wp_query->found_posts;

	// CSS custom property pro acento — fallback definido no SCSS
	$style_attr = '';
	if ( ! empty( $accent_color ) ) {
		$style_attr = ' style="--rd-archive-accent: ' . esc_attr( $accent_color ) . '"';
	}

	?>
	<header class="rd-archive-header rd-archive-header-<?php echo esc_attr( $context ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built attribute fragment with esc_attr applied above ?>>
		<div class="rd-archive-icon" aria-hidden="true"><?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup from hardcoded array in rd_archive_header_get_icon ?></div>
		<div class="rd-archive-text">
			<?php the_archive_title( '<h1 class="rd-archive-title">', '</h1>' ); ?>
			<?php the_archive_description( '<div class="rd-archive-description">', '</div>' ); ?>
			<div class="rd-archive-count">
				<?php
				printf(
					/* translators: %s: number of posts in the archive */
					esc_html( _n( '%s post', '%s posts', $count, 'reloaded' ) ),
					'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
				);
				?>
			</div>
		</div>
	</header>
	<?php
}
