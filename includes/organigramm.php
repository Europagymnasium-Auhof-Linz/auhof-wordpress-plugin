<?php
/**
 * Organigramm: Funktionen (Taxonomie „auhof_funktion“) und Klassenvorstand werden bei den Personen gepflegt,
 * der Shortcode [auhof_organigramm] erzeugt daraus das Organigramm.
 *
 * Aufbau über die oberste Ebene der Funktionen (Slug bestimmt die Darstellung):
 *   leitung    – erste Funktion als Kopf (Schulleitung), die übrigen als Kästen darunter
 *   zweige     – je Zweig: zugeordnete Personen = Koordination; Klassen nach Buchstaben (Feld „Klassen“, z. B. „A-E“)
 *   fach-args  – Liste „Fach → Personen“
 *   (andere)   – Kästen; Unterfunktionen erscheinen als Rollen im Kasten
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'auhof_organigramm_registrieren', 11 );

/**
 * Taxonomie „Funktionen“ für Personen.
 */
function auhof_organigramm_registrieren() {
	register_taxonomy(
		'auhof_funktion',
		'auhof_person',
		array(
			'labels'             => array(
				'name'          => __( 'Funktionen', 'auhof-schulseiten' ),
				'singular_name' => __( 'Funktion', 'auhof-schulseiten' ),
				'menu_name'     => __( 'Funktionen', 'auhof-schulseiten' ),
				'all_items'     => __( 'Alle Funktionen', 'auhof-schulseiten' ),
				'edit_item'     => __( 'Funktion bearbeiten', 'auhof-schulseiten' ),
				'add_new_item'  => __( 'Neue Funktion', 'auhof-schulseiten' ),
				'parent_item'   => __( 'Übergeordnet', 'auhof-schulseiten' ),
				'search_items'  => __( 'Funktionen suchen', 'auhof-schulseiten' ),
				'not_found'     => __( 'Keine Funktionen gefunden.', 'auhof-schulseiten' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => true,
			'hierarchical'       => true,
			'rewrite'            => false,
			'query_var'          => false,
			'capabilities'       => array(
				'manage_terms' => auhof_schulseiten_recht(),
				'edit_terms'   => auhof_schulseiten_recht(),
				'delete_terms' => auhof_schulseiten_recht(),
				'assign_terms' => auhof_schulseiten_recht(),
			),
		)
	);
}

/**
 * Grundstruktur (wird angelegt, solange es noch keine Funktionen gibt). Werte: Name => [Reihenfolge, Klassen, Unterfunktionen].
 *
 * @return array<string,array<string,mixed>>
 */
function auhof_organigramm_grundstruktur() {
	$args = array( 'IBOBB Oberstufe', 'IBOBB Unterstufe', 'Bewegung und Sport', 'Kunst und Gestaltung', 'Biologie und Umweltbildung', 'Chemie', 'Deutsch', 'Digitale Grundbildung', 'Drama', 'Englisch', 'Englisch A', 'Ethik', 'Französisch', 'Geographie und wirtschaftliche Bildung', 'Geometrie und Darstellende Geometrie', 'Geschichte und Politische Bildung', 'Informatik', 'Italienisch', 'Latein', 'Mathematik', 'Musik', 'Physik', 'Psychologie und Philosophie', 'Religion', 'Spanisch', 'Technik und Design' );
	$fach = array();
	foreach ( $args as $i => $name ) {
		$fach[ $name ] = array( 'reihenfolge' => $i + 1 );
	}
	return array(
		'leitung'   => array(
			'name'   => 'Schulleitung und Verwaltung',
			'kinder' => array(
				'Schulleitung'   => array( 'reihenfolge' => 1 ),
				'Sekretariat'    => array( 'reihenfolge' => 2 ),
				'Administration' => array( 'reihenfolge' => 3 ),
				'QMS'            => array( 'reihenfolge' => 4 ),
			),
		),
		'zweige'    => array(
			'name'   => 'Zweige & Klassen',
			'kinder' => array(
				'Kepler' => array(
					'reihenfolge' => 1,
					'klassen'     => 'A-E',
					'slug'        => 'kepler',
				),
				'eLLA'   => array(
					'reihenfolge' => 2,
					'klassen'     => 'F-G',
					'slug'        => 'ella',
				),
				'LISA'   => array(
					'reihenfolge' => 3,
					'klassen'     => 'L-N',
					'slug'        => 'lisa',
				),
				'TABE'   => array(
					'reihenfolge' => 4,
					'slug'        => 'tabe',
				),
			),
		),
		'fach-args' => array(
			'name'   => 'Fach-Arbeitsgemeinschaften',
			'kinder' => $fach,
		),
		'weitere'   => array(
			'name'   => 'Weitere Funktionen',
			'kinder' => array(
				'Personalvertretung'                  => array( 'reihenfolge' => 1 ),
				'Schüler:innen- und Bildungsberatung' => array( 'reihenfolge' => 2 ),
				'Erasmus+ Koordination'               => array( 'reihenfolge' => 3 ),
				'Vertrauenslehrer:innen'              => array(
					'reihenfolge' => 4,
					'kinder'      => array(
						'AIDS-Berater:innen'  => array( 'reihenfolge' => 1 ),
						'Schüler:innen-PEERS' => array( 'reihenfolge' => 2 ),
						'Suchtprävention'     => array( 'reihenfolge' => 3 ),
					),
				),
				'IT-Systembetreuung'                  => array( 'reihenfolge' => 5 ),
			),
		),
	);
}

/**
 * Legt die Grundstruktur an, falls noch keine Funktionen existieren.
 */
function auhof_organigramm_grundstruktur_anlegen() {
	auhof_organigramm_registrieren();
	if ( get_terms(
		array(
			'taxonomy'   => 'auhof_funktion',
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
		)
	) ) {
		return;
	}
	$i = 0;
	foreach ( auhof_organigramm_grundstruktur() as $slug => $bereich ) {
		$oben = wp_insert_term( $bereich['name'], 'auhof_funktion', array( 'slug' => $slug ) );
		if ( is_wp_error( $oben ) ) {
			continue;
		}
		update_term_meta( $oben['term_id'], 'auhof_reihenfolge', ++$i );
		auhof_organigramm_kinder_anlegen( (int) $oben['term_id'], $bereich['kinder'] );
	}
}

/**
 * Unterfunktionen anlegen (rekursiv).
 *
 * @param int                               $eltern Eltern-Term.
 * @param array<string,array<string,mixed>> $kinder Kinder.
 */
function auhof_organigramm_kinder_anlegen( $eltern, $kinder ) {
	foreach ( $kinder as $name => $daten ) {
		$args = array( 'parent' => $eltern );
		if ( ! empty( $daten['slug'] ) ) {
			$args['slug'] = $daten['slug'];
		}
		$t = wp_insert_term( $name, 'auhof_funktion', $args );
		if ( is_wp_error( $t ) ) {
			continue;
		}
		update_term_meta( $t['term_id'], 'auhof_reihenfolge', (int) $daten['reihenfolge'] );
		if ( ! empty( $daten['klassen'] ) ) {
			update_term_meta( $t['term_id'], 'auhof_klassen', $daten['klassen'] );
		}
		if ( ! empty( $daten['kinder'] ) ) {
			auhof_organigramm_kinder_anlegen( (int) $t['term_id'], $daten['kinder'] );
		}
	}
}

add_action(
	'init',
	function () {
		if ( get_option( 'auhof_organigramm_struktur' ) !== AUHOF_SCHULSEITEN_VERSION ) {
			update_option( 'auhof_organigramm_struktur', AUHOF_SCHULSEITEN_VERSION, false );
			auhof_organigramm_grundstruktur_anlegen();
		}
	},
	12
);

/**
 * Buchstaben einer Klassen-Angabe wie „A-E“ oder „F, G“ → [ 'A', 'B', … ].
 *
 * @param string $angabe Angabe.
 * @return string[]
 */
function auhof_organigramm_buchstaben( $angabe ) {
	$buchstaben = array();
	foreach ( preg_split( '/[,\s]+/', strtoupper( (string) $angabe ) ) as $teil ) {
		if ( preg_match( '/^([A-Z])-([A-Z])$/', $teil, $m ) ) {
			$buchstaben = array_merge( $buchstaben, range( $m[1], $m[2] ) );
		} elseif ( preg_match( '/^[A-Z]$/', $teil ) ) {
			$buchstaben[] = $teil;
		}
	}
	return array_unique( $buchstaben );
}

/**
 * Funktionen als Baum, sortiert nach Reihenfolge und Name.
 *
 * @param int $eltern Eltern-ID.
 * @return WP_Term[]
 */
function auhof_organigramm_kinder( $eltern ) {
	static $alle = null;
	if ( null === $alle ) {
		$alle = get_terms(
			array(
				'taxonomy'   => 'auhof_funktion',
				'hide_empty' => false,
			)
		);
		$alle = is_wp_error( $alle ) ? array() : $alle;
	}
	$kinder = array_values(
		array_filter(
			$alle,
			function ( $t ) use ( $eltern ) {
				return (int) $t->parent === (int) $eltern;
			}
		)
	);
	usort(
		$kinder,
		function ( $a, $b ) {
			$ra = (int) get_term_meta( $a->term_id, 'auhof_reihenfolge', true );
			$rb = (int) get_term_meta( $b->term_id, 'auhof_reihenfolge', true );
			return $ra !== $rb ? $ra - $rb : strcmp( auhof_personen_sortierschluessel( $a->name ), auhof_personen_sortierschluessel( $b->name ) );
		}
	);
	return $kinder;
}

/**
 * Reihenfolge einer Person innerhalb ihrer Funktionen (Organigramm).
 * Meta `_auhof_funktion_reihenfolge` = { Funktions-ID: Zahl 1–999 }. Ohne Zahl steht die Person nach den
 * nummerierten Personen, alphabetisch.
 *
 * @param int $post_id Person.
 * @return array<int,int>
 */
function auhof_funktion_reihenfolge( $post_id ) {
	$roh = get_post_meta( $post_id, '_auhof_funktion_reihenfolge', true );
	return auhof_funktion_reihenfolge_bereinigen( is_array( $roh ) ? $roh : array() );
}

/**
 * Nur positive ganze Zahlen (max. 999) je Funktions-ID behalten.
 *
 * @param mixed $werte Rohwerte.
 * @return array<int,int>
 */
function auhof_funktion_reihenfolge_bereinigen( $werte ) {
	$sauber = array();
	foreach ( (array) $werte as $term_id => $zahl ) {
		$term_id = absint( $term_id );
		$zahl    = is_numeric( $zahl ) ? min( 999, max( 0, (int) $zahl ) ) : 0;
		if ( $term_id && $zahl ) {
			$sauber[ $term_id ] = $zahl;
		}
	}
	ksort( $sauber );
	return $sauber;
}

/**
 * Reihenfolge speichern (leer = Meta entfernen).
 *
 * @param int   $post_id Person.
 * @param array $werte   { Funktions-ID: Zahl }.
 */
function auhof_funktion_reihenfolge_speichern( $post_id, $werte ) {
	$sauber = auhof_funktion_reihenfolge_bereinigen( $werte );
	if ( $sauber ) {
		update_post_meta( $post_id, '_auhof_funktion_reihenfolge', $sauber );
	} else {
		delete_post_meta( $post_id, '_auhof_funktion_reihenfolge' );
	}
}

add_action( 'admin_init', 'auhof_organigramm_reihenfolge_uebernehmen', 21 ); // wie Datenstand 2: nur Backend, nur berechtigte Personen

/**
 * Einmalige Datenaktualisierung (Datenstand 3, Plugin 1.4.0): Bis 1.3.1 ordnete das Organigramm nach dem Feld
 * „Reihenfolge“ der Person (gilt für alle Funktionen), dann alphabetisch. Diese Reihenfolge wird je Funktion als
 * 1, 2, 3 … festgeschrieben, damit das Organigramm nach dem Update gleich aussieht.
 */
function auhof_organigramm_reihenfolge_uebernehmen() {
	$stand = (int) get_option( 'auhof_personen_datenstand', 1 );
	if ( ! current_user_can( auhof_schulseiten_recht() ) || $stand >= 3 || $stand < 2 ) {
		return;
	}
	update_option( 'auhof_personen_datenstand', 3, false );
	$mitglieder = array();
	foreach ( get_posts(
		array(
			'post_type'   => 'auhof_person',
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => -1,
		)
	) as $post ) {
		$schluessel = sprintf( '%05d', (int) $post->menu_order ) . auhof_personen_sortierschluessel( get_post_meta( $post->ID, '_auhof_nachname', true ) . ' ' . get_post_meta( $post->ID, '_auhof_vorname', true ) ) . '#' . $post->ID;
		$terme      = wp_get_object_terms( $post->ID, 'auhof_funktion', array( 'fields' => 'ids' ) );
		foreach ( is_array( $terme ) ? $terme : array() as $term_id ) {
			$mitglieder[ (int) $term_id ][ $post->ID ] = $schluessel;
		}
	}
	$reihen = array();
	foreach ( $mitglieder as $term_id => $liste ) {
		asort( $liste, SORT_STRING );
		$n = 0;
		foreach ( array_keys( $liste ) as $post_id ) {
			$reihen[ $post_id ][ $term_id ] = ++$n;
		}
	}
	foreach ( $reihen as $post_id => $reihe ) {
		if ( ! get_post_meta( $post_id, '_auhof_funktion_reihenfolge', true ) ) {
			auhof_funktion_reihenfolge_speichern( $post_id, $reihe );
		}
	}
}

/**
 * Alle veröffentlichten Personen mit Funktionen und Klasse.
 *
 * @return array{nach_funktion:array<int,array<int,array<string,mixed>>>,klassen:array<string,array<int,array<string,mixed>>>}
 */
function auhof_organigramm_daten() {
	$abfrage       = new WP_Query(
		array(
			'post_type'      => 'auhof_person',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$nach_funktion = array();
	$klassen       = array();
	foreach ( $abfrage->posts as $post ) {
		$schluessel = auhof_personen_sortierschluessel( get_post_meta( $post->ID, '_auhof_nachname', true ) . ' ' . get_post_meta( $post->ID, '_auhof_vorname', true ) );
		$p          = array(
			'name'       => trim( get_post_meta( $post->ID, '_auhof_vorname', true ) . ' ' . get_post_meta( $post->ID, '_auhof_nachname', true ) ),
			'sortierung' => '1' . $schluessel,
		);
		$reihe      = auhof_funktion_reihenfolge( $post->ID );
		$terme      = get_the_terms( $post, 'auhof_funktion' );
		foreach ( is_array( $terme ) ? $terme : array() as $t ) {
			// Nummerierte Personen zuerst (nach Zahl), danach alle ohne Zahl alphabetisch.
			$eintrag = $p;
			if ( isset( $reihe[ $t->term_id ] ) ) {
				$eintrag['sortierung'] = sprintf( '0%03d', $reihe[ $t->term_id ] ) . $schluessel;
			}
			$nach_funktion[ $t->term_id ][] = $eintrag;
		}
		foreach ( preg_split( '/[,;\s]+/', strtoupper( (string) get_post_meta( $post->ID, '_auhof_klasse', true ) ) ) as $klasse ) {
			if ( preg_match( '/^\d[A-Z]{1,3}$/', $klasse ) ) {
				$klassen[ $klasse ][] = $p;
			}
		}
	}
	$sortieren = function ( &$liste ) {
		usort(
			$liste,
			function ( $a, $b ) {
				return strcmp( $a['sortierung'], $b['sortierung'] );
			}
		);
	};
	foreach ( $nach_funktion as &$l ) {
		$sortieren( $l );
	}
	unset( $l );
	foreach ( $klassen as &$l ) {
		$sortieren( $l );
	}
	unset( $l );
	return array(
		'nach_funktion' => $nach_funktion,
		'klassen'       => $klassen,
	);
}

/**
 * Namen als kommagetrennter Text.
 *
 * @param array<int,array<string,mixed>> $personen Personen.
 * @return string
 */
function auhof_organigramm_namen( $personen ) {
	return esc_html( implode( ', ', wp_list_pluck( $personen, 'name' ) ) );
}

/**
 * Schuljahr aus dem Datum: ab September das neue Schuljahr.
 *
 * @return string z. B. „2026/27“.
 */
function auhof_organigramm_schuljahr() {
	$jahr = (int) current_time( 'Y' );
	if ( (int) current_time( 'n' ) < 9 ) {
		--$jahr;
	}
	return $jahr . '/' . substr( (string) ( $jahr + 1 ), -2 );
}

add_shortcode( 'auhof_organigramm', 'auhof_organigramm_shortcode' );

/**
 * Shortcode [auhof_organigramm schuljahr="2026/27"] – ohne Angabe wird das laufende Schuljahr gezeigt.
 *
 * @param array<string,string>|string $atts Attribute.
 * @return string
 */
function auhof_organigramm_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'schuljahr' => '' ), $atts, 'auhof_organigramm' );
	$jahr  = '' !== trim( $atts['schuljahr'] ) ? sanitize_text_field( $atts['schuljahr'] ) : auhof_organigramm_schuljahr();
	$daten = auhof_organigramm_daten();
	$nf    = $daten['nach_funktion'];
	$html  = '';

	foreach ( auhof_organigramm_kinder( 0 ) as $bereich ) {
		$kinder = auhof_organigramm_kinder( $bereich->term_id );
		$id     = 'eg-h-' . sanitize_html_class( $bereich->slug );

		if ( 'leitung' === $bereich->slug ) {
			$kopf  = '';
			$staff = '';
			foreach ( $kinder as $i => $f ) {
				if ( empty( $nf[ $f->term_id ] ) ) {
					continue;
				}
				$karte = '<div class="eg-card' . ( '' === $kopf ? ' eg-head' : '' ) . '" data-s><h3>' . esc_html( $f->name ) . '</h3><p>' . auhof_organigramm_namen( $nf[ $f->term_id ] ) . '</p></div>';
				if ( '' === $kopf ) {
					$kopf = $karte;
				} else {
					$staff .= $karte;
				}
			}
			if ( $kopf ) {
				$html .= '<section class="eg-top" aria-labelledby="' . esc_attr( $id ) . '"><h2 class="eg-sr" id="' . esc_attr( $id ) . '">' . esc_html( $bereich->name ) . '</h2>' . $kopf . ( $staff ? '<div class="eg-staff">' . $staff . '</div>' : '' ) . '</section>';
			}
			continue;
		}

		$inhalt = '';
		if ( 'zweige' === $bereich->slug ) {
			foreach ( $kinder as $z ) {
				$buchstaben = auhof_organigramm_buchstaben( get_term_meta( $z->term_id, 'auhof_klassen', true ) );
				$leitung    = isset( $nf[ $z->term_id ] ) ? $nf[ $z->term_id ] : array();
				$zeilen     = array();
				if ( $buchstaben ) {
					foreach ( $daten['klassen'] as $klasse => $kv ) {
						if ( in_array( substr( $klasse, 1, 1 ), $buchstaben, true ) ) {
							$zeilen[ $klasse ] = '<li class="eg-class" data-s><span class="eg-badge">' . esc_html( $klasse ) . '</span><span class="eg-name">' . auhof_organigramm_namen( $kv ) . '</span></li>';
						}
					}
					uksort( $zeilen, 'strnatcmp' );
				} else {
					foreach ( $leitung as $p ) {
						$zeilen[] = '<li class="eg-class" data-s><span class="eg-name">' . esc_html( $p['name'] ) . '</span></li>';
					}
				}
				if ( ! $zeilen && ! $leitung ) {
					continue;
				}
				$breit   = ! $buchstaben || count( $zeilen ) > 18;
				$inhalt .= '<article class="eg-branch eg-branch--' . sanitize_html_class( $z->slug ) . ( $breit ? ' eg-branch--wide' : '' ) . '">'
					. '<div class="eg-branch-head"><h3>' . esc_html( $z->name ) . '</h3>'
					. ( $buchstaben && $leitung ? '<p data-s>' . esc_html__( 'Koordination:', 'auhof-schulseiten' ) . ' ' . auhof_organigramm_namen( $leitung ) . '</p>' : '' )
					. '</div>' . ( $zeilen ? '<ul class="eg-classes">' . implode( '', $zeilen ) . '</ul>' : '' ) . '</article>';
			}
			$inhalt = $inhalt ? '<div class="eg-branches">' . $inhalt . '</div>' : '';
		} elseif ( 'fach-args' === $bereich->slug ) {
			foreach ( $kinder as $f ) {
				if ( ! empty( $nf[ $f->term_id ] ) ) {
					$inhalt .= '<div class="eg-arg" data-s><dt>' . esc_html( $f->name ) . '</dt><dd>' . auhof_organigramm_namen( $nf[ $f->term_id ] ) . '</dd></div>';
				}
			}
			$inhalt = $inhalt ? '<dl class="eg-args">' . $inhalt . '</dl>' : '';
		} else {
			foreach ( $kinder as $f ) {
				$rollen = '';
				foreach ( auhof_organigramm_kinder( $f->term_id ) as $r ) {
					if ( ! empty( $nf[ $r->term_id ] ) ) {
						$rollen .= '<li data-s><span class="eg-role">' . esc_html( $r->name ) . '</span>' . auhof_organigramm_namen( $nf[ $r->term_id ] ) . '</li>';
					}
				}
				if ( $rollen ) {
					$inhalt .= '<div class="eg-card"><h3>' . esc_html( $f->name ) . '</h3><ul>' . $rollen . '</ul></div>';
				} elseif ( ! empty( $nf[ $f->term_id ] ) ) {
					$inhalt .= '<div class="eg-card" data-s><h3>' . esc_html( $f->name ) . '</h3><p>' . auhof_organigramm_namen( $nf[ $f->term_id ] ) . '</p></div>';
				}
			}
			$inhalt = $inhalt ? '<div class="eg-funcs">' . $inhalt . '</div>' : '';
		}

		if ( $inhalt ) {
			$html .= '<section class="eg-section" aria-labelledby="' . esc_attr( $id ) . '"><h2 class="eg-section-title" id="' . esc_attr( $id ) . '">' . esc_html( $bereich->name ) . '</h2>' . $inhalt . '</section>';
		}
	}

	if ( '' === $html ) {
		return '';
	}
	wp_enqueue_style( 'auhof-organigramm' );
	wp_enqueue_script( 'auhof-organigramm' );

	return '<div class="eg-org">'
		. '<div class="eg-header"><p class="eg-sub">' . esc_html( sprintf( /* translators: %s: Schuljahr */ __( 'Europagymnasium Auhof · Schuljahr %s', 'auhof-schulseiten' ), $jahr ) ) . '</p>'
		. '<div class="eg-search" hidden><label class="eg-sr" for="eg-q">' . esc_html__( 'Organigramm durchsuchen', 'auhof-schulseiten' ) . '</label>'
		. '<input id="eg-q" type="search" placeholder="' . esc_attr__( 'Name, Klasse, Fach …', 'auhof-schulseiten' ) . '" autocomplete="off"><span class="eg-count" aria-live="polite"></span></div></div>'
		. $html . '</div>';
}

add_action( 'wp_enqueue_scripts', 'auhof_organigramm_assets' );

/**
 * Stylesheet und Skript registrieren; Stylesheet bei Bedarf im Kopf laden.
 */
function auhof_organigramm_assets() {
	wp_register_style( 'auhof-organigramm', AUHOF_SCHULSEITEN_URL . 'assets/organigramm.css', array(), AUHOF_SCHULSEITEN_VERSION );
	wp_register_script(
		'auhof-organigramm',
		AUHOF_SCHULSEITEN_URL . 'assets/organigramm.js',
		array(),
		AUHOF_SCHULSEITEN_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'auhof_organigramm' ) ) {
		wp_enqueue_style( 'auhof-organigramm' );
	}
}
