# Vokabeltrainer — Anforderungen

## Technologie

- **PHP** + **MySQL**
- **Bootstrap** für responsives Design (Desktop + iPhone)
- Läuft auf einem Webserver, Deployment via Datei-Upload
- Kein Framework nötig
- **Zeitzone:** Europe/Zurich — gilt für alle Datumsberechnungen (Leitner, Streak, Drill-Reset)

## Sicherheit

- Globales Passwort wird als **Hash** gespeichert (kein Klartext in DB)
- **Session-Timeout:** 30 Minuten Inaktivität → automatischer Logout
- **Logout-Funktion** auf jeder Seite verfügbar
- **CSRF-Schutz** für alle schreibenden Aktionen (Löschen, Import, Bearbeiten, Erstellen)
- **SQL-Injection-Schutz** via Prepared Statements — konsequent überall
- **Upload-Beschränkung:** max. 2MB, nur `.csv` Dateiendung erlaubt
- **Fehlerbehandlung:** DB-Verbindungsfehler zeigt benutzerfreundliche Meldung — kein PHP-Stacktrace sichtbar

---

## Zugang / Benutzerverwaltung

- Ein **globales Passwort** schützt die gesamte App
- Nach Login: **Person auswählen** (aus Liste) — Personenliste ist prominent; "Neuen Benutzer hinzufügen" ist hinter einem Button versteckt um versehentliches Erstellen zu verhindern
- **Personenname muss eindeutig sein** — beim Erstellen wird geprüft ob der Name bereits existiert, sonst Fehlermeldung
- Keine individuellen Passwörter pro Person (alle kennen sich)
- Jede Person hat **eigene Listen** und **eigenen Lernfortschritt** (Fortschritt ist nicht öffentlich)
- Struktur ist so aufgebaut, dass ein individuelles Login später einfach nachrüstbar ist (`persons` Tabelle bekommt dann `email` + `password_hash`)

---

## Navigation

- Jede Seite zeigt eine **Breadcrumb-Navigation** in einem eigenen Container direkt unterhalb der Navbar — Position ist auf allen Seiten identisch, unabhängig von der Inhaltsbreite
- Breadcrumb zeigt immer den vollständigen Pfad zur aktuellen Seite, z.B.:
  - `Startseite > Meine Listen > Spanisch > Importieren`
  - `Startseite > Leitner`
  - `Startseite > Statistik`
- Das letzte Element (aktuelle Seite) ist nicht anklickbar — alle übergeordneten Stufen sind Links
- Breadcrumbs **ersetzen** die Zurück-Buttons — es gibt keine separaten Zurück-Buttons mehr
- Startseite (home.php) zeigt Breadcrumb mit nur `Startseite` (nicht verlinkt)
- Leitner und Drill: Breadcrumb zeigt immer `Startseite > Leitner` bzw. `Startseite > Drill` — unabhängig von Phase (Setup, aktive Session, Zusammenfassung)
- **Session-Verlassen-Warnung:** Während einer aktiven Leitner- oder Drill-Session (Karte wird angezeigt) löst jeder Link-Klick einen Bestätigungsdialog aus: "Achtung: die laufende Session wird dadurch automatisch beendet" — mit "Verlassen" und "Abbrechen"
- **Session-Abbruch:** Bei Bestätigung wird die Session server-seitig beendet (`$_SESSION['drill']` bzw. `$_SESSION['learn']` wird gelöscht) bevor zur Zielseite navigiert wird — verhindert Geisterzustände im Hintergrund
- **Streak-Badge in Navbar:** Das 🔥-Badge mit Streak-Anzahl wird auf allen Seiten angezeigt (via Session-Cache, einmal täglich berechnet auf home.php). Verschwindet wenn heute und gestern kein Lerntag war.
- **Container-Breite:** `lists.php` nutzt dieselbe (Bootstrap-Standard-)Container-Breite wie die Startseite `home.php` — kein eigenes `max-width` mehr _(v2.1.0)_

---

## Startseite

- Zeigt alle **eigenen Listen** der aktuellen Person (selbst erstellt oder kopiert)
- Öffentliche Listen anderer Personen sind über den Bereich "Entdecken" auf der Startseite zugänglich
- **Zuletzt verwendete Liste** wird automatisch vorgeschlagen (in DB gespeichert → browserübergreifend)
- Wahl des **Lernmodus** pro Session: Leitner oder Drill
  - **Drill:** startet sofort (keine Zwischenauswahl nötig — Liste bereits gewählt)
  - **Leitner:** kurze Konfigurationsseite (Richtung, Kartenanzahl) vor dem Start
- Navigation zur Startseite jederzeit über die Breadcrumb-Navigation möglich

---

## Wortlisten

### Verwaltung
- Listen **erstellen**, **umbenennen**, **löschen**
- Listen **exportieren** (CSV)
- Listen **importieren** (CSV)
- Downloadbare **CSV-Vorlage** mit Vokabel-Beispiel (nur Karten, kein Metadaten-Header)
- **Besitzer:** Die Person die eine Liste erstellt ist automatisch Besitzer — keine Übertragung möglich
- **Nur der Besitzer** kann seine Liste bearbeiten, umbenennen, löschen, importieren und exportieren
- **Privat-Flag** pro Liste — private Listen sind für andere Personen nicht sichtbar

