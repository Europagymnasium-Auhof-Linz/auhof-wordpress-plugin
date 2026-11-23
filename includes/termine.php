<?php
/**
 * Terminvorschau: die nächsten schulweiten Termine aus dem Plugin „Sintoora Kalender“.
 *
 * Verwendet nur die Termine, die das Sintoora-Plugin ohnehin zwischenspeichert (kein eigener
 * Abruf bei Sintoora). Gezeigt werden alle Termine der Terminart „Allgemein (Homepage)“ – auch solche,
 * die einzelnen Klassen zugeordnet sind (z. B. iKM-Plus, Sprachreisen, Performance Kl. 4L). Ausgeblendet
 * werden nur Ferien, Feiertage und schulfreie bzw. schulautonome Tage, erkannt am Titel
 * (auhof_termine_ist_frei()). Gleiche Termine mehrerer Klassen (gleicher Tag, gleiche Zeit, gleicher Titel)
 * erscheinen einmal.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/** Terminart in Sintoora, die auf der Startseite erscheinen soll (Vergleich ohne Groß-/Kleinschreibung). */
const AUHOF_TERMINE_TERMINART = 'allgemein (homepage)';

/** Zwischenspeicher der aufbereiteten Liste. */
const AUHOF_TERMINE_CACHE = 'auhof_termine_vorschau';

/**
 * Ferien, Feiertage und schulfreie Tage am Titel erkennen (sie erscheinen nicht in der Terminvorschau,
 * wohl aber weiterhin im Klassenkalender). Erkannt werden Wortteile wie „Ferien“, „Feiertag“, „schulfrei“,
 * „schulautonom“ sowie die österreichischen Feiertage (inkl. Florianitag, Landespatron Oberösterreich).
 * „unterrichtsfrei“ allein zählt nicht: „Elternsprechtag, ab 12:30 Uhr unterrichtsfrei“ ist ein Termin.
 * Erweiterbar über den Filter „auhof_termine_frei_muster“ (regulärer Ausdruck).
 *
 * @param string $titel Titel des Termins.
 * @return bool
 */
