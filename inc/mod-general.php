<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Módulo Geral - Recursos de Interface e Comportamento Padrão
 */

/***********************************************************************************
 * Controle de Exibição da Imagem Destacada (Single)       - (Interface) - (Geral) *
 */

// 1. Cria a caixinha (Meta Box) só se PELO MENOS uma das opções gate estiver
// ativa no painel. Hoje: enable_thumb_control OU enable_primary_category.
// Se ambas desligadas, a caixinha some por inteiro (não fica vazia).
add_action( 'add_meta_boxes', 'rd_add_post_options_meta_box' );
function rd_add_post_options_meta_box() {
	if ( ! rd_get_option_bool( 'enable_thumb_control' ) &&
		! rd_get_option_bool( 'enable_primary_category' ) &&
		! rd_get_option_bool( 'enable_post_kicker' ) ) {
		return;
	}
	add_meta_box(
		'rd_post_options',
		__( 'Post Options (ReloadeD)', 'reloaded' ),
		'rd_post_options_callback',
		'post',
		'side',
		'default'
	);
}

/**
 * 2. Desenha o HTML dos controles dentro da caixinha.
 *
 * @param WP_Post $post Post atual sendo editado.
 */
function rd_post_options_callback( $post ) {
	wp_nonce_field( 'rd_save_post_options', 'rd_post_options_nonce' );

	// --- Hide Featured Image (se a opção global estiver ativa) ---
	if ( rd_get_option_bool( 'enable_thumb_control' ) ) :
		$is_hidden = get_post_meta( $post->ID, '_rd_hide_thumbnail', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="rd_hide_thumbnail" value="yes" <?php checked( $is_hidden, 'yes' ); ?> />
				<?php esc_html_e( 'Hide Featured Image at the top of the post', 'reloaded' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Check to prevent the thumbnail from duplicating with videos inserted at the beginning of the text.', 'reloaded' ); ?></p>
		<hr class="rd-post-options-divider">
	<?php endif; ?>

	<?php // --- Primary Category (se a opção global estiver ativa) --- ?>
	<?php if ( rd_get_option_bool( 'enable_primary_category' ) ) : ?>
	<p>
		<label for="rd_primary_category" class="rd-post-options-label">
			<strong><?php esc_html_e( 'Primary Category', 'reloaded' ); ?></strong>
		</label>
		<?php
		$primary_id = (int) get_post_meta( $post->ID, '_rd_primary_category', true );
		$post_cats  = get_the_category( $post->ID );

		if ( empty( $post_cats ) ) :
			?>
			<span class="description"><?php esc_html_e( 'Save the post with categories assigned to choose a primary one.', 'reloaded' ); ?></span>
			<?php
		else :
			?>
			<select id="rd_primary_category" name="rd_primary_category" class="rd-post-options-select">
				<option value="0"><?php esc_html_e( 'Auto (first category)', 'reloaded' ); ?></option>
				<?php foreach ( $post_cats as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $primary_id, $cat->term_id ); ?>>
						<?php echo esc_html( $cat->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		endif;
		?>
	</p>
	<p class="description">
		<?php esc_html_e( 'When the post belongs to multiple categories, only the primary one will highlight in the menu.', 'reloaded' ); ?>
	</p>
	<?php endif; // enable_primary_category ?>

	<?php // --- Post Overline (se a opção global estiver ativa) --- ?>
	<?php
	if ( rd_get_option_bool( 'enable_post_kicker' ) ) :
		$overline = get_post_meta( $post->ID, '_rd_post_kicker', true );
		?>
		<hr class="rd-post-options-divider">
		<p>
			<label for="rd_post_kicker" class="rd-post-options-label">
				<strong><?php esc_html_e( 'Overline', 'reloaded' ); ?></strong>
			</label>
			<input type="text" id="rd_post_kicker" name="rd_post_kicker" value="<?php echo esc_attr( $overline ); ?>" class="widefat" maxlength="60" placeholder="<?php esc_attr_e( 'e.g. INTERVIEW, EXCLUSIVE, ANALYSIS', 'reloaded' ); ?>" />
		</p>
		<p class="description">
			<?php esc_html_e( 'Optional small label rendered above the title on single posts and on grid cards. Useful for journalism-style overlines. Leave empty to hide.', 'reloaded' ); ?>
		</p>
	<?php endif; // enable_post_kicker ?>
	<?php
}

// 3. Salva as preferências individuais no banco de dados
add_action( 'save_post', 'rd_save_post_options' );
function rd_save_post_options( $post_id ) {
	if ( ! isset( $_POST['rd_post_options_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rd_post_options_nonce'] ) ), 'rd_save_post_options' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Hide Featured Image
	if ( isset( $_POST['rd_hide_thumbnail'] ) ) {
		update_post_meta( $post_id, '_rd_hide_thumbnail', 'yes' );
	} else {
		delete_post_meta( $post_id, '_rd_hide_thumbnail' );
	}

	// Primary Category — só salva se a opção global estiver ativa E o ID for
	// de uma categoria realmente atribuída ao post
	if ( rd_get_option_bool( 'enable_primary_category' ) && isset( $_POST['rd_primary_category'] ) ) {
		$chosen_id = absint( $_POST['rd_primary_category'] );
		if ( $chosen_id === 0 ) {
			// "Auto" → limpa o meta pra usar o fallback da primeira categoria
			delete_post_meta( $post_id, '_rd_primary_category' );
		} else {
			$post_cat_ids = wp_get_post_categories( $post_id );
			if ( in_array( $chosen_id, $post_cat_ids, true ) ) {
				update_post_meta( $post_id, '_rd_primary_category', $chosen_id );
			} else {
				// Categoria escolhida não pertence mais ao post — limpa
				delete_post_meta( $post_id, '_rd_primary_category' );
			}
		}
	}

	// Post Overline — só salva se a opção global estiver ativa
	if ( rd_get_option_bool( 'enable_post_kicker' ) && isset( $_POST['rd_post_kicker'] ) ) {
		$overline = sanitize_text_field( wp_unslash( $_POST['rd_post_kicker'] ) );
		if ( ! empty( $overline ) ) {
			update_post_meta( $post_id, '_rd_post_kicker', $overline );
		} else {
			delete_post_meta( $post_id, '_rd_post_kicker' );
		}
	}
}

/***********************************************************************************
 * Helper: retorna o HTML do "Overline" (chapéu) de um post como string. - (Geral) *
 *                                                                                 *
 * Versão "get" pra ser consumida onde o markup é montado por concatenação         *
 * (ex.: `rd_render_post_card()` em `inc/post-card.php`). Mesma lógica do          *
 * `rd_render_post_overline()` que apenas envolve essa função com `echo`.          *
 *                                                                                 *
 * Retorna string vazia quando: feature global off, post_id inválido ou overline   *
 * não preenchido — chamador pode concatenar com segurança sem checagem extra.     *
 ***********************************************************************************/
function rd_get_post_overline_html( int $post_id = 0, string $context = 'single' ): string {
	if ( ! rd_get_option_bool( 'enable_post_kicker' ) ) {
		return '';
	}

	if ( ! $post_id ) {
		$post_id = (int) get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	$overline = trim( (string) get_post_meta( $post_id, '_rd_post_kicker', true ) );
	if ( empty( $overline ) ) {
		return '';
	}

	$context_safe = preg_replace( '/[^a-z0-9_-]/i', '', $context );
	if ( empty( $context_safe ) ) {
		$context_safe = 'single';
	}

	return sprintf(
		'<span class="entry-overline entry-overline-%s">%s</span>',
		esc_attr( $context_safe ),
		esc_html( $overline )
	);
}

/***********************************************************************************
 * Helper: imprime o "Overline" (chapéu) acima do título de um post.     - (Geral) *
 *                                                                                 *
 * Wrapper de echo sobre `rd_get_post_overline_html()`. Use direto em templates    *
 * (single.php, index.php) onde o markup é estruturado com tags PHP echo.          *
 ***********************************************************************************/
function rd_render_post_overline( int $post_id = 0, string $context = 'single' ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns already-escaped HTML (esc_attr + esc_html applied internally)
	echo rd_get_post_overline_html( $post_id, $context );
}

/***********************************************************************************
 * Qualidade de imagens Dinâmica - OPÇÕES GERAIS                         - (Geral) *
 ***********************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $quality faz parte da assinatura dos filters `jpeg_quality`/`webp_quality`/etc; ignoramos o valor recebido e devolvemos o configurado no painel.
function rd_custom_image_quality( $quality ) {
	$opt = get_option( 'rd_settings' );

	// Comparação estrita com '' em vez de !empty() — evita que valores
	// numéricos legítimos (que poderiam coincidir com falsy do PHP) sejam
	// descartados. Range clamping defensivo (1-100) caso algum valor inválido
	// chegue até aqui burlando o min/max do input do painel.
	if ( isset( $opt['jpeg_quality'] ) && $opt['jpeg_quality'] !== '' ) {
		return max( 1, min( 100, intval( $opt['jpeg_quality'] ) ) );
	}
	return 90;
}
add_filter( 'jpeg_quality', 'rd_custom_image_quality' );
add_filter( 'webp_quality', 'rd_custom_image_quality' );
add_filter( 'avif_quality', 'rd_custom_image_quality' );
add_filter( 'wp_editor_set_quality', 'rd_custom_image_quality' );

/*******************************************************************************
 * Kill switch global de comentários                                 - (Geral) *
 *                                                                             *
 * Quando `enable_comments_globally` está OFF:                                 *
 *   - `comments_open` retorna false → formulário e lista não renderizam      *
 *   - `pings_open` retorna false → pingbacks/trackbacks desabilitados        *
 *   - Aplica em TODOS os posts existentes instantaneamente (sem precisar    *
 *     bulk-edit no DB ou rodar SQL)                                          *
 *                                                                            *
 * Quando ON: comportamento WP nativo respeitado (per-post + Settings WP).    *
 *                                                                            *
 * Plugins que sobrescrevem `comments_template` (WP-Discourse, Disqus, etc)  *
 * continuam funcionando independente desse toggle — eles operam num hook    *
 * downstream que não é afetado por `comments_open`.                          *
 *******************************************************************************/
function rd_filter_comments_open( $open ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) ) {
		return false;
	}
	return $open;
}
add_filter( 'comments_open', 'rd_filter_comments_open' );
add_filter( 'pings_open', 'rd_filter_comments_open' ); // mesma lógica pra pingbacks/trackbacks

/*******************************************************************************
 * Esconde a lista de comments existentes quando kill switch está OFF          *
 *                                                                              *
 * O `comments_open` filter sozinho impede NOVOS comments mas a lista de      *
 * existentes continua renderizando. Esse filter complementar retorna array    *
 * vazio pro `wp_list_comments()` — ninguém aparece no front (lista some       *
 * inteira). DB permanece intocado: religar o toggle restaura tudo na hora.    *
 *                                                                              *
 * Também zera o `comment_count` exibido (ícone "X comentários" na meta),     *
 * via filter `get_comments_number`, pra evitar inconsistência visual.         *
 *******************************************************************************/
function rd_hide_comments_when_disabled( $comments ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) ) {
		return array();
	}
	return $comments;
}
add_filter( 'comments_array', 'rd_hide_comments_when_disabled' );

