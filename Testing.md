Manuelle Testfälle für den Learner-Vokabeltrainer.

Jeder Test ist als Task mit [] gekennzeichnet. Nach erfolgreichem Test [x] setzen.
Jeder Abschnitt oder Test trägt einen Release-Verweis _(vX.Y.Z)_ — zeigt ab welchem Release dieser Test relevant ist.

---

## 1. Login / DB 


---

## 2. Navigation _(v0.5.0)_

[ ] lists.php: Breadcrumb zeigt "Startseite > Meine Listen". _(v0.5.0)_
[ ] edit.php: Breadcrumb zeigt "Startseite > Meine Listen > [Listenname]". _(v0.5.0)_
[ ] import.php: Breadcrumb zeigt "Startseite > Meine Listen > [Listenname] > Importieren". _(v0.5.0)_
[ ] stats.php: Breadcrumb zeigt "Startseite > Statistik". _(v0.5.0)_
[ ] math.php: Breadcrumb zeigt "Startseite > Meine Listen > Mathe-Generator". _(v0.5.0)_
[ ] discover.php: Breadcrumb zeigt "Startseite > Entdecken". _(v0.5.0)_
[ ] learn.php Setup: Breadcrumb zeigt "Startseite > Leitner". _(v0.5.0)_
[ ] learn.php aktive Session: Breadcrumb zeigt "Startseite > Leitner". _(v0.5.0)_
[ ] learn.php Zusammenfassung: Breadcrumb zeigt "Startseite > Leitner". _(v0.5.0)_
[ ] drill.php Setup/Session/Zusammenfassung: Breadcrumb zeigt "Startseite > Drill". _(v0.5.0)_
[ ] "Startseite"-Link in Breadcrumb führt zu home.php. _(v0.5.0)_
[ ] Letzte Stufe in Breadcrumb ist nicht anklickbar (aktive Seite). _(v0.5.0)_
[ ] Breadcrumb erscheint auf home.php als "Startseite" (nicht verlinkt). _(v0.5.0)_
[ ] Breadcrumb steht auf allen Seiten an derselben horizontalen Position. _(v0.5.0)_
[ ] Startseite: Button "Meine Listen" führt zu lists.php (kein "Verwalten" mehr). _(v0.5.0)_
[ ] Startseite: kein "Mathe-Generator"-Button sichtbar. _(v0.5.0)_
[ ] lists.php: "Mathe-Generator"-Button rechts oben sichtbar, führt zu math.php. _(v0.5.0)_

---

## 4. Übersicht 


---

## 5. Listen (Import / Export / Bearbeiten)

### Listen verwalten 


### Karten bearbeiten _(v0.6.1)_

[ ] Aktionsbuttons zeigen nur Icons (kein Text). _(v0.6.1)_
[ ] Hover über Icon-Button → Tooltip mit Bezeichnung erscheint. _(v0.6.1)_
[ ] CSV Import / Export: Icon + Text sichtbar. _(v0.6.1)_


### Import 

[ ] Import: Karten die in einer anderen Liste existieren, werden als Duplikate erkannt. _(v0.5.0)_
[ ] Import: Duplikate überspringen → Karten werden nicht importiert. _(v0.5.0)_
[ ] Import: Duplikate trotzdem importieren → alle Karten werden importiert. _(v0.5.0)_
[ ] Import: Export reimportieren (gleiche Liste) → alle Karten als Duplikate erkannt. _(v0.5.0)_

### Export 



### Entdecken 


### Mathe-Generator 

[ ] Mathe-Generator: Multiplikationsdecks existiert bereits → beim Erstellen eines zweiten erscheint Warnung mit Checkbox. _(v0.4.0)_
[ ] Erstes Multiplikationsdeck → kein Warning, direkte Erstellung. _(v0.4.0)_

### Listen verwalten (PRG) _(v0.4.0)_


### Karten bearbeiten (PRG) _(v0.4.0)_

