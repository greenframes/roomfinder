<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All meta boxes for editing a single room. Built to be usable by staff
 * without WordPress or development knowledge - plain labelled fields,
 * grouped by topic, matching the admin UX described in the project brief.
 */
class P20_RF_Meta_Boxes {

	const NONCE_ACTION = 'p20_rf_save_room';
	const NONCE_NAME   = 'p20_rf_nonce';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post_' . P20_RF_CPT, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ) {
		global $post_type;
		if ( P20_RF_CPT !== $post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'p20-rf-admin', P20_RF_URL . 'assets/css/admin.css', array(), P20_RF_VERSION );
		wp_enqueue_script( 'p20-rf-admin', P20_RF_URL . 'assets/js/admin.js', array( 'jquery' ), P20_RF_VERSION, true );
	}

	public function register() {
		add_meta_box( 'p20_rf_general', __( 'Allgemein', 'p20-raumfinder' ), array( $this, 'render_general' ), P20_RF_CPT, 'normal', 'high' );
		add_meta_box( 'p20_rf_gallery', __( 'Bildergalerie', 'p20-raumfinder' ), array( $this, 'render_gallery' ), P20_RF_CPT, 'normal', 'default' );
		add_meta_box( 'p20_rf_capacity', __( 'Kapazitaet & Groesse', 'p20-raumfinder' ), array( $this, 'render_capacity' ), P20_RF_CPT, 'normal', 'default' );
		add_meta_box( 'p20_rf_seating', __( 'Bestuhlung', 'p20-raumfinder' ), array( $this, 'render_seating' ), P20_RF_CPT, 'normal', 'default' );
		add_meta_box( 'p20_rf_features', __( 'Ausstattung', 'p20-raumfinder' ), array( $this, 'render_features' ), P20_RF_CPT, 'normal', 'default' );
		add_meta_box( 'p20_rf_catering', __( 'Verpflegung / Service', 'p20-raumfinder' ), array( $this, 'render_catering' ), P20_RF_CPT, 'normal', 'default' );
		add_meta_box( 'p20_rf_prices', __( 'Preise', 'p20-raumfinder' ), array( $this, 'render_prices' ), P20_RF_CPT, 'normal', 'default' );
		add_meta_box( 'p20_rf_status', __( 'Status & Sortierung', 'p20-raumfinder' ), array( $this, 'render_status' ), P20_RF_CPT, 'side', 'high' );
	}

	private function nonce_field() {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
	}

	private function meta( $post_id, $key, $default = '' ) {
		$val = get_post_meta( $post_id, $key, true );
		return ( '' === $val || null === $val ) ? $default : $val;
	}

	/* ---------------------------------------------------------------- */

	public function render_general( $post ) {
		$this->nonce_field();
		$internal = $this->meta( $post->ID, 'p20_internal_name' );
		$short    = $this->meta( $post->ID, 'p20_short_desc' );
		?>
		<p>
			<label for="p20_internal_name"><strong><?php esc_html_e( 'Interne Bezeichnung', 'p20-raumfinder' ); ?></strong><br>
			<span class="description"><?php esc_html_e( 'Nur fuer die interne Verwaltung sichtbar, nicht im Frontend.', 'p20-raumfinder' ); ?></span></label><br>
			<input type="text" class="widefat" id="p20_internal_name" name="p20_internal_name" value="<?php echo esc_attr( $internal ); ?>">
		</p>
		<p>
			<label for="p20_short_desc"><strong><?php esc_html_e( 'Kurzbeschreibung', 'p20-raumfinder' ); ?></strong><br>
			<span class="description"><?php esc_html_e( 'Wird auf den Ergebnis-Karten im Raumfinder angezeigt (max. ca. 140 Zeichen).', 'p20-raumfinder' ); ?></span></label><br>
			<textarea class="widefat" rows="3" id="p20_short_desc" name="p20_short_desc" maxlength="200"><?php echo esc_textarea( $short ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Die ausfuehrliche Beschreibung wird im Beitragseditor oberhalb gepflegt. Das Hauptbild wird als Beitragsbild rechts festgelegt.', 'p20-raumfinder' ); ?></p>
		<?php
	}

	public function render_gallery( $post ) {
		$gallery = $this->meta( $post->ID, 'p20_gallery', '' );
		$ids     = $gallery ? array_filter( array_map( 'absint', explode( ',', $gallery ) ) ) : array();
		?>
		<div id="p20-rf-gallery-wrap">
			<ul id="p20-rf-gallery-list" class="p20-rf-gallery-list">
				<?php foreach ( $ids as $id ) :
					$src = wp_get_attachment_image_src( $id, 'thumbnail' );
					if ( ! $src ) { continue; }
					?>
					<li data-id="<?php echo esc_attr( $id ); ?>">
						<img src="<?php echo esc_url( $src[0] ); ?>" alt="">
						<button type="button" class="p20-rf-remove-img button-link" aria-label="<?php esc_attr_e( 'Entfernen', 'p20-raumfinder' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" id="p20_gallery" name="p20_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
			<button type="button" class="button" id="p20-rf-add-gallery"><?php esc_html_e( 'Bilder hinzufuegen', 'p20-raumfinder' ); ?></button>
		</div>
		<?php
	}

	public function render_capacity( $post ) {
		$size = $this->meta( $post->ID, 'p20_size_sqm' );
		$min  = $this->meta( $post->ID, 'p20_capacity_min' );
		$max  = $this->meta( $post->ID, 'p20_capacity_max' );
		?>
		<table class="p20-rf-fields">
			<tr>
				<th><label for="p20_size_sqm"><?php esc_html_e( 'Raumgroesse', 'p20-raumfinder' ); ?></label></th>
				<td><input type="number" min="0" step="0.5" id="p20_size_sqm" name="p20_size_sqm" value="<?php echo esc_attr( $size ); ?>"> m&sup2;</td>
			</tr>
			<tr>
				<th><label for="p20_capacity_min"><?php esc_html_e( 'Mindestpersonenzahl', 'p20-raumfinder' ); ?></label></th>
				<td><input type="number" min="0" step="1" id="p20_capacity_min" name="p20_capacity_min" value="<?php echo esc_attr( $min ); ?>"> <?php esc_html_e( 'Personen', 'p20-raumfinder' ); ?></td>
			</tr>
			<tr>
				<th><label for="p20_capacity_max"><?php esc_html_e( 'Maximale Personenzahl', 'p20-raumfinder' ); ?></label></th>
				<td><input type="number" min="0" step="1" id="p20_capacity_max" name="p20_capacity_max" value="<?php echo esc_attr( $max ); ?>"> <?php esc_html_e( 'Personen', 'p20-raumfinder' ); ?></td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Diese allgemeine Kapazitaet wird im Raumfinder zusaetzlich zu den Bestuhlungs-Kapazitaeten unten geprueft.', 'p20-raumfinder' ); ?></p>
		<?php
	}

	public function render_seating( $post ) {
		?>
		<p class="description"><?php esc_html_e( 'Maximale Personenzahl je Bestuhlungsart. Leer lassen, wenn diese Bestuhlung fuer den Raum nicht angeboten wird.', 'p20-raumfinder' ); ?></p>
		<table class="p20-rf-fields">
			<?php foreach ( P20_RF_Data::seating_types() as $key => $label ) :
				$val = $this->meta( $post->ID, 'p20_seating_' . $key );
				?>
				<tr>
					<th><label for="p20_seating_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="number" min="0" step="1" id="p20_seating_<?php echo esc_attr( $key ); ?>" name="p20_seating[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>"> <?php esc_html_e( 'Personen', 'p20-raumfinder' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public function render_features( $post ) {
		$terms  = get_terms( array( 'taxonomy' => P20_RF_TAX_FEATURE, 'hide_empty' => false ) );
		$states = get_post_meta( $post->ID, 'p20_feature_states', true );
		$states = is_array( $states ) ? $states : array();

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'Noch keine Ausstattungsmerkmale angelegt.', 'p20-raumfinder' ) . ' <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . P20_RF_TAX_FEATURE . '&post_type=' . P20_RF_CPT ) ) . '">' . esc_html__( 'Jetzt anlegen', 'p20-raumfinder' ) . '</a></p>';
			return;
		}
		?>
		<table class="p20-rf-fields p20-rf-tristate">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Merkmal', 'p20-raumfinder' ); ?></th>
					<?php foreach ( P20_RF_Data::feature_states() as $skey => $slabel ) : ?>
						<th><?php echo esc_html( $slabel ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $terms as $term ) :
				$current = isset( $states[ $term->term_id ] ) ? $states[ $term->term_id ] : 'nicht';
				?>
				<tr>
					<td><?php echo esc_html( $term->name ); ?></td>
					<?php foreach ( P20_RF_Data::feature_states() as $skey => $slabel ) : ?>
						<td>
							<label class="screen-reader-text"><?php echo esc_html( $slabel ); ?></label>
							<input type="radio" name="p20_feature_states[<?php echo esc_attr( $term->term_id ); ?>]" value="<?php echo esc_attr( $skey ); ?>" <?php checked( $current, $skey ); ?>>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . P20_RF_TAX_FEATURE . '&post_type=' . P20_RF_CPT ) ); ?>"><?php esc_html_e( '+ Neues Ausstattungsmerkmal anlegen', 'p20-raumfinder' ); ?></a></p>
		<?php
	}

	public function render_catering( $post ) {
		$terms    = get_terms( array( 'taxonomy' => P20_RF_TAX_CATERING, 'hide_empty' => false ) );
		$assigned = wp_get_object_terms( $post->ID, P20_RF_TAX_CATERING, array( 'fields' => 'ids' ) );
		$assigned = is_wp_error( $assigned ) ? array() : $assigned;

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'Noch keine Verpflegungsoptionen angelegt.', 'p20-raumfinder' ) . ' <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . P20_RF_TAX_CATERING . '&post_type=' . P20_RF_CPT ) ) . '">' . esc_html__( 'Jetzt anlegen', 'p20-raumfinder' ) . '</a></p>';
			return;
		}
		?>
		<div class="p20-rf-checkbox-grid">
			<?php foreach ( $terms as $term ) : ?>
				<label>
					<input type="checkbox" name="p20_catering[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $assigned, true ) ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<p><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . P20_RF_TAX_CATERING . '&post_type=' . P20_RF_CPT ) ); ?>"><?php esc_html_e( '+ Neue Verpflegungsoption anlegen', 'p20-raumfinder' ); ?></a></p>
		<?php
	}

	public function render_prices( $post ) {
		$p2h  = $this->meta( $post->ID, 'p20_price_2h' );
		$p4h  = $this->meta( $post->ID, 'p20_price_4h' );
		$pfd  = $this->meta( $post->ID, 'p20_price_fullday' );
		$pind = $this->meta( $post->ID, 'p20_price_individual' );
		$poa  = $this->meta( $post->ID, 'p20_price_on_request' );
		?>
		<table class="p20-rf-fields">
			<tr>
				<th><label for="p20_price_2h"><?php esc_html_e( '2 Stunden', 'p20-raumfinder' ); ?></label></th>
				<td><input type="number" min="0" step="1" id="p20_price_2h" name="p20_price_2h" value="<?php echo esc_attr( $p2h ); ?>"> &euro;</td>
			</tr>
			<tr>
				<th><label for="p20_price_4h"><?php esc_html_e( '4 Stunden', 'p20-raumfinder' ); ?></label></th>
				<td><input type="number" min="0" step="1" id="p20_price_4h" name="p20_price_4h" value="<?php echo esc_attr( $p4h ); ?>"> &euro;</td>
			</tr>
			<tr>
				<th><label for="p20_price_fullday"><?php esc_html_e( 'Ganztags (8 Std.)', 'p20-raumfinder' ); ?></label></th>
				<td><input type="number" min="0" step="1" id="p20_price_fullday" name="p20_price_fullday" value="<?php echo esc_attr( $pfd ); ?>"> &euro;</td>
			</tr>
			<tr>
				<th><label for="p20_price_individual"><?php esc_html_e( 'Individueller Preis', 'p20-raumfinder' ); ?></label></th>
				<td><input type="text" id="p20_price_individual" name="p20_price_individual" value="<?php echo esc_attr( $pind ); ?>" placeholder="<?php esc_attr_e( 'z. B. ab 450 € / Tag', 'p20-raumfinder' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="p20_price_on_request"><?php esc_html_e( 'Preis auf Anfrage', 'p20-raumfinder' ); ?></label></th>
				<td><label><input type="checkbox" id="p20_price_on_request" name="p20_price_on_request" value="1" <?php checked( $poa, '1' ); ?>> <?php esc_html_e( 'Statt Preisen "Preis auf Anfrage" anzeigen', 'p20-raumfinder' ); ?></label></td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Alle Preise werden im Frontend stets als Richtwert ("ab ...") kommuniziert, nie als verbindlicher Endpreis.', 'p20-raumfinder' ); ?></p>
		<?php
	}

	public function render_status( $post ) {
		$active = $this->meta( $post->ID, 'p20_active', '1' );
		$event_terms = get_terms( array( 'taxonomy' => P20_RF_TAX_EVENT, 'hide_empty' => false ) );
		$assigned_events = wp_get_object_terms( $post->ID, P20_RF_TAX_EVENT, array( 'fields' => 'ids' ) );
		$assigned_events = is_wp_error( $assigned_events ) ? array() : $assigned_events;
		?>
		<p>
			<label><input type="radio" name="p20_active" value="1" <?php checked( $active, '1' ); ?>> <?php esc_html_e( 'Aktiv', 'p20-raumfinder' ); ?></label><br>
			<label><input type="radio" name="p20_active" value="0" <?php checked( $active, '0' ); ?>> <?php esc_html_e( 'Inaktiv', 'p20-raumfinder' ); ?></label>
		</p>
		<p class="description"><?php esc_html_e( 'Inaktive Raeume werden im Raumfinder nicht beruecksichtigt.', 'p20-raumfinder' ); ?></p>
		<p class="description"><?php esc_html_e( 'Sortierung / Prioritaet ueber "Reihenfolge" (rechts, Seiten-Attribute) - kleinere Zahl = frueher angezeigt.', 'p20-raumfinder' ); ?></p>
		<hr>
		<p><strong><?php esc_html_e( 'Raumart / Eignung', 'p20-raumfinder' ); ?></strong></p>
		<div class="p20-rf-checkbox-grid">
			<?php foreach ( $event_terms as $term ) : ?>
				<label>
					<input type="checkbox" name="tax_input[<?php echo esc_attr( P20_RF_TAX_EVENT ); ?>][]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $assigned_events, true ) ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------- */

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array( 'p20_internal_name', 'p20_price_individual' );
		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		if ( isset( $_POST['p20_short_desc'] ) ) {
			update_post_meta( $post_id, 'p20_short_desc', sanitize_textarea_field( wp_unslash( $_POST['p20_short_desc'] ) ) );
		}

		if ( isset( $_POST['p20_gallery'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['p20_gallery'] ) ) ) ) );
			update_post_meta( $post_id, 'p20_gallery', implode( ',', $ids ) );
		}

		$number_fields = array( 'p20_size_sqm', 'p20_capacity_min', 'p20_capacity_max', 'p20_price_2h', 'p20_price_4h', 'p20_price_fullday' );
		foreach ( $number_fields as $field ) {
			if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
				update_post_meta( $post_id, $field, (float) wp_unslash( $_POST[ $field ] ) );
			} else {
				delete_post_meta( $post_id, $field );
			}
		}

		update_post_meta( $post_id, 'p20_price_on_request', isset( $_POST['p20_price_on_request'] ) ? '1' : '0' );
		update_post_meta( $post_id, 'p20_active', isset( $_POST['p20_active'] ) && '0' === $_POST['p20_active'] ? '0' : '1' );

		// Seating capacities.
		$seating = array();
		if ( isset( $_POST['p20_seating'] ) && is_array( $_POST['p20_seating'] ) ) {
			foreach ( array_keys( P20_RF_Data::seating_types() ) as $key ) {
				$val = isset( $_POST['p20_seating'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['p20_seating'][ $key ] ) ) : '';
				update_post_meta( $post_id, 'p20_seating_' . $key, '' === $val ? '' : absint( $val ) );
			}
		}

		// Feature tri-state.
		$states = array();
		if ( isset( $_POST['p20_feature_states'] ) && is_array( $_POST['p20_feature_states'] ) ) {
			foreach ( $_POST['p20_feature_states'] as $term_id => $state ) {
				$term_id = absint( $term_id );
				$state   = sanitize_key( wp_unslash( $state ) );
				if ( $term_id && array_key_exists( $state, P20_RF_Data::feature_states() ) ) {
					$states[ $term_id ] = $state;
				}
			}
		}
		update_post_meta( $post_id, 'p20_feature_states', $states );

		// Catering availability (real term relationship).
		$catering_ids = isset( $_POST['p20_catering'] ) && is_array( $_POST['p20_catering'] ) ? array_map( 'absint', wp_unslash( $_POST['p20_catering'] ) ) : array();
		wp_set_object_terms( $post_id, $catering_ids, P20_RF_TAX_CATERING, false );

		// Event types (real term relationship).
		$event_ids = isset( $_POST['tax_input'][ P20_RF_TAX_EVENT ] ) && is_array( $_POST['tax_input'][ P20_RF_TAX_EVENT ] ) ? array_map( 'absint', wp_unslash( $_POST['tax_input'][ P20_RF_TAX_EVENT ] ) ) : array();
		wp_set_object_terms( $post_id, $event_ids, P20_RF_TAX_EVENT, false );
	}
}
