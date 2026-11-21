<?php
// Test CSV-Export/-Import (Auhof Schulseiten 1.3.0). Stellt den Datenstand am Ende wieder her.
require_once WP_PLUGIN_DIR . '/auhof-schulseiten/includes/personen-admin.php';
require_once WP_PLUGIN_DIR . '/auhof-schulseiten/includes/personen-csv.php';
$GLOBALS['ok'] = 0; $GLOBALS['fehler'] = 0;
function t( $name, $b, $info = '' ) { $b ? $GLOBALS['ok']++ : $GLOBALS['fehler']++; WP_CLI::log( ( $b ? '  OK     ' : '  FEHLER ' ) . $name . ( $info ? " – $info" : '' ) ); }
function datei( $text ) { $f = wp_tempnam( 'csv' ); file_put_contents( $f, $text ); return $f; }
function csv_text( $kopf, $zeilen, $trenner = ';' ) {
	$s = fopen( 'php://temp', 'r+' ); fputcsv( $s, $kopf, $trenner, '"', '\\' ); foreach ( $zeilen as $z ) { fputcsv( $s, $z, $trenner, '"', '\\' ); } rewind( $s ); $t = stream_get_contents( $s ); fclose( $s ); return $t;
}
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $admin );
$kopf   = array_values( auhof_csv_spalten() );
$zeilen = auhof_csv_export_zeilen();
$anzahl = count( get_posts( array( 'post_type' => 'auhof_person', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'fields' => 'ids' ) ) );

WP_CLI::log( '1) Export → Import unverändert' );
$plan = auhof_csv_planen( auhof_csv_lesen( datei( "\xEF\xBB\xBF" . csv_text( $kopf, $zeilen ) ) ) );
$neu  = count( array_filter( $plan['aktionen'], function ( $a ) { return ! $a['ziel']; } ) );
$gea  = array_filter( $plan['aktionen'], function ( $a ) { return $a['ziel'] && $a['aenderungen']; } );
t( "Export enthält alle $anzahl Personen", count( $zeilen ) === $anzahl, count( $zeilen ) . '' );
t( 'Keine neuen, keine geänderten, keine fehlenden', 0 === $neu && ! $gea && ! $plan['fehlend'], "neu $neu, geändert " . count( $gea ) . ', fehlend ' . count( $plan['fehlend'] ) . ( $gea ? ' z. B. ' . reset( $gea )['name'] . ': ' . implode( ',', reset( $gea )['aenderungen'] ) : '' ) );
t( 'Keine Hinweise', ! $plan['warnungen'], implode( ' | ', array_slice( $plan['warnungen'], 0, 2 ) ) );
if ( $neu || $gea || $plan['fehlend'] || $plan['warnungen'] ) {
	WP_CLI::error( 'Abbruch: Export → Import ist nicht verlustfrei. Es wurde nichts geändert.' );
}

WP_CLI::log( '2) Geänderte Datei' );
$z2 = $zeilen;
$i_zusatz = array_search( 'Fächer', $kopf, true );
$alt_wert = $z2[0][ $i_zusatz ];
$z2[0][ $i_zusatz ] = 'TESTFACH';
$weg1 = array_pop( $z2 ); $weg2 = array_pop( $z2 );
$z2[] = array( '', 'Mag.', '<b>Test</b><script>x</script>', 'Prüfperson', '', '=HYPERLINK("http://x")', 'Prüfkoordination', 'pruef@example.org', '0732 1', 'Lehrkräfte', '3x', 'Informatik, Gibtsnicht', '' );
$plan = auhof_csv_planen( auhof_csv_lesen( datei( csv_text( $kopf, $z2 ) ) ) );
$neu  = array_values( array_filter( $plan['aktionen'], function ( $a ) { return ! $a['ziel']; } ) );
$gea  = array_values( array_filter( $plan['aktionen'], function ( $a ) { return $a['ziel'] && $a['aenderungen']; } ) );
t( '1 neu, 1 geändert, 2 fehlend', 1 === count( $neu ) && 1 === count( $gea ) && 2 === count( $plan['fehlend'] ), count( $neu ) . '/' . count( $gea ) . '/' . count( $plan['fehlend'] ) );
t( 'Schadcode im Namen entfernt', false === strpos( $neu[0]['daten']['vorname'], '<' ), $neu[0]['daten']['vorname'] );
t( 'KV „3x“ → „3X“', '3X' === $neu[0]['daten']['klasse'] );
t( 'Unbekannte Funktion als Hinweis', (bool) preg_grep( '/Gibtsnicht/', $plan['warnungen'] ) );
t( 'Bekannte Funktion zugeordnet', 1 === count( $neu[0]['funktionen'] ) );
t( 'Spalte Funktion gelesen', 'Prüfkoordination' === $neu[0]['daten']['funktion'] );
$alt_kopf = auhof_csv_lesen( datei( csv_text( array( 'Vorname', 'Nachname', 'Fächer / Funktion' ), array( array( 'A', 'B', 'D, E' ) ) ) ) );
t( 'Alte Spalte „Fächer / Funktion“ → Fächer', 'D, E' === auhof_csv_planen( $alt_kopf )['aktionen'][0]['daten']['zusatz'] );

