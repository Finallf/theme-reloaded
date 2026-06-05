<?php
defined( 'ABSPATH' ) || exit;
get_header();

// Active home sections (admin config). Empty array → classic fallback layout.
$rd_home_sections = rd_home_get_active_sections();
?>
	<main id="primary" class="site-main">
		<div class="container">
			<div class="content-area">
				<?php if ( have_posts() ) : ?>

					<?php if ( empty( $rd_home_sections ) ) : ?>

						<?php // Classic home: a single grid of hero cards + native pagination. ?>
						<div class="post-grid">
							<?php
							while ( have_posts() ) :
								the_post();
								rd_render_home_hero_card();
							endwhile;
							?>
						</div>

						<?php
						the_posts_pagination(
							array(
								'prev_text' => __( '&larr; Previous', 'reloaded' ),
								'next_text' => __( 'Next &rarr;', 'reloaded' ),
							)
						);
						?>

					<?php else : ?>

						<?php
						// Configurable showcase: 2 hero cards followed by the active
						// sections (Grid/Vertical/Compact). Posts are consumed
						// sequentially from the main loop, so the hero cards land on
						// current_post 0-1 (LCP eager) and section cards on 2+ (lazy).
						// No pagination — the home is a fixed vitrine.
						?>
						<div class="post-grid">
							<?php
							$rd_hero = 0;
							while ( have_posts() && $rd_hero < RD_HOME_HERO_COUNT ) :
								the_post();
								rd_render_home_hero_card();
								++$rd_hero;
							endwhile;
							?>
						</div>

						<div class="rd-home-sections">
							<?php foreach ( $rd_home_sections as $rd_layout => $rd_qty ) : ?>
								<?php
								if ( ! have_posts() ) {
									break; // No posts left — don't emit an empty section wrapper.
								}
								?>
								<div class="rd-wrapper-<?php echo esc_attr( $rd_layout ); ?>">
									<?php
									$rd_n = 0;
									while ( have_posts() && $rd_n < $rd_qty ) :
										the_post();
										rd_render_post_card( $rd_layout );
										++$rd_n;
									endwhile;
									?>
								</div>
							<?php endforeach; ?>
						</div>

					<?php endif; ?>

				<?php else : ?>
					<p><?php esc_html_e( 'No news found.', 'reloaded' ); ?></p>
				<?php endif; ?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</main>
<?php get_footer(); ?>
