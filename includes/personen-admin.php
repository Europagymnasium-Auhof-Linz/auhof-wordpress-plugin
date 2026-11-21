<?php
/**
 * Personen im Backend: Eingabemaske, Speichern, Übersichtsliste.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes_auhof_person', 'auhof_personen_metaboxen' );

/**
 * Eingabemaske „Angaben“ und Foto-Box an die Seite.
 */
function auhof_personen_metaboxen() {
	add_meta_box( 'auhof_person_angaben', __( 'Angaben', 'auhof-schulseiten' ), 'auhof_personen_maske', 'auhof_person', 'normal', 'high' );
	remove_meta_box( 'slugdiv', 'auhof_person', 'normal' );
}

/**
 * Eingabemaske.
 *
 * @param WP_Post $post Person.
 */
function auhof_personen_maske( $post ) {
	wp_nonce_field( 'auhof_person_speichern', 'auhof_person_nonce' );
	$typen = array(
		'email'   => 'email',
		'telefon' => 'tel',
	);
	echo '<div class="auhof-maske">';
	foreach ( auhof_personen_felder() as $feld => $label ) {
		$id   = 'auhof_' . $feld;
		$wert = (string) get_post_meta( $post->ID, '_auhof_' . $feld, true );
		printf(
			'<p class="auhof-maske__feld auhof-maske__feld--%1$s"><label for="%2$s">%3$s</label><input type="%4$s" id="%2$s" name="auhof[%1$s]" value="%5$s" maxlength="200"%6$s></p>',
			esc_attr( $feld ),
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( isset( $typen[ $feld ] ) ? $typen[ $feld ] : 'text' ),
			esc_attr( $wert ),
			'nachname' === $feld ? ' required' : ''
		);
	}
	printf(
		'<p class="auhof-maske__feld auhof-maske__feld--reihenfolge"><label for="auhof_reihenfolge">%1$s</label><input type="number" id="auhof_reihenfolge" name="auhof_reihenfolge" value="%2$d" min="0" max="9999" step="1"></p>',
		esc_html__( 'Reihenfolge', 'auhof-schulseiten' ),
		(int) $post->menu_order
	);
	echo '</div><p class="description">' . esc_html__( 'Reihenfolge 0 = alphabetisch. Kleinere Zahlen stehen weiter vorn.', 'auhof-schulseiten' ) . '</p>';
}

/**
 * Prüft Nonce und Berechtigung für das Speichern aus der Eingabemaske.
 *
 * @param int $post_id Beitrags-ID.
 * @return bool
 */
function auhof_personen_darf_speichern( $post_id ) {
	if ( ! isset( $_POST['auhof_person_nonce'] ) ) {
		return false;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['auhof_person_nonce'] ) );
	return wp_verify_nonce( $nonce, 'auhof_person_speichern' ) && current_user_can( 'edit_post', $post_id );
}

/**
 * Bereinigte Eingaben aus der Maske.
 *
 * @return array<string,string>
 */
function auhof_personen_eingaben() {
	// Nonce wurde in auhof_personen_darf_speichern() geprüft.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird unten feldweise bereinigt.
	$roh   = isset( $_POST['auhof'] ) && is_array( $_POST['auhof'] ) ? wp_unslash( $_POST['auhof'] ) : array();
	$werte = array();
	foreach ( array_keys( auhof_personen_felder() ) as $feld ) {
		$werte[ $feld ] = auhof_personen_wert_bereinigen( $feld, isset( $roh[ $feld ] ) && is_string( $roh[ $feld ] ) ? $roh[ $feld ] : '' );
	}
	return $werte;
}

add_filter( 'wp_insert_post_data', 'auhof_personen_titel', 10, 2 );

/**
 * Titel (für Übersicht und Suche) und Reihenfolge aus den Feldern setzen.
 *
 * @param array<string,mixed> $daten   Bereinigte Beitragsdaten.
 * @param array<string,mixed> $roh     Rohdaten.
 * @return array<string,mixed>
 */
