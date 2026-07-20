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
[ ] Streak-Badge (🔥 N Tage) erscheint in der Navbar auf allen Seiten wenn Streak aktiv. _(v0.7.0)_
[ ] Streak-Badge verschwindet wenn kein Streak (heute/gestern nicht gelernt). _(v0.7.0)_
[ ] Nach Person-Wechsel: Streak zeigt korrekten Wert für neue Person (nach home.php). _(v0.7.0)_
[ ] Startseite: Button "Meine Listen" führt zu lists.php (kein "Verwalten" mehr). _(v0.5.0)_
[ ] Startseite: kein "Mathe-Generator"-Button sichtbar. _(v0.5.0)_
[ ] lists.php: "Mathe-Generator"-Button rechts oben sichtbar, führt zu math.php. _(v0.5.0)_

---

## 4. Übersicht 


---

## 5. Listen (Import / Export / Bearbeiten)

### Listen verwalten 

[ ] lists.php: Container-Breite ist identisch mit home.php (kein schmalerer Inhalt mehr). _(v2.1.0)_
[ ] lists.php: bei genau einer eigenen Liste ist kein "Migrieren"-Button sichtbar. _(v2.1.0)_
[ ] Liste migrieren: Button "Migrieren" steht zwischen "Umbenennen" und "Löschen". _(v2.1.0)_
[ ] Liste migrieren: Auswahlfenster zeigt alle eigenen Listen ausser der Quellliste selbst als Ziel. _(v2.1.0)_
[ ] Liste migrieren: nach Migration sind alle Karten in der Zielliste, Quellliste ist leer (0 Karten). _(v2.1.0)_
[ ] Liste migrieren: Lernfortschritt (Leitner-Fach, next_due_date, Drill-Mastery) einer migrierten Karte bleibt exakt erhalten. _(v2.1.0)_
[ ] Liste migrieren: gleiches Sprachpaar Quelle/Ziel → keine Warnung, Migration läuft direkt durch. _(v2.1.0)_
[ ] Liste migrieren: unterschiedliches Sprachpaar Quelle/Ziel → Warnung erscheint, Abbrechen verhindert Migration. _(v2.1.0)_
[ ] Liste migrieren: unterschiedliches Sprachpaar, Warnung bestätigt → Migration wird trotzdem ausgeführt. _(v2.1.0)_
[ ] Liste migrieren: bereits vorhandenes gleiches Wort in Zielliste → keine Duplikat-Warnung, beide Karten bleiben bestehen. _(v2.1.0)_
[ ] Liste migrieren: Versuch mit manipulierter Zielliste einer anderen Person (z.B. per DevTools) → Fehlermeldung, keine Migration. _(v2.1.0)_

### Karten bearbeiten _(v0.6.1)_

[ ] Aktionsbuttons zeigen nur Icons (kein Text). _(v0.6.1)_
[ ] Hover über Icon-Button → Tooltip mit Bezeichnung erscheint. _(v0.6.1)_
[ ] CSV Import / Export: Icon + Text sichtbar. _(v0.6.1)_
[ ] Inline-Edit: alle 4 Felder (Wort A, Wort B, Beschreibung A, B) gleichbreit und vollständig sichtbar. _(v0.7.0)_
[ ] Speichern-Button zeigt ✓-Icon, Abbrechen zeigt ✕-Icon (mit Tooltip). _(v0.7.0)_


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
[ ] Langer Text (z.B. 35+ Zeichen): Frage benötigt max. 2 Zeilen auf der Karte. _(v0.7.1)_
[ ] Dieselbe Karte erscheint erneut → Antwort ist verdeckt, Flip muss manuell ausgelöst werden. _(v0.7.1)_

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
[ ] Einstellungen in 3 Gruppen: Allgemein, Leitner, Drill-Modus. _(v0.7.0)_
[ ] Alle 9 Einstellungen sichtbar, aktuelle Werte aus config.php korrekt angezeigt. _(v0.7.0)_
[ ] Seitentitel ändern → Navbar zeigt neuen Titel nach Speichern. _(v0.7.0)_
[ ] Default Kartenanzahl ändern → Leitner-Setup zeigt neuen Defaultwert. _(v0.7.0)_
[ ] Werte ändern und "Alle speichern" → config.php enthält alle neuen Werte. _(v0.6.4)_
[ ] Nach Speichern: Flash-Meldung "Einstellungen gespeichert." erscheint. _(v0.6.4)_
[ ] Drill startet mit neuem Timer-Wert (z.B. 2 Minuten → Timer läuft auf 2:00). _(v0.6.4)_
[ ] Ungültiger Wert (ausserhalb Bereich) → Fehlermeldung(en), config.php unverändert. _(v0.6.4)_
[ ] Session-Timeout: neuer Wert wirkt (z.B. 1 Min. → nach 1 Min. Inaktivität abgemeldet). _(v0.6.4)_

