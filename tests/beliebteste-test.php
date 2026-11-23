<?php
/**
 * Test Umschalter „Neueste | Beliebteste“ (Auhof Schulseiten ab 1.9.0, includes/beliebteste.php).
 *   wp eval-file beliebteste-test.php [basis-url]
 * Legt vorübergehend Test-Likes in der Tabelle von WP ULike an (IP 203.0.113.x = Dokumentationsnetz, Benutzer-IDs ab 990001)
 * und entfernt sie am Ende wieder. Seitenabrufe gehen an die eigene Website (Standard: home_url()).
 */
global $wpdb;
$ok = 0; $f = 0;
$pruefe = function ( $bedingung, $text ) use ( &$ok, &$f ) {
	if ( $bedingung ) { $ok++; } else { $f++; WP_CLI::log( '  FEHLER ' . $text ); }
};
$basis = isset( $args[0] ) ? untrailingslashit( $args[0] ) : untrailingslashit( home_url() );

$pruefe( function_exists( 'auhof_beliebt_ermitteln' ), 'Funktionen fehlen' );
$pruefe( auhof_beliebt_verfuegbar(), 'WP ULike nicht aktiv' );
$seite = auhof_beliebt_beitragsseite();
$pruefe( $seite > 0, 'keine Beitragsseite' );
$tab = $wpdb->prefix . 'ulike_pulse';
$pruefe( (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tab ) ), "Tabelle $tab fehlt" );
if ( $f ) { WP_CLI::log( "Ergebnis: $ok bestanden, $f fehlgeschlagen" ); return; }

// Schuljahresbeginn.
$j = (int) current_time( 'Y' ); $sj = (int) current_time( 'n' ) >= 9 ? $j : $j - 1;
$pruefe( auhof_beliebt_schuljahr_beginn() === "$sj-09-01", 'Schuljahresbeginn ' . auhof_beliebt_schuljahr_beginn() );
$pruefe( auhof_beliebt_schuljahr_beginn( 1 ) === ( $sj - 1 ) . '-09-01', 'voriges Schuljahr' );

