<?php
/**
 * Test für „Aktuelle Infos“ (includes/infos.php, infos-admin.php).
 *   wp --exec='define("WP_ADMIN",true);' eval-file tests/infos-test.php [/tmp/infos-post.json]
 * Prüft Bereinigung, Schadcode, Rechte, leere Ausgabe. Stellt den vorherigen Wert am Ende wieder her,
 * außer es wird eine Formular-Datei übergeben (dann bleibt deren Ergebnis gespeichert).
 */
require_once WP_PLUGIN_DIR . '/auhof-schulseiten/includes/infos-admin.php';
$GLOBALS['it_ok'] = 0; $GLOBALS['it_f'] = 0;
function it( $n, $b, $i = '' ) { if ( $b ) { $GLOBALS['it_ok']++; WP_CLI::log( "  OK     $n" ); } else { $GLOBALS['it_f']++; WP_CLI::log( "  FEHLER $n $i" ); } }
$vorher = get_option( 'auhof_infos', null );
$admin  = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];

WP_CLI::log( 'Bereinigung und Ausgabe' );
$b = auhof_infos_bereinigen( array(
	'ueberschrift' => 'Aktuelle Infos<script>x</script>',
	'eintraege'    => array(
		'a' => array( 'anzeigen' => '1', 'titel' => '<img src=x onerror=alert(1)>Infoabend', 'text' => "Zeile 1\nZeile 2 <b>fett</b>", 'link' => 'javascript:alert(1)' ),
		'b' => array( 'titel' => 'Versteckt', 'text' => 'nicht anzeigen' ),
		'c' => array( 'anzeigen' => '1', 'titel' => '   ', 'text' => 'ohne Titel' ),
		'd' => 'kein Array',
		'e' => array( 'anzeigen' => '1', 'titel' => 'Mail', 'link' => 'mailto:office@europagym.at' ),
		'f' => array( 'anzeigen' => '1', 'titel' => str_repeat( 'Ä', 300 ), 'link' => 'https://example.org/a"b' ),
	),
) );
it( 'Einträge ohne Titel/kein Array entfallen', 4 === count( $b['eintraege'] ), count( $b['eintraege'] ) );
it( 'HTML aus Überschrift/Titel entfernt', false === strpos( $b['ueberschrift'] . $b['eintraege'][0]['titel'], '<' ) );
it( 'javascript:-Link verworfen', '' === $b['eintraege'][0]['link'] );
it( 'mailto erlaubt', 'mailto:office@europagym.at' === $b['eintraege'][2]['link'] );
it( 'Titel auf 100 Zeichen gekürzt', 100 === mb_strlen( $b['eintraege'][3]['titel'] ) );
it( 'Anzeigen ohne Häkchen = aus', false === $b['eintraege'][1]['anzeigen'] );
$zu = array( 'eintraege' => array() );
for ( $i = 0; $i < 20; $i++ ) { $zu['eintraege'][] = array( 'anzeigen' => '1', 'titel' => "T$i" ); }
it( 'Höchstens 12 Einträge', 12 === count( auhof_infos_bereinigen( $zu )['eintraege'] ) );

update_option( 'auhof_infos', $b );
$html = do_shortcode( '[auhof_infos]' );
it( 'Nur angehakte erscheinen', 3 === substr_count( $html, '<li class="auhof-info"' ) && false === strpos( $html, 'Versteckt' ) );
it( 'Kein ausführbares HTML in der Ausgabe', ! preg_match( '/<(script|img|iframe)|onerror=|javascript:/i', $html ) );
it( 'Zeilenumbruch als <br>, fett bleibt', false !== strpos( $html, 'Zeile 1<br />' ) && false !== strpos( $html, 'Zeile 2 <b>fett</b>' ) );
$k = auhof_infos_text_bereinigen( 'Siehe <a href="https://example.org/x" onclick="alert(1)" style="color:red" target="_blank">Anmeldeformular</a>, <a href="javascript:alert(1)">böse</a>, <strong>wichtig</strong> <em>kursiv</em> <img src=x onerror=alert(1)><script>alert(2)</script><iframe src=x></iframe> <a href="mailto:office@europagym.at">Mail</a> <span style="x">Span</span> <strong>offen' );
it( 'Link mit https bleibt, ohne onclick/style/target', false !== strpos( $k, '<a href="https://example.org/x">Anmeldeformular</a>' ), $k );
it( 'javascript:-Link entschärft', false === stripos( $k, 'javascript' ), $k );
it( 'mailto-Link bleibt', false !== strpos( $k, '<a href="mailto:office@europagym.at">Mail</a>' ) );
it( 'strong/em bleiben', false !== strpos( $k, '<strong>wichtig</strong> <em>kursiv</em>' ) );
it( 'img/script/iframe/span entfernt', ! preg_match( '/<(img|script|iframe|span)/i', $k ) && false === strpos( $k, 'onerror' ), $k );
it( 'Offenes Tag wird geschlossen', '</strong>' === substr( $k, -9 ), $k );
it( 'Höchstens 1000 Zeichen Rohtext', mb_strlen( auhof_infos_text_bereinigen( str_repeat( 'x', 1500 ) ) ) === 1000 );
it( 'Überschrift als h2 mit Bezug', false !== strpos( $html, '<section class="auhof-infos" aria-labelledby="auhof-infos-titel">' ) );
update_option( 'auhof_infos', array( 'ueberschrift' => 'X', 'eintraege' => array( array( 'anzeigen' => false, 'titel' => 'A' ) ) ) );
it( 'Keine angehakt → gar keine Ausgabe', '' === do_shortcode( '[auhof_infos]' ) );
update_option( 'auhof_infos', array() );
it( 'Leer → gar keine Ausgabe', '' === do_shortcode( '[auhof_infos]' ) );

