<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Módulo Geral - Recursos de Interface e Comportamento Padrão
 *******************************************************************************/

/***********************************************************************************
 * Controle de Exibição da Imagem Destacada (Single)       - (Interface) - (Geral) *
 ***********************************************************************************/

// 1. Cria a caixinha (Meta Box) APENAS se a opção global estiver ativa no painel
add_action( 'add_meta_boxes', 'rd_add_post_options_meta_box' );
function rd_add_post_options_meta_box() {
    if ( rd_get_option_bool('enable_thumb_control') ) {
        add_meta_box(
            'rd_post_options',
            'Opções do Artigo (ReloadeD)',
            'rd_post_options_callback',
            'post',
            'side',
            'default'
        );
    }
}

// 2. Desenha o HTML do Checkbox dentro da caixinha
function rd_post_options_callback( $post ) {
    wp_nonce_field( 'rd_save_post_options', 'rd_post_options_nonce' );
    $is_hidden = get_post_meta( $post->ID, '_rd_hide_thumbnail', true );
    ?>
    <p>
        <label>
            <input type="checkbox" name="rd_hide_thumbnail" value="yes" <?php checked( $is_hidden, 'yes' ); ?> />
            Ocultar Imagem Destacada no topo da leitura
        </label>
    </p>
    <p class="description">Marque para impedir que a miniatura duplique com vídeos inseridos no início do texto.</p>
    <?php
}

// 3. Salva a preferência individual no banco de dados
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

    if ( isset( $_POST['rd_hide_thumbnail'] ) ) {
        update_post_meta( $post_id, '_rd_hide_thumbnail', 'yes' );
    } else {
        delete_post_meta( $post_id, '_rd_hide_thumbnail' );
    }
}

/***********************************************************************************
 * Qualidade de imagens Dinâmica - OPÇÕES GERAIS                         - (Geral) *
 ***********************************************************************************/
function rd_custom_image_quality( $quality ) {
    $opt = get_option('rd_settings');
    
    if ( isset( $opt['jpeg_quality'] ) && !empty( $opt['jpeg_quality'] ) ) {
        return intval( $opt['jpeg_quality'] ); 
    }
    return 90; 
}
add_filter( 'jpeg_quality', 'rd_custom_image_quality' );
add_filter( 'webp_quality', 'rd_custom_image_quality' );
add_filter( 'avif_quality', 'rd_custom_image_quality' );
add_filter( 'wp_editor_set_quality', 'rd_custom_image_quality' );

/*******************************************************************************
 * Corrige avisos de Acessibilidade no Formulário de Comentários     - (Geral) *
 *******************************************************************************/
function rd_fix_comment_form_labels( $fields ) {
    
    if ( ! rd_get_option_bool('comment_a11y') ) return $fields;

    $commenter = wp_get_current_commenter();
    $req       = get_option( 'require_name_email' );
    $html_req  = ( $req ? " required='required'" : '' );

    // Reescreve o campo NOME (Adicionado autocomplete="name")
    $fields['author'] = '<p class="comment-form-author">' .
                        '<label for="author_name">Nome' . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
                        '<input id="author_name" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245" autocomplete="name"' . $html_req . ' />' .
                        '</p>';

    // Reescreve o campo EMAIL (Adicionado autocomplete="email")
    $fields['email']  = '<p class="comment-form-email">' .
                        '<label for="author_email">E-mail' . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
                        '<input id="author_email" name="email" type="email" value="' . esc_attr(  $commenter['comment_author_email'] ) . '" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email"' . $html_req . ' />' .
                        '</p>';

    // Reescreve o campo SITE (Adicionado autocomplete="url")
    $fields['url']    = '<p class="comment-form-url">' .
                        '<label for="author_url">Site</label> ' .
                        '<input id="author_url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" autocomplete="url" />' .
                        '</p>';

    // Reescreve o campo de COOKIES
    $consent = empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"';
    $fields['cookies'] = '<p class="comment-form-cookies-consent">' .
                         '<input id="wp-comment-cookies-consent-id" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' />' .
                         '<label for="wp-comment-cookies-consent-id">Salvar meus dados neste navegador para a próxima vez que eu comentar.</label>' .
                         '</p>';

    return $fields;
}
add_filter( 'comment_form_default_fields', 'rd_fix_comment_form_labels' );

/*******************************************************************************
 * Personaliza o texto do "Leia Mais"                                - (Geral) *
 *******************************************************************************/
function rd_custom_excerpt_more( $more ) {
    $opt = get_option('rd_settings');
    
    if ( isset( $opt['excerpt_text'] ) && !empty( $opt['excerpt_text'] ) ) {
        return '... <br><span class="more-link">' . esc_html( $opt['excerpt_text'] ) . '</span>';
    }

    return $more; 
}
add_filter( 'excerpt_more', 'rd_custom_excerpt_more' );

/*******************************************************************************
 * Altera o texto "em" no bloco de Últimos Comentários               - (Geral) *
 *******************************************************************************/
function rd_change_latest_comments_text( $translated_text, $text, $domain ) {
    if ( $text === '%1$s on %2$s' || $text === '%s on %s' ) {
        
        $opt = get_option('rd_settings');
        $sep = isset($opt['comments_separator']) ? $opt['comments_separator'] : '';

        if ( $sep === '' ) {
            return $translated_text;
        }

        return '%1$s ' . wp_kses_post( $sep ) . ' %2$s';
    }

    return $translated_text;
}
add_filter( 'gettext', 'rd_change_latest_comments_text', 20, 3 );