<?php
/**
 * Template: Search Results
 * @package ReloadeD
 */

get_header();

// Layouts ativos vêm do helper único (também usado pelo pre_get_posts hook
// e pelo AJAX handler). Fallback é 'compact' quando admin desligou tudo.
$active_layouts = rd_search_get_admin_active_layouts();
$show_toggles   = count( $active_layouts ) > 1;
?>

<main id="primary" class="site-main rd-page rd-search-page">
    <div class="container">
        <div class="content-area">

            <header class="rd-search-header">
                <div class="rd-search-header-inner">
                    <h1 class="rd-page-title">
                        <?php
                        // translators: %s: search query
                        printf( esc_html__( 'Results for: %s', 'reloaded' ), '<span class="rd-search-term">' . get_search_query() . '</span>' );
                        ?>
                    </h1>

                    <?php if ( $show_toggles ) : ?>
                        <div class="rd-search-toggles" id="rd-search-toggles">
                            <span class="rd-toggles-label"><?php esc_html_e( 'Display:', 'reloaded' ); ?></span>
                            <?php foreach ( $active_layouts as $layout ) : ?>
                                <button type="button" class="rd-chip active" data-target="rd-wrap-<?php echo esc_attr($layout); ?>">
                                    <?php echo esc_html( ucfirst($layout) ); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </header>

            <?php if ( have_posts() ) : ?>

                <div class="rd-search-results-containers">
                    <?php
                    // Distribui os posts entre os layouts ativos.
                    // Server-side usa os layouts do admin como base — se o
                    // visitor tiver toggles diferentes em localStorage, o JS
                    // dispara AJAX (rd_ajax_search_redistribute) depois pra
                    // re-renderizar.
                    global $wp_query;
                    $distribution = rd_search_distribute_posts( $wp_query->posts, $active_layouts );
                    rd_render_distribution( $distribution );
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
