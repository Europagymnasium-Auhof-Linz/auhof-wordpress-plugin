<?php
/**
 * Test Terminvorschau: Ferien/Feiertage ausblenden (auhof_termine_ist_frei, ab Auhof 1.8.2).
 *   wp eval-file termine-frei-test.php
 * Titel = alle Einträge „Allgemein (Homepage)“ ohne Klasse aus dem Kalender vom 23.11.2026, dazu Grenzfälle.
 */
$ok = 0; $f = 0;
$faelle = array(
	"KV-Stunden" => false,
	"WHP schriftlich" => false,
	"WHP mündlich" => false,
	"KV Stunden" => false,
	"Welcome New LISA Families" => false,
	"KV Stunde" => false,
	"Unterricht lt. Stundenplan" => false,
	"1. Klassen Willkommensfeier" => false,
	"Ausgabe Aufbewahrungsboxen" => false,
	"TABE Elternabend" => false,
	"Fototermin 1. und 5. Klassen" => false,
	"Pädagogischer Nachmittag, unterrichtsfrei nach der 5 EH, TABE findet statt" => false,
	"Kennenlerntage Klasse 1D, 1E" => false,
	"Kennenlerntage Kl. 1A, 1B" => false,
	"Kennenlerntage Kl. 1L, 1M" => false,
	"JKU Ricam + Campusführung alle 5. Klassen" => false,
	"Japan Austausch der 5. Klassen" => false,
	"HPV-Impfung, 1. + 2. Klassen" => false,
	"Walkathon" => false,
	"Schulsprecherwahl Oberstufe" => false,
	"Teamtage 5. Klassen Burg Altpernstein" => false,
	"Oberstufen-Information 4. Klassen" => false,
	"Herbstferien" => true,
	"Allerseelen schulfrei" => true,
	"VS-Infoveranstaltung" => false,
	"EV Hauptversammlung ab 19:30 EVA Sitzung" => false,
	"8th grade Collaborative" => false,
	"schulfrei schulautonom" => true,
	"Feiertag Mariä Empfängnis" => true,
	"Elternsprechtag, ab 12:30 Uhr unterrichtsfrei, keine TABE" => false,
	"Weihnachtsferien" => true,
	"Semesterferien" => true,
	"eLLA Zirkus" => false,
	"Performance, Kl. 6L, 6M" => false,
	"Osterferien" => true,
	"Proben für Spring Gala" => false,
	"Florianitag" => true,
	"Feiertag Christi Himmelfahrt" => true,
	"Pfingstmontag" => true,
	"Schulfrei" => true,
	"CAS-Expo" => false,
	"Sommerferien" => true,
	'Ostermontag' => true,
	'Nationalfeiertag' => true,
	'Fronleichnam' => true,
	'Mariä Himmelfahrt' => true,
	'Maria Empfaengnis' => false,
	'Elternsprechtag, ab 12:30 Uhr unterrichtsfrei, keine TABE' => false,
	'Feierliche Zeugnisverteilung' => false,
	'Sommerfest' => false,
);
foreach ( $faelle as $titel => $erwartet ) {
	$ist = auhof_termine_ist_frei( $titel );
	if ( $ist === $erwartet ) { $ok++; } else { $f++; WP_CLI::log( "  FEHLER „$titel“: erwartet " . ( $erwartet ? 'ausblenden' : 'zeigen' ) ); }
}
WP_CLI::log( "  Kalendertitel und Grenzfälle: $ok richtig, $f falsch" );
// Kaputtes Muster über den Filter: nichts ausblenden, kein Fehler.
add_filter( 'auhof_termine_frei_muster', function () { return '/[kaputt'; } );
$r = auhof_termine_ist_frei( 'Sommerferien' );
remove_all_filters( 'auhof_termine_frei_muster' );
if ( false === $r ) { $ok++; } else { $f++; WP_CLI::log( '  FEHLER kaputtes Muster' ); }
// Ausgabe der Startseite: keine Ferien/Feiertage in der Vorschau.
delete_transient( 'auhof_termine_vorschau' );
$html = do_shortcode( '[auhof_termine anzahl="20"]' );
$tit = array();
preg_match_all( '#<li[^>]*>(.*?)</li>#s', $html, $m );
foreach ( $m[1] as $li ) { $tit[] = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $li ) ) ); }
$frei = array_filter( $tit, 'auhof_termine_ist_frei' );
if ( ! $frei ) { $ok++; } else { $f++; WP_CLI::log( '  FEHLER Vorschau enthält: ' . implode( ' | ', $frei ) ); }
WP_CLI::log( '  Vorschau (bis 20): ' . count( $tit ) . ' Termine, z. B. ' . implode( ' | ', array_slice( $tit, 0, 4 ) ) );
WP_CLI::log( "Ergebnis: $ok bestanden, $f fehlgeschlagen" );
