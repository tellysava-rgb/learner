Manuelle Testfälle für den Learner-Vokabeltrainer.

Jeder Test ist als Task mit [] gekennzeichnet. Nach erfolgreichem Test [x] setzen.
Jeder Abschnitt oder Test trägt einen Release-Verweis _(vX.Y.Z)_ — zeigt ab welchem Release dieser Test relevant ist.

---

## 1. Login / DB _(v0.1.0)_


---

## 2. Übersicht _(v0.1.0)_


---

## 3. Listen (Import / Export / Bearbeiten)

### Listen verwalten _(v0.1.0)_


### Karten bearbeiten _(v0.1.0)_


### Import _(v0.1.0)_


### Export _(v0.1.0)_



### Entdecken _(v0.1.0)_


### Mathe-Generator _(v0.1.0)_

[ ] Mathe-Generator: Multiplikationsdecks existiert bereits → beim Erstellen eines zweiten erscheint Warnung mit Checkbox. _(v0.4.0)_
[ ] Checkbox anhaken → zweites Multiplikationsdeck wird erstellt (unabhängig vom Namen). _(v0.4.0)_
[ ] Gleiches für Division: zweites Divisionsdeck erfordert Bestätigung. _(v0.4.0)_
[ ] Formularwerte (Typ, Von, Bis, Name) bleiben bei Warnung erhalten. _(v0.4.0)_
[ ] Erstes Multiplikationsdeck → kein Warning, direkte Erstellung. _(v0.4.0)_

### Listen verwalten (PRG) _(v0.4.0)_

[ ] Liste umbenennen → nach Speichern verschwindet das Editierformular. _(v0.4.0)_
[ ] Erfolgs-Flash erscheint nach Umbenennen (PRG). _(v0.4.0)_

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

## 4. Leitner

### Setup _(v0.1.0)_

[ ] Setup-Seite zeigt "← Startseite"-Button. _(v0.4.0)_
[ ] Zurück-Button führt zur Startseite ohne Session zu beeinflussen. _(v0.4.0)_

### Setup — Richtungs-Labels _(v0.2.0)_

[ ] Setup mit vorausgewählter Liste: Richtungs-Labels zeigen echte Sprachnamen (z.B. "Deutsch → Englisch"), nicht "A → B".
[ ] `learn.php` direkt ohne `list_id` aufrufen → Labels zeigen "A → B" bis eine Liste gewählt wird, dann aktualisieren sie sich per JS.
[ ] Andere Liste wählen → Labels passen sich sofort an (kein Seitenneuladen).

### Karten-Design _(v0.2.0)_

[ ] Auf dem iPhone: Karte sieht wie eine physische Karte aus.

### Click-to-Flip _(v0.2.0)_

[ ] Funktioniert per Finger auf dem iPhone (Touch-Event).

### Lernlogik _(v0.1.0)_


### Abschluss _(v0.1.0)_


---

## 5. Drill

### Start _(v0.1.0)_


### Timer _(v0.2.0)_


### Karten-Design _(v0.2.0)_


### Alle 3 Karten gleichzeitig _(v0.2.0)_


### Lernlogik _(v0.1.0)_


### Abschluss _(v0.1.0)_

[ ] Nach Timer-Ablauf und leerer Queue: Abschlussseite erscheint.
[ ] Abschlussseite zeigt Gewusst / Musste nachdenken / Gemeistert korrekt an. _(v0.4.0)_
[ ] "Zur Startseite"-Button führt zurück zur Übersicht.
[ ] "Erneut starten"-Button startet sofort neue Session mit gleicher Liste. _(v0.2.0)_

---

## 6. Statistik _(v0.1.0)_

[ ] Statistik ohne list_id aufrufen → automatische Weiterleitung zur ersten Liste. _(v0.4.0)_
[ ] Kein "Alle Listen"-Button sichtbar. _(v0.4.0)_
[ ] Statistik-Seite aufrufen → Karten pro Leitner-Fach werden korrekt angezeigt.
[ ] Lernstreak stimmt mit der tatsächlichen Lernhistorie überein.
[ ] Drill-Fortschritt (1×, 2×, 3× gemeistert) wird pro Liste korrekt angezeigt.
[ ] Drill-Statistik zeigt "musste nachdenken" (nicht "nicht gewusst"). _(v0.4.0)_
[ ] Richtig/Falsch-Statistik stimmt mit den durchgeführten Sessions überein.
