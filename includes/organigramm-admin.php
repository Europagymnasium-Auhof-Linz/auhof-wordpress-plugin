<?php
/**
 * Organigramm im Backend: Felder „Reihenfolge“ und „Klassen“ bei den Funktionen.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

add_action( 'auhof_funktion_add_form_fields', 'auhof_organigramm_felder_neu' );

/**
 * Felder beim Anlegen einer Funktion.
 */
function auhof_organigramm_felder_neu() {
	?>
	<div class="form-field">
		<label for="auhof-reihenfolge"><?php esc_html_e( 'Reihenfolge', 'auhof-schulseiten' ); ?></label>
		<input type="number" id="auhof-reihenfolge" name="auhof_reihenfolge" value="0" min="0" max="999">
	</div>
	<div class="form-field">
		<label for="auhof-klassen"><?php esc_html_e( 'Klassen (nur bei Zweigen)', 'auhof-schulseiten' ); ?></label>
		<input type="text" id="auhof-klassen" name="auhof_klassen" value="" maxlength="40" placeholder="A-E">
		<p><?php esc_html_e( 'Buchstaben der Klassen dieses Zweigs, z. B. „A-E“ oder „F, G“.', 'auhof-schulseiten' ); ?></p>
	</div>
	<?php
}

add_action( 'auhof_funktion_edit_form_fields', 'auhof_organigramm_felder_bearbeiten' );

/**
 * Felder beim Bearbeiten einer Funktion.
 *
 * @param WP_Term $term Funktion.
 */
function auhof_organigramm_felder_bearbeiten( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><label for="auhof-reihenfolge"><?php esc_html_e( 'Reihenfolge', 'auhof-schulseiten' ); ?></label></th>
		<td><input type="number" id="auhof-reihenfolge" name="auhof_reihenfolge" value="<?php echo esc_attr( (string) (int) get_term_meta( $term->term_id, 'auhof_reihenfolge', true ) ); ?>" min="0" max="999"></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="auhof-klassen"><?php esc_html_e( 'Klassen (nur bei Zweigen)', 'auhof-schulseiten' ); ?></label></th>
		<td><input type="text" id="auhof-klassen" name="auhof_klassen" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, 'auhof_klassen', true ) ); ?>" maxlength="40" placeholder="A-E">
			<p class="description"><?php esc_html_e( 'Buchstaben der Klassen dieses Zweigs, z. B. „A-E“ oder „F, G“.', 'auhof-schulseiten' ); ?></p></td>
	</tr>
	<?php
}

add_action( 'created_auhof_funktion', 'auhof_organigramm_felder_speichern' );
add_action( 'edited_auhof_funktion', 'auhof_organigramm_felder_speichern' );

/**
 * Felder speichern (Nonce des Formulars prüft WordPress; zusätzlich hier geprüft).
 *
 * @param int $term_id Funktion.
 */
function auhof_organigramm_felder_speichern( $term_id ) {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		return;
	}
	$ok = ( isset( $_POST['_wpnonce_add-tag'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_add-tag'] ) ), 'add-tag' ) )
		|| ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-tag_' . $term_id ) );
	if ( ! $ok ) {
		return;
	}
	if ( isset( $_POST['auhof_reihenfolge'] ) ) {
		update_term_meta( $term_id, 'auhof_reihenfolge', min( 999, absint( $_POST['auhof_reihenfolge'] ) ) );
	}
	if ( isset( $_POST['auhof_klassen'] ) ) {
		$klassen = implode( ', ', auhof_organigramm_buchstaben_text( sanitize_text_field( wp_unslash( $_POST['auhof_klassen'] ) ) ) );
		update_term_meta( $term_id, 'auhof_klassen', $klassen );
	}
}

/**
 * Klassen-Angabe auf erlaubte Teile beschränken („A-E“, „F“).
 *
 * @param string $angabe Eingabe.
 * @return string[]
 */
function auhof_organigramm_buchstaben_text( $angabe ) {
	preg_match_all( '/[A-Z]-[A-Z]|[A-Z]/', strtoupper( $angabe ), $m );
	return array_unique( $m[0] );
}

add_filter( 'manage_edit-auhof_funktion_columns', 'auhof_organigramm_spalten' );

/**
 * Spalten der Funktionsliste.
 *
 * @param array<string,string> $spalten Spalten.
 * @return array<string,string>
 */
function auhof_organigramm_spalten( $spalten ) {
	unset( $spalten['description'], $spalten['slug'] );
	$spalten['auhof_reihenfolge'] = __( 'Reihenfolge', 'auhof-schulseiten' );
	$spalten['auhof_klassen']     = __( 'Klassen', 'auhof-schulseiten' );
	return $spalten;
}

add_filter( 'manage_auhof_funktion_custom_column', 'auhof_organigramm_spalte', 10, 3 );

/**
 * Inhalt der Spalten.
 *
 * @param string $inhalt  Inhalt.
 * @param string $spalte  Spalte.
 * @param int    $term_id Funktion.
 * @return string
 */
function auhof_organigramm_spalte( $inhalt, $spalte, $term_id ) {
	if ( 'auhof_reihenfolge' === $spalte ) {
		return esc_html( (string) (int) get_term_meta( $term_id, 'auhof_reihenfolge', true ) );
	}
	if ( 'auhof_klassen' === $spalte ) {
		return esc_html( (string) get_term_meta( $term_id, 'auhof_klassen', true ) );
	}
	return $inhalt;
}

add_action( 'auhof_funktion_pre_add_form', 'auhof_organigramm_hinweis' );

/**
 * Kurzer Hinweis über dem Formular.
 */
function auhof_organigramm_hinweis() {
	echo '<p>' . esc_html__( 'Funktionen bei der Person anhaken; das Organigramm ([auhof_organigramm]) entsteht daraus. Oberste Ebene = Bereich, darunter die Funktionen.', 'auhof-schulseiten' ) . '</p>';
}
