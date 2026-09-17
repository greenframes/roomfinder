<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class P20_RF_Shortcode {

	public function __construct() {
		add_shortcode( 'p20_raumfinder', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style( 'p20-rf-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&display=swap', array(), null );
		wp_register_style( 'p20-rf-public', P20_RF_URL . 'assets/css/public.css', array( 'p20-rf-fonts' ), P20_RF_VERSION );
		wp_register_script( 'p20-rf-public', P20_RF_URL . 'assets/js/public-wizard.js', array(), P20_RF_VERSION, true );
	}

	public function render( $atts ) {
		wp_enqueue_style( 'p20-rf-fonts' );
		wp_enqueue_style( 'p20-rf-public' );
		wp_enqueue_script( 'p20-rf-public' );

		$config = array(
			'restUrl'      => esc_url_raw( rest_url( 'p20/v1' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'requestNonce' => wp_create_nonce( 'p20_rf_request' ),
		);
		wp_localize_script( 'p20-rf-public', 'p20RaumfinderConfig', $config );

		ob_start();
		p20_rf_get_template( 'wizard-root' );
		return ob_get_clean();
	}
}
