<?php
/**
 * Template: 404 - Page Not Found
 *
 * Displays the themed "Level not found" message with popular posts
 * shown as "Recommended Levels" suggestions.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<main id="primary" class="site-main rd-404-page">
	<div class="container-narrow">

		<section class="rd-404-card">

			<div class="rd-404-header">
				<span class="rd-404-code"><?php esc_html_e( '404', 'reloaded' ); ?></span>
				<h1 class="rd-404-title"><?php esc_html_e( 'LEVEL NOT FOUND', 'reloaded' ); ?></h1>
			</div>

			<div class="rd-404-body">
				<p class="rd-404-message">
					<?php esc_html_e( 'This area does not exist on the portal map. The path you tried to access may have been removed, moved to another coordinate, or never existed in this universe.', 'reloaded' ); ?>
				</p>

				<div class="rd-404-actions">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rd-404-btn rd-404-btn-primary">
						<span class="rd-404-btn-icon" aria-hidden="true">▶</span>
						<?php esc_html_e( 'Back to start', 'reloaded' ); ?>
					</a>

					<button type="button" class="rd-404-btn rd-404-btn-secondary" id="rd-404-search-trigger">
						<span class="rd-404-btn-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="11" cy="11" r="8"></circle>
								<line x1="22" y1="22" x2="16.65" y2="16.65"></line>
							</svg>
						</span>
						<?php esc_html_e( 'Search content', 'reloaded' ); ?>
					</button>
				</div>
			</div>

		</section>

		<?php
		// Popular posts (most viewed all-time).
		$popular = function_exists( 'rd_get_popular_posts' ) ? rd_get_popular_posts( 3 ) : null;

		// Fallback: if the views module is unavailable or has no posts yet,
		// show the 3 most recent ones.
		if ( ! $popular || ! $popular->have_posts() ) {
			$popular = new WP_Query(
				array(
					'post_type'      => 'post',
					'posts_per_page' => 3,
					'post_status'    => 'publish',
					'no_found_rows'  => true,
				)
			);
		}
		?>

		<?php if ( $popular->have_posts() ) : ?>

			<section class="rd-404-recommended">

				<h2 class="rd-404-recommended-title">
					<span class="rd-404-recommended-icon" aria-hidden="true">🎮</span>
					<?php esc_html_e( 'Recommended Levels', 'reloaded' ); ?>
				</h2>

				<div class="rd-404-recommended-grid">
					<?php
					while ( $popular->have_posts() ) :
						$popular->the_post();
						?>

						<article class="rd-404-recommended-item">
							<a href="<?php the_permalink(); ?>" class="rd-404-recommended-link">

								<?php if ( has_post_thumbnail() ) : ?>
									<div class="rd-404-recommended-thumb">
										<?php
										the_post_thumbnail(
											'medium',
											array(
												'loading' => 'lazy',
												'alt'     => esc_attr( get_the_title() ),
											)
										);
										?>
									</div>
								<?php else : ?>
									<div class="rd-404-recommended-thumb rd-404-recommended-thumb-empty" aria-hidden="true">
										<span>📄</span>
									</div>
								<?php endif; ?>

								<div class="rd-404-recommended-content">
									<h3 class="rd-404-recommended-item-title"><?php the_title(); ?></h3>
								</div>

							</a>
						</article>

					<?php endwhile; ?>
				</div>

			</section>

			<?php wp_reset_postdata(); ?>

		<?php endif; ?>

	</div>
</main>

<?php
// The behavior of focusing the search-field when the "Search content" button
// is clicked lives in assets/js/navigation.js (SoC: zero JS in PHP templates).
?>

<?php get_footer(); ?>
