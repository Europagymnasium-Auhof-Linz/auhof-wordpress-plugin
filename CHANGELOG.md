# Changelog „Auhof Schulseiten“

## 1.4.10 – 21.11.2026
- **Raumplan drucken in Safari** (Mac-Screenshot: ungedreht, verkleinert):
  - Safari baut die Druckseite nach der Fensterbreite auf, skaliert auf die Papierbreite und wertet `(orientation)` nach dem Fenster aus. CSS-Drehung und Formatvorgaben greifen deshalb nicht.
  - Lösung (`assets/raumplan.js` `druckBild()`, `assets/raumplan.css`): Auf Handys und in Safari wird eine bereits gedrehte Vektorgrafik gedruckt (hochkant, Plan quer darauf, alle Stockwerke im selben Maßstab). Der Browser muss sie nur auf Seitenbreite skalieren.
  - Seitenverhältnis 1,24: passt auf A4 und Letter samt Rand und Kopf-/Fußzeile.
  - Entsteht beim Drucken, also auch über Browsermenü oder Strg+P; markierter Raum und aktuelle Namen sind enthalten.
  - Die CSS-Drehung aus 1.4.7–1.4.9 ist entfernt.
  - Wählt man auf diesen Geräten selbst Querformat, wird die hochkante Grafik kleiner gedruckt. Standard ist hochkant.
- Geprüft (simuliert, Chromium mit den Kennungen von Android, iPhone, Mac Safari und Windows; für iPhone/Safari ohne `@page`):
  - 26 PDFs mit Kopf-/Fußzeile, A4 und Letter, Rand 6–15 mm: je 1 Seite, hoch auf Handy/Safari, quer am Computer.
  - Rechnerische Prüfung der Safari-Skalierung: passt.
  - Bildschirm pixelgleich zu 1.4.9.
  - Nicht getestet: echtes Safari und echte Geräte.

## 1.4.9 – 21.11.2026
- **Raumplan drucken am Handy, weiterhin 2 Seiten** (`assets/raumplan.js`, `assets/raumplan.css`):
  - Ursache (aus dem Screenshot von Brave/Android): Android übernimmt die Vorgabe „A4 quer“ für den Seitenaufbau, druckt aber auf hochkant liegendes Papier und verkleinert die Querseite darauf; deshalb füllte sie nur die obere Hälfte. Die Abfrage „Hochformat“ richtet sich nach dem Papier, also wurde gedreht, und der hohe gedrehte Plan passte nicht in die niedrige Querseite.
  - Die Annahme in 1.4.7/1.4.8, Android ignoriere `@page`, war falsch. Die Simulation hatte `@page` entfernt und den Fehler deshalb nicht gezeigt.
  - Lösung: Auf Handys und in Safari setzt `raumplan.js` `@page { size: auto; margin: 8mm }`. Damit folgt die Seite dem Papier: hochkant wird gedreht, quer gewählt bleibt ungedreht.
  - Die gedrehte Fläche ist jetzt 175 × 245 mm statt 230 mm, der Plan also größer.
- Geprüft:
  - Android-Kennung **mit** aktivem `@page`: Das Fehlerbild von 1.4.8 (2 Querseiten, Schnitt bei 5A/1E/FES) ist nachgestellt. Mit 1.4.9 ergeben A4 und Letter hoch mit Rand 8–20 mm sowie quer gewählt jeweils 1 Seite.
  - iPhone und Mac Safari (ohne `@page`) hoch und quer: je 1 Seite.
  - Windows Chrome unverändert 1 Seite quer.

## 1.4.8 – 21.11.2026
- **Raumplan drucken am Handy, zweite Seite** (`assets/raumplan.css`):
  - Ursache: Die gedrehte Fläche war 265 mm hoch und auf A4 berechnet. Android druckt häufig auf Letter (279 mm). Mit Rand blieben dort rund 263 mm, der untere bzw. rechte Teil des Plans brach auf eine zweite Seite um.
  - Lösung: Fläche 175 × 230 mm. Sie passt auf A4 und Letter hochkant mit bis zu 17 mm Rand seitlich und 24 mm oben/unten; der Plan wird etwas kleiner (rund 83 % statt 96 % der Höhe).
- Geprüft (simuliert mit Android-Kennung, ohne `@page`): A4 und Letter, hoch und quer, Rand 0/8/10/15/20 mm. Alle 20 Kombinationen ergeben 1 Seite, nichts abgeschnitten, Maßstab gleich. In 1.4.7 ergab Letter hoch ab 8 mm Rand 2 Seiten.
- Hinweis: Der Test für 1.4.7 hatte nur A4 geprüft.

