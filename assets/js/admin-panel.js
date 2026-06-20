/**
 * assets/js/admin-panel.js
 *
 * Consolidated admin-panel JavaScript (ReloadeD).
 * Single bundle enqueued on the rd_options panel (any tab) as `rd-admin-panel`.
 * Each module below is self-contained (its own scope) and self-guards: it bails
 * early if its DOM elements / localized object aren't present, so code for one
 * tab stays inert on the others.
 *
 * Merged modules (formerly separate files):
 *   - admin-scripts.js          (media upload buttons — jQuery)
 *   - admin-stats.js            (Statistics tab: K4 monthly chart)
 *   - admin-charts.js           (Dashboard/Security: generic Chart.js auto-render)
 *   - admin-dashboard-toggle.js (Dashboard: inline feature switches)
 *   - admin-self-update.js      (Dashboard: theme self-update check)
 *   - admin-backup.js           (Maintenance/Backup: import/export/restore)
 *   - admin-img-regen.js        (Images & Media: WebP/AVIF regeneration)
 *
 * Chart.js (lib/chartjs.min.js) stays a separate, tab-gated enqueue; the chart
 * modules guard on `typeof window.Chart` so they are safe when it is not loaded.
 */


/* ===================================================================== *
 * admin-scripts.js — media upload buttons — jQuery
 * ===================================================================== */

jQuery(document).ready(function($){
    // Localized i18n strings (rdAdminScripts.i18n), with en-US fallbacks if absent
    var rdI18n = (window.rdAdminScripts && window.rdAdminScripts.i18n) || {};

    // .on('click', ...) instead of .click(fn) — the shorthand was removed in jQuery 4
    // (jquery-migrate only warns for now, but it'll break once WP updates)
    $('.rd-upload-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var input_id = button.data('input-id');

        var custom_uploader = wp.media({
            title: rdI18n.selectImage || 'Select fallback image',
            button: { text: rdI18n.useImage || 'Use this image' },
            multiple: false
        }).on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $('#' + input_id).val(attachment.url); // Store the URL in the input
            $('#' + input_id + '_preview').html('<img src="'+attachment.url+'" style="max-width:200px; height:auto; border:1px solid #ccc; display:block;">');
            button.siblings('.rd-remove-button').show();
        }).open();
    });

    $('.rd-remove-button').on('click', function() {
        var input_id = $(this).data('input-id');
        $('#' + input_id).val('');
        $('#' + input_id + '_preview').html('');
        $(this).hide();
    });
});


/* ===================================================================== *
 * admin-stats.js — Statistics tab: K4 monthly chart
 * ===================================================================== */

/**
 * assets/js/admin-stats.js
 *
 * Initializes the monthly-growth chart (K4) on the panel's Statistics tab.
 * Loaded ONLY in the admin/rd_options/tab=estatisticas context — see the
 * enqueue in inc/mod-stats.php → rd_stats_admin_enqueue().
 *
 * Data (labels + values) comes via the <canvas> data-attributes, populated
 * by PHP in rd_stats_render_dashboard(). Keeps zero inline JS.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var canvas = document.getElementById( 'rd-stats-monthly-chart' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		// Defensive parse of the data-attributes (JSON.parse can throw if malformed)
		var labels, values, labelViews;
		try {
			labels     = JSON.parse( canvas.dataset.labels  || '[]' );
			values     = JSON.parse( canvas.dataset.values  || '[]' );
			labelViews = canvas.dataset.labelViews || 'Views';
		} catch ( err ) {
			console.warn( 'rd-stats: invalid chart data', err );
			return;
		}

		if ( labels.length === 0 || values.length === 0 ) {
			return;
		}

		new window.Chart( canvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [ {
					label: labelViews,
					data: values,
					backgroundColor: 'rgba(34, 113, 177, 0.7)', // WP blue with transparency
					borderColor: 'rgba(34, 113, 177, 1)',
					borderWidth: 1,
					borderRadius: 4,
					hoverBackgroundColor: 'rgba(34, 113, 177, 0.9)',
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false }, // single series, the dataset label is enough
					tooltip: {
						backgroundColor: 'rgba(29, 35, 39, 0.95)',
						padding: 10,
						titleFont: { weight: 'bold' },
						bodyFont: { size: 13 },
						displayColors: false,
						callbacks: {
							// Format the value with a thousands separator (locale-aware)
							label: function ( context ) {
								return labelViews + ': ' + context.parsed.y.toLocaleString();
							},
						},
					},
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							color: '#50575e',
							callback: function ( value ) {
								return value.toLocaleString();
							},
						},
						grid: { color: '#f0f0f1' },
					},
					x: {
						ticks: { color: '#50575e' },
						grid: { display: false },
					},
				},
			},
		} );
	} );

} )();


/* ===================================================================== *
 * admin-charts.js — Dashboard/Security: generic Chart.js auto-render
 * ===================================================================== */

