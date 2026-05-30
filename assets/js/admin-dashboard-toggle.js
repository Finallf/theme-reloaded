/**
 * assets/js/admin-dashboard-toggle.js
 *
 * Switches inline do Dashboard (Wave 11 Categoria B) — handler do clique
 * em `<button class="rd-pswitch">` pra flipar features ON/OFF via AJAX,
 * sem precisar abrir a aba específica do painel.
 *
 * Markup esperado:
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
 * AJAX endpoint: rd_dashboard_toggle (em inc/mod-dashboard.php).
 * Whitelist server-side restringe quais option keys podem ser flipadas.
 *
 * Carregado SÓ na aba Dashboard (gate em mod-stats.php → rd_stats_admin_enqueue).
 * Depende de window.ajaxurl (definido pelo WP no admin) + window.rdDashboardToggle
 * (localized via wp_localize_script com i18n).
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

		// Bloqueia clique duplo enquanto AJAX está rolando.
		if ( btn.classList.contains( 'is-loading' ) ) {
			return;
		}

		var key       = btn.dataset.rdToggle;
		var nonce     = btn.dataset.rdNonce;
		var confirmMsg = btn.dataset.rdConfirm;
		var isOn      = 'true' === btn.getAttribute( 'aria-checked' );
		var newValue  = isOn ? '0' : '1';

		// Confirm dialog opcional (Maintenance Mode tem). Só pede confirmação
		// quando vai LIGAR — desligar é seguro, não precisa avisar.
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
					// Sucesso — atualiza visual do switch + badge irmão.
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
		// Auto-remove o estado de erro após 1.5s pra não travar visualmente.
		setTimeout( function () {
			btn.classList.remove( 'is-error' );
		}, 1500 );
	}

	/**
	 * Atualiza o switch (aria-checked) E o badge irmão na mesma card-status-line.
	 * Sem reload — UX instantâneo.
	 */
	function applyState( btn, isOn ) {
		btn.setAttribute( 'aria-checked', isOn ? 'true' : 'false' );

		// Atualiza tooltip pra refletir a próxima ação disponível.
		// Ex: depois de ligar, tooltip vira "Desligar" (a ação disponível agora).
		var nextTooltip = isOn ? btn.dataset.tooltipOn : btn.dataset.tooltipOff;
		if ( nextTooltip ) {
			btn.setAttribute( 'data-tooltip', nextTooltip );
		}

		// Badge é o `<span class="rd-pbadge">` que vem ANTES do botão na mesma
		// linha .rd-dashboard-status-line. Atualiza variant + texto pra refletir
		// o novo estado sem precisar de reload.
		var statusLine = btn.closest( '.rd-dashboard-status-line' );
		if ( ! statusLine ) {
			return;
		}
		var badge = statusLine.querySelector( '.rd-pbadge' );
		if ( ! badge ) {
			return;
		}

		// Todos os toggles usam "success" (verde) pra ON e "neutral" pra OFF —
		// consistência visual. Maintenance ganha prefix ⚠️ no texto ON pra
		// reforço visual de "estado anormal" (site bloqueado), sem quebrar a
		// regra de cor uniforme. Emoji vai num <span class="rd-pbadge__emoji">
		// pra alinhamento vertical correto (sem isso emoji fica deslocado).
		var key = btn.dataset.rdToggle;
		var newVariant = isOn ? 'success' : 'neutral';
		var onHtml = 'maintenance_mode' === key
			? '<span class="rd-pbadge__emoji">⚠️</span>ON'
			: 'ON';

		// Remove variants antigos + adiciona o novo. innerHTML (não textContent)
		// porque o caso do Maintenance precisa do <span> do emoji.
		badge.className = 'rd-pbadge rd-pbadge--' + newVariant;
		badge.innerHTML = isOn ? onHtml : 'OFF';
	}
} )();