### Liste migrieren _(v2.1.0)_
- Auf **Meine Listen** steht pro Liste ein Button **"Migrieren"** zwischen "Umbenennen" und "Löschen" — ausgeblendet wenn keine weitere eigene Liste existiert
- Öffnet ein Auswahlfenster: Zielliste wählen (nur eigene Listen, die Quellliste selbst ist ausgeschlossen)
- Alle Karten der Quellliste werden per `list_id`-Änderung in die Zielliste verschoben — der komplette Lernfortschritt pro Karte (`card_progress`: Leitner-Fach, `next_due_date`, Drill-Mastery, `drill_too_hard`) bleibt erhalten, da er an `card_id` hängt, nicht an `list_id`
- **Sprachpaar-Mismatch:** Unterscheiden sich die Sprachpaare von Quelle und Ziel (z.B. Deutsch→Englisch vs. Deutsch→Französisch), erscheint eine Warnung die der User bestätigen muss, bevor migriert wird
- **Duplikate in der Zielliste** (gleiche Wörter bereits vorhanden) werden nicht geprüft — beide Einträge bleiben nebeneinander bestehen
- Migration ist nur zwischen **eigenen** Listen derselben Person möglich — keine Vermischung mit Listen anderer Personen
- Die Quellliste bleibt nach der Migration **leer bestehen** — der User löscht sie bei Bedarf manuell
- Vergangene `learning_sessions`/`session_lists`-Einträge bleiben unverändert (historisch, kein Bezug zur aktuellen `list_id` der Karten)

### Metadaten pro Liste
| Feld | Pflicht |
|---|---|
| Name | ✅ |
| Beschreibung (z.B. "Französisch Vokabeln Klasse 2a") | optional |
| Sprache A | ✅ |
| Sprache B | ✅ |
| Öffentlich / Privat | ✅ |
| Aussprache-Sprachcode (Sprache B) | optional |

### Aussprache (Audio) _(v2.2.0)_
- Pro Liste kann ein **Sprachcode für die Aussprache** hinterlegt werden — ausschliesslich für **Sprache B** (die Fremdsprache), nicht für Sprache A
- Format: **BCP-47** (Sprache-Region, z.B. `en-GB`, `de-CH`, `fr-FR`) — reine Sprachcodes ohne Region (z.B. `en`) sind nicht zulässig
- Eingabe im "Liste erstellen"/"Umbenennen"-Formular: Textfeld mit Autovervollständigung (HTML `<datalist>`) — Vorschläge aus einer kuratierten Liste gängiger Codes **plus** allen bereits in anderen Listen verwendeten Codes; eigene Werte sind trotzdem frei eintippbar
- **Validierung beim Speichern:** Sprachteil gegen ISO-639-1, Regionsteil gegen ISO-3166-1 geprüft (z.B. `en-UK` wird abgelehnt, da "UK" kein gültiger ISO-3166-1-Code ist — korrekt ist `en-GB`). Gross-/Kleinschreibung wird automatisch normalisiert (z.B. `EN-gb` → `en-GB`)
- Keine serverseitige Prüfung ob die Kombination Sprache+Region "sinnvoll" ist (z.B. `ja-DE` wäre technisch gültig, aber unüblich) — reine Formatprüfung
- **Wiedergabe:** Auf Leitner- und Drill-Karten erscheint ein 🔊-Button überall dort, wo der Begriff in Sprache B angezeigt wird (Frage- oder Antwortseite, je nach Lernrichtung) — nutzt die browsereigene **Web Speech API** (`speechSynthesis`), liest den vorhandenen Kartentext (Sprache B) mit dem hinterlegten Code vor
- Button erscheint **nur**, wenn die Liste einen Aussprache-Code hinterlegt hat — sonst kein Button
- Kein separates Lautschrift-/Phonetik-Feld pro Karte — die Audio-Wiedergabe deckt diesen Bedarf ab
- Bestehende Listen ohne Code: Button bleibt einfach aus, bis der Besitzer den Code einmalig über "Umbenennen" nachträgt
- Beim Kopieren einer öffentlichen Liste ("Entdecken") wird der Aussprache-Code der Quellliste automatisch mitkopiert

### Öffentliche Listen entdecken
- Startseite zeigt zwei Bereiche:
  - **Oben:** eigene Listen
  - **Unten:** öffentliche Listen anderer Personen (Name, Beschreibung, Besitzer, Sprachen, Anzahl Karten)
- Öffentliche Liste anklicken → öffnet `discover.php?list_id=X` mit **Vorschau** aller Karten (Sprache A + Sprache B)
- Button **"Kopieren"** → Liste wird als eigene unabhängige Kopie übernommen
- `discover.php` ohne `list_id` → Weiterleitung zur Startseite (kein eigener Überblick)
- Alle kopierten Karten erhalten `status = queued` — Tageslimit gilt wie bei CSV-Import
- Nach dem Kopieren erscheint sie normal in der eigenen Listen-Übersicht
- Änderungen des Besitzers an der Originalliste haben keinen Einfluss auf die Kopie
- Eigene Kopie kann in beiden Modi genutzt werden (Leitner + Drill)

### Felder pro Karte
| Feld | Pflicht |
|---|---|
| Sprache A | ✅ |
| Sprache B | ✅ |
| Beschreibung A | optional |
| Beschreibung B | optional |

