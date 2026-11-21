<?php
/**
 * Personen (Lehrkräfte, Verwaltung): Datentyp, Gruppen und Ausgabe auf der Website.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Felder einer Person: Schlüssel => Beschriftung. Gespeichert als Metadaten „_auhof_<schlüssel>“.
 *
 * @return array<string,string>
 */
function auhof_personen_felder() {
	return array(
		'titel_vor'  => __( 'Titel vorn', 'auhof-schulseiten' ),
		'vorname'    => __( 'Vorname', 'auhof-schulseiten' ),
		'nachname'   => __( 'Nachname', 'auhof-schulseiten' ),
		'titel_nach' => __( 'Titel hinten', 'auhof-schulseiten' ),
		'zusatz'     => __( 'Fächer / Funktion', 'auhof-schulseiten' ),
		'email'      => __( 'E-Mail', 'auhof-schulseiten' ),
		'telefon'    => __( 'Telefon', 'auhof-schulseiten' ),
		'klasse'     => __( 'Klassenvorstand (Klasse)', 'auhof-schulseiten' ),
	);
}

/**
 * Recht, das für alles im Plugin nötig ist (Personen, Gruppen, Raumplan).
 * Standard „edit_others_pages“: Redakteur:innen und Administrator:innen. Bewusst nicht „edit_pages“,
 * weil Autor:innen und Mitarbeiter:innen dieses Recht auf manchen Websites zusätzlich haben.
 *
 * @return string
 */
function auhof_schulseiten_recht() {
	return (string) apply_filters( 'auhof_schulseiten_recht', 'edit_others_pages' );
}

/**
 * Alle Grundrechte des Beitragstyps auf das Plugin-Recht abbilden (Lesen bleibt „read“).
 *
 * @return array<string,string>
 */
function auhof_schulseiten_rechte_fuer_beitragstyp() {
	$recht  = auhof_schulseiten_recht();
	$rechte = array( 'read' => 'read' );
	foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts', 'create_posts' ) as $grundrecht ) {
		$rechte[ $grundrecht ] = $recht;
	}
	return $rechte;
}

/**
 * Ein Feld einer Person bereinigen (Eingabemaske und CSV-Import).
 *
 * @param string $feld Feldname.
 * @param mixed  $wert Rohwert.
 * @return string
 */
function auhof_personen_wert_bereinigen( $feld, $wert ) {
	$wert = is_scalar( $wert ) ? (string) $wert : '';
	if ( 'email' === $feld ) {
		$wert = sanitize_email( $wert );
	} elseif ( 'telefon' === $feld ) {
		$wert = trim( (string) preg_replace( '/[^0-9+\-\/() ]/', '', $wert ) );
	} elseif ( 'klasse' === $feld ) {
		// Eine oder mehrere Klassen, z. B. „3B“ oder „4L, 4M“.
		preg_match_all( '/\d[A-Za-z]{1,3}/', $wert, $m );
		$wert = implode( ', ', array_unique( array_map( 'strtoupper', $m[0] ) ) );
	} else {
		$wert = sanitize_text_field( $wert );
	}
	return mb_substr( $wert, 0, 200 );
}

add_action( 'init', 'auhof_personen_registrieren' );

/**
 * Beitragstyp „Person“ und Taxonomie „Gruppe“. Beides ist nicht öffentlich abrufbar
 * (keine Einzelseiten, keine Archive, keine REST-Route) – sichtbar nur über die Shortcodes.
 */
