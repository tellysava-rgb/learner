Manuelle Testfälle für den Learner-Vokabeltrainer.

Jeder Test ist als Task mit [ ] gekennzeichnet. Nach erfolgreichem Test [x] setzen.
Jeder Abschnitt oder Test trägt einen Release-Verweis _(vX.Y.Z)_ — zeigt ab welchem Release dieser Test relevant ist.

---

## 1. Login / DB _(v0.1.0)_


---

## 2. Übersicht _(v0.1.0)_

[ ] Nach Login: Personenliste erscheint. Neue Person erstellen → erscheint in der Liste.
[ ] Personenwahl: Formular ist nicht sichtbar — nur Button "Neuen Benutzer hinzufügen" erscheint unter der Personenliste. _(v0.3.0)_
[ ] Button "Neuen Benutzer hinzufügen" klicken → Formular klappt auf. _(v0.3.0)_
[ ] Fehler beim Erstellen (z.B. Name bereits vergeben) → Formular bleibt offen und Fehlermeldung erscheint. _(v0.3.0)_

---

## 3. Listen (Import / Export / Bearbeiten)

### Listen verwalten _(v0.1.0)_

[ ] Neue Liste erstellen (Name, Sprache A, Sprache B) → erscheint in der Übersicht und auf der Startseite.
[ ] Liste umbenennen → neuer Name erscheint sofort.
[ ] Liste löschen → Bestätigungsdialog erscheint, danach verschwindet die Liste.
[ ] **XSS-Schutz** _(v0.2.0)_: Liste mit Name `Test "quotes" & <script>alert(1)</script>` erstellen. Auf "Löschen" klicken → Bestätigungsdialog zeigt den Namen korrekt an (kein JS-Fehler, keine Script-Ausführung).

### Karten bearbeiten _(v0.1.0)_

[ ] Karte hinzufügen (Sprache A + B) → erscheint in der Kartenliste.
[ ] Karte bearbeiten → Änderungen werden gespeichert.
[ ] Karte löschen → verschwindet aus der Liste, kein DB-Fehler.
[ ] Karte als archiviert markieren → Status wechselt zu "archiviert".
[ ] Karte archivieren bei langer Liste → Seite bleibt an derselben Scrollposition (kein Sprung an den Seitenanfang). _(v0.3.0)_
[ ] Karte bearbeiten & speichern → Seite kehrt zur gleichen Scrollposition zurück. _(v0.3.0)_

### Import _(v0.1.0)_

[ ] CSV mit gültigen Karten hochladen → Vorschau zeigt erkannte Karten korrekt an.
[ ] Duplikate beim Import → Warnung erscheint, User kann entscheiden (alle importieren / alle überspringen).
[ ] Ungültige Datei (z.B. `.txt` oder > 2MB) hochladen → Fehlermeldung, kein Absturz.
[ ] CSV mit Semikolon als Trennzeichen → wird korrekt erkannt.

### Export _(v0.1.0)_

[ ] Liste exportieren → CSV-Download startet, Datei enthält Kopfzeile `a,b,desc_a,desc_b`.
[ ] Exportierte CSV wieder importieren → Karten erscheinen korrekt (Roundtrip-Test).

### Entdecken _(v0.1.0)_

[ ] Öffentliche Liste einer anderen Person in der Übersicht sehen.
[ ] Vorschau öffnen → alle Karten der Liste sichtbar.
[ ] "Kopieren" → Liste erscheint als eigene Kopie in der Übersicht.
[ ] Originalliste des Besitzers ändern → Kopie bleibt unverändert.

### Mathe-Generator _(v0.1.0)_

[ ] Multiplikationstabelle generieren → neue Liste mit Karten `7 × 8 = ?` / `56` erscheint.
[ ] Divisionstabelle generieren → neue Liste mit Karten `56 ÷ 7 = ?` / `8` erscheint.
[ ] Generierte Liste normal im Leitner- und Drill-Modus verwendbar.
[ ] Mathe-Generator mit bereits verwendetem Listennamen → Fehlermeldung erscheint, keine Duplikat-Liste erstellt. _(v0.3.0)_
[ ] Nach Fehlermeldung anderen Namen eingeben → Liste wird normal erstellt. _(v0.3.0)_

---

## 4. Leitner

### Setup _(v0.1.0)_

[ ] Leitner über "Leitner"-Button auf Startseite starten → Setup-Seite erscheint mit vorausgewählter Liste.
[ ] Kartenanzahl mit −5/+5-Buttons anpassen → Wert ändert sich korrekt.

### Setup — Richtungs-Labels _(v0.2.0)_

[ ] Setup mit vorausgewählter Liste: Richtungs-Labels zeigen echte Sprachnamen (z.B. "Deutsch → Englisch"), nicht "A → B".
[ ] `learn.php` direkt ohne `list_id` aufrufen → Labels zeigen "A → B" bis eine Liste gewählt wird, dann aktualisieren sie sich per JS.
[ ] Andere Liste wählen → Labels passen sich sofort an (kein Seitenneuladen).

### Karten-Design _(v0.2.0)_

[ ] Lernkarte hat sichtbaren Rahmen, Rundungen und Schatten (sieht aus wie eine physische Karte).
[ ] Schrift auf der Karte ist deutlich grösser als normaler Fliesstext.
[ ] Karte reagiert auf Antippen/Klicken mit kurzem visuellem Feedback (leichte Verschiebung nach unten).
[ ] Auf dem iPhone: Karte sieht wie eine physische Karte aus.

