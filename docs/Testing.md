Manuelle Testfälle für den Learner-Vokabeltrainer.

Jeder Test ist als Task mit [] gekennzeichnet. Nach erfolgreichem Test [x] setzen.
Jeder Abschnitt oder Test trägt einen Release-Verweis _(vX.Y.Z)_ — zeigt ab welchem Release dieser Test relevant ist.

---

## 1. Login / DB

### Rate-Limiting Login _(v3.2.23)_
[ ] 10 Logins mit falschem Passwort → jedes Mal die normale Meldung "Name oder Passwort falsch.". _(v3.2.23)_
[ ] Der 11. Versuch innerhalb von 15 Minuten → Meldung "Zu viele fehlgeschlagene Login-Versuche…", auch bei RICHTIGEM Passwort. _(v3.2.23)_
[ ] Nach Ablauf des Zeitfensters (bzw. nach `DELETE FROM auth_attempts`) funktioniert der Login wieder normal. _(v3.2.23)_
[ ] Erfolgreicher Login löscht die Fehlversuche der eigenen IP (Tabelle `auth_attempts` danach ohne Einträge für diese IP). _(v3.2.23)_
[ ] Der Login funktioniert weiterhin, wenn die Tabelle `auth_attempts` fehlt (z.B. Deploy vor der Migration) — kein Blockieren, keine Fehlermeldung. _(v3.2.23)_

### Neuinstallation / install.php _(v3.2.23)_
[ ] Frische Installation: Tabelle `auth_attempts` wird von `install.php` mit angelegt. _(v3.2.23)_
[ ] Bestehende Installation: Migration 14 legt `auth_attempts` beim ersten Seitenaufruf nach dem Deploy an, `db_version` steht danach auf 14. _(v3.2.23)_
[ ] `install.php` mit bereits existierender Person: Klick auf "Tabellen erneut prüfen" führt NICHTS aus, sondern zeigt "Die Installation ist bereits abgeschlossen…". _(v3.2.23)_
[ ] `install.php`: POST ohne gültiges CSRF-Token → HTTP 403 "Ungültige Anfrage (CSRF-Fehler)". _(v3.2.23)_
[ ] `install.php`: manuell gesendeter `create_admin`-POST auf einem eingerichteten System legt KEINE Person an. _(v3.2.23)_

## 2. Zugriffsschutz auf Dateien _(v3.2.23)_
[ ] Aufruf von `mcp.log` per URL → HTTP 403 (vorher: Inhalt im Klartext lesbar). _(v3.2.23)_
[ ] Aufruf von `includes/db-credentials.php`, `includes/mcp-config.php`, `includes/deploy-config.php`, `includes/config.php` per URL → jeweils HTTP 403. _(v3.2.23)_
[ ] Die App selbst funktioniert unverändert (Login, Startseite, Leitner, Drill) — die `includes/`-Sperre betrifft nur den HTTP-Zugriff, nicht `require`. _(v3.2.23)_
[ ] Antwort-Header einer beliebigen Seite enthalten `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`. _(v3.2.23)_
[ ] Bootstrap-CSS/-JS und Bootstrap-Icons laden im Browser fehlerfrei (SRI-Hashes stimmen, keine Konsolen-Fehler "Failed to find a valid digest"). _(v3.2.23)_

## 3. Passwort vergessen / E-Mail _(v3.2.23)_
[ ] Einstellungen → Allgemein: Feld "Basis-URL" ist vorhanden und zeigt bei leerer Konfiguration die aktuelle Adresse als Vorschlag plus einen Warnhinweis. _(v3.2.23)_
[ ] Basis-URL speichern → Wert landet in `config-runtime.php` als `APP_BASE_URL`, Warnhinweis verschwindet. _(v3.2.23)_
[ ] Ungültige Basis-URL (z.B. `javascript:alert(1)` oder `example.com` ohne Schema) → Fehlermeldung, Wert wird NICHT gespeichert. _(v3.2.23)_
[ ] Passwort-Reset anfordern mit gesetzter Basis-URL → Link in der Mail zeigt auf die konfigurierte Adresse. _(v3.2.23)_
[ ] Passwort-Reset mit gefälschtem `Host`-Header (z.B. per curl `-H "Host: evil.example.com"`) → Link in der Mail zeigt WEITERHIN auf die konfigurierte Basis-URL, nicht auf die gefälschte Domain. _(v3.2.23)_
[ ] Auf dem Server ohne konfigurierte Basis-URL: "Passwort vergessen" zeigt die normale generische Meldung, verschickt aber keine Mail und schreibt einen Hinweis ins PHP-Error-Log. _(v3.2.23)_
[ ] Rate-Limiting: 5 "Passwort vergessen"-Anfragen sind möglich, ab der 6. innerhalb von 60 Minuten wird keine Mail mehr verschickt (Meldung bleibt aus Sicherheitsgründen identisch). _(v3.2.23)_
[ ] "Konto"-Modal: ungültige E-Mail-Adresse (z.B. `nicht-gueltig`) → Fehlermeldung "Ungültige E-Mail-Adresse.", nichts gespeichert. _(v3.2.23)_
[ ] "Konto"-Modal: gültige E-Mail-Adresse wird gespeichert; leeres Feld entfernt sie weiterhin. _(v3.2.23)_
[ ] Einstellungen → E-Mail-Test: Absenderadresse leitet sich aus der Basis-URL ab (nicht mehr aus dem Host-Header). _(v3.2.23)_