### Sprachen
- Frei definierbar pro Liste (z.B. Deutsch/Englisch, Deutsch/Japanisch)
- Lernrichtung wählbar pro Session: A→B, B→A oder Gemischt

### Karten-Identität
- Jede Karte erhält beim Erstellen eine **stabile `card_id`** in der Datenbank
- Jede Person lernt nur mit **eigenen Karten** — entweder selbst erstellt oder als Kopie einer öffentlichen Liste
- Keine geteilten Karten zwischen Personen — kein Fortschrittsverlust durch fremde Änderungen

### Decks mischen
- Beim Sessionstart können **mehrere eigene Listen gleichzeitig** ausgewählt werden
- Lernfortschritt ist immer **persönlich** pro Person und pro `card_id`

### Bearbeitung im Browser
- Einzelne Einträge **hinzufügen**, **ändern**, **löschen**
- Einträge direkt als **archiviert** markieren (erscheinen nicht mehr im Training)
- Aktionsbuttons pro Karte als **Icon-only** (Bootstrap Icons) mit Tooltip: Bearbeiten (Stift), Archivieren (Archiv-Box), Reaktivieren (Pfeil zurück), Löschen (Mülleimer)
- CSV Import / Export im Header: Icon + Text

### Duplikat-Prüfung beim Import
- Gilt **nur beim CSV-Import** — nicht beim Kopieren einer öffentlichen Liste
- Beim Kopieren werden immer neue Karten mit neuen IDs erstellt, keine Duplikat-Prüfung
- Beim Import wird auf Duplikate geprüft anhand von:
  - Normalisierter Text A (Kleinschreibung, Leerzeichen getrimmt, mehrfache Leerzeichen reduziert)
  - Normalisierter Text B (gleiche Normalisierung)
  - Prüfung ist **listenübergreifend** — alle eigenen Karten der Person werden berücksichtigt
  - `colour` vs. `color` gilt als unterschiedlich — kein automatischer Ausgleich
  - `7 × 8 = ?` vs. `7x8=?` gilt als unterschiedlich — Verantwortung beim User
- Warnung zeigt Übersicht aller gefundenen Duplikate (in welcher Liste sie existieren)
- User entscheidet **einmal global:** alle überspringen oder alle importieren
- Optional: einzelne Karten aus der globalen Entscheidung herausnehmen
- Beim Import einer bereits `archived` Karte → **Warnung mit drei Optionen:**
  - **Archiviert lassen** — Karte bleibt archiviert, wird nicht importiert
  - **Reaktivieren** — `status = active`, `leitner_box = 1`
  - **Als neue Karte importieren** — separate Karte mit eigener ID, archivierte bleibt unberührt

### CSV-Format
```
a,b,desc_a,desc_b
Diagnose,diagnosis,medizinischer Begriff,"A conclusion, reached by examination"
Behandlung,treatment,,
```
- Trennzeichen: **Komma oder Semikolon** — App erkennt automatisch
- **Encoding: UTF-8**
- Erste Zeile ist die Kopfzeile (Sprachnamen oder beliebige Spaltenbezeichnungen) — wird beim Import immer übersprungen
- Felder mit Kommas/Semikolons müssen in **doppelte Anführungszeichen** gesetzt werden
- Kommas/Semikolons innerhalb von Feldern sind nur erlaubt wenn das Feld korrekt gequotet ist
- Kein Listenname und keine Sprachen in der CSV — die Liste wird vorher in der App erstellt
- Import-Seite enthält ausführliche Erklärung und Beispiel

---

## Karten-Status

Jede Karte hat pro Person zwei separate Felder in der Datenbank:

### Status-Feld (`status`)
| Wert | Bedeutung |
|---|---|
| `queued` | Importiert, noch nicht aktiv — wartet in der Warteschlange |
| `active` | Aktiv im Leitner-System |
| `archived` | Gelernt — erscheint in keinem Modus mehr |

### Leitner-Feld (`leitner_box`)
| Wert | Bedeutung |
|---|---|
| 1–5 | Aktuelles Fach (nur relevant wenn `status = active`) |
| — | Leer wenn `queued` oder `archived` |

### Zusätzliche Felder pro Karte/Person
```
card_progress Tabelle:
- person_id
- card_id
- status            → 'queued', 'active', 'archived'
- leitner_box       → 1-5 (nur wenn status = 'active')
- next_due_date     → Datum der nächsten Fälligkeit (nur wenn status = 'active')
- drill_mastery     → 0-3 (Anzahl gemeisterter Drill-Sessions)
- drill_too_hard    → boolean, wird auf true gesetzt nach 5× "Noch nicht gewusst" in einer Session
                      wird zurückgesetzt zu false beim ersten Zugriff eines neuen Kalendertags (Zeitzone: Europe/Zurich)
```

### Ablauf Warteschlange
- Beim Upload von 100 Karten → alle erhalten `status = queued`
- Täglich werden 10 Karten aktiviert: `queued` → `active`, `leitner_box = 1`
- Button "10 weitere aktivieren" aktiviert sofort 10 weitere

### Archiv-Regeln
- Karten können manuell als `archived` markiert werden
- Kein automatisches Reaktivieren — User behält immer die Kontrolle

---

## Lernmodus 1: Leitner-System (5 Fächer)

