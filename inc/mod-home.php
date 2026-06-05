<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Home Module — configurable layout for the blog posts index (index.php).      *
 *                                                                              *
 * The home is a fixed showcase: 2 large hero cards (always shown) followed by  *
 * up to three optional sections that reuse the search card layouts             *
 * (Grid / Vertical / Compact). Each section's size is chosen by the admin in   *
 * the panel; "Disabled" (0) drops the section entirely.                        *
 *                                                                              *
 * When every section is Disabled the template falls back to the classic home   *
 * (a single grid of hero cards + native pagination) — see index.php. This is   *
 * fully independent from the Search page (different options, hooks, CSS scope).*
 *******************************************************************************/

const RD_HOME_HERO_COUNT = 2;

// Section order is fixed (hero → grid → vertical → compact). Google is not used
// on the home. Each entry lists the quantities the admin may pick — multiples of
// the layout's column count so every row is complete (grid 3-col, vertical 1-col,
// compact 2-col). 0 (Disabled) is always allowed and handled separately.
const RD_HOME_SECTION_CHOICES = array(
	'grid'     => array( 3, 6, 9 ),
	'vertical' => array( 1, 2, 3 ),
	'compact'  => array( 2, 4, 6 ),
);

/**
 * Returns the active home sections as [ layout => quantity ], in the fixed
 * order, including only sections whose configured quantity is a valid non-zero
 * choice. Empty array means "no sections" → the template renders the classic
 * fallback.
 *
 * @return array<string, int>
 */
function rd_home_get_active_sections(): array {
	$active = array();

	foreach ( RD_HOME_SECTION_CHOICES as $layout => $choices ) {
		$qty = (int) rd_get_option( 'home_layout_' . $layout );
		if ( in_array( $qty, $choices, true ) ) {
			$active[ $layout ] = $qty;
		}
	}

	return $active;
}

/**
 * Whether the configurable home layout is active (at least one section on).
 */
function rd_home_is_active(): bool {
	return ! empty( rd_home_get_active_sections() );
}

/**
 * Total posts the home needs when the configurable layout is active:
 * the 2 hero cards plus the sum of every active section's quantity.
 */
function rd_home_total_posts(): int {
	return RD_HOME_HERO_COUNT + array_sum( rd_home_get_active_sections() );
}

/**
 * Adjusts the home main query's posts_per_page so it fetches exactly the
 * number of posts the layout consumes (hero + active sections). Posts are
 * distributed sequentially in the template, so nothing is skipped or repeated.
 *
 * When the layout is inactive we leave the query untouched — the classic home
 * keeps WP's "Reading → posts per page" value and native pagination.
 *
 * Guard is strict (is_home + main query + front-end) and mutually exclusive
 * with the search hook (is_search), so the two never interfere.
 */
function rd_home_modify_query( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_home() ) {
		return;
	}
	if ( ! rd_home_is_active() ) {
		return;
	}

	$query->set( 'posts_per_page', rd_home_total_posts() );
}
add_action( 'pre_get_posts', 'rd_home_modify_query' );

/**
 * Renders a single large "hero" card (the historical home card markup).
 * Must run inside the WordPress loop. Shared by both the classic fallback and
 * the configurable layout so the hero markup lives in a single place.
 *
 * The first 2 posts in the loop are LCP candidates: in the 2-column hero grid
 * they sit side-by-side above the fold, so they get eager + high priority while
 * everything below stays lazy.
 */
function rd_render_home_hero_card() {
	global $wp_query;

	$is_lcp_candidate = isset( $wp_query->current_post ) && $wp_query->current_post < 2;
	$thumb_attrs      = $is_lcp_candidate
		? array(
			'loading'       => 'eager',
			'fetchpriority' => 'high',
		)
		: array( 'loading' => 'lazy' );
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'grid-item' ); ?>>

		<div class="post-thumbnail">
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'rd-card', $thumb_attrs );
			}
			?>

			<div class="post-categories">
				<?php
				$categories = get_the_category();
				if ( ! empty( $categories ) ) {
					foreach ( $categories as $category ) {
						echo '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" class="post-tag tag-' . esc_attr( $category->slug ) . '">' . esc_html( $category->name ) . '</a>';
					}
				}
				?>
			</div>
		</div>

		<div class="post-content-area">
			<header class="entry-header">
				<?php
				if ( function_exists( 'rd_render_post_overline' ) ) {
					rd_render_post_overline( get_the_ID(), 'card' );
				}
				?>
				<h2 class="entry-title">
					<a href="<?php the_permalink(); ?>" class="main-link"><?php the_title(); ?></a>
				</h2>
			</header>

			<div class="entry-content">
				<?php echo esc_html( wp_trim_words( get_the_excerpt(), 15 ) ); ?>
			</div>

			<div class="entry-meta">
				<?php echo rd_get_formatted_views( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns already-escaped HTML (esc_html + esc_attr in inc/post-card.php) ?>
			</div>
		</div>
	</article>
	<?php
}
