=== Auhof Schulseiten ===
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
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

= Diashow =
Backend → Diashow: Bilder wählen und ordnen (Ausschnitt oben/Mitte/unten), Anzeigedauer, Breite, Höhe am Computer, Format am Handy, Bildunterschrift. Beim Speichern entstehen zugeschnittene WebP-Fassungen in uploads/auhof-diashow/ (fehlen sie, gelten die normalen Bildgrößen). Einbinden: [auhof_diashow]

= Bilder =
Verkleinerte Fassungen (Zwischengrößen, „-scaled“) werden ohne Kamera- und GPS-Daten gespeichert (verlustfrei, Farbprofil bleibt); der Bestand wird nach dem Aktivieren bzw. einem Update schrittweise im Hintergrund bereinigt. Neue Zwischengrößen von JPEG-Fotos entstehen als WebP. Originale bleiben unverändert.

= Organigramm =
Bei jeder Person: Feld „Klassenvorstand (Klasse)“ und Kasten „Funktionen“ (Personen → Funktionen). [auhof_organigramm] erzeugt daraus das Organigramm (Leitung, Zweige mit Klassen, Fach-ARGEs, weitere Funktionen). Zweige ordnen Klassen über das Feld „Klassen“ (z. B. A-E) zu. Schuljahr automatisch (ab September).

= Import/Export =
Personen → Import/Export: CSV herunterladen (zugleich Vorlage), bearbeiten, hochladen. Vorschau zeigt neue, geänderte und fehlende Personen; fehlende nur nach Anhaken in den Papierkorb.

= Technik =
Nur WordPress-Standardfunktionen, keine REST-/AJAX-Endpunkte, keine externen Abrufe.
Personen und Gruppen sind nicht öffentlich abrufbar, nur über die Shortcodes.
Berechtigung: Recht „edit_others_pages“ (Redakteur, Administrator); änderbar mit dem Filter auhof_schulseiten_recht.
Beim Löschen des Plugins bleiben alle Daten erhalten (Personen, Gruppen, Option „auhof_raumplan“).

== Changelog ==
= 1.3.0 =
* Organigramm aus Funktionen und Klassenvorstand der Personen.
* CSV-Export und -Import der Personen mit Vorschau.

= 1.2.1 =
* Bilder: Metadaten (EXIF/GPS) aus verkleinerten Fassungen entfernen, neue Zwischengrößen als WebP.
* Raumplan am Handy: breitere, wischbare Stockwerksansicht und Zoom-Knöpfe.
* Requires at least 6.3 (Skript-Option „strategy“).

= 1.2.0 =
* Diashow mit Einstellungsseite und optimierten Bildfassungen (aus dem Theme übernommen).

= 1.1.0 =
* Terminvorschau [auhof_termine].

= 1.0.0 =
* Erste Version.
