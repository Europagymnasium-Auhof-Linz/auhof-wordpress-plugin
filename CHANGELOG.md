# Changelog „Auhof Schulseiten“

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
