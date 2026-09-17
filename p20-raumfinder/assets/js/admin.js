( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $list = $( '#p20-rf-gallery-list' );
		var $input = $( '#p20_gallery' );

		function syncInput() {
			var ids = [];
			$list.find( 'li' ).each( function () {
				ids.push( $( this ).data( 'id' ) );
			} );
			$input.val( ids.join( ',' ) );
		}

		$( '#p20-rf-add-gallery' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: 'Bilder auswaehlen',
				button: { text: 'Uebernehmen' },
				multiple: true,
			} );

			frame.on( 'select', function () {
				var selection = frame.state().get( 'selection' );
				selection.each( function ( attachment ) {
					attachment = attachment.toJSON();
					if ( $list.find( 'li[data-id="' + attachment.id + '"]' ).length ) {
						return;
					}
					var thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
					var $li = $(
						'<li data-id="' + attachment.id + '"><img src="' + thumb + '" alt=""><button type="button" class="p20-rf-remove-img button-link">&times;</button></li>'
					);
					$list.append( $li );
				} );
				syncInput();
			} );

			frame.open();
		} );

		$list.on( 'click', '.p20-rf-remove-img', function ( e ) {
			e.preventDefault();
			$( this ).closest( 'li' ).remove();
			syncInput();
		} );
	} );
} )( jQuery );
