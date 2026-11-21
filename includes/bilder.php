<?php
/**
 * Bilder: Kamera- und GPS-Metadaten aus verkleinerten Fassungen entfernen (verlustfrei) und neue
 * Zwischengrößen als WebP speichern.
 *
 * Warum: WordPress übernimmt EXIF/XMP (Kameradaten, oft auch die GPS-Position) in jede verkleinerte
 * Fassung – bei einem 150-px-Vorschaubild sind das bis zu 56 KB von 62 KB. Entfernt werden nur
 * APP1- (EXIF/XMP) und APP13-Blöcke (IPTC); das Farbprofil (ICC) und die Bilddaten bleiben unverändert.
 * Die hochgeladenen Originale werden nie angefasst.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Entfernt EXIF/XMP- und IPTC-Blöcke aus einer JPEG-Datei, ohne die Bilddaten neu zu kodieren.
 *
 * @param string $datei Pfad.
 * @return int Eingesparte Bytes (0, wenn nichts zu tun war oder die Datei kein JPEG ist).
 */
function auhof_bilder_metadaten_entfernen( $datei ) {
	if ( ! is_string( $datei ) || ! preg_match( '/\.jpe?g$/i', $datei ) || ! is_file( $datei ) || ! is_writable( $datei ) ) {
		return 0;
	}
	$daten = file_get_contents( $datei ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokale Datei.
	if ( ! is_string( $daten ) || "\xFF\xD8" !== substr( $daten, 0, 2 ) ) {
		return 0;
	}
	$neu    = "\xFF\xD8";
	$pos    = 2;
	$laenge = strlen( $daten );
	$weg    = false;
	while ( $pos + 4 <= $laenge && "\xFF" === $daten[ $pos ] ) {
		$marke = ord( $daten[ $pos + 1 ] );
		if ( 0xFF === $marke ) { // Füllbyte.
			++$pos;
			continue;
		}
		if ( 0xDA === $marke ) { // Beginn der Bilddaten: Rest unverändert übernehmen.
			break;
		}
		if ( 0xD8 === $marke || ( $marke >= 0xD0 && $marke <= 0xD7 ) || 0x01 === $marke ) {
			$neu .= substr( $daten, $pos, 2 );
			$pos += 2;
			continue;
		}
		$segment = 2 + ( ord( $daten[ $pos + 2 ] ) << 8 ) + ord( $daten[ $pos + 3 ] );
		if ( $segment < 4 || $pos + $segment > $laenge ) {
			return 0; // Unerwarteter Aufbau: Datei lieber nicht verändern.
		}
		if ( 0xE1 === $marke || 0xED === $marke ) {
			$weg = true; // APP1 (EXIF/XMP) bzw. APP13 (IPTC) auslassen.
		} else {
			$neu .= substr( $daten, $pos, $segment );
		}
		$pos += $segment;
	}
	if ( ! $weg || $pos >= $laenge ) {
		return 0;
	}
	$neu .= substr( $daten, $pos );
	$tmp  = $datei . '.auhof-tmp';
	if ( false === file_put_contents( $tmp, $neu ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return 0;
	}
	if ( ! @rename( $tmp, $datei ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
		wp_delete_file( $tmp );
		return 0;
	}
	return $laenge - strlen( $neu );
}

/**
 * Alle abgeleiteten Dateien eines Bildes (Zwischengrößen und „-scaled“-Fassung), ohne das Original.
 *
 * @param int $id Anhang-ID.
 * @return string[]
 */
function auhof_bilder_abgeleitete_dateien( $id ) {
	$meta  = wp_get_attachment_metadata( $id );
	$haupt = get_attached_file( $id );
	if ( ! is_array( $meta ) || ! $haupt ) {
		return array();
	}
	$ordner  = dirname( $haupt );
	$dateien = array();
	foreach ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() as $groesse ) {
		if ( ! empty( $groesse['file'] ) ) {
			$dateien[] = $ordner . '/' . wp_basename( $groesse['file'] );
		}
	}
	if ( ! empty( $meta['original_image'] ) ) {
		$dateien[] = $haupt; // „-scaled“ bzw. „-rotated“: von WordPress erzeugt, nicht das Original.
	}
	return array_unique( $dateien );
}

// Neue Uploads: verkleinerte Fassungen gleich ohne Metadaten speichern.
add_filter(
	'wp_generate_attachment_metadata',
	function ( $meta, $id ) {
		foreach ( auhof_bilder_abgeleitete_dateien( (int) $id ) as $datei ) {
			auhof_bilder_metadaten_entfernen( $datei );
		}
		return $meta;
	},
	20,
	2
);

// Neue Uploads: Zwischengrößen von JPEG als WebP speichern (Original bleibt JPEG). Nur, wenn der Server WebP kann.
add_filter(
	'image_editor_output_format',
	function ( $formate ) {
		static $webp = null;
		if ( null === $webp ) {
			$webp = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		}
		if ( $webp ) {
			$formate['image/jpeg'] = 'image/webp';
		}
		return $formate;
	}
);

/**
 * Bestand bereinigen, schrittweise im Hintergrund (WP-Cron): je Durchgang bis zu 40 Bilder.
 * Läuft einmal nach dem Aktivieren bzw. nach einem Update des Plugins, bis alle Bilder erledigt sind.
 */
function auhof_bilder_bestand_bereinigen() {
	global $wpdb;
	$stand = get_option(
		'auhof_bilder_stand',
		array(
			'letzte_id' => 0,
			'gespart'   => 0,
			'fertig'    => false,
		)
	);
	if ( ! empty( $stand['fertig'] ) ) {
		if ( ! auhof_bilder_fehlende_groessen() ) {
			wp_schedule_single_event( time() + 60, 'auhof_bilder_bestand' );
		}
		return;
	}
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type = 'image/jpeg' AND ID > %d ORDER BY ID ASC LIMIT 40",
			(int) $stand['letzte_id']
		)
	);
	foreach ( $ids as $id ) {
		foreach ( auhof_bilder_abgeleitete_dateien( (int) $id ) as $datei ) {
			$stand['gespart'] += auhof_bilder_metadaten_entfernen( $datei );
		}
		$stand['letzte_id'] = (int) $id;
	}
	$stand['fertig'] = count( $ids ) < 40;
	update_option( 'auhof_bilder_stand', $stand, false );
	$groessen_fertig = auhof_bilder_fehlende_groessen();
	if ( ! $stand['fertig'] || ! $groessen_fertig ) {
		wp_schedule_single_event( time() + 60, 'auhof_bilder_bestand' );
	}
}

