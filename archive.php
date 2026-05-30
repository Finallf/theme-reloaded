<?php
/**
 * Template: Archive (category, tag, date — fallback)
 *
 * Generic archive listing for category, tag, and date archives.
 * WordPress's template hierarchy uses this file whenever there is
 * no more-specific template (category.php / tag.php / date.php) for
 * the queried context.
 *
 * The contextual title and description come from the_archive_title()
 * and the_archive_description(), which automatically prefix "Categoria:",
 * "Tag:" or the relevant date depending on the query.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<main id="primary" class="site-main rd-page rd-archive-page">
	<div class="container">
		<div class="content-area">

			<?php
			if ( function_exists( 'rd_render_archive_header' ) ) {
				rd_render_archive_header(); }
			?>

			<?php if ( have_posts() ) : ?>

				<div class="rd-search-results-containers">
					<div class="rd-wrapper-vertical">
						<?php
						while ( have_posts() ) :
							the_post();
							?>
							<?php rd_render_post_card( 'vertical' ); ?>
						<?php endwhile; ?>
					</div>
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

				<p class="rd-no-posts"><?php esc_html_e( 'No articles found in this archive.', 'reloaded' ); ?></p>

			<?php endif; ?>

		</div>
		<?php get_sidebar(); ?>
	</div>
</main>

<?php get_footer(); ?>
