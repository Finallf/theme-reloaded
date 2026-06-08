<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Table of Contents (TOC)                                             *
 *                                                                             *
 * Auto-generates a TOC at the end of the single's `the_content`. Renders as a *
 * floating top-right FAB (below the header) that expands a glass panel        *
 * to the left+down when clicked.                                              *
 *                                                                             *
 * Algorithm (on the_content filter priority 25 — after Markdown @6,           *
 * wpautop @10, picture wrap @20):                                             *
 *   1. Parse via DOMDocument, find all h2/h3                                  *
 *   2. If < RD_TOC_MIN_HEADINGS, exit without injecting a TOC                 *
 *   3. Add a unique id="slug" to each heading (sluggify + collision counter)  *
 *   4. Build a nested list (h2 = top level, h3 = nested)                      *
 *   5. Append the TOC to the end of the content (position:fixed = floats free)*
 *                                                                             *
 * Gate: feature controlled by `enable_table_of_contents` (default ON).        *
 ******************************************************************************/

const RD_TOC_MIN_HEADINGS = 3;

/**
 * Parse + add unique IDs to the HTML's h2/h3. Returns [modified HTML, entries].
 *
 * @param string $html HTML of the already-processed content (after Markdown/wpautop/etc).
 * @return array{0:string,1:array} [HTML with IDs, list of entries].
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
	// Each entry: level (2 or 3), text (string) and slug (string).
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
 * Static cache of pre-processed data per post_id.
 * Avoids re-parsing the content between rd_render_table_of_contents() and the filter.
 *
 * @param int $post_id Post ID.
 * @return array{entries:array,modified_content:string}
 */
function rd_toc_get_cached_data( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	// Apply ALL the_content filters (markdown, wpautop, picture wrap...)
	// EXCEPT ours (rd_toc_filter_the_content), to get the "rendered" HTML
	// without recursion. Then we parse + add IDs.
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
 * Template helper — renders the TOC HTML into the single's markup.
 * Called from single.php INSIDE .entry-title-row after the post-tag.
 *
 * The rendered HTML is structured as:
 *   <div class="rd-toc-anchor">     ← absolute, scope sticky = entire <article>
 *     <div class="rd-toc">          ← sticky, follows scroll
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

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escaped internally in rd_toc_render_html via esc_attr/esc_html.
	echo rd_toc_render_html( $data['entries'] );
}

/**
 * Filter on the_content — returns the content WITH IDs added to the headings.
 * Reuses the cache populated by rd_toc_get_cached_data() — zero re-parse.
 *
 * @param string $content HTML of the content (comes after Markdown/wpautop/etc).
 * @return string Content with IDs on h2/h3.
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
 * Renders the TOC HTML (FAB + collapsible panel).
 *
 * @param array $entries List of [ 'level' => 2|3, 'text' => '...', 'slug' => '...' ]
 * @return string Escaped HTML ready to concat.
 */
function rd_toc_render_html( $entries ) {
	$title_text = esc_html__( 'Table of contents', 'reloaded' );
	$fab_label  = esc_attr__( 'Open table of contents', 'reloaded' );
	$nav_label  = esc_attr__( 'Article navigation', 'reloaded' );

	// Build the nested list. Simple strategy: h2 opens an <li>, h3 goes
	// inside a <ul> nested in the previous h2's <li>. If an h3 comes without an h2
	// before it, it becomes top-level (rare, but defensive).
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
			// Close the nested h3 if it was open
			if ( $nested_ul_open ) {
				$list_html     .= '</ul>';
				$nested_ul_open = false;
			}
			// Close the previous h2
			if ( $h2_open ) {
				$list_html .= '</li>';
			}
			$list_html .= '<li class="rd-toc__item rd-toc__item--h2">' . $link_html;
			$h2_open    = true;
		} else { // level 3
			if ( ! $nested_ul_open ) {
				if ( ! $h2_open ) {
					// Orphan: h3 without an h2 — open a top-level <li> for it
					$list_html .= '<li class="rd-toc__item rd-toc__item--h2">';
					$h2_open    = true;
				}
				$list_html     .= '<ul class="rd-toc__sublist">';
				$nested_ul_open = true;
			}
			$list_html .= '<li class="rd-toc__item rd-toc__item--h3">' . $link_html . '</li>';
		}
	}
	// Close anything still open
	if ( $nested_ul_open ) {
		$list_html .= '</ul>';
	}
	if ( $h2_open ) {
		$list_html .= '</li>';
	}

	$svg_list = '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>';

	// Structure: anchor wrapper (position absolute, scopes the sticky to the
	// whole <article>) + .rd-toc (sticky inside the anchor).
	//
	// The anchor lets the sticky have scope = the whole article without shifting
	// the content (vs float:right which squeezed the kicker in .entry-title-row).
	//
	// Padding-tracking: the anchor uses the shared CSS custom property
	// `--rd-content-pad-inline` (defined in _variables.scss) for `right`, the same
	// token used by the article's `padding`. Padding changes are followed
	// automatically by the TOC without changing _toc.scss.
	// Sentinel = an invisible 1×1 px element positioned at the top of the anchor (= natural
	// position of the FAB before the sticky kicks in). An IntersectionObserver in toc.js
	// watches this sentinel to detect when the sticky activates — when the sentinel
	// crosses the top of the viewport, it adds .is-stuck to .rd-toc (which enables box-shadow).
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
 * Enqueue the JS — only on post singles.
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
