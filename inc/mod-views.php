<?php
defined('ABSPATH') || exit;

/*******************************************************************************
 * Module: Views - Sistema de contagem de visualizações por post               *
 *                                                                             *
 * Recursos:                                                                   *
 * - Tracking via AJAX (escapa de cache de página)                             *
 * - Deduplicação por IP (1 view a cada 30min por IP)                          *
 * - Filtro de bots conhecidos via User-Agent                                  *
 * - Janelas temporais: all-time, mês, semana, dia                             *
 * - API pública: rd_get_post_views() / rd_get_popular_posts()                 *
 *******************************************************************************/

const RD_VIEWS_META_KEY        = '_rd_post_views';
const RD_VIEWS_META_KEY_LOG    = '_rd_post_views_log';
const RD_VIEWS_DEDUP_WINDOW    = 1800;  // 30 minutos
const RD_VIEWS_LOG_RETENTION   = 31536000; // 1 ano (limpa entries antigas)

/**
 * Hook: enfileira script de tracking nas páginas singulares
 */
function rd_views_enqueue_tracker() {
    if ( ! is_singular( array('post', 'page') ) ) {
        return;
    }

    if ( ! rd_get_option_bool('enable_views_tracking') ) {
        return;
    }

    // Não rastrear admins (evita inflar contagem durante edição)
    if ( current_user_can('edit_posts') ) {
        return;
    }

    $post_id = get_the_ID();
    $nonce   = wp_create_nonce( 'rd_track_view_' . $post_id );

    // Inline minúsculo, sem dependência de jQuery
    $script = sprintf(
        'window.addEventListener("DOMContentLoaded",function(){' .
        'setTimeout(function(){' .
        'fetch("%s",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=rd_track_view&post_id=%d&nonce=%s",keepalive:true});' .
        '},1500);' .
        '});',
        esc_url( admin_url('admin-ajax.php') ),
        $post_id,
        $nonce
    );

    wp_register_script( 'rd-views-tracker', '', array(), null, true );
    wp_enqueue_script( 'rd-views-tracker' );
    wp_add_inline_script( 'rd-views-tracker', $script );
}
add_action( 'wp_enqueue_scripts', 'rd_views_enqueue_tracker' );

/**
 * Endpoint AJAX: registra view (acessível por usuários não logados)
 */
function rd_views_ajax_track() {
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $nonce   = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';

    // Validação básica
    if ( ! $post_id || ! wp_verify_nonce( $nonce, 'rd_track_view_' . $post_id ) ) {
        wp_send_json_error( 'invalid', 400 );
    }

    if ( get_post_status($post_id) !== 'publish' ) {
        wp_send_json_error( 'not_published', 400 );
    }

    // Bloqueia admins / editores também no backend (defesa em profundidade)
    if ( is_user_logged_in() && current_user_can('edit_posts') ) {
        wp_send_json_success( 'admin_ignored' );
    }

    // Filtra bots por User-Agent
    if ( rd_views_is_bot() ) {
        wp_send_json_success( 'bot_ignored' );
    }

    // Deduplica por IP
    $ip = rd_views_get_client_ip();
    $dedup_key = 'rd_view_' . md5( $post_id . '_' . $ip );

    if ( get_transient( $dedup_key ) ) {
        wp_send_json_success( 'already_counted' );
    }

    // Registra view
    rd_views_increment( $post_id );

    // Marca IP como contado pra esse post
    set_transient( $dedup_key, 1, RD_VIEWS_DEDUP_WINDOW );

    wp_send_json_success( 'counted' );
}
add_action( 'wp_ajax_rd_track_view', 'rd_views_ajax_track' );
add_action( 'wp_ajax_nopriv_rd_track_view', 'rd_views_ajax_track' );

/**
 * Incrementa contadores: total + log temporal
 *
 * Usa update_post_meta diretamente sem trigger de save_post,
 * evitando que o editor detecte "post modificado".
 */
function rd_views_increment( $post_id ) {

    // Contador total (all-time)
    $total = (int) get_post_meta( $post_id, RD_VIEWS_META_KEY, true );
    update_post_meta( $post_id, RD_VIEWS_META_KEY, $total + 1 );

    // Log temporal (timestamps das últimas N views, pra queries por janela)
    $log = get_post_meta( $post_id, RD_VIEWS_META_KEY_LOG, true );
    if ( ! is_array( $log ) ) {
        $log = array();
    }

    $now = time();
    $log[] = $now;

    // Limpa entries mais velhas que retention (evita explodir banco)
    $cutoff = $now - RD_VIEWS_LOG_RETENTION;
    $log = array_filter( $log, fn($t) => $t >= $cutoff );

    update_post_meta( $post_id, RD_VIEWS_META_KEY_LOG, array_values( $log ) );
}

