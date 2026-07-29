Manuelle Testfälle für den Learner-Vokabeltrainer.

Jeder Test ist als Task mit [] gekennzeichnet. Nach erfolgreichem Test [x] setzen.
Jeder Abschnitt oder Test trägt einen Release-Verweis _(vX.Y.Z)_ — zeigt ab welchem Release dieser Test relevant ist.

---

## 1. Login / DB

### Login mit Name + eigenem Passwort _(v3.0.0)_

[ ] Login-Formular zeigt Feld "Name" und Feld "Passwort" (kein reines Passwort-Formular mehr). _(v3.0.0)_
[ ] Korrekter Name + korrektes Passwort → Login gelingt, landet direkt auf der eigenen Startseite (kein "Wer bist du?"-Zwischenschritt mehr). _(v3.0.0)_
[ ] Falscher Name (existiert nicht) → generische Fehlermeldung "Name oder Passwort falsch.", kein Hinweis dass der Name unbekannt ist. _(v3.0.0)_
[ ] Korrekter Name, falsches Passwort → dieselbe generische Fehlermeldung. _(v3.0.0)_
[ ] Bereits eingeloggt und `index.php` erneut aufgerufen → Redirect zu `home.php`. _(v3.0.0)_
[ ] Migration (einmalig nach Update): jede bestehende Person kann sich mit ihrem bisherigen Namen und dem Passwort `123456` einloggen. _(v3.0.0)_
[ ] Migration: Person "Beat" hat nach der Migration Admin-Status (sichtbar z.B. an "Einstellungen"-Link und "Person wechseln" in der Navbar). _(v3.0.0)_

### Neuinstallation ohne Migrationspfad (`install.php`) _(v3.2.1)_
[ ] Frische, leere Datenbank: Schritt 1 ("Tabellen erstellen") legt alle Tabellen inkl. `password_hash`/`is_admin`/`email`/Reset-Token-Spalten in `persons` an, ohne dass danach noch Migrationen nötig sind. _(v3.2.1)_
[ ] Schritt 2 zeigt ein Formular "Ersten Admin anlegen" (Name + Passwort + Wiederholung), solange noch keine Person existiert. _(v3.2.1)_
[ ] Schritt 2 mit gültigen Werten → Person wird in `persons` angelegt mit `is_admin = 1`, Erfolgsmeldung, Login mit diesem Namen/Passwort funktioniert sofort auf `index.php`. _(v3.2.1)_
[ ] Schritt 2 mit Passwort unter 8 Zeichen oder abweichender Wiederholung → Fehlermeldung, keine Person angelegt. _(v3.2.1)_
[ ] Nachdem eine Person existiert: Schritt 2 zeigt "Erstellt" und einen Hinweis auf `users.php` statt eines erneuten Anlage-Formulars — kein zweiter Admin über `install.php` anlegbar. _(v3.2.1)_
[ ] Nach Schritt 1 + 2 auf einer frischen Installation: `run_pending_migrations()` (nächster Seitenaufruf) läuft fehlerfrei durch und verändert nichts an der bereits angelegten Person (Migration 6 ist No-Op, da `password_hash` schon gesetzt ist und "Beat" ggf. gar nicht existiert). _(v3.2.1)_

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

### Hilfe-Icon in Navbar _(v2.8.0)_

[ ] Auf jeder Seite mit Navbar erscheint ganz rechts, nach dem Logout-Button, ein Icon-Button (`bi-info-lg`, Tooltip "Hilfe"). _(v2.8.0)_
[ ] Klick auf das Icon führt zu `help.php`. _(v2.8.0)_
[ ] `help.php` ist nach dem Login direkt erreichbar (`require_person()` — Login löst immer direkt eine Person auf, kein separater Personenwahl-Schritt mehr). _(v2.8.0, Login-Modell aktualisiert v3.0.0)_
[ ] `help.php` ohne Login → Redirect zu `index.php`. _(v2.8.0)_
[ ] Auf `learn.php`/`drill.php`: Hilfe-Icon erscheint auch während einer aktiven Session (wenn statt Logout "Session abbrechen" angezeigt wird). _(v2.8.0)_
[ ] `help.php`: Breadcrumb zeigt "Startseite > Hilfe". _(v2.8.0)_
[ ] `help.php`: Accordion mit 9 Abschnitten, erster Abschnitt initial aufgeklappt, restliche eingeklappt; Klick klappt jeweils auf/zu. _(v2.8.0, 9. Abschnitt "Für Admins" ergänzt v3.0.0)_
[ ] `help.php`: Abschnitt "Für Admins: Einstellungen & Benutzerverwaltung" beschreibt Einstellungen, Benutzerverwaltung und "Person wechseln" korrekt als admin-only. _(v3.0.0)_
[ ] `help.php`: Logout-Button auf der Hilfeseite funktioniert wie auf jeder anderen Seite. _(v2.8.0)_
[ ] `help.php`: Abschnitt "Leitner-Modus" nennt die tatsächlich konfigurierten Intervalle (Fach 2–5 aus `LEITNER_INTERVALS`), das tägliche Warteschlangen-Limit (`DAILY_CARD_LIMIT`) und die Default-Kartenanzahl pro Session (`LEITNER_DEFAULT_CARDS`) — Werte müssen mit `settings.php` übereinstimmen. _(v3.0.2)_
[ ] `help.php`: Abschnitt "Drill-Modus" nennt die tatsächlich konfigurierte Session-Dauer in Minuten (`DRILL_SESSION_SECONDS`), das Bekannt/Neu-Verhältnis (`DRILL_KNOWN_RATIO`), die Mastery-Schwelle (`DRILL_MASTERY_THRESHOLD`) und das "Musste nachdenken"-Limit (`DRILL_TOO_HARD_LIMIT`) — Werte müssen mit `settings.php` übereinstimmen. _(v3.0.2)_
[ ] Wird ein Wert in `settings.php` geändert (z.B. `DRILL_SESSION_SECONDS`), zeigt `help.php` beim nächsten Aufruf automatisch den neuen Wert, ohne Code-Änderung. _(v3.0.2)_

---

## 4. Übersicht 

### Startseite: Fälligkeits-Info & Icon-Buttons _(v2.7.0)_

