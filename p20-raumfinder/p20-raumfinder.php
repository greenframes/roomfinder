<?php
/**
 * Plugin Name:       P20 Raumfinder
 * Plugin URI:        https://alte-schraubenfabrik.de/
 * Description:       Interaktiver Raumfinder fuer Tagungs-, Seminar- und Veranstaltungsraeume. Besucher werden per gefuehrtem Wizard zu passenden Raeumen geleitet. Raeume, Ausstattung, Verpflegung und Preise sind vollstaendig im Backend pflegbar.
 * Version:           1.0.4
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Die alte Schraubenfabrik
 * Text Domain:       p20-raumfinder
 * Domain Path:       /languages
 * License:           GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'P20_RF_VERSION', '1.0.4' );
define( 'P20_RF_FILE', __FILE__ );
define( 'P20_RF_PATH', plugin_dir_path( __FILE__ ) );
define( 'P20_RF_URL', plugin_dir_url( __FILE__ ) );
define( 'P20_RF_CPT', 'p20_room' );
define( 'P20_RF_TAX_EVENT', 'p20_event_type' );
define( 'P20_RF_TAX_FEATURE', 'p20_feature' );
define( 'P20_RF_TAX_CATERING', 'p20_catering' );

require_once P20_RF_PATH . 'includes/helpers.php';
require_once P20_RF_PATH . 'includes/class-p20-cpt.php';
require_once P20_RF_PATH . 'includes/class-p20-taxonomies.php';
require_once P20_RF_PATH . 'includes/class-p20-seeder.php';
require_once P20_RF_PATH . 'includes/class-p20-meta-boxes.php';
require_once P20_RF_PATH . 'includes/class-p20-matching.php';
require_once P20_RF_PATH . 'includes/class-p20-rest-api.php';
require_once P20_RF_PATH . 'includes/class-p20-mailer.php';
require_once P20_RF_PATH . 'includes/class-p20-settings.php';
require_once P20_RF_PATH . 'includes/class-p20-shortcode.php';
require_once P20_RF_PATH . 'includes/class-p20-block.php';
require_once P20_RF_PATH . 'admin/class-p20-admin.php';

/**
 * Bootstraps all plugin components.
 */
final class P20_Raumfinder {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new P20_RF_CPT();
		new P20_RF_Taxonomies();
		new P20_RF_Meta_Boxes();
		new P20_RF_REST_API();
		new P20_RF_Settings();
		new P20_RF_Shortcode();
		new P20_RF_Block();
		new P20_RF_Admin();

		register_activation_hook( P20_RF_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( P20_RF_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_flush_on_upgrade' ), 20 );
	}

	/**
	 * Flushes rewrite rules once after an update (e.g. when the room CPT's
	 * public/rewrite settings change) without requiring a manual
	 * deactivate/reactivate.
	 */
	public function maybe_flush_on_upgrade() {
		if ( get_option( 'p20_rf_flush_ver' ) !== P20_RF_VERSION ) {
			flush_rewrite_rules();
			update_option( 'p20_rf_flush_ver', P20_RF_VERSION );
		}
	}

	public function activate() {
		require_once P20_RF_PATH . 'includes/class-p20-activator.php';
		P20_RF_Activator::activate();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'p20-raumfinder', false, dirname( plugin_basename( P20_RF_FILE ) ) . '/languages' );
	}
}

P20_Raumfinder::instance();
