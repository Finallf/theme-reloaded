<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Featured Carousel                                                   *
 *                                                                             *
 * Full-width hero carousel rendered between the header and the main content   *
 * on the blog home (page 1 only). Peek layout: each slide is ~84% wide and    *
 * snaps to center, so the neighbors' edges stay visible inviting the swipe.   *
 *                                                                             *
 * Content sources (panel):                                                    *
 *   - sticky  (default): WP sticky posts (★ "Stick to the top"), newest       *
 *     first, falling back to the latest posts when nothing is stickied        *
 *   - category: latest posts from a chosen category                           *
 *   - latest:   latest posts, unfiltered                                      *
 *   Only posts WITH a featured image qualify (the carousel is visual).        *
 *                                                                             *
 * Engine: native CSS scroll-snap does the sliding/swiping (zero-JS            *
 * fallback = a plain swipeable row). assets/js/carousel.js (enqueued only     *
 * when the carousel renders) adds autoplay, arrows, dots and pause logic.     *
 *                                                                             *
 * Performance:                                                                *
 *   - Slide 1 is the home's LCP candidate → eager + fetchpriority=high;       *
 *     slides 2+ lazy                                                          *
 *   - Reuses the existing `rd-full-banner` crop (1200x675) — every post with  *
 *     a featured image already has it, so NO thumbnail regeneration needed    *
 *     (CSS crops to the wide 21:9 box via object-fit: cover)                  *
 *   - De-dup: carousel post IDs are excluded from the home main query so the  *
 *     same story never appears twice on the page                              *
 ******************************************************************************/

/**
 * Master toggle.
 */
function rd_carousel_is_enabled(): bool {
	return rd_get_option_bool( 'enable_carousel' );
}

/**
 * Returns the carousel posts (cached per request — the list is consulted by
 * the renderer, the script enqueue and the home-query exclusion).
 *
 * @return WP_Post[]
 */
function rd_carousel_get_posts(): array {
	static $posts = null;
	if ( null !== $posts ) {
		return $posts;
	}

	$count  = max( 3, min( 8, (int) rd_get_option( 'carousel_count', 5 ) ) );
	$source = (string) rd_get_option( 'carousel_source', 'sticky' );

	$args = array(
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		// Visual block: only posts that HAVE a featured image qualify.
		'meta_key'            => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- presence check, indexed meta, small LIMIT.
	);

	if ( 'category' === $source ) {
		$cat = (int) rd_get_option( 'carousel_category', 0 );
		if ( $cat > 0 ) {
			$args['cat'] = $cat;
		}
	} elseif ( 'sticky' === $source ) {
		$sticky = array_filter( array_map( 'absint', (array) get_option( 'sticky_posts', array() ) ) );
		if ( ! empty( $sticky ) ) {
			$args['post__in'] = $sticky; // newest first via the default date ordering
		}
		// No sticky posts → falls through to the latest posts (no extra arg).
	}
	// 'latest' → base args as-is.

	$posts = get_posts( $args );
	return $posts;
}

/**
 * IDs shown in the carousel — used by the home-query exclusion.
 *
 * @return int[]
 */
function rd_carousel_post_ids(): array {
	return wp_list_pluck( rd_carousel_get_posts(), 'ID' );
}

/**
 * Whether the carousel renders on the current request:
 * toggle ON + blog home page 1 + at least one qualifying post.
 */
function rd_carousel_should_render(): bool {
	if ( ! rd_carousel_is_enabled() ) {
		return false;
	}
	if ( ! is_home() || is_paged() ) {
		return false;
	}
	return ! empty( rd_carousel_get_posts() );
}

/**
 * Excludes the carousel posts from the home main query so the same story
 * never appears in the carousel AND in the hero/sections below. Runs on every
 * home page (not just page 1) to keep the pagination set consistent.
 *
 * When the source is "sticky" we also disable WP's sticky-prepend on the main
 * query — the carousel IS the sticky showcase; re-prepending them below would
 * defeat the exclusion.
 *
 * @param WP_Query $query Main query (by reference).
 */
function rd_carousel_exclude_from_home( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
		return;
	}
	if ( ! rd_carousel_is_enabled() ) {
		return;
	}
	$ids = rd_carousel_post_ids();
	if ( empty( $ids ) ) {
		return;
	}
	$query->set( 'post__not_in', $ids );
	if ( 'sticky' === (string) rd_get_option( 'carousel_source', 'sticky' ) ) {
		$query->set( 'ignore_sticky_posts', true );
	}
}
add_action( 'pre_get_posts', 'rd_carousel_exclude_from_home' );

