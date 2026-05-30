/**
 * assets/js/admin-self-update.js
 *
 * Handler do botão "Check for updates" no card Theme Updates do Dashboard.
 * AJAX: action=rd_check_update (em inc/mod-self-update.php).
 *
 * Atualiza inline (sem reload):
 *   #rd-self-update-latest      ← versão remota
 *   #rd-self-update-status      ← badge (Up to date / Update available)
 *   #rd-self-update-last-check  ← "just now"
 *   #rd-self-update-action      ← injeta CTA quando há update disponível
 *
 * Localized data:
 *   window.rdSelfUpdate = {
 *     i18n: {
 *       checking, just_now, up_to_date, update_available,
 *       go_to_themes, view_release, network_error
 *     },
 *     themes_url: '/wp-admin/themes.php'
 *   }
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.getElementById( 'rd-self-update-check' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', handleClick );
	} );

	function handleClick( ev ) {
		var btn = ev.currentTarget;
		if ( btn.classList.contains( 'is-loading' ) ) {
			return;
		}

		var nonce = btn.dataset.nonce;
		var i18n  = ( window.rdSelfUpdate && window.rdSelfUpdate.i18n ) || {};

		setLoading( btn, true, i18n.checking );

		var body = new URLSearchParams();
		body.append( 'action', 'rd_check_update' );
		body.append( '_wpnonce', nonce );

		fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				setLoading( btn, false );
				if ( res && res.success && res.data ) {
					applyState( res.data, i18n );
				} else {
					applyError( i18n.network_error || 'Error' );
				}
			} )
			.catch( function () {
				setLoading( btn, false );
				applyError( i18n.network_error || 'Error' );
			} );
	}

	function setLoading( btn, loading, label ) {
		if ( loading ) {
			btn.classList.add( 'is-loading' );
			btn.disabled = true;
			if ( label ) {
				btn.dataset.originalLabel = btn.textContent.trim();
				btn.innerHTML = '<span class="dashicons dashicons-update" aria-hidden="true"></span> ' + label;
			}
		} else {
			btn.classList.remove( 'is-loading' );
			btn.disabled = false;
			if ( btn.dataset.originalLabel ) {
				btn.innerHTML = '<span class="dashicons dashicons-update" aria-hidden="true"></span> ' + btn.dataset.originalLabel;
				delete btn.dataset.originalLabel;
			}
		}
	}

	function applyState( data, i18n ) {
		var latestEl    = document.getElementById( 'rd-self-update-latest' );
		var statusEl    = document.getElementById( 'rd-self-update-status' );
		var lastCheckEl = document.getElementById( 'rd-self-update-last-check' );
		var actionEl    = document.getElementById( 'rd-self-update-action' );

		if ( latestEl ) {
			latestEl.textContent = data.latest || '—';
		}
		if ( statusEl ) {
			var variant = data.has_update ? 'warning' : 'success';
			var text    = data.status || ( data.has_update ? i18n.update_available : i18n.up_to_date );
			statusEl.innerHTML = '<span class="rd-pbadge rd-pbadge--' + variant + '">' + escapeHtml( text ) + '</span>';
		}
		if ( lastCheckEl ) {
			lastCheckEl.textContent = data.last_check_human || i18n.just_now || 'just now';
		}
		if ( actionEl ) {
			if ( data.has_update ) {
				var themesUrl  = ( window.rdSelfUpdate && window.rdSelfUpdate.themes_url ) || '/wp-admin/themes.php';
				var goLabel    = i18n.go_to_themes || 'Go to Themes → Update Now';
				var viewLabel  = i18n.view_release || 'View release on GitHub';
				var releaseUrl = data.release_url || '';
				var html       = '<a class="button button-primary" href="' + escapeAttr( themesUrl ) + '">' + escapeHtml( goLabel ) + '</a>';
				if ( releaseUrl ) {
					html += ' <a class="button-link" href="' + escapeAttr( releaseUrl ) + '" target="_blank" rel="noopener">' + escapeHtml( viewLabel ) + '</a>';
				}
				actionEl.innerHTML = html;
			} else {
				actionEl.innerHTML = '';
			}
		}
	}

	function applyError( message ) {
		var statusEl = document.getElementById( 'rd-self-update-status' );
		if ( statusEl ) {
			statusEl.innerHTML = '<span class="rd-pbadge rd-pbadge--danger">' + escapeHtml( message ) + '</span>';
		}
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str );
		return div.innerHTML;
	}

	function escapeAttr( str ) {
		return String( str ).replace( /"/g, '&quot;' );
	}
} )();