[ ] Karte bearbeiten → nach Speichern verschwindet das Inline-Editierformular. _(v0.4.0)_
[ ] Karte archivieren → Flash-Meldung erscheint, Karte bleibt korrekt gefiltert. _(v0.4.0)_
[ ] Karte reaktivieren (Archiv-Tab) → Flash-Meldung erscheint. _(v0.4.0)_
[ ] Karte löschen → Flash-Meldung erscheint, Filter-Tab bleibt erhalten. _(v0.4.0)_

### Export _(v0.4.0)_

[ ] Export: Kopfzeile enthält echte Sprachnamen (z.B. "Deutsch;Englisch;..."), nicht "a;b;...". _(v0.4.0)_
[ ] Export: Dateiname = Listenname (keine "_export"-Endung). _(v0.4.0)_
[ ] Export: Keine HTML-Tags (&lt;p&gt;, &lt;br&gt; etc.) im exportierten Text. _(v0.4.0)_
[ ] Export → Import derselben Datei: alle Karten werden erkannt (Roundtrip). _(v0.4.0)_

---

## 6. Leitner

### Setup 

[ ] Setup-Seite: Breadcrumb "Startseite > Leitner" sichtbar, "Startseite"-Link führt zu home.php ohne Session zu starten. _(v0.5.0)_

### Setup — Richtungs-Labels _(v0.2.0)_

### Karten-Design _(v0.6.2)_

[ ] Leitner-Karte zentriert, max. Breite ~540px. _(v0.6.2)_
[ ] Auf dem iPhone: Karte sieht wie eine physische Karte aus. _(v0.6.2)_
[ ] Leitner-Karte und Drill-Karte haben identisches Aussehen und identische Grösse. _(v0.6.2)_

### Click-to-Flip _(v0.6.2)_

[ ] Klick auf Karte → horizontale Flip-Animation (scaleX), Rückseite erscheint danach. _(v0.6.2)_
[ ] Antwort-Buttons erscheinen erst nach Abschluss der Animation. _(v0.6.2)_
[ ] Flip-Animation identisch in Leitner und Drill. _(v0.6.2)_
[ ] Funktioniert per Finger auf dem iPhone (Touch-Event). _(v0.6.2)_

### Session verlassen _(v0.5.0)_

[ ] Während aktiver Session: Klick auf "Startseite" im Breadcrumb → Bestätigungsdialog erscheint. _(v0.5.0)_
[ ] Während aktiver Session: Klick auf App-Logo in Navbar → Bestätigungsdialog erscheint. _(v0.5.0)_
[ ] Während aktiver Session: Klick auf "Session abbrechen" → Bestätigungsdialog erscheint. _(v0.5.0)_
[ ] Dialog "Verlassen" bestätigen → Session wird server-seitig beendet, nächster Drill-Start beginnt frisch. _(v0.6.5)_
[ ] Setup- und Zusammenfassungsseite (kein aktives Karte): kein Dialog beim Klicken. _(v0.5.0)_

### Lernlogik 


### Abschluss 


---

## 7. Drill _(v0.6.0)_

### Start _(v0.6.0)_

[ ] Drill startet direkt aus Startseite (list_id in URL) — keine Konfigurationsseite. _(v0.6.0)_
[ ] Keine geeigneten Karten → Fehlermeldung, Weiterleitung zur Startseite. _(v0.6.0)_

### Einzelkarten-Ablauf _(v0.6.0)_

[ ] Karte zeigt beim Start nur Vorderseite (Frage + Sprachbezeichnung). _(v0.6.0)_
[ ] Klick auf Karte → Flip-Animation, Rückseite (Antwort) erscheint. _(v0.6.0)_
[ ] "Gewusst" und "Musste nachdenken" Buttons erscheinen erst nach Flip. _(v0.6.0)_
[ ] Nach Bewertung: nächste Karte erscheint sofort (PRG-Redirect). _(v0.6.0)_

### Timer & Fortschritt _(v0.6.0)_

[ ] Timer läuft sichtbar in der Navbar (MM:SS, rückwärts). _(v0.6.0)_
[ ] "X gemeistert" steht neben dem Timer und zählt nach oben wenn eine Karte gemeistert wird. _(v0.6.0)_
[ ] Nach Timer-Ablauf: aktuelle Karte kann noch fertig gespielt werden (Flip + Bewertung). _(v0.6.0)_
[ ] Danach: Abschlussseite erscheint. _(v0.6.0)_