### Absender-E-Mail / Zustellbarkeit _(v3.2.24)_
[ ] Einstellungen → Allgemein: Feld "Absender-E-Mail" ist vorhanden. _(v3.2.24)_
[ ] Feld leer + Basis-URL zeigt auf eine Subdomain → Warnhinweis erscheint und schlägt konkret die Hauptdomain-Adresse vor. _(v3.2.24)_
[ ] Ungültiger Wert (z.B. `keine-mail`) → Fehlermeldung "Absender-E-Mail: Keine gültige E-Mail-Adresse.", nichts gespeichert. _(v3.2.24)_
[ ] Gültige Adresse speichern → landet als `MAIL_FROM` in `config-runtime.php`, Warnhinweis verschwindet. _(v3.2.24)_
[ ] **Prod:** Absender auf eine Adresse der Hauptdomain setzen (deren SPF den Hoster abdeckt) → E-Mail-Test kommt tatsächlich im Postfach an (nicht nur "Erfolg" gemeldet), auch bei Gmail. _(v3.2.24)_
[ ] **Prod:** Passwort-Reset anfordern → Mail kommt an, Link zeigt auf die Basis-URL, Zurücksetzen funktioniert. _(v3.2.24)_
[ ] Mail-Header der angekommenen Nachricht prüfen: `spf=pass` und `dmarc=pass` für die Absenderdomain. _(v3.2.24)_

## 4. Berechtigungen / Parameter _(v3.2.23)_
[ ] `edit.php`: Archivieren mit einer `card_id`, die zu einer FREMDEN Liste gehört (manueller POST) → "Karte nicht gefunden.", kein `card_progress`-Eintrag entsteht. _(v3.2.23)_
[ ] `edit.php`: Reaktivieren mit fremder `card_id` (manueller POST) → ebenfalls abgelehnt. _(v3.2.23)_
[ ] `edit.php`: Vormerken/Entfernen (`toggle_pin`) mit fremder `card_id` (manueller POST) → ebenfalls abgelehnt, kein `card_progress`-Eintrag entsteht. _(v3.2.30)_
[ ] `edit.php`: Archivieren/Reaktivieren eigener Karten funktioniert unverändert. _(v3.2.23)_
[ ] `stats.php?list_id=<fremde ID>` → Redirect auf die erste eigene Liste, keine fremden Daten, kein "alle Listen"-Modus. _(v3.2.23)_
[ ] `math.php`: Liste mit `<b>`/`"` im Namen anlegen und Duplikat-Warnung auslösen → Name erscheint als Text, kein HTML wird interpretiert. _(v3.2.23)_
[ ] Navbar-Aktionen (Logout, Passwort ändern, E-Mail ändern, Person wechseln) leiten weiterhin korrekt auf die Ausgangsseite zurück. _(v3.2.23)_

---

## 5. Mobile Darstellung (iPhone) _(v3.2.25)_

Prüfen auf einem echten iPhone (Referenz: 15 Pro Max, 430 px) oder im Browser mit 430-px-Viewport.

