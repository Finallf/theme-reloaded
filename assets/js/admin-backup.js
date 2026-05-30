/**
 * assets/js/admin-backup.js
 *
 * Coordena o fluxo de import na sub-seção Backup da aba Manutenção do painel:
 *   1. User escolhe arquivo JSON
 *   2. JS lê via FileReader e faz POST pra /wp-json/rd/v1/backup/preview
 *   3. Backend valida + calcula diff → JS renderiza preview
 *   4. User clica "Apply import" → POST pra /wp-json/rd/v1/backup/import
 *   5. Backend salva snapshot e aplica → JS recarrega a página
 *
 * Carregado SÓ na aba Manutenção via rd_backup_admin_enqueue() em inc/mod-backup.php.
 * Sem dependências externas (vanilla JS).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Sai cedo se o objeto rdBackup não existe (carregamos só na aba Manutenção)
		if ( typeof window.rdBackup === 'undefined' ) return;

		// ============= EXPORT: atualizar href do link conforme checkboxes =============
		// Usamos link <a> em vez de form/button pra não conflitar com o form pai
		// (callback de section roda DENTRO do <form action="options.php"> do painel —
		// forms aninhados viram submit pro form errado). Esse handler reconstrói o
		// URL do ZERO a partir dos data-attrs do link (base, action, nonce) e
		// adiciona sections[] conforme checkboxes — evita acumular params antigos.
		var exportLink     = document.getElementById( 'rd-backup-export-link' );
		var exportCheckbox = document.querySelectorAll( '.rd-backup-export-cb' );

		function updateExportLink() {
			if ( ! exportLink ) return;

			// Reconstrói do zero: base URL limpa + action + nonce
			var url = new URL( exportLink.dataset.baseUrl, window.location.origin );
			url.searchParams.set( 'action',   exportLink.dataset.action );
			url.searchParams.set( '_wpnonce', exportLink.dataset.nonce );

			// Adiciona sections[] das checkboxes marcadas
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

		// Roda 1x no load pra normalizar o URL (caso PHP tenha gerado sections[0]/[1])
		updateExportLink();

		// ============= IMPORT =============
		var fileInput    = document.getElementById( 'rd-backup-file' );
		var filenameEl   = document.getElementById( 'rd-backup-filename' );
		var previewEl    = document.getElementById( 'rd-backup-preview' );
		var statusEl     = document.getElementById( 'rd-backup-status' );

		if ( ! fileInput ) return;

		// Estado in-memory do JSON pendente — usado pelo botão Apply
		var pendingData = null;

		// ============= Helpers de UI =============

		// Mapping callsite -> CSS variant: callsites usam 'info'/'success'/'error'
		// (semântica histórica); CSS do design system rd-pstatus usa 'danger' em
		// vez de 'error'. Mapeamos aqui pra não tocar nos chamadores.
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

		// Escape HTML pra inserção segura (defesa em profundidade — backend já sanitiza)
		function esc( str ) {
			var d = document.createElement( 'div' );
			d.textContent = String( str );
			return d.innerHTML;
		}

		// sprintf-style %d/%s substitution pra strings i18n com placeholders.
		// Necessário porque traduções (ex: pt-BR) podem reordenar a posição do
		// número/valor na frase — não dá pra concatenar literal em JS.
		function fmt( template, value ) {
			return String( template ).replace( /%[ds]/, value );
		}

		// Atalho pro objeto de strings traduzidas
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

		// ============= Render do preview =============

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

			// Ad banners diff (mesma estrutura que settings)
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
				// Reload pra mostrar configs aplicadas
				setTimeout( function () { window.location.reload(); }, 1200 );
			} )
			.catch( function ( err ) {
				setStatus( 'error', rdBackup.i18n.importFailed + ' ' + String( err ) );
			} );
		}

		// ============= RESTORE: rollback do último import =============
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
