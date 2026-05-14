/**
 * View Tracker — registra uma view do post via AJAX, 1.5s depois do
 * DOMContentLoaded. O server (mod-views.php) valida o nonce, deduplica
 * por IP por 30 minutos e filtra bots conhecidos pela User-Agent.
 *
 * Dados dinâmicos injetados por wp_localize_script como `rd_views_data`:
 *   - ajaxurl: endpoint do admin-ajax
 *   - post_id: ID do post atual
 *   - nonce:   nonce de segurança específico do post
 *
 * Este arquivo só é enfileirado em is_singular() pra posts/pages com
 * tracking ativo no painel — admins e bots conhecidos não chegam aqui.
 */
window.addEventListener('DOMContentLoaded', function () {
    if (typeof rd_views_data === 'undefined') return;

    setTimeout(function () {
        fetch(rd_views_data.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=rd_track_view'
                + '&post_id=' + encodeURIComponent(rd_views_data.post_id)
                + '&nonce=' + encodeURIComponent(rd_views_data.nonce),
            keepalive: true
        });
    }, 1500);
});