### Fächer & Intervalle
| Fach | Intervall |
|---|---|
| Fach 1 | täglich |
| Fach 2 | alle 2 Tage |
| Fach 3 | alle 7 Tage |
| Fach 4 | alle 14 Tage |
| Fach 5 | alle 30 Tage (monatliche Auffrischung, bleibt in Fach 5) |

### Scheduling-Regeln
- Importierte Karten erhalten zuerst `status = queued` — noch nicht fällig
- Bei Aktivierung (täglich 10 Stück): `status = active`, `leitner_box = 1`, `next_due_date = heute`
- `next_due_date` wird berechnet ab **Datum der richtigen Antwort** + Intervall des neuen Fachs
- Fach-5-Karten bleiben in Fach 5, bekommen `next_due_date` = heute + 30 Tage
- **Falsche Antwort → sofort zurück in Fach 1**, `next_due_date = morgen`
- Falsch beantwortete Karte wandert ans **Ende der Session-Queue** — erscheint einmal nochmal
- Beim zweiten Versuch gewusst → bleibt in Fach 1, kein Aufstieg, `next_due_date = morgen`
- Beim zweiten Versuch wieder falsch → bleibt in Fach 1, kein weiterer Versuch in dieser Session
- Übersprungene Karten wandern ans Ende der Queue, `next_due_date` bleibt unverändert

### Priorisierung innerhalb einer Session
```
1. Überfällige Karten       (next_due_date < heute)
2. Heute fällige Karten     (next_due_date = heute)
3. Neu aktivierte Karten    (status = active, leitner_box = 1, noch nie beantwortet, bis Tageslimit)
4. Weitere Karten           (nur wenn User Anzahl manuell erhöht)
```

### Neue Karten / Tageslimit
- **Standard: 10 neue Karten pro Tag** aus der Warteschlange
- Button "10 weitere neue Karten hinzufügen" lädt sofort 10 mehr nach
- Warteschlange zeigt wie viele Karten noch warten
- Beim Upload von 100 Karten → nur 10 sofort aktiv, 90 in Warteschlange

### Kartendarstellung
- Karte zentriert, max. Breite 540px (`max-width:540px; margin: 0 auto`)
- Innenabstand `p-5`, Mindesthöhe 280px
- Frage in `fs-2`, Antwort in `fs-3` — bewusst kleiner als `fs-1` damit lange Texte max. 2 Zeilen benötigen
- Flip-Animation: Karte faltet sich horizontal (`scaleX(0)` → Inhalt tauschen → `scaleX(1)`, 150ms)
- Antwort-Buttons erscheinen erst nach Abschluss der Animation (300ms)
- `pageshow`-Listener verhindert dass bfcache eine bereits aufgeklappte Karte wiederherstellt
- Gleiche Karte und Animation wie im Drill-Modus

### Session
- **Kartenanzahl** wählbar — App macht Vorschlag (alle fälligen), User kann ändern via:
  - Button **-5** / Eingabefeld (Zahl) / Button **+5**
- **Lernrichtung** wählbar: A→B, B→A oder Gemischt
- **Letzte verwendete Liste** wird automatisch vorgeschlagen
- Session-Ende: motivierende Zusammenfassung mit:
  - Anzahl gewusst
  - Anzahl Karten aufgestiegen
  - Aktueller Lernstreak (z.B. "5 Tage in Folge!")
  - Kurzer Motivationstext (z.B. "Super gemacht!")
  - Anzahl Karten noch in Warteschlange
- **Keine Karten fällig:** statt leerer 0/0/0-Zusammenfassung wird eine eigene Meldung angezeigt (✅) mit dem Datum, wann die nächsten Karten fällig werden


---

## Lernmodus 2: Drill-Modus (Incremental Rehearsal)

Basiert auf **Incremental Rehearsal** und **Mastery Learning**. Der Drill-Modus dient als **Eingangstor ins Leitner-System** — Karten beweisen zuerst im Drill ihre Automatizität und steigen dann progressiv ins Leitner-System ein.

### Ziel
Automatizität — die Antwort soll nicht errechnet oder überlegt, sondern **sofort gewusst** werden.
Terminologie: "Gewusst" / "Musste nachdenken" (kein Richtig/Falsch).
Geeignet für: Mathe-Fakten, häufig vergessene Vokabeln, neue Wörter festigen.

### Karten-Auswahl (automatisch)
- Karten mit `drill_mastery = 0` (noch nie gemeistert) → neue/unbekannte Karten
- Karten mit `drill_mastery >= 1` (mindestens einmal früher gemeistert) → bekannte Karten
- Archivierte Karten erscheinen **nicht** im Drill
- Keine manuelle Karten-Auswahl — stattdessen ungewünschte Karten einfach archivieren

### Ablauf (eine Karte nach der anderen)
1. Karte wird angezeigt (nur Vorderseite / Frage)
2. User denkt nach, tippt/klickt auf die Karte → Karte dreht sich um (Flip-Animation)
3. Antwort erscheint, darunter: Button **"Gewusst"** (grün) und **"Musste nachdenken"** (orange)
4. User bewertet → nächste Karte erscheint sofort

