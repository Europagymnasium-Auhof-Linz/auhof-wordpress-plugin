<?php
// Sicherheitstests für Auhof Schulseiten (wp eval-file). Legt keine Benutzer an; Testdaten werden zurückgesetzt.
$GLOBALS["ok"] = 0; $GLOBALS["fehler"] = 0;
require_once WP_PLUGIN_DIR . "/auhof-schulseiten/includes/personen-admin.php";
require_once WP_PLUGIN_DIR . "/auhof-schulseiten/includes/raumplan-admin.php";
// Echte HTML-Analyse: gefährlich sind nur Elemente/Attribute, nicht maskierter Text.
function html_gefaehrlich( $html ) {
	$d = new DOMDocument(); libxml_use_internal_errors( true );
	$d->loadHTML( "<?xml encoding=\"UTF-8\"><div>" . $html . "</div>" ); libxml_clear_errors();
	foreach ( $d->getElementsByTagName( "*" ) as $e ) {
		if ( in_array( strtolower( $e->nodeName ), array( "script", "svg", "iframe", "object" ), true ) ) { return $e->nodeName; }
		foreach ( $e->attributes as $a ) {
			if ( 0 === stripos( $a->name, "on" ) ) { return "Attribut " . $a->name; }
			if ( in_array( $a->name, array( "href", "src" ), true ) && preg_match( "/^\s*javascript:/i", $a->value ) ) { return "javascript-URL"; }
		}
		if ( "img" === strtolower( $e->nodeName ) && "x" === $e->getAttribute( "src" ) ) { return "eingeschleustes img"; }
	}
	return "";
}
function pruef( $name, $bedingung ) { global $ok, $fehler; if ( $bedingung ) { $ok++; WP_CLI::log( "  OK    $name" ); } else { $fehler++; WP_CLI::log( "  FEHLER $name" ); } }

$payloads = array(
	'<script>alert(1)</script>',
	'"><img src=x onerror=alert(1)>',
	"' onmouseover='alert(1)",
	'javascript:alert(1)',
	'</li></ul><svg onload=alert(1)>',
);

// 1) Gespeicherter Schadcode in der Datenbank (schlimmster Fall: direkt geschrieben, ohne Bereinigung) wird maskiert ausgegeben.
WP_CLI::log( '1) Ausgabe maskiert (gespeichertes XSS)' );
$p  = get_posts( array( 'post_type' => 'auhof_person', 'numberposts' => 1, 'tax_query' => array( array( 'taxonomy' => 'auhof_gruppe', 'field' => 'slug', 'terms' => 'sekretariat' ) ) ) )[0];
$alt = array();
foreach ( array_keys( auhof_personen_felder() ) as $f ) { $alt[ $f ] = get_post_meta( $p->ID, '_auhof_' . $f, true ); }
foreach ( $payloads as $x ) {
	foreach ( array_keys( auhof_personen_felder() ) as $f ) { update_post_meta( $p->ID, '_auhof_' . $f, $x ); }
	$html = do_shortcode( '[auhof_personen gruppe="sekretariat"]' );
	$g = html_gefaehrlich( $html ); pruef( "Payload " . substr( $x, 0, 24 ) . ( $g ? " → $g" : "" ), "" === $g );
	$g = html_gefaehrlich( do_shortcode( '[auhof_personen gruppe="sekretariat" ansicht="lehrkraft"]' ) ); pruef( "Lehrkraft-Ansicht " . substr( $x, 0, 16 ) . ( $g ? " → $g" : "" ), "" === $g );
}
foreach ( $alt as $f => $w ) { update_post_meta( $p->ID, '_auhof_' . $f, $w ); }

// 2) Shortcode-Attribute aus dem Seiteninhalt.
WP_CLI::log( '2) Shortcode-Attribute' );
$html = do_shortcode( '[auhof_personen gruppe="<script>alert(1)</script>" az="ja"]' );
pruef( 'Gruppe mit Schadcode liefert nichts', '' === $html );
$html = do_shortcode( '[auhof_personen gruppe="lehrkraefte\' OR 1=1 --"]' );
pruef( 'SQL-artiger Gruppenname liefert nichts', '' === $html );