/**
 * assets/js/admin-charts.js
 *
 * Generic Chart.js auto-render in the ReloadeD admin (Wave 11 Phase G).
 *
 * Detects every <canvas data-rd-chart-type="..."> on the page and
 * initializes Chart.js automatically. Supported types:
 *   - doughnut : labels + values, 1 dataset
 *   - bar      : labels + values, 1 dataset (WP blue)
 *
 * data-attribute convention:
 *   data-rd-chart-type    : chart type ('doughnut' | 'bar')
 *   data-labels           : JSON array of labels (strings)
 *   data-values           : JSON array of values (numbers)
 *   data-label            : dataset label (text, e.g. "Views")
 *
 * Data (labels + values) comes via the <canvas> data-attributes, populated
 * by PHP in the rendering callback. Keeps zero inline JS — compatible
 * with nonce-based CSP (Wave 8.5).
 *
 * Loaded ONLY on the Dashboard and Security tabs (gated in mod-stats.php
 * → rd_stats_admin_enqueue).
 */
( function () {
	'use strict';

	/**
	 * Default palette for the doughnut chart — native WP admin colors +
	 * complementary tones to tell apart up to ~10 categories.
	 */
	var DOUGHNUT_PALETTE = [
		'rgba(34, 113, 177, 0.85)',  // WP blue
		'rgba(214, 54, 56, 0.85)',   // danger red
		'rgba(210, 153, 34, 0.85)',  // amber warning
		'rgba(0, 122, 57, 0.85)',    // success green
		'rgba(135, 60, 190, 0.85)',  // purple
		'rgba(0, 160, 175, 0.85)',   // cyan
		'rgba(230, 120, 60, 0.85)',  // orange
		'rgba(80, 87, 94, 0.85)',    // WP gray
	];

	/**
	 * Initializes a doughnut chart on a canvas.
	 */
	function initDoughnut( canvas, labels, values ) {
		new window.Chart( canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [ {
					data: values,
					backgroundColor: labels.map( function ( _, i ) {
						return DOUGHNUT_PALETTE[ i % DOUGHNUT_PALETTE.length ];
					} ),
					borderColor: '#fff',
					borderWidth: 2,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						// 'bottom' so the doughnut fits a narrow column (CSP reports
						// sidebar + the Dashboard summary card) without the legend
						// squishing the chart horizontally.
						position: 'bottom',
						labels: {
							font: { size: 12 },
							padding: 12,
							boxWidth: 14,
						},
					},
					tooltip: {
						callbacks: {
							label: function ( ctx ) {
								// "directive: 12 (45%)" — shows value + percentage.
								var total = ctx.dataset.data.reduce( function ( a, b ) { return a + b; }, 0 );
								var pct   = total > 0 ? Math.round( ( ctx.parsed / total ) * 100 ) : 0;
								return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
							},
						},
					},
				},
			},
		} );
	}

	/**
	 * Initializes a bar chart on a canvas (WP blue, similar to mod-stats' K4).
	 */
	function initBar( canvas, labels, values, labelText ) {
		new window.Chart( canvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [ {
					label: labelText || 'Value',
					data: values,
					backgroundColor: 'rgba(34, 113, 177, 0.7)',
					borderColor: 'rgba(34, 113, 177, 1)',
					borderWidth: 1,
					borderRadius: 4,
					hoverBackgroundColor: 'rgba(34, 113, 177, 0.9)',
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false }, // single dataset — legend is redundant.
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }, // no decimals (views are integers).
					},
				},
			},
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof window.Chart === 'undefined' ) {
			return;
		}

		var canvases = document.querySelectorAll( 'canvas[data-rd-chart-type]' );
		canvases.forEach( function ( canvas ) {
			var type = canvas.dataset.rdChartType;
			var labels, values;

			try {
				labels = JSON.parse( canvas.dataset.labels || '[]' );
				values = JSON.parse( canvas.dataset.values || '[]' );
			} catch ( err ) {
				console.warn( 'rd-admin-charts: invalid data on canvas', canvas.id, err );
				return;
			}

			if ( labels.length === 0 || values.length === 0 ) {
				return;
			}

			switch ( type ) {
				case 'doughnut':
					initDoughnut( canvas, labels, values );
					break;
				case 'bar':
					initBar( canvas, labels, values, canvas.dataset.label );
					break;
				default:
					console.warn( 'rd-admin-charts: unknown chart type "' + type + '"' );
			}
		} );
	} );
} )();


