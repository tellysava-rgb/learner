# Changelog

Alle relevanten Änderungen werden hier dokumentiert.
Format: `MAJOR.MINOR.PATCH` — siehe `config.php` für die aktuelle Version.

---

## [2.6.2] - 2026-07-22

### Behoben
- `edit.php`: Klick auf "Bearbeiten" oder "Abbrechen" bei einem Eintrag weiter unten in der Liste liess die Seite an den Anfang springen — Scroll-Position bleibt jetzt erhalten (nutzt denselben Mechanismus wie bereits bei Speichern/Löschen).

### Verbessert
- `drill.php`: Veraltete Prüfung auf ein altes `$_SESSION['drill']`-Format entfernt — nicht mehr relevant, da immer nur eine einzige aktuelle Version im Einsatz ist.

---

## [2.6.1] - 2026-07-21

### Behoben
- Lautschrift-Konvention für nicht-rhotische Dialekte (`en-GB` u.ä.) präzisiert: "r" nach Vokal vor Konsonant/am Wortende wird nicht mehr mitgeschrieben (z.B. "thunder" → "THUN-duh" statt "THUN-der"), "r" bleibt nur vor einem folgenden Vokal (Silbenanfang oder verbindendes R). Betraf bisher generierte Lautschrift, die zu amerikanisch/rhotisch klang. Regel in den MCP-Agent-Anweisungen ergänzt, damit künftig generierte Lautschrift konsistent ist.

---

## [2.6.0] - 2026-07-21

### Neu
- **MCP: bestehende Karten lesen/ändern.** Zwei neue Tools ergänzen `add_cards`: `list_cards(list_id)` liest alle Karten einer Liste (inkl. `card_id` und `phonetik_b`), `update_card(card_id, ...)` ändert gezielt einzelne Felder einer bestehenden Karte, ohne die übrigen anzutasten. Ermöglicht Agenten (z.B. bei Wartungsarbeiten wie Rechtschreib-Korrekturen oder nachträglichem Ergänzen von Lautschrift) auch bestehende Karten zu bearbeiten, nicht nur neue anzulegen. Agent muss dem User vor jeder Änderung alt → neu zeigen und Bestätigung abwarten.

---

## [2.5.3] - 2026-07-21

### Verbessert
- Direktlink-Button in `edit.php`: Icon von Kette (🔗) auf Auge (👁) geändert, passend zur tatsächlichen Funktion (Karte ansehen statt Link kopieren/teilen).

---

## [2.5.2] - 2026-07-21

### Behoben
- Direktlink pro Karte zeigte nur eine markierte Position in der `edit.php`-Tabelle statt die Karte wie im Lernmodus. Jetzt öffnet der Link ein Modal mit der Karte im gleichen Flip-Kartenstil wie Leitner/Drill (inkl. Lautschrift und 🔊-Button auf der Rückseite).

---

## [2.5.1] - 2026-07-21

### Verbessert
- Direktlink pro Karte: statt Link in die Zwischenablage zu kopieren, ruft ein Klick die Karte jetzt direkt auf (einfacher `<a>`-Link statt Clipboard-API + JS).

---

## [2.5.0] - 2026-07-21

### Neu
- **Direktlink pro Karte:** Neuer Kettensymbol-Button ganz vorne in der Aktionsleiste jeder Karte (`edit.php`) kopiert eine URL in die Zwischenablage, die direkt zu dieser Karte springt — unabhängig vom aktuell gewählten Filter (wechselt automatisch auf "Alle"), die Zielkarte wird angesprungen und kurz hervorgehoben.

---

## [2.4.0] - 2026-07-21

### Neu
- **Lautschrift jetzt auch in CSV-Import/-Export und MCP:** CSV-Format um optionale 5. Spalte `phonetic_b` erweitert (rückwärtskompatibel — alte 4-spaltige CSVs funktionieren weiterhin), Vorlage und Import-Erklärung aktualisiert. MCP-Tool `add_cards` unterstützt neu `phonetik_b` mit derselben vereinfachten Lautschrift-Konvention (Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Zeichen) wie die manuelle Erfassung — Agent befüllt es nur bei Listen mit gesetztem `speech_lang_b`.

---

## [2.3.0] - 2026-07-21

