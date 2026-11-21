<?php
/**
 * Raumplan im Backend: Schuljahr und Raumbezeichnungen ändern, mit Vorschau.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'auhof_raumplan_menue' );

/**
 * Menüpunkt „Raumplan“ (für Redakteur:innen und Administrator:innen).
 */
function auhof_raumplan_menue() {
	add_menu_page( __( 'Raumplan', 'auhof-schulseiten' ), __( 'Raumplan', 'auhof-schulseiten' ), auhof_schulseiten_recht(), 'auhof-raumplan', 'auhof_raumplan_seite', 'dashicons-location-alt', 22 );
}

add_action( 'admin_init', 'auhof_raumplan_einstellung_registrieren' );

/**
 * Einstellung über die Einstellungs-API (Nonce und Berechtigung prüft WordPress in options.php).
 */
function auhof_raumplan_einstellung_registrieren() {
	register_setting(
		'auhof_raumplan',
		'auhof_raumplan',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'auhof_raumplan_bereinigen',
			'default'           => auhof_raumplan_standard(),
			'show_in_rest'      => false,
		)
	);
}

add_filter(
	'option_page_capability_auhof_raumplan',
	function () {
		return auhof_schulseiten_recht();
	}
);

/**
 * Eingaben bereinigen: nur bekannte Raum-IDs, kurzer Text; unveränderte Namen werden nicht gespeichert.
 *
 * @param mixed $eingabe Formulardaten.
 * @return array{jahr:string,namen:array<int,string>}
 */
function auhof_raumplan_bereinigen( $eingabe ) {
	$eingabe = is_array( $eingabe ) ? $eingabe : array();
	$jahr    = isset( $eingabe['jahr'] ) && is_string( $eingabe['jahr'] ) ? mb_substr( sanitize_text_field( $eingabe['jahr'] ), 0, 60 ) : '';
	$namen   = array();

	$roh  = isset( $eingabe['namen'] ) && is_array( $eingabe['namen'] ) ? $eingabe['namen'] : array();
	$plan = auhof_raumplan_geometrie();
	foreach ( isset( $plan['rooms'] ) ? $plan['rooms'] : array() as $raum ) {
		$id = (int) $raum['id'];
		if ( ! isset( $roh[ $id ] ) || ! is_string( $roh[ $id ] ) ) {
			continue;
		}
		$name = mb_substr( sanitize_text_field( $roh[ $id ] ), 0, 40 );
		if ( '' !== $name && $name !== $raum['n'] ) {
			$namen[ $id ] = $name;
		}
	}

	return array(
		'jahr'  => '' !== $jahr ? $jahr : auhof_raumplan_standard()['jahr'],
		'namen' => $namen,
	);
}

/**
 * Seite „Raumplan“.
 */
function auhof_raumplan_seite() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		return;
	}
	$einstellungen = auhof_raumplan_einstellungen();
	$plan          = auhof_raumplan_geometrie();
	$stockwerke    = array();
	foreach ( isset( $plan['floors'] ) ? $plan['floors'] : array() as $stock ) {
		$stockwerke[ $stock['id'] ] = array(
			'name'   => $stock['name'],
			'raeume' => array(),
		);
	}
	foreach ( isset( $plan['rooms'] ) ? $plan['rooms'] : array() as $raum ) {
		if ( isset( $stockwerke[ $raum['f'] ] ) ) {
			$stockwerke[ $raum['f'] ]['raeume'][] = $raum;
		}
	}
	?>
	<div class="wrap auhof-rp-admin">
		<h1><?php esc_html_e( 'Raumplan', 'auhof-schulseiten' ); ?></h1>
		<p><?php esc_html_e( 'Raum im Plan anklicken oder Feld wählen, Namen ändern, speichern. Leeres Feld = ursprünglicher Name.', 'auhof-schulseiten' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'auhof_raumplan' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="auhof_rp_jahr"><?php esc_html_e( 'Schuljahr', 'auhof-schulseiten' ); ?></label></th>
					<td><input type="text" id="auhof_rp_jahr" name="auhof_raumplan[jahr]" value="<?php echo esc_attr( $einstellungen['jahr'] ); ?>" class="regular-text" maxlength="60"></td>
				</tr>
			</table>
			<div class="auhof-rp-vorschau">
				<?php echo auhof_raumplan_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in auhof_raumplan_html() maskiert. ?>
			</div>
			<?php
			foreach ( $stockwerke as $stock ) :
				usort(
					$stock['raeume'],
					function ( $a, $b ) {
						return array( round( $a['box'][1] / 8 ), $a['box'][0] ) <=> array( round( $b['box'][1] / 8 ), $b['box'][0] );
					}
				);
				?>
				<fieldset class="auhof-rp-stock">
					<legend><?php echo esc_html( $stock['name'] ); ?></legend>
					<div class="auhof-rp-felder">
						<?php
						foreach ( $stock['raeume'] as $raum ) :
							$id   = (int) $raum['id'];
							$wert = isset( $einstellungen['namen'][ $id ] ) ? $einstellungen['namen'][ $id ] : $raum['n'];
							?>
							<input type="text" name="auhof_raumplan[namen][<?php echo esc_attr( (string) $id ); ?>]" value="<?php echo esc_attr( $wert ); ?>" placeholder="<?php echo esc_attr( $raum['n'] ); ?>" data-i="<?php echo esc_attr( (string) $id ); ?>" maxlength="40" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: Raumname laut Plan, 2: Stockwerk */ __( 'Raum %1$s, %2$s', 'auhof-schulseiten' ), $raum['n'], $stock['name'] ) ); ?>">
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>
			<?php submit_button( __( 'Speichern', 'auhof-schulseiten' ) ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'auhof_raumplan_admin_assets' );

/**
 * Skripte nur auf der Raumplan-Seite.
 *
 * @param string $hook Seitenkennung.
 */
function auhof_raumplan_admin_assets( $hook ) {
	if ( 'toplevel_page_auhof-raumplan' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'auhof-admin', AUHOF_SCHULSEITEN_URL . 'assets/admin.css', array(), AUHOF_SCHULSEITEN_VERSION );
	wp_enqueue_script( 'auhof-raumplan-admin', AUHOF_SCHULSEITEN_URL . 'assets/raumplan-admin.js', array( 'auhof-raumplan' ), AUHOF_SCHULSEITEN_VERSION, true );
}
