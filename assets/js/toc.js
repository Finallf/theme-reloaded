/**
 * assets/js/toc.js
 *
 * Table of Contents — FAB + collapsible panel interactivity.
 * Rendered by rd_toc_render_html() in inc/mod-table-of-contents.php.
 *
 * Behaviors:
 *   1. Click the FAB → toggle the panel (.is-open)
 *   2. Click an internal link (.rd-toc__list a) → close the panel
 *      (browser scrolls via native href + CSS scroll-behavior: smooth)
 *   3. Click outside the TOC → close the panel
 *   4. ESC → close the panel
 *
 * FAB visibility is handled by CSS via position:sticky on the parent
 * .entry-content — when the user scrolls past the content, the FAB scrolls
 * away naturally. No IntersectionObserver needed.
 *
 * Loaded only on post singles when `enable_table_of_contents` is ON.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toc = document.querySelector( '[data-rd-toc]' );
		if ( ! toc ) {
			return; // page without a TOC (short post, feature off, etc.)
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

		// Click on an internal link: scroll is native via the href hash + CSS scroll-behavior.
		// We only close the panel — no preventDefault.
		panel.addEventListener( 'click', function ( ev ) {
			var link = ev.target.closest( 'a' );
			if ( link && panel.contains( link ) ) {
				closeToc();
			}
		} );

		// Click outside: close the panel
		document.addEventListener( 'click', function ( ev ) {
			if ( ! toc.classList.contains( 'is-open' ) ) {
				return;
			}
			if ( toc.contains( ev.target ) ) {
				return;
			}
			closeToc();
		} );

		// ESC: close + return focus to the FAB
		document.addEventListener( 'keydown', function ( ev ) {
			if ( 'Escape' === ev.key && toc.classList.contains( 'is-open' ) ) {
				closeToc();
				fab.focus();
			}
		} );

		// Detect the "sticky active" state via an IntersectionObserver on the sentinel.
		// When the sentinel (positioned at the top of the anchor = the FAB's natural
		// position) passes the top of the viewport, the sticky has kicked in — we add
		// .is-stuck on .rd-toc, which enables the FAB's box-shadow via CSS.
		//
		// rootMargin '-20px 0 0 0' shrinks the viewport root by 20px from the top,
		// matching the `top: 20px` of the .rd-toc sticky. When the sentinel crosses
		// that threshold, intersectionRatio goes to 0 = sticky activated.
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