### Neu
- **Lautschrift pro Karte:** Zusätzlich zur Audio-Wiedergabe kann pro Karte manuell eine Lautschrift (`phonetic_b`) erfasst werden — Eingabefeld erscheint in `edit.php` nur, wenn die Liste einen Aussprache-Sprachcode hat. Anzeige unter dem Begriff in Sprache B, sowohl in der Kartenübersicht als auch auf Leitner- und Drill-Karten.

### Verbessert
- `lists.php`: Button "Umbenennen" heisst neu "Bearbeiten" (passt besser, da dort auch Sprachen und Aussprache-Code geändert werden).
- `lists.php`: Eingabefeld für den Aussprache-Sprachcode auf realistische Breite verkleinert.
- `edit.php`: Container-Breite an home.php/lists.php angeglichen (kein eigenes `max-width` mehr), Beschreibungsfelder A/B sind jetzt mehrzeilige Textareas statt einzeiliger Inputs.

### Behoben
- Audio-Wiedergabe (🔊-Button): `utterance.lang` allein wurde von manchen Browsern/Geräten ignoriert, wodurch trotz z.B. `en-GB` die Standardstimme des Systems (teils Deutsch) erklang. Passende Stimme wird jetzt explizit über `speechSynthesis.getVoices()` gesucht und gesetzt.

---

## [2.2.0] - 2026-07-21

### Neu
- **Aussprache-Sprachcode & Audio:** Pro Liste kann ein BCP-47-Sprachcode (z.B. `en-GB`) für Sprache B hinterlegt werden — Eingabe mit Autovervollständigung (kuratierte Vorschläge + bereits verwendete Codes), serverseitig gegen ISO-639-1/ISO-3166-1 validiert und automatisch normalisiert (z.B. `EN-gb` → `en-GB`). Auf Leitner- und Drill-Karten erscheint dort, wo Sprache B angezeigt wird, ein 🔊-Button, der den Begriff per Web Speech API vorliest. Kein Button ohne hinterlegten Code. Der Code wird beim Kopieren einer öffentlichen Liste ("Entdecken") automatisch mitübernommen.
- **MCP:** `list_lists` gibt neu `speech_lang_b` zurück; der Agent wird angewiesen, Schreibweise und Wortwahl beim Hinzufügen von Karten an den hinterlegten Dialekt anzupassen (z.B. `en-GB` vs. `en-US`).

---

## [2.1.0] - 2026-07-20

### Neu
- **Liste migrieren:** Auf "Meine Listen" können alle Karten einer eigenen Liste per Button "Migrieren" (zwischen "Umbenennen" und "Löschen") in eine andere eigene Liste verschoben werden. Der komplette Lernfortschritt pro Karte (Leitner-Fach, Fälligkeitsdatum, Drill-Mastery) bleibt erhalten, da er an die Karte selbst hängt. Bei unterschiedlichen Sprachpaaren zwischen Quelle und Ziel erscheint eine Bestätigungswarnung. Duplikate in der Zielliste werden nicht geprüft. Die Quellliste bleibt danach leer bestehen.

### Verbessert
- `lists.php` nutzt jetzt dieselbe Container-Breite wie die Startseite (kein eigenes `max-width` mehr).

---

## [2.0.7] - 2026-07-04

### Behoben
- Toten Code entfernt: `stats.php` — ungenutzte Variable `$filter_list`; `learn.php` — `next_due_date` wurde an zwei Stellen mitgeladen, aber nie gelesen; `edit.php` — `created_at` und `next_due_date` wurden mitgeladen, aber nie gelesen (kein Verhaltensunterschied, nur überflüssige SELECT-Spalten/Variable entfernt).

---

## [2.0.6] - 2026-07-04

### Behoben
- `deploy.php`: veralteter Kommentar entfernt ("NICHT im Git-Repo: in .gitignore eingetragen" — stimmte seit v2.0.3 nicht mehr, die Datei ist seither versioniert).

---

## [2.0.5] - 2026-07-04

### Verbessert
- Reine Versions-Erhöhung ohne inhaltliche Code-Änderung (auf ausdrücklichen Wunsch).

---

## [2.0.4] - 2026-07-03

### Behoben
- MCP-Server: Agent-Anweisungen verschärft — der fremdsprachige Begriff darf nicht mehr in der deutschen Beschreibung auftauchen (beobachteter Fehlerfall: "bounced" in der Beschreibung zu "unzustellbar"). Neue klare Regeln pro Feld: Begriff (Fremdsprache) exakt, bei Verben Grundform, bei unregelmässigen Verben alle drei Formen; Begriff (Deutsch) exakt; Beschreibung (Fremdsprache) Beispielsatz mit dem exakten Begriff; Beschreibung (Deutsch) beschreibt die Bedeutung ohne den fremdsprachigen Begriff zu nennen, vermerkt ggf. unregelmässiges Verb, klärt bei Mehrdeutigkeit den Verwendungskontext.

