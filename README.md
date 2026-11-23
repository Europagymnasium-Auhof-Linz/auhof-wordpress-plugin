# Auhof Schulseiten

WordPress-Plugin der Website des Europagymnasiums Auhof (Linz): Personen, Organigramm, Raumplan mit PDF, Pinnwand, Diashow und Terminvorschau der Startseite.

- **Lizenz:** GPL-2.0-or-later (siehe `LICENSE`)
- **Voraussetzungen:** WordPress 6.3+, PHP 7.4+. Für die Terminvorschau zusätzlich das Plugin „Sintoora Kalender“ (eigenes Repository).
- **Versionen:** Jede Version ist ein Commit mit Tag (`v1.0.0` … `v1.8.0`), die Änderungen stehen in `CHANGELOG.md`.

## Updates auf den Websites

Ab Version 1.8.0 holt sich WordPress neue Versionen selbst aus diesem Repository (`Update URI` im Plugin-Kopf, Klasse `includes/class-auhof-codeberg-updater.php`):

- **Neue Version veröffentlichen = Tag `vX.Y.Z` pushen.** Spätestens nach 6 Stunden zeigt jede Website unter Plugins „Aktualisierung verfügbar“; sofort mit Dashboard → Aktualisierungen → „Erneut prüfen“.
- Installiert wird beim Klick auf „Jetzt aktualisieren“ – oder von selbst, wenn für das Plugin automatische Updates eingeschaltet sind.
- Ein Tag ist damit eine Veröffentlichung: erst taggen, wenn die Version getestet ist. Vorabversionen (`v2.0.0-beta`) werden nicht angeboten.

## Erstinstallation

Das Repository ist der Plugin-Ordner selbst. Für WordPress braucht es eine ZIP-Datei, in der der Ordner `auhof-schulseiten/` liegt:

```
git archive --format=zip --prefix=auhof-schulseiten/ -o auhof-schulseiten-1.8.0.zip v1.8.0
```

Dann in WordPress unter Plugins → Installieren → Plugin hochladen. `README.md`, `CHANGELOG.md`, `ENTWICKLUNG.md`, `tests/` und die Git-Dateien sind über `.gitattributes` vom Archiv ausgenommen und landen nicht auf dem Webserver. Auch die Download-Schaltfläche von Codeberg liefert so ein Archiv; dort heißt der Ordner aber `auhof-wordpress-plugin` (Name des Repositories) und muss vor dem Hochladen in `auhof-schulseiten` umbenannt werden.

## Tests (`tests/`)

Die Skripte laufen auf einer Testinstallation mit WP-CLI (nie auf der echten Website), z. B.:

```
wp --allow-root --exec='define("WP_ADMIN",true);' eval-file tests/schulseiten-sicherheitstest.php
```

- `schulseiten-sicherheitstest.php`: XSS, CSRF, Rechte, Bereinigung, Sichtbarkeit.
- `infos-test.php`: Pinnwand.
- `raumplan-pdf-test.php`: PDF des Raumplans.
- `schulseiten-csv-test.php`: CSV-Import und -Export. Stellt den Datenstand danach wieder her.
- `raumplan-pdf-prototyp.mjs`: JavaScript-Vorlage des PDF-Schreibers.
- `codeberg-update-test.php`: Updater beider Plugins, mit nachgebildetem Codeberg (keine Verbindung nach außen nötig). Installiert dabei Testversionen; danach den ursprünglichen Plugin-Ordner wieder einspielen.

---

# Plugin „Auhof Schulseiten“

**Aktuelle Version:** 1.8.3. Was sich geändert hat, steht in `CHANGELOG.md`.

## Aufgabe
Mit diesem Plugin pflegt die Schule ohne HTML-Kenntnisse im Backend:
- **Personen:** Lehrkräfte, Schulleitung und Administration, Sekretariat, weitere Kontakte;
- das **Organigramm** (Funktionen und Klassenvorstand bei der Person);
- den **Raumplan**;
- die **Pinnwand** (Aktuelle Infos) auf der Startseite (z. B. Infoabend, Neuanmeldungen);
- die **Diashow** der Startseite.

Zusätzlich zeigt es eine **Terminvorschau** aus Sintoora und hält **Bilder** schlank (ohne Kamera- und GPS-Daten, neue Zwischengrößen als WebP).

