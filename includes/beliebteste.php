<?php
/**
 * Beitragsseite „Aktuelles“: Umschalter „Neueste | Beliebteste“.
 *
 * „Neueste“ ist die normale Beitragsseite (Einstellungen → Lesen → Beitragsseite), „Beliebteste“
 * dieselbe Seite unter …/beliebteste/ – gleiche Kacheln und Seitennummern, nur nach Likes sortiert.
 * Die Likes kommen aus dem Plugin „WP ULike“ (wp_ulike_get_popular_items_ids()). Gezählt werden die
 * Likes im laufenden Schuljahr (ab 1. September); hat das Schuljahr noch weniger als drei Beiträge mit
 * Likes (z. B. im September), zählt zusätzlich das vorige Schuljahr. Beiträge ohne Like erscheinen
 * unter „Beliebteste“ nicht.
 *
 * Ohne WP ULike gibt es keinen Umschalter, …/beliebteste/ leitet dann auf die Beitragsseite um.
 * Die Ansicht „Beliebteste“ wird nicht zwischengespeichert (DONOTCACHEPAGE) und nicht indexiert.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/** Abfragevariable der Ansicht „Beliebteste“. */
const AUHOF_BELIEBT_VAR = 'auhof_beliebt';

/** Adresszusatz nach der Beitragsseite. */
const AUHOF_BELIEBT_PFAD = 'beliebteste';

/** Mindestzahl Beiträge mit Likes im laufenden Schuljahr, sonst zählt das vorige Schuljahr mit. */
const AUHOF_BELIEBT_MINDESTENS = 3;

/** Höchstens so viele Beiträge erscheinen unter „Beliebteste“. */
const AUHOF_BELIEBT_HOECHSTENS = 100;

/**
 * ID der Beitragsseite, wenn die Website eine statische Startseite und eine eigene Beitragsseite hat.
 *
 * @return int 0, wenn es keine eigene Beitragsseite gibt.
 */
function auhof_beliebt_beitragsseite() {
	$seite = (int) get_option( 'page_for_posts' );
	if ( ! $seite || 'page' !== get_option( 'show_on_front' ) || 'publish' !== get_post_status( $seite ) ) {
		return 0;
	}
	return $seite;
}

/**
 * Ist WP ULike mit der benötigten Funktion vorhanden?
 *
 * @return bool
 */
function auhof_beliebt_verfuegbar() {
	return function_exists( 'wp_ulike_get_popular_items_ids' );
}

/**
 * Abfragevariable bekannt machen.
 *
 * @param string[] $vars Öffentliche Abfragevariablen.
 * @return string[]
 */
function auhof_beliebt_query_vars( $vars ) {
	$vars[] = AUHOF_BELIEBT_VAR;
	return $vars;
}
add_filter( 'query_vars', 'auhof_beliebt_query_vars' );

/**
 * Adressen …/beliebteste/ und …/beliebteste/page/2/ unter der Beitragsseite.
 * Die Regeln werden neu geschrieben, wenn sich Beitragsseite oder Adresse ändern.
 */
function auhof_beliebt_regeln() {
	$seite = auhof_beliebt_beitragsseite();
	$pfad  = $seite ? get_page_uri( $seite ) : '';
	if ( $pfad ) {
		add_rewrite_rule(
			'^' . preg_quote( $pfad, '#' ) . '/' . AUHOF_BELIEBT_PFAD . '(?:/page/([0-9]+))?/?$',
			'index.php?page_id=' . $seite . '&' . AUHOF_BELIEBT_VAR . '=1&paged=$matches[1]',
			'top'
		);
	}
	$kennung = md5( $seite . '|' . $pfad . '|' . AUHOF_BELIEBT_PFAD );
	if ( get_option( 'auhof_beliebt_regeln' ) !== $kennung ) {
		flush_rewrite_rules( false );
		update_option( 'auhof_beliebt_regeln', $kennung, false );
	}
}
add_action( 'init', 'auhof_beliebt_regeln', 20 );

/**
 * Ist die aktuelle Anfrage die Ansicht „Beliebteste“ der Beitragsseite?
 *
 * @param WP_Query|null $q Abfrage (Standard: Hauptabfrage).
 * @return bool
 */
