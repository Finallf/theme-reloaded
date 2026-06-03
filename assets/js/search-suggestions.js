/**
 * assets/js/search-suggestions.js
 *
 * Search autocomplete — attaches to .search-field (desktop header) and
 * .menu-search-field (search inside the hamburger panel).
 *
 * Behaviors:
 *   1. 250ms debounce between keystrokes (configurable via rdSearchSugg.debounceMs)
 *   2. Min 3 chars to fire the AJAX (rdSearchSugg.minChars)
 *   3. Dropdown below the input with up to 5 results
 *   4. Keyboard nav: ↑/↓ moves, Enter opens, Esc closes
 *   5. Click outside closes
 *   6. Footer "See all results for X" → goes to the full search page
 *
 * Backend: wp_ajax_rd_search_suggestions in inc/mod-search-suggestions.php.
 * Results cached server-side for 15min; AbortController cancels pending
 * fetches when the user keeps typing.
 *
 * Dropdown HTML markup (injected by JS):
 *   <div class="rd-sugg" role="listbox">
 *     <a class="rd-sugg__item" role="option" href="...">
 *       <img class="rd-sugg__thumb">
 *       <span class="rd-sugg__title">...</span>
 *     </a>
 *     ...
 *     <a class="rd-sugg__see-all">See all results for "X" →</a>
 *   </div>
 */
