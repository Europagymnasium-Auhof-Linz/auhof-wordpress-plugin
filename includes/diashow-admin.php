<?php
/**
 * Diashow im Backend: Bilder wählen und ordnen, Anzeigedauer, Breite, Höhe.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'auhof_diashow_menue' );

/**
 * Menüpunkt „Diashow“.
 */
function auhof_diashow_menue() {
	add_menu_page( __( 'Diashow', 'auhof-schulseiten' ), __( 'Diashow', 'auhof-schulseiten' ), auhof_schulseiten_recht(), 'auhof-diashow', 'auhof_diashow_seite', 'dashicons-images-alt2', 20 );
}

add_action( 'admin_init', 'auhof_diashow_einstellung_registrieren' );

/**
 * Einstellung über die Einstellungs-API (Nonce und Berechtigung prüft WordPress in options.php).
 */
function auhof_diashow_einstellung_registrieren() {
	register_setting(
		'auhof_diashow',
		'auhof_diashow',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'auhof_diashow_bereinigen',
			'default'           => auhof_diashow_standard(),
			'show_in_rest'      => false,
		)
	);
}

add_filter(
	'option_page_capability_auhof_diashow',
	function () {
		return auhof_schulseiten_recht();
	}
);

/**
 * Eingaben bereinigen.
 *
 * @param mixed $eingabe Formulardaten.
 * @return array<string,mixed>
 */
function auhof_diashow_bereinigen( $eingabe ) {
	$eingabe = is_array( $eingabe ) ? $eingabe : array();
	$s       = auhof_diashow_standard();
	$lagen   = auhof_diashow_ausschnitte();

	$ids    = isset( $eingabe['bilder']['id'] ) && is_array( $eingabe['bilder']['id'] ) ? array_values( $eingabe['bilder']['id'] ) : array();
	$lage   = isset( $eingabe['bilder']['lage'] ) && is_array( $eingabe['bilder']['lage'] ) ? array_values( $eingabe['bilder']['lage'] ) : array();
	$bilder = array();
	$schon  = array();
	foreach ( $ids as $k => $id ) {
		$id = is_scalar( $id ) ? absint( $id ) : 0;
		if ( ! $id || isset( $schon[ $id ] ) || ! wp_attachment_is_image( $id ) || count( $bilder ) >= 20 ) {
			continue;
		}
		$schon[ $id ] = true;
		$l            = isset( $lage[ $k ] ) && is_string( $lage[ $k ] ) && isset( $lagen[ $lage[ $k ] ] ) ? $lage[ $k ] : 'mitte';
		$bilder[]     = array(
			'id'   => $id,
			'lage' => $l,
		);
	}

	$zahl = function ( $schluessel, $min, $max, $schritt = 1 ) use ( $eingabe, $s ) {
		$w = isset( $eingabe[ $schluessel ] ) && is_numeric( $eingabe[ $schluessel ] ) ? (int) $eingabe[ $schluessel ] : $s[ $schluessel ];
		return (int) ( round( max( $min, min( $max, $w ) ) / $schritt ) * $schritt );
	};

	return array(
		'bilder'       => $bilder,
		'sekunden'     => $zahl( 'sekunden', 3, 30 ),
		'breite'       => isset( $eingabe['breite'] ) && 'inhalt' === $eingabe['breite'] ? 'inhalt' : 'voll',
		'hoehe'        => $zahl( 'hoehe', 200, 800, 10 ),
		'handy'        => isset( $eingabe['handy'] ) && is_string( $eingabe['handy'] ) && isset( auhof_diashow_handy_formate()[ $eingabe['handy'] ] ) ? $eingabe['handy'] : $s['handy'],
		'beschriftung' => ! empty( $eingabe['beschriftung'] ),
	);
}

// Nach dem Speichern: optimierte Fassungen erzeugen und nicht mehr benötigte entfernen.
add_action( 'add_option_auhof_diashow', 'auhof_diashow_nach_speichern' );
add_action( 'update_option_auhof_diashow', 'auhof_diashow_nach_speichern' );

/**
 * Fassungen nach dem Speichern aktualisieren (max. 20 s, Rest im Hintergrund).
 */
function auhof_diashow_nach_speichern() {
	auhof_diashow_aufraeumen();
	auhof_diashow_erzeugen( 20 );
}

/**
 * Ein Bild in der Liste (auch als Vorlage für neu gewählte Bilder).
 *
 * @param int    $id   Bild-ID (0 = Vorlage).
 * @param string $lage Ausschnitt.
 */
function auhof_diashow_admin_bild( $id, $lage ) {
	$alt = $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
	echo '<li class="auhof-dia-bild"' . ( $id ? '' : ' hidden' ) . '>';
	echo '<span class="auhof-dia-bild__vorschau">' . ( $id ? wp_get_attachment_image( $id, 'medium', false, array( 'alt' => '' ) ) : '<img alt="">' ) . '</span>';
	echo '<input type="hidden" name="auhof_diashow[bilder][id][]" value="' . esc_attr( (string) $id ) . '"' . ( $id ? '' : ' disabled' ) . '>';
	echo '<label class="auhof-dia-bild__lage">' . esc_html__( 'Ausschnitt', 'auhof-schulseiten' ) . ' <select name="auhof_diashow[bilder][lage][]"' . ( $id ? '' : ' disabled' ) . '>';
	foreach ( auhof_diashow_ausschnitte() as $schluessel => $daten ) {
		echo '<option value="' . esc_attr( $schluessel ) . '"' . selected( $lage, $schluessel, false ) . '>' . esc_html( $daten[0] ) . '</option>';
	}
	echo '</select></label>';
	echo '<span class="auhof-dia-bild__knoepfe">';
	echo '<button type="button" class="button auhof-dia-hoch" aria-label="' . esc_attr__( 'Nach vorne', 'auhof-schulseiten' ) . '">↑</button>';
	echo '<button type="button" class="button auhof-dia-runter" aria-label="' . esc_attr__( 'Nach hinten', 'auhof-schulseiten' ) . '">↓</button>';
	echo '<button type="button" class="button-link button-link-delete auhof-dia-weg">' . esc_html__( 'Entfernen', 'auhof-schulseiten' ) . '</button>';
	echo '</span>';
	if ( $id && '' === $alt ) {
		echo '<a class="auhof-dia-bild__hinweis" href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( 'Alternativtext fehlt', 'auhof-schulseiten' ) . '</a>';
	}
	echo '</li>';
}

