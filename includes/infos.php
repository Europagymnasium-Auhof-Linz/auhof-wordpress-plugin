<?php
/**
 * Pinnwand (Aktuelle Infos): kurze Hinweise oberhalb von „Aktuelles“ und „Termine“ auf der Startseite,
 * z. B. Volksschul-Infoabend, Neuanmeldungen, Elternabend.
 *
 * Gepflegt unter „Pinnwand“ im Backend (includes/infos-admin.php), ausgegeben mit [auhof_infos].
 * Ist keine Info zum Anzeigen angehakt, gibt der Shortcode nichts aus – auch keine Überschrift.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

const AUHOF_INFOS_MAX = 12;

/**
 * Standardwerte.
 *
 * @return array{ueberschrift:string,eintraege:array<int,array<string,mixed>>}
 */
function auhof_infos_standard() {
	return array(
		'ueberschrift' => 'Pinnwand',
		'eintraege'    => array(),
	);
}

/**
 * Ein Eintrag bereinigen; leere Überschrift = kein Eintrag.
 *
 * @param mixed $e Rohdaten.
 * @return array{anzeigen:bool,titel:string,text:string,link:string}|null
 */
function auhof_infos_eintrag_bereinigen( $e ) {
	if ( ! is_array( $e ) ) {
		return null;
	}
	$titel = isset( $e['titel'] ) && is_string( $e['titel'] ) ? mb_substr( trim( sanitize_text_field( $e['titel'] ) ), 0, 100 ) : '';
	if ( '' === $titel ) {
		return null;
	}
	$text = isset( $e['text'] ) && is_string( $e['text'] ) ? mb_substr( trim( sanitize_textarea_field( $e['text'] ) ), 0, 400 ) : '';
	$link = isset( $e['link'] ) && is_string( $e['link'] ) ? esc_url_raw( trim( $e['link'] ), array( 'http', 'https', 'mailto', 'tel' ) ) : '';
	return array(
		'anzeigen' => ! empty( $e['anzeigen'] ),
		'titel'    => $titel,
		'text'     => $text,
		'link'     => $link,
	);
}

/**
 * Gespeicherte Infos (bereinigt).
 *
 * @return array{ueberschrift:string,eintraege:array<int,array{anzeigen:bool,titel:string,text:string,link:string}>}
 */
function auhof_infos_einstellungen() {
	$wert = get_option( 'auhof_infos', array() );
	$wert = is_array( $wert ) ? $wert : array();
	$s    = auhof_infos_standard();
	$aus  = array(
		'ueberschrift' => isset( $wert['ueberschrift'] ) && is_string( $wert['ueberschrift'] ) && '' !== $wert['ueberschrift'] ? $wert['ueberschrift'] : $s['ueberschrift'],
		'eintraege'    => array(),
	);
	foreach ( isset( $wert['eintraege'] ) && is_array( $wert['eintraege'] ) ? $wert['eintraege'] : array() as $e ) {
		if ( is_array( $e ) && isset( $e['titel'] ) && '' !== (string) $e['titel'] ) {
			$aus['eintraege'][] = array(
				'anzeigen' => ! empty( $e['anzeigen'] ),
				'titel'    => (string) $e['titel'],
				'text'     => isset( $e['text'] ) ? (string) $e['text'] : '',
				'link'     => isset( $e['link'] ) ? (string) $e['link'] : '',
			);
		}
	}
	return $aus;
}

add_shortcode( 'auhof_infos', 'auhof_infos_shortcode' );

/**
 * Shortcode [auhof_infos]: angehakte Infos als Karten; ohne angehakte Info nichts.
 *
 * @return string
 */
function auhof_infos_shortcode() {
	$e      = auhof_infos_einstellungen();
	$karten = '';
	foreach ( $e['eintraege'] as $info ) {
		if ( ! $info['anzeigen'] ) {
			continue;
		}
		$titel   = esc_html( $info['titel'] );
		$titel   = '' !== $info['link'] ? '<a href="' . esc_url( $info['link'] ) . '">' . $titel . '</a>' : $titel;
		$karten .= '<li class="auhof-info"><h3 class="auhof-info__titel">' . $titel . '</h3>'
			. ( '' !== $info['text'] ? '<p class="auhof-info__text">' . nl2br( esc_html( $info['text'] ) ) . '</p>' : '' )
			. '</li>';
	}
	if ( '' === $karten ) {
		return '';
	}
	wp_enqueue_style( 'auhof-infos' );
	return '<section class="auhof-infos" aria-labelledby="auhof-infos-titel">'
		. '<h2 class="auhof-infos__ueberschrift" id="auhof-infos-titel">' . esc_html( $e['ueberschrift'] ) . '</h2>'
		. '<ul class="auhof-infos__liste" role="list">' . $karten . '</ul>'
		. '</section>';
}

add_action( 'wp_enqueue_scripts', 'auhof_infos_assets' );

/**
 * Stylesheet registrieren; im Kopf laden, wenn die Seite den Shortcode enthält.
 */
function auhof_infos_assets() {
	wp_register_style( 'auhof-infos', AUHOF_SCHULSEITEN_URL . 'assets/infos.css', array(), AUHOF_SCHULSEITEN_VERSION );
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'auhof_infos' ) ) {
		wp_enqueue_style( 'auhof-infos' );
	}
}

// Nach dem Speichern: zwischengespeicherte Startseite verwerfen, damit Änderungen sofort sichtbar sind.
add_action( 'add_option_auhof_infos', 'auhof_infos_cache_leeren' );
add_action( 'update_option_auhof_infos', 'auhof_infos_cache_leeren' );

/**
 * Seiten-Cache der Startseite leeren (WP Fastest Cache, falls aktiv; sonst ohne Wirkung).
 */
function auhof_infos_cache_leeren() {
	$start = (int) get_option( 'page_on_front' );
	if ( $start ) {
		clean_post_cache( $start );
		do_action( 'wpfc_clear_post_cache_by_id', $start );
	}
	do_action( 'wpfc_clear_all_cache' );
}
