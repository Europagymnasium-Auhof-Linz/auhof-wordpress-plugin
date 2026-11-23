=== Auhof Schulseiten ===
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
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
Beitragsseite „Aktuelles“: Umschalter „Neueste | Beliebteste“ unter der Überschrift. „Beliebteste“ (…/beliebteste/) zeigt dieselben Kacheln, sortiert nach Likes im laufenden Schuljahr (Plugin „WP ULike“ nötig; ohne WP ULike kein Umschalter).

Startseite: [auhof_termine anzahl="5"] zeigt die nächsten Termine der Sintoora-Terminart „Allgemein (Homepage)“ (auch Klassentermine; ohne Ferien, Feiertage und schulfreie bzw. schulautonome Tage). Nutzt die zwischengespeicherten Daten des Plugins „Sintoora Kalender“; ohne dieses Plugin erscheint nur der Link zum Schulkalender.

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

= 1.9.0 =
* Beitragsseite: Umschalter „Neueste | Beliebteste“; „Beliebteste“ sortiert nach Likes (WP ULike) im laufenden Schuljahr, eigene Adresse …/beliebteste/ mit Seitennummern.

= 1.8.3 =
* Terminvorschau der Startseite: alle Termine der Terminart „Allgemein (Homepage)“ erscheinen, auch die einzelner Klassen; ausgeblendet werden nur Ferien, Feiertage und schulfreie bzw. schulautonome Tage. Gleiche Termine mehrerer Klassen erscheinen einmal.

= 1.8.2 =
* Terminvorschau der Startseite: Ferien, Feiertage und schulfreie Tage werden ausgeblendet (Erkennung am Titel); im Klassenkalender bleiben sie.

= 1.8.1 =
* Updates: neue Adresse des Repositorys (Organisation europagymnasium-auhof-linz); Weiterleitungen von Codeberg werden innerhalb von codeberg.org gefolgt, damit ein späterer Umzug Updates nicht mehr unterbricht.

= 1.8.0 =
* Updates aus Codeberg: WordPress bietet neue Versionen aus dem öffentlichen Repository europagymnasium-auhof-linz/auhof-wordpress-plugin an (neuer Tag vX.Y.Z).

= 1.7.1 =
* Pinnwand: großes Bild steht in breiten Kästchen (z. B. nur eine Info) links neben dem Text statt riesig darüber.

= 1.7.0 =
* Pinnwand: je Info optional ein Bild aus der Mediathek, „klein, links neben dem Text“ oder „groß, über der Überschrift“.

= 1.6.2 =
* Pinnwand: im Text sind Links, fett und kursiv erlaubt (wp_kses); anderes HTML wird weiter entfernt.

= 1.6.1 =
* Menüpunkt „Aktuelle Infos“ heißt jetzt „Pinnwand“ (auch Standard-Überschrift).

= 1.6.0 =
* Neu: „Aktuelle Infos“ – kurze Hinweise über „Aktuelles“ und „Termine“ auf der Startseite ([auhof_infos]), im Backend mit + / − / ↑ / ↓ und „Anzeigen“.

= 1.5.1 =
* Raumplan: ein Knopf „Drucken (PDF)“ statt „Drucken“ und „PDF“; Sonderwege für Handy/Safari entfernt.

= 1.5.0 =
* Raumplan als PDF (A4 quer): wird beim Speichern am Server erzeugt, Knopf „PDF“ neben „Drucken“. Ohne fremde Bibliothek.

= 1.4.12 =
* Raumplan drucken: gedrehte Grafik nur auf Android; Safari wieder normale Druckseite (eine Seite).

= 1.4.10 =
* Raumplan drucken am Handy und in Safari: fertig gedrehte Grafik, hochkant, Plan quer darauf.

= 1.4.9 =
* Raumplan drucken am Handy: wirklich eine Seite (Seitenformat folgt dem Papier), Plan größer.

= 1.4.8 =
* Raumplan drucken am Handy: gedrehter Plan passt auch auf Letter-Papier (Android-Standard) auf eine Seite.

= 1.4.7 =
* Raumplan drucken am Handy und in Safari: Plan liegt bei Hochformat automatisch quer auf dem Blatt.

= 1.4.6 =
* Raumplan: Beschriftung „3. STOCK“ einheitlich wie die übrigen Stockwerke (nicht fett, Großbuchstaben).

= 1.4.5 =
* Raumplan drucken: immer alle Stockwerke auf einer A4-Seite quer, im selben Maßstab, unabhängig von Ansicht und Vergrößerung.

= 1.4.4 =
* Einmalige Datenumstellungen laufen nur noch im Backend durch berechtigte Personen.

= 1.4.3 =
* Raumplan drucken: keine leere Seite mehr am Ende (Querformat, Handy, Seitenränder, Kopf-/Fußzeile).

= 1.4.2 =
* Raumplan drucken: immer eine Seite, ohne leere Seite davor oder danach (auch nach Vergrößern oder am Handy).

= 1.4.1 =
* Raumplan am Handy: mit „−“ bis zur ganzen Planbreite verkleinern; „+“ nur bis zur Ausgangsgröße. Am Computer unverändert.

= 1.4.0 =
* Organigramm: eigene Reihenfolge der Personen je Funktion (Personen → Funktionen → Funktion bearbeiten, oder CSV „Deutsch (1)“). Bisherige Reihenfolge wird beim Update übernommen.

= 1.3.1 =
* Neues Feld „Funktion“ (Kontakt) getrennt von „Fächer“ (Lehrkräfte); Lehrkräfte-Liste alphabetisch ohne Foto/Telefon.
* Doppelte Personen (gleiche E-Mail, Lehrkraft + Verwaltung) werden beim Update zusammengeführt.
* Suche in der Personenliste repariert („Alle Gruppen“) und auf E-Mail, Fächer, Funktion, Klasse erweitert.

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
