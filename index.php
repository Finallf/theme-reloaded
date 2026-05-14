<?php get_header(); ?>
	<main id="primary" class="site-main">
		<div class="container">
			<div class="content-area">
				<?php if ( have_posts() ) : ?>
					<div class="post-grid">
						<?php while ( have_posts() ) : the_post(); ?>
							<article id="post-<?php the_ID(); ?>" <?php post_class('grid-item'); ?>>

								<div class="post-thumbnail">
									<?php if ( has_post_thumbnail() ) : ?>
										<?php the_post_thumbnail( 'rd-card', array( 'loading' => 'lazy' ) ); ?>
									<?php endif; ?>

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
										<h2 class="entry-title">
											<a href="<?php the_permalink(); ?>" class="main-link"><?php the_title(); ?></a>
										</h2>
									</header>

									<div class="entry-content">
										<?php echo wp_trim_words( get_the_excerpt(), 15 ); ?>
									</div>

									<div class="entry-meta">
										<?php echo rd_get_formatted_views( get_the_ID() ); ?>
									</div>
								</div>
							</article>
						<?php endwhile; ?>
					</div>

					<?php
					the_posts_pagination( array(
                        'prev_text' => __( '&larr; Previous', 'reloaded' ),
                        'next_text' => __( 'Next &rarr;', 'reloaded' ),
					) );
					?>

				<?php else : ?>
					<p><?php esc_html_e( 'No news found.', 'reloaded' ); ?></p>
				<?php endif; ?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</main>
<?php get_footer(); ?>