// Testdaten: vier Beiträge, Likes im laufenden bzw. vorigen Schuljahr.
$posts = get_posts( array( 'post_type' => 'post', 'numberposts' => 4, 'fields' => 'ids' ) );
list( $a, $b, $c, $d ) = $posts;
$aufraeumen = function () use ( $wpdb, $tab ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM `$tab` WHERE ip LIKE %s", '203.0.113.%' ) );
	wp_cache_flush();
};
$aufraeumen();
$vorher = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$tab`" );
$like = function ( $id, $datum, $n ) use ( $wpdb, $tab ) {
	static $z = 1;
	for ( $i = 0; $i < $n; $i++ ) {
		$wpdb->insert( $tab, array( 'item_id' => $id, 'item_type' => 'post', 'engagement_kind' => 'vote', 'engagement_key' => 'like', 'value' => 1, 'status' => 'active', 'date_time' => $datum . ' 10:00:00', 'ip' => '203.0.113.' . ( $z % 250 + 1 ), 'user_id' => (string) ( 990000 + $z ) ) );
		$z++;
	}
};
$heute = current_time( 'Y-m-d' );
$like( $b, $heute, 3 );
$like( $c, $heute, 2 );
$like( $d, ( $sj - 1 ) . '-10-10', 5 ); // voriges Schuljahr
wp_cache_flush();

// Nur zwei Beiträge im laufenden Schuljahr: das vorige Schuljahr zählt mit.
$r = auhof_beliebt_ermitteln();
$pruefe( ( $sj - 1 ) . '-09-01' === $r['seit'], 'Rückgriff aufs vorige Schuljahr fehlt: ' . $r['seit'] );
$pruefe( array_slice( $r['ids'], 0, 3 ) === array( $d, $b, $c ), 'Reihenfolge mit Vorjahr: ' . implode( ',', $r['ids'] ) . " erwartet $d,$b,$c" );

// Drittes Like im laufenden Schuljahr: nur das laufende Schuljahr zählt.
$like( $a, $heute, 1 );
wp_cache_flush();
$r = auhof_beliebt_ermitteln();
$pruefe( "$sj-09-01" === $r['seit'], 'Zeitraum laufendes Schuljahr: ' . $r['seit'] );
$pruefe( array_slice( $r['ids'], 0, 3 ) === array( $b, $c, $a ), 'Reihenfolge: ' . implode( ',', $r['ids'] ) . " erwartet $b,$c,$a" );
$pruefe( ! in_array( $d, $r['ids'], true ), 'Beitrag nur mit Vorjahres-Likes darf nicht erscheinen' );
$erwartet = $r['ids'];

// Seiten abrufen (Like-Daten bleiben bis dahin bestehen).
$hole = function ( $url ) {
	$antwort = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0, 'sslverify' => false ) );
	return is_wp_error( $antwort ) ? array( 0, '', array() ) : array( wp_remote_retrieve_response_code( $antwort ), wp_remote_retrieve_body( $antwort ), wp_remote_retrieve_headers( $antwort ) );
};
$neu  = get_permalink( $seite );
$bel  = auhof_beliebt_adresse( true );
$pruefe( trailingslashit( $neu ) . 'beliebteste/' === $bel, "Adresse $bel" );

list( $code, $html ) = $hole( $neu . '?nc=' . time() );
$pruefe( 200 === $code, "Neueste: HTTP $code" );
$pruefe( false !== strpos( $html, 'class="auhof-beitragsansicht"' ), 'Neueste: Umschalter fehlt' );
$pruefe( (bool) preg_match( '#<a href="' . preg_quote( esc_url( $neu ), '#' ) . '" aria-current="page">Neueste</a>#', $html ), 'Neueste: aria-current fehlt' );
$pruefe( 1 === substr_count( $html, 'aria-current="page"' ) || false === strpos( $html, 'aria-current="page">Beliebteste' ), 'Neueste: Beliebteste fälschlich aktiv' );
$pruefe( false !== strpos( $html, 'assets/beliebteste.css' ), 'Neueste: CSS fehlt' );
if ( '1' === (string) get_option( 'blog_public' ) ) { $pruefe( false === strpos( $html, 'noindex' ), 'Neueste darf nicht noindex sein' ); }
// Umschalter steht im Kopf, vor der Kachelliste.
$pruefe( strpos( $html, 'auhof-beitragsansicht' ) < strpos( $html, 'id="archive-container"' ), 'Umschalter nicht vor den Kacheln' );

list( $code, $html, $kopf ) = $hole( $bel );
$pruefe( 200 === $code, "Beliebteste: HTTP $code" );
$pruefe( false !== strpos( $html, 'aria-current="page">Beliebteste</a>' ), 'Beliebteste: aria-current fehlt' );
$pruefe( false !== strpos( $html, 'noindex' ), 'Beliebteste: noindex fehlt' );
$pruefe( false !== strpos( $html, 'auhof-beitragsansicht__hinweis' ) && false !== strpos( $html, 'meisten Likes seit September ' . $sj ), 'Beliebteste: Hinweis fehlt' );
$pruefe( (bool) preg_match( '#<title>[^<]*Beliebteste#', $html ), 'Beliebteste: Seitentitel' );
preg_match_all( '#<article[^>]*\bpost-(\d+)\b#', $html, $m );
$gezeigt = array_map( 'intval', $m[1] );
$pro_seite = (int) get_option( 'posts_per_page' );
$pruefe( array_slice( $erwartet, 0, $pro_seite ) === $gezeigt, 'Beliebteste: Kacheln ' . implode( ',', $gezeigt ) . ' erwartet ' . implode( ',', array_slice( $erwartet, 0, $pro_seite ) ) );
$pruefe( false === strpos( $html, 'id="post-' . $d . '"' ) && ! in_array( $d, $gezeigt, true ), 'Beliebteste: Vorjahresbeitrag sichtbar' );

// Seitennummern: vorübergehend 2 Beiträge pro Seite, dann /beliebteste/page/2/ = Plätze 3 und 4.
update_option( 'posts_per_page', 2 );
list( $code, $html2 ) = $hole( $bel . 'page/2/' );
update_option( 'posts_per_page', $pro_seite );
preg_match_all( '#<article[^>]*\bpost-(\d+)\b#', $html2, $m2 );
$pruefe( 200 === $code && array_map( 'intval', $m2[1] ) === array_slice( $erwartet, 2, 2 ), "Seite 2: HTTP $code, Kacheln " . implode( ',', $m2[1] ) );
$pruefe( false !== strpos( $html2, 'aria-current="page">Beliebteste</a>' ), 'Seite 2: Umschalter' );
$pruefe( (int) get_option( 'posts_per_page' ) === $pro_seite, 'posts_per_page nicht zurückgesetzt' );

// Fremde Werte der Abfragevariable bewirken nichts.
list( $code, $html ) = $hole( $neu . '?auhof_beliebt=<script>&nc=' . time() );
$pruefe( 200 === $code && false === strpos( $html, '<script>&' ) && false !== strpos( $html, 'aria-current="page">Neueste</a>' ), 'Abfragevariable mit Fremdwert' );

// Ohne Likes: Hinweis, keine Kacheln.
$aufraeumen();
$like( $d, ( $sj - 3 ) . '-10-10', 1 ); // nur alte Likes
wp_cache_flush();
$r = auhof_beliebt_ermitteln();
$pruefe( array() === $r['ids'], 'ohne aktuelle Likes: leere Liste erwartet' );
list( $code, $html ) = $hole( $bel . '?nc=' . time() );
$pruefe( false === strpos( $html, 'id="archive-container"' ) || 0 === preg_match( '#<article[^>]*\bpost-\d+#', $html ), 'ohne Likes: Kacheln sichtbar' );
$pruefe( false !== strpos( $html, 'noch kein Beitrag ein Like' ), 'ohne Likes: Hinweis fehlt' );

$aufraeumen();
$nachher = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$tab`" );
$pruefe( $vorher === $nachher, "Test-Likes nicht vollständig entfernt ($vorher/$nachher)" );
WP_CLI::log( "Ergebnis: $ok bestanden, $f fehlgeschlagen" );