[ ] Keine Seite lässt sich horizontal wegschieben — auf Startseite, Statistik, Meine Listen, Kartenübersicht, Import, Einstellungen, Benutzerverwaltung, Hilfe, Mathe, Leitner und Drill je einmal prüfen. _(v3.2.25)_
[ ] Navbar: alle Icons sichtbar und antippbar; der Personenname ist auf dem Handy ausgeblendet, auf dem Desktop weiterhin sichtbar. _(v3.2.25)_
[ ] Statistik-Heatmap: auf dem Handy sind nur ~4 Monate zu sehen, der aktuelle Zeitraum ist ohne Scrollen sichtbar, die Monatsbeschriftung passt zu den angezeigten Spalten. _(v3.2.25)_
[ ] Statistik-Heatmap auf dem Desktop: weiterhin alle 52 Wochen mit korrekter Monatsbeschriftung. _(v3.2.25)_
[ ] Kartenübersicht: die vier Aktions-Icons (Ansehen/Bearbeiten/Archivieren/Löschen) sind vollständig sichtbar (zwei Reihen) und funktionieren. _(v3.2.25)_
[ ] Einstellungen: Beschriftungen stehen über den Feldern, Textfelder nutzen die volle Breite, Zahlenfelder bleiben schmal; Speichern funktioniert. _(v3.2.25)_
[ ] Import: das CSV-Beispiel scrollt innerhalb seines Rahmens, die Seite selbst nicht. _(v3.2.25)_
[ ] **iPhone (Safari):** Tippen auf "Datei auswählen" beim CSV-Import öffnet den nativen Datei-Dialog (vorher reagierte der Button gar nicht). _(v3.2.40)_
[ ] Leitner- und Drill-Karte: Karte, Buttons und Timer sind vollständig sichtbar und bedienbar. _(v3.2.25)_

## 6. Session abbrechen als Icon _(v3.2.26)_

[ ] Laufende **Leitner**-Session: in der Navbar erscheint als erstes Element ein X-Icon (`bi-x-lg`) mit Tooltip "Session abbrechen"; der Logout-Button ist in diesem Zustand ausgeblendet. _(v3.2.26)_
[ ] Laufende **Drill**-Session: X-Icon steht ganz links in der rechten Navbar-Gruppe, vor Timer und "N gemeistert". _(v3.2.26)_
[ ] Klick auf das X-Icon während einer Session → Bestätigungsdialog erscheint, nach Bestätigung wird die Session beendet und man landet auf der Zielseite. _(v3.2.26)_
[ ] Ausserhalb einer Session (Setup- und Zusammenfassungsseite) erscheint kein X-Icon, dafür wieder der normale Logout-Button. _(v3.2.26)_
[ ] Auf dem Handy: die Drill-Navbar (X, Timer, Zähler, Hilfe) passt in eine Zeile und läuft nicht über. _(v3.2.26)_

## 7. Modals auf dem iPhone _(v3.2.28)_

[ ] Benutzerverwaltung auf dem iPhone: "E-Mail" antippen → Modal öffnet, das Eingabefeld lässt sich antippen und beschreiben, Tastatur erscheint. _(v3.2.28)_
[ ] Dasselbe Modal per X, per "Abbrechen" und per Tippen auf den Hintergrund wieder schliessbar. _(v3.2.28)_
[ ] "Passwort zurücksetzen" auf dem iPhone: beide Passwortfelder ausfüllbar, Speichern funktioniert. _(v3.2.28)_
[ ] "Person löschen" auf dem iPhone: Checkbox antippbar, "Endgültig löschen" erst danach absendbar. _(v3.2.28)_
[ ] Am Desktop funktionieren alle drei Modals unverändert. _(v3.2.28)_
[ ] Auch bei vielen Personen (Tabelle breiter als das Display, seitlich scrollbar) bleiben die Modals bedienbar. _(v3.2.28)_

## 8. Für Drill vormerken _(v3.2.30)_