function auhof_beliebt_ist_ansicht( $q = null ) {
	if ( null === $q ) {
		global $wp_query;
		$q = $wp_query;
	}
	return $q instanceof WP_Query && $q->is_home() && $q->is_posts_page && '1' === (string) $q->get( AUHOF_BELIEBT_VAR ) && auhof_beliebt_beitragsseite() > 0;
}

/**
 * Beginn eines Schuljahres (1. September) als JJJJ-MM-TT.
 *
 * @param int $zurueck 0 = laufendes Schuljahr, 1 = voriges.
 * @return string
 */
function auhof_beliebt_schuljahr_beginn( $zurueck = 0 ) {
	$jahr = (int) current_time( 'Y' );
	if ( (int) current_time( 'n' ) < 9 ) {
		--$jahr;
	}
	return sprintf( '%04d-09-01', $jahr - (int) $zurueck );
}

/**
 * Beitrags-IDs nach Likes (meiste zuerst) und der Zeitraum, der gezählt wurde – einmal je Aufruf der Seite.
 *
 * @return array{ids:int[],seit:string}|null Null ohne WP ULike.
 */
function auhof_beliebt_daten() {
	static $daten = null;
	if ( null === $daten ) {
		$daten = auhof_beliebt_ermitteln();
	}
	return $daten;
}

/**
 * Beitrags-IDs nach Likes ermitteln: laufendes Schuljahr, bei zu wenigen Beiträgen ab dem vorigen.
 *
 * @return array{ids:int[],seit:string}|null Null ohne WP ULike.
 */
function auhof_beliebt_ermitteln() {
	if ( ! auhof_beliebt_verfuegbar() ) {
		return null;
	}
	$bis  = current_time( 'Y-m-d' );
	$seit = auhof_beliebt_schuljahr_beginn();
	$ids  = auhof_beliebt_abfrage( $seit, $bis );
	if ( count( $ids ) < AUHOF_BELIEBT_MINDESTENS ) {
		$seit = auhof_beliebt_schuljahr_beginn( 1 );
		$ids  = auhof_beliebt_abfrage( $seit, $bis );
	}
	return array(
		'ids'  => $ids,
		'seit' => $seit,
	);
}

/**
 * Beiträge mit Likes im Zeitraum, meiste zuerst.
 *
 * @param string $seit JJJJ-MM-TT.
 * @param string $bis  JJJJ-MM-TT.
 * @return int[]
 */
function auhof_beliebt_abfrage( $seit, $bis ) {
	$ids = wp_ulike_get_popular_items_ids(
		array(
			'type'     => 'post',
			'rel_type' => 'post',
			'status'   => 'like',
			'period'   => array(
				'start' => $seit,
				'end'   => $bis,
			),
			'limit'    => AUHOF_BELIEBT_HOECHSTENS,
		)
	);
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Hauptabfrage der Ansicht „Beliebteste“: nur Beiträge mit Likes, nach Likes sortiert.
 *
 * @param WP_Query $q Abfrage.
 */
function auhof_beliebt_abfrage_anpassen( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! auhof_beliebt_ist_ansicht( $q ) ) {
		return;
	}
	$daten = auhof_beliebt_daten();
	if ( null === $daten ) {
		return; // Umleitung folgt in auhof_beliebt_umleiten().
	}
	$q->set( 'post__in', $daten['ids'] ? $daten['ids'] : array( 0 ) );
	$q->set( 'orderby', 'post__in' );
	$q->set( 'ignore_sticky_posts', true );
}
add_action( 'pre_get_posts', 'auhof_beliebt_abfrage_anpassen' );

/**
 * Ohne WP ULike: …/beliebteste/ auf die Beitragsseite umleiten. Sonst: nicht zwischenspeichern.
 */
function auhof_beliebt_umleiten() {
	if ( ! auhof_beliebt_ist_ansicht() ) {
		return;
	}
	if ( ! auhof_beliebt_verfuegbar() ) {
		wp_safe_redirect( get_permalink( auhof_beliebt_beitragsseite() ), 302 );
		exit;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // Reihenfolge ändert sich mit jedem Like.
	}
}
add_action( 'template_redirect', 'auhof_beliebt_umleiten', 1 );

