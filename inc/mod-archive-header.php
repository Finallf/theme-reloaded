<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Archive Header — Contextual header for the archive.php template     *
 *                                                                             *
 * Renders a rich header for archive pages (category, tag, taxonomy,           *
 * date) with 3 features over the original header:                             *
 *                                                                             *
 *   1. Contextual SVG icon (folder for cat, tag for tag, calendar for date)   *
 *   2. Counter of found posts (`$wp_query->found_posts`)                      *
 *   3. Colored left border — on a category archive, uses the color            *
 *      configured in the `rd_category_color` term_meta (mod-category-colors)  *
 *                                                                             *
 * The title and description come from WP's native helpers (the_archive_title, *
 * the_archive_description), preserving i18n and the title's inner `<span>`.   *
 *                                                                             *
 * Called from archive.php (and derived templates) via                         *
 * `rd_render_archive_header()`. No automatic hook — the template calls it     *
 * because the markup must go in the right place in the DOM.                   *
 *******************************************************************************/

/*******************************************************************************
 * SVG icons in feather/lucide style (stroke 2, no fill).                      *
 * Inline to avoid an extra HTTP request. `aria-hidden` on the outer wrapper.  *
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
 * Identifies the current archive context in a single string                   *
 *******************************************************************************/
function rd_archive_header_get_context(): string {
	if ( is_category() ) {
		return 'category';
	}
	if ( is_tag() ) {
		return 'tag';
	}
	if ( is_tax() ) {
		return 'tag';   // custom taxonomies follow the tag icon
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
 * Resolves the accent color of the header's left border.                      *
 * Only categories have their own color (`rd_category_color` term meta). Other *
 * contexts return an empty string → CSS uses the theme's brand-blue fallback. *
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
 * Renders the full archive header.                                            *
 * Called directly from the template (archive.php).                            *
 *******************************************************************************/
function rd_render_archive_header(): void {
	global $wp_query;

	$context      = rd_archive_header_get_context();
	$icon_svg     = rd_archive_header_get_icon( $context );
	$accent_color = rd_archive_header_get_accent_color();
	$count        = (int) $wp_query->found_posts;

	// CSS custom property for the accent — fallback defined in SCSS
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