// 3) Eingaben aus der Maske werden bereinigt.
WP_CLI::log( '3) Bereinigung der Eingaben' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $admin ); // nur im Speicher dieses Aufrufs, keine Anmeldung
$_POST = array(
	'auhof_person_nonce' => wp_create_nonce( 'auhof_person_speichern' ),
	'auhof'              => array(
		'vorname'  => '<b>Max</b><script>x</script>',
		'nachname' => str_repeat( 'A', 500 ),
		'email'    => 'evil@example.com"><script>',
		'telefon'  => '0732<script>123',
		'zusatz'   => array( 'kein', 'text' ),
		'funktion' => 'Leiterin<script>x</script>',
	),
);
$w = auhof_personen_eingaben();
pruef( 'HTML entfernt', false === strpos( $w['vorname'], '<' ) );
pruef( 'Länge begrenzt (200)', 200 === mb_strlen( $w['nachname'] ) );
pruef( 'E-Mail bereinigt', false === strpos( $w['email'], '<' ) && false === strpos( $w['email'], '"' ) );
pruef( 'Telefon nur Ziffern/Zeichen', 1 === preg_match( '#^[0-9+\-/() ]*$#', $w['telefon'] ) );
pruef( 'Array statt Text wird verworfen', '' === $w['zusatz'] );
pruef( 'Funktion ohne HTML', false === strpos( $w['funktion'], '<' ) && 0 === strpos( $w['funktion'], 'Leiterin' ) );

// 4) CSRF: ohne oder mit falscher Nonce wird nichts gespeichert.
WP_CLI::log( '4) CSRF-Schutz' );
pruef( 'Gültige Nonce + Admin darf speichern', auhof_personen_darf_speichern( $p->ID ) );
$_POST['auhof_person_nonce'] = 'falsch';
pruef( 'Falsche Nonce abgewiesen', ! auhof_personen_darf_speichern( $p->ID ) );
unset( $_POST['auhof_person_nonce'] );
pruef( 'Fehlende Nonce abgewiesen', ! auhof_personen_darf_speichern( $p->ID ) );
$_POST = array();

// 5) Berechtigungen je Rolle (Benutzer nur im Speicher, nicht angelegt).
WP_CLI::log( '5) Berechtigungen' );
function test_benutzer( $rolle ) {
	$u        = new WP_User();
	$u->ID    = 999999;
	$u->caps  = array( $rolle => true );
	$u->roles = array( $rolle );
	$u->get_role_caps();
	return $u;
}
foreach ( array( 'subscriber' => false, 'contributor' => false, 'author' => false, 'editor' => true, 'administrator' => true ) as $rolle => $darf ) {
	$u = test_benutzer( $rolle );
	pruef( "$rolle: Person bearbeiten " . ( $darf ? 'ja' : 'nein' ), $darf === $u->has_cap( 'edit_post', $p->ID ) );
	pruef( "$rolle: Personen anlegen " . ( $darf ? 'ja' : 'nein' ), $darf === $u->has_cap( get_post_type_object( 'auhof_person' )->cap->create_posts ) );
	pruef( "$rolle: Gruppen verwalten " . ( $darf ? 'ja' : 'nein' ), $darf === $u->has_cap( get_taxonomy( 'auhof_gruppe' )->cap->manage_terms ) );
	pruef( "$rolle: Raumplan " . ( $darf ? 'ja' : 'nein' ), $darf === $u->has_cap( apply_filters( 'option_page_capability_auhof_raumplan', 'manage_options' ) ) );
}

// 6) Raumplan-Einstellungen bereinigen.
WP_CLI::log( '6) Raumplan-Eingaben' );
$r = auhof_raumplan_bereinigen(
	array(
		'jahr'  => '<script>alert(1)</script>Schuljahr 2027/28',
		'namen' => array( 0 => '<img src=x onerror=alert(1)>', 1 => str_repeat( 'x', 100 ), 99999 => 'unbekannt', 'abc' => 'x', 2 => array( 'a' ), 3 => 'Spielearchiv' ),
	)
);
pruef( 'Schuljahr ohne HTML', false === strpos( $r['jahr'], '<' ) );
pruef( 'Raumname ohne HTML', ! isset( $r['namen'][0] ) || false === strpos( $r['namen'][0], '<' ) );
pruef( 'Raumname max. 40 Zeichen', 40 === mb_strlen( $r['namen'][1] ) );
pruef( 'Unbekannte Raum-IDs verworfen', ! isset( $r['namen'][99999] ) && ! isset( $r['namen']['abc'] ) );
pruef( 'Array statt Text verworfen', ! isset( $r['namen'][2] ) );
pruef( 'Unveränderter Name nicht gespeichert', ! isset( $r['namen'][3] ) || 'Spielearchiv' !== get_option( 'auhof_raumplan' ) );
pruef( 'Unsinnige Eingabe (kein Array)', array() === auhof_raumplan_bereinigen( 'x' )['namen'] );