function rd_zero_comments_count_when_disabled( $count ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) ) {
		return 0;
	}
	return $count;
}
add_filter( 'get_comments_number', 'rd_zero_comments_count_when_disabled' );

/*******************************************************************************
 * Zera queries de comments no frontend quando kill switch está OFF            *
 *                                                                              *
 * Os filters `comments_array` e `get_comments_number` cobrem a renderização   *
 * do template `comments.php` mas NÃO afetam queries diretas de comments       *
 * feitas por outros lugares — principalmente o widget "Comentários Recentes" *
 * (legado WP_Widget_Recent_Comments + block core/latest-comments) e qualquer  *
 * código de frontend que use `get_comments()` ou `WP_Comment_Query`.          *
 *                                                                              *
 * Injetamos `post__in = [0]` (post ID inexistente) → query sempre retorna     *
 * array vazio. Admin é preservado (`! is_admin()`) pra moderação funcionar    *
 * normal. Toggle religado = queries voltam ao normal na hora.                 *
 *******************************************************************************/
function rd_disable_frontend_comment_queries( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( rd_get_option_bool( 'enable_comments_globally' ) ) {
		return;
	}

	$query->query_vars['post__in'] = array( 0 );
}
add_action( 'pre_get_comments', 'rd_disable_frontend_comment_queries' );

