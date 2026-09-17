<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class P20_RF_Activator {

	public static function activate() {
		$cpt = new P20_RF_CPT();
		$cpt->register();
		$tax = new P20_RF_Taxonomies();
		$tax->register();
		$tax->maybe_seed_defaults();
		P20_RF_Seeder::maybe_seed();

		if ( false === get_option( 'p20_rf_settings' ) ) {
			update_option( 'p20_rf_settings', P20_RF_Settings::default_settings() );
		}

		flush_rewrite_rules();
	}
}