/**
 * Detecta bots por User-Agent (lista dos principais)
 */
function rd_views_is_bot() {
    if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
        return true; // sem UA = suspeito
    }

    $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
    $bots = array(
        'bot', 'spider', 'crawler', 'scraper', 'slurp', 'mediapartners',
        'facebookexternalhit', 'twitterbot', 'discordbot', 'telegrambot',
        'whatsapp', 'linkedinbot', 'embedly', 'quora', 'pinterest',
        'redditbot', 'applebot', 'duckduckbot', 'baiduspider', 'yandexbot',
        'bingbot', 'googlebot', 'gptbot', 'chatgpt-user', 'ccbot', 'claudebot',
        'anthropic-ai', 'perplexitybot', 'wget', 'curl', 'headless',
    );

    foreach ( $bots as $bot ) {
        if ( strpos( $ua, $bot ) !== false ) {
            return true;
        }
    }
    return false;
}

/**
 * Pega IP real do cliente (considerando Cloudflare)
 */
function rd_views_get_client_ip() {
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
    }
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
        return sanitize_text_field( trim( $ips[0] ) );
    }
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
}

/**
 * Esconde nossos meta keys da UI de Custom Fields do editor
 * (eles começam com "_" então o WP já esconde por convenção,
 *  mas reforçamos aqui pra garantir)
 */
function rd_views_hide_meta_keys( $protected, $meta_key ) {
    if ( in_array( $meta_key, array( RD_VIEWS_META_KEY, RD_VIEWS_META_KEY_LOG ), true ) ) {
        return true;
    }
    return $protected;
}
add_filter( 'is_protected_meta', 'rd_views_hide_meta_keys', 10, 2 );

/* =============================================================================
 *  API PÚBLICA (use em templates)
 * ============================================================================= */

/**
 * Retorna número de views de um post (all-time ou janela)
 *
 * @param int    $post_id
 * @param string $window 'all', 'day', 'week', 'month', 'year'
 * @return int
 */
function rd_get_post_views( $post_id, $window = 'all' ) {
    if ( $window === 'all' ) {
        return (int) get_post_meta( $post_id, RD_VIEWS_META_KEY, true );
    }

    $log = get_post_meta( $post_id, RD_VIEWS_META_KEY_LOG, true );
    if ( ! is_array( $log ) ) {
        return 0;
    }

    $cutoff_map = array(
        'day'   => DAY_IN_SECONDS,
        'week'  => WEEK_IN_SECONDS,
        'month' => MONTH_IN_SECONDS,
        'year'  => YEAR_IN_SECONDS,
    );

    if ( ! isset( $cutoff_map[ $window ] ) ) {
        return 0;
    }

    $cutoff = time() - $cutoff_map[ $window ];
    return count( array_filter( $log, fn($t) => $t >= $cutoff ) );
}

/**
 * Retorna posts mais populares (all-time)
 *
 * @param int   $limit Quantidade
 * @param array $args  Args adicionais pra WP_Query
 * @return WP_Query
 */
function rd_get_popular_posts( $limit = 3, $args = array() ) {
    $defaults = array(
        'post_type'      => 'post',
        'posts_per_page' => $limit,
        'meta_key'       => RD_VIEWS_META_KEY,
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    );

    return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Adiciona coluna "Views" na lista de posts do admin
 */
function rd_views_admin_column( $columns ) {
    $columns['rd_views'] = '👁️ Views';
    return $columns;
}
add_filter( 'manage_post_posts_columns', 'rd_views_admin_column' );

function rd_views_admin_column_content( $column, $post_id ) {
    if ( $column === 'rd_views' ) {
        $views = rd_get_post_views( $post_id );
        echo number_format_i18n( $views );
    }
}
add_action( 'manage_post_posts_custom_column', 'rd_views_admin_column_content', 10, 2 );

function rd_views_admin_column_sortable( $columns ) {
    $columns['rd_views'] = 'rd_views';
    return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', 'rd_views_admin_column_sortable' );

function rd_views_admin_column_orderby( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get('orderby') === 'rd_views' ) {
        $query->set('meta_key', RD_VIEWS_META_KEY);
        $query->set('orderby', 'meta_value_num');
    }
}
add_action( 'pre_get_posts', 'rd_views_admin_column_orderby' );