[ ] Liste mit heute fälligen Leitner-Karten zeigt "📚 N heute fällig" unterhalb von "⏳ N in Warteschlange". _(v2.7.0)_
[ ] Liste ohne Warteschlange zeigt "⏳ Keine in Warteschlange" (nicht ausgeblendet). _(v2.7.2)_
[ ] Liste ohne heute fällige Karten zeigt "✅ Keine heute fällig" mit Häkchen-Icon statt 📚 (nicht ausgeblendet). _(v2.7.2)_
[ ] Anzeige zählt nur aktive Karten mit `next_due_date` heute oder in der Vergangenheit, nicht die gesamte Kartenzahl. _(v2.7.0)_
[ ] Drei Icon-Buttons erscheinen oben rechts neben dem Listennamen: Liste bearbeiten (Stift `bi-pencil`), Karten bearbeiten (Stift-im-Quadrat `bi-pencil-square`), Statistik (Balkendiagramm). _(v2.7.0, v2.7.6, v2.7.7)_
[ ] Klick auf "Liste bearbeiten" führt zu `lists.php?edit=X` mit dieser Liste im Inline-Bearbeiten-Formular. _(v2.7.6)_
[ ] Klick auf "Karten bearbeiten" führt zu `edit.php?list_id=X` (Kartenübersicht dieser Liste). _(v2.7.6)_
[ ] "Liste bearbeiten" und "Karten bearbeiten" nutzen unterscheidbare Icons (nicht beide denselben Stift). _(v2.7.6, v2.7.7)_
[ ] Klick auf Statistik-Icon führt direkt zu `stats.php?list_id=X` mit dieser Liste vorausgewählt, nicht zur allgemeinen Übersicht. _(v2.7.0)_
[ ] "Bearbeiten"-Textbutton ist aus dem Footer verschwunden, Leitner/Drill bleiben dort als grosse Buttons. _(v2.7.0)_

### Listen-Status Aktiv/Inaktiv _(v3.3.0)_

[ ] Neu erstellte Liste ist standardmässig aktiv, erscheint im normalen Listen-Bereich mit Warteschlange/"heute fällig"/Leitner/Drill. _(v3.3.0)_
[ ] Aktive Liste hat zusätzlich zu Leitner/Drill einen Icon-Button unten rechts im Footer: `bi-check-circle-fill`, Tooltip "Inaktiv setzen". _(v3.3.0)_
[ ] Klick auf "Inaktiv setzen" → Liste verschwindet aus dem aktiven Bereich, erscheint stattdessen kompakt im neuen Bereich "Inaktive Listen" darunter. _(v3.3.0)_
[ ] Karten in "Inaktive Listen" zeigen NUR Name, Sprachpaar und Kartenzahl — keine Warteschlangen-/Fällig-Anzeige, keine Leitner-/Drill-Buttons — und sind sichtbar kleiner/kompakter als aktive Listen-Karten. _(v3.3.0)_
[ ] Inaktive Listen-Karte hat einen Icon-Button unten rechts: `bi-circle text-secondary`, Tooltip "Aktiv setzen". _(v3.3.0)_
[ ] Klick auf "Aktiv setzen" → Liste erscheint wieder im normalen aktiven Bereich mit allen gewohnten Anzeigen. _(v3.3.0)_
[ ] Bereich "Inaktive Listen" erscheint nur, wenn mindestens eine inaktive Liste existiert — sonst nicht sichtbar. _(v3.3.0)_
[ ] Alle Listen einer Person inaktiv gesetzt → aktiver Bereich zeigt "Keine aktiven Listen." statt einer leeren Kartengruppe. _(v3.3.0)_
[ ] Leitner-Fortschritt einer inaktiven Liste läuft im Hintergrund unverändert weiter (z.B. `next_due_date` verschiebt sich normal weiter) — Inaktiv-Status pausiert nur die Anzeige, nicht die Lernmechanik. _(v3.3.0)_
[ ] `lists.php` (separate Listenverwaltung) ist von der Aktiv/Inaktiv-Unterscheidung unberührt — zeigt weiterhin alle Listen unabhängig vom Status. _(v3.3.0)_

### MCP-Server: Aktiv/Inaktiv-Filter _(v3.3.0)_

[ ] `list_lists` ohne `include_inactive` gibt nur aktive Listen zurück, inaktive fehlen komplett in der Antwort. _(v3.3.0)_
[ ] `list_lists` mit `include_inactive=true` gibt zusätzlich inaktive Listen zurück, jeweils mit `is_active: 0` gekennzeichnet. _(v3.3.0)_
[ ] Nennt der User im Gespräch eine Liste beim Namen, die nicht unter den aktiven Listen auftaucht, fragt der Agent nicht "existiert nicht", sondern ruft `list_lists` erneut mit `include_inactive=true` auf. _(v3.3.0)_
[ ] `add_cards` funktioniert unverändert auch mit der `list_id` einer inaktiven Liste (kein Status-Check beim Einfügen). _(v3.3.0)_

---

## 5. Listen (Import / Export / Bearbeiten)

### Listen verwalten 

[ ] lists.php: Container-Breite ist identisch mit home.php (kein schmalerer Inhalt mehr). _(v2.1.0)_
[ ] lists.php: bei genau einer eigenen Liste ist kein "Migrieren"-Button sichtbar. _(v2.1.0)_
[ ] Liste migrieren: Button "Migrieren" steht zwischen "Bearbeiten" und "Löschen". _(v2.3.0)_
[ ] Liste migrieren: Auswahlfenster zeigt alle eigenen Listen ausser der Quellliste selbst als Ziel. _(v2.1.0)_
[ ] Liste migrieren: nach Migration sind alle Karten in der Zielliste, Quellliste ist leer (0 Karten). _(v2.1.0)_
[ ] Liste migrieren: Lernfortschritt (Leitner-Fach, next_due_date, Drill-Mastery) einer migrierten Karte bleibt exakt erhalten. _(v2.1.0)_
[ ] Liste migrieren: gleiches Sprachpaar Quelle/Ziel → keine Warnung, Migration läuft direkt durch. _(v2.1.0)_
[ ] Liste migrieren: unterschiedliches Sprachpaar Quelle/Ziel → Warnung erscheint, Abbrechen verhindert Migration. _(v2.1.0)_
[ ] Liste migrieren: unterschiedliches Sprachpaar, Warnung bestätigt → Migration wird trotzdem ausgeführt. _(v2.1.0)_
[ ] Liste migrieren: bereits vorhandenes gleiches Wort in Zielliste → keine Duplikat-Warnung, beide Karten bleiben bestehen. _(v2.1.0)_
[ ] Liste migrieren: Versuch mit manipulierter Zielliste einer anderen Person (z.B. per DevTools) → Fehlermeldung, keine Migration. _(v2.1.0)_

### Aussprache-Sprachcode & Audio _(v2.2.0)_

