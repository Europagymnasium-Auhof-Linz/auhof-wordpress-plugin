<?php
/**
 * Raumplan: Gebäudeplan (fest, data/raumplan.json) plus pflegbare Raumbezeichnungen und Schuljahr.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standardwerte der Einstellungen.
 *
 * @return array{jahr:string,namen:array<int,string>}
 */
function auhof_raumplan_standard() {
	return array(
		'jahr'  => 'Schuljahr 2026/27',
		'namen' => array(),
	);
}

/**
 * Gespeicherte Einstellungen (Schuljahr, geänderte Raumnamen je Raum-ID).
 *
 * @return array{jahr:string,namen:array<int,string>}
 */
function auhof_raumplan_einstellungen() {
	$wert = get_option( 'auhof_raumplan', array() );
	$wert = is_array( $wert ) ? $wert : array();
	return array(
		'jahr'  => isset( $wert['jahr'] ) && is_string( $wert['jahr'] ) ? $wert['jahr'] : auhof_raumplan_standard()['jahr'],
		'namen' => isset( $wert['namen'] ) && is_array( $wert['namen'] ) ? $wert['namen'] : array(),
	);
}

/**
 * Gebäudeplan aus data/raumplan.json (Linien, Flächen, Räume mit fester ID).
 *
 * @return array<string,mixed>
 */
function auhof_raumplan_geometrie() {
	static $plan = null;
	if ( null === $plan ) {
		$json = file_get_contents( AUHOF_SCHULSEITEN_PFAD . 'data/raumplan.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokale Plugin-Datei.
		$plan = $json ? json_decode( $json, true ) : array();
		$plan = is_array( $plan ) ? $plan : array();
	}
	return $plan;
}

/**
 * Plan mit den aktuellen Bezeichnungen und dem Schuljahr.
 *
 * @return array<string,mixed>
 */
function auhof_raumplan_daten() {
	$plan          = auhof_raumplan_geometrie();
	$einstellungen = auhof_raumplan_einstellungen();
	if ( empty( $plan['rooms'] ) ) {
		return array();
	}
	foreach ( $plan['rooms'] as $k => $raum ) {
		$id = (int) $raum['id'];
		if ( isset( $einstellungen['namen'][ $id ] ) && '' !== $einstellungen['namen'][ $id ] && $einstellungen['namen'][ $id ] !== $raum['n'] ) {
			$plan['rooms'][ $k ]['n'] = $einstellungen['namen'][ $id ];
			$plan['rooms'][ $k ]['d'] = $einstellungen['namen'][ $id ];
			unset( $plan['rooms'][ $k ]['spans'] );
		}
	}
	$plan['year'] = $einstellungen['jahr'];
	return $plan;
}

/**
 * HTML-Gerüst des Raumplans; gezeichnet wird er von assets/raumplan.js.
 *
 * @param bool $admin Vorschau im Backend (ohne Drucken, ohne Adresszeilen-Änderung).
 * @return string
 */
function auhof_raumplan_html( $admin = false ) {
	$daten = auhof_raumplan_daten();
	if ( ! $daten ) {
		return '';
	}
	$daten['admin'] = (bool) $admin;
	wp_enqueue_style( 'auhof-raumplan' );
	wp_enqueue_script( 'auhof-raumplan' );
	wp_add_inline_script(
		'auhof-raumplan',
		'window.auhofRaumplan = ' . wp_json_encode( $daten, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ) . ';',
		'before'
	);

	$drucken = $admin ? '' : '<button type="button" class="rp-print">' . esc_html__( 'Drucken', 'auhof-schulseiten' ) . '</button>';
	$pdf     = $admin ? '' : auhof_raumplan_pdf_url();
	if ( $pdf ) {
		$drucken .= '<a class="rp-pdf" href="' . esc_url( $pdf ) . '" type="application/pdf">' . esc_html__( 'PDF', 'auhof-schulseiten' ) . '<span class="screen-reader-text"> ' . esc_html__( '– ganzer Raumplan, A4 quer', 'auhof-schulseiten' ) . '</span></a>';
	}
	return '<div id="raumplan">'
		. '<div class="rp-bar">'
		. '<span class="rp-year"></span>'
		. '<div class="rp-views" role="group" aria-label="' . esc_attr__( 'Ansicht wählen', 'auhof-schulseiten' ) . '"></div>'
		. '<div class="rp-search"><input type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rp-results" placeholder="' . esc_attr__( 'Raum suchen, z. B. 3C, LM CH, LIB', 'auhof-schulseiten' ) . '" autocomplete="off" aria-label="' . esc_attr__( 'Raum suchen', 'auhof-schulseiten' ) . '">'
		. '<ul class="rp-results" id="rp-results" role="listbox" aria-label="' . esc_attr__( 'Gefundene Räume', 'auhof-schulseiten' ) . '" hidden></ul></div>'
		. '<div class="rp-zoom" role="group" aria-label="' . esc_attr__( 'Vergrößerung', 'auhof-schulseiten' ) . '">'
		. '<button type="button" class="rp-zoom-minus" aria-label="' . esc_attr__( 'Plan verkleinern', 'auhof-schulseiten' ) . '">−</button>'
		. '<button type="button" class="rp-zoom-plus" aria-label="' . esc_attr__( 'Plan vergrößern', 'auhof-schulseiten' ) . '">+</button>'
		. '</div>'
		. $drucken
		. '</div>'
		. '<div class="rp-sheet"><div class="rp-printonly"></div><svg xmlns="http://www.w3.org/2000/svg" role="group" aria-label="' . esc_attr__( 'Raumplan', 'auhof-schulseiten' ) . '"></svg></div>'
		. '<div class="rp-status" role="status"></div>'
		. '<noscript><p>' . esc_html__( 'Der Raumplan benötigt JavaScript.', 'auhof-schulseiten' ) . '</p></noscript>'
		. '</div>';
}

add_shortcode( 'auhof_raumplan', 'auhof_raumplan_shortcode' );

/**
 * Shortcode [auhof_raumplan] – einmal je Seite.
 *
 * @return string
 */
function auhof_raumplan_shortcode() {
	static $schon = false;
	if ( $schon ) {
		return '';
	}
	$schon = true;
	return auhof_raumplan_html( false );
}

add_action( 'wp_enqueue_scripts', 'auhof_raumplan_assets_registrieren' );
add_action( 'admin_enqueue_scripts', 'auhof_raumplan_assets_registrieren', 5 );

/**
 * Stylesheet und Skript registrieren; das Stylesheet bei Bedarf schon im Kopf laden.
 */
function auhof_raumplan_assets_registrieren() {
	wp_register_style( 'auhof-raumplan', AUHOF_SCHULSEITEN_URL . 'assets/raumplan.css', array(), AUHOF_SCHULSEITEN_VERSION );
	wp_register_script( 'auhof-raumplan', AUHOF_SCHULSEITEN_URL . 'assets/raumplan.js', array(), AUHOF_SCHULSEITEN_VERSION, true );
	if ( is_admin() ) {
		return;
	}
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'auhof_raumplan' ) ) {
		wp_enqueue_style( 'auhof-raumplan' );
	}
}
