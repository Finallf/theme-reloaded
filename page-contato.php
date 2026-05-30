<?php
/**
 * Template: Page — Contato
 *
 * Template específico para a página com slug "contato".
 * WP usa esse arquivo automaticamente via hierarquia de templates
 * (page-{slug}.php) — não precisa selecionar nada no editor.
 *
 * Diferenças vs page.php genérico:
 *   - Sem imagem destacada (apresentação focada nos canais de contato)
 *   - Sem comentários (CTAs precisam de destaque, não conversa)
 *   - Todo o conteúdo (email, redes, Discord, etc) vai pelo editor — sem
 *     bloco hardcoded auto-injetado. Filosofia: máximo controle no editor
 *
 * Formulário nativo (sem plugin) fica como item futuro do backlog se quiser.
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
