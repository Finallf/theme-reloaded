/**
 * View Tracker — registers a post view via AJAX, 1.5s after
 * DOMContentLoaded. The server (mod-views.php) validates the nonce, dedupes
 * by IP for 30 minutes and filters known bots by User-Agent.
 *
 * Dynamic data injected by wp_localize_script as `rd_views_data`:
 *   - ajaxurl: the admin-ajax endpoint
 *   - post_id: current post ID
 *   - nonce:   post-specific security nonce
 *
 * This file is only enqueued on is_singular() for posts/pages with
 * tracking enabled in the panel — admins and known bots never reach here.
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
