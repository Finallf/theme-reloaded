<?php
/**
 * Template: Search Results
 * @package ReloadeD
 */

get_header();

// Puxa as opções do painel (Fallback para '1' caso seja a primeira instalação)
$layouts = [
    'grid'     => rd_get_option('search_layout_grid', 1),
    'vertical' => rd_get_option('search_layout_vertical', 0),
    'compact'  => rd_get_option('search_layout_compact', 0),
    'google'   => rd_get_option('search_layout_google', 0)
];

// Filtra apenas os layouts habilitados pelo admin
$active_layouts = array_keys( array_filter( $layouts ) );
// Se nenhum estiver habilitado, força o grid como segurança
if ( empty( $active_layouts ) ) $active_layouts = ['grid'];

$show_toggles = count( $active_layouts ) > 1;
?>

<main id="primary" class="site-main rd-page rd-search-page">
    <div class="container">
        <div class="content-area">

            <header class="rd-page-header">
                <h1 class="rd-page-title">
                    <?php
                    // translators: %s: search query
                    printf( esc_html__( 'Results for: %s', 'reloaded' ), '<span class="rd-search-term">' . get_search_query() . '</span>' );
                    ?>
                </h1>
            </header>

            <?php if ( have_posts() ) : ?>

                <?php if ( $show_toggles ) : ?>
                    <div class="rd-search-toggles" id="rd-search-toggles">
                        <span class="rd-toggles-label"><?php esc_html_e( 'View as:', 'reloaded' ); ?></span>
                        <?php foreach ( $active_layouts as $layout ) : ?>
                            <button type="button" class="rd-chip active" data-target="rd-wrap-<?php echo esc_attr($layout); ?>">
                                <?php echo esc_html( ucfirst($layout) ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="rd-search-results-containers">
                    <?php
                    // Renderiza os wrappers de cada layout ativo
                    foreach ( $active_layouts as $layout ) {
                        echo '<div id="rd-wrap-' . esc_attr($layout) . '" class="rd-search-wrapper rd-wrapper-' . esc_attr($layout) . '">';

                        while ( have_posts() ) {
                            the_post();
                            rd_render_post_card( $layout );
                        }
                        rewind_posts(); // Reseta o loop para o próximo layout

                        echo '</div>';
                    }
                    ?>
                </div>

                <?php
                the_posts_pagination( array(
                    'prev_text' => __( '&larr; Previous', 'reloaded' ),
                    'next_text' => __( 'Next &rarr;', 'reloaded' ),
                ) );
                ?>

            <?php else : ?>
                <div class="rd-no-results">
                    <p><?php esc_html_e( 'No levels found for this term. Try using different keywords.', 'reloaded' ); ?></p>
                    <?php get_search_form(); ?>
                </div>
            <?php endif; ?>

        </div>
        <?php get_sidebar(); ?>
    </div>
</main>

<?php get_footer(); ?>
