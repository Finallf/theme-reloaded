/**
 * Image Formats — Regeneration UI
 *
 * Handler for the "Start regeneration" button on the panel's Performance tab.
 * AJAX loop in chunks (10 attachments per request) with a progress bar.
 *
 * Data localized by wp_localize_script('rd_img_regen', ...):
 *   - ajaxurl
 *   - i18n: translated strings for the labels
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const btn      = document.getElementById('rd-img-regen-start');
        const progress = document.getElementById('rd-img-regen-progress');
        const bar      = document.getElementById('rd-img-regen-bar');
        const status   = document.getElementById('rd-img-regen-status');

        if (!btn || typeof rd_img_regen === 'undefined') return;

        const t = rd_img_regen.i18n || {};
        const ajaxurl = rd_img_regen.ajaxurl;

        btn.addEventListener('click', function () {
            const nonce = btn.getAttribute('data-nonce');
            const total = parseInt(btn.getAttribute('data-total'), 10) || 0;

            if (total === 0) {
                alert(t.no_images || 'No JPEG/PNG attachments to process.');
                return;
            }

            if (!confirm((t.confirm || 'Start processing %d images? This may take several minutes.').replace('%d', total))) {
                return;
            }

            btn.disabled = true;
            progress.hidden = false;
            bar.value = 0;
            status.textContent = (t.starting || 'Starting...');

            processChunk(0);

            function processChunk(offset) {
                const body = new URLSearchParams({
                    action: 'rd_img_regenerate',
                    nonce:  nonce,
                    offset: offset
                });

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        throw new Error((data && data.data) || 'unknown_error');
                    }

                    const processed = data.data.processed;
                    const totalRet  = data.data.total;
                    const pct       = totalRet > 0 ? Math.round((processed / totalRet) * 100) : 100;

                    bar.value = pct;
                    status.textContent = (t.progress || 'Processed %p of %t (%pct%)')
                        .replace('%p',   processed)
                        .replace('%t',   totalRet)
                        .replace('%pct', pct);

                    if (data.data.done) {
                        status.textContent = (t.done || 'Done! Processed %t images.').replace('%t', totalRet);
                        btn.disabled = false;
                        return;
                    }

                    // Next chunk
                    processChunk(processed);
                })
                .catch(function (err) {
                    status.textContent = (t.error || 'Error: ') + err.message;
                    btn.disabled = false;
                });
            }
        });
    });
})();