[ ] `edit.php`: Kartenansicht (Direktlink `highlight=<id>`) zeigt das Pin-Icon oben links auf der Karte, klickbar ohne dass die Karte dabei umdreht, toggelt die Vormerkung; Status wechselt sofort sichtbar (gefülltes vs. leeres Pin-Symbol, Badge "Vorgemerkt" in der Status-Spalte der Tabelle). _(v3.2.31)_
[ ] Archivierte Karte: Vormerken ist nicht möglich (Icon ausgeblendet/deaktiviert bzw. Aktion liefert Fehlermeldung). _(v3.2.30)_
[ ] Neu importierte Karte, für die noch nie eine Leitner-Session gestartet wurde: Vormerken funktioniert trotzdem (kein stiller Fehlschlag durch fehlende `card_progress`-Zeile). _(v3.2.30)_
[ ] Filter-Tab "Vorgemerkt" zeigt genau die vorgemerkten Karten dieser Liste, Zähler stimmt. _(v3.2.30)_
[ ] Eine Karte in Leitner-Fach 4 vormerken → Fach bleibt unverändert Fach 4, `next_due_date` bleibt unverändert, solange sie im Drill geübt wird. _(v3.2.30)_
[ ] Vorgemerkte Karte im Drill: erscheint deutlich häufiger als unvorgemerkte Karten; ausgefülltes Pin-Symbol oben links auf der Drill-Karte sichtbar; dasselbe Symbol erscheint auf der Leitner-Karte, falls die Karte zufällig auch dort fällig ist. _(v3.2.30)_
[ ] Modus "Absolut" (Einstellungen): solange eine Karte vorgemerkt ist, erscheinen keine anderen (bekannten/neuen) Karten im Drill. _(v3.2.30)_
[ ] Modus "Gewichtet" mit z.B. N=3: etwa jede 3. Karte im Drill ist die vorgemerkte, dazwischen läuft die normale 9:1-Rotation weiter. _(v3.2.30)_
[ ] Konfigurierte Mastery-Schwelle an richtigen Antworten in Folge im Drill erreicht → Vormerkung wird automatisch entfernt, Leitner-Fach bleibt dabei unverändert (siehe Testfall oben). _(v3.2.30)_
[ ] Falsche Antwort auf eine vorgemerkte Karte im Drill → Zähler setzt sich zurück auf 0, Karte bleibt trotzdem im Pool (keine `drill_too_hard`-Sperre, taucht nicht erst am nächsten Tag wieder auf). _(v3.2.30)_
[ ] Vormerkung während laufender Drill-Session in einem zweiten Tab über `edit.php` entfernen → die laufende Session "wiederbelebt" die Vormerkung nicht, auch wenn die Karte danach im Drill richtig beantwortet wird. _(v3.2.30)_
[ ] Einstellungen → Drill-Modus: "Vormerkungs-Priorität" (Absolut/Gewichtet) und "Vormerkungs-Häufigkeit" sind änderbar und wirken sich unmittelbar auf die nächste Drill-Session aus. _(v3.2.30)_
[ ] Kartenübersicht auf Desktop-Breite (Safari): mit einer vorgemerkten Karte in der Liste ("Vorgemerkt"-Badge sichtbar) bleiben die 4 Aktions-Icons in einer Zeile nebeneinander, kein Umbruch. _(v3.2.31)_
[ ] Neuinstallation über `install.php`: Spalte `drill_pinned_correct` ist in der Tabellendefinition von `card_progress` vorhanden. _(v3.2.30)_
[ ] Bestehende Installation: Migration 15 legt `drill_pinned_correct` beim ersten Seitenaufruf nach dem Deploy an, `db_version` steht danach auf 15. _(v3.2.30)_

## 9. Deploy-Status: Changelog-Vorschau _(v3.2.32)_

[ ] `deploy.php` mit älterer installierter Version als auf GitHub: zwischen Versionsvergleich und "Deploy starten"-Button erscheint je Änderungspunkt eine Zeile `[X.Y.Z] - Titel`, neueste Version zuerst. _(v3.2.32)_
[ ] Die aktuell installierte Version selbst taucht in der Liste NICHT auf — nur Versionen neuer als die installierte, bis einschliesslich der GitHub-Version. _(v3.2.32)_
[ ] Ist bereits alles aktuell (Installiert = GitHub), erscheint kein Changelog-Block. _(v3.2.32)_
[ ] Änderungspunkte ohne Fett-Hervorhebung in `CHANGELOG.md` zeigen trotzdem einen sinnvollen (gekürzten) Titel, kein leerer oder abgeschnittener Text mitten im Wort. _(v3.2.32)_
[ ] Nach einem tatsächlich ausgeführten Deploy (Erfolgsmeldung sichtbar) wird kein Changelog-Block mehr angezeigt. _(v3.2.32)_

## 10. Debug-Modus _(v3.2.34)_