## Dateien
| Datei | Inhalt |
|---|---|
| `auhof-schulseiten.php` | Plugin-Kopf, Konstanten (`AUHOF_SCHULSEITEN_VERSION`, `_PFAD`, `_URL`), lädt die Module, legt beim Aktivieren die Standardgruppen an |
| `includes/personen.php` | Beitragstyp `auhof_person`, Taxonomie `auhof_gruppe`, Recht `auhof_schulseiten_recht()`, Shortcode `[auhof_personen]`, Bildgröße `auhof-person` (176×220) |
| `includes/personen-admin.php` | Eingabemaske „Angaben“, Speichern (Nonce, Rechte, Bereinigung), Spalten, Gruppenfilter, Shortcode-Spalte bei den Gruppen |
| `includes/organigramm.php` | Taxonomie `auhof_funktion` (Funktionen), Grundstruktur, Shortcode `[auhof_organigramm]` |
| `includes/organigramm-admin.php` | Felder „Reihenfolge“ und „Klassen“ bei den Funktionen, Personenliste mit Reihenfolge je Funktion, Spalten |
| `includes/personen-csv.php` | Personen → Import/Export: CSV-Export, Import mit Vorschau und Rückfrage vor dem Papierkorb |
| `includes/raumplan.php` | Option `auhof_raumplan` (Schuljahr, geänderte Raumnamen), Shortcode `[auhof_raumplan]`, HTML-Gerüst |
| `includes/raumplan-pdf.php` | Raumplan als PDF (A4 quer) ohne fremde Bibliothek: eigener PDF-Schreiber, Erzeugen beim Speichern/Update, feste Datei `uploads/auhof-raumplan/raumplan.pdf`, Knopf „PDF“ |
| `includes/raumplan-admin.php` | Backend-Seite „Raumplan“ mit Vorschau (Einstellungs-API) |
| `includes/infos.php` | „Aktuelle Infos“: Option `auhof_infos`, Bereinigung eines Eintrags, Shortcode `[auhof_infos]`, Seiten-Cache der Startseite leeren nach dem Speichern |
| `includes/infos-admin.php` | Backend-Seite „Pinnwand“ (Einstellungs-API, + / − / ↑ / ↓, „Anzeigen“), höchstens 12 Einträge |
| `includes/class-auhof-codeberg-updater.php` | Updates aus Codeberg: Das Plugin trägt im Kopf `Update URI: https://codeberg.org/europagymnasium-auhof-linz/…`. WordPress fragt deshalb nie wordpress.org (Schutz vor gleichnamigen Fremd-Plugins), sondern ruft `update_plugins_codeberg.org` auf. Die Updater-Klasse liest die Tags des öffentlichen Repositories (`vX.Y.Z`, höchste gültige Version) und bietet das Tag-Archiv als Paket an. Beim Installieren benennt sie den Ordner (Repository-Name) in den Plugin-Ordner um, prüft vorher, dass die Hauptdatei darin liegt, und aktiviert das Plugin bei einem Abbruch wieder so wie vorher. Zwischenspeicher 6 h (nach Fehlern 1 h), „Erneut prüfen“ unter Dashboard → Aktualisierungen leert ihn. **Neue Version veröffentlichen = Tag auf Codeberg** – danach bietet jede Website das Update an (bzw. installiert es, wenn automatische Updates für das Plugin eingeschaltet sind). Die Klasse steckt in beiden Plugins (eigener Name), Änderungen in beiden nachziehen. Test: `tests/codeberg-update-test.php` (bildet Codeberg nach, 46 Prüfungen). |
| `includes/termine.php` | Shortcode `[auhof_termine anzahl="5"]`: nächste schulweite Termine aus dem Sintoora-Plugin |
| `includes/diashow.php` | Option `auhof_diashow`, Shortcode `[auhof_diashow]`, optimierte WebP-Fassungen in `uploads/auhof-diashow/`, WP-Cron `auhof_diashow_erzeugen` |
| `includes/diashow-admin.php` | Backend-Seite „Diashow“ (Mediathek-Auswahl, Reihenfolge, Ausschnitt, Dauer, Breite, Höhe, Format) |
| `includes/bilder.php` | EXIF/XMP/IPTC verlustfrei aus verkleinerten Fassungen entfernen; JPEG-Zwischengrößen als WebP; Hintergrundaufgabe `auhof_bilder_bestand` (Bestand bereinigen, fehlende Zwischengrößen der Beitragsbilder erzeugen) |
| `assets/organigramm.css`, `organigramm.js` | Organigramm (Gestaltung und Suche aus der Vorlage der Schule) |
| `assets/personen.css`, `termine.css` | Darstellung Personen bzw. Terminvorschau |
| `assets/raumplan.js`, `raumplan.css`, `raumplan-admin.js` | interaktiver Raumplan (SVG), Suche, Ansichten, Vergrößerung, Verbindung zur Backend-Vorschau |
| `assets/diashow.js`, `diashow.css`, `diashow-admin.js` | Karussell nach WAI-ARIA, Backend-Auswahl |
| `assets/infos.css`, `infos-admin.js` | Karten der Aktuellen Infos; Backend-Liste (Vorlage `__nr__` wird beim „+“ kopiert und neu nummeriert) |
| `assets/admin.css` | Backend-Gestaltung |
| `data/raumplan.json` | Gebäudegeometrie (Flächen, Linien, Räume mit fester `id`, Ansichten je Stockwerk) |
| `readme.txt` | Kurzbeschreibung und Changelog im WordPress-Format |