[ ] Liste erstellen/bearbeiten: Feld "Aussprache-Sprachcode" mit Autovervollständigung (Datalist) zeigt kuratierte Vorschläge. _(v2.2.0)_
[ ] Gültiger Code (z.B. en-GB) wird gespeichert und beim erneuten Öffnen des Bearbeiten-Formulars korrekt vorausgefüllt. _(v2.2.0)_
[ ] Ungültiger Code (z.B. en-UK) wird beim Speichern abgelehnt, Fehlermeldung erscheint. _(v2.2.0)_
[ ] Code in falscher Gross-/Kleinschreibung (z.B. EN-gb) wird automatisch zu en-GB normalisiert gespeichert. _(v2.2.0)_
[ ] Leeres Feld ist zulässig (Code bleibt optional). _(v2.2.0)_
[ ] Leitner-Karte: 🔊-Button erscheint nur auf der Seite, wo Sprache B angezeigt wird (abhängig von Lernrichtung A→B / B→A / Gemischt). _(v2.2.0)_
[ ] Leitner-Karte: Liste ohne Aussprache-Code → kein 🔊-Button sichtbar. _(v2.2.0)_
[ ] Drill-Karte: 🔊-Button erscheint nur auf der Rückseite (Sprache B), sofern Code gesetzt. _(v2.2.0)_
[ ] Klick auf 🔊-Button spielt den Begriff ab und löst NICHT das Umdrehen der Karte aus. _(v2.2.0)_
[ ] "Entdecken" → Liste kopieren: Aussprache-Code der Originalliste wird in die Kopie übernommen. _(v2.2.0)_
[ ] MCP `list_lists`: Antwort enthält `speech_lang_b` je Liste. _(v2.2.0)_

### Listen verwalten — Feinschliff _(v2.3.0)_

[ ] lists.php: Button heisst "Bearbeiten" statt "Umbenennen" (Formular/Verhalten unverändert). _(v2.3.0)_
[ ] lists.php: Eingabefeld für Aussprache-Sprachcode ist deutlich schmaler als vorher (ca. halbe Breite). _(v2.3.0)_

### Lautschrift pro Karte _(v2.3.0)_

[ ] edit.php: Container-Breite identisch mit home.php/lists.php (kein schmalerer Inhalt mehr). _(v2.3.0)_
[ ] edit.php: Beschreibung A/B sind mehrzeilige Textfelder (Textarea) statt einzeiliger Inputs, sowohl im "Neue Karte"-Formular als auch im Inline-Bearbeiten. _(v2.3.0)_
[ ] edit.php: Bei einer Liste mit Aussprache-Code erscheint ein Eingabefeld "Lautschrift" (Neue Karte + Bearbeiten). _(v2.3.0)_
[ ] edit.php: Bei einer Liste ohne Aussprache-Code (z.B. Mathe-Liste) erscheint kein Lautschrift-Feld. _(v2.3.0)_
[ ] edit.php: Erfasste Lautschrift wird in der Kartenübersicht unter dem Begriff in Sprache B angezeigt (in eckigen Klammern). _(v2.3.0)_
[ ] Wird der Aussprache-Code einer Liste nachträglich entfernt, bleibt eine zuvor erfasste Lautschrift beim Speichern einer Karte erhalten (wird nicht stillschweigend gelöscht). _(v2.3.0)_
[ ] Leitner-Karte: Lautschrift erscheint unter dem Begriff in Sprache B, auf der jeweils richtigen Seite je nach Lernrichtung. _(v2.3.0)_
[ ] Drill-Karte: Lautschrift erscheint unter dem Begriff in Sprache B auf der Kartenrückseite. _(v2.3.0)_
[ ] Audio: Bei installierter passender Stimme (z.B. en-GB) auf dem Testgerät wird diese verwendet, nicht die Systemstandardstimme. _(v2.3.0)_

### Lautschrift in CSV & MCP _(v2.4.0)_

[ ] CSV-Export: 5. Spalte "Lautschrift" enthält den Wert von `phonetic_b`, leer wenn nicht gesetzt. _(v2.4.0)_
[ ] CSV-Import: 5-spaltige CSV (mit Lautschrift) wird korrekt importiert, Wert landet in `phonetic_b`. _(v2.4.0)_
[ ] CSV-Import: alte 4-spaltige CSV (ohne Lautschrift-Spalte) funktioniert weiterhin fehlerfrei, `phonetic_b` bleibt leer. _(v2.4.0)_
[ ] Import-Review-Ansicht zeigt eine "Lautschrift"-Spalte in der Vorschau neuer Karten. _(v2.4.0)_
[ ] Downloadbare CSV-Vorlage enthält die neue Spalte inkl. Beispielwert. _(v2.4.0)_
[ ] MCP `add_cards`: `phonetik_b` wird korrekt in `phonetic_b` gespeichert (verifiziert gegen Dev-DB). _(v2.4.0)_
[ ] MCP `add_cards`: Feld länger als 200 Zeichen wird mit Fehlermeldung abgelehnt. _(v2.4.0)_
[ ] MCP-Agent befüllt `phonetik_b` nur bei Listen mit `speech_lang_b`, lässt es bei Listen ohne Sprachcode leer. _(v2.4.0)_

### MCP: bestehende Karten lesen/ändern _(v2.6.0)_

[ ] MCP `list_cards`: gibt Listen-Metadaten + alle Karten inkl. `card_id` und `phonetik_b` zurück (verifiziert gegen Dev-DB). _(v2.6.0)_
[ ] MCP `update_card`: einzelnes Feld (z.B. `phonetik_b`) ändern → nur dieses Feld ändert sich, restliche Felder bleiben unverändert (verifiziert gegen Dev-DB). _(v2.6.0)_
[ ] MCP `update_card`: leerer `sprache_a_begriff`/`sprache_b_begriff` wird mit Fehlermeldung abgelehnt (verifiziert). _(v2.6.0)_
[ ] MCP `update_card`: unbekannte `card_id` liefert Fehlermeldung, keine Änderung (verifiziert). _(v2.6.0)_
[ ] MCP-Agent zeigt vor `update_card`-Aufrufen dem User pro Karte alt → neu und wartet auf Bestätigung, ändert nie ungefragt automatisch.
[ ] Lautschrift bei `en-GB`/nicht-rhotischen Listen: "r" nach Vokal vor Konsonant/am Wortende fehlt (z.B. "THUN-duh" nicht "THUN-der"), "r" bleibt vor Vokal (z.B. "rayn"). _(v2.6.1)_
[ ] Lautschrift bei `en-US`/rhotischen Listen: "r" wird normal geschrieben (unverändert). _(v2.6.1)_

### Direktlink pro Karte _(v2.5.0, überarbeitet v2.5.2, Icon v2.5.3)_