// 7) JSON für den Raumplan kann das Skript nicht verlassen.
WP_CLI::log( '7) Raumplan-Daten im Skript' );
$json = wp_json_encode( array( 'n' => '</script><script>alert(1)</script>' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
pruef( 'Keine </script>-Sequenz im JSON', false === stripos( $json, '</script' ) );

// 8) Nicht öffentlich: Beitragstyp und Taxonomie.
WP_CLI::log( '8) Sichtbarkeit' );
$pt = get_post_type_object( 'auhof_person' );
$tx = get_taxonomy( 'auhof_gruppe' );
pruef( 'Personen nicht öffentlich/abfragbar/REST', ! $pt->public && ! $pt->publicly_queryable && ! $pt->show_in_rest && false === $pt->rewrite );
pruef( 'Gruppen nicht öffentlich/abfragbar/REST', ! $tx->public && ! $tx->publicly_queryable && ! $tx->show_in_rest && false === $tx->rewrite );
pruef( 'Nicht in der Sitemap', ! array_key_exists( 'auhof_person', apply_filters( 'wp_sitemaps_post_types', get_post_types( array( 'public' => true ), 'objects' ) ) ) );

// 9) Reihenfolge je Funktion (Formular „Funktion bearbeiten“).
WP_CLI::log( '9) Reihenfolge je Funktion' );
require_once WP_PLUGIN_DIR . '/auhof-schulseiten/includes/organigramm-admin.php';
$mp   = get_posts( array( 'post_type' => 'auhof_person', 'numberposts' => 1, 'tax_query' => array( array( 'taxonomy' => 'auhof_funktion', 'operator' => 'EXISTS' ) ) ) )[0]->ID;
$tid  = (int) wp_get_object_terms( $mp, 'auhof_funktion', array( 'fields' => 'ids' ) )[0];
$ohne = get_posts( array( 'post_type' => 'auhof_person', 'numberposts' => 1, 'tax_query' => array( array( 'taxonomy' => 'auhof_funktion', 'terms' => $tid, 'operator' => 'NOT IN' ) ) ) )[0]->ID;
$alt_mp = auhof_funktion_reihenfolge( $mp ); $alt_ohne = auhof_funktion_reihenfolge( $ohne );
wp_set_current_user( $admin );
$_POST = array( '_wpnonce' => wp_create_nonce( 'update-tag_' . $tid ), 'auhof_personen_reihe' => array( $mp => '7<script>', $ohne => '3', 100 => '4', 'x' => '5' ) );
auhof_organigramm_felder_speichern( $tid );
pruef( 'Zahl gespeichert und bereinigt (7)', 7 === ( auhof_funktion_reihenfolge( $mp )[ $tid ] ?? 0 ) );
pruef( 'Person ohne diese Funktion unverändert', $alt_ohne === auhof_funktion_reihenfolge( $ohne ) );
pruef( 'Fremder Beitrag (Startseite) unverändert', '' === get_post_meta( 100, '_auhof_funktion_reihenfolge', true ) );
$_POST['_wpnonce'] = 'falsch'; $_POST['auhof_personen_reihe'] = array( $mp => '9' );
auhof_organigramm_felder_speichern( $tid );
pruef( 'Falsche Nonce: nichts gespeichert', 7 === ( auhof_funktion_reihenfolge( $mp )[ $tid ] ?? 0 ) );
wp_set_current_user( 0 );
$_POST = array( '_wpnonce' => wp_create_nonce( 'update-tag_' . $tid ), 'auhof_personen_reihe' => array( $mp => '9' ) );
auhof_organigramm_felder_speichern( $tid );
pruef( 'Ohne Recht: nichts gespeichert', 7 === ( auhof_funktion_reihenfolge( $mp )[ $tid ] ?? 0 ) );
pruef( 'Bereinigung: nur positive Zahlen ≤ 999', array( 5 => 999 ) === auhof_funktion_reihenfolge_bereinigen( array( 5 => 5000, 6 => -3, 0 => 4, 7 => array( 1 ), 8 => 'abc' ) ) );
$_POST = array();
auhof_funktion_reihenfolge_speichern( $mp, $alt_mp );

WP_CLI::log( "Ergebnis: " . $GLOBALS["ok"] . " bestanden, " . $GLOBALS["fehler"] . " fehlgeschlagen" );