/* ===================================================================== *
 * admin-dashboard-toggle.js — Dashboard: inline feature switches
 * ===================================================================== */

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

					// Beta-channel flip (Theme Updates card): the cached release
					// belongs to the previous channel — chain an immediate
					// "Check for updates" so the card reflects the new channel,
					// and sync the BETA badge next to "Latest version".
					if ( 'update_beta_channel' === key ) {
						var channelBadge = document.getElementById( 'rd-self-update-channel-badge' );
						if ( channelBadge ) {
							channelBadge.hidden = '1' !== String( res.body.value );
						}
						var checkBtn = document.getElementById( 'rd-self-update-check' );
						if ( checkBtn ) {
							checkBtn.click();
						}
					}
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


/* ===================================================================== *
 * admin-self-update.js — Dashboard: theme self-update check
 * ===================================================================== */

/**
 * assets/js/admin-self-update.js
 *
 * Handler for the "Check for updates" button on the Dashboard's Theme Updates card.
 * AJAX: action=rd_check_update (in inc/mod-self-update.php).
 *
 * Updates inline (no reload):
 *   #rd-self-update-latest      ← remote version
 *   #rd-self-update-status      ← badge (Up to date / Update available)
 *   #rd-self-update-last-check  ← "just now"
 *   #rd-self-update-action      ← injects the CTA when an update is available
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
				// One-click update: core update.php URL (nonce'd server-side in
				// the rdSelfUpdate localize) — runs the upgrader immediately.
				var updateUrl  = ( window.rdSelfUpdate && window.rdSelfUpdate.update_url ) || '/wp-admin/themes.php';
				var goLabel    = i18n.update_now || 'Update now';
				var viewLabel  = i18n.view_release || 'View release on GitHub';
				var releaseUrl = data.release_url || '';
				var html       = '<a class="button button-primary rd-update-now" href="' + escapeAttr( updateUrl ) + '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' + escapeHtml( goLabel ) + '</a>';
				if ( releaseUrl ) {
					html += ' <a class="button-link" href="' + escapeAttr( releaseUrl ) + '" target="_blank" rel="noopener">' + escapeHtml( viewLabel ) + '</a>';
				}
				actionEl.innerHTML = html;
			} else {
				actionEl.innerHTML = '';
			}
		}
	}

	// "Update now" feedback: between the click and update.php painting its
	// progress screen there's a multi-second gap (the server is fetching the
	// release zip) where the only hint is the browser tab spinner — easy to
	// miss and click again. Spin the icon + lock the link until navigation.
	// Delegated on document: the CTA exists in two flavors (PHP-rendered on
	// page load, JS-injected after a check) and may be replaced at any time.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target && e.target.closest ? e.target.closest( '.rd-update-now' ) : null;
		if ( ! link ) {
			return;
		}
		if ( link.classList.contains( 'is-loading' ) ) {
			e.preventDefault(); // already navigating — swallow double clicks
			return;
		}
		link.classList.add( 'is-loading' );
		link.setAttribute( 'aria-busy', 'true' );
	} );

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


/* ===================================================================== *
 * admin-backup-installer.js — Backup tab: install the ReloadeD Backup plugin
 * ===================================================================== */