### Passwort ändern _(v0.8.0)_

[ ] Falsches aktuelles Passwort → Fehlermeldung, Passwort unverändert. _(v0.8.0)_
[ ] Neues Passwort unter 8 Zeichen → Fehlermeldung. _(v0.8.0)_
[ ] Neues Passwort und Wiederholung stimmen nicht überein → Fehlermeldung. _(v0.8.0)_
[ ] Korrektes aktuelles Passwort + gültiges neues Passwort → Flash "Passwort erfolgreich geändert.", Login mit neuem Passwort möglich. _(v0.8.0)_

---

## 9. Statistik 


---

## 10. MCP-Server _(v2.0.0, erweitert v2.0.1)_

Testvoraussetzung: `mcp-config.php` mit Token vorhanden, Apache läuft lokal.
Testtools: `curl` oder Claude Code mit `.mcp.json`.

### Authentifizierung _(v2.0.0, erweitert v2.0.1)_
[ ] POST ohne Authorization-Header und ohne `?token=` → HTTP 401, JSON-RPC-Fehler. _(v2.0.0)_
[ ] POST mit falschem Token (Header oder Query) → HTTP 401. _(v2.0.0)_
[ ] POST mit korrektem Token im Authorization-Header → Antwort korrekt. _(v2.0.0)_
[ ] POST ohne Authorization-Header, aber mit korrektem `?token=`-Query-Parameter → Antwort korrekt. _(v2.0.1)_
[ ] GET-Request → HTTP 405. _(v2.0.0)_
[ ] Ungültiger JSON-Body → HTTP 400, JSON-RPC-Fehler. _(v2.0.0)_

### initialize _(v2.0.0, erweitert v2.0.1)_
[ ] `initialize`-Request → Response enthält `protocolVersion`, `serverInfo.name = "learner-mcp"`, `serverInfo.version`. _(v2.0.0)_
[ ] `initialize`-Response enthält `instructions` mit dem Vokabel-Workflow (Person → Liste → Bestätigung → add_cards). _(v2.0.1)_

### tools/list _(v2.0.0)_
[ ] `tools/list` → Response enthält genau 3 Tools: `list_persons`, `list_lists`, `add_cards`. _(v2.0.0)_
[ ] `list_lists.inputSchema.required` enthält `person_id`. _(v2.0.0)_
[ ] `add_cards.inputSchema.required` enthält `list_id` und `cards`. _(v2.0.0)_

### list_persons _(v2.0.0)_
[ ] `list_persons` → gibt Array aller Personen mit `id` und `name` zurück. _(v2.0.0)_

### list_lists _(v2.0.0)_
[ ] `list_lists` ohne `person_id` → `isError: true`. _(v2.0.0)_
[ ] `list_lists` mit ungültiger `person_id` → `isError: true`. _(v2.0.0)_
[ ] `list_lists` mit gültiger `person_id` → gibt `person` und `lists` (mit `language_a`, `language_b`) zurück. _(v2.0.0)_