[ ] edit.php: Augen-Symbol-Button ("Karte ansehen") steht als erstes in der Aktionsleiste, vor "Bearbeiten". _(v2.5.3)_
[ ] Klick auf Augen-Symbol öffnet ein Modal mit der Karte als Lernkarte (wie Leitner/Drill), nicht nur eine markierte Tabellenzeile. _(v2.5.2)_
[ ] Modal-Karte zeigt zuerst Sprache A, Tippen deckt Sprache B auf (Flip-Animation). _(v2.5.2)_
[ ] Bei Liste mit Aussprache-Code: 🔊-Button und Lautschrift erscheinen auf der aufgedeckten Rückseite. _(v2.5.2)_
[ ] Direktlink funktioniert unabhängig vom aktuell gewählten Filter (Alle/Aktiv/Warteschlange/Archiviert). _(v2.5.2)_
[ ] Modal schliessen funktioniert über "X" und Klick ausserhalb. _(v2.5.2)_

### Scroll-Position beim Bearbeiten _(v2.6.2)_

[ ] edit.php: Klick auf "Bearbeiten" bei einem Eintrag weiter unten in der Liste → Seite bleibt an dieser Position, kein Sprung nach oben. _(v2.6.2)_
[ ] edit.php: Klick auf "Abbrechen" im Inline-Bearbeiten-Formular → Seite bleibt ebenfalls an der Position, kein Sprung nach oben. _(v2.6.2)_

### Fach-Filter _(v2.7.8)_

[ ] edit.php: neben den Status-Filtern (Alle/Aktiv/Warteschlange/Archiviert) erscheinen, optisch abgetrennt, fünf zusätzliche Filter-Buttons "Fach 1"–"Fach 5" mit Anzahl-Badge. _(v2.7.8)_
[ ] Klick auf "Fach N" zeigt nur aktive Karten mit `leitner_box = N`. _(v2.7.8)_
[ ] Karten in der Warteschlange oder archivierte Karten erscheinen in keinem Fach-Filter, auch wenn `leitner_box` einen alten Wert enthält. _(v2.7.8)_
[ ] Anzahl-Badge pro Fach-Filter stimmt mit der tatsächlichen Kartenzahl in diesem Fach überein. _(v2.7.8)_
[ ] Ungültiger `filter`-Parameter in der URL (z.B. manuell editiert) fällt auf "Alle" zurück statt Fehler oder leere Ansicht. _(v2.7.8)_

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

### Prompt für KI-generierte Wortlisten _(v2.7.9)_

[ ] import.php: im CSV-Format-Bereich erscheint unterhalb des Beispiels ein Textfeld mit einem fertigen Prompt sowie ein Button "Prompt kopieren". _(v2.7.9)_
[ ] Prompt enthält die korrekten Sprachnamen dieser Liste (`language_a`/`language_b`) in Kopfzeile und Regeltext. _(v2.7.9)_
[ ] Liste mit `speech_lang_b = en-GB` (oder en-AU/en-NZ/en-ZA): Prompt enthält die nicht-rhotische Lautschrift-Regel (r nach Vokal weglassen). _(v2.7.9)_
[ ] Liste mit `speech_lang_b = en-US` (oder anderer rhotischer Dialekt): Prompt enthält die rhotische Lautschrift-Regel (r normal aussprechen). _(v2.7.9)_
[ ] Liste ohne `speech_lang_b`: Prompt weist die KI an, explizit nach dem gewünschten Aussprache-Dialekt zu fragen und die Lautschrift danach auszufüllen — nicht mehr leer zu lassen. _(v2.7.12, ersetzt v2.7.9)_
[ ] Klick auf "Prompt kopieren" kopiert den vollständigen Text in die Zwischenablage, Bestätigung "Kopiert!" erscheint kurz. _(v2.7.9)_
[ ] Eine mit diesem Prompt von einer KI erzeugte CSV-Ausgabe lässt sich ohne Anpassung über das Upload-Formular importieren. _(v2.7.9)_

### Dialekt-Standard Englisch im KI-Prompt _(v2.7.11)_

[ ] Liste mit Sprache B = Englisch und `speech_lang_b = en-GB`: Prompt enthält die Anweisung, dass die Listen-Definition (en-GB) Vorrang hat. _(v2.7.11)_
[ ] Liste mit Sprache B = Englisch und `speech_lang_b = en-US`: Prompt enthält die Anweisung, dass die Listen-Definition (en-US) Vorrang hat. _(v2.7.11)_
[ ] Liste mit Sprache B = Englisch OHNE `speech_lang_b`: Prompt enthält die Anweisung, standardmässig britisches Englisch (en-GB) zu verwenden, ausser beim Thema wird ausdrücklich ein anderer Dialekt verlangt. _(v2.7.11)_
[ ] Liste mit Sprache B = einer anderen Sprache als Englisch (z.B. Französisch): Prompt enthält KEINE GB/US-Dialekt-Regel. _(v2.7.11)_
[ ] Test mit echter KI: Prompt ohne `speech_lang_b` an eine KI gegeben → generierte Begriffe/Beispielsätze verwenden britische statt amerikanische Schreibweise (z.B. "colour" statt "color"). _(v2.7.11)_

### Lautschrift-Rückfrage ohne speech_lang_b _(v2.7.12)_

[ ] Liste ohne `speech_lang_b`: Prompt weist die KI an, den User explizit nach dem gewünschten Aussprache-Dialekt zu fragen, bevor die Lautschrift-Spalte gefüllt wird. _(v2.7.12)_
[ ] Ist der Dialekt bereits beim Thema-Platzhalter angegeben (z.B. "Reisen, amerikanisches Englisch"), entfällt laut Prompt die Rückfrage. _(v2.7.12)_
[ ] Test mit echter KI: Prompt ohne `speech_lang_b`, ohne Dialekt-Angabe beim Thema → KI fragt im Chat nach dem gewünschten Dialekt, statt die Lautschrift-Spalte stillschweigend leer zu lassen oder zu raten. _(v2.7.12)_
[ ] Nach Antwort des Users (z.B. "britisches Englisch") → generierte Lautschrift folgt der passenden Konvention (nicht-rhotisch bei britischem Englisch, rhotisch bei amerikanischem). _(v2.7.12)_
[ ] MCP-Server (`add_cards`/`update_card`) bleibt unverändert: `phonetik_b` wird weiterhin nur befüllt wenn die Liste ein `speech_lang_b` hat, sonst leer gelassen — keine Rückfrage-Logik im MCP. _(v2.7.12)_

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

## 8. Einstellungen (nur Admin) _(v0.6.3, admin-only seit v3.0.0)_

