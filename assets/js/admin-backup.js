/**
 * assets/js/admin-backup.js
 *
 * Coordinates the import flow in the Backup sub-section of the panel's Maintenance tab:
 *   1. User picks a JSON file
 *   2. JS reads it via FileReader and POSTs to /wp-json/rd/v1/backup/preview
 *   3. Backend validates + computes the diff → JS renders the preview
 *   4. User clicks "Apply import" → POST to /wp-json/rd/v1/backup/import
 *   5. Backend saves a snapshot and applies it → JS reloads the page
 *
 * Loaded ONLY on the Maintenance tab via rd_backup_admin_enqueue() in inc/mod-backup.php.
 * No external dependencies (vanilla JS).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Bail early if the rdBackup object doesn't exist (we only load on the Maintenance tab)
		if ( typeof window.rdBackup === 'undefined' ) return;

		// ============= EXPORT: update the link href based on the checkboxes =============
		// We use an <a> link instead of a form/button to avoid clashing with the parent
		// form (the section callback runs INSIDE the panel's <form action="options.php"> —
		// nested forms submit to the wrong form). This handler rebuilds the URL from
		// SCRATCH out of the link's data-attrs (base, action, nonce) and appends
		// sections[] based on the checkboxes — avoids accumulating stale params.
		var exportLink     = document.getElementById( 'rd-backup-export-link' );
		var exportCheckbox = document.querySelectorAll( '.rd-backup-export-cb' );

		function updateExportLink() {
			if ( ! exportLink ) return;

			// Rebuild from scratch: clean base URL + action + nonce
			var url = new URL( exportLink.dataset.baseUrl, window.location.origin );
			url.searchParams.set( 'action',   exportLink.dataset.action );
			url.searchParams.set( '_wpnonce', exportLink.dataset.nonce );

			// Append sections[] from the checked checkboxes
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

		// Run once on load to normalize the URL (in case PHP generated sections[0]/[1])
		updateExportLink();

		// ============= IMPORT =============
		var fileInput    = document.getElementById( 'rd-backup-file' );
		var filenameEl   = document.getElementById( 'rd-backup-filename' );
		var previewEl    = document.getElementById( 'rd-backup-preview' );
		var statusEl     = document.getElementById( 'rd-backup-status' );

		if ( ! fileInput ) return;

		// In-memory state of the pending JSON — used by the Apply button
		var pendingData = null;

		// ============= UI helpers =============

		// Mapping callsite -> CSS variant: callsites use 'info'/'success'/'error'
		// (historical semantics); the rd-pstatus design-system CSS uses 'danger'
		// instead of 'error'. We map here so we don't have to touch the callers.
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

		// Escape HTML for safe insertion (defense in depth — backend already sanitizes)
		function esc( str ) {
			var d = document.createElement( 'div' );
			d.textContent = String( str );
			return d.innerHTML;
		}

		// sprintf-style %d/%s substitution for i18n strings with placeholders.
		// Needed because translations (e.g. pt-BR) may reorder the position of the
		// number/value in the sentence — you can't concatenate literally in JS.
		function fmt( template, value ) {
			return String( template ).replace( /%[ds]/, value );
		}

		// Shortcut to the translated-strings object
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

		// ============= Preview render =============

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

			// Ad banners diff (same structure as settings)
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
				// Reload to show the applied settings
				setTimeout( function () { window.location.reload(); }, 1200 );
			} )
			.catch( function ( err ) {
				setStatus( 'error', rdBackup.i18n.importFailed + ' ' + String( err ) );
			} );
		}

		// ============= RESTORE: rollback of the last import =============
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
