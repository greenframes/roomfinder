<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class P20_RF_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'manage_' . P20_RF_CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . P20_RF_CPT . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . P20_RF_CPT . '_sortable_columns', array( $this, 'sortable_columns' ) );
	}

	public function register() {
		$labels = array(
			'name'               => __( 'Raeume', 'p20-raumfinder' ),
			'singular_name'      => __( 'Raum', 'p20-raumfinder' ),
			'add_new'            => __( 'Raum hinzufuegen', 'p20-raumfinder' ),
			'add_new_item'       => __( 'Neuen Raum hinzufuegen', 'p20-raumfinder' ),
			'edit_item'          => __( 'Raum bearbeiten', 'p20-raumfinder' ),
			'new_item'           => __( 'Neuer Raum', 'p20-raumfinder' ),
			'view_item'          => __( 'Raum ansehen', 'p20-raumfinder' ),
			'search_items'       => __( 'Raeume durchsuchen', 'p20-raumfinder' ),
			'not_found'          => __( 'Keine Raeume gefunden', 'p20-raumfinder' ),
			'not_found_in_trash' => __( 'Keine Raeume im Papierkorb', 'p20-raumfinder' ),
			'menu_name'          => __( 'Raumfinder', 'p20-raumfinder' ),
			'all_items'          => __( 'Alle Raeume', 'p20-raumfinder' ),
		);

		register_post_type(
			P20_RF_CPT,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => 'p20-raumfinder',
				'show_in_rest'       => false,
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => 'raum' ),
				'capability_type'    => 'post',
				'hierarchical'       => false,
				'menu_position'      => 25,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			)
		);
	}

	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['p20_capacity'] = __( 'Kapazitaet', 'p20-raumfinder' );
				$new['p20_size']     = __( 'Groesse', 'p20-raumfinder' );
				$new['p20_status']   = __( 'Status', 'p20-raumfinder' );
			}
		}
		return $new;
	}

	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'p20_capacity':
				$min = get_post_meta( $post_id, 'p20_capacity_min', true );
				$max = get_post_meta( $post_id, 'p20_capacity_max', true );
				echo esc_html( trim( $min . '–' . $max, '–' ) ? ( $min ? $min . '–' . $max : $max ) : '—' );
				break;
			case 'p20_size':
				$size = get_post_meta( $post_id, 'p20_size_sqm', true );
				echo $size ? esc_html( $size ) . ' m&sup2;' : '—';
				break;
			case 'p20_status':
				$active = get_post_meta( $post_id, 'p20_active', true );
				$active = ( '' === $active ) ? '1' : $active;
				echo '1' === $active
					? '<span style="color:#2e7d32;font-weight:600;">● ' . esc_html__( 'Aktiv', 'p20-raumfinder' ) . '</span>'
					: '<span style="color:#999;">○ ' . esc_html__( 'Inaktiv', 'p20-raumfinder' ) . '</span>';
				break;
		}
	}

	public function sortable_columns( $columns ) {
		$columns['p20_size'] = 'p20_size';
		return $columns;
	}
}