[ ] Als Admin eingeloggt: "Einstellungen"-Link in Navbar der Startseite sichtbar. _(v3.0.0)_
[ ] Als Nicht-Admin eingeloggt: kein "Einstellungen"-Link in der Navbar sichtbar. _(v3.0.0)_
[ ] Als Nicht-Admin `settings.php` direkt per URL aufgerufen → Redirect zu `home.php` mit Fehlermeldung "Nur für Admins zugänglich.", keine Einstellungen sichtbar. _(v3.0.0)_
[ ] Einstellungen in 3 Gruppen: Allgemein, Leitner, Drill-Modus. _(v0.7.0)_
[ ] Alle 9 Einstellungen sichtbar, aktuelle Werte aus config.php korrekt angezeigt. _(v0.7.0)_
[ ] Seitentitel ändern → Navbar zeigt neuen Titel nach Speichern. _(v0.7.0)_
[ ] Default Kartenanzahl ändern → Leitner-Setup zeigt neuen Defaultwert. _(v0.7.0)_
[ ] Werte ändern und "Alle speichern" → config.php enthält alle neuen Werte. _(v0.6.4)_
[ ] Nach Speichern: Flash-Meldung "Einstellungen gespeichert." erscheint. _(v0.6.4)_
[ ] Drill startet mit neuem Timer-Wert (z.B. 2 Minuten → Timer läuft auf 2:00). _(v0.6.4)_
[ ] Ungültiger Wert (ausserhalb Bereich) → Fehlermeldung(en), config.php unverändert. _(v0.6.4)_
[ ] Session-Timeout: neuer Wert wirkt (z.B. 1 Min. → nach 1 Min. Inaktivität abgemeldet). _(v0.6.4)_
[ ] Session-Timeout: Wert bis 1440 (24 Std.) wird akzeptiert, Wert darüber abgelehnt. _(v2.7.4)_
[ ] Session-Timeout: in `config-runtime.php` steht nach dem Speichern die eingegebene Minutenzahl direkt (nicht ×60 in Sekunden). _(v2.7.5)_
[ ] Session-Timeout: Abmeldung nach Inaktivität erfolgt weiterhin nach der korrekten Anzahl Minuten (z.B. 1 Min. testen). _(v2.7.5)_
[ ] Session-Timeout auf hohen Wert (z.B. 1440 Min.) gestellt: Cookie-Header (`Set-Cookie`) zeigt `Max-Age`/`expires` passend zu diesem Wert (nicht mehr Session-Cookie ohne Ablaufzeit). _(v2.7.13)_
[ ] Bei hohem Session-Timeout: User bleibt über mehrere Stunden Inaktivität (deutlich über 24 Min.) eingeloggt, statt vorzeitig durch PHPs Standard-`gc_maxlifetime` (24 Min.) abgemeldet zu werden. _(v2.7.13)_
[ ] Auf Prod (HTTPS): Cookie hat zusätzlich das `Secure`-Flag; auf Localhost (HTTP) fehlt es. _(v2.7.13)_
[ ] Nach Login: neue Datei `sess_<id>` erscheint in `includes/sessions/`, nicht im System-Standardpfad (z.B. `/tmp` oder `/var/lib/php/sessions`). _(v2.7.14)_
[ ] Direktzugriff per Browser/curl auf eine Datei unter `includes/sessions/` → HTTP 403 (durch `.htaccess` blockiert). _(v2.7.14)_
[ ] Direktzugriff auf `includes/sessions/.htaccess` selbst → ebenfalls HTTP 403. _(v2.7.14)_
[ ] Auf Prod: User bleibt auch nach mehreren Stunden Inaktivität (deutlich über 24 Min.) eingeloggt, KEIN vorzeitiger Logout mehr trotz Hoster-Cron. _(v2.7.14, behebt Wiederauftreten von v2.7.13)_
[ ] Nach längerer Nutzung: alte Session-Dateien in `includes/sessions/` werden mit der Zeit bereinigt, sammeln sich nicht unbegrenzt an (PHPs eigene GC läuft für das eigene Verzeichnis). _(v2.7.14)_

### Deploy nur mit Admin-Session _(v3.0.0)_

[ ] `deploy.php` direkt per URL mit gültigem Token, aber OHNE eingeloggte Admin-Session (z.B. neuer Browser/Incognito) → HTTP 403 "Admin-Login erforderlich...". _(v3.0.0)_
[ ] `deploy.php` mit gültigem Token UND aktiver Admin-Session → funktioniert wie bisher (Version wird aktualisiert). _(v3.0.0)_
[ ] "Deploy starten"-Button in `settings.php` funktioniert weiterhin unverändert (Browser-Session wird automatisch mitgeschickt). _(v3.0.0)_
[ ] Eingeloggt als Nicht-Admin: `deploy.php` mit gültigem Token → ebenfalls HTTP 403. _(v3.0.0)_

### Versions-Vergleich (Pfeil/Gleichheitszeichen) _(v3.0.1)_

[ ] `settings.php`, Bereich "Deployment": installierte Version ≠ GitHub-Version → Pfeil `←` zwischen den beiden Versionsangaben. _(v3.0.1)_
[ ] `settings.php`: installierte Version = GitHub-Version → `=` statt Pfeil, zusätzlich weiterhin "✓ Bereits auf dem neuesten Stand". _(v3.0.1)_
[ ] `deploy.php`-Statusseite (nach einem Deploy): dieselbe Logik — `←` bei unterschiedlichen Versionen, `=` bei identischen. _(v3.0.1)_
---

## 9. Statistik 

### Lernaktivität (Kennzahlen + Heatmap) _(v3.0.3)_
[ ] Karte "Lernaktivität" erscheint oben auf `stats.php`, oberhalb des Listen-Filters, und bleibt beim Wechsel des Listen-Filters unverändert (zählt über alle Listen). _(v3.0.3)_
[ ] Kennzahl "Aktueller Streak" stimmt mit dem bisherigen Streak-Badge in der Navbar überein. _(v3.0.3)_
[ ] Kennzahl "Lerntage gesamt" entspricht der Anzahl distinkter Tage mit mindestens einer beantworteten Karte (gewusst/nicht gewusst oder richtig/falsch), über alle Zeit. _(v3.0.3)_
[ ] Kennzahl "Beste Woche" entspricht der höchsten Anzahl Lerntage innerhalb einer einzelnen Kalenderwoche (Mo–So), über alle Zeit. _(v3.0.3)_
[ ] Heatmap zeigt 52 Wochenspalten (älteste links, aktuelle Woche rechts), 7 Zeilen (Mo–So). _(v3.0.3)_
[ ] Tage ohne Lernaktivität sind leer/grau, Tage mit Aktivität in 4 abgestuften Grüntönen je nach Anzahl beantworteter Karten relativ zum Maximum im sichtbaren Zeitraum. _(v3.0.3)_
[ ] Monatsbeschriftungen erscheinen über der jeweils ersten Wochenspalte eines neuen Monats. _(v3.0.3)_
[ ] Wochentag-Labels links zeigen nur "Mo", "Mi", "Fr". _(v3.0.3)_
[ ] Hover über eine Tages-Zelle zeigt Tooltip mit Datum und Anzahl gelernter Karten bzw. "nicht gelernt". _(v3.0.3)_
[ ] Tage nach heute (Rest der laufenden Kalenderwoche) werden leer dargestellt, ohne Tooltip. _(v3.0.3)_
[ ] Heatmap ist auf schmalen Bildschirmen (Mobile) horizontal scrollbar, restliche Seite scrollt nicht mit. _(v3.0.3)_
[ ] Auf breiten Bildschirmen (Desktop, genug Platz) ist die Heatmap horizontal zentriert dargestellt, nicht linksbündig. _(v3.1.1)_
[ ] Bei aktiviertem dunklen Farbschema (Browser/OS) sind Heatmap-Farben und Beschriftungen weiterhin gut lesbar. _(v3.0.3)_
[ ] Person ohne jegliche Lernaktivität: Kennzahlen zeigen 0, Heatmap komplett leer/grau, keine Fehler. _(v3.0.3)_

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

