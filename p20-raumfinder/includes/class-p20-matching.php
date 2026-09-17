<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scoring engine. Filters out rooms that cannot physically host the
 * request, then ranks the rest with a transparent, weighted score.
 *
 * Weights (out of 100 points total):
 *   Personenzahl / Kapazitaet   40  (hard requirement, see filter)
 *   Bestuhlung                  20  (hard requirement if chosen)
 *   Veranstaltungsart           20
 *   Technik                     15
 *   Verpflegung                  5
 */
class P20_RF_Matching {

	const W_CAPACITY  = 40;
	const W_SEATING   = 20;
	const W_EVENT     = 20;
	const W_FEATURES  = 15;
	const W_CATERING  = 5;

	/**
	 * @param array $criteria {
	 *   @type int    $persons
	 *   @type string $event_type   term slug or ''
	 *   @type string $duration     2h|4h|ganztags|unsicher
	 *   @type string $seating      seating key or '' / 'unsicher'
	 *   @type array  $features     array of feature term ids requested
	 *   @type array  $catering     array of catering term ids requested
	 * }
	 * @return array List of ['room' => WP_Post data array, 'score' => int, 'label' => string, 'reasons' => array]
	 */
	public static function find_matches( $criteria ) {
		$persons  = isset( $criteria['persons'] ) ? absint( $criteria['persons'] ) : 0;
		$event    = isset( $criteria['event_type'] ) ? sanitize_key( $criteria['event_type'] ) : '';
		$duration = isset( $criteria['duration'] ) ? sanitize_key( $criteria['duration'] ) : '';
		$seating  = isset( $criteria['seating'] ) ? sanitize_key( $criteria['seating'] ) : '';
		$features = isset( $criteria['features'] ) && is_array( $criteria['features'] ) ? array_map( 'absint', $criteria['features'] ) : array();
		$catering = isset( $criteria['catering'] ) && is_array( $criteria['catering'] ) ? array_map( 'absint', $criteria['catering'] ) : array();

		$rooms = get_posts(
			array(
				'post_type'      => P20_RF_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$results  = array();
		$fallback = array();

		foreach ( $rooms as $room ) {
			$active = get_post_meta( $room->ID, 'p20_active', true );
			if ( '0' === $active ) {
				continue;
			}

			$cap_min = (int) get_post_meta( $room->ID, 'p20_capacity_min', true );
			$cap_max = (int) get_post_meta( $room->ID, 'p20_capacity_max', true );

			$seating_cap = null;
			if ( $seating && 'unsicher' !== $seating && array_key_exists( $seating, P20_RF_Data::seating_types() ) ) {
				$raw = get_post_meta( $room->ID, 'p20_seating_' . $seating, true );
				$seating_cap = ( '' === $raw ) ? 0 : (int) $raw;
			}

			// HARD FILTER: capacity.
			if ( $persons > 0 ) {
				if ( $cap_max > 0 && $persons > $cap_max ) {
					self::maybe_add_fallback( $fallback, $room, $persons, $cap_max );
					continue;
				}
				if ( $cap_min > 0 && $persons < $cap_min ) {
					// A room requiring more minimum guests than requested is not a fit either.
					continue;
				}
			}

			// HARD FILTER: chosen seating not offered / not enough capacity.
			if ( null !== $seating_cap ) {
				if ( 0 === $seating_cap ) {
					continue; // Bestuhlung nicht angeboten.
				}
				if ( $persons > 0 && $persons > $seating_cap ) {
					continue; // Gewaehlte Bestuhlung fasst nicht genug Personen.
				}
			}

			$score   = 0;
			$reasons = array();
			$max_score = 0;

			// Capacity score: closer fit (not oversized) scores higher.
			$max_score += self::W_CAPACITY;
			if ( $persons > 0 && $cap_max > 0 ) {
				$ratio = $persons / $cap_max;
				if ( $ratio >= 0.4 ) {
					$score += self::W_CAPACITY;
				} elseif ( $ratio >= 0.2 ) {
					$score += self::W_CAPACITY * 0.75;
				} else {
					$score += self::W_CAPACITY * 0.5;
				}
				$reasons[] = 'capacity';
			} else {
				$score += self::W_CAPACITY * 0.5;
			}

			// Seating score.
			$max_score += self::W_SEATING;
			if ( null !== $seating_cap && $seating_cap > 0 ) {
				$score += self::W_SEATING;
				$reasons[] = 'seating';
			} elseif ( '' === $seating || 'unsicher' === $seating ) {
				$score += self::W_SEATING * 0.6;
			}

			// Event type score.
			$max_score += self::W_EVENT;
			if ( $event ) {
				$room_events = wp_get_object_terms( $room->ID, P20_RF_TAX_EVENT, array( 'fields' => 'slugs' ) );
				$room_events = is_wp_error( $room_events ) ? array() : $room_events;
				if ( in_array( $event, $room_events, true ) ) {
					$score += self::W_EVENT;
					$reasons[] = 'event';
				}
			} else {
				$score += self::W_EVENT * 0.5;
			}

			// Feature score: vorhanden = full points, optional = partial, missing = 0.
			$max_score += self::W_FEATURES;
			if ( ! empty( $features ) ) {
				$states     = get_post_meta( $room->ID, 'p20_feature_states', true );
				$states     = is_array( $states ) ? $states : array();
				$per_feat   = self::W_FEATURES / count( $features );
				$feat_score = 0;
				foreach ( $features as $fid ) {
					$state = isset( $states[ $fid ] ) ? $states[ $fid ] : 'nicht';
					if ( 'vorhanden' === $state ) {
						$feat_score += $per_feat;
					} elseif ( 'optional' === $state ) {
						$feat_score += $per_feat * 0.6;
					}
				}
				$score += $feat_score;
				if ( $feat_score > 0 ) {
					$reasons[] = 'features';
				}
			} else {
				$score += self::W_FEATURES * 0.5;
			}

			// Catering score.
			$max_score += self::W_CATERING;
			if ( ! empty( $catering ) ) {
				$room_catering = wp_get_object_terms( $room->ID, P20_RF_TAX_CATERING, array( 'fields' => 'ids' ) );
				$room_catering = is_wp_error( $room_catering ) ? array() : $room_catering;
				$matched       = count( array_intersect( $catering, $room_catering ) );
				if ( count( $catering ) > 0 ) {
					$score += self::W_CATERING * ( $matched / count( $catering ) );
					if ( $matched > 0 ) {
						$reasons[] = 'catering';
					}
				}
			} else {
				$score += self::W_CATERING * 0.5;
			}

			$percent = $max_score > 0 ? round( ( $score / $max_score ) * 100 ) : 0;

			$results[] = array(
				'room_id' => $room->ID,
				'score'   => $percent,
				'reasons' => $reasons,
				'label'   => self::score_to_label( $percent ),
			);
		}

		usort(
			$results,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array(
			'matches'  => $results,
			'fallback' => $fallback,
		);
	}

	private static function maybe_add_fallback( &$fallback, $room, $persons, $cap_max ) {
		if ( $cap_max <= 0 ) {
			return;
		}
		// Remember rooms that are too small, in case nothing fits - used to
		// suggest the "next bigger room" alternative.
		$fallback[] = array(
			'room_id' => $room->ID,
			'cap_max' => $cap_max,
			'delta'   => $persons - $cap_max,
		);
	}

	public static function score_to_label( $percent ) {
		if ( $percent >= 85 ) {
			return __( 'Besonders passend', 'p20-raumfinder' );
		}
		if ( $percent >= 65 ) {
			return __( 'Sehr gut geeignet', 'p20-raumfinder' );
		}
		return __( 'Alternative', 'p20-raumfinder' );
	}
}
