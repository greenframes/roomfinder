<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires up the "Raumfinder" admin menu with the exact structure requested:
 * Raeume, Raum hinzufuegen, Ausstattung, Verpflegung, Einstellungen.
 */
class P20_RF_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'redirect_default' ) );
	}

	public function menu() {
		add_menu_page(
			__( 'Raumfinder', 'p20-raumfinder' ),
			__( 'Raumfinder', 'p20-raumfinder' ),
			'edit_posts',
			'p20-raumfinder',
			array( $this, 'render_rooms_redirect' ),
			'dashicons-building',
			25
		);

		add_submenu_page(
			'p20-raumfinder',
			__( 'Raeume', 'p20-raumfinder' ),
			__( 'Raeume', 'p20-raumfinder' ),
			'edit_posts',
			'edit.php?post_type=' . P20_RF_CPT
		);

		add_submenu_page(
			'p20-raumfinder',
			__( 'Raum hinzufuegen', 'p20-raumfinder' ),
			__( 'Raum hinzufuegen', 'p20-raumfinder' ),
			'edit_posts',
			'post-new.php?post_type=' . P20_RF_CPT
		);

		add_submenu_page(
			'p20-raumfinder',
			__( 'Ausstattung', 'p20-raumfinder' ),
			__( 'Ausstattung', 'p20-raumfinder' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . P20_RF_TAX_FEATURE . '&post_type=' . P20_RF_CPT
		);

		add_submenu_page(
			'p20-raumfinder',
			__( 'Verpflegung', 'p20-raumfinder' ),
			__( 'Verpflegung', 'p20-raumfinder' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . P20_RF_TAX_CATERING . '&post_type=' . P20_RF_CPT
		);

		// Settings submenu is registered by P20_RF_Settings itself, it will
		// simply appear alongside these because it also targets 'p20-raumfinder'.

		// Remove the auto-added duplicate first item (equal to top-level slug).
		remove_submenu_page( 'p20-raumfinder', 'p20-raumfinder' );
	}

	public function render_rooms_redirect() {
		// Not used - the first click always lands on the "Raeume" submenu below.
	}

	public function redirect_default() {
		global $pagenow;
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'p20-raumfinder' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . P20_RF_CPT ) );
			exit;
		}
	}
}
