<?php
/**
 * The template for displaying comments.
 */

defined( 'ABSPATH' ) || exit;

// If the post is password protected and the visitor has not entered the password, abort.
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
				// translators: %s: number of comments
				esc_html( _n( '%s Comment', '%s Comments', $comment_count, 'reloaded' ) ),
				esc_html( number_format_i18n( $comment_count ) )
			);
			?>
		</h3>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 50, // Profile photo size.
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>

	<?php endif; // End of have_comments() check. ?>

	<?php
	// If comments are closed, display a friendly notice.
	if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
		?>
		<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'reloaded' ); ?></p>
	<?php endif; ?>

	<?php
	// Render the "Leave a Comment" form.
	comment_form();
	?>

</div>