function auhof_termine_ist_frei( $titel ) {
	$muster = (string) apply_filters(
		'auhof_termine_frei_muster',
		'/ferien|feiertag|schulfrei|schulautonom|neujahr|heilige drei k(ö|oe)nige|dreik(ö|oe)nig|ostermontag|christi himmelfahrt|pfingstmontag|fronleichnam|mari(ä|ae) himmelfahrt|allerheiligen|allerseelen|mari(ä|ae) empf(ä|ae)ngnis|floriani?tag|heiliger florian|christtag|stefanitag/iu'
	);
	return 1 === @preg_match( $muster, (string) $titel ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fehlerhaftes Muster aus dem Filter: nichts ausblenden.
}

/**
 * Kommende schulweite Termine (ab heute, laufende eingeschlossen), aufbereitet und 15 Minuten zwischengespeichert.
 *
 * @return array<int,array<string,string>>|null Null, wenn das Sintoora-Plugin fehlt oder keine Daten hat.
 */
function auhof_termine_liste() {
	$heute = current_time( 'Y-m-d' );
	$cache = get_transient( AUHOF_TERMINE_CACHE );
	if ( is_array( $cache ) && isset( $cache['tag'], $cache['liste'], $cache['version'] ) && $cache['tag'] === $heute && AUHOF_SCHULSEITEN_VERSION === $cache['version'] ) {
		return $cache['liste'];
	}
	if ( ! function_exists( 'sintoora_kalender_termine' ) || ! function_exists( 'sintoora_kalender_terminart' ) ) {
		return null;
	}
	$daten = sintoora_kalender_termine();
	if ( ! is_array( $daten ) || empty( $daten['appointments'] ) || ! is_array( $daten['appointments'] ) ) {
		return null;
	}
	$jahr    = (int) wp_date( 'Y', isset( $daten['zeit'] ) ? (int) $daten['zeit'] : time() );
	$liste   = array();
	$gesehen = array();
	foreach ( $daten['appointments'] as $termin ) {
		if ( AUHOF_TERMINE_TERMINART !== sintoora_kalender_terminart( $termin ) ) {
			continue;
		}
		$eintrag = auhof_termine_auswerten( $termin, $jahr );
		// Nur Ferien, Feiertage und schulfreie bzw. schulautonome Tage gehören nicht in die Terminvorschau.
		if ( ! $eintrag || $eintrag['ende'] < $heute || auhof_termine_ist_frei( $eintrag['titel'] ) ) {
			continue;
		}
		// Derselbe Termin für mehrere Klassen (z. B. „iKM-Plus Englisch“ für 4A und 4B) nur einmal.
		$schluessel = $eintrag['beginn'] . '|' . $eintrag['ende'] . '|' . $eintrag['zeit'] . '|' . mb_strtolower( $eintrag['titel'] );
		if ( isset( $gesehen[ $schluessel ] ) ) {
			continue;
		}
		$gesehen[ $schluessel ] = true;
		$liste[]                = $eintrag;
	}
	usort(
		$liste,
		function ( $a, $b ) {
			return strcmp( $a['beginn'] . $a['zeit'], $b['beginn'] . $b['zeit'] );
		}
	);
	$liste = array_slice( $liste, 0, 20 );
	set_transient(
		AUHOF_TERMINE_CACHE,
		array(
			'tag'     => $heute,
			'liste'   => $liste,
			'version' => AUHOF_SCHULSEITEN_VERSION,
		),
		15 * MINUTE_IN_SECONDS
	);
	return $liste;
}

/**
 * Datum, Uhrzeit und Titel aus einem Sintoora-Termin. Sintoora schreibt das Datum als Text,
 * z. B. „Do. 24.12. - Mi. 06.01.2027 10:00 - 13:00“; das Jahr fehlt, wenn es das laufende ist.
 *
 * @param mixed $termin Termin (bereinigt vom Sintoora-Plugin).
 * @param int   $jahr   Jahr des Abrufs (für Daten ohne Jahr).
 * @return array<string,string>|null
 */
function auhof_termine_auswerten( $termin, $jahr ) {
	if ( ! is_array( $termin ) || ! isset( $termin['html'], $termin['titel'] ) || ! is_string( $termin['html'] ) || ! is_scalar( $termin['titel'] ) ) {
		return null;
	}
	if ( ! preg_match( '@<span[^>]*>([^<]*\d{1,2}\.\d{1,2}\.[^<]*)</span>@', $termin['html'], $span ) ) {
		return null;
	}
	$text = trim( html_entity_decode( $span[1], ENT_QUOTES, 'UTF-8' ) );
	if ( ! preg_match_all( '/(\d{1,2})\.(\d{1,2})\.(\d{4})?/', $text, $d, PREG_SET_ORDER ) ) {
		return null;
	}
	$daten = array();
	foreach ( array_slice( $d, 0, 2 ) as $teil ) {
		$j = ! empty( $teil[3] ) ? (int) $teil[3] : $jahr;
		if ( ! checkdate( (int) $teil[2], (int) $teil[1], $j ) ) {
			return null;
		}
		$daten[] = sprintf( '%04d-%02d-%02d', $j, $teil[2], $teil[1] );
	}
	$beginn = $daten[0];
	$ende   = isset( $daten[1] ) ? $daten[1] : $beginn;
	if ( $ende < $beginn ) { // z. B. „28.12. - 02.01.“ ohne Jahresangaben
		$ende = ( (int) substr( $ende, 0, 4 ) + 1 ) . substr( $ende, 4 );
	}

	// Uhrzeit bzw. Unterrichtsstunden: Rest nach dem letzten Datum.
	$rest = trim( substr( $text, strrpos( $text, end( $d )[0] ) + strlen( end( $d )[0] ) ) );
	$zeit = '';
	if ( preg_match( '/(\d{1,2}:\d{2})(?:\s*-\s*(\d{1,2}:\d{2}))?/', $rest, $z ) ) {
		$zeit = $z[1] . ( isset( $z[2] ) ? '–' . $z[2] : '' ) . ' Uhr';
	} elseif ( preg_match( '/(\d{1,2})\.(?:\s*-\s*(\d{1,2})\.)?\s*UE/', $rest, $z ) ) {
		$zeit = isset( $z[2] ) ? $z[1] . '.–' . $z[2] . '. Stunde' : $z[1] . '. Stunde';
	}

	$titel = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $termin['titel'] ) ) );
	if ( '' === $titel ) {
		return null;
	}
	// „schulfrei schulautonom“ → „Schulfrei …“, aber Eigennamen wie „eLLA“ unverändert lassen.
	$erstes = strtok( $titel, ' ' );
	if ( mb_strtolower( $erstes ) === $erstes ) {
		$titel = mb_strtoupper( mb_substr( $titel, 0, 1 ) ) . mb_substr( $titel, 1 );
	}

	return array(
		'beginn' => $beginn,
		'ende'   => $ende,
		'zeit'   => $zeit,
		'titel'  => mb_substr( $titel, 0, 160 ),
	);
}