## Gespeicherte Daten
| Was | Wo |
|---|---|
| Personen | Beiträge vom Typ `auhof_person`; Felder als Meta `_auhof_titel_vor`, `_auhof_vorname`, `_auhof_nachname`, `_auhof_titel_nach`, `_auhof_zusatz` (Fächer), `_auhof_funktion` (Funktion für Kontakt), `_auhof_klasse`, `_auhof_email`, `_auhof_telefon`; Foto = Beitragsbild; Reihenfolge = `menu_order` |
| Funktionen | Taxonomie `auhof_funktion` (hierarchisch); Term-Meta `auhof_reihenfolge`, `auhof_klassen` (bei Zweigen); Klassenvorstand = Meta `_auhof_klasse` der Person; Reihenfolge je Funktion = Meta `_auhof_funktion_reihenfolge` der Person (`{ Funktions-ID: Zahl }`) |
| Gruppen | Taxonomie `auhof_gruppe` (Slugs `lehrkraefte`, `schulleitung`, `sekretariat`, `weitere-kontakte`) |
| Raumplan | Option `auhof_raumplan` = `{ jahr, namen: { <Raum-ID>: <Name> } }` (nur geänderte Namen) |
| Aktuelle Infos | Option `auhof_infos` = `{ ueberschrift, eintraege: [ {anzeigen, titel, text, link, bild, bildart} ] }` (`bild` = Anhang-ID, `bildart` = `klein`|`gross`) (Reihenfolge = Anzeige) |
| Diashow | Option `auhof_diashow` = `{ bilder: [ {id, lage} ], sekunden, breite, hoehe, handy, beschriftung }`; Stand der Fassungen in `auhof_diashow_stand` |
| Terminvorschau | Transient `auhof_termine_vorschau` (15 min bzw. bis Tageswechsel) |
| Bildbereinigung | Optionen `auhof_bilder_stand`, `auhof_bilder_groessen`, `auhof_bilder_version` |

Beim Löschen des Plugins bleiben alle Daten erhalten (kein `uninstall.php`).

## Shortcodes (auf den Seiten eingebunden)
| Seite | Shortcode |
|---|---|
| Startseite | `[auhof_diashow]`, `[auhof_infos]` (in der grauen Gruppe vor den Spalten „Aktuelles“/„Termine“), `[auhof_termine anzahl="5"]` |
| Lehrkräfte | `[auhof_personen gruppe="lehrkraefte" az="ja"]` |
| Kontakt | `[auhof_personen gruppe="schulleitung"]`, `…="sekretariat"`, `…="weitere-kontakte"` |

`ansicht="lehrkraft"` (Standard bei `gruppe="lehrkraefte"`): Fächer, E-Mail, kein Foto, kein Telefon, alphabetisch. `ansicht="kontakt"` (sonst): Funktion (ersatzweise Fächer), Foto, Telefon, Reihenfolge.
| Raumplan | `[auhof_raumplan]` |
| Organigramm | `[auhof_organigramm]` |

