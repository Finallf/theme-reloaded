/**
 * assets/js/admin-dashboard-toggle.js
 *
 * Inline Dashboard switches (Wave 11 Category B) — handler for the click
 * on `<button class="rd-pswitch">` to flip features ON/OFF via AJAX,
 * without having to open the panel's specific tab.
 *
 * Expected markup:
 *   <button type="button"
 *           class="rd-pswitch"
 *           role="switch"
 *           aria-checked="true|false"
 *           data-rd-toggle="option_key"
 *           data-rd-nonce="nonce_string"
 *           data-rd-confirm="optional confirm message">
 *     <span class="rd-pswitch__track"></span>
 *     <span class="rd-pswitch__thumb"></span>
 *   </button>
 *
 * AJAX endpoint: rd_dashboard_toggle (in inc/mod-dashboard.php).
 * A server-side whitelist restricts which option keys can be flipped.
 *
 * Loaded ONLY on the Dashboard tab (gated in mod-stats.php → rd_stats_admin_enqueue).
 * Depends on window.ajaxurl (defined by WP in the admin) + window.rdDashboardToggle
 * (localized via wp_localize_script with i18n).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var switches = document.querySelectorAll( '.rd-pswitch[data-rd-toggle]' );
		if ( switches.length === 0 ) {
			return;
		}

		switches.forEach( function ( btn ) {
			btn.addEventListener( 'click', handleClick );
		} );
	} );

	function handleClick( ev ) {
		var btn = ev.currentTarget;

		// Block double-clicks while the AJAX request is in flight.
		if ( btn.classList.contains( 'is-loading' ) ) {
			return;
		}

		var key       = btn.dataset.rdToggle;
		var nonce     = btn.dataset.rdNonce;
		var confirmMsg = btn.dataset.rdConfirm;
		var isOn      = 'true' === btn.getAttribute( 'aria-checked' );
		var newValue  = isOn ? '0' : '1';

		// Optional confirm dialog (Maintenance Mode has one). Only asks for
		// confirmation when turning ON — turning off is safe, no warning needed.
		if ( confirmMsg && '1' === newValue ) {
			if ( ! window.confirm( confirmMsg ) ) {
				return;
			}
		}

		setLoading( btn, true );

		var body = new URLSearchParams();
		body.append( 'action', 'rd_dashboard_toggle' );
		body.append( 'key', key );
		body.append( 'value', newValue );
		body.append( '_wpnonce', nonce );

		fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json().then( function ( body ) {
					return { status: r.status, body: body };
				} );
			} )
			.then( function ( res ) {
				setLoading( btn, false );
				if ( res.body && res.body.ok ) {
					// Success — update the switch visual + sibling badge.
					applyState( btn, '1' === String( res.body.value ) );
				} else {
					setError( btn );
					console.warn( 'rd-dashboard-toggle: server error', res );
				}
			} )
			.catch( function ( err ) {
				setLoading( btn, false );
				setError( btn );
				console.warn( 'rd-dashboard-toggle: network error', err );
			} );
	}

	function setLoading( btn, loading ) {
		if ( loading ) {
			btn.classList.add( 'is-loading' );
			btn.classList.remove( 'is-error' );
		} else {
			btn.classList.remove( 'is-loading' );
		}
	}

	function setError( btn ) {
		btn.classList.add( 'is-error' );
		// Auto-clear the error state after 1.5s so it doesn't get visually stuck.
		setTimeout( function () {
			btn.classList.remove( 'is-error' );
		}, 1500 );
	}

	/**
	 * Updates the switch (aria-checked) AND the sibling badge on the same card-status-line.
	 * No reload — instant UX.
	 */
	function applyState( btn, isOn ) {
		btn.setAttribute( 'aria-checked', isOn ? 'true' : 'false' );

		// Update the tooltip to reflect the next available action.
		// E.g. after turning on, the tooltip becomes "Turn off" (the action available now).
		var nextTooltip = isOn ? btn.dataset.tooltipOn : btn.dataset.tooltipOff;
		if ( nextTooltip ) {
			btn.setAttribute( 'data-tooltip', nextTooltip );
		}

		// The badge is the `<span class="rd-pbadge">` that comes BEFORE the button on
		// the same .rd-dashboard-status-line. Update variant + text to reflect the
		// new state without needing a reload.
		var statusLine = btn.closest( '.rd-dashboard-status-line' );
		if ( ! statusLine ) {
			return;
		}
		var badge = statusLine.querySelector( '.rd-pbadge' );
		if ( ! badge ) {
			return;
		}

		// All toggles use "success" (green) for ON and "neutral" for OFF —
		// visual consistency. Maintenance gets a ⚠️ prefix on the ON text as a
		// visual cue for an "abnormal state" (site locked), without breaking the
		// uniform color rule. The emoji goes in a <span class="rd-pbadge__emoji">
		// for correct vertical alignment (without it the emoji is offset).
		var key = btn.dataset.rdToggle;
		var newVariant = isOn ? 'success' : 'neutral';
		var onHtml = 'maintenance_mode' === key
			? '<span class="rd-pbadge__emoji">⚠️</span>ON'
			: 'ON';

		// Remove old variants + add the new one. innerHTML (not textContent)
		// because the Maintenance case needs the emoji's <span>.
		badge.className = 'rd-pbadge rd-pbadge--' + newVariant;
		badge.innerHTML = isOn ? onHtml : 'OFF';
	}
} )();
