# Changelog

Alle relevanten Änderungen werden hier dokumentiert.
Format: `MAJOR.MINOR.PATCH` — siehe `config.php` für die aktuelle Version.

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
