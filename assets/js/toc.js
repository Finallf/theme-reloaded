/**
 * assets/js/toc.js
 *
 * Table of Contents — interactivity do FAB + painel collapsible.
 * Renderizado por rd_toc_render_html() em inc/mod-table-of-contents.php.
 *
 * Comportamentos:
 *   1. Click no FAB → toggla painel (.is-open)
 *   2. Click em link interno (.rd-toc__list a) → fecha painel
 *      (browser scrolla via href nativo + CSS scroll-behavior: smooth)
 *   3. Click fora do TOC → fecha painel
 *   4. ESC → fecha painel
 *
 * Visibilidade do FAB é gerenciada por CSS via position:sticky no parent
 * .entry-content — quando user passa do conteúdo, FAB scrolla pra fora
 * naturalmente. Sem necessidade de IntersectionObserver.
 *
 * Carregado só em singles de post quando `enable_table_of_contents` está ON.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toc = document.querySelector( '[data-rd-toc]' );
		if ( ! toc ) {
			return; // página sem TOC (post curto, feature off, etc)
		}

		var fab   = toc.querySelector( '.rd-toc__fab' );
		var panel = toc.querySelector( '.rd-toc__panel' );
		if ( ! fab || ! panel ) {
			return;
		}

		function openToc() {
			toc.classList.add( 'is-open' );
			fab.setAttribute( 'aria-expanded', 'true' );
		}

		function closeToc() {
			toc.classList.remove( 'is-open' );
			fab.setAttribute( 'aria-expanded', 'false' );
		}

		function toggleToc() {
			if ( toc.classList.contains( 'is-open' ) ) {
				closeToc();
			} else {
				openToc();
			}
		}

		fab.addEventListener( 'click', toggleToc );

		// Click em link interno: scroll é nativo via href hash + CSS scroll-behavior.
		// Só fechamos o painel — sem preventDefault.
		panel.addEventListener( 'click', function ( ev ) {
			var link = ev.target.closest( 'a' );
			if ( link && panel.contains( link ) ) {
				closeToc();
			}
		} );

		// Click fora: fecha painel
		document.addEventListener( 'click', function ( ev ) {
			if ( ! toc.classList.contains( 'is-open' ) ) {
				return;
			}
			if ( toc.contains( ev.target ) ) {
				return;
			}
			closeToc();
		} );

		// ESC: fecha + devolve foco pro FAB
		document.addEventListener( 'keydown', function ( ev ) {
			if ( 'Escape' === ev.key && toc.classList.contains( 'is-open' ) ) {
				closeToc();
				fab.focus();
			}
		} );

		// Detecta estado "sticky ativo" via IntersectionObserver no sentinel.
		// Quando o sentinel (posicionado no top do anchor = natural position do
		// FAB) passa do top da viewport, o sticky kicou — adicionamos .is-stuck
		// no .rd-toc, que ativa box-shadow no FAB via CSS.
		//
		// rootMargin '-20px 0 0 0' encolhe o root da viewport por 20px do topo,
		// matching com o `top: 20px` do .rd-toc sticky. Quando o sentinel cruza
		// esse threshold, intersectionRatio vai a 0 = sticky ativou.
		var sentinel = toc.parentNode && toc.parentNode.querySelector( '.rd-toc__sentinel' );
		if ( sentinel && 'IntersectionObserver' in window ) {
			var stickyObserver = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						toc.classList.toggle( 'is-stuck', ! entry.isIntersecting );
					} );
				},
				{
					rootMargin: '-20px 0px 0px 0px',
					threshold: 0,
				}
			);
			stickyObserver.observe( sentinel );
		}
	} );
} )();
