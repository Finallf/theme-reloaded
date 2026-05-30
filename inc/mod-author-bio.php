<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Author Bio Box                                                       *
 *                                                                              *
 * Renderiza um bloco com avatar + nome + bio + redes sociais do autor no final *
 * de cada single, entre o footer do artigo e o bloco de related posts.        *
 * Reforça E-E-A-T (Schema.org Person já existe em author.php) + facilita      *
 * descoberta de autores em portal multi-author.                                *
 *                                                                              *
 * Markup reusa as classes .rd-author-* de _page-author.scss (visual idêntico  *
 * ao bloco do topo da página /author/{slug}/) — única adição é o link         *
 * "Ver todos os posts deste autor" no rodapé do box.                          *
 *                                                                              *
 * Graceful degradation: se o autor tem bio VAZIA e ZERO redes sociais         *
 * configuradas, o box não renderiza (mostraria só "Sobre o autor" + nome,    *
 * o que já está no entry-meta do single — seria ruído visual).                *
 *                                                                              *
 * Gate: feature controlada por `enable_author_bio` (default ON).              *
 *******************************************************************************/

/**
 * Renderiza o bloco de bio do autor no final do single. Sai cedo em vários
 * cenários pra evitar bloco vazio/redundante.
 *
 * @param int $post_id ID do post atual.
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
		// Detect se autor tem ALGUMA rede preenchida — usando mesmo padrão do
		// helper (lista de redes hardcoded). Evita chamar render só pra esconder.
		$redes = array( 'discord', 'telegram', 'whatsapp', 'youtube', 'instagram', 'steam', 'twitter', 'facebook' );
		foreach ( $redes as $r ) {
			if ( trim( (string) get_the_author_meta( 'social_' . $r, $author_id ) ) !== '' ) {
				$has_socs = true;
				break;
			}
		}
	}

	// Graceful degradation: bio vazia + zero socials = box redundante (autor
	// já aparece no entry-meta). Não renderiza.
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

	// Header com nome + socials
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

	// Bio (só se tem conteúdo)
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