### Dialekt-Standard Englisch (MCP) _(v2.7.11)_
[ ] `initialize`-Instructions, `list_lists`- und `add_cards`-Beschreibung erwähnen: Liste mit `speech_lang_b` → dieser Dialekt hat Vorrang; Liste ohne `speech_lang_b` und Sprache B Englisch → Standard ist britisches Englisch (en-GB), nicht US-Englisch. _(v2.7.11)_
[ ] Agent fügt Karten zu einer Englisch-Liste OHNE `speech_lang_b` hinzu, ohne dass der User einen Dialekt nennt → Begriff/Beispielsatz verwenden britische Schreibweise (z.B. "colour", "lorry", "flat"), nicht amerikanische. _(v2.7.11)_
[ ] User verlangt im Gespräch ausdrücklich "amerikanisches Englisch" → Agent verwendet US-Schreibweise trotz fehlendem `speech_lang_b`. _(v2.7.11)_
[ ] Liste mit `speech_lang_b = en-US` → Agent verwendet US-Schreibweise, auch ohne explizite Ansage des Users (Listen-Definition hat Vorrang). _(v2.7.11)_

### Gross-/Kleinschreibung deutscher Begriff _(v2.7.10)_
[ ] `tools/list` → `initialize`-Instructions, `add_cards`-Beschreibung, `sprache_a_begriff`/`sprache_b_begriff`-Feldbeschreibungen sowie `update_card`-Feldbeschreibungen erwähnen die Regel: Nomen immer gross, andere Wortarten klein, ausser Satzanfang bei mehrteiligen Begriffen. _(v2.7.10)_
[ ] "Füge das Verb [X] hinzu" (Deutsch als Zielsprache B oder A) → deutscher Begriff wird kleingeschrieben vorgeschlagen (z.B. "laufen", nicht "Laufen"). _(v2.7.10)_
[ ] "Füge das Nomen [X] hinzu" → deutscher Begriff wird grossgeschrieben vorgeschlagen (z.B. "Haus"). _(v2.7.10)_
[ ] Mehrteiliger deutscher Begriff/Wendung (z.B. Redewendung) → nur das erste Wort ist gross, unabhängig von dessen Wortart. _(v2.7.10)_
[ ] `list_cards`/`update_card`-Workflow: bei falscher Gross-/Kleinschreibung eines bestehenden Begriffs schlägt der Agent eine Korrektur vor (alt → neu) und wartet auf Bestätigung, bevor `update_card` aufgerufen wird. _(v2.7.10)_

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

---

## 11. Eigenes Konto (Passwort + E-Mail, alle Personen) _(v3.0.0)_

[ ] Auf jeder Seite: Schlüssel-Icon (`bi-key`, Tooltip "Passwort ändern") neben dem Personennamen öffnet ein Modal "Konto". _(v3.0.0)_
[ ] Falsches aktuelles Passwort → Fehlermeldung, Passwort unverändert. _(v3.0.0)_
[ ] Neues Passwort unter 8 Zeichen → Fehlermeldung. _(v3.0.0)_
[ ] Neues Passwort und Wiederholung stimmen nicht überein → Fehlermeldung. _(v3.0.0)_
[ ] Korrektes aktuelles Passwort + gültiges neues Passwort → Flash "Passwort erfolgreich geändert.", Login mit neuem Passwort möglich, altes Passwort funktioniert nicht mehr. _(v3.0.0)_
[ ] Diese Funktion steht JEDER Person zur Verfügung, nicht nur Admins. _(v3.0.0)_
[ ] Im selben Modal: eigene E-Mail-Adresse setzen/ändern, unabhängig vom Passwort-Formular. _(v3.0.0)_
[ ] E-Mail-Feld leer speichern → E-Mail-Adresse wird entfernt (Flash "E-Mail-Adresse entfernt."). _(v3.0.0)_
[ ] E-Mail-Adresse setzen, die bereits einer anderen Person gehört → Fehlermeldung, keine Änderung. _(v3.0.0)_
[ ] Nach dem Setzen: Feld zeigt beim erneuten Öffnen des Modals die gespeicherte E-Mail-Adresse vorausgefüllt. _(v3.0.0)_

---

## 12. Benutzerverwaltung (`users.php`, nur Admin) _(v3.0.0)_

