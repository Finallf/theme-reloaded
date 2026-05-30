<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Reading Time Estimate                                                *
 *                                                                              *
 * Calcula e exibe um indicador "X min read" no entry-meta do single, baseado *
 * em contagem de palavras / 200 WPM (padrão internacional usado por Medium,  *
 * Substack, dev.to, etc).                                                     *
 *                                                                              *
 * Cache: valor é calculado e salvo em post meta `_rd_reading_time` no        *
 * save_post (1x por edição), lido instantâneo nas renderizações. Posts pré- *
 * instalação do módulo recalculam on-the-fly e cacheiam na primeira leitura. *
 *                                                                              *
 * Gate: feature controlada por `enable_reading_time` (default ON).            *
 *                                                                              *
 * Sem omissão pra posts curtos — sempre mostra (mínimo 1 min).                *
 *******************************************************************************/

const RD_READING_WPM = 200;

/**
 * Calcula tempo de leitura de um post (sempre on-the-fly, sem cache).
 * Usa preg_match_all com flag /u (UTF-8) — str_word_count falha com acentos.
 *
 * @param int $post_id ID do post.
 * @return int Minutos (mínimo 1).
 */
function rd_calculate_reading_time( $post_id ) {
	$content    = (string) get_post_field( 'post_content', $post_id );
	$text       = wp_strip_all_tags( strip_shortcodes( $content ) );
	$word_count = preg_match_all( '/\S+/u', $text );
	if ( false === $word_count || $word_count < 1 ) {
		return 1;
	}
	return max( 1, (int) ceil( $word_count / RD_READING_WPM ) );
}

/**
 * Retorna tempo de leitura (cacheado em post meta).
 *
 * @param int $post_id ID do post.
 * @return int Minutos.
 */
function rd_get_reading_time( $post_id ) {
	$cached = get_post_meta( $post_id, '_rd_reading_time', true );
	if ( '' !== $cached && is_numeric( $cached ) ) {
		return (int) $cached;
	}
	$minutes = rd_calculate_reading_time( $post_id );
	update_post_meta( $post_id, '_rd_reading_time', $minutes );
	return $minutes;
}

/**
 * Renderiza o span do reading time pra inserir no .entry-meta do single.
 * Sai cedo se feature OFF — zero overhead.
 *
 * @param int $post_id ID do post.
 */
function rd_render_reading_time( $post_id ) {
	if ( ! rd_get_option_bool( 'enable_reading_time', true ) ) {
		return;
	}
	$minutes = rd_get_reading_time( $post_id );
	?>
	<span class="reading-time">
		<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
		<?php
		printf(
			esc_html(
				/* translators: %d = number of minutes to read the post */
				_n( '%d min read', '%d min read', $minutes, 'reloaded' )
			),
			(int) $minutes
		);
		?>
	</span>
	<?php
}

/**
 * Recalcula e atualiza o cache no save_post. Só pra post_type=post.
 *
 * @param int $post_id ID do post salvo.
 */
function rd_reading_time_refresh_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_rd_reading_time', rd_calculate_reading_time( $post_id ) );
}
add_action( 'save_post', 'rd_reading_time_refresh_cache' );