/**
 * Beitragsbilder: fehlende Zwischengrößen (v. a. 1536 px) nachträglich erzeugen. Ohne sie laden Handys mit hoher
 * Pixeldichte in den Kacheln „Aktuelles“ das Original. Je Durchgang höchstens 5 Bilder bzw. 20 Sekunden.
 *
 * @return bool Fertig?
 */
function auhof_bilder_fehlende_groessen() {
	global $wpdb;
	$stand = get_option(
		'auhof_bilder_groessen',
		array(
			'letzte_id' => 0,
			'fertig'    => false,
		)
	);
	if ( ! empty( $stand['fertig'] ) ) {
		return true;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$start = microtime( true );
	$ids   = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT CAST(meta_value AS UNSIGNED) AS id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND CAST(meta_value AS UNSIGNED) > %d ORDER BY id ASC LIMIT 5",
			(int) $stand['letzte_id']
		)
	);
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( microtime( true ) - $start > 20 ) {
			break;
		}
		if ( wp_attachment_is_image( $id ) && wp_get_missing_image_subsizes( $id ) ) {
			wp_update_image_subsizes( $id );
			foreach ( auhof_bilder_abgeleitete_dateien( $id ) as $datei ) {
				auhof_bilder_metadaten_entfernen( $datei );
			}
		}
		$stand['letzte_id'] = $id;
	}
	$stand['fertig'] = count( $ids ) < 5;
	update_option( 'auhof_bilder_groessen', $stand, false );
	return $stand['fertig'];
}
add_action( 'auhof_bilder_bestand', 'auhof_bilder_bestand_bereinigen' );

add_action(
	'init',
	function () {
		// Einmal je Plugin-Version anstoßen (z. B. nach dem Hochladen beim Hoster).
		if ( get_option( 'auhof_bilder_version' ) !== AUHOF_SCHULSEITEN_VERSION ) {
			update_option( 'auhof_bilder_version', AUHOF_SCHULSEITEN_VERSION, false );
			if ( ! wp_next_scheduled( 'auhof_bilder_bestand' ) ) {
				wp_schedule_single_event( time() + 30, 'auhof_bilder_bestand' );
			}
		}
	}
);