/**
 * Enqueues the carousel script only when the carousel actually renders.
 */
function rd_carousel_enqueue() {
	if ( ! rd_carousel_should_render() ) {
		return;
	}
	wp_enqueue_script(
		'rd-carousel',
		get_template_directory_uri() . '/assets/js/carousel.js',
		array(),
		rd_asset_version( '/assets/js/carousel.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'rd_carousel_enqueue' );

/**
 * Renders the carousel. Called from index.php between get_header() and <main>.
 */
function rd_render_carousel() {
	if ( ! rd_carousel_should_render() ) {
		return;
	}

	$posts    = rd_carousel_get_posts();
	$total    = count( $posts );
	$interval = max( 0, (int) rd_get_option( 'carousel_interval', 6 ) );

	$svg_prev = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>';
	$svg_next = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>';
	?>
	<section class="rd-carousel"
			aria-roledescription="carousel"
			aria-label="<?php esc_attr_e( 'Featured posts', 'reloaded' ); ?>"
			data-rd-carousel
			data-interval="<?php echo esc_attr( $interval ); ?>">

		<div class="rd-carousel__track" data-rd-track>
			<?php foreach ( $posts as $i => $rd_post ) : ?>
				<?php
				$thumb_id   = get_post_thumbnail_id( $rd_post );
				$categories = get_the_category( $rd_post->ID );
				$category   = ! empty( $categories ) ? $categories[0] : null;

				// Slide 1 is the LCP candidate — load it eagerly with high priority.
				// The rest only load as the user (or the autoplay) approaches them.
				$img_attr = array( 'class' => 'rd-carousel__img' );
				if ( 0 === $i ) {
					$img_attr['loading']       = 'eager';
					$img_attr['fetchpriority'] = 'high';
				} else {
					$img_attr['loading'] = 'lazy';
				}
				?>
				<article class="rd-carousel__slide"
						aria-roledescription="slide"
						aria-label="<?php echo esc_attr( sprintf( /* translators: 1: slide number, 2: total slides */ __( '%1$d of %2$d', 'reloaded' ), $i + 1, $total ) ); ?>">
					<a class="rd-carousel__link" href="<?php echo esc_url( get_permalink( $rd_post ) ); ?>">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() returns escaped HTML; AVIF/WebP <picture> wrap added by mod-image-formats.
						echo wp_get_attachment_image( $thumb_id, 'rd-full-banner', false, $img_attr );
						?>
						<div class="rd-carousel__overlay">
							<?php if ( $category ) : ?>
								<span class="post-tag tag-<?php echo esc_attr( $category->slug ); ?>"><?php echo esc_html( $category->name ); ?></span>
							<?php endif; ?>
							<?php
							// Journalism overline (chapéu) — its own line between the
							// chip and the headline. Empty-safe: returns '' when the
							// post has no kicker or the global feature is off.
							if ( function_exists( 'rd_get_post_overline_html' ) ) {
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes internally (esc_attr/esc_html).
								echo rd_get_post_overline_html( $rd_post->ID, 'carousel' );
							}
							?>
							<h2 class="rd-carousel__title"><?php echo esc_html( get_the_title( $rd_post ) ); ?></h2>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>

		<button type="button" class="rd-carousel__arrow rd-carousel__arrow--prev" aria-label="<?php esc_attr_e( 'Previous slide', 'reloaded' ); ?>">
			<?php echo $svg_prev; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG above ?>
		</button>
		<button type="button" class="rd-carousel__arrow rd-carousel__arrow--next" aria-label="<?php esc_attr_e( 'Next slide', 'reloaded' ); ?>">
			<?php echo $svg_next; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG above ?>
		</button>

		<div class="rd-carousel__dots">
			<?php for ( $d = 0; $d < $total; $d++ ) : ?>
				<button type="button"
						class="rd-carousel__dot"
						data-rd-goto="<?php echo esc_attr( $d ); ?>"
						<?php echo 0 === $d ? 'aria-current="true"' : ''; ?>
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Go to slide %d', 'reloaded' ), $d + 1 ) ); ?>"></button>
			<?php endfor; ?>
		</div>
	</section>
	<?php
}