/**
 * Adresse einer Ansicht.
 *
 * @param bool $beliebt Ansicht „Beliebteste“.
 * @return string
 */
function auhof_beliebt_adresse( $beliebt ) {
	$basis = get_permalink( auhof_beliebt_beitragsseite() );
	if ( ! $beliebt ) {
		return $basis;
	}
	if ( get_option( 'permalink_structure' ) ) {
		return trailingslashit( $basis ) . user_trailingslashit( AUHOF_BELIEBT_PFAD );
	}
	return add_query_arg( AUHOF_BELIEBT_VAR, '1', $basis );
}

/**
 * Umschalter unter der Überschrift der Beitragsseite (Kadence: Archiv-Kopf).
 */
function auhof_beliebt_umschalter() {
	if ( ! is_home() || ! is_main_query() || ! auhof_beliebt_beitragsseite() || ! auhof_beliebt_verfuegbar() ) {
		return;
	}
	global $wp_query;
	if ( ! $wp_query->is_posts_page ) {
		return;
	}
	$beliebt = auhof_beliebt_ist_ansicht();
	$punkte  = array(
		array( __( 'Neueste', 'auhof-schulseiten' ), auhof_beliebt_adresse( false ), ! $beliebt ),
		array( __( 'Beliebteste', 'auhof-schulseiten' ), auhof_beliebt_adresse( true ), $beliebt ),
	);
	echo '<nav class="auhof-beitragsansicht" aria-label="' . esc_attr__( 'Beiträge sortieren', 'auhof-schulseiten' ) . '"><ul>';
	foreach ( $punkte as $p ) {
		printf(
			'<li><a href="%1$s"%2$s>%3$s</a></li>',
			esc_url( $p[1] ),
			$p[2] ? ' aria-current="page"' : '',
			esc_html( $p[0] )
		);
	}
	echo '</ul></nav>';
	if ( $beliebt ) {
		$daten = auhof_beliebt_daten();
		$seit  = wp_date( 'F Y', strtotime( $daten['seit'] . ' 12:00:00' ) );
		if ( $daten['ids'] ) {
			/* translators: %s: Monat und Jahr, z. B. „September 2026“. */
			$text = sprintf( __( 'Beiträge mit den meisten Likes seit %s.', 'auhof-schulseiten' ), $seit );
		} else {
			/* translators: %s: Monat und Jahr, z. B. „September 2026“. */
			$text = sprintf( __( 'Seit %s hat noch kein Beitrag ein Like bekommen.', 'auhof-schulseiten' ), $seit );
		}
		echo '<p class="auhof-beitragsansicht__hinweis">' . esc_html( $text ) . '</p>';
	}
}
add_action( 'kadence_archive_after_entry_header', 'auhof_beliebt_umschalter' );

/**
 * Seitentitel im Browser: „Aktuelles – Beliebteste – …“.
 *
 * @param array<string,string> $teile Titelteile.
 * @return array<string,string>
 */
function auhof_beliebt_titel( $teile ) {
	if ( auhof_beliebt_ist_ansicht() && isset( $teile['title'] ) ) {
		$teile['title'] .= ' – ' . __( 'Beliebteste', 'auhof-schulseiten' );
	}
	return $teile;
}
add_filter( 'document_title_parts', 'auhof_beliebt_titel' );

/**
 * Ansicht „Beliebteste“ nicht in Suchmaschinen (gleiche Beiträge wie die Beitragsseite).
 *
 * @param array<string,bool|string> $robots Robots-Angaben.
 * @return array<string,bool|string>
 */
function auhof_beliebt_robots( $robots ) {
	if ( auhof_beliebt_ist_ansicht() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'auhof_beliebt_robots' );

/**
 * Stil des Umschalters nur auf der Beitragsseite laden.
 */
function auhof_beliebt_assets() {
	if ( is_home() && auhof_beliebt_beitragsseite() && auhof_beliebt_verfuegbar() ) {
		wp_enqueue_style( 'auhof-beliebteste', AUHOF_SCHULSEITEN_URL . 'assets/beliebteste.css', array(), AUHOF_SCHULSEITEN_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'auhof_beliebt_assets' );