### Gemeistert-Logik _(v0.6.0)_

[ ] Karte 3× hintereinander "Gewusst" → erscheint auf Abschlussseite als gemeistert. _(v0.6.0)_
[ ] "Musste nachdenken" dazwischen → Zähler auf 0, Karte muss wieder 3× hintereinander korrekt. _(v0.6.0)_
[ ] Gemeisterte Karte: drill_mastery in DB um 1 erhöht. _(v0.6.0)_

### "Musste nachdenken"-Limit _(v0.6.0)_

[ ] 5× "Musste nachdenken" für eine Karte → Karte verschwindet aus Session (drill_too_hard = 1). _(v0.6.0)_
[ ] drill_too_hard-Reset: Karte am nächsten Tag wieder im Pool vorhanden. _(v0.6.0)_

### Leitner-Übergang _(v0.6.0)_

[ ] 1. Mal gemeistert (drill_mastery = 1) → leitner_box = 2, next_due_date = heute + 2. _(v0.6.0)_
[ ] 2. Mal gemeistert (drill_mastery = 2) → leitner_box = 3, next_due_date = heute + 7. _(v0.6.0)_
[ ] 3. Mal gemeistert (drill_mastery = 3) → leitner_box = 4, next_due_date = heute + 14. _(v0.6.0)_

### Session verlassen _(v0.5.0)_

[ ] Während aktiver Drill-Session: Klick auf Breadcrumb/Logo → Bestätigungsdialog erscheint. _(v0.5.0)_
[ ] Während aktiver Drill-Session: Klick auf "Session abbrechen" → Bestätigungsdialog erscheint. _(v0.5.0)_
[ ] Dialog "Abbrechen" → Drill läuft weiter. _(v0.5.0)_
[ ] Dialog "Verlassen" → Session wird beendet, Navigation erfolgt. _(v0.6.5)_
[ ] Nach Verlassen: neuer Drill-Start mit gleicher Liste beginnt sauber (keine alte Session). _(v0.6.5)_

### Abschluss _(v0.6.0)_

[ ] Abschlussseite zeigt Kacheln: Gewusst (grün) / Musste nachdenken (orange) / Gemeistert (blau). _(v0.6.0)_
[ ] Gemeisterte Karten werden mit Wortpaar und drill_mastery-Badge (1×/2×/3×) aufgelistet. _(v0.6.0)_
[ ] Motivationstext: "Super! Weiter so!" wenn Karten gemeistert, sonst Aufmunterungstext. _(v0.6.0)_
[ ] "Für beste Resultate warte ein paar Stunden" Hinweis immer sichtbar. _(v0.6.0)_
[ ] "Erneut starten"-Button startet neue Session mit gleicher Liste. _(v0.6.0)_


---

## 8. Einstellungen (Localhost) _(v0.6.3)_

[ ] Auf localhost: "Einstellungen"-Link in Navbar der Startseite sichtbar. _(v0.6.3)_
[ ] Auf Prod-URL: kein Einstellungen-Link, settings.php gibt HTTP 403. _(v0.6.3)_
[ ] Alle 6 Einstellungen sichtbar, aktuelle Werte aus config.php korrekt angezeigt. _(v0.6.4)_
[ ] Werte ändern und "Alle speichern" → config.php enthält alle neuen Werte. _(v0.6.4)_
[ ] Nach Speichern: Flash-Meldung "Einstellungen gespeichert." erscheint. _(v0.6.4)_
[ ] Drill startet mit neuem Timer-Wert (z.B. 2 Minuten → Timer läuft auf 2:00). _(v0.6.4)_
[ ] Ungültiger Wert (ausserhalb Bereich) → Fehlermeldung(en), config.php unverändert. _(v0.6.4)_
[ ] Session-Timeout: neuer Wert wirkt (z.B. 1 Min. → nach 1 Min. Inaktivität abgemeldet). _(v0.6.4)_

---

## 9. Statistik 


