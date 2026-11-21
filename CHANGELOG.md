# Changelog „Auhof Schulseiten“

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
