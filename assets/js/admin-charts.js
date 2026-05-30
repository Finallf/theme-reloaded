/**
 * assets/js/admin-charts.js
 *
 * Auto-render genérico de Chart.js no admin do ReloadeD (Wave 11 Fase G).
 *
 * Detecta todo <canvas data-rd-chart-type="..."> na página e inicializa
 * Chart.js automaticamente. Tipos suportados:
 *   - doughnut : labels + values, 1 dataset
 *   - bar      : labels + values, 1 dataset (azul WP)
 *
 * Convenção de data-attributes:
 *   data-rd-chart-type    : tipo do gráfico ('doughnut' | 'bar')
 *   data-labels           : JSON array de labels (strings)
 *   data-values           : JSON array de values (numbers)
 *   data-label            : label do dataset (texto, ex: "Views")
 *
 * Dados (labels + values) vêm via data-attributes do <canvas>, populados
 * pelo PHP no callback que renderiza. Mantém zero inline JS — compatível
 * com CSP nonce-based (Wave 8.5).
 *
 * Carregado SÓ nas abas Dashboard e Security (gate em mod-stats.php
 * → rd_stats_admin_enqueue).
 */
( function () {
	'use strict';

	/**
	 * Paleta padrão pra doughnut chart — cores do WP admin nativo +
	 * tons complementares pra distinguir até ~10 categorias.
	 */
	var DOUGHNUT_PALETTE = [
		'rgba(34, 113, 177, 0.85)',  // azul WP
		'rgba(214, 54, 56, 0.85)',   // vermelho danger
		'rgba(210, 153, 34, 0.85)',  // amber warning
		'rgba(0, 122, 57, 0.85)',    // verde success
		'rgba(135, 60, 190, 0.85)',  // roxo
		'rgba(0, 160, 175, 0.85)',   // ciano
		'rgba(230, 120, 60, 0.85)',  // laranja
		'rgba(80, 87, 94, 0.85)',    // cinza WP
	];

	/**
	 * Inicializa doughnut chart num canvas.
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
								// "directive: 12 (45%)" — mostra valor + percentual.
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
	 * Inicializa bar chart num canvas (azul WP, similar ao K4 do mod-stats).
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
					legend: { display: false }, // 1 dataset só — legend é redundante.
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }, // sem decimais (views são inteiros).
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
