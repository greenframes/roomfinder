<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a handful of starter rooms on first activation, based on the
 * information published on alte-schraubenfabrik.de/tagungsraeume-mieten/.
 * Runs once (guarded by an option flag) - everything created here is a
 * normal p20_room post and can be freely edited, deactivated or deleted
 * afterwards in the backend. Nothing here is hardcoded into the frontend.
 */
class P20_RF_Seeder {

	public static function maybe_seed() {
		if ( get_option( 'p20_rf_rooms_seeded' ) ) {
			return;
		}

		$feature_ids  = self::term_ids_by_name( P20_RF_TAX_FEATURE, array( 'WLAN', 'Grossbildschirm', 'PA-Anlage / Beschallung', 'Praesentationstechnik', 'Flipchart' ) );
		$catering_ids = self::term_ids_by_name( P20_RF_TAX_CATERING, array( 'Getraenke', 'Kaffee', 'Individuelles Catering' ) );
		$seminar_event_ids = self::term_ids_by_name( P20_RF_TAX_EVENT, array( 'Seminar', 'Schulung', 'Workshop', 'Meeting' ) );
		$event_event_ids   = self::term_ids_by_name( P20_RF_TAX_EVENT, array( 'Tagung', 'Konferenz', 'Event' ) );

		$rooms = array(
			array(
				'title'    => 'Seminarraum 1',
				'desc'     => 'Heller, flexibel bestuhlbarer Seminarraum in der historischen Schraubenfabrik - ideal fuer Workshops, Schulungen und kleinere Tagungen.',
				'size'     => 78,
				'cap_min'  => 4,
				'cap_max'  => 28,
				'seating'  => array( 'theater' => 28, 'parlament' => 22, 'u_form' => 16, 'block' => 18, 'stuhlkreis' => 20 ),
				'events'   => $seminar_event_ids,
				'price'    => array( '2h' => 120, '4h' => 180, 'fullday' => 280 ),
			),
			array(
				'title'    => 'Seminarraum 2',
				'desc'     => 'Ruhiger Raum mit viel Tageslicht, perfekt fuer konzentrierte Seminare und Besprechungen in kleiner bis mittlerer Runde.',
				'size'     => 72,
				'cap_min'  => 4,
				'cap_max'  => 25,
				'seating'  => array( 'theater' => 25, 'parlament' => 20, 'u_form' => 15, 'block' => 16, 'stuhlkreis' => 18 ),
				'events'   => $seminar_event_ids,
				'price'    => array( '2h' => 120, '4h' => 180, 'fullday' => 280 ),
			),
			array(
				'title'    => 'Seminarraum 3',
				'desc'     => 'Industrieller Charme trifft moderne Tagungstechnik - vielseitig einsetzbar fuer Trainings und Coachings.',
				'size'     => 80,
				'cap_min'  => 4,
				'cap_max'  => 30,
				'seating'  => array( 'theater' => 30, 'parlament' => 24, 'u_form' => 18, 'block' => 20, 'stuhlkreis' => 22 ),
				'events'   => $seminar_event_ids,
				'price'    => array( '2h' => 120, '4h' => 180, 'fullday' => 280 ),
			),
			array(
				'title'    => 'Grosser Veranstaltungsraum',
				'desc'     => 'Der grosse Veranstaltungsraum bietet Platz fuer Tagungen, Konferenzen und Events mit variabler Bestuhlung.',
				'size'     => 150,
				'cap_min'  => 20,
				'cap_max'  => 80,
				'seating'  => array( 'theater' => 80, 'parlament' => 60, 'u_form' => 30, 'block' => 40, 'stuhlkreis' => 45 ),
				'events'   => $event_event_ids,
				'price'    => array( '2h' => 250, '4h' => 380, 'fullday' => 590 ),
			),
			array(
				'title'    => 'Smart Room',
				'desc'     => 'Der Smart Room verbindet Arbeiten, Besprechen und Uebernachten in einem flexiblen Raumkonzept.',
				'size'     => 45,
				'cap_min'  => 2,
				'cap_max'  => 12,
				'seating'  => array( 'block' => 10, 'stuhlkreis' => 12, 'individuell' => 12 ),
				'events'   => self::term_ids_by_name( P20_RF_TAX_EVENT, array( 'Meeting', 'Besprechung', 'Coaching' ) ),
				'price'    => array( '2h' => 90, '4h' => 140, 'fullday' => 220 ),
			),
			array(
				'title'    => 'Smart Suite „Denkfabrik“',
				'desc'     => 'Die Smart Suite Denkfabrik ist auf laengere Aufenthalte, Meetings und konzentriertes Arbeiten ausgelegt.',
				'size'     => 55,
				'cap_min'  => 2,
				'cap_max'  => 14,
				'seating'  => array( 'block' => 12, 'u_form' => 10, 'individuell' => 14 ),
				'events'   => self::term_ids_by_name( P20_RF_TAX_EVENT, array( 'Meeting', 'Coaching', 'Hybrides Meeting' ) ),
				'price'    => array( '2h' => 100, '4h' => 160, 'fullday' => 250 ),
			),
		);

		$order = 0;
		foreach ( $rooms as $room ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => P20_RF_CPT,
					'post_status'  => 'publish',
					'post_title'   => $room['title'],
					'post_content' => $room['desc'],
					'menu_order'   => $order,
				)
			);
			$order++;

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, 'p20_short_desc', $room['desc'] );
			update_post_meta( $post_id, 'p20_size_sqm', $room['size'] );
			update_post_meta( $post_id, 'p20_capacity_min', $room['cap_min'] );
			update_post_meta( $post_id, 'p20_capacity_max', $room['cap_max'] );
			update_post_meta( $post_id, 'p20_active', '1' );
			update_post_meta( $post_id, 'p20_price_2h', $room['price']['2h'] );
			update_post_meta( $post_id, 'p20_price_4h', $room['price']['4h'] );
			update_post_meta( $post_id, 'p20_price_fullday', $room['price']['fullday'] );
			update_post_meta( $post_id, 'p20_price_on_request', '0' );

			foreach ( P20_RF_Data::seating_types() as $key => $label ) {
				update_post_meta( $post_id, 'p20_seating_' . $key, isset( $room['seating'][ $key ] ) ? $room['seating'][ $key ] : '' );
			}

			$states = array();
			foreach ( $feature_ids as $fid ) {
				$states[ $fid ] = 'vorhanden';
			}
			update_post_meta( $post_id, 'p20_feature_states', $states );

			wp_set_object_terms( $post_id, $catering_ids, P20_RF_TAX_CATERING, false );
			wp_set_object_terms( $post_id, $room['events'], P20_RF_TAX_EVENT, false );
		}

		update_option( 'p20_rf_rooms_seeded', 1 );
	}

	private static function term_ids_by_name( $taxonomy, $names ) {
		$ids = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( $term ) {
				$ids[] = $term->term_id;
			}
		}
		return $ids;
	}
}
