<?php
/**
 * Template: Page — Contact
 *
 * Specific template for the page with the "contato" slug.
 * WP uses this file automatically via the template hierarchy
 * (page-{slug}.php) — nothing needs to be selected in the editor.
 *
 * Differences vs the generic page.php:
 *   - No featured image (presentation focused on the contact channels)
 *   - No comments (CTAs need emphasis, not conversation)
 *   - All the content (email, socials, Discord, etc.) goes through the editor — no
 *     auto-injected hardcoded block. Philosophy: maximum control in the editor
 *
 * A native form (no plugin) is left as a future backlog item if wanted.
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

				<article id="page-<?php the_ID(); ?>" <?php post_class( 'rd-page-article rd-page-contact' ); ?>>

					<header class="rd-page-header">
						<h1 class="rd-page-title"><?php the_title(); ?></h1>
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