---

## [2.0.3] - 2026-07-03

### Verbessert
- `deploy.php` ist nicht mehr in `.gitignore` — jetzt im Git-Repo versioniert (Historie/Diffs nachvollziehbar). Das Deploy-Token bleibt getrennt in `includes/deploy-config.php`, weiterhin gitignored und über die Skip-Liste geschützt. Produktions-Kopie von `deploy.php` muss bei Änderungen weiterhin manuell per FTP aktualisiert werden (schützt sich selbst vor Überschreiben durch den eigenen Deploy-Lauf).

---

## [2.0.2] - 2026-07-03

### Verbessert
- MCP-Server: Agent-Anweisungen (`initialize`-Instructions und `add_cards`-Tool-Beschreibung) ergänzt — bei Verben soll die Grundform (Infinitiv) in der Beschreibung ergänzt werden, bei unregelmässigen Verben zusätzlich ein Vermerk in der deutschen Beschreibung.

---

## [2.0.1] - 2026-07-03

### Behoben
- MCP-Server: Token wurde nur im `Authorization`-Header akzeptiert — ChatGPT und Claude Desktop (Browser-basierte Konnektoren) können diesen Header nicht setzen und scheiterten mit "Ungültiger Token". Fallback: Token wird jetzt zusätzlich als `?token=`-Query-Parameter akzeptiert.
- `.mcp.json` / `.mcp.json.example`: Prod-URL zeigte noch auf `/learner/mcp-server.php` (404 seit Verzeichnis-Refactoring) — korrigiert auf `mcp-server.php` an der Domain-Wurzel.

### Verbessert
- MCP `initialize`-Response enthält jetzt `instructions` mit dem Vokabel-Workflow (Person → Liste → Bestätigung → add_cards).
- `mcp-einrichtung.md`: Anleitungen für ChatGPT (Query-Token) und Claude Desktop (`mcp-remote`) ergänzt; Hinweis dass claude.ai Browser-Konnektoren OAuth voraussetzen und aktuell nicht funktionieren.

---

## [2.0.0] - 2026-06-30

### Neu
- MCP-Server (`mcp-server.php`): JSON-RPC 2.0 über HTTP POST (Streamable HTTP, sync), zustandslos
- Tool `list_persons`: gibt alle Personen zurück
- Tool `list_lists(person_id)`: gibt Listen einer Person zurück
- Tool `add_cards(list_id, cards[], force?)`: fügt Vokabelkarten ein, mit Duplikatprüfung
- Bearer-Token-Authentifizierung via `mcp-config.php` (gitignored)
- HTTPS-Pflicht auf Produktion
- Logging in `mcp.log` (gitignored)
- `.mcp.json.example` für Claude Code / VS Code (HTTP-Transport, Dev + Prod)
- `mcp-config.example.php` als Token-Vorlage
- `mcp-einrichtung.md`: Setup-Anleitung für Claude Code und n8n Cloud

---

## [1.4.3] - 2026-06-30

### Behoben
- settings.php: Deprecation-Warning `curl_close()` (seit PHP 8.5 deprecated, ohne Effekt seit PHP 8.0) — durch `unset($ch)` ersetzt

---

## [1.4.2] - 2026-06-30

### Behoben
- learn.php: Session startet ohne Karten wenn Karten keine card_progress-Einträge haben — lazy-Init stellt sicher dass alle Karten der Liste bei Session-Start als «queued» registriert werden
- learn.php: Statt leerer Zusammenfassung mit 0/0/0 wird jetzt «Keine Karten fällig» angezeigt, mit dem Datum wann die nächsten Karten fällig werden

---

## [1.4.1] - 2026-06-30

### Behoben
- learn.php: Undefined-array-key-Warnings auf der Session-Zusammenfassung wenn keine fälligen Karten vorhanden waren (stats-Array war leer)
- learn.php: XSS-Lücke — json_encode in script-Block ohne JSON_HEX_TAG; Listensprachnamen mit `</script>` hätten Script-Kontext brechen können
- auth.php: htmlspecialchars() in breadcrumb() ohne ENT_QUOTES — defensiver Flag ergänzt
- home.php: session_regenerate_id() fehlte beim switch_person-Handler (inkonsistent mit select_person/create_person)
- deploy.php: Vorhersagbarer Temp-Pfad via time() ersetzt durch tempnam() und random_bytes(8)
- deploy.php / settings.php: Deploy-Token wird nicht mehr als GET-Parameter übergeben (war in Server-Logs sichtbar) — Deploy-Button nutzt jetzt POST-Formular

