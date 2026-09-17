<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the three taxonomies used to keep Raumart, Ausstattung and
 * Verpflegung fully editable in the backend without touching code.
 */
class P20_RF_Taxonomies {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'admin_init', array( $this, 'maybe_seed_defaults' ) );
	}

	public function register() {
		register_taxonomy(
			P20_RF_TAX_EVENT,
			P20_RF_CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Raumarten / Eignung', 'p20-raumfinder' ),
					'singular_name' => __( 'Raumart', 'p20-raumfinder' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'meta_box_cb'       => 'post_categories_meta_box',
				'show_in_rest'      => false,
			)
		);

		register_taxonomy(
			P20_RF_TAX_FEATURE,
			P20_RF_CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Ausstattung', 'p20-raumfinder' ),
					'singular_name' => __( 'Ausstattungsmerkmal', 'p20-raumfinder' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'meta_box_cb'       => false,
				'show_in_rest'      => false,
			)
		);

		register_taxonomy(
			P20_RF_TAX_CATERING,
			P20_RF_CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Verpflegung', 'p20-raumfinder' ),
					'singular_name' => __( 'Verpflegungsoption', 'p20-raumfinder' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'meta_box_cb'       => false,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Seeds the three taxonomies with the starting data described in the
	 * project brief, once. Everything is fully editable afterwards.
	 */
	public function maybe_seed_defaults() {
		if ( get_option( 'p20_rf_seeded' ) ) {
			return;
		}

		foreach ( P20_RF_Data::default_event_types() as $term ) {
			if ( ! term_exists( $term, P20_RF_TAX_EVENT ) ) {
				wp_insert_term( $term, P20_RF_TAX_EVENT );
			}
		}
		foreach ( P20_RF_Data::default_features() as $term ) {
			if ( ! term_exists( $term, P20_RF_TAX_FEATURE ) ) {
				wp_insert_term( $term, P20_RF_TAX_FEATURE );
			}
		}
		foreach ( P20_RF_Data::default_catering() as $term ) {
			if ( ! term_exists( $term, P20_RF_TAX_CATERING ) ) {
				wp_insert_term( $term, P20_RF_TAX_CATERING );
			}
		}

		update_option( 'p20_rf_seeded', 1 );
	}
}
