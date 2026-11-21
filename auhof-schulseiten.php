<?php
/**
 * Plugin Name:       Auhof Schulseiten
 * Description:       Lehrkräfte, Verwaltung, Organigramm, Raumplan, Terminvorschau und Diashow – im Backend pflegen, die Seiten werden automatisch erzeugt.
 * Version:           1.4.8
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Europagymnasium Auhof
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       auhof-schulseiten
 * Update URI:        false
 *
 * Shortcodes:
 *   [auhof_personen gruppe="lehrkraefte" az="ja"]  Personen einer Gruppe, optional mit A–Z-Leiste
 *   [auhof_raumplan]                                Interaktiver Raumplan
 *   [auhof_termine anzahl="5"]                       Nächste schulweite Termine (aus dem Plugin „Sintoora Kalender“)
 *   [auhof_diashow]                                 Diashow der Startseite (Bilder und Anzeige unter „Diashow“)
 *   [auhof_organigramm]                             Organigramm aus Funktionen und Klassenvorstand der Personen
 *
 * Bewusst einfach gehalten: nur WordPress-Standardfunktionen (eigener Beitragstyp,
 * Taxonomie, Einstellungs-API, Shortcodes), keine REST- oder AJAX-Endpunkte,
 * keine externen Bibliotheken oder Abrufe.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

define( 'AUHOF_SCHULSEITEN_VERSION', '1.4.8' );
define( 'AUHOF_SCHULSEITEN_PFAD', plugin_dir_path( __FILE__ ) );
define( 'AUHOF_SCHULSEITEN_URL', plugin_dir_url( __FILE__ ) );

require_once AUHOF_SCHULSEITEN_PFAD . 'includes/personen.php';
require_once AUHOF_SCHULSEITEN_PFAD . 'includes/raumplan.php';
require_once AUHOF_SCHULSEITEN_PFAD . 'includes/termine.php';
require_once AUHOF_SCHULSEITEN_PFAD . 'includes/diashow.php';
require_once AUHOF_SCHULSEITEN_PFAD . 'includes/bilder.php';
require_once AUHOF_SCHULSEITEN_PFAD . 'includes/organigramm.php';

if ( is_admin() ) {
	require_once AUHOF_SCHULSEITEN_PFAD . 'includes/personen-admin.php';
	require_once AUHOF_SCHULSEITEN_PFAD . 'includes/raumplan-admin.php';
	require_once AUHOF_SCHULSEITEN_PFAD . 'includes/diashow-admin.php';
	require_once AUHOF_SCHULSEITEN_PFAD . 'includes/organigramm-admin.php';
	require_once AUHOF_SCHULSEITEN_PFAD . 'includes/personen-csv.php';
}

register_activation_hook( __FILE__, 'auhof_schulseiten_aktivieren' );

/**
 * Legt beim Aktivieren die Standardgruppen an.
 */
function auhof_schulseiten_aktivieren() {
	auhof_organigramm_grundstruktur_anlegen();
	auhof_personen_registrieren();
	$gruppen = array(
		'lehrkraefte'      => 'Lehrkräfte',
		'schulleitung'     => 'Schulleitung & Administration',
		'sekretariat'      => 'Sekretariat',
		'weitere-kontakte' => 'Weitere Kontakte',
	);
	foreach ( $gruppen as $slug => $name ) {
		if ( ! term_exists( $slug, 'auhof_gruppe' ) ) {
			wp_insert_term( $name, 'auhof_gruppe', array( 'slug' => $slug ) );
		}
	}
}
