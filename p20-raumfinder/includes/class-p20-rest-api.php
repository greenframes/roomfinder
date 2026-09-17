<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API surface used by the frontend wizard. All endpoints are read-only
 * except /request, which is rate-limited-friendly (no PII stored beyond the
 * inquiry) and protected by a nonce plus honeypot field.
 */
class P20_RF_REST_API {

	const NS = 'p20/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/match',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'match' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'persons'    => array( 'type' => 'integer' ),
					'event_type' => array( 'type' => 'string' ),
					'duration'   => array( 'type' => 'string' ),
					'seating'    => array( 'type' => 'string' ),
					'features'   => array( 'type' => 'array' ),
					'catering'   => array( 'type' => 'array' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/room/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_room' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function check_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public function get_config( $request ) {
		$settings = P20_RF_Settings::get_settings();

		$event_terms = get_terms( array( 'taxonomy' => P20_RF_TAX_EVENT, 'hide_empty' => false ) );
		$feature_terms = get_terms(
			array(
				'taxonomy'   => P20_RF_TAX_FEATURE,
				'hide_empty' => false,
				'meta_query' => array(), // active filtering happens via settings below.
			)
		);
		$catering_terms = get_terms( array( 'taxonomy' => P20_RF_TAX_CATERING, 'hide_empty' => false ) );

		$active_features = isset( $settings['active_features'] ) ? (array) $settings['active_features'] : array();

		$features = array();
		foreach ( (array) $feature_terms as $term ) {
			if ( ! empty( $active_features ) && ! in_array( (string) $term->term_id, array_map( 'strval', $active_features ), true ) ) {
				continue;
			}
			$features[] = array(
				'id'   => $term->term_id,
				'name' => p20_rf_text( $term->name ),
				'slug' => $term->slug,
			);
		}

		$catering = array();
		foreach ( (array) $catering_terms as $term ) {
			$catering[] = array(
				'id'   => $term->term_id,
				'name' => p20_rf_text( $term->name ),
				'slug' => $term->slug,
			);
		}

		$events = array();
		foreach ( (array) $event_terms as $term ) {
			$events[] = array(
				'id'   => $term->term_id,
				'name' => p20_rf_text( $term->name ),
				'slug' => $term->slug,
			);
		}

		return rest_ensure_response(
			array(
				'events'        => $events,
				'features'      => $features,
				'catering'      => $catering,
				'seating_types' => P20_RF_Data::seating_types(),
				'durations'     => P20_RF_Data::durations(),
				'settings'      => array(
					'intro_headline' => p20_rf_text( $settings['intro_headline'] ),
					'intro_subline'  => p20_rf_text( $settings['intro_subline'] ),
					'privacy_text'   => p20_rf_text( $settings['privacy_text'] ),
					'privacy_url'    => $settings['privacy_url'],
				),
			)
		);
	}

	public function match( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$criteria = array(
			'persons'    => isset( $params['persons'] ) ? absint( $params['persons'] ) : 0,
			'event_type' => isset( $params['event_type'] ) ? sanitize_text_field( $params['event_type'] ) : '',
			'duration'   => isset( $params['duration'] ) ? sanitize_key( $params['duration'] ) : '',
			'seating'    => isset( $params['seating'] ) ? sanitize_key( $params['seating'] ) : '',
			'features'   => isset( $params['features'] ) && is_array( $params['features'] ) ? array_map( 'absint', $params['features'] ) : array(),
			'catering'   => isset( $params['catering'] ) && is_array( $params['catering'] ) ? array_map( 'absint', $params['catering'] ) : array(),
		);

		$result = P20_RF_Matching::find_matches( $criteria );

		$matches = array();
		foreach ( $result['matches'] as $m ) {
			$room = $this->format_room( $m['room_id'], $criteria['duration'], $criteria['seating'] );
			if ( ! $room ) {
				continue;
			}
			$room['score']   = $m['score'];
			$room['label']   = $m['label'];
			$matches[]       = $room;
		}

		$fallback_rooms = array();
		if ( empty( $matches ) && ! empty( $result['fallback'] ) ) {
			usort(
				$result['fallback'],
				function ( $a, $b ) {
					return $a['delta'] <=> $b['delta'];
				}
			);
			foreach ( array_slice( $result['fallback'], 0, 3 ) as $f ) {
				$room = $this->format_room( $f['room_id'], $criteria['duration'], $criteria['seating'] );
				if ( $room ) {
					$fallback_rooms[] = $room;
				}
			}
		}

		return rest_ensure_response(
			array(
				'matches'  => $matches,
				'fallback' => $fallback_rooms,
				'has_exact' => ! empty( $matches ),
			)
		);
	}

