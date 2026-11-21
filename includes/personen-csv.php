<?php
/**
 * Personen als CSV exportieren und importieren (Excel-tauglich: Strichpunkt, UTF-8 mit BOM).
 *
 * Import in zwei Schritten: 1. Datei prüfen und Vorschau zeigen (nichts wird geändert),
 * 2. übernehmen – Personen in der Datei werden aktualisiert bzw. neu angelegt; Personen, die nicht in der
 * Datei stehen, werden nur in den Papierkorb gelegt, wenn sie in der Vorschau ausdrücklich angehakt wurden.
 * Spalten, die in der Datei fehlen, bleiben unverändert. Eine leere Zelle löscht den Wert.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Spalten der Vorlage: Schlüssel => Überschrift.
 *
 * @return array<string,string>
 */
function auhof_csv_spalten() {
	return array(
		'id'          => 'ID',
		'titel_vor'   => 'Titel vorn',
		'vorname'     => 'Vorname',
		'nachname'    => 'Nachname',
		'titel_nach'  => 'Titel hinten',
		'zusatz'      => 'Fächer / Funktion',
		'email'       => 'E-Mail',
		'telefon'     => 'Telefon',
		'gruppen'     => 'Gruppen',
		'klasse'      => 'Klassenvorstand',
		'funktionen'  => 'Funktionen',
		'reihenfolge' => 'Reihenfolge',
	);
}

/**
 * Überschrift → Spaltenschlüssel (auch übliche andere Schreibweisen).
 *
 * @param string $ueberschrift Überschrift aus der Datei.
 * @return string Schlüssel oder ''.
 */
function auhof_csv_spalte_erkennen( $ueberschrift ) {
	$n        = preg_replace( '/[^a-z0-9]/', '', auhof_personen_sortierschluessel( $ueberschrift ) );
	$synonyme = array(
		'id'          => array( 'id' ),
		'titel_vor'   => array( 'titelvorn', 'titelvor', 'titel', 'akadgrad', 'titelvorne' ),
		'vorname'     => array( 'vorname' ),
		'nachname'    => array( 'nachname', 'familienname', 'name' ),
		'titel_nach'  => array( 'titelhinten', 'titelnach' ),
		'zusatz'      => array( 'facherfunktion', 'facher', 'fach', 'funktiontext', 'zusatz', 'faecherfunktion', 'faecher' ),
		'email'       => array( 'email', 'mail', 'emailadresse' ),
		'telefon'     => array( 'telefon', 'tel', 'telefonnummer' ),
		'gruppen'     => array( 'gruppen', 'gruppe' ),
		'klasse'      => array( 'klassenvorstand', 'klasse', 'kv' ),
		'funktionen'  => array( 'funktionen' ),
		'reihenfolge' => array( 'reihenfolge', 'sortierung' ),
	);
	foreach ( $synonyme as $schluessel => $namen ) {
		if ( in_array( $n, $namen, true ) ) {
			return $schluessel;
		}
	}
	return '';
}

/**
 * Begriffe einer Taxonomie für Export/Import: Name (bei Mehrdeutigkeit „Eltern > Name“) => ID.
 *
 * @param string $taxonomie Taxonomie.
 * @return array<string,int>
 */
function auhof_csv_begriffe( $taxonomie ) {
	$terme = get_terms(
		array(
			'taxonomy'   => $taxonomie,
			'hide_empty' => false,
		)
	);
	$terme = is_wp_error( $terme ) ? array() : $terme;
	foreach ( $terme as $t ) {
		$t->name = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ); // WordPress speichert „&“ als „&amp;“.
	}
	$zahl = array();
	foreach ( $terme as $t ) {
		$k          = auhof_personen_sortierschluessel( $t->name );
		$zahl[ $k ] = isset( $zahl[ $k ] ) ? $zahl[ $k ] + 1 : 1;
	}
	$nach_id = array();
	foreach ( $terme as $t ) {
		$name = $t->name;
		if ( $zahl[ auhof_personen_sortierschluessel( $t->name ) ] > 1 && $t->parent ) {
			$eltern = get_term( $t->parent, $taxonomie );
			$name   = ( $eltern && ! is_wp_error( $eltern ) ? html_entity_decode( $eltern->name, ENT_QUOTES, 'UTF-8' ) : '' ) . ' > ' . $t->name;
		}
		$nach_id[ $t->term_id ] = $name;
	}
	return array_flip( $nach_id );
}