[ ] Einstellungen → Debug: Schalter "Debug-Modus aktiv" lässt sich unabhängig vom grossen Einstellungs-Formular speichern, andere Einstellungen bleiben dabei unverändert. _(v3.2.34)_
[ ] Debug-Modus aktiv, als Admin eingeloggt: nach einer Leitner-Antwort erscheint ein Info-Panel mit Fach- und Fälligkeits-Änderung der beantworteten Karte, bevor die neue Karte gezeigt wird. _(v3.2.34)_
[ ] Leitner und Drill: das Debug-Panel klebt fix am unteren Bildschirmrand (`position: fixed`) statt im normalen Textfluss zu stehen — sichtbar ohne Scrollen, unabhängig davon wie lang die Karte/Seite ist. _(v3.2.44)_
[ ] Auf dem iPhone überlappt das fixierte Panel NICHT die Statusleiste/Home-Indicator-Leiste am unteren Rand (sichtbarer Abstand durch `safe-area-inset-bottom`). _(v3.2.44)_
[ ] Ungewöhnlich lange Karte (z.B. langer Beispielsatz) → das fixierte Panel darf dabei die Antwort-Buttons überlagern, das ist bekannt und akzeptiert (nur admin-seitiges Debug-Feature). _(v3.2.44)_
[ ] Leitner "Überspringen" bei aktivem Debug-Modus → Panel zeigt "übersprungen, nichts geändert". _(v3.2.34)_
[ ] Leitner 2. Versuch (nach falscher Antwort) → Panel vermerkt "2. Versuch" korrekt, unabhängig ob danach richtig oder falsch. _(v3.2.34)_
[ ] War die beantwortete Karte die letzte der Leitner-Session → Panel erscheint auf der Zusammenfassungsseite, nicht auf einer (nicht mehr existierenden) nächsten Karte. _(v3.2.34)_
[ ] Drill, normale Karte (nicht vorgemerkt): Panel zeigt bei einer normalen Antwort BEIDE Zähler gleichzeitig ("Mastery-Zähler X/3" und "Zu-schwer-Zähler X/5", je auf eigener Zeile); wird die Karte in diesem Schritt gemeistert oder als zu schwer markiert, zeigt das Panel stattdessen nur die Fach-Änderung bzw. den Hinweis "als zu schwer markiert". _(v3.2.42)_
[ ] Drill, vorgemerkte Karte: Panel zeigt den Vormerk-Zähler; wird die Vormerkung in diesem Schritt entfernt, vermerkt das Panel das explizit — Fach bleibt dabei laut Panel unverändert. _(v3.2.34)_
[ ] War die beantwortete Karte die letzte der Drill-Session → Panel erscheint auf dem Abschluss-Screen. _(v3.2.34)_
[ ] Debug-Modus aktiv, aber als NICHT-Admin eingeloggt (z.B. andere Person im selben Haushalt): kein Panel sichtbar, keinerlei Unterschied zum ausgeschalteten Zustand. _(v3.2.34)_
[ ] Debug-Modus deaktiviert: kein Panel sichtbar, auch nicht für Admins. _(v3.2.34)_
[ ] `math.php` bleibt vom Debug-Modus unberührt (kein Panel dort). _(v3.2.34)_

## 11. help.php-Ergänzungen und MCP-Server-Regeln _(v3.2.37)_

[ ] `help.php`: neue Einleitung direkt unter dem Titel listet die Grundfunktionen der App auf (Wortlisten lernen/erstellen/kopieren, Leitner/Drill, 1×1, Audioaussprache). _(v3.2.37)_
[ ] `help.php`, Abschnitt "Leitner-Modus": neuer Hinweis zum ausgefüllten Pin-Symbol, inkl. sichtbarem Icon in der Erklärung selbst. _(v3.2.37)_
[ ] `help.php`, Abschnitt "Drill-Modus": Hinweis auf "in der Aktionsleiste" entfernt (Button existiert seit v3.2.31 nicht mehr), stattdessen korrekt auf die Kartenansicht verwiesen. _(v3.2.37)_
[ ] MCP-Server: Karte mit deutschem Begriff, der ein "ß" enthalten würde (z.B. "Straße") → Agent schreibt stattdessen "Strasse" (de-CH). _(v3.2.37)_
[ ] MCP-Server: Liste mit Sprache A = Deutsch, Sprache B = Fremdsprache, `speech_lang_b` gesetzt → Agent fragt NICHT explizit nach der Muttersprache, sondern nimmt Sprache A als gegeben an. _(v3.2.37)_
[ ] MCP-Server: Liste bei der beide Sprachen für den User erkennbar fremd sind (z.B. im Gespräch erwähnt) → Agent fragt in diesem Fall doch explizit nach der Muttersprache, bevor `phonetik_b` befüllt wird. _(v3.2.37)_

## 12. Bugfix: Drill-Meisterung stufte Leitner-Fach fälschlich zurück _(v3.2.38)_