( function () {
	'use strict';

	if ( typeof window.rdSearchSugg === 'undefined' ) {
		return;
	}

	var config = window.rdSearchSugg;

	document.addEventListener( 'DOMContentLoaded', function () {
		var inputs = document.querySelectorAll( '.search-field, .menu-search-field' );
		if ( ! inputs.length ) {
			return;
		}
		inputs.forEach( setupInput );
	} );

	function setupInput( input ) {
		// Create the dropdown and attach it to <body>. We can NOT place it inside
		// the <form> because .search-form has overflow:hidden (needed for the
		// hover/focus expansion animation) — the dropdown would be clipped.
		// position:fixed + coordinates computed via getBoundingClientRect()
		// work in any container, regardless of the ancestor's overflow.
		var dropdown = document.createElement( 'div' );
		dropdown.className = 'rd-sugg';
		dropdown.setAttribute( 'role', 'listbox' );
		dropdown.setAttribute( 'aria-label', 'Search suggestions' );
		dropdown.hidden = true;
		document.body.appendChild( dropdown );

		var form = input.closest( 'form' ) || input.parentNode;

		// Per-input state — debounce timer, AbortController, active index
		var state = {
			input:      input, // ref for repositioning the dropdown
			timer:      null,
			controller: null,
			items:      [],
			active:     -1,
			lastQuery:  '',
		};

		// ===== Input typing =====
		input.addEventListener( 'input', function () {
			if ( state.timer ) {
				clearTimeout( state.timer );
			}
			var query = input.value.trim();
			if ( query.length < config.minChars ) {
				hideDropdown( dropdown, state );
				return;
			}
			state.timer = setTimeout( function () {
				fetchSuggestions( query, dropdown, state );
			}, config.debounceMs );
		} );

		// ===== Keyboard nav =====
		input.addEventListener( 'keydown', function ( ev ) {
			if ( dropdown.hidden ) {
				return;
			}
			if ( 'ArrowDown' === ev.key ) {
				ev.preventDefault();
				moveActive( 1, state, dropdown );
			} else if ( 'ArrowUp' === ev.key ) {
				ev.preventDefault();
				moveActive( -1, state, dropdown );
			} else if ( 'Enter' === ev.key && state.active >= 0 ) {
				ev.preventDefault();
				var item = dropdown.querySelectorAll( '.rd-sugg__item' )[ state.active ];
				if ( item ) {
					window.location.href = item.href;
				}
			} else if ( 'Escape' === ev.key ) {
				ev.preventDefault();
				hideDropdown( dropdown, state );
				input.blur();
			}
		} );

		// ===== Click outside closes =====
		document.addEventListener( 'click', function ( ev ) {
			if ( dropdown.hidden ) {
				return;
			}
			if ( form.contains( ev.target ) ) {
				return;
			}
			hideDropdown( dropdown, state );
		} );

		// Re-open the dropdown when focusing the input with enough text
		input.addEventListener( 'focus', function () {
			if ( input.value.trim().length >= config.minChars && state.lastQuery === input.value.trim() && state.items.length ) {
				positionDropdown( dropdown, input );
				dropdown.hidden = false;
			}
		} );
	}

	// ===== Global scroll/resize → hide all dropdowns =====
	// User scrolling = not actively searching. Hide it so it doesn't float
	// desynced from the inputs (position:fixed doesn't follow scroll).
	function hideAllDropdowns() {
		document.querySelectorAll( '.rd-sugg' ).forEach( function ( d ) {
			if ( ! d.hidden ) {
				d.hidden = true;
			}
		} );
	}
	window.addEventListener( 'scroll', hideAllDropdowns, { passive: true } );
	window.addEventListener( 'resize', hideAllDropdowns );

	// ===== Dropdown positioning =====
	// position:fixed + coords computed from the input to escape any container
	// with overflow:hidden. Recomputed in show() to get the input's current
	// position (the header search-form expands from 40→230px on focus).
	//
	// Behavior differs between the 2 inputs:
	//   - .menu-search-field (hamburger, left side of the screen): dropdown comes
	//     out 13px to the left of the input, grows to the right (narrow input in a
	//     narrow panel; the dropdown gains usable width).
	//   - .search-field (desktop, right corner of the screen): dropdown anchored
	//     by the RIGHT of the input, grows to the left (avoids overflow at the
	//     right edge of the viewport).
	var DROPDOWN_WIDTH         = 310;
	var MENU_LEFT_OFFSET_PX    = -13;
	var DESKTOP_RIGHT_SHIFT_PX = 40; // compensates for the submit button (magnifier) that comes after .search-field in .search-form

	function positionDropdown( dropdown, input ) {
		var rect         = input.getBoundingClientRect();
		var isMenuSearch = input.classList.contains( 'menu-search-field' );

		dropdown.style.top   = ( rect.bottom + 6 ) + 'px';
		dropdown.style.width = DROPDOWN_WIDTH + 'px';

		if ( isMenuSearch ) {
			// Hamburger: anchor by the left of the input + offset to the left
			dropdown.style.left = ( rect.left + MENU_LEFT_OFFSET_PX ) + 'px';
		} else {
			// Desktop: anchor by the right of the FORM (input + submit button).
			// rect.right is just the input — we add the button shift to align
			// with the search-form's real right edge.
			dropdown.style.left = ( rect.right + DESKTOP_RIGHT_SHIFT_PX - DROPDOWN_WIDTH ) + 'px';
		}
	}

	// ===== AJAX fetch + render =====
	function fetchSuggestions( query, dropdown, state ) {
		// Cancel the previous request if it's still in flight
		if ( state.controller ) {
			state.controller.abort();
		}
		state.controller = new AbortController();

		var url = config.ajaxUrl + '?action=rd_search_suggestions&q=' + encodeURIComponent( query );

		fetch( url, {
			method:      'GET',
			credentials: 'same-origin',
			signal:      state.controller.signal,
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( ! data || ! data.success || ! data.data ) {
					return;
				}
				state.items     = data.data.results || [];
				state.lastQuery = query;
				state.active    = -1;
				renderDropdown( dropdown, query, state.items );
				positionDropdown( dropdown, state.input );
			} )
			.catch( function ( err ) {
				// AbortError when the user keeps typing — silent
				if ( err.name !== 'AbortError' ) {
					console.warn( 'rd-search-suggestions:', err );
				}
			} );
	}

	function renderDropdown( dropdown, query, results ) {
		dropdown.innerHTML = '';

		if ( results.length === 0 ) {
			var empty = document.createElement( 'div' );
			empty.className   = 'rd-sugg__empty';
			empty.textContent = config.i18n.noResults + ' "' + query + '"';
			dropdown.appendChild( empty );
		} else {
			results.forEach( function ( item, idx ) {
				var link  = document.createElement( 'a' );
				link.className = 'rd-sugg__item';
				link.href      = item.url;
				link.setAttribute( 'role', 'option' );
				link.setAttribute( 'data-idx', String( idx ) );

				if ( item.thumb ) {
					var thumb = document.createElement( 'img' );
					thumb.className = 'rd-sugg__thumb';
					thumb.src       = item.thumb;
					thumb.alt       = ''; // decorative — the title is right next to it
					thumb.loading   = 'lazy';
					thumb.width     = 71;
					thumb.height    = 40;
					link.appendChild( thumb );
				} else {
					var thumbFallback = document.createElement( 'span' );
					thumbFallback.className = 'rd-sugg__thumb rd-sugg__thumb--fallback';
					link.appendChild( thumbFallback );
				}

				var title = document.createElement( 'span' );
				title.className   = 'rd-sugg__title';
				title.textContent = item.title;
				link.appendChild( title );

				dropdown.appendChild( link );
			} );
		}

		// Footer "See all results for X →"
		var seeAll = document.createElement( 'a' );
		seeAll.className   = 'rd-sugg__see-all';
		seeAll.href        = config.searchUrl + encodeURIComponent( query );
		seeAll.textContent = config.i18n.seeAll + ' "' + query + '" →';
		dropdown.appendChild( seeAll );

		dropdown.hidden = false;
	}

	function hideDropdown( dropdown, state ) {
		dropdown.hidden = true;
		state.active    = -1;
		// Clear the active highlight
		dropdown.querySelectorAll( '.is-active' ).forEach( function ( el ) {
			el.classList.remove( 'is-active' );
		} );
	}

	function moveActive( delta, state, dropdown ) {
		var items = dropdown.querySelectorAll( '.rd-sugg__item' );
		if ( ! items.length ) {
			return;
		}
		// Clear the previous one
		if ( state.active >= 0 && items[ state.active ] ) {
			items[ state.active ].classList.remove( 'is-active' );
		}
		state.active = ( state.active + delta + items.length ) % items.length;
		items[ state.active ].classList.add( 'is-active' );
		items[ state.active ].scrollIntoView( { block: 'nearest' } );
	}
} )();
