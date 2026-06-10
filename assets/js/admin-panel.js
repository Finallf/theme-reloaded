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


/* ===================================================================== *
 * admin-backup.js — Maintenance/Backup: import/export/restore
 * ===================================================================== */

/**
 * assets/js/admin-backup.js
 *
 * Coordinates the import flow in the Backup sub-section of the panel's Maintenance tab:
 *   1. User picks a JSON file
 *   2. JS reads it via FileReader and POSTs to /wp-json/rd/v1/backup/preview
 *   3. Backend validates + computes the diff → JS renders the preview
 *   4. User clicks "Apply import" → POST to /wp-json/rd/v1/backup/import
 *   5. Backend saves a snapshot and applies it → JS reloads the page
 *
 * Loaded ONLY on the Maintenance tab via rd_backup_admin_enqueue() in inc/mod-backup.php.
 * No external dependencies (vanilla JS).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Bail early if the rdBackup object doesn't exist (we only load on the Maintenance tab)
		if ( typeof window.rdBackup === 'undefined' ) return;

		// ============= EXPORT: update the link href based on the checkboxes =============
		// We use an <a> link instead of a form/button to avoid clashing with the parent
		// form (the section callback runs INSIDE the panel's <form action="options.php"> —
		// nested forms submit to the wrong form). This handler rebuilds the URL from
		// SCRATCH out of the link's data-attrs (base, action, nonce) and appends
		// sections[] based on the checkboxes — avoids accumulating stale params.
		var exportLink     = document.getElementById( 'rd-backup-export-link' );
		var exportCheckbox = document.querySelectorAll( '.rd-backup-export-cb' );

		function updateExportLink() {
			if ( ! exportLink ) return;

			// Rebuild from scratch: clean base URL + action + nonce
			var url = new URL( exportLink.dataset.baseUrl, window.location.origin );
			url.searchParams.set( 'action',   exportLink.dataset.action );
			url.searchParams.set( '_wpnonce', exportLink.dataset.nonce );

			// Append sections[] from the checked checkboxes
			exportCheckbox.forEach( function ( cb ) {
				if ( cb.checked ) {
					url.searchParams.append( 'sections[]', cb.value );
				}
			} );

			exportLink.href = url.toString();
		}

		exportCheckbox.forEach( function ( cb ) {
			cb.addEventListener( 'change', updateExportLink );
		} );

		// Run once on load to normalize the URL (in case PHP generated sections[0]/[1])
		updateExportLink();

		// ============= IMPORT =============
		var fileInput    = document.getElementById( 'rd-backup-file' );
		var filenameEl   = document.getElementById( 'rd-backup-filename' );
		var previewEl    = document.getElementById( 'rd-backup-preview' );
		var statusEl     = document.getElementById( 'rd-backup-status' );

		if ( ! fileInput ) return;

		// In-memory state of the pending JSON — used by the Apply button
		var pendingData = null;

		// ============= UI helpers =============

		// Mapping callsite -> CSS variant: callsites use 'info'/'success'/'error'
		// (historical semantics); the rd-pstatus design-system CSS uses 'danger'
		// instead of 'error'. We map here so we don't have to touch the callers.
		function setStatus( type, message ) {
			var variant = ( 'error' === type ) ? 'danger' : type;
			statusEl.hidden = false;
			statusEl.className = 'rd-pstatus rd-pstatus--' + variant;
			statusEl.textContent = message;
		}

		function clearStatus() {
			statusEl.hidden = true;
			statusEl.textContent = '';
		}

		function clearPreview() {
			previewEl.hidden = true;
			previewEl.innerHTML = '';
			pendingData = null;
		}

		// Escape HTML for safe insertion (defense in depth — backend already sanitizes)
		function esc( str ) {
			var d = document.createElement( 'div' );
			d.textContent = String( str );
			return d.innerHTML;
		}

		// sprintf-style %d/%s substitution for i18n strings with placeholders.
		// Needed because translations (e.g. pt-BR) may reorder the position of the
		// number/value in the sentence — you can't concatenate literally in JS.
		function fmt( template, value ) {
			return String( template ).replace( /%[ds]/, value );
		}

		// Shortcut to the translated-strings object
		var i18n = window.rdBackup.i18n;

		// ============= File selection =============

		fileInput.addEventListener( 'change', function () {
			var file = fileInput.files && fileInput.files[ 0 ];
			if ( ! file ) return;

			filenameEl.textContent = file.name;
			clearPreview();
			setStatus( 'info', rdBackup.i18n.reading );

			var reader = new FileReader();
			reader.onload = function ( e ) {
				var parsed;
				try {
					parsed = JSON.parse( e.target.result );
				} catch ( err ) {
					setStatus( 'error', rdBackup.i18n.invalidJson );
					return;
				}
				requestPreview( parsed );
			};
			reader.onerror = function () {
				setStatus( 'error', rdBackup.i18n.invalidJson );
			};
			reader.readAsText( file );
		} );

		// ============= REST: preview =============

		function requestPreview( data ) {
			setStatus( 'info', rdBackup.i18n.previewing );

			fetch( rdBackup.restUrl + 'preview', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': rdBackup.nonce,
				},
				body: JSON.stringify( data ),
			} )
			.then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
			.then( function ( res ) {
				if ( ! res.body.ok ) {
					setStatus( 'error', res.body.error || ( 'HTTP ' + res.status ) );
					return;
				}
				pendingData = data;
				renderPreview( res.body.meta, res.body.diff );
				clearStatus();
			} )
			.catch( function ( err ) {
				setStatus( 'error', String( err ) );
			} );
		}

		// ============= Preview render =============

		function renderPreview( meta, diff ) {
			var html = '<h4 class="rd-backup-preview__title">' + esc( i18n.previewTitle ) + '</h4>';

			// Meta info
			var themeVerLabel = fmt( i18n.previewThemeVersion, meta.theme_version || '?' );
			html += '<div class="rd-backup-preview__meta">';
			html += '<small>' + esc( i18n.previewExportedFrom + ' ' + ( meta.exported_from || '?' ) + ' • ' + ( meta.exported_at || '?' ) + ' • ' + themeVerLabel ) + '</small>';
			html += '</div>';

			// Settings diff
			if ( diff.settings ) {
				var s = diff.settings;
				html += '<div class="rd-backup-diff-section">';
				html += '<h5>' + esc( i18n.sectionSettings ) + '</h5>';
				html += '<ul class="rd-backup-diff-list">';
				html += '<li class="rd-backup-diff--update">' + esc( '✎ ' + fmt( i18n.diffUpdate, Object.keys( s.will_update ).length ) ) + '</li>';
				html += '<li class="rd-backup-diff--add">'    + esc( '+ ' + fmt( i18n.diffAdd,    Object.keys( s.will_add ).length ) )    + '</li>';
				html += '<li class="rd-backup-diff--keep">'   + esc( '= ' + fmt( i18n.diffKeepSettings, s.will_keep.length ) )           + '</li>';
				html += '</ul>';

				if ( Object.keys( s.will_update ).length > 0 ) {
					html += '<details class="rd-backup-diff-details"><summary>' + esc( i18n.showChangedKeys ) + '</summary>';
					html += '<ul class="rd-backup-diff-keys">';
					for ( var k in s.will_update ) {
						html += '<li><code>' + esc( k ) + '</code> <small>' + esc( JSON.stringify( s.will_update[ k ].from ) + ' → ' + JSON.stringify( s.will_update[ k ].to ) ) + '</small></li>';
					}
					html += '</ul></details>';
				}
				html += '</div>';
			}

			// Ad banners diff (same structure as settings)
			if ( diff.ad_banners ) {
				var a = diff.ad_banners;
				html += '<div class="rd-backup-diff-section">';
				html += '<h5>' + esc( i18n.sectionAdBanners ) + '</h5>';
				html += '<ul class="rd-backup-diff-list">';
				html += '<li class="rd-backup-diff--update">' + esc( '✎ ' + fmt( i18n.diffUpdate,  Object.keys( a.will_update ).length ) ) + '</li>';
				html += '<li class="rd-backup-diff--add">'    + esc( '+ ' + fmt( i18n.diffAdd,     Object.keys( a.will_add ).length ) )    + '</li>';
				html += '<li class="rd-backup-diff--keep">'   + esc( '= ' + fmt( i18n.diffKeepAds, a.will_keep.length ) )                  + '</li>';
				html += '</ul>';
				html += '</div>';
			}

			// Category colors diff
			if ( diff.category_colors ) {
				var c = diff.category_colors;
				html += '<div class="rd-backup-diff-section">';
				html += '<h5>' + esc( i18n.sectionCategoryColors ) + '</h5>';
				html += '<ul class="rd-backup-diff-list">';
				html += '<li class="rd-backup-diff--update">' + esc( '✎ ' + fmt( i18n.diffUpdate, c.will_update.length ) ) + '</li>';
				html += '<li class="rd-backup-diff--add">'    + esc( '+ ' + fmt( i18n.diffAdd,    c.will_add.length ) )    + '</li>';
				if ( c.skipped_no_match.length > 0 ) {
					html += '<li class="rd-backup-diff--skip">' + esc( '⊘ ' + fmt( i18n.diffSkipped, c.skipped_no_match.length ) ) + '</li>';
				}
				html += '</ul>';
				html += '</div>';
			}

			html += '<p class="rd-backup-preview__actions">';
			html += '<button type="button" id="rd-backup-apply" class="button button-primary">';
			html += '<span class="dashicons dashicons-yes"></span>';
			html += esc( i18n.applyImport );
			html += '</button> ';
			html += '<button type="button" id="rd-backup-cancel" class="button">' + esc( i18n.cancel ) + '</button>';
			html += '</p>';

			previewEl.innerHTML = html;
			previewEl.hidden = false;

			document.getElementById( 'rd-backup-apply' ).addEventListener( 'click', applyImport );
			document.getElementById( 'rd-backup-cancel' ).addEventListener( 'click', function () {
				clearPreview();
				fileInput.value = '';
				filenameEl.textContent = '';
				clearStatus();
			} );
		}

		// ============= REST: import =============

		function applyImport() {
			if ( ! pendingData ) return;
			if ( ! window.confirm( rdBackup.i18n.confirmApply ) ) return;

			setStatus( 'info', rdBackup.i18n.importing );

			fetch( rdBackup.restUrl + 'import', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': rdBackup.nonce,
				},
				body: JSON.stringify( pendingData ),
			} )
			.then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
			.then( function ( res ) {
				if ( ! res.body.ok ) {
					setStatus( 'error', rdBackup.i18n.importFailed + ' ' + ( res.body.error || ( 'HTTP ' + res.status ) ) );
					return;
				}
				setStatus( 'success', rdBackup.i18n.importSuccess );
				// Reload to show the applied settings
				setTimeout( function () { window.location.reload(); }, 1200 );
			} )
			.catch( function ( err ) {
				setStatus( 'error', rdBackup.i18n.importFailed + ' ' + String( err ) );
			} );
		}

		// ============= RESTORE: rollback of the last import =============
		var restoreBtn    = document.getElementById( 'rd-backup-restore-btn' );
		var restoreStatus = document.getElementById( 'rd-backup-restore-status' );

		if ( restoreBtn ) {
			restoreBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( rdBackup.i18n.confirmRestore ) ) return;

				restoreStatus.hidden = false;
				restoreStatus.className = 'rd-pstatus rd-pstatus--info';
				restoreStatus.textContent = rdBackup.i18n.restoring;

				fetch( rdBackup.restUrl + 'restore', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': rdBackup.nonce,
					},
				} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
				.then( function ( res ) {
					if ( ! res.body.ok ) {
						restoreStatus.className = 'rd-pstatus rd-pstatus--danger';
						restoreStatus.textContent = rdBackup.i18n.restoreFailed + ' ' + ( res.body.error || ( 'HTTP ' + res.status ) );
						return;
					}
					restoreStatus.className = 'rd-pstatus rd-pstatus--success';
					restoreStatus.textContent = rdBackup.i18n.restoreSuccess;
					setTimeout( function () { window.location.reload(); }, 1200 );
				} )
				.catch( function ( err ) {
					restoreStatus.className = 'rd-pstatus rd-pstatus--danger';
					restoreStatus.textContent = rdBackup.i18n.restoreFailed + ' ' + String( err );
				} );
			} );
		}
	} );

} )();


/* ===================================================================== *
 * admin-img-regen.js — Images & Media: WebP/AVIF regeneration
 * ===================================================================== */

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