### Karten-Reihenfolge (9:1-Verhältnis)
- Bekannte Karten (`drill_mastery >= 1`) bilden einen rotierenden Pool
- Neue/unbekannte Karten werden einzeln eingeführt: nach jeweils 9 bekannten Karten erscheint 1 neue
- Neu eingeführte Karten wandern in den rotierenden Pool und werden ab dann gemeinsam wiederholt
- Das Mischen passiert im Hintergrund — der User sieht nur eine Karte nach der anderen

### "Gemeistert"-Definition
Eine Karte gilt als in dieser Session gemeistert wenn sie **3× hintereinander** mit "Gewusst" beantwortet wurde. "Musste nachdenken" setzt den Zähler auf 0 zurück.

### "Musste nachdenken"-Behandlung
- Nach **5× "Musste nachdenken"** in einer Session → Karte als "zu schwer für heute" markiert (`drill_too_hard = 1`) und aus dem Pool entfernt
- Gilt für alle Karten gleichermassen (bekannte wie neue)
- Reset von `drill_too_hard`: lazy — beim ersten Zeigen der Karte wenn `last_drill_shown < heute`

### Navbar während der Session
- **Timer** (MM:SS, rückwärts) und **X gemeistert** werden nebeneinander angezeigt — aktualisieren sich nach jeder Kartenbewertung (PRG-Redirect)

### Session-Ende
- Endet nach **10 Minuten** — nach Ablauf wird die aktuelle Karte noch fertig gespielt (Flip + Bewertung), dann Abschluss
- Oder früher wenn alle Karten gemeistert oder als "zu schwer" markiert wurden
- Abschlussmeldung:
  - Anzahl Gewusst / Musste nachdenken / Gemeistert
  - Drill-Fortschritt pro gemeisterter Karte (1×, 2×, 3×)
  - Kurzer Motivationstext
  - Hinweis: "Für beste Resultate warte ein paar Stunden bis zur nächsten Session"

### Progressiver Übergang ins Leitner-System
Gemeisterte Drill-Karten steigen je nach `drill_mastery` ins Leitner ein:

| drill_mastery (nach Session) | Einstieg Leitner |
|---|---|
| 1× gemeistert | Fach 2, next_due_date = heute + 2 |
| 2× gemeistert | Fach 3, next_due_date = heute + 7 |
| 3× gemeistert | Fach 4, next_due_date = heute + 14 |

Fach 5 wird ausschliesslich durch echte Leitner-Wiederholungen erreicht.

- Drill-Fortschritt (`drill_mastery`) wird **separat** gespeichert
- Leitner-Fächer werden nur durch den obigen Übergang beeinflusst, nie durch Drill-Fehler

### Gilt für
- Mathe-Listen (Multiplikation, Division)
- Vokabel-Listen — generisch, kein Unterschied im Code

---

## Einstellungsseite

- Zugänglich auf allen Umgebungen (Login + CSRF-Schutz erforderlich)
- Link "Einstellungen" erscheint in der Navbar der Startseite (home.php) auf allen Umgebungen
- Einstellungen werden **dauerhaft in `config-runtime.php`** geschrieben (gitignored, wird nie per Deploy überschrieben)
- Auf Localhost: zusätzlicher "Localhost"-Badge sichtbar
- PRG-Muster: nach Speichern Redirect auf GET, Flash-Meldung via Session

### Konfigurierbare Werte (Gruppen: Allgemein / Leitner / Drill)
| Gruppe | Einstellung | Konstante | Beschreibung | Bereich |
|---|---|---|---|---|
| Allgemein | Seitentitel | `APP_NAME` | Anzeigename oben links in der Navbar | max. 50 Zeichen |
| Allgemein | Session-Timeout | `SESSION_TIMEOUT` | Inaktivitäts-Timeout in Minuten | 1–480 |
| Leitner | Tägliches Karten-Limit | `DAILY_CARD_LIMIT` | Neue Karten pro Tag aus der Warteschlange | 1–100 |
| Leitner | Default Kartenanzahl | `LEITNER_DEFAULT_CARDS` | Voreingestellte Anzahl Karten beim Session-Start | 1–200 |
| Drill | Timer | `DRILL_SESSION_SECONDS` | Dauer einer Drill-Session in Minuten | 1–120 |
| Drill | «Musste nachdenken»-Limit | `DRILL_TOO_HARD_LIMIT` | Bewertungen bis Karte aus Session entfernt wird | 1–20 |
| Drill | Mastery-Schwelle | `DRILL_MASTERY_THRESHOLD` | Aufeinanderfolgende Korrekt-Antworten für «gemeistert» | 1–10 |
| Drill | Bekannt/Neu-Verhältnis | `DRILL_KNOWN_RATIO` | Bekannte Karten pro neuer Karte in der Rotation | 1–30 |

### Passwort ändern
- Formular in der Einstellungsseite (Abschnitt «Sicherheit»)
- Aktuelles Passwort muss bestätigt werden — kein Reset ohne Kenntnis des alten Passworts
- CSRF-geschützt, Login erforderlich

---

## Mathe-Generator

