/**
 * assets/js/admin-stats.js
 *
 * Inicializa o gráfico de crescimento mensal (K4) na aba Statistics do painel.
 * Carregado SÓ no contexto admin/rd_options/tab=estatisticas — ver enqueue
 * em inc/mod-stats.php → rd_stats_admin_enqueue().
 *
 * Dados (labels + values) vêm via data-attributes do <canvas>, populados
 * pelo PHP em rd_stats_render_dashboard(). Mantém zero inline JS.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var canvas = document.getElementById( 'rd-stats-monthly-chart' );
		if ( ! canvas || typeof window.Chart === 'undefined' ) {
			return;
		}

		// Parse defensivo dos data-attributes (JSON.parse pode jogar se vier malformado)
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
					backgroundColor: 'rgba(34, 113, 177, 0.7)', // azul WP com transparência
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
					legend: { display: false }, // só uma série, label do dataset basta
					tooltip: {
						backgroundColor: 'rgba(29, 35, 39, 0.95)',
						padding: 10,
						titleFont: { weight: 'bold' },
						bodyFont: { size: 13 },
						displayColors: false,
						callbacks: {
							// Formata o valor com separador de milhar (locale-aware)
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