function auhof_personen_registrieren() {
	register_post_type(
		'auhof_person',
		array(
			'labels'              => array(
				'name'                  => __( 'Personen', 'auhof-schulseiten' ),
				'singular_name'         => __( 'Person', 'auhof-schulseiten' ),
				'menu_name'             => __( 'Personen', 'auhof-schulseiten' ),
				'all_items'             => __( 'Alle Personen', 'auhof-schulseiten' ),
				'add_new'               => __( 'Neue Person', 'auhof-schulseiten' ),
				'add_new_item'          => __( 'Neue Person', 'auhof-schulseiten' ),
				'edit_item'             => __( 'Person bearbeiten', 'auhof-schulseiten' ),
				'search_items'          => __( 'Personen suchen', 'auhof-schulseiten' ),
				'not_found'             => __( 'Keine Personen gefunden.', 'auhof-schulseiten' ),
				'not_found_in_trash'    => __( 'Keine Personen im Papierkorb.', 'auhof-schulseiten' ),
				'featured_image'        => __( 'Foto', 'auhof-schulseiten' ),
				'set_featured_image'    => __( 'Foto wählen', 'auhof-schulseiten' ),
				'remove_featured_image' => __( 'Foto entfernen', 'auhof-schulseiten' ),
				'use_featured_image'    => __( 'Als Foto verwenden', 'auhof-schulseiten' ),
				'item_published'        => __( 'Person gespeichert.', 'auhof-schulseiten' ),
				'item_updated'          => __( 'Person gespeichert.', 'auhof-schulseiten' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'thumbnail', 'revisions' ),
			'capability_type'     => 'auhof_person',
			'capabilities'        => auhof_schulseiten_rechte_fuer_beitragstyp(),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => true,
			'delete_with_user'    => false,
		)
	);

	register_taxonomy(
		'auhof_gruppe',
		'auhof_person',
		array(
			'labels'             => array(
				'name'          => __( 'Gruppen', 'auhof-schulseiten' ),
				'singular_name' => __( 'Gruppe', 'auhof-schulseiten' ),
				'menu_name'     => __( 'Gruppen', 'auhof-schulseiten' ),
				'all_items'     => __( 'Alle Gruppen', 'auhof-schulseiten' ),
				'edit_item'     => __( 'Gruppe bearbeiten', 'auhof-schulseiten' ),
				'add_new_item'  => __( 'Neue Gruppe', 'auhof-schulseiten' ),
				'search_items'  => __( 'Gruppen suchen', 'auhof-schulseiten' ),
				'not_found'     => __( 'Keine Gruppen gefunden.', 'auhof-schulseiten' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => true,
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

	add_image_size( 'auhof-person', 176, 220, array( 'center', 'top' ) );
}

/**
 * Personen einer Gruppe, sortiert nach Reihenfolge, dann Nachname und Vorname.
 *
 * @param string $gruppe Slug der Gruppe.
 * @return array<int,array<string,mixed>>
 */
function auhof_personen_laden( $gruppe ) {
	$abfrage = new WP_Query(
		array(
			'post_type'              => 'auhof_person',
			'post_status'            => 'publish',
			'posts_per_page'         => 500,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- kleine Datenmenge, ein Aufruf je Seite.
			'tax_query'              => array(
				array(
					'taxonomy' => 'auhof_gruppe',
					'field'    => 'slug',
					'terms'    => $gruppe,
				),
			),
		)
	);

	$personen = array();
	foreach ( $abfrage->posts as $post ) {
		$p = array(
			'reihenfolge' => (int) $post->menu_order,
			'foto'        => (int) get_post_thumbnail_id( $post ),
		);
		foreach ( array_keys( auhof_personen_felder() ) as $feld ) {
			$p[ $feld ] = (string) get_post_meta( $post->ID, '_auhof_' . $feld, true );
		}
		$p['sortierung'] = auhof_personen_sortierschluessel( $p['nachname'] . ' ' . $p['vorname'] );
		$personen[]      = $p;
	}

	usort(
		$personen,
		function ( $a, $b ) {
			if ( $a['reihenfolge'] !== $b['reihenfolge'] ) {
				return $a['reihenfolge'] < $b['reihenfolge'] ? -1 : 1;
			}
			return strcmp( $a['sortierung'], $b['sortierung'] );
		}
	);
	return $personen;
}

/**
 * Sortierschlüssel ohne Umlaute und Akzente (Ä → A, é → e), klein geschrieben.
 *
 * @param string $text Text.
 * @return string
 */
function auhof_personen_sortierschluessel( $text ) {
	$ersetzt = strtr(
		$text,
		array(
			'Ä' => 'A',
			'Ö' => 'O',
			'Ü' => 'U',
			'ä' => 'a',
			'ö' => 'o',
			'ü' => 'u',
			'ß' => 'ss',
		)
	);
	return strtolower( remove_accents( $ersetzt ) );
}

/**
 * Anfangsbuchstabe für die A–Z-Leiste (A–Z, sonst „#“).
 *
 * @param string $nachname Nachname.
 * @return string
 */
function auhof_personen_buchstabe( $nachname ) {
	$b = strtoupper( substr( auhof_personen_sortierschluessel( $nachname ), 0, 1 ) );
	return ( '' !== $b && ctype_alpha( $b ) ) ? $b : '#';
}

/**
 * Telefonnummer als tel:-Adresse (0732 … → +43732 …).
 *
 * @param string $nummer Angezeigte Nummer.
 * @return string
 */
function auhof_personen_tel_link( $nummer ) {
	$ziffern = preg_replace( '/[^0-9+]/', '', $nummer );
	if ( 0 === strpos( $ziffern, '00' ) ) {
		$ziffern = '+' . substr( $ziffern, 2 );
	} elseif ( 0 === strpos( $ziffern, '0' ) ) {
		$ziffern = '+43' . substr( $ziffern, 1 );
	}
	return 'tel:' . $ziffern;
}

/**
 * HTML einer Person.
 *
 * @param array<string,mixed> $p Person.
 * @return string
 */
function auhof_personen_karte( $p ) {
	$name = trim( $p['vorname'] . ' ' . $p['nachname'] );

	$zeile = '';
	if ( '' !== $p['titel_vor'] ) {
		$zeile .= '<span class="auhof-person__titel">' . esc_html( $p['titel_vor'] ) . '</span> ';
	}
	$zeile .= '<strong>' . esc_html( $name ) . '</strong>';
	if ( '' !== $p['titel_nach'] ) {
		$zeile .= '<span class="auhof-person__titel">, ' . esc_html( $p['titel_nach'] ) . '</span>';
	}

	$text = '<div>' . $zeile . '</div>';
	if ( '' !== $p['zusatz'] ) {
		$text .= '<div class="auhof-person__zusatz">' . esc_html( $p['zusatz'] ) . '</div>';
	}
	if ( '' !== $p['telefon'] ) {
		$text .= '<div>' . esc_html__( 'Tel.', 'auhof-schulseiten' ) . ' <a href="' . esc_url( auhof_personen_tel_link( $p['telefon'] ), array( 'tel' ) ) . '">' . esc_html( $p['telefon'] ) . '</a></div>';
	}
	if ( '' !== $p['email'] ) {
		$text .= '<div><a href="' . esc_url( 'mailto:' . $p['email'], array( 'mailto' ) ) . '">' . esc_html( $p['email'] ) . '</a></div>';
	}

	if ( ! $p['foto'] ) {
		return '<li class="auhof-person">' . $text . '</li>';
	}
	$bild = wp_get_attachment_image(
		$p['foto'],
		'auhof-person',
		false,
		array(
			'class'   => 'auhof-person__foto',
			'alt'     => '', // Der Name steht direkt daneben; so wird er nicht doppelt vorgelesen.
			'loading' => 'lazy',
			'sizes'   => '88px',
		)
	);
	return '<li class="auhof-person auhof-person--foto">' . $bild . '<div class="auhof-person__text">' . $text . '</div></li>';
}

/**
 * Liste von Personen (Raster).
 *
 * @param array<int,array<string,mixed>> $personen Personen.
 * @return string
 */
function auhof_personen_liste( $personen ) {
	$mit_foto = false;
	foreach ( $personen as $p ) {
		if ( $p['foto'] ) {
			$mit_foto = true;
			break;
		}
	}
	$html = '<ul class="auhof-personen' . ( $mit_foto ? ' auhof-personen--foto' : '' ) . '" role="list">';
	foreach ( $personen as $p ) {
		$html .= auhof_personen_karte( $p );
	}
	return $html . '</ul>';
}

add_shortcode( 'auhof_personen', 'auhof_personen_shortcode' );

/**
 * Shortcode [auhof_personen gruppe="slug" az="ja"].
 *
 * @param array<string,string>|string $atts Attribute.
 * @return string
 */
function auhof_personen_shortcode( $atts ) {
	$atts     = shortcode_atts(
		array(
			'gruppe' => '',
			'az'     => 'nein',
		),
		$atts,
		'auhof_personen'
	);
	$gruppe   = sanitize_title( $atts['gruppe'] );
	$personen = '' !== $gruppe ? auhof_personen_laden( $gruppe ) : array();
	if ( ! $personen ) {
		return '';
	}
	wp_enqueue_style( 'auhof-personen' );

	if ( ! in_array( strtolower( $atts['az'] ), array( 'ja', '1', 'true' ), true ) ) {
		return auhof_personen_liste( $personen );
	}

	$nach_buchstabe = array();
	foreach ( $personen as $p ) {
		$nach_buchstabe[ auhof_personen_buchstabe( $p['nachname'] ) ][] = $p;
	}
	ksort( $nach_buchstabe );

	$leiste    = '';
	$abschnitt = '';
	foreach ( $nach_buchstabe as $buchstabe => $liste ) {
		$anker      = 'lk-' . ( '#' === $buchstabe ? 'andere' : strtolower( $buchstabe ) );
		$leiste    .= '<a href="#' . esc_attr( $anker ) . '">' . esc_html( $buchstabe ) . '</a>';
		$abschnitt .= '<div class="auhof-az__kopf"><h3 id="' . esc_attr( $anker ) . '">' . esc_html( $buchstabe ) . '</h3>'
			. '<a href="#lk-top">' . esc_html__( '↑ nach oben', 'auhof-schulseiten' ) . '</a></div>'
			. auhof_personen_liste( $liste );
	}

	return '<nav class="auhof-az" id="lk-top" aria-label="' . esc_attr__( 'Nach Anfangsbuchstabe', 'auhof-schulseiten' ) . '">' . $leiste . '</nav>' . $abschnitt;
}

add_action( 'wp_enqueue_scripts', 'auhof_personen_assets' );

/**
 * Stylesheet registrieren; im Kopf laden, wenn die Seite den Shortcode enthält (vermeidet Flackern).
 */
function auhof_personen_assets() {
	wp_register_style( 'auhof-personen', AUHOF_SCHULSEITEN_URL . 'assets/personen.css', array(), AUHOF_SCHULSEITEN_VERSION );
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'auhof_personen' ) ) {
		wp_enqueue_style( 'auhof-personen' );
	}
}