## 1.4.7 – 21.11.2026
- **Raumplan drucken am Handy und in Safari** (`assets/raumplan.js`, `assets/raumplan.css`):
  - Diese Browser übernehmen die Vorgabe „A4 quer“ (`@page`) nicht und drucken hochkant. Eine Webseite kann das Querformat dort nicht erzwingen.
  - Neu: Auf Android und iOS (alle Browser, auch Brave) sowie in Safari am Mac wird die Druckseite bei Hochformat um 90° gedreht; der Plan liegt quer auf dem Blatt und nutzt es ganz aus.
  - Wählt man im Druckdialog selbst Querformat, bleibt der Plan ungedreht. Am Computer mit Chrome, Edge oder Firefox ändert sich nichts.
  - Erkennung: `navigator.userAgentData.mobile`, Kennung Android/iPhone/iPad (auch iPad mit Desktop-Kennung) oder Safari ohne Chrome/Firefox/Edge.
- Geprüft (simuliert in Chromium mit den Kennungen der Browser, `@page` für Handy/Safari entfernt):
  - Brave Android hoch mit 8 und 15 mm Rand sowie selbst quer, iPhone Safari hoch, Mac Safari hoch und quer, Windows Chrome und Firefox.
  - Alle ergeben genau 1 Seite; gedreht nur bei Hochformat, nichts abgeschnitten.
  - Nicht getestet: echte Geräte bzw. echtes Safari (WebKit stand nicht zur Verfügung).

## 1.4.6 – 21.11.2026
- **Raumplan, Stockwerksnamen einheitlich** (`data/raumplan.json`, Eintrag in `static`):
  - „3. Stock“ war aus der Vorlage fett und in normaler Schreibweise übernommen, die übrigen nicht fett in Großbuchstaben. Jetzt heißt er „3. STOCK“, nicht fett, gleiche Größe 13,45.
  - Die Position bleibt (rechts neben 8C, weil links oben Räume liegen), kein Raum wird überdeckt.
  - Wirkt am Bildschirm und im Druck. Raum-IDs und Geometrie sind unverändert.

## 1.4.5 – 21.11.2026
- **Raumplan drucken: ganzer Plan auf A4 quer** (`assets/raumplan.js`, `assets/raumplan.css`):
  - Beim Drucken entsteht aus dem aktuellen Plan eine eigene Druckseite mit allen vier Stockwerken, im selben Maßstab und so groß wie möglich, auf A4 quer (`@page`, Rand 8 mm). Der Titel lautet „Raumplan · Schuljahr …“.
  - Ansicht („Alle“ oder ein Stockwerk), Vergrößerung und Gerät spielen keine Rolle. Umbenannte Räume und ein markierter Raum erscheinen im Druck, weil die Druckseite erst beim Drucken gebaut wird.
  - Vorher hatte jedes Stockwerk im Raster eine eigene Höhengrenze und wurde daher unterschiedlich stark verkleinert; große Teile der Seite blieben leer.
  - Bei schmalerem Papier oder größeren Rändern schrumpfen alle Stockwerke gemeinsam.
  - Die Druckregeln aus 1.4.2/1.4.3 (Höhengrenzen für den Bildschirmplan) sind ersetzt.
- Geprüft:
  - 160 PDFs: Computer 1920/1280/820 px und Handy, alle Ansichten, normal/vergrößert/verkleinert, Standard, mit Kopf-/Fußzeile, 20 mm Rand, Letter. Alle ergeben genau 1 Seite quer, ohne Skriptfehler, mit identischer Druckgröße.
  - Fünf Papier-/Randvarianten: nichts abgeschnitten, Maßstab überall gleich.
  - Bildschirmansicht pixelgleich wie vorher.
  - Beim Entwickeln verkleinerte eine `(orientation: portrait)`-Regel den Druck auch quer auf 68 %. Das zeigte erst das angesehene PDF; die Regel wurde entfernt.

## 1.4.4 – 21.11.2026
- **Sicherheit, Datenumstellungen:**
  - `auhof_personen_daten_aktualisieren()` (Datenstand 2) und `auhof_organigramm_reihenfolge_uebernehmen()` (Datenstand 3) liefen bisher auf `init`, also auch beim ersten Seitenaufruf eines beliebigen Besuchers nach dem Update.
  - Sie hängen jetzt an `admin_init` und prüfen `auhof_schulseiten_recht()`. `admin_init` läuft auch bei `admin-ajax.php`/`admin-post.php` ohne Anmeldung, daher die zusätzliche Rechteprüfung.
  - Ein zweiter Durchlauf ändert ohnehin nichts.
