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
- Nach Login: **Person auswählen** (aus Liste) oder **neue Person erstellen**
- **Personenname muss eindeutig sein** — beim Erstellen wird geprüft ob der Name bereits existiert, sonst Fehlermeldung
- Keine individuellen Passwörter pro Person (alle kennen sich)
- Jede Person hat **eigene Listen** und **eigenen Lernfortschritt** (Fortschritt ist nicht öffentlich)
- Struktur ist so aufgebaut, dass ein individuelles Login später einfach nachrüstbar ist (`persons` Tabelle bekommt dann `email` + `password_hash`)

---

## Startseite

- Zeigt alle **eigenen Listen** der aktuellen Person (selbst erstellt oder kopiert)
- Öffentliche Listen anderer Personen sind über den Bereich "Entdecken" auf der Startseite zugänglich
- **Zuletzt verwendete Liste** wird automatisch vorgeschlagen (in DB gespeichert → browserübergreifend)
- Wahl des **Lernmodus** pro Session: Leitner oder Drill
  - **Drill:** startet sofort (keine Zwischenauswahl nötig — Liste bereits gewählt)
  - **Leitner:** kurze Konfigurationsseite (Richtung, Kartenanzahl) vor dem Start
- Jederzeit zurück zur Startseite möglich

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

### Metadaten pro Liste
| Feld | Pflicht |
|---|---|
| Name | ✅ |
| Beschreibung (z.B. "Französisch Vokabeln Klasse 2a") | optional |
| Sprache A | ✅ |
| Sprache B | ✅ |
| Öffentlich / Privat | ✅ |

### Öffentliche Listen entdecken
- Startseite zeigt zwei Bereiche:
  - **Oben:** eigene Listen
  - **Unten:** öffentliche Listen anderer Personen (Name, Beschreibung, Besitzer, Sprachen, Anzahl Karten)
- Öffentliche Liste anklicken → **Vorschau** aller Karten (Sprache A + Sprache B)
- Button **"Kopieren"** → Liste wird als eigene unabhängige Kopie übernommen
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
- Kopfzeile `a,b,desc_a,desc_b` ist **Pflicht**
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

---

## Lernmodus 2: Drill-Modus (Fluency / Precision Teaching)

Basiert auf **Precision Teaching** und **Mastery Learning**. Der Drill-Modus dient als **Eingangstor ins Leitner-System** — Karten beweisen zuerst im Drill ihre Automatizität und steigen dann progressiv ins Leitner-System ein.

### Ziel
Automatizität — die Antwort soll nicht errechnet oder überlegt, sondern **sofort gewusst** werden.
Terminologie: "Gewusst" / "Noch nicht gewusst" (kein Richtig/Falsch).
Geeignet für: Mathe-Fakten, häufig vergessene Vokabeln, neue Wörter festigen.

### Karten-Auswahl (automatisch)
- Noch nie gedrillt → **höchste Priorität**
- Danach sortiert nach höchster Quote "Noch nicht gewusst"
- Archivierte Karten erscheinen **nicht** im Drill
- Keine manuelle Karten-Auswahl — stattdessen ungewünschte Karten einfach archivieren

### Ablauf (Hybrid / Incremental Rehearsal)
1. **3 aktive Karten** zu Beginn der Session
2. Alle 3 Karten werden **gleichzeitig** angezeigt (je mit Vorderseite). User tippt/klickt auf eine Karte um sie umzudrehen → Antwort erscheint → "Gewusst" oder "Nicht gewusst" wählen. Reihenfolge ist frei wählbar.
3. Alle 3 "Gewusst" in einer Runde (fixe Phase) → **Reihenfolge intern mischen** → nochmal alle 3 gewusst nötig
4. Alle 3 auch in der gemischten Runde "Gewusst" → **die Karte mit den meisten aktiven Runden** gilt als gemeistert (bei Gleichstand: die zuerst geladene Karte)
5. Gemeisterte Karte verlässt die 3er-Gruppe → **neue Karte rückt nach** (immer 3 aktive Karten)
6. Die anderen zwei Karten bleiben aktiv und werden weiter geübt
7. Gemeisterte Karten bleiben im Drill-Pool für Folge-Sessions (zur Bestätigung)

### Fehler-Behandlung ("Noch nicht gewusst")
- Nur die betroffene **Karte** wird zurückgesetzt, nicht die ganze Runde
- Nach **5× "Noch nicht gewusst"** in einer Session → Karte als "zu schwer für heute" markiert, neue Karte kommt rein

### Session-Ende
- Endet nach **10 Minuten** ODER wenn keine geeigneten Drill-Karten mehr verfügbar sind (nicht `archived`, nicht `drill_too_hard = true`) — was zuerst eintritt
- Nach Timer-Ablauf wird die **aktuelle Runde noch zu Ende gespielt**
- Danach: motivierende Abschlussmeldung mit:
  - Anzahl Karten gemeistert in dieser Session
  - Aktueller Drill-Fortschritt pro gemeisterter Karte (1×, 2×, 3×)
  - Kurzer Motivationstext
- Mehrere Sessions pro Tag erlaubt — nach Abschluss erscheint Hinweis: "Für beste Resultate warte ein paar Stunden bis zur nächsten Session"

