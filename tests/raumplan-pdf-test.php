<?php
/**
 * Test für includes/raumplan-pdf.php. Ablauf:
 *   lokal:   node tests/raumplan-pdf-prototyp.mjs <plugin>/data/raumplan.json proto.pdf "Schuljahr 2026/27"
 *            proto.pdf nach /tmp/proto.pdf auf die Testseite kopieren
 *   Server:  wp eval-file tests/raumplan-pdf-test.php
 * Prüft: byte-gleich mit dem JavaScript-Prototyp, PDF-Aufbau, Maskierung, Rechte, Datei-Ort.
 * Ändert keine Einstellungen; erzeugt die PDF-Datei neu.
 */
$GLOBALS['rp_ok']     = 0;
$GLOBALS['rp_fehler'] = 0;
function rp_t( $name, $b, $info = '' ) {
	if ( $b ) {
		$GLOBALS['rp_ok']++;
		WP_CLI::log( "  OK     $name" );
	} else {
		$GLOBALS['rp_fehler']++;
		WP_CLI::log( "  FEHLER $name $info" );
	}
}

$plan         = auhof_raumplan_geometrie();
$plan['year'] = 'Schuljahr 2026/27';
$pdf          = auhof_raumplan_pdf_erzeugen( $plan );
rp_t( 'PDF erzeugt', strlen( $pdf ) > 20000, strlen( $pdf ) . ' Bytes' );
rp_t( 'Kopf und Ende', 0 === strpos( $pdf, '%PDF-1.4' ) && "%%EOF\n" === substr( $pdf, -6 ) );
if ( is_file( '/tmp/proto.pdf' ) ) {
	$proto = file_get_contents( '/tmp/proto.pdf' );
	// Gleich bis auf Rundung der 3. Nachkommastelle (PHP round vs. JavaScript Math.round) und die daraus folgenden Längen/Positionen.
	$norm = function ( $d ) {
		$d = substr( $d, 0, (int) strpos( $d, "endstream" ) );
		$d = preg_replace( '#/Length \d+#', '/Length N', $d );
		return preg_replace_callback( '/-?\d+\.\d+/', function ( $m ) { return (string) round( (float) $m[0], 2 ); }, $d );
	};
	rp_t( 'Inhalt gleich wie der JavaScript-Prototyp (auf 2 Nachkommastellen)', $norm( $proto ) === $norm( $pdf ), 'Längen ' . strlen( $pdf ) . '/' . strlen( $proto ) );
} else {
	WP_CLI::log( '  (kein /tmp/proto.pdf – Vergleich übersprungen)' );
}
preg_match( '/startxref\n(\d+)\n/', $pdf, $m );
rp_t( 'xref-Zeiger stimmt', 'xref' === substr( $pdf, (int) $m[1], 4 ) );
preg_match_all( '/\n(\d{10}) 00000 n /', $pdf, $x );
$alle = 7 === count( $x[1] );
foreach ( $x[1] as $i => $pos ) {
	$alle = $alle && 0 === strpos( substr( $pdf, (int) $pos, 12 ), ( $i + 1 ) . ' 0 obj' );
}
rp_t( 'Alle 7 Objekt-Positionen stimmen', $alle );
preg_match( '/\/Length (\d+) >>\nstream\n/', $pdf, $l, PREG_OFFSET_CAPTURE );
rp_t( 'Stream-Länge stimmt', 'endstream' === substr( $pdf, $l[0][1] + strlen( $l[0][0] ) + (int) $l[1][0], 9 ) );
rp_t( 'Keine aktiven Inhalte', ! preg_match( '#/(JS|JavaScript|OpenAction|AA|Launch|URI|EmbeddedFile|AcroForm)\b#', $pdf ) );

WP_CLI::log( 'Maskierung' );
rp_t( 'Klammern/Rückstrich maskiert', '(a\\(b\\)c\\\\d)' === auhof_raumplan_pdf_kette( 'a(b)c\\d' ) );
rp_t( 'Umlaute in WinAnsi', "(\xC4\xF6\xFC\xDF \x96 \xB7)" === auhof_raumplan_pdf_kette( 'Äöüß – ·' ) );
rp_t( 'Unbekannte Zeichen werden ?', '(?? ?)' === auhof_raumplan_pdf_kette( "😀Ω \x01" ), auhof_raumplan_pdf_kette( "😀Ω \x01" ) );
$boese                  = $plan;
$boese['rooms'][5]['n'] = ') Tj ET /JS (x) ( ';
$boese['rooms'][5]['d'] = ') Tj ET /JS (x) ( ';
$boese['year']          = 'X) >> /OpenAction << /S /JavaScript (';
$boese['fills'][0][4]   = 'red) /JS (';
$p2                     = auhof_raumplan_pdf_erzeugen( $boese );
// Jede schließende Klammer aus den Eingaben muss maskiert sein: „) Tj“ darf nur nach einer echten Zeichenkette stehen.
rp_t( 'Schadtext bleibt Text', false === strpos( $p2, '() Tj ET /JS' ) && false !== strpos( $p2, '\\) Tj ET /JS \\(x\\) \\(' ) && false !== strpos( $p2, 'X\\) >> /OpenAction' ) && 7 === substr_count( $p2, "endobj\n" ) );
rp_t( 'Ungültige Farbe wird Weiß', false === strpos( $p2, 'red' ) );
rp_t( 'Zahl unabhängig von der Sprache', '1.5' === auhof_raumplan_pdf_zahl( 1.5 ) && '0' === auhof_raumplan_pdf_zahl( -0.0001 ) && '12' === auhof_raumplan_pdf_zahl( 12.0 ) );
rp_t( 'Unvollständiger Plan → leer', '' === auhof_raumplan_pdf_erzeugen( array( 'rooms' => array() ) ) );

WP_CLI::log( 'Datei und Rechte' );
$ort = auhof_raumplan_pdf_ort();
rp_t( 'Fester Ort im Upload-Ordner', 0 === strpos( $ort['pfad'], wp_get_upload_dir()['basedir'] . '/auhof-raumplan/' ) && 'raumplan.pdf' === basename( $ort['pfad'] ) );
wp_set_current_user( 0 );
delete_option( 'auhof_raumplan_pdf_stand' );
auhof_raumplan_pdf_pruefen();
rp_t( 'Ohne Anmeldung wird nichts erzeugt', '' === (string) get_option( 'auhof_raumplan_pdf_stand', '' ) );
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
auhof_raumplan_pdf_pruefen();
rp_t( 'Berechtigt: Datei erzeugt, Stand gesetzt', is_file( $ort['pfad'] ) && 0 === strpos( (string) get_option( 'auhof_raumplan_pdf_stand' ), AUHOF_SCHULSEITEN_VERSION . '|' ) );
rp_t( 'Adresse mit Versionsanhang', (bool) preg_match( '#/auhof-raumplan/raumplan\.pdf\?v=[0-9a-f]{10}$#', auhof_raumplan_pdf_url() ), auhof_raumplan_pdf_url() );
rp_t( 'Knopf „PDF“ im Raumplan', false !== strpos( auhof_raumplan_html( false ), 'class="rp-pdf"' ) );
WP_CLI::log( 'Ergebnis: ' . $GLOBALS['rp_ok'] . ' bestanden, ' . $GLOBALS['rp_fehler'] . ' fehlgeschlagen' );
