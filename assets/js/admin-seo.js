/**
 * Meta Description box — live character counter.
 *
 * Counts toward the 160-char Google snippet limit (the meta output truncates
 * there). Turns the counter red past the limit. No dependencies; self-guards
 * if the box isn't on the page.
 */
( function () {
	'use strict';

	var field = document.getElementById( 'rd_meta_description' );
	if ( ! field ) {
		return;
	}

	var counter = document.querySelector( '[data-rd-seo-count]' );
	if ( ! counter ) {
		return;
	}

	var LIMIT = 160;

	function update() {
		var len = field.value.length;
		counter.textContent = len;
		counter.parentNode.classList.toggle( 'rd-seo-over', len > LIMIT );
	}

	field.addEventListener( 'input', update );
	update();
}() );