---

## [1.4.0] - 2026-06-29

### Neu
- config-runtime.php (gitignored): speichert laufzeitspezifische Einstellungen — wird nie per Deploy überschrieben
- Einstellungen-Form auf allen Umgebungen sichtbar (nicht mehr localhost-only)

### Verbessert
- settings.php schreibt in config-runtime.php statt config.php — Prod-Einstellungen überleben jeden Deploy

---

## [1.3.0] - 2026-06-29

### Neu
- Einstellungen: Versions-Vergleich im Deployment-Bereich (installierte Version vs. GitHub-Version)
- deploy.php: install.php wird nie deployed — muss einmalig manuell hochgeladen und danach gelöscht werden

### Verbessert
- Lernkarten: Rahmen dunkler (#adb5bd statt #dee2e6), Schatten stärker — auf iPhone besser sichtbar

---

## [1.2.0] - 2026-06-29

### Neu
- Auto-Migration: fehlende DB-Spalten werden beim ersten Seitenaufruf automatisch ergänzt
- migrations.php: versionierte Migrationsliste — neue Migrationen am Ende anfügen
- Migration 1: `completed_at` in `learning_sessions` (behebt Fehler am Ende einer Leitner/Drill-Session)

---

## [1.1.0] - 2026-06-29

### Neu
- Einstellungen auf Produktion zugänglich (Login + CSRF-Schutz) — Config-Werte bleiben Localhost-only
- Einstellungen: Deploy-Bereich mit Link zu deploy.php (erscheint nur wenn deploy.php vorhanden)
- Einstellungen-Link in Navbar auf allen Umgebungen sichtbar

---

## [1.0.0] - 2026-06-29

### Erste stabile, getestete Version

- Leitner-System (5 Fächer, konfigurierbare Intervalle, Mehrfach-Listen, Click-to-Flip)
- Drill-Modus (Incremental Rehearsal, 9:1-Verhältnis, Timer, Leitner-Übergang)
- Listen verwalten, Karten bearbeiten, CSV-Import/Export, öffentliche Listen entdecken
- Mathe-Generator (Multiplikation + Division)
- Statistik-Dashboard, Streak-Badge in Navbar
- Einstellungen (Localhost): alle Konfigurationswerte, Passwort ändern
- install.php für Ersteinrichtung auf Produktion, db-credentials.example.php als Vorlage
- CSRF-Schutz, Prepared Statements, Session-Timeout, Passwort als Hash

---

## [0.8.0] - 2026-06-29

### Neu
- Einstellungen: Passwort ändern — aktuelles Passwort bestätigen, neues Passwort setzen (CSRF-geschützt, Login erforderlich)

### Behoben
- install.php: Localhost-Guard entfernt — Datei muss für Ersteinrichtung auf Produktion aufrufbar sein, wird danach manuell gelöscht
- index.php: Sicherheitswarnung blockiert App nur auf Produktion wenn install.php noch existiert — auf Localhost kein Block

---

## [0.7.1] - 2026-06-29

### Behoben
- Drill + Leitner: Karte erscheint aufgeklappt wenn Browser sie aus bfcache wiederherstellt — pageshow-Listener erzwingt Reload

### Verbessert
- Karten: Schriftgrösse Frage fs-2 (statt fs-1), Antwort fs-3 (statt fs-2) — lange Texte benötigen max. 2 Zeilen

---

## [0.7.0] - 2026-06-28

### Neu
- Streak-Badge (🔥) in Navbar auf allen Seiten sichtbar — Session-Cache, einmal täglich berechnet
- Einstellungen: Seitentitel (APP_NAME) konfigurierbar — wird in Navbar aller Seiten angezeigt
- Einstellungen: Default Kartenanzahl für Leitner-Session konfigurierbar (LEITNER_DEFAULT_CARDS)
- Einstellungen: 3 Gruppen (Allgemein / Leitner / Drill-Modus)

### Verbessert
- edit.php: Inline-Edit-Felder gleichbreit (flex col statt feste col-md-N), Speichern/Abbrechen als Icon-Buttons

---

## [0.6.5] - 2026-06-28

### Behoben
- Drill + Leitner: Session-Abbruch nach Bestätigung des Verlassen-Dialogs löscht jetzt korrekt den Session-Zustand — verhindert Geisterzustände im Hintergrund

### Verbessert
- Alle Content-Seiten (Listen, Bearbeiten, Mathe, Statistik, Einstellungen, Import, Entdecken) auf einheitliche Breite 960px vereinheitlicht

---

## [0.6.4] - 2026-06-28

### Verbessert
- settings.php: Alle 6 Konfigurationswerte auf einen Blick — Session-Timeout, Tägliches Karten-Limit, Drill-Timer, «Musste nachdenken»-Limit, Mastery-Schwelle, Bekannt/Neu-Verhältnis
- Zwei-Spalten-Layout (Desktop), gestapelt auf Mobile
- Ein "Alle speichern"-Button für alle Einstellungen

---

## [0.6.3] - 2026-06-28

### Neu
- settings.php: Localhost-only Einstellungsseite — Drill-Timer in Minuten anpassen, dauerhaft in config.php gespeichert
- Startseite (Navbar): "Einstellungen"-Link erscheint nur auf Localhost

---

## [0.6.2] - 2026-06-28

### Verbessert
- Leitner-Karte: identisches Design und Animation wie Drill-Karte (max-width 540px, p-5, fs-1/fs-2)
- Flip-Animation in Leitner: scaleX-Transform identisch zum Drill-Modus

---

## [0.6.1] - 2026-06-28

### Verbessert
- edit.php: Aktionsbuttons (Bearbeiten, Archivieren, Reaktivieren, Löschen) als Icon-only mit Tooltip — kompaktere Kartenliste
- edit.php: CSV Import / Export mit Icon + Text (Bootstrap Icons eingebunden)

---

## [0.6.0] - 2026-06-28

### Neu
- Drill-Modus komplett neu: Incremental Rehearsal — eine Karte nach der anderen statt 3 gleichzeitig
- 9:1-Verhältnis: bekannte Karten (drill_mastery >= 1) rotieren, neue Karten werden einzeln eingeführt
- Flip-Animation beim Aufdecken der Karte (CSS scaleX-Transform)
- "Gemeistert" = 3× hintereinander korrekt; "Musste nachdenken" setzt Zähler auf 0 zurück

### Verbessert
- Drill: "Musste nachdenken"-Limit gilt für alle Karten gleichermassen (bekannte und neue)
- Drill: drill_too_hard-Reset (lazy) jetzt auch beim Laden des Pools berücksichtigt — Karten die gestern zu schwer waren erscheinen heute wieder
- Drill: Abschluss zeigt "Musste nachdenken" in orange (statt rot) passend zum Button

### Behoben
- Drill: config-Konstante `DRILL_ACTIVE_CARDS` entfernt (war für 3-Karten-Modus, nicht mehr nötig)

---

## [0.5.0] - 2026-06-28

### Neu
- Warnung beim Verlassen einer aktiven Leitner- oder Drill-Session: Klick auf beliebigen Link zeigt Bestätigungs-Dialog ("Session wird beendet") mit Abbrechen-Option
- Breadcrumb-Navigation auf allen Seiten (inkl. Startseite): zeigt immer den vollständigen Pfad zur aktuellen Seite (z.B. `Startseite > Listen > Spanisch > Importieren`)
- Breadcrumb steht in eigenem Container — Position ist auf allen Seiten identisch, unabhängig von der Inhaltsbreite
- Mathe-Generator von Startseite nach "Meine Listen" (lists.php) verschoben

### Verbessert
- Redundante Zurück-Buttons und "Zur Startseite"-Links entfernt — Navigation läuft ausschliesslich über Breadcrumbs
- Startseite: Button "Meine Listen" (war "Verwalten") — Bezeichnung entspricht jetzt der Zielseite
- Import: Duplikat-Prüfung listenübergreifend — alle eigenen Karten werden verglichen, mit Wahl ob Duplikate importiert oder übersprungen werden
- Import: Duplikat-Vergleich ignoriert HTML-Tags in DB-Werten (strip_tags vor normalize) — Export→Import Roundtrip erkennt Duplikate korrekt

### Behoben
- Import: Export reimportieren schlug mit 0 importierten Karten fehl, weil Duplikate in anderen Listen die Prüfung blockierten

---

## [0.4.0] - 2026-06-28

### Neu
- Leitner-Setup: "← Startseite"-Button vor dem Sessionstart (kein Zurück-Button während aktiver Session)

### Verbessert
- Listen umbenennen (lists.php): PRG-Redirect nach Speichern — Editierformular verschwindet zuverlässig
- Karten bearbeiten (edit.php): PRG-Redirect für alle Aktionen (Bearbeiten, Archivieren, Reaktivieren, Löschen)
- Export: Kopfzeile enthält echte Sprachnamen statt "a;b;desc_a;desc_b"; Dateiname = Listenname; HTML-Tags bereinigt; Roundtrip-importierbar
- Import: Erste Nicht-Kommentar-Zeile wird immer als Kopfzeile behandelt (unabhängig vom Inhalt)
- Statistik: Direkt mit erster Liste vorausgewählt — kein globaler "Alle Listen"-Modus
- Öffentliche Listen (discover.php): Vorschau-Liste erscheint nicht mehr doppelt im Grid darunter
- Drill: Button- und Label-Text "Nicht gewusst" → "Musste nachdenken" (inkl. Abschlussseite und Statistik)
- Lernreihenfolge: Warteschlangen-Aktivierung (home.php, learn.php) und Leitner-Queue per `ORDER BY RAND()` gemischt
- Mathe-Generator: Typ-basierter Duplikat-Check (Multiplikation/Division) statt Namensprüfung — zweites Deck desselben Typs erfordert explizite Checkbox-Bestätigung; Formularwerte bleiben bei Warnung erhalten

### Behoben
- Discover: Angezeigte Vorschau-Liste erscheint nicht mehr im Grid der weiteren öffentlichen Listen

---

## [0.3.0] - 2026-06-28

### Verbessert
- Personenwahl: Formular "Neue Person erstellen" ist hinter Button "Neuen Benutzer hinzufügen" versteckt — verhindert versehentliches Erstellen einer neuen Person
- Mathe-Generator: Duplikat-Prüfung beim Listennamen — Warnung wenn eine Liste mit dem gewählten Namen bereits existiert
- Karten bearbeiten (edit.php): Scrollposition wird nach Archivieren, Bearbeiten und Löschen wiederhergestellt — kein Sprung an den Seitenanfang mehr

---

## [0.2.0] - 2026-06-28

### Neu
- Drill: Alle 3 aktiven Karten werden gleichzeitig angezeigt, in beliebiger Reihenfolge beantwortbar
- Drill + Leitner: Click-to-Flip — Karte per Klick/Tippen umdrehen, kein "Antwort zeigen"-Button mehr
- Drill: Countdown-Timer in der Navbar (verbleibende Zeit der Session)
- Testing.md mit strukturierten manuellen Testfällen eingeführt

### Verbessert
- Karten-Design einheitlich in Drill und Leitner (Rahmen, Rundungen, Schatten, grössere Schrift)
- Drill-Abschlussseite: "Erneut starten"-Button direkt zur gleichen Liste
- Leitner-Abschlussseite: "Neue Session"-Button behält die list_id
- Leitner-Setup: Richtungs-Labels zeigen echte Sprachnamen statt "A → B"

### Behoben
- Drill: Aufgedeckte Karten bleiben nach dem Beantworten einer anderen Karte sichtbar (Flip-Zustand via sessionStorage)
- Drill: Archivierte Karten erscheinen nicht mehr als Fallback wenn zu wenig aktive Karten vorhanden sind
- XSS-Lücke im Listen-Löschen-Dialog behoben (json_encode statt addslashes)

---

## [0.1.0] - 2026-06-01

### Initiales Release
- Leitner-System (5 Fächer, konfigurierbare Intervalle, Mehrfach-Listen)
- Drill-Modus (Incremental Rehearsal, Übergang ins Leitner)
- Listen verwalten (erstellen, umbenennen, löschen, öffentlich/privat)
- Karten bearbeiten (hinzufügen, bearbeiten, löschen, archivieren)
- CSV-Import mit Duplikat-Erkennung und Archiviert-Warnung
- CSV-Export (UTF-8 mit BOM, Semikolon)
- Öffentliche Listen entdecken & kopieren
- Statistik-Dashboard (Leitner-Fächer, Streak, Drill-Fortschritt)
- Mathe-Generator (Multiplikation + Division)
- Globales Passwort + Personenwahl
- CSRF-Schutz, Prepared Statements, Session-Timeout