	public function get_room( $request ) {
		$id   = absint( $request['id'] );
		$room = $this->format_room( $id, '', '', true );
		if ( ! $room ) {
			return new WP_Error( 'p20_rf_not_found', __( 'Raum nicht gefunden.', 'p20-raumfinder' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $room );
	}

	private function format_room( $room_id, $duration = '', $seating = '', $full = false ) {
		$post = get_post( $room_id );
		if ( ! $post || P20_RF_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$active = get_post_meta( $room_id, 'p20_active', true );
		if ( '0' === $active ) {
			return null;
		}

		$gallery_raw = get_post_meta( $room_id, 'p20_gallery', true );
		$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();
		$gallery     = array();
		foreach ( $gallery_ids as $gid ) {
			$src = wp_get_attachment_image_src( $gid, 'large' );
			if ( $src ) {
				$gallery[] = $src[0];
			}
		}

		$thumb = get_the_post_thumbnail_url( $room_id, 'large' );

		$seating_caps = array();
		foreach ( P20_RF_Data::seating_types() as $key => $label ) {
			$val = get_post_meta( $room_id, 'p20_seating_' . $key, true );
			if ( '' !== $val && (int) $val > 0 ) {
				$seating_caps[ $key ] = array(
					'label'    => $label,
					'capacity' => (int) $val,
				);
			}
		}

		$states = get_post_meta( $room_id, 'p20_feature_states', true );
		$states = is_array( $states ) ? $states : array();
		$feature_terms = get_terms( array( 'taxonomy' => P20_RF_TAX_FEATURE, 'hide_empty' => false ) );
		$features = array();
		foreach ( (array) $feature_terms as $term ) {
			$state = isset( $states[ $term->term_id ] ) ? $states[ $term->term_id ] : 'nicht';
			if ( 'nicht' === $state ) {
				continue;
			}
			$features[] = array(
				'name'  => p20_rf_text( $term->name ),
				'state' => $state,
			);
		}

		$catering_terms = wp_get_object_terms( $room_id, P20_RF_TAX_CATERING, array( 'fields' => 'names' ) );
		$catering_terms = is_wp_error( $catering_terms ) ? array() : array_map( 'p20_rf_text', $catering_terms );

		$event_terms = wp_get_object_terms( $room_id, P20_RF_TAX_EVENT, array( 'fields' => 'names' ) );
		$event_terms = is_wp_error( $event_terms ) ? array() : array_map( 'p20_rf_text', $event_terms );

		$on_request = '1' === get_post_meta( $room_id, 'p20_price_on_request', true );
		$prices     = array(
			'2h'       => get_post_meta( $room_id, 'p20_price_2h', true ),
			'4h'       => get_post_meta( $room_id, 'p20_price_4h', true ),
			'ganztags' => get_post_meta( $room_id, 'p20_price_fullday', true ),
			'individual' => p20_rf_text( get_post_meta( $room_id, 'p20_price_individual', true ) ),
			'on_request' => $on_request,
		);

		$display_price = '';
		if ( $on_request ) {
			$display_price = __( 'Preis auf Anfrage', 'p20-raumfinder' );
		} elseif ( $duration && isset( $prices[ $duration ] ) && '' !== $prices[ $duration ] && 'individual' !== $duration ) {
			$display_price = p20_rf_format_price( $prices[ $duration ] ) . ' / ' . P20_RF_Data::durations()[ $duration ];
		} elseif ( '' !== $prices['4h'] ) {
			$display_price = p20_rf_format_price( $prices['4h'] ) . ' / 4 ' . __( 'Stunden', 'p20-raumfinder' );
		} elseif ( $prices['individual'] ) {
			$display_price = p20_rf_text( $prices['individual'] );
		} else {
			$display_price = __( 'Preis auf Anfrage', 'p20-raumfinder' );
		}

		$data = array(
			'id'             => $room_id,
			'name'           => p20_rf_text( get_the_title( $room_id ) ),
			'short_desc'     => p20_rf_text( get_post_meta( $room_id, 'p20_short_desc', true ) ),
			'size_sqm'       => get_post_meta( $room_id, 'p20_size_sqm', true ),
			'capacity_min'   => get_post_meta( $room_id, 'p20_capacity_min', true ),
			'capacity_max'   => get_post_meta( $room_id, 'p20_capacity_max', true ),
			'image'          => $thumb ? $thumb : '',
			'seating'        => $seating_caps,
			'features'       => $features,
			'catering'       => array_values( $catering_terms ),
			'event_types'    => array_values( $event_terms ),
			'price_display'  => $display_price,
			'permalink'      => get_permalink( $room_id ),
		);

		if ( $full ) {
			$data['description'] = apply_filters( 'the_content', $post->post_content );
			$data['gallery']     = $gallery;
			$data['prices']      = $prices;
		}

		return $data;
	}

	public function submit_request( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$nonce = isset( $params['nonce'] ) ? $params['nonce'] : $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'p20_rf_request' ) ) {
			return new WP_Error( 'p20_rf_invalid_nonce', __( 'Sicherheitspruefung fehlgeschlagen. Bitte laden Sie die Seite neu.', 'p20-raumfinder' ), array( 'status' => 403 ) );
		}

		// Honeypot field - must stay empty.
		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		$first_name = isset( $params['first_name'] ) ? sanitize_text_field( $params['first_name'] ) : '';
		$last_name  = isset( $params['last_name'] ) ? sanitize_text_field( $params['last_name'] ) : '';
		$email      = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$company    = isset( $params['company'] ) ? sanitize_text_field( $params['company'] ) : '';
		$phone      = isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '';
		$date       = isset( $params['date'] ) ? sanitize_text_field( $params['date'] ) : '';
		$time       = isset( $params['time'] ) ? sanitize_text_field( $params['time'] ) : '';
		$message    = isset( $params['message'] ) ? sanitize_textarea_field( $params['message'] ) : '';
		$consent    = ! empty( $params['consent'] );
		$room_id    = isset( $params['room_id'] ) ? absint( $params['room_id'] ) : 0;

		if ( ! $first_name || ! $last_name || ! is_email( $email ) || ! $consent ) {
			return new WP_Error( 'p20_rf_invalid_data', __( 'Bitte fuellen Sie alle Pflichtfelder korrekt aus.', 'p20-raumfinder' ), array( 'status' => 400 ) );
		}

		$criteria = array(
			'persons'    => isset( $params['persons'] ) ? absint( $params['persons'] ) : '',
			'event_type' => isset( $params['event_type_label'] ) ? sanitize_text_field( $params['event_type_label'] ) : '',
			'duration'   => isset( $params['duration_label'] ) ? sanitize_text_field( $params['duration_label'] ) : '',
			'seating'    => isset( $params['seating_label'] ) ? sanitize_text_field( $params['seating_label'] ) : '',
			'features'   => isset( $params['features_labels'] ) && is_array( $params['features_labels'] ) ? array_map( 'sanitize_text_field', $params['features_labels'] ) : array(),
			'catering'   => isset( $params['catering_labels'] ) && is_array( $params['catering_labels'] ) ? array_map( 'sanitize_text_field', $params['catering_labels'] ) : array(),
		);

		$contact = compact( 'first_name', 'last_name', 'email', 'company', 'phone', 'date', 'time', 'message' );

		$sent = P20_RF_Mailer::send_inquiry( $room_id, $contact, $criteria );

		if ( ! $sent ) {
			return new WP_Error( 'p20_rf_mail_failed', __( 'Ihre Anfrage konnte nicht versendet werden. Bitte versuchen Sie es spaeter erneut.', 'p20-raumfinder' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
