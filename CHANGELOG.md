# Changelog

Alle relevanten Änderungen werden hier dokumentiert.
Format: `MAJOR.MINOR.PATCH` — siehe `config.php` für die aktuelle Version.

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