/**
 * One button on the Backup tab: installs the latest STABLE ReloadeD Backup
 * release from GitHub (server-side Plugin_Upgrader) and auto-activates it,
 * then redirects to the plugin's page. No external dependencies.
 *
 * Data localized by wp_localize_script( 'rd-admin-panel', 'rdBackupInstaller', … ):
 *   - ajaxurl, nonce, i18n
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.getElementById( 'rd-backup-install' );
		if ( ! btn || typeof window.rdBackupInstaller === 'undefined' ) return;

		var status = document.getElementById( 'rd-backup-install-status' );
		var t      = window.rdBackupInstaller.i18n || {};

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			if ( status ) {
				status.className   = 'rd-backup-install-status';
				status.textContent = t.installing || 'Installing & activating…';
			}

			var body = new URLSearchParams( {
				action: 'rd_backup_install',
				nonce:  btn.getAttribute( 'data-nonce' )
			} );

			fetch( window.rdBackupInstaller.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
				credentials: 'same-origin'
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.success ) {
					throw new Error( ( data && data.data ) || 'unknown_error' );
				}
				window.location.href = data.data.redirect;
			} )
			.catch( function ( err ) {
				if ( status ) {
					status.className   = 'rd-backup-install-status rd-backup-install-status--error';
					status.textContent = ( t.failed || 'Install failed:' ) + ' ' + String( err.message || err );
				}
				btn.disabled = false;
			} );
		} );
	} );

} )();


/* ===================================================================== *
 * admin-img-regen.js — Images & Media: WebP/AVIF regeneration
 * ===================================================================== */

/**
 * Image Formats — Regeneration + format-cleanup UI
 *
 * Handler for the "Start regeneration" button on the panel's Performance tab.
 * AJAX loop in time-budgeted chunks (server stops early when the PHP time
 * budget is spent and reports how far it got) with a progress bar. Also the
 * "Remove unused format" button (single-shot AJAX).
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
                        let doneMsg = (t.done || 'Done! Processed %t images.').replace('%t', totalRet);
                        if (data.data.failed > 0) {
                            doneMsg += ' ' + (t.failures || '%f conversions failed.').replace('%f', data.data.failed);
                        }
                        status.textContent = doneMsg;
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

        // --- Cleanup of unused next-gen formats (mode-switch leftovers) ---
        const cleanupBtn    = document.getElementById('rd-img-cleanup-start');
        const cleanupStatus = document.getElementById('rd-img-cleanup-status');

        if (cleanupBtn && cleanupStatus) {
            cleanupBtn.addEventListener('click', function () {
                if (!confirm(t.cleanup_confirm || 'Delete all files of the unused format?')) {
                    return;
                }

                cleanupBtn.disabled = true;
                cleanupStatus.textContent = (t.cleanup_busy || 'Cleaning…');

                const body = new URLSearchParams({
                    action: 'rd_img_cleanup_formats',
                    nonce:  cleanupBtn.getAttribute('data-nonce')
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
                    cleanupStatus.textContent = (t.cleanup_done || 'Removed %1$d files (%2$s KB freed).')
                        .replace('%1$d', data.data.deleted)
                        .replace('%2$s', data.data.freed_kb);
                    cleanupBtn.disabled = false;
                })
                .catch(function (err) {
                    cleanupStatus.textContent = (t.error || 'Error: ') + err.message;
                    cleanupBtn.disabled = false;
                });
            });
        }
    });
})();