## Wichtige Festlegungen (beim Ändern beibehalten)
- **Rechte:** Alles hängt an `auhof_schulseiten_recht()` (Standard `edit_others_pages`). Der Beitragstyp bildet alle Grundrechte darauf ab (`auhof_schulseiten_rechte_fuer_beitragstyp()`).
- **Nicht öffentlich:** Personen und Gruppen haben keine Einzelseiten, keine Archive, keine REST-Route und erscheinen nicht in der Sitemap. Ausgabe nur über Shortcodes.
- **Raum-IDs** in `data/raumplan.json` sind die Indizes der Räume und dürfen sich nicht verschieben. Neue Räume nur **anhängen**, sonst passen die gespeicherten Namen nicht mehr.
- **Raumplan-Größe (−/+):** Die Stufen hängen vom Platz ab. Ist der Plan breiter als der Bereich (Handy: Stockwerk mind. 1000 px), gibt es nur Verkleinerungen: 75 %, 50 %, „ganzer Plan sichtbar“. Passt er, gibt es nur Vergrößerungen: 150/200/300 %. Die gewählte Größe (`wunsch`) bleibt beim Wechsel der Ansicht erhalten.
- **Aktuelle Infos:** Ohne angehakte Info gibt `[auhof_infos]` nichts aus (auch keine Überschrift). Deshalb steht der Shortcode **in** der vorhandenen grauen Gruppe und nicht in einer eigenen – sonst bliebe deren Innenabstand als Leerraum stehen. Text mit wenig erlaubtem HTML: `auhof_infos_erlaubt()` = `a[href]`, `strong`, `em`, `b`, `i`, über `wp_kses` beim Speichern **und** bei der Ausgabe, Links nur http/https/mailto/tel, höchstens 1000 Zeichen, offene Tags werden geschlossen; Zeilenumbrüche als `<br>`, Links nur http/https/mailto/tel. Nach dem Speichern wird der WP-Fastest-Cache der Startseite geleert (`wpfc_clear_post_cache_by_id`, `wpfc_clear_all_cache`; ohne das Plugin wirkungslos). Bilder: je Info ein Bild aus der Mediathek (nur echte Bilddateien, `wp_attachment_is_image`; SVG daher nicht), zwei feste Darstellungen statt Prozent (`auhof_infos_bildarten()`): „klein“ 96 px links (72 px am Handy, `object-fit: contain` für Logos), „groß“ über der Überschrift über die ganze Kartenbreite, 16:9 zugeschnitten – in breiten Kästchen (ab 560 px, z. B. nur eine Info) links neben dem Text, höchstens 40 % bzw. 440 px. Umschaltung per Container-Abfrage: `li.auhof-info` ist Container (`container-type: inline-size`), gestaltet wird die innere Hülle `.auhof-info__karte` (ein Element kann seine eigene Breite nicht abfragen). Ausgabe mit `wp_get_attachment_image` (srcset, `loading="lazy"`, Alternativtext aus der Mediathek); fehlt er, zeigt das Backend einen Hinweis. Test: `tests/infos-test.php`.
- **Raumplan-PDF:**
  - `raumplan-pdf.php` zeichnet dieselbe Seite wie die Druckseite in `raumplan.js` (Ausschnitte, Anordnung, Schrifteinpassung). Änderungen dort hier nachziehen.
  - Referenz ist `tests/raumplan-pdf-prototyp.mjs` (gleicher Ablauf in JavaScript, Ergebnis soll byte-gleich sein); Test: `tests/raumplan-pdf-test.php`.
  - Erzeugt wird nur beim Speichern der Einstellung und im Backend durch berechtigte Personen (nach Update oder wenn die Datei fehlt), nie durch Besucher. Stand in Option `auhof_raumplan_pdf_stand` (`Version|md5`).
  - Keine fremden Eingaben außer Raumnamen und Schuljahr (bereinigt, im PDF maskiert); keine Skripte, Links oder Formulare im PDF.
  - Grund: Safari und Handys lassen sich beim Drucken der Webseite nicht verlässlich auf Querformat bringen; ein PDF bringt sein Format mit.