- **Hinweis für den Server:** `readme.txt` der Plugins war öffentlich lesbar und verriet die Version. Gesperrt wird sie über eine `.htaccess`-Regel des Servers (readme/changelog/license, auch in Plugin-Ordnern), nicht im Plugin.
- Geprüft: PHP-Syntax, PHPCS 0 Fehler, PHP 7.4+ kompatibel, Semgrep 0 Befunde.

## 1.4.3 – 21.11.2026
- **Raumplan drucken, leere Seite am Ende** (`assets/raumplan.css`, Druckregeln am Ende):
  - Ursache: Das Theme gibt dem Inhaltsbereich 80 px Abstand oben und unten und der Seite eine Mindesthöhe von einer Seitenhöhe. Mit Plan, Überschrift und diesen Abständen war der Inhalt etwas höher als eine Seite, besonders im Querformat und am Handy.
  - Lösung: Beim Drucken verlieren alle Bereiche, die den Plan enthalten, diese Abstände und die Mindesthöhe. Die Planhöhe hat Reserve für Ränder bis 2 cm und Kopf-/Fußzeile: hoch 20 cm (Raster 9 cm), quer 13 cm (Raster 6 cm).
  - Geprüft: 160 Fälle (Computer/Handy, alle Ansichten, normal/vergrößert/verkleinert, A4/Letter, hoch/quer, Rand 1 cm sowie 2 cm mit Kopf-/Fußzeile). Vorher ergaben 41 davon 2 Seiten, jetzt alle 1 Seite. Die Darstellung am Bildschirm ist unverändert (pixelgleich).
  - Der Test in 1.4.2 lief ohne Seitenränder und hat den Fehler deshalb nicht gezeigt.

## 1.4.2 – 21.11.2026
- **Raumplan drucken** (`assets/raumplan.css`, Druckregeln am Ende):
  - Ursache: Im Druck behielt der Plan seine Bildschirmgröße (nach „+“ bis 300 %, „Alle“ als hoher Gesamtplan). Er war damit größer als eine Seite und ließ sich nicht teilen. Seite 1 enthielt dann nur die Überschrift, Seite 2 den Plan, Seite 3 den Überhang.
  - Lösung: Im Druck ist der Plan immer seitenbreit und höchstens 21 cm (Hochformat) bzw. 14 cm (Querformat) hoch, im Raster je Stockwerk 9,5/6,5 cm. Der Block wird nicht getrennt.
  - Geprüft: 80 Kombinationen (Bildschirm 1920/390 px, alle Ansichten, normal/vergrößert/verkleinert, A4 und Letter, hoch und quer), jeweils genau 1 Seite. Die Darstellung am Bildschirm ist unverändert.

## 1.4.1 – 21.11.2026
- **Raumplan am Handy:**
  - „−“ verkleinert die Stockwerksansicht in Stufen (75 %, 50 %) bis „ganzer Plan sichtbar“.
  - „+“ geht nur bis zur Ausgangsgröße zurück; am Handy wäre größer zu groß.
  - Am Computer und Tablet ist alles unverändert (Vergrößern bis 300 %).
  - Die gewählte Größe bleibt beim Wechsel der Ansicht erhalten.
  - Datei `assets/raumplan.js`.

## 1.4.0 – 21.11.2026
- **Reihenfolge je Funktion im Organigramm:**
  - Personen → Funktionen → Funktion bearbeiten zeigt alle Personen dieser Funktion mit einem Zahlenfeld. Eine kleinere Zahl steht weiter vorn; Personen ohne Zahl folgen alphabetisch.
  - CSV-Spalte „Funktionen“ mit Zahl in Klammern, z. B. „Administration (2), QMS (3)“.
  - Gespeichert in `_auhof_funktion_reihenfolge` bei der Person. Gespeichert wird nur bei gültiger Nonce, mit Bearbeitungsrecht und nur für Personen, die die Funktion haben.
  - Das Feld „Reihenfolge“ der Person wirkt nur noch auf der Kontakt-Seite.
  - Beim Update wird die bisherige Reihenfolge je Funktion als 1, 2, 3 … festgeschrieben (Datenstand 3); das Organigramm sieht danach gleich aus.
- Klassenvorstände je Klasse jetzt rein alphabetisch.
- CSV-Test bricht ab, bevor er etwas ändert, wenn Export → Import nicht verlustfrei ist.

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