[ ] Als Admin: Icon-Button (`bi-person-gear`, Tooltip "Benutzerverwaltung") in der zentralen Navbar auf JEDER Seite sichtbar, neben "Einstellungen", führt zu `users.php`. _(v3.0.0)_
[ ] Als Nicht-Admin: kein "Benutzerverwaltung"-Icon in der Navbar sichtbar. _(v3.0.0)_
[ ] Als Nicht-Admin `users.php` direkt per URL aufgerufen → Redirect zu `home.php` mit Fehlermeldung, kein Zugriff. _(v3.0.0)_
[ ] `users.php`: Breadcrumb zeigt "Startseite > Benutzerverwaltung" — NICHT unter "Einstellungen" verschachtelt. _(v3.0.0)_
[ ] `settings.php`: keine "Benutzerverwaltung"-Karte/Link mehr auf der Seite selbst (nur noch über das Navbar-Icon erreichbar). _(v3.0.0)_
[ ] Als Admin: Tabelle zeigt alle Personen mit Name, E-Mail (oder "–" wenn keine hinterlegt), Status-Badge (Admin/Person), eigene Zeile mit "(du)"-Hinweis. _(v3.0.0)_
[ ] "Neue Person anlegen": Name + Passwort + Passwort-Wiederholung (beide min. 8 Zeichen, müssen identisch sein) + optionale E-Mail + optionales Admin-Häkchen → Person wird angelegt, kann sich sofort einloggen. _(v3.0.0)_
[ ] Neue Person anlegen mit unterschiedlichen Passwort/Wiederholung-Werten → Fehlermeldung "Die Passwörter stimmen nicht überein.", Person wird nicht angelegt. _(v3.0.0)_
[ ] Neue Person mit bereits vergebenem Namen anlegen → Fehlermeldung. _(v3.0.0)_
[ ] Neue Person mit bereits vergebener E-Mail-Adresse anlegen → Fehlermeldung, Person wird nicht angelegt. _(v3.0.0)_
[ ] Neue Person mit ungültig formatierter E-Mail-Adresse (z.B. "nicht-valide") anlegen → Fehlermeldung "Ungültige E-Mail-Adresse.", Person wird nicht angelegt. _(v3.1.1)_
[ ] Aktions-Buttons pro Person sind Icons mit Tooltip statt Text: E-Mail (`bi-envelope-plus`), Passwort zurücksetzen (`bi-key`), Admin umschalten (`bi-person-dash`/"Admin entfernen" bei Admin-Personen, `bi-person-lock`/"Zu Admin machen" bei Nicht-Admin-Personen). _(v3.0.1)_
[ ] "E-Mail"-Icon bei einer Person → Modal, E-Mail setzen/ändern/entfernen (leeres Feld speichern = entfernen). _(v3.0.0, Icon v3.0.1)_
[ ] E-Mail-Adresse setzen, die bereits einer anderen Person gehört → Fehlermeldung, keine Änderung. _(v3.0.0)_
[ ] E-Mail-Adresse mit ungültigem Format setzen (z.B. "nicht-valide") → Fehlermeldung "Ungültige E-Mail-Adresse.", keine Änderung gespeichert. _(v3.1.1)_
[ ] "Passwort zurücksetzen"-Icon bei einer Person → Modal mit neuem Passwort + Wiederholung (min. 8 Zeichen, müssen identisch sein, kein altes Passwort nötig) → Person kann sich mit neuem Passwort einloggen. _(v3.0.0, Icon v3.0.1)_
[ ] "Passwort zurücksetzen" mit unterschiedlichen Werten in Passwort/Wiederholung → Fehlermeldung, Passwort bleibt unverändert. _(v3.0.0)_
[ ] "Zu Admin machen"-Icon (`bi-person-lock`) bei einer Nicht-Admin-Person → Status wechselt zu Admin-Badge, Icon wechselt zu `bi-person-dash`/"Admin entfernen". _(v3.0.0, Icon v3.0.1)_
[ ] "Admin entfernen"-Icon (`bi-person-dash`) bei einer Admin-Person, sofern mind. ein weiterer Admin existiert → Status wechselt zu Person-Badge, Icon wechselt zu `bi-person-lock`/"Zu Admin machen". _(v3.0.0, Icon v3.0.1)_
[ ] "Admin entfernen" beim LETZTEN verbleibenden Admin → Fehlermeldung "Der letzte verbleibende Admin kann nicht entfernt werden.", Status bleibt Admin. _(v3.0.0)_
[ ] Admin-Status-Änderung wirkt erst beim nächsten Login der betroffenen Person (nicht rückwirkend auf eine bereits laufende Session). _(v3.0.0)_
[ ] Bei der eigenen Zeile (du) ist das "Person löschen"-Icon unsichtbar/nicht klickbar, reserviert aber denselben Platz — Aktions-Icons aller Zeilen bleiben untereinander bündig ausgerichtet. _(v3.2.0)_
[ ] Bei jeder anderen Person erscheint das "Person löschen"-Icon (`bi-trash`) → öffnet Bestätigungs-Modal mit Warntext und Namenseingabe-Feld. _(v3.2.0)_
[ ] Im Lösch-Modal: "Endgültig löschen"-Button ist deaktiviert, solange das eingetippte Feld nicht exakt dem Namen der Person entspricht (Gross-/Kleinschreibung exakt). _(v3.2.0)_
[ ] Falscher Name serverseitig eingereicht (z.B. per manipuliertem Request trotz deaktiviertem Button) → Fehlermeldung "Name stimmt nicht überein, Person wurde nicht gelöscht.", Person bleibt bestehen. _(v3.2.0)_
[ ] Löschen einer Person mit korrektem Namen → Person UND alle ihre Listen, Karten (via Listen), Lernfortschritt, Lernsessions/-events sind vollständig aus der DB entfernt (keine Restdaten). _(v3.2.0)_
[ ] Löschen einer Person, die öffentliche Listen besitzt, die von ANDEREN Personen gelernt werden → auch die eigenen `card_progress`-Einträge dieser anderen Personen zu den gelöschten Listen/Karten werden mitgelöscht (bestehende Kaskade). _(v3.2.0)_
[ ] Versuch, sich selbst zu löschen (direkter POST-Request mit eigener `person_id`, z.B. Button ist ja ausgeblendet) → Fehlermeldung "Du kannst dich nicht selbst löschen.", keine Löschung. _(v3.2.0)_
[ ] Löschen des LETZTEN verbleibenden Admins → Fehlermeldung "Der letzte verbleibende Admin kann nicht gelöscht werden.", Person bleibt bestehen. _(v3.2.0)_
[ ] Löschvorgang läuft in einer Transaktion — bei einem simulierten Fehler bleiben alle Daten der Person unverändert erhalten (kein Teil-Löschen). _(v3.2.0)_

---

## 13. "Person wechseln" (nur Admin) _(v3.0.0)_

[ ] Als Admin: Dropdown-Icon "Person wechseln" (`bi-person-lines-fill`) in der zentralen Navbar auf JEDER Seite sichtbar (nur wenn mehr als eine Person existiert). _(v3.0.0)_
[ ] Als Nicht-Admin: kein "Person wechseln"-Element sichtbar. _(v3.0.0)_
[ ] Admin wählt eine andere (Nicht-Admin-)Person aus dem Dropdown → agiert danach als diese Person (eigene Listen/Fortschritt dieser Person sichtbar). _(v3.0.0)_
[ ] Nach dem Wechsel zu einer Nicht-Admin-Person: "Benutzerverwaltung"- und "Einstellungen"-Icons verschwinden aus der Navbar (genau wie bei der echten Person). _(v3.0.0)_
[ ] Nach dem Wechsel zu einer Nicht-Admin-Person: `settings.php`/`deploy.php`/`users.php` direkt per URL aufgerufen → blockiert, genau wie für die echte Person. _(v3.0.0)_
[ ] Nach dem Wechsel zu einer Nicht-Admin-Person: "Person wechseln"-Icon bleibt trotzdem sichtbar und funktioniert weiterhin (Ausnahme von der Berechtigungs-Übernahme). _(v3.0.0)_
[ ] Admin kann über dasselbe Dropdown zurück zu seiner eigenen Person wechseln → alle Admin-Icons und -Zugriffe sind wieder vorhanden. _(v3.0.0)_
[ ] Aktuell aktive Person ist im Dropdown optisch hervorgehoben (z.B. "active"-Zustand). _(v3.0.0)_
[ ] Person wechseln funktioniert von JEDER Seite aus (nicht nur von der Startseite) und landet nach dem Wechsel wieder auf derselben Seite. _(v3.0.0)_