- **Raumplan drucken:**
  - Der einzige Knopf ist „Drucken (PDF)“ und öffnet das fertige PDF. Einen Knopf für `window.print()` gibt es nicht mehr.
  - Für Strg+P bzw. das Browsermenü baut `raumplan.js` bei `beforeprint` weiterhin eine Druckseite `.rp-druck` (alle Stockwerke, gleicher Maßstab, `@page` A4 quer). Das funktioniert in Chrome, Edge und Firefox am Computer; in Safari und am Handy kommt sie verkleinert im Hochformat.
  - Sonderwege für Handy und Safari (gedrehte Grafik, `size: auto`, `position: fixed`; Versionen 1.4.7–1.4.12) sind entfernt: In echtem Safari ließ sich keiner verlässlich machen. Nicht wieder einbauen – dafür gibt es das PDF.
  - Keine Abfrage `(orientation: portrait)` für den Druck verwenden: Chrome wertet sie gegen das voreingestellte Hochformat aus.
- **Diashow:** Die erste Folie lädt sofort (`fetchpriority=high`), weitere erst 1,5 s nach dem Laden der Seite bzw. kurz vor dem Erscheinen. Es läuft nur, solange sie sichtbar ist. Pause-Knopf, kein Wechsel bei „Bewegung reduzieren“, inaktive Folien sind `inert` (WCAG 2.2.2).
- **Terminvorschau:** Sie zeigt nur die Sintoora-Terminart „Allgemein (Homepage)“ ohne Klassenzuordnung. Titel mit einzelnen Klassen („Kl. 1A“) werden ausgeblendet. Ohne Sintoora-Plugin erscheint nur der Kalender-Link.
- **Organigramm:** Die Slugs der obersten Funktionsebene (`leitung`, `zweige`, `fach-args`) steuern die Darstellung, nicht umbenennen (Namen dürfen geändert werden). Zweig-Slugs `kepler`, `ella`, `lisa`, `tabe` bestimmen die Farben (`.eg-branch--<slug>`).
- **Einmalige Datenumstellungen** (Option `auhof_personen_datenstand`):
  - Sie hängen an `admin_init` und laufen nur, wenn eine Person mit `auhof_schulseiten_recht()` das Backend öffnet, nie durch Besucher.
  - Neue Schritte mit höherer Nummer anhängen und genauso absichern.
  - Nach einem Update einmal ins Backend gehen.
- **Eine Person = ein Eintrag:** Wer Lehrkraft und Verwaltung ist, hat beide Gruppen. Die Datenaktualisierung `auhof_personen_daten_aktualisieren()` (Option `auhof_personen_datenstand`) hat 1.3.1 Doppeleinträge mit gleicher E-Mail zusammengeführt; neue Schritte dort mit höherer Nummer anhängen.
- **Reihenfolge im Organigramm:** je Funktion über `_auhof_funktion_reihenfolge`; nummerierte Personen zuerst, dann alphabetisch. Das Feld „Reihenfolge“ (`menu_order`) gilt nur für die Kontakt-Seite. In der CSV steht die Zahl in Klammern hinter der Funktion (`QMS (3)`); eine Funktion ohne Klammer löscht die Zahl. Datenstand 3 (`auhof_organigramm_reihenfolge_uebernehmen()`) hat beim Update auf 1.4.0 die bisherige Reihenfolge als 1, 2, 3 … übernommen.
- **CSV-Import:** Löschen nur in den Papierkorb und nur für Einträge der Vorschau-Liste „Nicht in der Datei“. Datei wird nicht gespeichert, nur gelesen; Plan liegt 30 min als Transient je Benutzer.
- **Bilder:** Originale werden nie verändert. Metadaten werden nur aus von WordPress erzeugten Fassungen entfernt (Zwischengrößen, `-scaled`, wenn `original_image` gesetzt ist).

## Ändern und testen
Ablauf, Tests und Paketbau stehen in `ENTWICKLUNG.md`. Spezielle Tests für dieses Plugin:
- Browser-Tests (Playwright, nicht im Repository): Raumplan (Suche, Ansichten, Direktlinks, eingeschleuster Code), Lehrkräfte, Kontakt, Diashow und deren Ladezeit;
- `tests/schulseiten-csv-test.php`: Export/Import-Rundlauf, Änderungen, Papierkorb, Schadcode, Excel-Formeln, Kodierungen – stellt den Datenstand wieder her;
- `tests/schulseiten-sicherheitstest.php` auf der Testinstallation (XSS, CSRF, Rechte, Bereinigung, Sichtbarkeit). Bei neuen Feldern oder Formularen den Test ergänzen.
