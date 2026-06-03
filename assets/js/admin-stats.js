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
