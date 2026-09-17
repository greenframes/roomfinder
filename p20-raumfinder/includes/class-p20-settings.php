<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class P20_RF_Settings {

	const OPTION_KEY = 'p20_rf_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public static function default_settings() {
		return array(
			'intro_headline'      => __( 'Welcher Raum passt zu Ihrer Veranstaltung?', 'p20-raumfinder' ),
			'intro_subline'       => __( 'Ein paar Angaben genuegen – wir zeigen Ihnen Raeume, die zu Ihren Plaenen passen.', 'p20-raumfinder' ),
			'notification_email'  => get_option( 'admin_email' ),
			'privacy_text'        => __( 'Ich habe die Datenschutzerklaerung gelesen und bin mit der Verarbeitung meiner Daten zur Bearbeitung meiner Anfrage einverstanden.', 'p20-raumfinder' ),
			'privacy_url'         => '',
			'active_features'     => array(),
		);
	}

	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	public function menu() {
		add_submenu_page(
			'p20-raumfinder',
			__( 'Einstellungen', 'p20-raumfinder' ),
			__( 'Einstellungen', 'p20-raumfinder' ),
			'manage_options',
			'p20-raumfinder-settings',
			array( $this, 'render' )
		);
	}

	public function register() {
		register_setting( 'p20_rf_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$defaults = self::default_settings();
		$output   = array();

		$output['intro_headline']     = sanitize_text_field( $input['intro_headline'] ?? $defaults['intro_headline'] );
		$output['intro_subline']      = sanitize_text_field( $input['intro_subline'] ?? $defaults['intro_subline'] );
		$output['notification_email'] = sanitize_email( $input['notification_email'] ?? $defaults['notification_email'] );
		$output['privacy_text']       = sanitize_textarea_field( $input['privacy_text'] ?? $defaults['privacy_text'] );
		$output['privacy_url']        = esc_url_raw( $input['privacy_url'] ?? '' );
		$output['active_features']    = isset( $input['active_features'] ) && is_array( $input['active_features'] ) ? array_map( 'absint', $input['active_features'] ) : array();

		return $output;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::get_settings();
		$features = get_terms( array( 'taxonomy' => P20_RF_TAX_FEATURE, 'hide_empty' => false ) );
		?>
		<div class="wrap p20-rf-settings">
			<h1><?php esc_html_e( 'Raumfinder – Einstellungen', 'p20-raumfinder' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'p20_rf_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="intro_headline"><?php esc_html_e( 'Headline (Start)', 'p20-raumfinder' ); ?></label></th>
						<td><input type="text" class="large-text" id="intro_headline" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[intro_headline]" value="<?php echo esc_attr( $settings['intro_headline'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="intro_subline"><?php esc_html_e( 'Subline (Start)', 'p20-raumfinder' ); ?></label></th>
						<td><input type="text" class="large-text" id="intro_subline" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[intro_subline]" value="<?php echo esc_attr( $settings['intro_subline'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="notification_email"><?php esc_html_e( 'E-Mail fuer Anfragen', 'p20-raumfinder' ); ?></label></th>
						<td><input type="email" class="regular-text" id="notification_email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>">
						<p class="description"><?php esc_html_e( 'An diese Adresse werden neue Anfragen aus dem Raumfinder gesendet (per wp_mail).', 'p20-raumfinder' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="privacy_text"><?php esc_html_e( 'Datenschutz-Einwilligungstext', 'p20-raumfinder' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="privacy_text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[privacy_text]"><?php echo esc_textarea( $settings['privacy_text'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="privacy_url"><?php esc_html_e( 'Link zur Datenschutzerklaerung', 'p20-raumfinder' ); ?></label></th>
						<td><input type="url" class="large-text" id="privacy_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[privacy_url]" value="<?php echo esc_attr( $settings['privacy_url'] ); ?>" placeholder="https://alte-schraubenfabrik.de/datenschutz/"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Technik-Optionen im Raumfinder', 'p20-raumfinder' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Waehlen Sie, welche Ausstattungsmerkmale Besuchern im Raumfinder-Schritt "Technik" zur Auswahl angeboten werden. Nichts ausgewaehlt = alle Merkmale werden angezeigt.', 'p20-raumfinder' ); ?></p>
							<div class="p20-rf-checkbox-grid">
								<?php foreach ( (array) $features as $term ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[active_features][]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, (array) $settings['active_features'], true ) ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Shortcode', 'p20-raumfinder' ); ?></h2>
			<p><?php esc_html_e( 'Fuegen Sie den Raumfinder auf einer beliebigen Seite mit folgendem Shortcode ein:', 'p20-raumfinder' ); ?></p>
			<code>[p20_raumfinder]</code>
		</div>
		<?php
	}
}
