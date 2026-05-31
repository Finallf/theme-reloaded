<?php
/**
 * Template: 403 - Forbidden / Restricted Access
 *
 * Passive template — WordPress does not automatically route to 403 in the
 * normal flow (uses redirects to login, returns 401, etc). This file is
 * available to be served by future code that needs to emit a 403:
 *
 *   status_header( 403 );
 *   include get_template_directory() . '/403.php';
 *   exit;
 *
 * Expected scenarios: manual IP blocking, attempted access to a restricted
 * area by a user without permission, security plugins that want a visual
 * screen instead of the default grey `wp_die`.
 *
 * Reuses the same visual card as the 404 (.rd-404-card) — a single SCSS
 * component covers both templates. The content is specific to the 403 context.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<main id="primary" class="site-main rd-404-page">
	<div class="container-narrow">

		<section class="rd-404-card">

			<div class="rd-404-header">
				<span class="rd-404-code"><?php esc_html_e( '403', 'reloaded' ); ?></span>
				<h1 class="rd-404-title"><?php esc_html_e( 'SEALED GATE', 'reloaded' ); ?></h1>
			</div>

			<div class="rd-404-body">
				<p class="rd-404-message">
					<?php esc_html_e( 'You approach a locked door. The guardian does not recognize your authority — this chamber requires a key you have not yet earned. Return to the main portal or present your credentials to try again.', 'reloaded' ); ?>
				</p>

				<div class="rd-404-actions">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rd-404-btn rd-404-btn-primary">
						<span class="rd-404-btn-icon" aria-hidden="true">▶</span>
						<?php esc_html_e( 'Back to the portal', 'reloaded' ); ?>
					</a>

					<a href="<?php echo esc_url( wp_login_url( home_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) ) ); ?>" class="rd-404-btn rd-404-btn-secondary">
						<span class="rd-404-btn-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
								<polyline points="10 17 15 12 10 7"></polyline>
								<line x1="15" y1="12" x2="3" y2="12"></line>
							</svg>
						</span>
						<?php esc_html_e( 'Present credentials', 'reloaded' ); ?>
					</a>
				</div>
			</div>

		</section>

		<?php
		// Popular posts (most viewed all-time) — same logic as the 404 template.
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
					<span class="rd-404-recommended-icon" aria-hidden="true">🗝️</span>
					<?php esc_html_e( 'Unlocked Areas', 'reloaded' ); ?>
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

<?php get_footer(); ?>