function auhof_personen_titel( $daten, $roh ) {
	if ( 'auhof_person' !== $daten['post_type'] || empty( $roh['ID'] ) || ! auhof_personen_darf_speichern( (int) $roh['ID'] ) ) {
		return $daten;
	}
	$w     = auhof_personen_eingaben();
	$titel = trim( $w['vorname'] . ' ' . $w['nachname'] );

	$daten['post_title'] = '' !== $titel ? $titel : __( '(ohne Namen)', 'auhof-schulseiten' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- geprüft in auhof_personen_darf_speichern().
	$daten['menu_order'] = isset( $_POST['auhof_reihenfolge'] ) ? min( 9999, absint( $_POST['auhof_reihenfolge'] ) ) : 0;
	return $daten;
}

add_action( 'save_post_auhof_person', 'auhof_personen_speichern' );

/**
 * Felder speichern.
 *
 * @param int $post_id Beitrags-ID.
 */
function auhof_personen_speichern( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! auhof_personen_darf_speichern( $post_id ) ) {
		return;
	}
	foreach ( auhof_personen_eingaben() as $feld => $wert ) {
		update_post_meta( $post_id, '_auhof_' . $feld, $wert );
	}
}

/* ---------- Übersichtsliste ---------- */

add_filter( 'manage_auhof_person_posts_columns', 'auhof_personen_spalten' );

/**
 * Spalten der Übersicht.
 *
 * @param array<string,string> $spalten Spalten.
 * @return array<string,string>
 */
function auhof_personen_spalten( $spalten ) {
	return array(
		'cb'                      => $spalten['cb'],
		'auhof_foto'              => __( 'Foto', 'auhof-schulseiten' ),
		'title'                   => __( 'Name', 'auhof-schulseiten' ),
		'auhof_zusatz'            => __( 'Fächer / Funktion', 'auhof-schulseiten' ),
		'auhof_email'             => __( 'E-Mail', 'auhof-schulseiten' ),
		'taxonomy-auhof_gruppe'   => __( 'Gruppe', 'auhof-schulseiten' ),
		'taxonomy-auhof_funktion' => __( 'Funktionen', 'auhof-schulseiten' ),
		'auhof_klasse'            => __( 'KV', 'auhof-schulseiten' ),
	);
}

add_action( 'manage_auhof_person_posts_custom_column', 'auhof_personen_spalte', 10, 2 );

/**
 * Inhalt der eigenen Spalten.
 *
 * @param string $spalte  Spalte.
 * @param int    $post_id Beitrags-ID.
 */
function auhof_personen_spalte( $spalte, $post_id ) {
	if ( 'auhof_foto' === $spalte ) {
		echo get_the_post_thumbnail( $post_id, array( 40, 50 ), array( 'alt' => '' ) );
	} elseif ( 'auhof_zusatz' === $spalte ) {
		echo esc_html( get_post_meta( $post_id, '_auhof_zusatz', true ) );
	} elseif ( 'auhof_email' === $spalte ) {
		echo esc_html( get_post_meta( $post_id, '_auhof_email', true ) );
	} elseif ( 'auhof_klasse' === $spalte ) {
		echo esc_html( get_post_meta( $post_id, '_auhof_klasse', true ) );
	}
}

add_filter( 'manage_edit-auhof_person_sortable_columns', 'auhof_personen_sortierbar' );

/**
 * Name sortiert nach Nachname.
 *
 * @param array<string,string> $spalten Sortierbare Spalten.
 * @return array<string,string>
 */
function auhof_personen_sortierbar( $spalten ) {
	$spalten['title'] = 'nachname';
	return $spalten;
}

add_action( 'pre_get_posts', 'auhof_personen_admin_abfrage' );

/**
 * Übersicht: standardmäßig nach Nachname sortiert; Filter nach Gruppe.
 *
 * @param WP_Query $abfrage Abfrage.
 */
function auhof_personen_admin_abfrage( $abfrage ) {
	if ( ! is_admin() || ! $abfrage->is_main_query() || 'auhof_person' !== $abfrage->get( 'post_type' ) ) {
		return;
	}
	$orderby = $abfrage->get( 'orderby' );
	if ( '' === $orderby || 'nachname' === $orderby ) {
		$abfrage->set( 'meta_key', '_auhof_nachname' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- kleine Datenmenge.
		$abfrage->set( 'orderby', array( 'meta_value' => $abfrage->get( 'order' ) ? $abfrage->get( 'order' ) : 'ASC' ) );
	}
	// Reiner Lesefilter der Übersicht (wie die Kategorie-Auswahl von WordPress), ändert keine Daten.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$gruppe = isset( $_GET['auhof_gruppe'] ) ? sanitize_title( wp_unslash( $_GET['auhof_gruppe'] ) ) : '';
	// „Alle Gruppen“ sendet den Wert 0 – dann nicht filtern.
	if ( '' !== $gruppe && '0' !== $gruppe ) {
		$abfrage->set(
			'tax_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				array(
					'taxonomy' => 'auhof_gruppe',
					'field'    => 'slug',
					'terms'    => $gruppe,
				),
			)
		);
	}
}

