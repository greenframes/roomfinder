<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central place for the fixed data structures (seating types, meta keys,
 * feature states, ...) so the rest of the plugin never hardcodes strings
 * in more than one place.
 */
class P20_RF_Data {

	/**
	 * Fixed seating / Bestuhlung types. Kept as a structural constant
	 * (not editable in the backend) because each one has its own capacity
	 * field on every room - but new types can be added here later by a
	 * developer without touching the data model.
	 */
	public static function seating_types() {
		return array(
			'theater'     => __( 'Theater / Reihe', 'p20-raumfinder' ),
			'parlament'   => __( 'Parlament', 'p20-raumfinder' ),
			'u_form'      => __( 'U-Form', 'p20-raumfinder' ),
			'block'       => __( 'Block', 'p20-raumfinder' ),
			'stuhlkreis'  => __( 'Stuhlkreis', 'p20-raumfinder' ),
			'individuell' => __( 'Individuelle Bestuhlung', 'p20-raumfinder' ),
		);
	}

	/**
	 * Duration options offered in the wizard and used for price lookups.
	 */
	public static function durations() {
		return array(
			'2h'        => __( '2 Stunden', 'p20-raumfinder' ),
			'4h'        => __( '4 Stunden', 'p20-raumfinder' ),
			'ganztags'  => __( 'Ganztags', 'p20-raumfinder' ),
			'unsicher'  => __( 'Noch nicht sicher', 'p20-raumfinder' ),
		);
	}

	/**
	 * Feature availability states: fest verbaut / optional buchbar / nicht verfuegbar.
	 */
	public static function feature_states() {
		return array(
			'vorhanden' => __( 'Im Raum vorhanden', 'p20-raumfinder' ),
			'optional'  => __( 'Optional verfuegbar / zubuchbar', 'p20-raumfinder' ),
			'nicht'     => __( 'Nicht verfuegbar', 'p20-raumfinder' ),
		);
	}

	public static function default_event_types() {
		return array( 'Meeting', 'Besprechung', 'Seminar', 'Schulung', 'Workshop', 'Vortrag', 'Tagung', 'Konferenz', 'Coaching', 'Event', 'Hybrides Meeting' );
	}

	public static function default_features() {
		return array( 'WLAN', 'Grossbildschirm', 'Praesentationstechnik', 'Beamer', 'Mikrofon', 'PA-Anlage / Beschallung', 'Flipchart', 'Whiteboard', 'Moderationsmaterial', 'Videokonferenztechnik', 'Stromanschluesse', 'Barrierefrei' );
	}

	public static function default_catering() {
		return array( 'Getraenke', 'Kaffee', 'Fruehstueck', 'Fingerfood', 'Kuchen & Desserts', 'Buffet', 'Menue', 'Individuelles Catering' );
	}
}

/**
 * Decodes HTML entities (WordPress commonly stores term/post text with
 * entity-encoded characters, e.g. "&amp;"). Needed for any text sent
 * through the REST API as JSON, since the frontend inserts it via
 * textContent, not innerHTML, so raw entities would show up literally.
 */
function p20_rf_text( $value ) {
	if ( '' === $value || null === $value ) {
		return $value;
	}
	return wp_specialchars_decode( (string) $value, ENT_QUOTES );
}

/**
 * Small formatting helper for price display, never a binding final price.
 * Uses the real UTF-8 Euro sign, not an HTML entity, since this value is
 * sent through the REST API as JSON and rendered via textContent.
 */
function p20_rf_format_price( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( is_numeric( $value ) ) {
		return 'ab ' . number_format_i18n( (float) $value, 0 ) . ' €';
	}
	return p20_rf_text( $value );
}

function p20_rf_get_template( $name, $args = array() ) {
	if ( $args ) {
		extract( $args ); // phpcs:ignore
	}
	$path = P20_RF_PATH . 'templates/' . $name . '.php';
	if ( file_exists( $path ) ) {
		include $path;
	}
}
