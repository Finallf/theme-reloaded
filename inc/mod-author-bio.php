<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Author Bio Box                                                       *
 *                                                                              *
 * Renders a block with avatar + name + bio + the author's social networks at   *
 * the end of each single, between the article footer and the related posts.    *
 * Reinforces E-E-A-T (Schema.org Person already exists in author.php) + eases  *
 * author discovery on a multi-author portal.                                   *
 *                                                                              *
 * Markup reuses the .rd-author-* classes from _page-author.scss (visuals       *
 * identical to the top block of the /author/{slug}/ page) — the only addition  *
 * is the "View all posts by this author" link at the bottom of the box.        *
 *                                                                              *
 * Graceful degradation: if the author has an EMPTY bio and ZERO social         *
 * networks configured, the box doesn't render (it would show only "About the   *
 * author" + name, which is already in the single entry-meta — visual noise).   *
 *                                                                              *
 * Gate: feature controlled by `enable_author_bio` (default ON).                *
 *******************************************************************************/

/**
 * Renders the author bio block at the end of the single. Exits early in several
 * scenarios to avoid an empty/redundant block.
 *
 * @param int $post_id ID of the current post.
 */
function rd_render_author_bio_box( $post_id ) {
	if ( ! rd_get_option_bool( 'enable_author_bio', true ) ) {
		return;
	}

	$author_id = (int) get_post_field( 'post_author', $post_id );
	if ( ! $author_id ) {
		return;
	}

	$bio      = trim( (string) get_the_author_meta( 'description', $author_id ) );
	$has_socs = false;
	if ( function_exists( 'rd_render_social_icons' ) ) {
		// Detect whether the author has ANY network filled — using the same pattern as
		// the helper (hardcoded network list). Avoids calling render just to hide.
		$redes = array( 'discord', 'telegram', 'whatsapp', 'youtube', 'instagram', 'steam', 'twitter', 'facebook' );
		foreach ( $redes as $r ) {
			if ( trim( (string) get_the_author_meta( 'social_' . $r, $author_id ) ) !== '' ) {
				$has_socs = true;
				break;
			}
		}
	}

	// Graceful degradation: empty bio + zero socials = redundant box (author
	// already appears in the entry-meta). Doesn't render.
	if ( '' === $bio && ! $has_socs ) {
		return;
	}

	$display_name = get_the_author_meta( 'display_name', $author_id );
	if ( '' === trim( (string) $display_name ) ) {
		$display_name = get_the_author_meta( 'user_login', $author_id ); // fallback
	}
	$archive_url = get_author_posts_url( $author_id );

	echo '<aside class="rd-author-header rd-author-header--single" aria-labelledby="rd-author-bio-title">';

	// Avatar
	echo '<div class="rd-author-avatar">';
	echo get_avatar( $author_id, 120, '', $display_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns sanitized HTML.
	echo '</div>';

	echo '<div class="rd-author-info">';

	// Header with name + socials
	echo '<div class="rd-author-info-header">';
	printf(
		'<h3 id="rd-author-bio-title" class="rd-author-name"><a href="%1$s">%2$s</a></h3>',
		esc_url( $archive_url ),
		esc_html( $display_name )
	);
	if ( $has_socs ) {
		echo '<div class="rd-author-socials">';
		rd_render_social_icons( $author_id );
		echo '</div>';
	}
	echo '</div>'; // .rd-author-info-header

	// Bio (only if it has content)
	if ( '' !== $bio ) {
		echo '<p class="rd-author-bio">' . esc_html( $bio ) . '</p>';
	}

	// Link archive
	printf(
		'<a class="rd-author-archive-link" href="%1$s">%2$s <span aria-hidden="true">→</span></a>',
		esc_url( $archive_url ),
		sprintf(
			/* translators: %s = author display name. Shown as link text "View all posts by Author Name →" at the bottom of the author bio box. */
			esc_html__( 'View all posts by %s', 'reloaded' ),
			esc_html( $display_name )
		)
	);

	echo '</div>'; // .rd-author-info

	echo '</aside>';
}
