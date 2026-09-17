<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends inquiry notifications via wp_mail() so existing SMTP setups are
 * used automatically. No third-party services involved.
 */
class P20_RF_Mailer {

	public static function send_inquiry( $room_id, $contact, $criteria ) {
		$settings   = P20_RF_Settings::get_settings();
		$to         = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		$room_name  = $room_id ? p20_rf_text( get_the_title( $room_id ) ) : __( 'Individuelle Anfrage', 'p20-raumfinder' );
		$site_name  = get_bloginfo( 'name' );

		$subject = sprintf( '[%s] %s: %s', $site_name, __( 'Neue Raumanfrage', 'p20-raumfinder' ), $room_name );

		$lines   = array();
		$lines[] = __( 'Neue Anfrage ueber den Raumfinder', 'p20-raumfinder' );
		$lines[] = str_repeat( '-', 40 );
		$lines[] = __( 'Raum:', 'p20-raumfinder' ) . ' ' . $room_name;
		if ( ! empty( $criteria['persons'] ) ) {
			$lines[] = __( 'Personen:', 'p20-raumfinder' ) . ' ' . $criteria['persons'];
		}
		if ( ! empty( $criteria['event_type'] ) ) {
			$lines[] = __( 'Anlass:', 'p20-raumfinder' ) . ' ' . $criteria['event_type'];
		}
		if ( ! empty( $criteria['duration'] ) ) {
			$lines[] = __( 'Dauer:', 'p20-raumfinder' ) . ' ' . $criteria['duration'];
		}
		if ( ! empty( $criteria['seating'] ) ) {
			$lines[] = __( 'Bestuhlung:', 'p20-raumfinder' ) . ' ' . $criteria['seating'];
		}
		if ( ! empty( $criteria['features'] ) ) {
			$lines[] = __( 'Technik:', 'p20-raumfinder' ) . ' ' . implode( ', ', $criteria['features'] );
		}
		if ( ! empty( $criteria['catering'] ) ) {
			$lines[] = __( 'Verpflegung:', 'p20-raumfinder' ) . ' ' . implode( ', ', $criteria['catering'] );
		}
		$lines[] = '';
		$lines[] = __( 'Kontaktdaten', 'p20-raumfinder' );
		$lines[] = str_repeat( '-', 40 );
		$lines[] = __( 'Name:', 'p20-raumfinder' ) . ' ' . $contact['first_name'] . ' ' . $contact['last_name'];
		if ( ! empty( $contact['company'] ) ) {
			$lines[] = __( 'Unternehmen:', 'p20-raumfinder' ) . ' ' . $contact['company'];
		}
		$lines[] = __( 'E-Mail:', 'p20-raumfinder' ) . ' ' . $contact['email'];
		if ( ! empty( $contact['phone'] ) ) {
			$lines[] = __( 'Telefon:', 'p20-raumfinder' ) . ' ' . $contact['phone'];
		}
		if ( ! empty( $contact['date'] ) ) {
			$lines[] = __( 'Wunschtermin:', 'p20-raumfinder' ) . ' ' . $contact['date'] . ( ! empty( $contact['time'] ) ? ' ' . $contact['time'] : '' );
		}
		if ( ! empty( $contact['message'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Nachricht:', 'p20-raumfinder' );
			$lines[] = $contact['message'];
		}
		$lines[] = '';
		$lines[] = __( 'Hinweis: Dies ist eine unverbindliche Anfrage, keine verbindliche Buchung.', 'p20-raumfinder' );

		$body    = implode( "\n", $lines );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( is_email( $contact['email'] ) ) {
			$headers[] = 'Reply-To: ' . $contact['first_name'] . ' ' . $contact['last_name'] . ' <' . $contact['email'] . '>';
		}

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( $sent && is_email( $contact['email'] ) ) {
			$placeholders = array(
				'{vorname}'  => $contact['first_name'],
				'{nachname}' => $contact['last_name'],
				'{raum}'     => $room_name,
				'{webseite}' => $site_name,
			);

			$confirm_subject = strtr( $settings['confirmation_subject'], $placeholders );
			$confirm_body    = strtr( $settings['confirmation_message'], $placeholders );

			wp_mail( $contact['email'], $confirm_subject, $confirm_body );
		}

		return $sent;
	}
}