[ ] Karte mit `drill_mastery = 1` und `leitner_box = 4` (z.B. früher einmal im Drill gemeistert, danach unabhängig über Leitner bis Fach 4 aufgestiegen) im Drill erneut 3× hintereinander "Gewusst" → Fach bleibt bei 4, `drill_mastery` steigt auf 2, `next_due_date` unverändert. _(v3.2.38)_
[ ] Debug-Panel zeigt in diesem Fall "Fach 4→4" (keine Änderung) statt einer Rückstufung. _(v3.2.38)_
[ ] Normalfall weiterhin korrekt: komplett neue Karte (`drill_mastery = 0`, `leitner_box = 1`) wird im Drill gemeistert → steigt wie gewohnt auf Fach 2 (bzw. 3 bei der zweiten, 4 bei der dritten Meisterung), da das Ziel-Fach dort höher ist als das aktuelle. _(v3.2.38)_

## 13. Vormerken direkt in der Leitner-Session _(v3.2.41)_

[ ] Laufende Leitner-Session: oben links auf der Karte erscheint ein runder, **klickbarer** Pin-Button (nicht nur ein Symbol). _(v3.2.41)_
[ ] Klick auf den Pin-Button → Karte wird "für Drill vorgemerkt" (Icon wird ausgefüllt), dieselbe Karte bleibt weiterhin angezeigt, Fortschritt/Position in der Session bleibt unverändert. _(v3.2.41)_
[ ] Erneuter Klick → Vormerkung wird wieder entfernt (Icon wird zum Umriss). _(v3.2.41)_
[ ] Das Vormerken/Entfernen zählt **nicht** als Antwort — Zähler "Gewusst/Nicht gewusst/Aufgestiegen" sowie die Fortschrittsanzeige bleiben unverändert. _(v3.2.41)_
[ ] Klick auf den Pin-Button klappt die Karte NICHT um (kein versehentliches Aufdecken der Antwort). _(v3.2.41)_
[ ] Manuell gesendeter POST mit `card_id` einer NICHT aktuell angezeigten Karte → wird ignoriert, kein `card_progress`-Eintrag entsteht/ändert sich. _(v3.2.41)_
[ ] `edit.php`-Kartenansicht und Leitner-Session zeigen für dieselbe Karte konsistent denselben Vormerkungs-Status. _(v3.2.41)_
[ ] In `drill.php` bleibt das Pin-Symbol weiterhin nur anzeigend (nicht klickbar) — bewusst kein Umschalten während einer laufenden Drill-Session. _(v3.2.41)_

## 14. Listenauswahl in der Leitner-Session zeigt nur aktive Listen _(v3.2.45)_

[ ] `learn.php` ohne `list_id`-Vorauswahl aufrufen, mind. eine eigene Liste ist inaktiv → die Checkbox-Liste "Listen auswählen" zeigt nur aktive Listen, inaktive tauchen nicht auf. _(v3.2.45)_
[ ] Sind ALLE eigenen Listen inaktiv → Hinweis "Du hast noch keine Listen" erscheint (gleiches Verhalten wie ganz ohne Listen), kein leeres Formular. _(v3.2.45)_
[ ] Startseite → Leitner-Button einer aktiven Liste (setzt `list_id` in der URL) funktioniert weiterhin unverändert (Liste vorausgewählt, keine Checkbox-Liste sichtbar). _(v3.2.45)_

## 15. Deploy: atomares Überschreiben der Live-Dateien _(v3.2.46)_

[ ] Deploy ausführen, während gleichzeitig (zweiter Browser/Tab) aktiv eine Seite genutzt wird (z.B. Kartenupdate in `edit.php`) → kein Verbindungsabbruch, kein unerwarteter Logout während des Deploys. Manuell schwer reproduzierbar (Timing-Fenster sehr klein) — primär Code-Review-Absicherung, kein zwingender manueller Test.
[ ] Normaler Deploy ohne parallele Last funktioniert weiterhin unverändert: Statusanzeige, "Deploy starten", Erfolgsmeldung mit Anzahl kopierter/übersprungener Dateien. _(v3.2.46)_
[ ] Nach einem Deploy sind geschützte Dateien (`db-credentials.php`, `config-runtime.php`, `deploy.php`, `deploy-config.php`, `install.php`) unverändert — Regressionscheck, da die Kopierlogik angepasst wurde. _(v3.2.46)_

---
