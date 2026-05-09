<?php
/**
 * Template: Author Archive (Posts by author)
 *
 * Lists every post authored by the queried user. The user's profile
 * (avatar, display name, bio, post count, registration date) appears
 * in the header, followed by their posts rendered with the shared
 * vertical post card.
 *
 * Note: this is the ARCHIVE of posts by an author, not a profile page.
 * A richer "About the author" template (single-author-page) is planned
 * separately in the future-agenda backlog.
 *
 * @package ReloadeD
 */

get_header();

$author_id  = (int) get_queried_object_id();
$author     = get_userdata( $author_id );
$post_count = (int) count_user_posts( $author_id, 'post', true );
$bio        = $author ? get_the_author_meta( 'description', $author_id ) : '';
$registered = $author ? $author->user_registered : '';
?>

<main id="primary" class="site-main rd-page rd-author-page">
    <div class="container">
        <div class="content-area">

            <?php if ( $author ) : ?>
                <header class="rd-author-header">

                    <div class="rd-author-avatar">
                        <?php
                        echo get_avatar(
                            $author_id,
                            120,
                            '',
                            $author->display_name,
                            array( 'class' => 'rd-author-avatar-img' )
                        );
                        ?>
                    </div>

                    <div class="rd-author-info">
                        <h1 class="rd-author-name"><?php echo esc_html( $author->display_name ); ?></h1>

                        <?php if ( ! empty( $bio ) ) : ?>
                            <p class="rd-author-bio"><?php echo esc_html( $bio ); ?></p>
                        <?php endif; ?>

                        <div class="rd-author-meta">
                            <span class="rd-author-meta-item">
                                <?php
                                printf(
                                    esc_html( _n( '%s published article', '%s published articles', $post_count, 'reloaded' ) ),
                                    esc_html( number_format_i18n( $post_count ) )
                                );
                                ?>
                            </span>

                            <?php if ( ! empty( $registered ) ) : ?>
                                <span class="rd-author-meta-item">
                                    <?php
                                    printf(
                                        esc_html__( 'Member since %s', 'reloaded' ),
                                        esc_html( date_i18n( 'F \d\e Y', strtotime( $registered ) ) )
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                </header>
            <?php endif; ?>

            <?php if ( have_posts() ) : ?>

                <div class="rd-search-results-containers">
                    <div class="rd-wrapper-vertical">
                        <?php while ( have_posts() ) : the_post(); ?>
                            <?php rd_render_post_card( 'vertical' ); ?>
                        <?php endwhile; ?>
                    </div>
                </div>

                <?php
                the_posts_pagination( array(
                    'prev_text' => __( '&larr; Previous', 'reloaded' ),
                    'next_text' => __( 'Next &rarr;', 'reloaded' ),
                ) );
                ?>

            <?php else : ?>

                <p class="rd-no-posts"><?php esc_html_e( 'This author has not published any articles yet.', 'reloaded' ); ?></p>

            <?php endif; ?>

        </div>
        <?php get_sidebar(); ?>
    </div>
</main>

<?php get_footer(); ?>