/**
 * Datum lesbar, z. B. „Mo., 7. Dezember 2026“ (lang) oder „7. Dez.“ (kurz).
 *
 * @param string $datum JJJJ-MM-TT.
 * @param bool   $lang  Lange Form mit Wochentag und Jahr.
 * @return string
 */
function auhof_termine_datum( $datum, $lang = true ) {
	$tage              = array( 'So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.' );
	$monate            = array( 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' );
	$kurz              = array( 'Jän.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.' );
	list( $j, $m, $t ) = array_map( 'intval', explode( '-', $datum ) );
	$wtag              = (int) gmdate( 'w', gmmktime( 0, 0, 0, $m, $t, $j ) );
	return $lang ? sprintf( '%s, %d. %s %d', $tage[ $wtag ], $t, $monate[ $m - 1 ], $j ) : sprintf( '%d. %s', $t, $kurz[ $m - 1 ] );
}

add_shortcode( 'auhof_termine', 'auhof_termine_shortcode' );

/**
 * Shortcode [auhof_termine anzahl="5" kalender="/klassenkalender/"].
 *
 * @param array<string,string>|string $atts Attribute.
 * @return string
 */
function auhof_termine_shortcode( $atts ) {
	$atts     = shortcode_atts(
		array(
			'anzahl'   => '5',
			'kalender' => '/klassenkalender/',
		),
		$atts,
		'auhof_termine'
	);
	$anzahl   = max( 1, min( 15, absint( $atts['anzahl'] ) ) );
	$kalender = home_url( '/' . ltrim( (string) wp_parse_url( $atts['kalender'], PHP_URL_PATH ), '/' ) );
	$liste    = auhof_termine_liste();
	wp_enqueue_style( 'auhof-termine' );

	$link = '<p class="auhof-termine__alle"><a href="' . esc_url( $kalender ) . '">' . esc_html__( 'Alle Termine im Schulkalender', 'auhof-schulseiten' ) . '</a></p>';
	if ( ! $liste ) {
		return '<p>' . esc_html__( 'Die nächsten Termine finden Sie im Schulkalender.', 'auhof-schulseiten' ) . '</p>' . $link;
	}

	$heute = current_time( 'Y-m-d' );
	$html  = '<ul class="auhof-termine" role="list">';
	foreach ( array_slice( $liste, 0, $anzahl ) as $t ) {
		list( , $m, $tag ) = explode( '-', $t['beginn'] );
		$kurz              = explode( ' ', auhof_termine_datum( $t['beginn'], false ) );
		$mehrtaegig        = $t['ende'] !== $t['beginn'];
		$wann              = auhof_termine_datum( $t['beginn'] ) . ( $mehrtaegig ? ' ' . __( 'bis', 'auhof-schulseiten' ) . ' ' . auhof_termine_datum( $t['ende'] ) : '' );
		$zusatz            = $mehrtaegig ? sprintf( /* translators: %s: Enddatum */ __( 'bis %s', 'auhof-schulseiten' ), auhof_termine_datum( $t['ende'], false ) ) : $t['zeit'];
		$laeuft            = $t['beginn'] < $heute;

		$html .= '<li class="auhof-termin' . ( $laeuft ? ' auhof-termin--laeuft' : '' ) . '">'
			. '<span class="auhof-termin__blatt" aria-hidden="true"><span class="auhof-termin__tag">' . esc_html( (string) (int) $tag ) . '</span><span class="auhof-termin__monat">' . esc_html( $kurz[1] ) . '</span></span>'
			. '<span class="auhof-termin__text">'
			. '<span class="screen-reader-text">' . esc_html( $wann . ( $t['zeit'] && ! $mehrtaegig ? ', ' . $t['zeit'] : '' ) . ': ' ) . '</span>'
			. '<span class="auhof-termin__titel">' . esc_html( $t['titel'] ) . '</span>'
			. ( '' !== $zusatz ? '<span class="auhof-termin__zeit" aria-hidden="true">' . esc_html( ( $laeuft ? __( 'läuft', 'auhof-schulseiten' ) . ' ' : '' ) . $zusatz ) . '</span>' : '' )
			. '</span></li>';
	}
	return $html . '</ul>' . $link;
}

add_action( 'wp_enqueue_scripts', 'auhof_termine_assets' );

/**
 * Stylesheet registrieren; im Kopf laden, wenn die Seite den Shortcode enthält.
 */
function auhof_termine_assets() {
	wp_register_style( 'auhof-termine', AUHOF_SCHULSEITEN_URL . 'assets/termine.css', array(), AUHOF_SCHULSEITEN_VERSION );
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'auhof_termine' ) ) {
		wp_enqueue_style( 'auhof-termine' );
	}
}