/**
 * Seite „Diashow“.
 */
function auhof_diashow_seite() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		return;
	}
	$e     = auhof_diashow_einstellungen();
	$stand = get_option( 'auhof_diashow_stand', array() );
	?>
	<div class="wrap auhof-dia-admin">
		<h1><?php esc_html_e( 'Diashow', 'auhof-schulseiten' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'auhof_diashow' ); ?>
			<h2><?php esc_html_e( 'Bilder', 'auhof-schulseiten' ); ?></h2>
			<ul class="auhof-dia-bilder" id="auhof-dia-bilder">
				<?php
				auhof_diashow_admin_bild( 0, 'mitte' ); // Vorlage.
				foreach ( $e['bilder'] as $bild ) {
					auhof_diashow_admin_bild( (int) $bild['id'], $bild['lage'] );
				}
				?>
			</ul>
			<p><button type="button" class="button button-secondary" id="auhof-dia-hinzu"><?php esc_html_e( 'Bilder hinzufügen', 'auhof-schulseiten' ); ?></button></p>

			<h2><?php esc_html_e( 'Anzeige', 'auhof-schulseiten' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="auhof-dia-sekunden"><?php esc_html_e( 'Anzeigedauer je Bild', 'auhof-schulseiten' ); ?></label></th>
					<td><input type="number" id="auhof-dia-sekunden" name="auhof_diashow[sekunden]" value="<?php echo esc_attr( (string) $e['sekunden'] ); ?>" min="3" max="30" step="1" class="small-text"> <?php esc_html_e( 'Sekunden', 'auhof-schulseiten' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Breite', 'auhof-schulseiten' ); ?></th>
					<td><fieldset>
						<label><input type="radio" name="auhof_diashow[breite]" value="voll" <?php checked( $e['breite'], 'voll' ); ?>> <?php esc_html_e( 'ganze Bildschirmbreite', 'auhof-schulseiten' ); ?></label><br>
						<label><input type="radio" name="auhof_diashow[breite]" value="inhalt" <?php checked( $e['breite'], 'inhalt' ); ?>> <?php esc_html_e( 'Breite des Inhalts', 'auhof-schulseiten' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"><label for="auhof-dia-hoehe"><?php esc_html_e( 'Höhe am Computer', 'auhof-schulseiten' ); ?></label></th>
					<td><input type="number" id="auhof-dia-hoehe" name="auhof_diashow[hoehe]" value="<?php echo esc_attr( (string) $e['hoehe'] ); ?>" min="200" max="800" step="10" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="auhof-dia-handy"><?php esc_html_e( 'Format am Handy', 'auhof-schulseiten' ); ?></label></th>
					<td><select id="auhof-dia-handy" name="auhof_diashow[handy]">
						<?php foreach ( array_keys( auhof_diashow_handy_formate() ) as $f ) : ?>
							<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $e['handy'], $f ); ?>><?php echo esc_html( $f ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Bildunterschrift', 'auhof-schulseiten' ); ?></th>
					<td><label><input type="checkbox" name="auhof_diashow[beschriftung]" value="1" <?php checked( (bool) $e['beschriftung'] ); ?>> <?php esc_html_e( 'anzeigen (Beschriftung aus der Mediathek)', 'auhof-schulseiten' ); ?></label></td>
				</tr>
			</table>
			<?php if ( ! empty( $stand['gesamt'] ) ) : ?>
				<p class="description">
					<?php
					/* translators: 1: fertige, 2: alle Fassungen */
					echo esc_html( sprintf( __( 'Optimierte Bildfassungen: %1$d von %2$d fertig.', 'auhof-schulseiten' ), (int) $stand['fertig'], (int) $stand['gesamt'] ) );
					?>
				</p>
			<?php endif; ?>
			<?php submit_button( __( 'Speichern', 'auhof-schulseiten' ) ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'auhof_diashow_admin_assets' );

/**
 * Mediathek-Auswahl und Skript nur auf der Diashow-Seite.
 *
 * @param string $hook Seitenkennung.
 */
function auhof_diashow_admin_assets( $hook ) {
	if ( 'toplevel_page_auhof-diashow' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'auhof-admin', AUHOF_SCHULSEITEN_URL . 'assets/admin.css', array(), AUHOF_SCHULSEITEN_VERSION );
	wp_enqueue_script( 'auhof-diashow-admin', AUHOF_SCHULSEITEN_URL . 'assets/diashow-admin.js', array( 'jquery', 'jquery-ui-sortable' ), AUHOF_SCHULSEITEN_VERSION, true );
	wp_localize_script(
		'auhof-diashow-admin',
		'auhofDiashow',
		array(
			'titel'    => __( 'Bilder für die Diashow wählen', 'auhof-schulseiten' ),
			'knopf'    => __( 'Hinzufügen', 'auhof-schulseiten' ),
			'altFehlt' => __( 'Alternativtext fehlt', 'auhof-schulseiten' ),
		)
	);
}