---

## 14. Passwort vergessen (E-Mail-Reset) _(v3.0.0)_

[ ] Login-Seite (`index.php`) zeigt Link "Passwort vergessen?" unterhalb des Login-Formulars. _(v3.0.0)_
[ ] `forgot-password.php`: E-Mail eingeben, die KEINER Person zugeordnet ist → generische Erfolgsmeldung ("Falls diese E-Mail-Adresse..."), keine Mail wird verschickt. _(v3.0.0)_
[ ] `forgot-password.php`: E-Mail eingeben, die einer Person mit hinterlegter E-Mail zugeordnet ist → dieselbe generische Erfolgsmeldung, UND eine E-Mail mit Reset-Link wird verschickt. _(v3.0.0)_
[ ] Beide Fälle (bekannte/unbekannte E-Mail) sind an der Antwort nicht unterscheidbar (keine Rückschlüsse möglich, welche E-Mails registriert sind). _(v3.0.0)_
[ ] E-Mail enthält einen Link auf `reset-password.php?token=...` mit korrekter Domain/Pfad (funktioniert sowohl auf Dev als auch auf Prod). _(v3.0.0)_
[ ] `reset-password.php` mit gültigem, noch nicht abgelaufenem Token → Formular für neues Passwort erscheint. _(v3.0.0)_
[ ] `reset-password.php` mit ungültigem/erfundenem Token → Fehlermeldung "ungültig oder abgelaufen", Link zurück zu `forgot-password.php`. _(v3.0.0)_
[ ] `reset-password.php` mit einem Token, das älter als 60 Minuten ist → dieselbe "ungültig oder abgelaufen"-Fehlermeldung. _(v3.0.0)_
[ ] Neues Passwort unter 8 Zeichen → Fehlermeldung, Token bleibt gültig (nochmaliger Versuch möglich). _(v3.0.0)_
[ ] Neues Passwort und Wiederholung stimmen nicht überein → Fehlermeldung. _(v3.0.0)_
[ ] Gültiges neues Passwort gesetzt → Erfolgsmeldung, Login mit neuem Passwort funktioniert. _(v3.0.0)_
[ ] Nach erfolgreichem Reset: derselbe Link (Token) erneut aufgerufen → "ungültig oder abgelaufen" (Token ist nach Gebrauch entwertet, nicht wiederverwendbar). _(v3.0.0)_
[ ] Person ohne hinterlegte E-Mail-Adresse: `forgot-password.php` mit einer beliebigen E-Mail führt nie zu einem Reset dieser Person (da keine Zuordnung existiert). _(v3.0.0)_
[ ] Bereits eingeloggt und `forgot-password.php`/`reset-password.php` aufgerufen → Redirect zu `home.php`. _(v3.0.0)_
[ ] Auf Produktion (getestet mit echtem Hosting-Mailversand): E-Mail mit Reset-Link kommt tatsächlich im Postfach an (nicht nur im Spam-Ordner) — insbesondere bei Hostern mit strikter Outbound-Spam-Prüfung (z.B. HostFactory). _(v3.2.3)_
[ ] Subject der Reset-E-Mail zeigt Umlaute korrekt im E-Mail-Programm (kein Mojibake, keine sichtbaren `=?UTF-8?B?...?=`-Reste). _(v3.2.3)_
[ ] Bei fehlgeschlagenem `mail()`-Versand landet ein Eintrag im PHP-Error-Log der Produktion (`forgot-password.php: mail() fehlgeschlagen für person_id=...`). _(v3.2.3)_

---

## 15. Zentrale Navbar (alle Seiten) _(v3.0.0)_

[ ] Reihenfolge der Navbar-Elemente (rechtsbündig) ist auf JEDER Seite identisch: Streak-Badge, Personenname, Passwort ändern (`bi-key`), Person wechseln (`bi-person-lines-fill`, nur Admin), Benutzerverwaltung (`bi-person-gear`, nur Admin), Einstellungen (`bi-gear`, nur Admin), Logout (`bi-box-arrow-right`), Hilfe (`bi-info-lg`). _(v3.0.0)_
[ ] Stichprobe auf mind. 3 verschiedenen Seiten (z.B. `lists.php`, `edit.php`, `stats.php`) zeigt exakt dieselbe Navbar mit denselben Icons in derselben Reihenfolge wie auf `home.php`. _(v3.0.0)_
[ ] Logout-Button zeigt nur noch das Icon `bi-box-arrow-right`, keinen Text "Logout" mehr. _(v3.0.0)_
[ ] Einstellungen-Element zeigt nur noch das Icon `bi-gear`, keinen Text "Einstellungen" mehr. _(v3.0.0)_
[ ] "Person wechseln"-Dropdown zeigt nur noch das Icon `bi-person-lines-fill`, keinen Text "Person wechseln" mehr — Dropdown-Pfeil und Personenauswahl funktionieren weiterhin wie zuvor. _(v3.0.0)_
[ ] Logout funktioniert von jeder Seite aus identisch (führt zu `index.php`). _(v3.0.0)_
[ ] `learn.php`/`drill.php` OHNE aktive Session zeigen dieselbe zentrale Navbar wie alle anderen Seiten. _(v3.0.0)_
[ ] `learn.php` MIT aktiver Session: weiterhin "Session abbrechen" statt der Standard-Navbar-Elemente (Sonderfall bleibt bestehen, unverändert). _(v3.0.0)_
[ ] `drill.php` MIT aktiver Session: weiterhin Timer + "gemeistert"-Zähler + "Session abbrechen" statt der Standard-Navbar-Elemente (Sonderfall bleibt bestehen, unverändert). _(v3.0.0)_
[ ] Konto-Modal (Passwort/E-Mail ändern) funktioniert von JEDER Seite aus (nicht nur `home.php`) und landet nach dem Speichern wieder auf derselben Seite. _(v3.0.0)_
[ ] `assets/style.css` wird auf JEDER Seite mit Versions-Query-String eingebunden (`?v=<APP_VERSION>`), sichtbar im Seitenquelltext. _(v3.1.1)_
[ ] Nach einem Release mit CSS-Änderung: neue Seite ohne manuellen Hard-Refresh korrekt gestylt (Browser holt CSS neu, da sich der `?v=`-Parameter geändert hat). _(v3.1.1)_

