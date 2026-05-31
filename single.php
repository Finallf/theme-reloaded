<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
	<main id="primary" class="site-main">
		<div class="container">
			<div class="content-area">
				<?php
				while ( have_posts() ) :
					the_post();
					?>

					<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post-content' ); ?>>

						<?php
						// Table of contents — renders the anchor + sticky FAB as the
						// FIRST child of <article>. The anchor is position:absolute relative
						// to .single-post-content (which is position:relative), giving the
						// sticky a scope = the full article height (FAB follows the scroll
						// throughout the entire post reading).
						if ( function_exists( 'rd_render_table_of_contents' ) ) {
							rd_render_table_of_contents();
						}
						?>

						<header class="entry-header">
							<div class="entry-title-row">
								<div class="entry-title-block">
									<?php
									if ( function_exists( 'rd_render_post_overline' ) ) {
										rd_render_post_overline( get_the_ID(), 'single' ); }
									?>
									<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
								</div>
								<?php
								if ( function_exists( 'rd_render_single_category_kicker' ) ) {
									rd_render_single_category_kicker(); }
								?>
							</div>

							<div class="entry-meta">
								<span class="posted-on">
									<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
									<?php echo esc_html( get_the_date() ); ?>
								</span>
								<span class="meta-separator">•</span>
								<span class="byline">
									<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
									<?php the_author_posts_link(); ?>
								</span>
								<span class="meta-separator">•</span>
								<?php echo rd_get_formatted_views( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns already-escaped HTML (esc_html + esc_attr in inc/post-card.php) ?>
								<?php
								// Reading time — right-aligned via margin-left: auto on .reading-time
								// (no meta-separator needed before it — sits on the other side of the line).
								if ( function_exists( 'rd_render_reading_time' ) ) {
									rd_render_reading_time( get_the_ID() );
								}
								?>
							</div>
						</header>

						<?php
							// Checks whether the user asked to hide the image on this specific post.
							$hide_thumb = get_post_meta( get_the_ID(), '_rd_hide_thumbnail', true );

							// If there is an image and it was NOT marked to hide, render the thumb.
						if ( has_post_thumbnail() && $hide_thumb !== 'yes' ) :
							?>
								<div class="post-thumbnail">
									<?php
									the_post_thumbnail(
										'rd-full-banner',
										array(
											'loading' => 'eager',
											'fetchpriority' => 'high',
										)
									);
									?>
								</div>
						<?php endif; ?>

						<div class="entry-content">
							<?php
							the_content();

							wp_link_pages(
								array(
									'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'reloaded' ),
									'after'  => '</div>',
								)
							);
							?>
						</div>

						<footer class="entry-footer">
							<div class="post-tags">
								<?php the_tags( '', '', '' ); ?>
							</div>
						</footer>

					</article>

					<?php
					// Author bio box — avatar + name + bio + author's social networks.
					// Auto-hides if bio + socials are all empty (anti-noise).
					if ( function_exists( 'rd_render_author_bio_box' ) ) {
						rd_render_author_bio_box( get_the_ID() );
					}

					// Related posts — block of 3 posts from the same primary category,
					// ordered by tag overlap. Cached for 1h via transient.
					if ( function_exists( 'rd_render_related_posts' ) ) {
						rd_render_related_posts( get_the_ID() );
					}

					// If there are comments, load the comments template.
					if ( comments_open() || get_comments_number() ) :
						comments_template();
					endif;

				endwhile;
				?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</main>
<?php get_footer(); ?>