- Erreichbar über **Meine Listen** (lists.php) — nicht mehr direkt von der Startseite
- Einmaliger Generator für **Multiplikationstabellen** und **Divisionstabellen** (1×1 bis 10×10, konfigurierbar)
- **Duplikat-Prüfung (typ-basiert):** Existiert bereits eine Liste desselben Typs (Multiplikation oder Division), erscheint eine Warnung mit Checkbox-Bestätigung — erst mit Bestätigung wird ein zweites Deck erstellt. Listenname spielt dabei keine Rolle.
- Multiplikation und Division werden als **separate Decks** generiert:
  - Deck Multiplikation: `7 × 8 = ?`
  - Deck Division: `56 ÷ 7 = ?`
- Erstellte Listen laufen normal durch beide Lernmodi (Leitner + Drill)
- Einträge können manuell als `archived` markiert werden (z.B. 1×1, 1×2 zu einfach)
- Später erweiterbar: Addition, Subtraktion

---

## Statistik-Dashboard

Statistik startet mit der ersten eigenen Liste vorausgewählt — kein globaler "Alle Listen"-Modus. Auswahl per Button oben.

**Leitner-Übersicht:**
- Anzahl Karten pro Fach (Fach 1–5 + archiviert)
- Lernstreak (wie viele Tage in Folge gelernt) — Definition:
  - Leitner und Drill zählen beide
  - Mindestens eine Karte beantwortet (gewusst oder nicht gewusst) = Lerntag
  - Überspringen allein zählt nicht
  - Abgebrochene Session zählt wenn mindestens eine Karte beantwortet wurde
- Richtig/Falsch-Statistik
- Anzahl Karten in Warteschlange

**Drill-Übersicht:**
- Anzahl Karten gemeistert (1×, 2×, 3×)
- Gesamtquote "Gewusst" / "Musste nachdenken" pro Liste

---

## Import-Seite

- Ausführliche Erklärung des CSV-Formats mit Beispiel
- Hinweis auf erlaubte Trennzeichen (Komma oder Semikolon)
- Downloadbare CSV-Vorlage (Vokabeln)
- Duplikat-Warnung vor dem Import mit Entscheidungsmöglichkeit

---

## Installation

- Einmaliges `install.php` Script das:
  1. Datenbankverbindung prüft
  2. Alle Tabellen automatisch erstellt (idempotent — `IF NOT EXISTS`)
  3. Globales Passwort setzen lässt
- Nach der Ersteinrichtung muss `install.php` **manuell vom Produktiv-Server gelöscht** werden
- `index.php` erkennt ob `install.php` noch existiert und sperrt die App auf Produktion bis sie gelöscht ist — auf Localhost kein Block
- `install.php` ist im Git-Repo (nicht gitignored) — beim Deploy wird sie automatisch übersprungen (in `deploy.php` Skip-Liste)
- **DB-Migrationen:** `migrations.php` wird bei jedem Request automatisch aufgerufen — fehlende Spalten/Tabellen werden ergänzt ohne manuellen SQL-Eingriff

---

## Deployment auf Produktiv-Server

Neue Versionen werden via ZIP-Download von GitHub eingespielt (kein `shell_exec`/`exec` nötig):

- `deploy.php` ist im Git-Repo versioniert _(v2.0.3)_ — schützt sich aber über die eigene Skip-Liste selbst vor Überschreiben, muss also bei Änderungen weiterhin manuell per FTP auf den Produktiv-Server kopiert werden
- Aufruf: Deploy-Button in den Einstellungen (settings.php) — Token wird per POST-Formular übermittelt (nicht als URL-Parameter, verhindert Sichtbarkeit in Server-Logs)
- Script lädt das GitHub-Repo als ZIP via cURL herunter, entpackt es und kopiert die Dateien
- Token wird in `deploy-config.php` konfiguriert (bleibt in `.gitignore` — Trennung von Logik und Geheimnis)

**Dateien die nie per Deploy überschrieben werden (Skip-Liste in deploy.php):**
- `db-credentials.php` — Datenbankzugangsdaten
- `config-runtime.php` — Laufzeit-Einstellungen (Prod-spezifisch)
- `deploy.php` — das Deploy-Script selbst
- `deploy-config.php` — Deploy-Token und GitHub-Konfiguration
- `install.php` — Erstinstallations-Script (manuell verwalten)

**Voraussetzungen auf dem Server:**
- PHP mit cURL-Extension (auf den meisten Hostern verfügbar)
- GitHub-Repo muss **public** sein (kein Token für Download nötig)
- Schreibrechte im App-Verzeichnis

**Konfiguration (`deploy-config.php`):**
- `DEPLOY_TOKEN` — schützt die deploy.php-URL, zufällig generieren: `php -r "echo bin2hex(random_bytes(32));"`
- `GITHUB_OWNER` — GitHub-Benutzername
- `GITHUB_REPO` — Repository-Name

**Versions-Vergleich in Einstellungen:**
- settings.php zeigt installierte Version und GitHub-Version nebeneinander
- Grün = aktuell, Blau = Update verfügbar

---

## Versionsverwaltung & GitHub

- **Öffentliches GitHub-Repository** (public — ermöglicht ZIP-Download ohne Token)
- **Semantic Versioning:** `MAJOR.MINOR.PATCH` (Start: `1.0.0`)
- **CHANGELOG.md** mit Versionshistorie aller Änderungen
- **`.gitignore`** schliesst aus: `db-credentials.php`, `config-runtime.php`, `deploy-config.php`, temporäre Dateien _(`deploy.php` seit v2.0.3 im Repo versioniert)_

---

## Datenbankmodell

