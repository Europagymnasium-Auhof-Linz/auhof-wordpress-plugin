=== Auhof Schulseiten ===
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lehrkräfte, Verwaltung und Raumplan im Backend pflegen.

== Description ==

= Personen =
Backend → Personen. Je Person: Name, Titel, Fächer/Funktion, E-Mail, Telefon, Foto, Gruppe.
Reihenfolge 0 = alphabetisch nach Nachname; kleinere Zahlen stehen weiter vorn.
Ausscheiden: Person in den Papierkorb legen. Vorübergehend ausblenden: als Entwurf speichern.

Einbinden auf einer Seite (Shortcode steht auch unter Personen → Gruppen):
[auhof_personen gruppe="lehrkraefte" az="ja"]
[auhof_personen gruppe="sekretariat"]

= Raumplan =
Backend → Raumplan. Schuljahr und Raumnamen ändern; der Gebäudeplan selbst liegt in data/raumplan.json.
Einbinden: [auhof_raumplan]

= Terminvorschau =
Startseite: [auhof_termine anzahl="5"] zeigt die nächsten schulweiten Termine (Sintoora-Terminart „Allgemein (Homepage)“, ohne Klassentermine). Nutzt die zwischengespeicherten Daten des Plugins „Sintoora Kalender“; ohne dieses Plugin erscheint nur der Link zum Schulkalender.

= Technik =
Nur WordPress-Standardfunktionen, keine REST-/AJAX-Endpunkte, keine externen Abrufe.
Personen und Gruppen sind nicht öffentlich abrufbar, nur über die Shortcodes.
Berechtigung: Recht „edit_others_pages“ (Redakteur, Administrator); änderbar mit dem Filter auhof_schulseiten_recht.
Beim Löschen des Plugins bleiben alle Daten erhalten (Personen, Gruppen, Option „auhof_raumplan“).

== Changelog ==
= 1.1.0 =
* Terminvorschau [auhof_termine].

= 1.0.0 =
* Erste Version.
