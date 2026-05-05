<?php
/**
 * Template: Page (Páginas estáticas — Sobre, Contato, Política, etc.)
 *
 * Diferente de single.php (post de blog), este é otimizado para conteúdo
 * estrutural/atemporal. Sem categorias, data ou autor visíveis.
 *
 * @package ReloadeD
 */

get_header(); ?>

<main id="primary" class="site-main rd-page">
    <div class="container">
        <div class="content-area">

            <?php while ( have_posts() ) : the_post(); ?>

                <article id="page-<?php the_ID(); ?>" <?php post_class('rd-page-article'); ?>>

                    <header class="rd-page-header">
                        <h1 class="rd-page-title"><?php the_title(); ?></h1>
                    </header>

                    <?php
					// Respeita a opção do meta box "Ocultar Imagem Destacada"
					$hide_thumbnail = get_post_meta( get_the_ID(), '_rd_hide_thumbnail', true );

					if ( has_post_thumbnail() && $hide_thumbnail !== 'yes' ) : ?>
                        <div class="rd-page-thumbnail">
                            <?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="rd-page-content">
                        <?php
                        the_content();

                        wp_link_pages( array(
                            'before' => '<div class="page-links">' . esc_html__( 'Páginas:', 'reloaded' ),
                            'after'  => '</div>',
                        ) );
                        ?>
                    </div>

                </article>

                <?php
                // Comentários (apenas se admin habilitou pra esta página)
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