| Tabelle | Inhalt |
|---|---|
| `persons` | Personen (Name, erstellt am) |
| `lists` | Wortlisten (Name, Beschreibung, Sprachen, Besitzer, öffentlich/privat) |
| `cards` | Karten (Sprache A/B, Beschreibung A/B, Liste, erstellt am) |
| `card_progress` | Fortschritt pro Person/Karte (status, leitner_box, next_due_date, drill_mastery, drill_too_hard) |
| `learning_sessions` | Abgeschlossene Sessions (Person, Modus, Datum) |
| `session_lists` | Join-Tabelle: welche Listen waren an einer Session beteiligt (session_id, list_id) |
| `learning_events` | Einzelne Karten-Antworten (für Statistik und Streak-Berechnung) |

### Lösch-Verhalten
- Karte löschen → `card_progress` Einträge dieser Karte werden **physisch mitgelöscht** (kaskadierend)
- Liste löschen → alle Karten + deren `card_progress` werden **physisch mitgelöscht**
- Kopien anderer Personen sind unabhängig — nicht betroffen

---

## CSV-Export

- Exportiert nur **Kartendaten** (Sprache A, Sprache B, Beschreibung A, Beschreibung B)
- Erste Zeile: Kommentar `# Listenname (Sprache A / Sprache B)` — zur menschenlesbaren Dokumentation, wird beim Import ignoriert
- Zweite Zeile: Kopfzeile mit echten Sprachnamen (z.B. `Deutsch;Englisch;Beschreibung Deutsch;Beschreibung Englisch`)
- Dateiname = Listenname (Sonderzeichen ersetzt durch `_`)
- HTML-Tags und Entities werden vor dem Export bereinigt (z.B. `<p>`, `<br>`, `&amp;`) — Export ist plain text
- Kein Fortschritt im Export
- Nur **eigene Listen** exportierbar (selbst erstellt oder kopiert — beides gilt als eigene Liste)
- Encoding: **UTF-8** mit BOM (Excel-kompatibel)
- Trennzeichen: **Semikolon** (Excel-freundlich in der Schweiz)
- Export-Datei kann direkt wieder importiert werden (Roundtrip-kompatibel)

---

## Projektstruktur

```
/learner/
  index.php                ← Login (globales Passwort)
  home.php                 ← Personenwahl / Startseite / Dashboard
  learn.php                ← Leitner-Session
  drill.php                ← Drill-Modus (Incremental Rehearsal)
  lists.php                ← Listen verwalten (erstellen, umbenennen, löschen)
  edit.php                 ← Karte hinzufügen / bearbeiten / löschen
  discover.php             ← Öffentliche Listen entdecken & kopieren
  import.php               ← CSV Upload mit Formatbeschreibung
  export.php               ← CSV Export
  stats.php                ← Statistik-Dashboard
  math.php                 ← Mathe-Generator (Multiplikation + Division)
  settings.php             ← Einstellungsseite (alle Umgebungen, schreibt in config-runtime.php)
  install.php               ← Erstinstallation: Tabellen erstellen, Passwort setzen (manuell löschen nach Setup)
  mcp-server.php            ← MCP-Endpoint für Agenten (JSON-RPC über HTTP)
  deploy.php                ← ZIP-Deploy via Browser (im Repo versioniert, schützt sich selbst vor Überschreiben)
  /assets/                  ← CSS, JS
  /templates/               ← CSV-Vorlage zum Download
  /includes/                ← reine Library-/Config-Dateien, nie direkt per URL aufgerufen
    config.php                 ← Statische Konfiguration (Zeitzone, Intervalle, Version, Standardwerte)
    config-runtime.php         ← Laufzeit-Einstellungen pro Umgebung (gitignored, nie deployed, schreibt settings.php)
    migrations.php             ← Auto-Migrationen: fehlende DB-Spalten werden beim Start automatisch ergänzt
    auth.php                   ← Session-Start, Timeout, CSRF-Funktionen, require_login/person, today()
    db.php                     ← Umgebungserkennung + DB-Verbindung + Migrationen
    db-credentials.php         ← Zugangsdaten Dev + Prod (gitignored, nie committen)
    db-credentials.example.php ← Vorlage für db-credentials.php (committet)
    deploy-config.php          ← Deploy-Token + GitHub-Konfiguration (gitignored)
    mcp-config.php             ← MCP-Token (gitignored)
  /docs/                    ← Dokumentation ausser CLAUDE.md
    ANFORDERUNGEN.md, CHANGELOG.md, Checkliste.md, Testing.md, mcp-einrichtung.md
```

---

## MCP-Server _(v2.0.0, erweitert v2.0.1)_

`mcp-server.php` stellt einen MCP-Endpoint bereit (JSON-RPC 2.0 über HTTP POST, Streamable-HTTP im synchronen Modus — kein SSE). Clients sind Claude Code, Claude Desktop (via `mcp-remote`), ChatGPT und n8n Cloud. claude.ai Browser-Konnektoren funktionieren aktuell **nicht** (siehe unten).

