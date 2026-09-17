<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal Gutenberg block wrapper around the [p20_raumfinder] shortcode,
 * kept intentionally simple (no build step / JSX) to avoid unnecessary
 * complexity or framework dependencies.
 */
class P20_RF_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'p20-rf-block',
			P20_RF_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			P20_RF_VERSION,
			true
		);

		register_block_type(
			'p20/raumfinder',
			array(
				'editor_script'   => 'p20-rf-block',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	public function render() {
		return do_shortcode( '[p20_raumfinder]' );
	}
}