/*******************************************************************************
 * Esconde widgets de "Comentários Recentes" inteiros quando kill switch off   *
 *                                                                              *
 * Filtrar a query (acima) já zera o conteúdo do widget, mas ele ainda         *
 * renderiza o título e a mensagem "Nenhum comentário pra mostrar" — visual    *
 * de widget órfão. Aqui pulamos a renderização total dos 2 sabores:           *
 *                                                                              *
 *   - `WP_Widget_Recent_Comments` (widget legado, em sidebars antigas)        *
 *   - `core/latest-comments` (block widget, editor Gutenberg de widgets)      *
 *                                                                              *
 * Religou o toggle → widgets reaparecem na hora, sem perder configuração.    *
 *******************************************************************************/
function rd_hide_legacy_recent_comments_widget( $instance, $widget ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) && $widget instanceof WP_Widget_Recent_Comments ) {
		return false; // skip render
	}
	return $instance;
}
add_filter( 'widget_display_callback', 'rd_hide_legacy_recent_comments_widget', 10, 2 );

function rd_hide_block_latest_comments( $pre_render, $parsed_block ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' )
		&& isset( $parsed_block['blockName'] )
		&& $parsed_block['blockName'] === 'core/latest-comments' ) {
		return ''; // string vazia pula o render do block
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'rd_hide_block_latest_comments', 10, 2 );