/**
 * Zellenwert gegen Formel-Ausführung in Excel schützen.
 *
 * @param string $wert Wert.
 * @return string
 */
function auhof_csv_zelle( $wert ) {
	$wert = (string) $wert;
	return preg_match( '/^[=+\-@\t\r]/', $wert ) && ! preg_match( '/^\+?[0-9 ()\/-]+$/', $wert ) ? "'" . $wert : $wert;
}

add_action( 'admin_menu', 'auhof_csv_menue' );

/**
 * Unterpunkt „Import/Export“ bei den Personen.
 */
function auhof_csv_menue() {
	add_submenu_page( 'edit.php?post_type=auhof_person', __( 'Personen importieren und exportieren', 'auhof-schulseiten' ), __( 'Import/Export', 'auhof-schulseiten' ), auhof_schulseiten_recht(), 'auhof-personen-csv', 'auhof_csv_seite' );
}

add_action( 'admin_post_auhof_personen_export', 'auhof_csv_export' );

/**
 * Alle Personen als CSV herunterladen.
 */
function auhof_csv_export() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'auhof-schulseiten' ), 403 );
	}
	check_admin_referer( 'auhof_personen_export' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="personen-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$aus = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $aus, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM, damit Excel UTF-8 erkennt.
	fputcsv( $aus, array_values( auhof_csv_spalten() ), ';', '"', '\\' );
	foreach ( auhof_csv_export_zeilen() as $zeile ) {
		fputcsv( $aus, $zeile, ';', '"', '\\' );
	}
	fclose( $aus ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

/**
 * Zeilen des Exports (ohne Kopfzeile).
 *
 * @return array<int,array<int,string|int>>
 */
function auhof_csv_export_zeilen() {
	$gruppen    = array_flip( auhof_csv_begriffe( 'auhof_gruppe' ) );
	$funktionen = array_flip( auhof_csv_begriffe( 'auhof_funktion' ) );
	$personen   = get_posts(
		array(
			'post_type'   => 'auhof_person',
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	$zeilen     = array();
	foreach ( $personen as $p ) {
		$zeile = array();
		foreach ( array_keys( auhof_csv_spalten() ) as $s ) {
			if ( 'id' === $s ) {
				$zeile[] = $p->ID;
			} elseif ( 'gruppen' === $s || 'funktionen' === $s ) {
				$ids     = wp_get_object_terms( $p->ID, 'gruppen' === $s ? 'auhof_gruppe' : 'auhof_funktion', array( 'fields' => 'ids' ) );
				$namen   = 'gruppen' === $s ? $gruppen : $funktionen;
				$zeile[] = implode(
					', ',
					array_filter(
						array_map(
							function ( $id ) use ( $namen ) {
								return isset( $namen[ $id ] ) ? $namen[ $id ] : '';
							},
							is_array( $ids ) ? $ids : array()
						)
					)
				);
			} elseif ( 'reihenfolge' === $s ) {
				$zeile[] = (int) $p->menu_order;
			} else {
				$zeile[] = auhof_csv_zelle( get_post_meta( $p->ID, '_auhof_' . $s, true ) );
			}
		}
		$zeilen[] = $zeile;
	}
	return $zeilen;
}

/**
 * Datei einlesen: Kodierung (UTF-8 oder Windows-1252), Trennzeichen (; , Tab) erkennen.
 *
 * @param string $pfad Temporäre Datei.
 * @return array{kopf:array<int,string>,zeilen:array<int,array<int,string>>}|WP_Error
 */
function auhof_csv_lesen( $pfad ) {
	$text = file_get_contents( $pfad ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- hochgeladene Datei, nur gelesen.
	if ( ! is_string( $text ) || '' === trim( $text ) ) {
		return new WP_Error( 'leer', __( 'Die Datei ist leer.', 'auhof-schulseiten' ) );
	}
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
	if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
	}
	$erste   = strtok( $text, "\n" );
	$zeichen = array(
		';'  => substr_count( $erste, ';' ),
		','  => substr_count( $erste, ',' ),
		"\t" => substr_count( $erste, "\t" ),
	);
	arsort( $zeichen );
	$trenner = key( $zeichen );

	$strom = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $strom, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	rewind( $strom );
	$kopf   = fgetcsv( $strom, 0, $trenner, '"', '\\' );
	$zeilen = array();
	while ( ( $z = fgetcsv( $strom, 0, $trenner, '"', '\\' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		if ( count( $zeilen ) >= 2000 ) {
			break;
		}
		if ( array_filter( array_map( 'trim', (array) $z ), 'strlen' ) ) {
			$zeilen[] = $z;
		}
	}
	fclose( $strom ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return array(
		'kopf'   => is_array( $kopf ) ? $kopf : array(),
		'zeilen' => $zeilen,
	);
}

/**
 * Mehrfachwerte („a, b“) einer Taxonomie zuordnen.
 *
 * @param string            $zelle    Zellinhalt.
 * @param array<string,int> $begriffe Name => ID.
 * @param string[]          $warnungen Warnungen (wird ergänzt).
 * @param string            $art      Bezeichnung für Warnungen.
 * @param int               $zeile    Zeilennummer.
 * @return int[]
 */
function auhof_csv_begriffe_zuordnen( $zelle, $begriffe, &$warnungen, $art, $zeile ) {
	$index = array();
	foreach ( $begriffe as $name => $id ) {
		$index[ auhof_personen_sortierschluessel( $name ) ] = $id;
		$t = get_term( $id );
		if ( $t && ! is_wp_error( $t ) ) {
			$index[ auhof_personen_sortierschluessel( $t->slug ) ] = $id;
		}
	}
	$ids = array();
	foreach ( preg_split( '/\s*[,|]\s*/', trim( $zelle ) ) as $teil ) {
		if ( '' === $teil ) {
			continue;
		}
		$k = auhof_personen_sortierschluessel( $teil );
		if ( isset( $index[ $k ] ) ) {
			$ids[] = $index[ $k ];
		} else {
			/* translators: 1: Zeile, 2: Art, 3: Wert */
			$warnungen[] = sprintf( __( 'Zeile %1$d: %2$s „%3$s“ unbekannt – ignoriert.', 'auhof-schulseiten' ), $zeile, $art, $teil );
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Prüft die Datei und erstellt den Plan (ohne etwas zu ändern).
 *
 * @param array{kopf:array<int,string>,zeilen:array<int,array<int,string>>} $csv Datei.
 * @return array<string,mixed>|WP_Error
 */
function auhof_csv_planen( $csv ) {
	$spalten = array();
	foreach ( $csv['kopf'] as $i => $ue ) {
		$s = auhof_csv_spalte_erkennen( (string) $ue );
		if ( $s && ! in_array( $s, $spalten, true ) ) {
			$spalten[ $i ] = $s;
		}
	}
	if ( ! in_array( 'nachname', $spalten, true ) ) {
		return new WP_Error( 'spalten', __( 'Die Spalte „Nachname“ fehlt. Bitte die Vorlage (Export) verwenden.', 'auhof-schulseiten' ) );
	}

	// Bestand für den Abgleich.
	$bestand = array();
	foreach ( get_posts(
		array(
			'post_type'   => 'auhof_person',
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => -1,
		)
	) as $p ) {
		$b = array(
			'id'          => $p->ID,
			'titel'       => $p->post_title,
			'reihenfolge' => (int) $p->menu_order,
			'gruppen'     => wp_get_object_terms( $p->ID, 'auhof_gruppe', array( 'fields' => 'ids' ) ),
			'funktionen'  => wp_get_object_terms( $p->ID, 'auhof_funktion', array( 'fields' => 'ids' ) ),
		);
		foreach ( array_keys( auhof_personen_felder() ) as $f ) {
			$b[ $f ] = (string) get_post_meta( $p->ID, '_auhof_' . $f, true );
		}
		$bestand[ $p->ID ] = $b;
	}
	$nach_mail = array();
	$nach_name = array();
	foreach ( $bestand as $id => $b ) {
		if ( '' !== $b['email'] ) {
			$nach_mail[ strtolower( $b['email'] ) ][] = $id;
		}
		$nach_name[ auhof_personen_sortierschluessel( $b['vorname'] . ' ' . $b['nachname'] ) ][] = $id;
	}

	$gruppen    = auhof_csv_begriffe( 'auhof_gruppe' );
	$funktionen = auhof_csv_begriffe( 'auhof_funktion' );
	$felder     = auhof_personen_felder();
	$warnungen  = array();
	$aktionen   = array();
	$getroffen  = array();

	foreach ( $csv['zeilen'] as $nr => $roh ) {
		$zeile = $nr + 2;
		$w     = array();
		foreach ( $spalten as $i => $s ) {
			$wert    = isset( $roh[ $i ] ) ? trim( (string) $roh[ $i ] ) : '';
			$w[ $s ] = preg_replace( '/^\'(?=[=+\-@])/', '', $wert ); // Schutzzeichen aus dem Export entfernen.
		}
		$daten = array();
		foreach ( $w as $s => $wert ) {
			if ( isset( $felder[ $s ] ) ) {
				$daten[ $s ] = auhof_personen_wert_bereinigen( $s, $wert );
			}
		}
		if ( '' === ( $daten['nachname'] ?? '' ) ) {
			/* translators: %d: Zeile */
			$warnungen[] = sprintf( __( 'Zeile %d: ohne Nachname – übersprungen.', 'auhof-schulseiten' ), $zeile );
			continue;
		}

		// Person finden: ID, dann E-Mail, dann Vor- und Nachname.
		$ziel = 0;
		$id   = isset( $w['id'] ) ? absint( $w['id'] ) : 0;
		if ( $id && isset( $bestand[ $id ] ) ) {
			$ziel = $id;
		} elseif ( '' !== ( $daten['email'] ?? '' ) && isset( $nach_mail[ strtolower( $daten['email'] ) ] ) ) {
			$treffer = $nach_mail[ strtolower( $daten['email'] ) ];
			if ( count( $treffer ) > 1 ) {
				/* translators: 1: Zeile, 2: E-Mail */
				$warnungen[] = sprintf( __( 'Zeile %1$d: E-Mail %2$s gehört zu mehreren Personen – bitte Spalte „ID“ verwenden; übersprungen.', 'auhof-schulseiten' ), $zeile, $daten['email'] );
				continue;
			}
			$ziel = $treffer[0];
		} else {
			$k = auhof_personen_sortierschluessel( ( $daten['vorname'] ?? '' ) . ' ' . $daten['nachname'] );
			if ( isset( $nach_name[ $k ] ) ) {
				if ( count( $nach_name[ $k ] ) > 1 ) {
					/* translators: %d: Zeile */
					$warnungen[] = sprintf( __( 'Zeile %d: Name kommt mehrfach vor – bitte Spalte „ID“ oder „E-Mail“ verwenden; übersprungen.', 'auhof-schulseiten' ), $zeile );
					continue;
				}
				$ziel = $nach_name[ $k ][0];
			}
		}
		if ( $ziel && isset( $getroffen[ $ziel ] ) ) {
			/* translators: 1: Zeile, 2: frühere Zeile */
			$warnungen[] = sprintf( __( 'Zeile %1$d: dieselbe Person wie in Zeile %2$d – übersprungen.', 'auhof-schulseiten' ), $zeile, $getroffen[ $ziel ] );
			continue;
		}

		$aktion = array(
			'ziel'  => $ziel,
			'daten' => $daten,
			'zeile' => $zeile,
		);
		if ( array_key_exists( 'gruppen', $w ) ) {
			$aktion['gruppen'] = auhof_csv_begriffe_zuordnen( $w['gruppen'], $gruppen, $warnungen, __( 'Gruppe', 'auhof-schulseiten' ), $zeile );
		} elseif ( ! $ziel && isset( $gruppen['Lehrkräfte'] ) ) {
			$aktion['gruppen'] = array( $gruppen['Lehrkräfte'] );
		}
		if ( array_key_exists( 'funktionen', $w ) ) {
			$aktion['funktionen'] = auhof_csv_begriffe_zuordnen( $w['funktionen'], $funktionen, $warnungen, __( 'Funktion', 'auhof-schulseiten' ), $zeile );
		}
		if ( array_key_exists( 'reihenfolge', $w ) && '' !== $w['reihenfolge'] ) {
			$aktion['reihenfolge'] = min( 9999, absint( $w['reihenfolge'] ) );
		}

		// Änderungen gegenüber dem Bestand.
		$aenderungen = array();
		if ( $ziel ) {
			$alt = $bestand[ $ziel ];
			foreach ( $daten as $f => $wert ) {
				if ( $alt[ $f ] !== $wert ) {
					$aenderungen[] = $felder[ $f ];
				}
			}
			foreach ( array(
				'gruppen'    => __( 'Gruppen', 'auhof-schulseiten' ),
				'funktionen' => __( 'Funktionen', 'auhof-schulseiten' ),
			) as $f => $label ) {
				if ( isset( $aktion[ $f ] ) ) {
					$a = array_map( 'intval', (array) $alt[ $f ] );
					$b = $aktion[ $f ];
					sort( $a );
					sort( $b );
					if ( $a !== $b ) {
						$aenderungen[] = $label;
					}
				}
			}
			if ( isset( $aktion['reihenfolge'] ) && $aktion['reihenfolge'] !== $alt['reihenfolge'] ) {
				$aenderungen[] = __( 'Reihenfolge', 'auhof-schulseiten' );
			}
			$getroffen[ $ziel ] = $zeile;
		}
		$aktion['aenderungen'] = $aenderungen;
		$aktion['name']        = trim( ( $daten['vorname'] ?? ( $ziel ? $bestand[ $ziel ]['vorname'] : '' ) ) . ' ' . $daten['nachname'] );
		$aktionen[]            = $aktion;
	}

	$fehlend = array();
	foreach ( $bestand as $id => $b ) {
		if ( ! isset( $getroffen[ $id ] ) ) {
			$fehlend[ $id ] = trim( $b['vorname'] . ' ' . $b['nachname'] );
		}
	}
	asort( $fehlend );

	return array(
		'aktionen'  => $aktionen,
		'fehlend'   => $fehlend,
		'warnungen' => $warnungen,
		'spalten'   => array_values( $spalten ),
	);
}

add_action( 'admin_post_auhof_personen_import_pruefen', 'auhof_csv_pruefen' );

/**
 * Schritt 1: Datei hochladen und prüfen.
 */
function auhof_csv_pruefen() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'auhof-schulseiten' ), 403 );
	}
	check_admin_referer( 'auhof_personen_import_pruefen' );
	$zurueck = admin_url( 'edit.php?post_type=auhof_person&page=auhof-personen-csv' );
	$datei   = isset( $_FILES['auhof_csv'] ) && is_array( $_FILES['auhof_csv'] ) ? $_FILES['auhof_csv'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur tmp_name/Größe/Name werden geprüft.
	$fehler  = '';
	if ( empty( $datei['tmp_name'] ) || ! is_uploaded_file( $datei['tmp_name'] ) || ! empty( $datei['error'] ) ) {
		$fehler = __( 'Keine Datei empfangen.', 'auhof-schulseiten' );
	} elseif ( (int) $datei['size'] > 2 * MB_IN_BYTES ) {
		$fehler = __( 'Die Datei ist größer als 2 MB.', 'auhof-schulseiten' );
	} elseif ( ! preg_match( '/\.(csv|txt)$/i', (string) $datei['name'] ) ) {
		$fehler = __( 'Bitte eine CSV-Datei hochladen.', 'auhof-schulseiten' );
	}
	if ( ! $fehler ) {
		$csv  = auhof_csv_lesen( $datei['tmp_name'] );
		$plan = is_wp_error( $csv ) ? $csv : auhof_csv_planen( $csv );
		if ( is_wp_error( $plan ) ) {
			$fehler = $plan->get_error_message();
		} else {
			set_transient( 'auhof_csv_plan_' . get_current_user_id(), $plan, 30 * MINUTE_IN_SECONDS );
		}
	}
	if ( $fehler ) {
		set_transient( 'auhof_csv_meldung_' . get_current_user_id(), array( 'error', $fehler ), 5 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( $zurueck );
	exit;
}

add_action( 'admin_post_auhof_personen_import_ausfuehren', 'auhof_csv_ausfuehren' );

/**
 * Schritt 2: Plan übernehmen.
 */
function auhof_csv_ausfuehren() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'auhof-schulseiten' ), 403 );
	}
	check_admin_referer( 'auhof_personen_import_ausfuehren' );
	$schluessel = 'auhof_csv_plan_' . get_current_user_id();
	$plan       = get_transient( $schluessel );
	$zurueck    = admin_url( 'edit.php?post_type=auhof_person&page=auhof-personen-csv' );
	if ( isset( $_POST['abbrechen'] ) || ! is_array( $plan ) ) {
		delete_transient( $schluessel );
		wp_safe_redirect( $zurueck );
		exit;
	}
	$gewaehlt = isset( $_POST['papierkorb'] ) && is_array( $_POST['papierkorb'] ) ? array_map( 'absint', wp_unslash( $_POST['papierkorb'] ) ) : array();
	$ergebnis = auhof_csv_anwenden( $plan, $gewaehlt );
	delete_transient( $schluessel );
	set_transient(
		'auhof_csv_meldung_' . get_current_user_id(),
		/* translators: 1: neu, 2: geändert, 3: Papierkorb */
		array( 'success', sprintf( __( 'Übernommen: %1$d neu, %2$d geändert, %3$d in den Papierkorb gelegt.', 'auhof-schulseiten' ), $ergebnis['neu'], $ergebnis['geaendert'], $ergebnis['papierkorb'] ) ),
		5 * MINUTE_IN_SECONDS
	);
	wp_safe_redirect( $zurueck );
	exit;
}

/**
 * Plan anwenden. Nur Personen aus der Liste „fehlend“ des Plans können in den Papierkorb.
 *
 * @param array<string,mixed> $plan     Plan aus auhof_csv_planen().
 * @param int[]               $gewaehlt Für den Papierkorb angehakte Personen.
 * @return array{neu:int,geaendert:int,papierkorb:int}
 */
function auhof_csv_anwenden( $plan, $gewaehlt ) {
	$neu       = 0;
	$geaendert = 0;
	foreach ( $plan['aktionen'] as $a ) {
		if ( $a['ziel'] && ! $a['aenderungen'] ) {
			continue;
		}
		$titel = $a['name'];
		$post  = array(
			'post_type'  => 'auhof_person',
			'post_title' => $titel,
		);
		if ( isset( $a['reihenfolge'] ) ) {
			$post['menu_order'] = $a['reihenfolge'];
		}
		if ( $a['ziel'] ) {
			if ( 'auhof_person' !== get_post_type( $a['ziel'] ) || ! current_user_can( 'edit_post', $a['ziel'] ) ) {
				continue;
			}
			$post['ID'] = $a['ziel'];
			$id         = wp_update_post( wp_slash( $post ), true );
			++$geaendert;
		} else {
			$post['post_status'] = 'publish';
			$id                  = wp_insert_post( wp_slash( $post ), true );
			++$neu;
		}
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		foreach ( $a['daten'] as $f => $wert ) {
			update_post_meta( $id, '_auhof_' . $f, $wert );
		}
		if ( ! $a['ziel'] ) {
			foreach ( array_keys( auhof_personen_felder() ) as $f ) {
				if ( ! isset( $a['daten'][ $f ] ) ) {
					update_post_meta( $id, '_auhof_' . $f, '' );
				}
			}
		}
		if ( isset( $a['gruppen'] ) ) {
			wp_set_object_terms( $id, array_map( 'intval', $a['gruppen'] ), 'auhof_gruppe' );
		}
		if ( isset( $a['funktionen'] ) ) {
			wp_set_object_terms( $id, array_map( 'intval', $a['funktionen'] ), 'auhof_funktion' );
		}
	}

	// Nur ausdrücklich angehakte und in der Vorschau aufgeführte Personen in den Papierkorb.
	$weg = 0;
	foreach ( array_intersect( array_map( 'intval', $gewaehlt ), array_map( 'intval', array_keys( $plan['fehlend'] ) ) ) as $id ) {
		if ( 'auhof_person' === get_post_type( $id ) && current_user_can( 'delete_post', $id ) && wp_trash_post( $id ) ) {
			++$weg;
		}
	}
	return array(
		'neu'        => $neu,
		'geaendert'  => $geaendert,
		'papierkorb' => $weg,
	);
}

/**
 * Seite „Import/Export“.
 */
function auhof_csv_seite() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		return;
	}
	$nutzer  = get_current_user_id();
	$meldung = get_transient( 'auhof_csv_meldung_' . $nutzer );
	delete_transient( 'auhof_csv_meldung_' . $nutzer );
	$plan = get_transient( 'auhof_csv_plan_' . $nutzer );
	echo '<div class="wrap auhof-csv"><h1>' . esc_html__( 'Personen importieren und exportieren', 'auhof-schulseiten' ) . '</h1>';
	if ( is_array( $meldung ) ) {
		echo '<div class="notice notice-' . esc_attr( 'error' === $meldung[0] ? 'error' : 'success' ) . '"><p>' . esc_html( $meldung[1] ) . '</p></div>';
	}

	if ( is_array( $plan ) ) {
		$neu       = array_filter(
			$plan['aktionen'],
			function ( $a ) {
				return ! $a['ziel'];
			}
		);
		$geaendert = array_filter(
			$plan['aktionen'],
			function ( $a ) {
				return $a['ziel'] && $a['aenderungen'];
			}
		);
		$gleich    = count( $plan['aktionen'] ) - count( $neu ) - count( $geaendert );
		echo '<h2>' . esc_html__( 'Vorschau – noch nichts geändert', 'auhof-schulseiten' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'auhof_personen_import_ausfuehren' );
		echo '<input type="hidden" name="action" value="auhof_personen_import_ausfuehren">';
		/* translators: 1: neu, 2: geändert, 3: unverändert */
		echo '<p><strong>' . esc_html( sprintf( __( '%1$d neu, %2$d geändert, %3$d unverändert.', 'auhof-schulseiten' ), count( $neu ), count( $geaendert ), $gleich ) ) . '</strong></p>';
		if ( $neu ) {
			echo '<details open><summary>' . esc_html__( 'Neu', 'auhof-schulseiten' ) . '</summary><ul class="auhof-csv-liste">';
			foreach ( $neu as $a ) {
				echo '<li>' . esc_html( $a['name'] ) . '</li>';
			}
			echo '</ul></details>';
		}
		if ( $geaendert ) {
			echo '<details open><summary>' . esc_html__( 'Geändert', 'auhof-schulseiten' ) . '</summary><ul class="auhof-csv-liste">';
			foreach ( $geaendert as $a ) {
				echo '<li>' . esc_html( $a['name'] ) . ' <span class="description">(' . esc_html( implode( ', ', $a['aenderungen'] ) ) . ')</span></li>';
			}
			echo '</ul></details>';
		}
		if ( $plan['fehlend'] ) {
			echo '<fieldset class="auhof-csv-fehlend"><legend>' . esc_html( sprintf( /* translators: %d: Anzahl */ __( 'Nicht in der Datei (%d) – anhaken = in den Papierkorb, sonst bleiben sie', 'auhof-schulseiten' ), count( $plan['fehlend'] ) ) ) . '</legend>';
			echo '<p><button type="button" class="button-link" data-alle="1">' . esc_html__( 'alle anhaken', 'auhof-schulseiten' ) . '</button> · <button type="button" class="button-link" data-alle="0">' . esc_html__( 'keine', 'auhof-schulseiten' ) . '</button></p><ul class="auhof-csv-liste">';
			foreach ( $plan['fehlend'] as $id => $name ) {
				echo '<li><label><input type="checkbox" name="papierkorb[]" value="' . esc_attr( (string) $id ) . '"> ' . esc_html( $name ) . '</label></li>';
			}
			echo '</ul></fieldset>';
		}
		if ( $plan['warnungen'] ) {
			echo '<details><summary>' . esc_html( sprintf( /* translators: %d: Anzahl */ __( 'Hinweise (%d)', 'auhof-schulseiten' ), count( $plan['warnungen'] ) ) ) . '</summary><ul class="auhof-csv-liste">';
			foreach ( $plan['warnungen'] as $w ) {
				echo '<li>' . esc_html( $w ) . '</li>';
			}
			echo '</ul></details>';
		}
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Übernehmen', 'auhof-schulseiten' ) . '</button> <button type="submit" name="abbrechen" value="1" class="button">' . esc_html__( 'Abbrechen', 'auhof-schulseiten' ) . '</button></p>';
		echo '</form></div>';
		echo '<script>document.querySelectorAll(".auhof-csv [data-alle]").forEach(function(b){b.addEventListener("click",function(){var an=b.getAttribute("data-alle")==="1";document.querySelectorAll(".auhof-csv-fehlend input[type=checkbox]").forEach(function(c){c.checked=an;});});});</script>';
		return;
	}

	echo '<h2>' . esc_html__( 'Export', 'auhof-schulseiten' ) . '</h2>';
	echo '<p>' . esc_html__( 'Alle Personen als Excel-taugliche CSV-Datei – zugleich die Vorlage für den Import.', 'auhof-schulseiten' ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=auhof_personen_export' ), 'auhof_personen_export' ) ) . '">' . esc_html__( 'CSV herunterladen', 'auhof-schulseiten' ) . '</a></p>';

	echo '<h2>' . esc_html__( 'Import', 'auhof-schulseiten' ) . '</h2>';
	echo '<p>' . esc_html__( 'Spalten wie im Export. Personen werden über ID, sonst E-Mail, sonst Vor- und Nachname erkannt. Fehlende Spalten bleiben unverändert, leere Zellen löschen den Wert. Mehrere Gruppen oder Funktionen mit Komma trennen.', 'auhof-schulseiten' ) . '</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'auhof_personen_import_pruefen' );
	echo '<input type="hidden" name="action" value="auhof_personen_import_pruefen">';
	echo '<p><label for="auhof-csv-datei">' . esc_html__( 'CSV-Datei (max. 2 MB)', 'auhof-schulseiten' ) . '</label><br><input type="file" id="auhof-csv-datei" name="auhof_csv" accept=".csv,text/csv" required></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Prüfen (Vorschau)', 'auhof-schulseiten' ) . '</button></p></form></div>';
}
