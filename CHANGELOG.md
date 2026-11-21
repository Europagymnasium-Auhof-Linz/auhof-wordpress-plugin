# Changelog „Auhof Schulseiten“

## 1.3.1 – 21.11.2026
- **Neues Feld „Funktion“** (`_auhof_funktion`) getrennt von „Fächer“ (`_auhof_zusatz`); auch als CSV-Spalte (alte Spalte „Fächer / Funktion“ wird weiter als Fächer erkannt).
- **Ansichten** `[auhof_personen ansicht="lehrkraft|kontakt"]`: Lehrkräfte alphabetisch mit Fächern, ohne Foto und Telefon; Kontakt mit Funktion, Foto und Telefon.
- **Doppelte Personen zusammengeführt:** Gleiche E-Mail in „Lehrkräfte“ und einer Verwaltungsgruppe → der Verwaltungseintrag behält Foto, Telefon und Funktion, übernimmt Fächer, Gruppen, Funktionen und Klasse; der Lehrkraft-Eintrag kommt in den Papierkorb. Danach wandert bei Verwaltungseinträgen der alte Text von „Fächer“ nach „Funktion“.
- **Suche in der Personenliste:** „Alle Gruppen“ filterte versehentlich alles weg; die Suche findet jetzt auch E-Mail, Fächer, Funktion und Klasse. Spalte „Fächer / Funktion“ zeigt beide Felder.

## 1.3.0 – 21.11.2026
- **Organigramm** `[auhof_organigramm]` (`includes/organigramm.php`, `organigramm-admin.php`, `assets/organigramm.css/js`):
  - Gepflegt wird bei jeder Person: Feld „Klassenvorstand (Klasse)“ und Kasten „Funktionen“ (Taxonomie `auhof_funktion`, hierarchisch).
  - Die oberste Ebene bestimmt die Darstellung: `leitung` (erste Funktion als Kopf), `zweige` (Koordination; Klassen nach Buchstaben im Feld „Klassen“, z. B. A-E), `fach-args` (Liste), sonst Kästen; Unterfunktionen erscheinen als Rollen.
  - Gestaltung und Suche aus der Vorlage der Schule; Farben LISA blau, Kepler rot, eLLA violett, Leitung dunkelgrau. Nachnamen in normaler Schreibweise. Schuljahr automatisch ab September.
  - Grundstruktur (46 Funktionen) wird angelegt, wenn noch keine existiert.
- **CSV-Export/-Import der Personen** (`includes/personen-csv.php`, Personen → Import/Export):
  - Excel-tauglich (Strichpunkt, UTF-8 mit BOM); Windows-1252 und Komma werden erkannt. Excel-Formeln werden beim Export mit `'` geschützt.
  - Personen werden über ID, sonst E-Mail, sonst Vor- und Nachname erkannt.
  - Vorschau ohne Änderung; „Nicht in der Datei“ kommt nur nach Anhaken in den Papierkorb (nur Einträge aus dieser Liste).
  - Fehlende Spalten bleiben unverändert, leere Zellen löschen den Wert.
- `auhof_personen_wert_bereinigen()` als gemeinsame Bereinigung für Maske und Import.

## 1.2.1 – 21.11.2026
- **Bilder** (`includes/bilder.php`, neu):
  - Kamera- und GPS-Metadaten (EXIF/XMP/IPTC) werden verlustfrei aus verkleinerten Fassungen entfernt; Farbprofil und Pixel bleiben gleich. Beispiel: Vorschaubild 62,5 → 6,6 KB.
  - Neue JPEG-Zwischengrößen entstehen als WebP.
  - Der Bestand wird im Hintergrund bereinigt; fehlende Zwischengrößen der Beitragsbilder (1536 px) werden nachgeneriert.
  - Grund: In 158 von 1878 geprüften Fassungen war eine GPS-Position gespeichert, und die Übersicht „Aktuelles“ lud auf Handys Originale.
- **Raumplan am Handy:** Die Stockwerksansicht ist mindestens 1000 px breit und wischbar. Neu sind die Knöpfe „−“ und „+“ (100/150/200/300 %); bei Vergrößerung wird die Rasteransicht verlassen.
- **Plugin-Kopf:** „Requires at least: 6.3“ (Skript-Option `strategy`).

## 1.2.0 – 21.11.2026
- **Diashow** aus dem Theme übernommen:
  - Backend-Seite „Diashow“ zum Wählen und Ordnen der Bilder, mit Ausschnitt je Bild, Dauer, Breite, Höhe, Format am Handy und Bildunterschrift.
  - Zugeschnittene WebP-Fassungen für Computer und Handy, Ausgabe über `<picture>`.
  - Bilder werden nur geladen bzw. gewechselt, solange die Diashow sichtbar ist; der Datensparmodus wird beachtet.
  - Ergebnis: Desktop nach 20 s 3,0 → 1,5 MB, Handy-LCP 1,17 → 0,89 s.

## 1.1.0 – 21.11.2026
- **Terminvorschau** `[auhof_termine]`: die nächsten schulweiten Termine aus dem Sintoora-Plugin (Terminart „Allgemein (Homepage)“, ohne Klassentermine).

## 1.0.0 – 21.11.2026
- **Erste Version:**
  - Personen (Lehrkräfte, Verwaltung) mit Gruppen, Fotos, A–Z-Leiste;
  - Raumplan mit pflegbaren Raumnamen und Backend-Vorschau.
- **Übernahme:** 149 Lehrkräfte und 8 Verwaltungseinträge aus den bisherigen Seiten.
- **Rechte:** `edit_others_pages` statt `edit_pages`, weil Autoren auf dieser Website Seitenrechte haben.