### add_cards _(v2.0.0)_
[ ] `add_cards` ohne `list_id` → `isError: true`. _(v2.0.0)_
[ ] `add_cards` mit leeren `cards` → `isError: true`. _(v2.0.0)_
[ ] `add_cards` mit 51 Karten → `isError: true` (Limit 50). _(v2.0.0)_
[ ] `add_cards` mit gültiger Liste und 1 neuer Karte → `status: "inserted"`, Karte in DB vorhanden. _(v2.0.0)_
[ ] Kein `card_progress`-Eintrag für neue Karte (lazy-init erst beim Leitner-Start). _(v2.0.0)_
[ ] Dieselbe Karte nochmals senden (kein force) → `status: "duplicate"`, Warnung mit Originalwerten. _(v2.0.0)_
[ ] Duplikat mit `force: true` → `status: "inserted"`, Karte trotzdem in DB. _(v2.0.0)_
[ ] Begriff > 500 Zeichen → `status: "error"` für diese Karte, restliche normal verarbeitet. _(v2.0.0)_
[ ] Leere `sprache_a_begriff` → `status: "error"`. _(v2.0.0)_
[ ] `beschreibung_a` leer → `desc_a` ist NULL in DB. _(v2.0.0)_
[ ] Gemischtes Batch (1 ok, 1 Duplikat, 1 Fehler) → `summary` zeigt korrekte Zahlen. _(v2.0.0)_
[ ] `tools/list` → `add_cards`-Beschreibung sowie `beschreibung_a`/`beschreibung_b`-Feldbeschreibungen erwähnen Grundform-Ergänzung bei Verben und Vermerk bei unregelmässigen Verben in der deutschen Beschreibung. _(v2.0.2)_

### Logging _(v2.0.0)_
[ ] Nach jedem Request: neuer Eintrag in `mcp.log` mit Zeitstempel, Umgebung, Methode, Tool-Name. _(v2.0.0)_

### Claude Code Integration _(v2.0.0)_
[ ] `.mcp.json` aus `.mcp.json.example` erstellt, Token eingetragen → Claude Code erkennt `learner-dev` Server. _(v2.0.0)_
[ ] "Füge [Begriff] zu Liste [Name] von Person [Name] hinzu" → Agent ruft list_persons, list_lists, add_cards auf, zeigt Resultat vor dem Einfügen zur Bestätigung. _(v2.0.0)_
[ ] "Füge das Verb [X] hinzu" → Begriff (Fremdsprache) ist Grundform, bei unregelmässigem Verb alle drei Formen; Beschreibung (Fremdsprache) ist Beispielsatz mit dem Begriff; Beschreibung (Deutsch) beschreibt die Bedeutung und vermerkt ggf. "unregelmässiges Verb". _(v2.0.2, verschärft v2.0.4)_
[ ] Kritischer Test: Deutsche Beschreibung enthält an keiner Stelle den fremdsprachigen Begriff (auch nicht in Anführungszeichen oder als Beispiel) — z.B. bei "bounced"/"unzustellbar" darf "bounced" nicht in der deutschen Beschreibung auftauchen. _(v2.0.4)_
[ ] Mehrdeutiger Begriff (z.B. Wort mit mehreren Bedeutungen) → deutsche Beschreibung nennt den konkreten Verwendungskontext. _(v2.0.4)_

### ChatGPT / Claude Desktop Integration _(v2.0.1)_
[ ] ChatGPT-Konnektor mit URL `.../mcp-server.php?token=...` eingerichtet → `tools/list` liefert alle 3 Tools. _(v2.0.1)_
[ ] ChatGPT: Karte über den Connector hinzufügen → Karte erscheint korrekt in der DB. _(v2.0.1)_
[ ] Claude Desktop via `mcp-remote` mit Authorization-Header eingerichtet → Server erreichbar. _(v2.0.1)_
[ ] claude.ai Browser-Konnektor (ohne OAuth) → schlägt wie erwartet fehl (bekannte Einschränkung, kein Bug). _(v2.0.1)_