/*******************************************************************************
 * Corrige avisos de Acessibilidade no Formulário de Comentários     - (Geral) *
 *******************************************************************************/
function rd_fix_comment_form_labels( $fields ) {

	if ( ! rd_get_option_bool( 'comment_a11y' ) ) {
		return $fields;
	}

	$commenter = wp_get_current_commenter();
	$req       = get_option( 'require_name_email' );
	$html_req  = ( $req ? " required='required'" : '' );

	// Reescreve o campo NOME (Adicionado autocomplete="name")
	$fields['author'] = '<p class="comment-form-author">' .
						'<label for="author_name">' . esc_html__( 'Name', 'reloaded' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
						'<input id="author_name" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245" autocomplete="name"' . $html_req . ' />' .
						'</p>';

	// Reescreve o campo EMAIL (Adicionado autocomplete="email")
	$fields['email'] = '<p class="comment-form-email">' .
						'<label for="author_email">' . esc_html__( 'Email', 'reloaded' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
						'<input id="author_email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email"' . $html_req . ' />' .
						'</p>';

	// Reescreve o campo SITE (Adicionado autocomplete="url")
	$fields['url'] = '<p class="comment-form-url">' .
						'<label for="author_url">' . esc_html__( 'Website', 'reloaded' ) . '</label> ' .
						'<input id="author_url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" autocomplete="url" />' .
						'</p>';

	// Reescreve o campo de COOKIES
	$consent           = empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"';
	$fields['cookies'] = '<p class="comment-form-cookies-consent">' .
						'<input id="wp-comment-cookies-consent-id" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' />' .
						'<label for="wp-comment-cookies-consent-id">' . esc_html__( 'Save my name, email, and website in this browser for the next time I comment.', 'reloaded' ) . '</label>' .
						'</p>';

	return $fields;
}
add_filter( 'comment_form_default_fields', 'rd_fix_comment_form_labels' );

/*******************************************************************************
 * Markdown nos comentários (renderização)                           - (Geral) *
 *                                                                             *
 * Substitui o pipeline default do `comment_text` (wpautop + texturize) por   *
 * Parsedown — a mesma lib que já carregamos pros posts. Comentários ganham   *
 * suporte a:                                                                  *
 *                                                                             *
 *   - `**bold**` / `_italic_`                                                 *
 *   - `> citação`                                                             *
 *   - `[texto](url)` links                                                    *
 *   - listas, headers, code inline + blocks com syntax highlight              *
 *   - tabelas (GFM)                                                           *
 *                                                                             *
 * Parsedown em safe mode = strip de HTML perigoso (scripts, iframes etc),    *
 * só o Markdown puro é convertido. Usuários comuns escrevem texto normal,    *
 * power users formatam com Markdown — ambos funcionam.                       *
 *                                                                             *
 * `setMarkupEscaped(true)` adicional escapa qualquer HTML literal que o     *
 * usuário tente colar — proteção dupla.                                       *
 *******************************************************************************/
function rd_comment_markdown( $content ) {
	if ( ! class_exists( 'Parsedown' ) ) {
		require_once get_template_directory() . '/lib/Parsedown.php';
	}

	$parsedown = new Parsedown();
	$parsedown->setSafeMode( true );
	$parsedown->setMarkupEscaped( true );

	return $parsedown->text( $content );
}
// IMPORTANTE: Parsedown roda em prioridade 5 — ANTES dos filters padrão do WP
// (make_clickable=9, wptexturize=10, convert_chars=10, force_balance_tags=25,
// wpautop=30) que mangleam o input Markdown:
// - convert_chars transforma `>` em `&gt;` (quebra blockquotes)
// - wptexturize transforma `-` em `&#8211;` (quebra listas, troca por en-dash)
// - make_clickable converte URLs cruas antes do Parsedown ver a sintaxe [text](url)
// Rodando primeiro, o output JÁ é HTML com `<a>`, `<ul>`, `<blockquote>` etc.,
// e os filters seguintes operam só em text nodes (typography touches inofensivos).
add_filter( 'comment_text', 'rd_comment_markdown', 5 );

// Remove filters que estragam HTML estruturado depois do Parsedown:
// - wpautop transforma quebras de linha em <p>, duplicando tags do Parsedown
// - force_balance_tags pode quebrar HTML válido nosso
remove_filter( 'comment_text', 'wpautop', 30 );
remove_filter( 'comment_text', 'force_balance_tags', 25 );

/*******************************************************************************
 * Dica "Markdown suportado" ao lado da label do form de comments    - (Geral) *
 *                                                                             *
 * Hook em `comment_form_field_comment` que injeta um <span> com exemplos      *
 * curtos logo APÓS o </label> do textarea — fica irmão do label no DOM, o    *
 * CSS então posiciona um à esquerda e outro à direita na mesma linha.        *
 *                                                                             *
 * Usamos <span> (não <p>) porque o field já é um <p class="comment-form-      *
 * comment">, e <p> aninhado em <p> é HTML inválido (o parser fechava o pai). *
 * Aparece pra TODOS os visitantes (logados ou não).                          *
 *******************************************************************************/
function rd_comment_form_markdown_hint( $field ) {
	// O id é referenciado por aria-describedby no <textarea> (a11y: leitor de
	// tela anuncia a dica de markdown quando o campo recebe foco).
	$hint  = '<span id="rd-comment-md-hint" class="rd-comment-markdown-hint">';
	$hint .= esc_html__( 'Markdown:', 'reloaded' );
	$hint .= ' <code>**bold**</code> <code>_italic_</code> <code>[link](url)</code> <code>> quote</code> <code>`code`</code>';
	$hint .= '</span>';

	// Adiciona aria-describedby no <textarea> ligando-o ao hint.
	$field = preg_replace(
		'/<textarea\b([^>]*)>/i',
		'<textarea$1 aria-describedby="rd-comment-md-hint">',
		$field,
		1
	);

	// Injeta o hint logo após o primeiro </label> do field. Garante posição
	// semântica (label + hint juntos, descrevendo o mesmo input).
	$needle = '</label>';
	$pos    = strpos( $field, $needle );
	if ( false !== $pos ) {
		return substr_replace( $field, $needle . $hint, $pos, strlen( $needle ) );
	}

	// Fallback defensivo: se o markup mudar e não tiver </label>, mantém
	// comportamento antigo de appendar depois do field.
	return $field . $hint;
}
add_filter( 'comment_form_field_comment', 'rd_comment_form_markdown_hint' );

/*******************************************************************************
 * Posição da Sidebar (esquerda/direita)                             - (Geral) *
 *                                                                             *
 * Adiciona a classe `rd-sidebar-left` no <body> quando o admin escolhe        *
 * posicionar a sidebar à esquerda. O CSS faz o flip via `flex-direction:      *
 * row-reverse` no container do .site-main, sem tocar markup.                  *
 *                                                                             *
 * Mobile (≤1024px) ignora a flag — sidebar sempre vai abaixo do conteúdo     *
 * (gerenciado pelo media query do _grid.scss).                                *
 *******************************************************************************/
function rd_add_sidebar_position_body_class( array $classes ): array {
	if ( rd_get_option( 'sidebar_position', 'right' ) === 'left' ) {
		$classes[] = 'rd-sidebar-left';
	}
	return $classes;
}
add_filter( 'body_class', 'rd_add_sidebar_position_body_class' );

/*******************************************************************************
 * Quantidade de palavras nos excerpts automáticos                   - (Geral) *
 *                                                                             *
 * WordPress default é 55. O admin pode ajustar via painel (Geral → Excerpt    *
 * Length). Posts com excerpt manual (campo "Resumo" do editor) não passam     *
 * pelo `wp_trim_words` e portanto não são afetados por essa configuração.    *
 *******************************************************************************/
function rd_custom_excerpt_length( $length ) {
	$opt = get_option( 'rd_settings' );

	if ( isset( $opt['excerpt_length'] ) && (int) $opt['excerpt_length'] > 0 ) {
		return (int) $opt['excerpt_length'];
	}
	return $length; // mantém o default do WP (55)
}
add_filter( 'excerpt_length', 'rd_custom_excerpt_length', 999 );

/*******************************************************************************
 * Personaliza o texto do "Leia Mais"                                - (Geral) *
 *******************************************************************************/
function rd_custom_excerpt_more( $more ) {
	$opt = get_option( 'rd_settings' );

	// Comparação estrita com '' em vez de !empty() — `empty('0')` retorna
	// true, o que descartaria um texto literal "0" como label do botão.
	// Caso extremo, mas mantém o pattern consistente em todo o tema.
	if ( isset( $opt['excerpt_text'] ) && $opt['excerpt_text'] !== '' ) {
		return '... <br><span class="more-link">' . esc_html( $opt['excerpt_text'] ) . '</span>';
	}

	return $more;
}
add_filter( 'excerpt_more', 'rd_custom_excerpt_more' );

/*******************************************************************************
 * Altera o texto "em" no bloco de Últimos Comentários               - (Geral) *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $domain faz parte da assinatura do filter `gettext`, mesmo quando não consultamos o text domain.
function rd_change_latest_comments_text( $translated_text, $text, $domain ) {
	if ( $text === '%1$s on %2$s' || $text === '%s on %s' ) {

		$opt = get_option( 'rd_settings' );
		$sep = isset( $opt['comments_separator'] ) ? $opt['comments_separator'] : '';

		if ( $sep === '' ) {
			return $translated_text;
		}

		return '%1$s ' . wp_kses_post( $sep ) . ' %2$s';
	}

	return $translated_text;
}
add_filter( 'gettext', 'rd_change_latest_comments_text', 20, 3 );
