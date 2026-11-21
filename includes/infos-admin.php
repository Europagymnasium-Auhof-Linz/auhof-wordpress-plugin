<?php
/**
 * Pinnwand (Aktuelle Infos) im Backend: Einträge hinzufügen (+), entfernen (−), verschieben (↑/↓), anzeigen ja/nein.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'auhof_infos_menue' );

/**
 * Menüpunkt „Pinnwand“ (für Redakteur:innen und Administrator:innen).
 */
function auhof_infos_menue() {
	add_menu_page( __( 'Pinnwand', 'auhof-schulseiten' ), __( 'Pinnwand', 'auhof-schulseiten' ), auhof_schulseiten_recht(), 'auhof-infos', 'auhof_infos_seite', 'dashicons-megaphone', 21 );
}

add_action( 'admin_init', 'auhof_infos_einstellung_registrieren' );

/**
 * Einstellung über die Einstellungs-API (Nonce und Berechtigung prüft WordPress in options.php).
 */
function auhof_infos_einstellung_registrieren() {
	register_setting(
		'auhof_infos',
		'auhof_infos',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'auhof_infos_bereinigen',
			'default'           => auhof_infos_standard(),
			'show_in_rest'      => false,
		)
	);
}

add_filter(
	'option_page_capability_auhof_infos',
	function () {
		return auhof_schulseiten_recht();
	}
);

/**
 * Eingaben bereinigen: Reihenfolge wie im Formular, höchstens AUHOF_INFOS_MAX Einträge, Einträge ohne Überschrift entfallen.
 *
 * @param mixed $eingabe Formulardaten.
 * @return array{ueberschrift:string,eintraege:array<int,array<string,mixed>>}
 */
function auhof_infos_bereinigen( $eingabe ) {
	$eingabe   = is_array( $eingabe ) ? $eingabe : array();
	$ueber     = isset( $eingabe['ueberschrift'] ) && is_string( $eingabe['ueberschrift'] ) ? mb_substr( trim( sanitize_text_field( $eingabe['ueberschrift'] ) ), 0, 60 ) : '';
	$eintraege = array();
	foreach ( isset( $eingabe['eintraege'] ) && is_array( $eingabe['eintraege'] ) ? $eingabe['eintraege'] : array() as $roh ) {
		$e = auhof_infos_eintrag_bereinigen( $roh );
		if ( $e && count( $eintraege ) < AUHOF_INFOS_MAX ) {
			$eintraege[] = $e;
		}
	}
	return array(
		'ueberschrift' => '' !== $ueber ? $ueber : auhof_infos_standard()['ueberschrift'],
		'eintraege'    => $eintraege,
	);
}

/**
 * Eine Info im Formular (auch als leere Vorlage für „+“).
 *
 * @param string                   $nr   Schlüssel im Formular („__nr__“ in der Vorlage).
 * @param array<string,mixed>|null $info Daten oder null für die Vorlage.
 */