WP_CLI::log( '3) Übernehmen und Papierkorb' );
$fehl_ids = array_keys( $plan['fehlend'] );
$fremd    = 100; // Startseite – darf nie gelöscht werden, auch wenn untergeschoben
$e = auhof_csv_anwenden( $plan, array( $fehl_ids[0], $fremd ) );
t( 'Ergebnis 1 neu, 1 geändert, 1 Papierkorb', 1 === $e['neu'] && 1 === $e['geaendert'] && 1 === $e['papierkorb'], json_encode( $e ) );
t( 'Startseite nicht angetastet', 'publish' === get_post_status( $fremd ) );
t( 'Nicht angehakte fehlende Person bleibt', 'publish' === get_post_status( $fehl_ids[1] ) );
t( 'Angehakte Person im Papierkorb', 'trash' === get_post_status( $fehl_ids[0] ) );
$neue_id = (int) get_posts( array( 'post_type' => 'auhof_person', 'meta_key' => '_auhof_email', 'meta_value' => 'pruef@example.org', 'fields' => 'ids', 'numberposts' => 1 ) )[0];
t( 'Neue Person mit Gruppe und Funktion', $neue_id && has_term( 'lehrkraefte', 'auhof_gruppe', $neue_id ) && has_term( 'informatik', 'auhof_funktion', $neue_id ) );
t( 'Excel-Formel als Text gespeichert, im Export geschützt', 0 === strpos( get_post_meta( $neue_id, '_auhof_zusatz', true ), '=' ) && "'" === substr( auhof_csv_zelle( get_post_meta( $neue_id, '_auhof_zusatz', true ) ), 0, 1 ) );
t( 'Telefon bleibt ohne Schutzzeichen', '0732 1' === auhof_csv_zelle( '0732 1' ) && '+43 732' === auhof_csv_zelle( '+43 732' ) );

WP_CLI::log( '4) Kodierung und Trennzeichen' );
$text = mb_convert_encoding( csv_text( array( 'Vorname', 'Nachname', 'E-Mail' ), array( array( 'Jörg', 'Müßig', 'jm@example.org' ) ), ',' ), 'Windows-1252', 'UTF-8' );
$p4   = auhof_csv_planen( auhof_csv_lesen( datei( $text ) ) );
t( 'Windows-1252 + Komma erkannt', 'Müßig' === $p4['aktionen'][0]['daten']['nachname'], $p4['aktionen'][0]['daten']['nachname'] );
t( 'Fehlende Spalten bleiben unverändert (keine Gruppen-/Funktionsänderung)', ! isset( $p4['aktionen'][0]['funktionen'] ) );
$p5 = auhof_csv_planen( auhof_csv_lesen( datei( "Vorname;Fach\nA;B\n" ) ) );
t( 'Ohne Spalte Nachname: Fehler', is_wp_error( $p5 ) );

