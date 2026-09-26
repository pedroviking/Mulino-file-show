jQuery( function ( $ ) {
	'use strict';

	var frame;

	$( '#mfs_select_file' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: mfsMediaPicker.title,
			button: { text: mfsMediaPicker.buttonText },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#mfs_file_id' ).val( attachment.id );
			$( '#mfs_file_name' ).text( attachment.filename || attachment.title );
		} );

		frame.open();
	} );
} );