### Protokoll & Authentifizierung
- Protokoll: MCP über HTTP, nur synchroner JSON-Response (kein Streaming/SSE)
- Stateless: kein serverseitiger Session-Store
- 3 JSON-RPC-Methoden: `initialize`, `tools/list`, `tools/call`
- Bearer-Token im `Authorization`-Header — Pflicht auf jedem Request
- Fallback _(v2.0.1)_: Token als `?token=`-Query-Parameter, für Clients ohne Header-Unterstützung (ChatGPT)
- Token in `mcp-config.php` (gitignored, analog `deploy-config.php`)
- HTTPS verpflichtend auf Produktion (HTTP → HTTP 403)
- **claude.ai Browser-Konnektoren verlangen OAuth** — mit reinem Bearer-/Query-Token nicht nutzbar. Ohne OAuth-Implementierung bleibt claude.ai als Client aussen vor _(v2.0.1)_

### Tools

**`list_persons`** — keine Parameter
- Gibt alle Personen zurück: `[{ id, name }]`

**`list_lists(person_id)`** — Pflichtfeld: `person_id` (integer)
- Gibt alle Listen einer Person zurück: `{ person: { id, name }, lists: [{ id, name, language_a, language_b, speech_lang_b }] }` _(`speech_lang_b` seit v2.2.0)_

**`add_cards(list_id, cards[], force?)`**
- Fügt eine oder mehrere Vokabelkarten in eine Liste ein
- `cards[]` = Array aus `{ sprache_a_begriff, sprache_b_begriff, beschreibung_a?, beschreibung_b? }`
- Feld-Regeln für den Agent (in Tool-Beschreibung, Feld-Beschreibungen und `initialize`-Instructions — keine serverseitige Validierung, reine Agent-Anweisung) _(v2.0.2, verschärft v2.0.4)_:
  - Begriff (Fremdsprache): exakt — bei Verben Grundform (Infinitiv), bei unregelmässigen Verben alle drei Formen (z.B. "go / went / gone")
  - Begriff (Deutsch): exakt
  - Beschreibung (Fremdsprache): Beispielsatz mit dem exakten fremdsprachigen Begriff
  - Beschreibung (Deutsch): beschreibt die Bedeutung genauer, **ohne den fremdsprachigen Begriff zu nennen** — bei unregelmässigen Verben ggf. vermerken, bei Mehrdeutigkeit den Verwendungskontext klären
  - Bekannter Fehlerfall, der zur Verschärfung führte: Agent schrieb den fremdsprachigen Begriff versehentlich in die deutsche Beschreibung (z.B. `bounced` in der Beschreibung zu `unzustellbar`) — jetzt explizit verboten
  - **Dialekt-Konsistenz** _(v2.2.0)_: Hat die Zielliste einen `speech_lang_b`-Code (z.B. `en-GB` vs. `en-US`), müssen Schreibweise und Wortwahl des Begriffs sowie des Beispielsatzes zu diesem Dialekt passen (z.B. `en-GB` → "colour", "lorry", "flat"; `en-US` → "color", "truck", "apartment") — wie bei den übrigen Feld-Regeln reine Agent-Anweisung, keine serverseitige Validierung
- Karten werden nur in `cards`-Tabelle eingefügt — **kein `card_progress`-Eintrag** (lazy-init beim nächsten Leitner-Session-Start)
- Duplikatprüfung: exakter Vergleich (case-insensitive, getrimmt) auf `word_a + word_b` innerhalb der Ziel-Liste
- Duplikat + `force = false`: Karte wird nicht eingefügt, Warnung mit gefundener Karte zurückgegeben
- Duplikat + `force = true`: Karte wird trotzdem eingefügt
- Limits: max. 50 Karten/Aufruf, Begriff max. 500 Zeichen, Beschreibung max. 1000 Zeichen
- Antwort: `{ summary, list: { id, name }, results: [{ index, status, card, message? }] }`
  - `status`: `inserted` / `duplicate` / `error`

### Sicherheit
- Prepared Statements für alle DB-Zugriffe
- Keine PHP-Stacktraces nach aussen (generische Fehlermeldungen)
- Input-Validierung: Pflichtfelder, Typ, Längen, max. Karten-Anzahl
- Logging: `mcp.log` (gitignored via `*.log`) — Zeitstempel, Umgebung, Methode, Tool, Argumente

### Client-Einrichtung
- `.mcp.json.example` für Claude Code / VS Code (HTTP-Transport, Token-Header, Dev + Prod)
- `mcp-einrichtung.md`: Setup-Anleitung inkl. n8n AI Agent Node, ChatGPT (Query-Token), Claude Desktop (`mcp-remote`), Apache `.htaccess`-Workaround

### n8n vs. Claude Code — Duplikat-Verhalten
| Client | Duplikat-Reaktion |
|---|---|
| Claude Code | Warnung anzeigen, erst nach Bestätigung mit `force=true` erneut aufrufen |
| n8n | Sofort mit `force=true` erneut aufrufen (kein Mensch anwesend) |

---

## Version 1 — bewusst weggelassen

- Kein JSON-Import (nur CSV)
- Kein Excel-Upload
- Kein individuelles Login pro Person (globales Passwort + Personenwahl)
- Keine E-Mail / Passwort-Reset Funktion
- Keine Audio-Aussprache
- Keine Gamification (Punkte, Badges)

---

## Offen / Später

- Individuelle Logins pro Person (Datenstruktur ist bereits vorbereitet)
- Addition, Subtraktion im Mathe-Generator
- Audio-Aussprache via externe API
- JSON-Import