### Click-to-Flip _(v0.2.0)_

[ ] Session starten: Karte zeigt nur die Frage — kein "Antwort zeigen"-Button sichtbar.
[ ] Auf die Karte tippen/klicken → Antwort erscheint, "Gewusst"/"Nicht gewusst"-Buttons erscheinen.
[ ] Vor dem Aufdecken: "Überspringen"-Button sichtbar. Nach dem Aufdecken: verschwindet.
[ ] Nach dem Aufdecken ist die Karte nicht mehr anklickbar (kein doppeltes Aufdecken).
[ ] Funktioniert per Finger auf dem iPhone (Touch-Event).

### Lernlogik _(v0.1.0)_

[ ] Karte richtig → steigt ein Fach auf, `next_due_date` entspricht Intervall des neuen Fachs.
[ ] Karte falsch → zurück in Fach 1, Karte erscheint nochmal am Ende der Session.
[ ] Beim zweiten Versuch richtig → bleibt in Fach 1 (kein Aufstieg).
[ ] Fach-5-Karte richtig → bleibt in Fach 5, `next_due_date = heute + 30`.
[ ] Übersprungene Karte erscheint am Ende der Queue, `next_due_date` unverändert.

### Abschluss _(v0.1.0)_

[ ] Session-Zusammenfassung zeigt Gewusst / Nicht gewusst / Aufgestiegen korrekt an.
[ ] "Zur Startseite"-Button führt zurück zur Übersicht.
[ ] "Neue Session"-Button führt zur Konfigurationsseite mit bereits vorausgewählter Liste. _(v0.2.0)_

---

## 5. Drill

### Start _(v0.1.0)_

[ ] "Drill"-Button auf Startseite → Session startet sofort (keine Zwischenauswahl).

### Timer _(v0.2.0)_

[ ] Timer erscheint in der Navbar und zählt sekündlich rückwärts (z.B. `9:59`).
[ ] Nach dem Beantworten einer Karte läuft der Timer weiter (kein Reset).
[ ] Auf der Abschlussseite und der Startseite ist kein Timer sichtbar.

### Karten-Design _(v0.2.0)_

[ ] Drill-Karten und Leitner-Karte haben identische Optik (gleicher Rahmen, Rundungen, Schatten).
[ ] Schrift ist deutlich grösser als normaler Text — auch kurze Ausdrücke wie `7 × 8 = ?` wirken gross.

### Alle 3 Karten gleichzeitig _(v0.2.0)_

[ ] Session starten: Alle 3 aktiven Karten werden gleichzeitig angezeigt (nebeneinander auf Desktop, untereinander auf Mobile).
[ ] Jede Karte zeigt initial nur die Vorderseite (Frage).
[ ] Karte 1 antippen → nur Karte 1 dreht sich um; Karte 2 und 3 bleiben zugedeckt.
[ ] Nach dem Aufdecken erscheinen "Gewusst"/"Nicht gewusst"-Buttons unter der jeweiligen Karte.
[ ] Karte 2 unabhängig von Karte 1 aufdecken und beantworten (beliebige Reihenfolge möglich).
[ ] "Nicht gewusst" auf einer Karte: Karte bleibt in der Runde und erscheint beim nächsten Laden wieder zugedeckt (neuer Versuch).
[ ] Bereits aufgedeckte Karten bleiben nach dem Beantworten einer anderen Karte sichtbar (kein Zurückklappen). _(v0.2.0 bugfix)_
[ ] Nach Beantworten aller Karten in der Runde: neue Runde startet, alle Karten erscheinen wieder zugedeckt.
[ ] Zu keinem Zeitpunkt werden mehr als 3 Karten gleichzeitig angezeigt.
[ ] Nach dem Meistern einer Karte rückt eine neue nach — weiterhin max. 3 Karten sichtbar.

### Lernlogik _(v0.1.0)_

[ ] Karten werden zufällig ausgewählt (nicht immer dieselbe Reihenfolge bei Wiederholung). _(v0.2.0)_
[ ] Archivierte Karten erscheinen nicht im Drill, auch wenn zu wenig aktive Karten vorhanden sind. _(v0.2.0)_
[ ] Wenn weniger als 3 nicht-archivierte Karten vorhanden: Drill startet trotzdem (mit 1–2 Karten).
[ ] Nach 5× "Nicht gewusst" für eine Karte: Karte wird als "zu schwer" markiert, neue Karte rückt nach.

### Abschluss _(v0.1.0)_

[ ] Nach Timer-Ablauf und leerer Queue: Abschlussseite erscheint.
[ ] Abschlussseite zeigt Gewusst / Noch nicht gewusst / Gemeistert korrekt an.
[ ] "Zur Startseite"-Button führt zurück zur Übersicht.
[ ] "Erneut starten"-Button startet sofort neue Session mit gleicher Liste. _(v0.2.0)_

---

## 6. Statistik _(v0.1.0)_

[ ] Statistik-Seite aufrufen → Karten pro Leitner-Fach werden korrekt angezeigt.
[ ] Lernstreak stimmt mit der tatsächlichen Lernhistorie überein.
[ ] Drill-Fortschritt (1×, 2×, 3× gemeistert) wird pro Liste korrekt angezeigt.
[ ] Richtig/Falsch-Statistik stimmt mit den durchgeführten Sessions überein.
