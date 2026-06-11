/**
 * AJAX comment submit — avoids a full page reload.
 *
 * Loaded ONLY on singular posts with comments open (enqueued conditionally in
 * mod-performance.php) — it lived inside the global navigation.js for a long
 * time, shipping dead code to every commentless page.
 *
 * i18n strings come from the shared `reloaded_i18n` object localized on the
 * rd-navigation handle (always enqueued, printed before any deferred script
 * executes).
 *
 * Behavior:
 *   1. Captures the #commentform submit, prevents the native send
 *   2. Sends FormData via fetch to /wp-comments-post.php
 *   3. WP processes (Akismet, validations, save) and returns a 302
 *      redirect (success) or 200 with error HTML
 *   4. Success → shows feedback + soft reload to display the
 *      just-created comment (with the correct position/anchor)
 *   5. Error → shows the extracted message + form stays editable
 */
document.addEventListener('DOMContentLoaded', function () {

	// Localized i18n strings (reloaded_i18n), with en-US fallbacks if absent
	var t = (typeof reloaded_i18n !== 'undefined') ? reloaded_i18n : {};

	const commentForm = document.getElementById('commentform');
	if (!commentForm) {
		return;
	}

	const submitBtn = commentForm.querySelector('.submit, input[type="submit"], button[type="submit"]');
	const formActions = commentForm.querySelector('.form-submit') || commentForm;

	// Helper: creates/updates the feedback block
	function showFeedback(type, message) {
		let box = commentForm.querySelector('.rd-comment-feedback');
		if (!box) {
			box = document.createElement('div');
			box.className = 'rd-comment-feedback';
			formActions.appendChild(box);
		}
		box.className = 'rd-comment-feedback is-' + type;
		box.textContent = message;
	}

	commentForm.addEventListener('submit', async function (e) {
		// Only act if the form is still the native WP one — if another plugin
		// is already controlling it (Discourse, Disqus, etc.), don't interfere.
		if (!commentForm.action || commentForm.action.indexOf('wp-comments-post.php') === -1) {
			return;
		}

		e.preventDefault();

		const originalLabel = submitBtn ? (submitBtn.value || submitBtn.textContent) : '';
		if (submitBtn) {
			submitBtn.disabled = true;
			var sendingLabel = t.comment_sending || 'Sending…';
			if ('value' in submitBtn) submitBtn.value = sendingLabel;
			else submitBtn.textContent = sendingLabel;
		}

		try {
			// `redirect: 'manual'` — we do NOT follow the 302 automatically.
			// When WP returns a 302 (= comment created successfully), the fetch
			// returns response.type === 'opaqueredirect'. If we follow
			// (redirect: 'follow'), the #comment-XXX fragment from the Location
			// header is lost (HTTP doesn't send fragments), and it becomes impossible
			// to tell success apart from any 200 that comes back.
			const response = await fetch(commentForm.action, {
				method: 'POST',
				body: new FormData(commentForm),
				credentials: 'same-origin',
				redirect: 'manual',
			});

			// Redirect = comment created. Reload the current page so the user
			// sees the comment in the list (the page already has it in the DB).
			if (response.type === 'opaqueredirect') {
				showFeedback('success', t.comment_sent || 'Comment sent! Loading…');
				setTimeout(function () { window.location.reload(); }, 600);
				return;
			}

			// Not a redirect → WP returned an error (400/403/500) or wp_die.
			// Try to extract the message from the #error-page wrapper or .wp-die-message.
			const html = await response.text();

			let errMsg = '';
			const dieMatch = html.match(/<div[^>]*id=["']error-page["'][^>]*>([\s\S]*?)<\/div>\s*<\/body>/i)
				|| html.match(/<div[^>]*class=["'][^"']*wp-die-message[^"']*["'][^>]*>([\s\S]*?)<\/div>/i);

			if (dieMatch) {
				const pMatch = dieMatch[1].match(/<p[^>]*>([\s\S]*?)<\/p>/i);
				if (pMatch) errMsg = pMatch[1].replace(/<[^>]+>/g, '').trim();
			}

			if (!errMsg) {
				errMsg = t.comment_error || 'Unexpected error submitting the comment. Reload the page and try again.';
			}

			// Reaching here means a real error (200 + wp_die HTML). A successful
			// comment — including one held for moderation — comes back as a 302
			// redirect, handled by the opaqueredirect branch above, so it never
			// lands here. No locale-specific moderation string matching needed.
			showFeedback('error', errMsg);
		} catch (err) {
			showFeedback('error', (t.network_error || 'Network error: ') + err.message);
		} finally {
			if (submitBtn) {
				submitBtn.disabled = false;
				if ('value' in submitBtn) submitBtn.value = originalLabel;
				else submitBtn.textContent = originalLabel;
			}
		}
	});
});
