( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'p20/raumfinder', {
		title: __( 'P20 Raumfinder', 'p20-raumfinder' ),
		icon: 'search',
		category: 'widgets',
		description: __( 'Interaktiver Raumfinder fuer Tagungs- und Veranstaltungsraeume.', 'p20-raumfinder' ),
		supports: { html: false },
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'p20-rf-block-placeholder' } );
			return el(
				'div',
				blockProps,
				el( 'strong', {}, __( 'P20 Raumfinder', 'p20-raumfinder' ) ),
				el( 'p', {}, __( 'Der interaktive Raumfinder wird im Frontend angezeigt.', 'p20-raumfinder' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
