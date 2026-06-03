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
						position: 'right',
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
