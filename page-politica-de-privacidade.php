<?php
/**
 * Template: Page — Política de Privacidade
 *
 * Template específico para a página com slug "politica-de-privacidade".
 * WP usa esse arquivo automaticamente via hierarquia de templates
 * (page-{slug}.php) — não precisa selecionar nada no editor.
 *
 * Diferenças vs page.php genérico:
 *   - Sem imagem destacada (irrelevante em texto legal)
 *   - Sem comentários (texto legal não vira fórum)
 *   - Header inclui data de "Última atualização" via get_the_modified_date()
 *     (atualiza automaticamente quando o admin editar o conteúdo — boa prática
 *     LGPD: visitante sabe quando o texto foi revisado pela última vez)
 *   - Classe rd-page-privacy no <article> pra estilos específicos
 *
 * Tipografia (font-size 1.05rem, line-height 1.75) já vem do .rd-page-content
 * em _page-static.scss — adequada pra texto legal denso.
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
