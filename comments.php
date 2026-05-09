<?php
/**
 * O template para exibir comentários.
 */

// Se o post for protegido por senha e o visitante não a tiver inserido, aborta.
if ( post_password_required() ) {
    return;
}
?>

<div id="comments" class="comments-area">

    <?php if ( have_comments() ) : ?>
        <h3 class="comments-title">
            <?php
            $comment_count = get_comments_number();
            printf(
                esc_html( _n( '%s Comment', '%s Comments', $comment_count, 'reloaded' ) ),
                number_format_i18n( $comment_count )
            );
            ?>
        </h3>

        <ol class="comment-list">
            <?php
            wp_list_comments( array(
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 50, // Tamanho da foto de perfil
            ) );
            ?>
        </ol>

        <?php the_comments_navigation(); ?>

    <?php endif; // Fim da checagem de have_comments() ?>

    <?php
    // Se os comentários estiverem fechados, exibe um aviso amigável
    if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
        ?>
        <p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'reloaded' ); ?></p>
    <?php endif; ?>

    <?php
    // Gera o Formulário de "Deixe um Comentário"
    comment_form();
    ?>

</div>
