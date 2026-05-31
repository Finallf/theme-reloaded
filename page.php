<?php
/**
 * Template: Page (static pages — About, Contact, Privacy Policy, etc.)
 *
 * Unlike single.php (blog post), this one is optimized for structural,
 * time-agnostic content. No visible categories, date, or author.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<main id="primary" class="site-main rd-page">
	<div class="container">
		<div class="content-area">

			<?php
			while ( have_posts() ) :
				the_post();
				?>

				<article id="page-<?php the_ID(); ?>" <?php post_class( 'rd-page-article' ); ?>>

					<header class="rd-page-header">
						<h1 class="rd-page-title"><?php the_title(); ?></h1>
					</header>

					<?php
					// Respects the "Hide Featured Image" meta box option.
					$hide_thumbnail = get_post_meta( get_the_ID(), '_rd_hide_thumbnail', true );

					if ( has_post_thumbnail() && $hide_thumbnail !== 'yes' ) :
						?>
						<div class="rd-page-thumbnail">
							<?php
							the_post_thumbnail(
								'large',
								array(
									'loading'       => 'eager',
									'fetchpriority' => 'high',
								)
							);
							?>
						</div>
					<?php endif; ?>

					<div class="rd-page-content">
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

				</article>

				<?php
				// Comments (only if the admin enabled them for this page).
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;
				?>

			<?php endwhile; ?>

		</div>
		<?php get_sidebar(); ?>
	</div>
</main>

<?php get_footer(); ?>