### Progressiver Übergang ins Leitner-System
Gemeisterte Drill-Karten steigen je nach Anzahl gemeisterter Sessions ins Leitner ein:

| Drill-Sessions gemeistert | Einstieg Leitner |
|---|---|
| 1× gemeistert | Fach 2 (alle 2 Tage) |
| 2× gemeistert | Fach 3 (alle 7 Tage) |
| 3× gemeistert | Fach 4 (alle 14 Tage) |

Fach 5 wird ausschliesslich durch echte Leitner-Wiederholungen erreicht.

- Drill-Fortschritt wird **separat** gespeichert
- Leitner-Fächer werden nur durch den obigen Übergang beeinflusst, nie durch Drill-Fehler

### Gilt für
- Mathe-Listen (Multiplikation, Division)
- Vokabel-Listen — generisch, kein Unterschied im Code

---

## Mathe-Generator

- Einmaliger Generator für **Multiplikationstabellen** und **Divisionstabellen** (1×1 bis 10×10, konfigurierbar)
- Multiplikation und Division werden als **separate Decks** generiert:
  - Deck Multiplikation: `7 × 8 = ?`
  - Deck Division: `56 ÷ 7 = ?`
- Erstellte Listen laufen normal durch beide Lernmodi (Leitner + Drill)
- Einträge können manuell als `archived` markiert werden (z.B. 1×1, 1×2 zu einfach)
- Später erweiterbar: Addition, Subtraktion

---

## Statistik-Dashboard

Kombinierte Ansicht pro Person und Liste:

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
- Gesamtquote "Gewusst" / "Noch nicht gewusst" pro Liste

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
  2. Alle Tabellen automatisch erstellt
  3. Globales Passwort setzen lässt
  4. Sich nach erfolgreicher Installation selbst zu löschen versucht
  5. Falls Selbst-Löschen nicht möglich (Dateirechte) → App wird gesperrt bis `install.php` manuell entfernt wurde
- Systemvoraussetzungen in `README.md` dokumentiert (PHP-Version, MySQL-Version)

---

## Deployment auf Produktiv-Server

Neue Versionen werden via Webhook-Deploy eingespielt:

- `deploy.php` liegt auf dem Produktiv-Server (nicht im Git-Repo — in `.gitignore`)
- Aufruf via Browser: `https://deinserver.ch/learner/deploy.php?token=GEHEIM`
- Script prüft Token und führt `git pull origin main` aus
- Token wird in `deploy-config.php` konfiguriert (ebenfalls in `.gitignore`)

**Voraussetzungen auf dem Server:**
- Das Verzeichnis muss ein Git-Repo sein (`git clone` bei Erstinstallation)
- PHP muss `exec()` erlauben — beim Hoster prüfen
- SSH-Key oder HTTPS-Credentials für GitHub hinterlegt

**Sicherheit:**
- Token zufällig generieren: `php -r "echo bin2hex(random_bytes(32));"`
- `deploy.php` und `deploy-config.php` nie committen
- Nur `git pull` — kein weiterer Shell-Zugriff möglich

---

## Versionsverwaltung & GitHub

- **Privates GitHub-Repository**
- **Semantic Versioning:** `MAJOR.MINOR.PATCH` (Start: `1.0.0`)
- **README.md** mit Projektbeschreibung, Installationsanleitung, Systemvoraussetzungen, Konfiguration
- **CHANGELOG.md** mit Versionshistorie aller Änderungen
- **`.gitignore`** schliesst aus: `db-credentials.php` (Zugangsdaten), `install.php` (nach Installation), `deploy.php`, `deploy-config.php`, temporäre Dateien

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
- Kein Fortschritt im Export
- Nur **eigene Listen** exportierbar (selbst erstellt oder kopiert — beides gilt als eigene Liste)
- Encoding: **UTF-8** mit BOM (Excel-kompatibel)
- Trennzeichen: **Semikolon** (Excel-freundlich in der Schweiz)

---

## Projektstruktur

```
/learner/
  install.php         ← Erstinstallation, Tabellen erstellen, Passwort setzen
  config.php          ← Konfiguration (Zeitzone, Intervalle etc., keine Credentials)
  auth.php            ← Session-Start, Timeout, CSRF-Funktionen, require_login/person, today()
  index.php           ← Login (globales Passwort)
  home.php            ← Personenwahl / Startseite / Dashboard
  learn.php           ← Leitner-Session
  drill.php           ← Drill-Modus (3 Karten, ~10 Min.)
  lists.php           ← Listen verwalten (erstellen, umbenennen, löschen)
  edit.php            ← Karte hinzufügen / bearbeiten / löschen
  discover.php        ← Öffentliche Listen entdecken & kopieren
  import.php          ← CSV Upload mit Formatbeschreibung
  export.php          ← CSV Export
  stats.php           ← Statistik-Dashboard
  math.php            ← Mathe-Generator (Multiplikation + Division)
  db.php              ← Umgebungserkennung + DB-Verbindung (committet, keine Credentials)
  db-credentials.php  ← Zugangsdaten Dev + Prod (in .gitignore, nie committen)
  deploy.php          ← Webhook-Deploy via Browser (in .gitignore)
  deploy-config.php   ← Deploy-Token (in .gitignore)
  /assets/            ← CSS, JS
  /templates/         ← CSV-Vorlage zum Download
```

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
