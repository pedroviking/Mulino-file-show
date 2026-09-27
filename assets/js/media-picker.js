jQuery( function ( $ ) {
	'use strict';

	var frame;

	$( '#mulino_select_file' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: mulinoMediaPicker.title,
			button: { text: mulinoMediaPicker.buttonText },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#mulino_file_id' ).val( attachment.id );
			$( '#mulino_file_name' ).text( attachment.filename || attachment.title );
		} );

		frame.open();
	} );
} );
