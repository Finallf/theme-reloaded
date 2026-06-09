/* global jQuery, wp, rdWidgetMedia */
/**
 * Media picker for RD widget forms (Support, Discord) on the classic Widgets
 * screen. Uses event delegation on `document` so it keeps working for widgets
 * added or re-rendered dynamically (the classic screen rebuilds a widget form
 * after each save). Binds to the .rd-widget-media-field structure rendered by
 * the widget's media_field() helper.
 */
( function ( $ ) {
	'use strict';

	// "Select image" → open the WP media frame, store the chosen URL.
	$( document ).on( 'click', '.rd-widget-media-btn', function ( e ) {
		e.preventDefault();

		var $field = $( this ).closest( '.rd-widget-media-field' );
		var $input = $field.find( '.rd-widget-media-url' );
		var $prev  = $field.find( '.rd-widget-media-preview' );

		var frame = wp.media( {
			title: rdWidgetMedia.title,
			button: { text: rdWidgetMedia.button },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var thumb = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;

			// Store the full-size URL — rd_render_qr_img() resolves the right
			// size on the frontend. `change` marks the widget dirty so WP saves it.
			$input.val( att.url ).trigger( 'change' );
			$prev.html(
				'<img src="' + thumb + '" alt="" style="max-width:120px;height:auto;display:block;margin:6px 0;">'
			);
		} );

		frame.open();
	} );

	// "Remove" → clear the field + preview.
	$( document ).on( 'click', '.rd-widget-media-remove', function ( e ) {
		e.preventDefault();
		var $field = $( this ).closest( '.rd-widget-media-field' );
		$field.find( '.rd-widget-media-url' ).val( '' ).trigger( 'change' );
		$field.find( '.rd-widget-media-preview' ).empty();
	} );

}( jQuery ) );
