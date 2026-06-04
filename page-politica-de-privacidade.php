<?php
/**
 * Template: Page — Privacy Policy
 *
 * Specific template for the page with the "politica-de-privacidade" slug.
 * WP uses this file automatically via the template hierarchy
 * (page-{slug}.php) — nothing needs to be selected in the editor.
 *
 * Differences vs the generic page.php:
 *   - No featured image (irrelevant for legal text)
 *   - No comments (legal text isn't a forum)
 *   - Header includes a "Last updated" date via get_the_modified_date()
 *     (updates automatically when the admin edits the content — good LGPD
 *     practice: the visitor knows when the text was last revised)
 *   - rd-page-privacy class on the <article> for specific styles
 *
 * Typography (font-size 1.05rem, line-height 1.75) already comes from .rd-page-content
 * in _page-static.scss — suitable for dense legal text.
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

				<article id="page-<?php the_ID(); ?>" <?php post_class( 'rd-page-article rd-page-privacy' ); ?>>

					<header class="rd-page-header">
						<h1 class="rd-page-title"><?php the_title(); ?></h1>
						<p class="rd-page-updated">
							<?php
							printf(
								/* translators: %s: last modified date of the privacy policy */
								esc_html__( 'Last updated: %s', 'reloaded' ),
								esc_html( get_the_modified_date() )
							);
							?>
						</p>
					</header>

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

			<?php endwhile; ?>

		</div>
		<?php get_sidebar(); ?>
	</div>
</main>

<?php get_footer(); ?>
