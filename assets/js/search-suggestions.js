/**
 * assets/js/search-suggestions.js
 *
 * Search autocomplete — anexa em .search-field (header desktop) e
 * .menu-search-field (busca dentro do painel hambúrguer).
 *
 * Comportamentos:
 *   1. Debounce 250ms entre keystrokes (configurável via rdSearchSugg.debounceMs)
 *   2. Min 3 chars pra disparar AJAX (rdSearchSugg.minChars)
 *   3. Dropdown abaixo do input com até 5 resultados
 *   4. Keyboard nav: ↑/↓ percorre, Enter abre, Esc fecha
 *   5. Click fora fecha
 *   6. Footer "See all results for X" → vai pra página de busca completa
 *
 * Backend: wp_ajax_rd_search_suggestions em inc/mod-search-suggestions.php.
 * Resultados cacheados server-side 15min; AbortController cancela fetches
 * pendentes quando o user continua digitando.
 *
 * Markup HTML do dropdown (injetado por JS):
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
		// Cria o dropdown e anexa no <body>. NÃO podemos colocar dentro do
		// <form> porque o .search-form tem overflow:hidden (necessário pra
		// animação de expansão hover/focus) — dropdown seria clipado.
		// position:fixed + coordenadas calculadas via getBoundingClientRect()
		// resolvem em qualquer container, sem importar overflow do ancestral.
		var dropdown = document.createElement( 'div' );
		dropdown.className = 'rd-sugg';
		dropdown.setAttribute( 'role', 'listbox' );
		dropdown.setAttribute( 'aria-label', 'Search suggestions' );
		dropdown.hidden = true;
		document.body.appendChild( dropdown );

		var form = input.closest( 'form' ) || input.parentNode;

		// Estado por input — debounce timer, AbortController, índice ativo
		var state = {
			input:      input, // ref pro reposicionamento do dropdown
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

		// ===== Click fora fecha =====
		document.addEventListener( 'click', function ( ev ) {
			if ( dropdown.hidden ) {
				return;
			}
			if ( form.contains( ev.target ) ) {
				return;
			}
			hideDropdown( dropdown, state );
		} );

		// Re-abre dropdown ao focar input com texto suficiente
		input.addEventListener( 'focus', function () {
			if ( input.value.trim().length >= config.minChars && state.lastQuery === input.value.trim() && state.items.length ) {
				positionDropdown( dropdown, input );
				dropdown.hidden = false;
			}
		} );
	}

	// ===== Scroll/resize global → esconde todos os dropdowns =====
	// User scrollando = não tá ativamente buscando. Esconde pra não ficar
	// flutuando dessincronizado dos inputs (position:fixed não acompanha scroll).
	function hideAllDropdowns() {
		document.querySelectorAll( '.rd-sugg' ).forEach( function ( d ) {
			if ( ! d.hidden ) {
				d.hidden = true;
			}
		} );
	}
	window.addEventListener( 'scroll', hideAllDropdowns, { passive: true } );
	window.addEventListener( 'resize', hideAllDropdowns );

	// ===== Posicionamento do dropdown =====
	// position:fixed + coords calculadas a partir do input pra escapar de
	// qualquer container com overflow:hidden. Recalcula em show() pra pegar
	// a posição atual do input (search-form do header expande de 40→230px no focus).
	//
	// Comportamento difere entre os 2 inputs:
	//   - .menu-search-field (hamburger, lado esquerdo da tela): dropdown sai
	//     13px à esquerda do input, cresce pra direita (input estreito num
	//     painel estreito; dropdown ganha largura útil).
	//   - .search-field (desktop, canto direito da tela): dropdown ancorado
	//     pela DIREITA do input, cresce pra esquerda (evita overflow no
	//     edge direito da viewport).
	var DROPDOWN_WIDTH         = 310;
	var MENU_LEFT_OFFSET_PX    = -13;
	var DESKTOP_RIGHT_SHIFT_PX = 40; // compensa o botão de submit (lupa) que vem depois do .search-field no .search-form

	function positionDropdown( dropdown, input ) {
		var rect         = input.getBoundingClientRect();
		var isMenuSearch = input.classList.contains( 'menu-search-field' );

		dropdown.style.top   = ( rect.bottom + 6 ) + 'px';
		dropdown.style.width = DROPDOWN_WIDTH + 'px';

		if ( isMenuSearch ) {
			// Hamburger: ancora pela esquerda do input + offset pra esquerda
			dropdown.style.left = ( rect.left + MENU_LEFT_OFFSET_PX ) + 'px';
		} else {
			// Desktop: ancora pela direita do FORM (input + botão submit).
			// rect.right é só do input — somamos o shift do botão pra alinhar
			// com a borda direita real do search-form.
			dropdown.style.left = ( rect.right + DESKTOP_RIGHT_SHIFT_PX - DROPDOWN_WIDTH ) + 'px';
		}
	}

	// ===== AJAX fetch + render =====
	function fetchSuggestions( query, dropdown, state ) {
		// Cancela request anterior se ainda tá em flight
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
				// AbortError quando user continua digitando — silencioso
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
					thumb.alt       = ''; // decorativo — título tá ao lado
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
		// Limpa highlight ativo
		dropdown.querySelectorAll( '.is-active' ).forEach( function ( el ) {
			el.classList.remove( 'is-active' );
		} );
	}

	function moveActive( delta, state, dropdown ) {
		var items = dropdown.querySelectorAll( '.rd-sugg__item' );
		if ( ! items.length ) {
			return;
		}
		// Limpa anterior
		if ( state.active >= 0 && items[ state.active ] ) {
			items[ state.active ].classList.remove( 'is-active' );
		}
		state.active = ( state.active + delta + items.length ) % items.length;
		items[ state.active ].classList.add( 'is-active' );
		items[ state.active ].scrollIntoView( { block: 'nearest' } );
	}
} )();