add_filter( 'posts_search', 'auhof_personen_admin_suche', 10, 2 );

/**
 * Suche in der Übersicht: zusätzlich zu Vor- und Nachname (Titel) auch E-Mail/Kürzel, Fächer/Funktion und Klasse.
 *
 * @param string   $suche   SQL-Teil der Suche.
 * @param WP_Query $abfrage Abfrage.
 * @return string
 */
function auhof_personen_admin_suche( $suche, $abfrage ) {
	global $wpdb;
	$begriff = trim( (string) $abfrage->get( 's' ) );
	if ( '' === $suche || '' === $begriff || ! is_admin() || ! $abfrage->is_main_query() || 'auhof_person' !== $abfrage->get( 'post_type' ) ) {
		return $suche;
	}
	$like  = '%' . $wpdb->esc_like( $begriff ) . '%';
	$extra = $wpdb->prepare(
		"{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '_auhof_email', '_auhof_zusatz', '_auhof_klasse', '_auhof_nachname', '_auhof_vorname' ) AND meta_value LIKE %s )",
		$like
	);
	// Die Suche von WordPress beginnt mit „ AND (“ – die zusätzliche Bedingung wird mit OR angehängt.
	return preg_replace( '/^\s*AND\s*\(/', ' AND ( ' . $extra . ' OR ', $suche, 1 );
}

add_action( 'restrict_manage_posts', 'auhof_personen_gruppenfilter' );

/**
 * Auswahlfeld „Alle Gruppen“ über der Übersicht.
 *
 * @param string $post_type Beitragstyp.
 */
function auhof_personen_gruppenfilter( $post_type ) {
	if ( 'auhof_person' !== $post_type ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lesefilter.
	$aktiv = isset( $_GET['auhof_gruppe'] ) ? sanitize_title( wp_unslash( $_GET['auhof_gruppe'] ) ) : '';
	echo '<label class="screen-reader-text" for="auhof_gruppe">' . esc_html__( 'Nach Gruppe filtern', 'auhof-schulseiten' ) . '</label>';
	wp_dropdown_categories(
		array(
			'taxonomy'        => 'auhof_gruppe',
			'name'            => 'auhof_gruppe',
			'id'              => 'auhof_gruppe',
			'value_field'     => 'slug',
			'selected'        => $aktiv,
			'show_option_all' => __( 'Alle Gruppen', 'auhof-schulseiten' ),
			'hide_empty'      => false,
			'orderby'         => 'name',
		)
	);
}

/* ---------- Gruppen: Shortcode zum Kopieren ---------- */

add_filter( 'manage_edit-auhof_gruppe_columns', 'auhof_gruppen_spalten' );

/**
 * Spalte „Shortcode“ in der Gruppenliste.
 *
 * @param array<string,string> $spalten Spalten.
 * @return array<string,string>
 */
function auhof_gruppen_spalten( $spalten ) {
	unset( $spalten['description'] );
	$spalten['auhof_shortcode'] = __( 'Shortcode', 'auhof-schulseiten' );
	return $spalten;
}

add_filter( 'manage_auhof_gruppe_custom_column', 'auhof_gruppen_spalte', 10, 3 );

/**
 * Shortcode einer Gruppe.
 *
 * @param string $inhalt  Bisheriger Inhalt.
 * @param string $spalte  Spalte.
 * @param int    $term_id Gruppen-ID.
 * @return string
 */
function auhof_gruppen_spalte( $inhalt, $spalte, $term_id ) {
	if ( 'auhof_shortcode' !== $spalte ) {
		return $inhalt;
	}
	$term = get_term( $term_id, 'auhof_gruppe' );
	if ( ! $term || is_wp_error( $term ) ) {
		return $inhalt;
	}
	$az = 'lehrkraefte' === $term->slug ? ' az="ja"' : '';
	return '<code>' . esc_html( '[auhof_personen gruppe="' . $term->slug . '"' . $az . ']' ) . '</code>';
}

/* ---------- Aussehen der Maske ---------- */

add_action( 'admin_enqueue_scripts', 'auhof_personen_admin_assets' );

/**
 * Stylesheet nur auf den Personen-Seiten laden.
 */
function auhof_personen_admin_assets() {
	$screen = get_current_screen();
	if ( $screen && 'auhof_person' === $screen->post_type ) {
		wp_enqueue_style( 'auhof-admin', AUHOF_SCHULSEITEN_URL . 'assets/admin.css', array(), AUHOF_SCHULSEITEN_VERSION );
	}
}