WP_CLI::log( '5) Reihenfolge im Organigramm' );
$i_fkt = array_search( 'Funktionen', $kopf, true );
$mit   = null;
foreach ( $zeilen as $nr => $z ) {
	if ( $nr > 0 && preg_match( '/\((\d+)\)/', (string) $z[ $i_fkt ] ) ) { $mit = $nr; break; }
}
t( 'Export enthält Zahlen in Klammern', null !== $mit );
$pid       = (int) $zeilen[ $mit ][0];
$reihe_alt = auhof_funktion_reihenfolge( $pid );
$z6        = $zeilen[ $mit ];
$z6[ $i_fkt ] = preg_replace( '/\((\d+)\)/', '(77)', (string) $z6[ $i_fkt ], 1 );
$p6 = auhof_csv_planen( auhof_csv_lesen( datei( csv_text( $kopf, array( $z6 ) ) ) ) );
$g6 = array_values( array_filter( $p6['aktionen'], function ( $a ) { return $a['ziel'] && $a['aenderungen']; } ) );
t( 'Geänderte Zahl erkannt (nur Reihenfolge im Organigramm)', 1 === count( $g6 ) && array( 'Reihenfolge im Organigramm' ) === $g6[0]['aenderungen'], $g6 ? implode( ',', $g6[0]['aenderungen'] ) : 'keine' );
t( 'Keine Hinweise (Klammer gehört nicht zum Namen)', ! $p6['warnungen'], implode( ' | ', $p6['warnungen'] ) );
auhof_csv_anwenden( $p6, array() );
t( 'Zahl gespeichert, Funktionen unverändert', in_array( 77, auhof_funktion_reihenfolge( $pid ), true ) && count( auhof_funktion_reihenfolge( $pid ) ) === count( $reihe_alt ) );
$z7 = $zeilen[ $mit ];
$z7[ $i_fkt ] = preg_replace( '/\s*\(\d+\)/', '', (string) $z7[ $i_fkt ] );
auhof_csv_anwenden( auhof_csv_planen( auhof_csv_lesen( datei( csv_text( $kopf, array( $z7 ) ) ) ) ), array() );
t( 'Ohne Zahl in der Zelle: Reihenfolge entfernt', array() === auhof_funktion_reihenfolge( $pid ) );
auhof_funktion_reihenfolge_speichern( $pid, $reihe_alt );
// Organigramm folgt der Zahl: letzte Person einer Funktion bekommt 1, alle anderen 500.
$daten = auhof_organigramm_daten();
$fkt   = 0;
foreach ( $daten['nach_funktion'] as $tid => $l ) { if ( count( $l ) >= 3 ) { $fkt = $tid; break; } }
$mitglieder = get_posts( array( 'post_type' => 'auhof_person', 'numberposts' => -1, 'tax_query' => array( array( 'taxonomy' => 'auhof_funktion', 'terms' => $fkt, 'include_children' => false ) ) ) );
$nf1   = $daten['nach_funktion'][ $fkt ];
$letzt = end( $nf1 )['name'];
$sich  = array();
foreach ( $mitglieder as $m ) {
	$sich[ $m->ID ] = auhof_funktion_reihenfolge( $m->ID );
	$r = $sich[ $m->ID ];
	$r[ $fkt ] = trim( get_post_meta( $m->ID, '_auhof_vorname', true ) . ' ' . get_post_meta( $m->ID, '_auhof_nachname', true ) ) === $letzt ? 1 : 500;
	auhof_funktion_reihenfolge_speichern( $m->ID, $r );
}
$nf2  = auhof_organigramm_daten()['nach_funktion'][ $fkt ];
$erst = $nf2[0]['name'];
t( 'Organigramm: Person mit 1 steht vorn', $erst === $letzt, "$erst / $letzt" );
foreach ( $sich as $id => $r ) { auhof_funktion_reihenfolge_speichern( $id, $r ); }
$nf3 = auhof_organigramm_daten()['nach_funktion'][ $fkt ];
t( 'Reihenfolge wiederhergestellt', end( $nf3 )['name'] === $letzt && $reihe_alt === auhof_funktion_reihenfolge( $pid ) );

// Aufräumen: Datenstand wiederherstellen.
wp_untrash_post( $fehl_ids[0] );
wp_update_post( array( 'ID' => $fehl_ids[0], 'post_status' => 'publish' ) );
wp_delete_post( $neue_id, true );
update_post_meta( (int) $zeilen[0][0], '_auhof_zusatz', $alt_wert );
$nachher = count( get_posts( array( 'post_type' => 'auhof_person', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'fields' => 'ids' ) ) );
t( 'Datenstand wiederhergestellt', $nachher === $anzahl && $alt_wert === get_post_meta( (int) $zeilen[0][0], '_auhof_zusatz', true ), "$nachher Personen" );
WP_CLI::log( 'Ergebnis: ' . $GLOBALS['ok'] . ' bestanden, ' . $GLOBALS['fehler'] . ' fehlgeschlagen' );