function auhof_infos_admin_eintrag( $nr, $info ) {
	$vorlage = null === $info;
	$info    = $vorlage ? array(
		'anzeigen' => true,
		'titel'    => '',
		'text'     => '',
		'link'     => '',
	) : $info;
	$name    = 'auhof_infos[eintraege][' . $nr . ']';
	$id      = 'auhof-info-' . $nr;
	$aus     = $vorlage ? ' disabled' : '';
	?>
	<li class="auhof-info-zeile"<?php echo $vorlage ? ' hidden' : ''; ?>>
		<div class="auhof-info-zeile__kopf">
			<label class="auhof-info-zeile__anzeigen"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[anzeigen]" value="1" <?php checked( (bool) $info['anzeigen'] ); ?><?php echo esc_attr( $aus ); ?>> <?php esc_html_e( 'Anzeigen', 'auhof-schulseiten' ); ?></label>
			<span class="auhof-info-zeile__knoepfe">
				<button type="button" class="button auhof-info-hoch" aria-label="<?php esc_attr_e( 'Nach oben', 'auhof-schulseiten' ); ?>">↑</button>
				<button type="button" class="button auhof-info-runter" aria-label="<?php esc_attr_e( 'Nach unten', 'auhof-schulseiten' ); ?>">↓</button>
				<button type="button" class="button auhof-info-weg" aria-label="<?php esc_attr_e( 'Info entfernen', 'auhof-schulseiten' ); ?>">−</button>
			</span>
		</div>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>-titel"><?php esc_html_e( 'Überschrift', 'auhof-schulseiten' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $id ); ?>-titel" name="<?php echo esc_attr( $name ); ?>[titel]" value="<?php echo esc_attr( $info['titel'] ); ?>" maxlength="100" class="widefat"<?php echo esc_attr( $aus ); ?>>
		</p>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>-text"><?php esc_html_e( 'Kurzer Text (optional)', 'auhof-schulseiten' ); ?></label>
			<textarea id="<?php echo esc_attr( $id ); ?>-text" name="<?php echo esc_attr( $name ); ?>[text]" rows="2" maxlength="400" class="widefat"<?php echo esc_attr( $aus ); ?>><?php echo esc_textarea( $info['text'] ); ?></textarea>
		</p>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>-link"><?php esc_html_e( 'Link (optional, z. B. Seite mit Details)', 'auhof-schulseiten' ); ?></label>
			<input type="url" id="<?php echo esc_attr( $id ); ?>-link" name="<?php echo esc_attr( $name ); ?>[link]" value="<?php echo esc_attr( $info['link'] ); ?>" placeholder="https://" class="widefat"<?php echo esc_attr( $aus ); ?>>
		</p>
	</li>
	<?php
}

/**
 * Seite „Pinnwand“.
 */
function auhof_infos_seite() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		return;
	}
	$e = auhof_infos_einstellungen();
	?>
	<div class="wrap auhof-infos-admin">
		<h1><?php esc_html_e( 'Pinnwand', 'auhof-schulseiten' ); ?></h1>
		<p><?php esc_html_e( 'Kurze Hinweise auf der Startseite über „Aktuelles“ und „Termine“. Nur angehakte Infos erscheinen; ist keine angehakt, wird der ganze Bereich ausgeblendet.', 'auhof-schulseiten' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'auhof_infos' ); ?>
			<p>
				<label for="auhof-infos-ueberschrift"><strong><?php esc_html_e( 'Überschrift des Bereichs', 'auhof-schulseiten' ); ?></strong></label><br>
				<input type="text" id="auhof-infos-ueberschrift" name="auhof_infos[ueberschrift]" value="<?php echo esc_attr( $e['ueberschrift'] ); ?>" maxlength="60" class="regular-text">
			</p>
			<ol class="auhof-info-liste" id="auhof-info-liste" data-max="<?php echo esc_attr( (string) AUHOF_INFOS_MAX ); ?>">
				<?php
				auhof_infos_admin_eintrag( '__nr__', null ); // Vorlage für „+“.
				foreach ( $e['eintraege'] as $k => $info ) {
					auhof_infos_admin_eintrag( (string) $k, $info );
				}
				?>
			</ol>
			<p class="auhof-info-leer" id="auhof-info-leer"<?php echo $e['eintraege'] ? ' hidden' : ''; ?>><?php esc_html_e( 'Noch keine Infos. Mit „+ Info hinzufügen“ anlegen.', 'auhof-schulseiten' ); ?></p>
			<p><button type="button" class="button button-secondary" id="auhof-info-hinzu">+ <?php esc_html_e( 'Info hinzufügen', 'auhof-schulseiten' ); ?></button></p>
			<?php submit_button( __( 'Speichern', 'auhof-schulseiten' ) ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'auhof_infos_admin_assets' );

/**
 * Skript und Gestaltung nur auf dieser Seite.
 *
 * @param string $hook Seitenkennung.
 */
function auhof_infos_admin_assets( $hook ) {
	if ( 'toplevel_page_auhof-infos' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'auhof-admin', AUHOF_SCHULSEITEN_URL . 'assets/admin.css', array(), AUHOF_SCHULSEITEN_VERSION );
	wp_enqueue_script( 'auhof-infos-admin', AUHOF_SCHULSEITEN_URL . 'assets/infos-admin.js', array(), AUHOF_SCHULSEITEN_VERSION, true );
}