WP_CLI::log( 'Bilder' );
$bild_mit_alt = (int) $GLOBALS['wpdb']->get_var( "SELECT p.ID FROM {$GLOBALS['wpdb']->posts} p JOIN {$GLOBALS['wpdb']->postmeta} m ON m.post_id=p.ID AND m.meta_key='_wp_attachment_image_alt' WHERE p.post_type='attachment' AND p.post_mime_type='image/jpeg' AND m.meta_value<>'' ORDER BY p.ID DESC LIMIT 1" );
$bb = auhof_infos_bereinigen( array( 'eintraege' => array(
	array( 'anzeigen' => '1', 'titel' => 'Groß', 'bild' => (string) $bild_mit_alt, 'bildart' => 'gross' ),
	array( 'anzeigen' => '1', 'titel' => 'Klein', 'bild' => $bild_mit_alt, 'bildart' => 'unbekannt' ),
	array( 'anzeigen' => '1', 'titel' => 'Kein Bild (Seite)', 'bild' => '100', 'bildart' => 'gross' ),
	array( 'anzeigen' => '1', 'titel' => 'Schadcode', 'bild' => '1 OR 1=1', 'bildart' => '"><script>' ),
) ) );
it( 'Bild-ID aus der Mediathek übernommen', $bild_mit_alt > 0 && $bild_mit_alt === $bb['eintraege'][0]['bild'] && 'gross' === $bb['eintraege'][0]['bildart'] );
it( 'Unbekannte Darstellung → klein', 'klein' === $bb['eintraege'][1]['bildart'] );
it( 'Keine Bilddatei (Seite 100) → kein Bild', 0 === $bb['eintraege'][2]['bild'] );
it( 'Schadcode in Bild/Darstellung verworfen', 1 === $bb['eintraege'][3]['bild'] ? false : ( 'klein' === $bb['eintraege'][3]['bildart'] ) );
update_option( 'auhof_infos', $bb );
$hb = do_shortcode( '[auhof_infos]' );
it( 'Groß: Bild mit Klasse und lazy', (bool) preg_match( '#<li class="auhof-info auhof-info--gross"><div class="auhof-info__karte"><img[^>]+class="auhof-info__bild"[^>]+loading="lazy"#', $hb ), substr( $hb, 0, 300 ) );
it( 'Klein: Bild mit Klasse', false !== strpos( $hb, 'auhof-info auhof-info--klein' ) );
it( 'Alternativtext aus der Mediathek', false !== strpos( $hb, 'alt="' . esc_attr( get_post_meta( $bild_mit_alt, '_wp_attachment_image_alt', true ) ) . '"' ) );
it( 'Ohne Bild: keine Bildklasse', (bool) preg_match( '#<li class="auhof-info"><div class="auhof-info__karte"><div class="auhof-info__inhalt"><h3 class="auhof-info__titel">Kein Bild#', $hb ) );
it( 'srcset für passende Größe', false !== strpos( $hb, 'srcset=' ) );

WP_CLI::log( 'Rechte' );
foreach ( array( 'subscriber' => false, 'contributor' => false, 'author' => false, 'editor' => true, 'administrator' => true ) as $rolle => $darf ) {
	$u = new WP_User(); $u->ID = 999999; $u->caps = array( $rolle => true ); $u->roles = array( $rolle ); $u->get_role_caps();
	it( "$rolle darf speichern: " . ( $darf ? 'ja' : 'nein' ), $darf === $u->has_cap( apply_filters( 'option_page_capability_auhof_infos', 'manage_options' ) ) );
}
it( 'Nicht in der REST-API', ! isset( get_registered_settings()['auhof_infos'] ) || empty( get_registered_settings()['auhof_infos']['show_in_rest'] ) );

if ( isset( $args[0] ) && is_file( $args[0] ) ) {
	WP_CLI::log( 'Formulardaten aus dem Browser speichern' );
	$post = array();
	foreach ( json_decode( file_get_contents( $args[0] ), true ) as $paar ) {
		parse_str( rawurlencode( $paar[0] ) . '=' . rawurlencode( $paar[1] ), $teil );
		$post = array_replace_recursive( $post, $teil );
	}
	wp_set_current_user( $admin );
	update_option( 'auhof_infos', auhof_infos_bereinigen( $post['auhof_infos'] ) );
	$e = auhof_infos_einstellungen();
	it( 'Gespeichert in Formular-Reihenfolge', 'Neuanmeldungen|Volksschul-Infoabend|Elternabend' === implode( '|', wp_list_pluck( $e['eintraege'], 'titel' ) ), implode( '|', wp_list_pluck( $e['eintraege'], 'titel' ) ) );
	it( 'Anzeigen-Häkchen übernommen', array( true, true, false ) === wp_list_pluck( $e['eintraege'], 'anzeigen' ) );
} elseif ( null === $vorher ) {
	delete_option( 'auhof_infos' );
} else {
	update_option( 'auhof_infos', $vorher );
}
WP_CLI::log( 'Ergebnis: ' . $GLOBALS['it_ok'] . ' bestanden, ' . $GLOBALS['it_f'] . ' fehlgeschlagen' );
