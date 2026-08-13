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
[ ] `stats.php?list_id=<fremde ID>` → Redirect auf die globale Ansicht (`stats.php` ohne `list_id`), keine fremden Daten sichtbar. _(v3.2.23, Redirect-Ziel geändert v3.6.0)_
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
[ ] Leitner, 1. Versuch richtig (Karte steigt auf, z.B. Fach 1→2) → Panel zeigt zusätzliche Zeile "Intervall Fach X: Y Tage, Basis: Z" — Basis + Intervall ergibt rechnerisch exakt das angezeigte neue Fälligkeitsdatum. _(v3.3.23)_
[ ] Leitner, 2. Versuch richtig (Karte bleibt in Fach 1) → KEINE Intervall-Zusatzzeile, da hier fest mit +1 Tag gerechnet wird statt einem Tabellen-Lookup. _(v3.3.23)_
[ ] Leitner und Drill: das Debug-Panel klebt fix am unteren Bildschirmrand (`position: fixed`) statt im normalen Textfluss zu stehen — sichtbar ohne Scrollen, unabhängig davon wie lang die Karte/Seite ist. _(v3.2.44)_
[ ] Auf dem iPhone überlappt das fixierte Panel NICHT die Statusleiste/Home-Indicator-Leiste am unteren Rand (sichtbarer Abstand durch `safe-area-inset-bottom`). _(v3.2.44)_
[ ] Ungewöhnlich lange Karte (z.B. langer Beispielsatz) → das fixierte Panel kann dabei die Antwort-Buttons überlagern — dagegen der Schliessen-Button (siehe unten). _(v3.2.44)_
[ ] Debug-Panel zeigt rechts einen "×"-Schliessen-Button (Bootstrap `btn-close`). Klick darauf blendet das Panel sofort aus, ohne die Seite neu zu laden. _(v3.3.6)_
[ ] Nach dem Schliessen bleibt der Rest der Kartenanzeige (Karte, Antwort-Buttons) uneingeschränkt bedienbar — insbesondere wenn das Panel vorher die Buttons verdeckt hatte. _(v3.3.6)_
[ ] Bei der nächsten beantworteten Karte erscheint ein neues Debug-Panel wieder normal (das Schliessen wirkt nur für die aktuell angezeigte Meldung, nicht dauerhaft für die Session). _(v3.3.6)_
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

[ ] `help.php`: Einleitungstext direkt unter dem Titel fasst die App in ein bis zwei Sätzen zusammen (kein eigener Accordion-Abschnitt mehr — siehe Abschnitt 32 für die aktuelle Struktur). _(v3.2.37, Struktur geändert v3.5.0)_
[ ] `help.php`, Abschnitt "Leitner-Modus" (Grundlagen): kein Pin-Hinweis mehr direkt im Abschnitt, stattdessen ein Verweis-Link auf den eigenständigen Abschnitt "Karten gezielt für Drill vormerken" (Fortgeschritten), der das ausgefüllte Pin-Symbol samt Icon erklärt. _(v3.2.37, ausgelagert v3.5.0)_
[ ] `help.php`, Abschnitt "Drill-Modus" (Grundlagen): die ausführliche Vormerken-Erklärung wurde komplett in den neuen Abschnitt "Karten gezielt für Drill vormerken" (Fortgeschritten) ausgelagert — im Drill-Modus-Abschnitt selbst steht nur noch ein kurzer Verweis-Link dorthin. _(v3.2.37, ausgelagert v3.5.0)_
[ ] MCP-Server: Karte mit deutschem Begriff, der ein "ß" enthalten würde (z.B. "Straße") → Agent schreibt stattdessen "Strasse" (de-CH). _(v3.2.37)_
[ ] MCP-Server: Liste mit Sprache A = Deutsch, Sprache B = Fremdsprache, `speech_lang_b` gesetzt → Agent fragt NICHT explizit nach der Muttersprache, sondern nimmt Sprache A als gegeben an. _(v3.2.37)_
[ ] MCP-Server: Liste bei der beide Sprachen für den User erkennbar fremd sind (z.B. im Gespräch erwähnt) → Agent fragt in diesem Fall doch explizit nach der Muttersprache, bevor `phonetik_b` befüllt wird. _(v3.2.37)_

## 12. Bugfix: Drill-Meisterung stufte Leitner-Fach fälschlich zurück _(v3.2.38)_

[ ] Karte mit `drill_mastery = 1` und `leitner_box = 4` (z.B. früher einmal im Drill gemeistert, danach unabhängig über Leitner bis Fach 4 aufgestiegen) im Drill erneut 3× hintereinander "Gewusst" → Fach bleibt bei 4, `drill_mastery` steigt auf 2, `next_due_date` unverändert. _(v3.2.38)_
[ ] Debug-Panel zeigt in diesem Fall "Fach 4→4" (keine Änderung) statt einer Rückstufung. _(v3.2.38)_
[ ] Normalfall weiterhin korrekt: komplett neue Karte (`drill_mastery = 0`, `leitner_box = 1`) wird im Drill gemeistert → steigt wie gewohnt auf Fach 2 (bzw. 3 bei der zweiten, 4 bei der dritten Meisterung), da das Ziel-Fach dort höher ist als das aktuelle. _(v3.2.38)_

## 13. Vormerken direkt in der Leitner-Session _(v3.2.41, Bugfix aufgedeckte Karte v3.3.10)_

[ ] Laufende Leitner-Session: oben links auf der Karte erscheint ein runder, **klickbarer** Pin-Button (nicht nur ein Symbol). _(v3.2.41)_
[ ] Klick auf den Pin-Button → Karte wird "für Drill vorgemerkt" (Icon wird ausgefüllt), dieselbe Karte bleibt weiterhin angezeigt, Fortschritt/Position in der Session bleibt unverändert. _(v3.2.41)_
[ ] Erneuter Klick → Vormerkung wird wieder entfernt (Icon wird zum Umriss). _(v3.2.41)_
[ ] **Karte antippen, sodass die Übersetzung/Lösung aufgedeckt ist, dann den Pin-Button klicken** → die Kartenanzeige ändert sich dabei NICHT, die Lösung bleibt sichtbar (kein Zurückspringen auf die zugeklappte Vorderseite). Gilt in beide Richtungen (Vormerken wie Entfernen). _(v3.3.10, Bugfix — vorher löste der Klick einen vollen Seiten-Reload aus, der den rein clientseitigen "aufgedeckt"-Zustand zurücksetzte)_
[ ] Pin-Button-Klick bei zugeklappter Karte (Lösung noch nicht aufgedeckt) → Karte bleibt weiterhin zugeklappt, kein unerwartetes Aufdecken durch den Klick selbst. _(v3.3.10)_
[ ] Netzwerkfehler/CSRF-Fehler beim Umschalten (z.B. Session inzwischen abgelaufen) → Icon/Button-Zustand ändert sich NICHT optisch, wenn die Anfrage fehlschlägt (kein optimistisches Update ohne Erfolg). _(v3.3.10)_
[ ] Das Vormerken/Entfernen zählt **nicht** als Antwort — Zähler "Gewusst/Nicht gewusst/Aufgestiegen" sowie die Fortschrittsanzeige bleiben unverändert. _(v3.2.41)_
[ ] Klick auf den Pin-Button klappt die Karte NICHT um (kein versehentliches Aufdecken der Antwort). _(v3.2.41)_
[ ] Manuell gesendeter POST mit `card_id` einer NICHT aktuell angezeigten Karte → wird ignoriert, kein `card_progress`-Eintrag entsteht/ändert sich. _(v3.2.41)_
[ ] `edit.php`-Kartenansicht und Leitner-Session zeigen für dieselbe Karte konsistent denselben Vormerkungs-Status. _(v3.2.41)_
[ ] In `drill.php` ist das Pin-Symbol seit v3.6.0 ebenfalls klickbar (siehe eigener Abschnitt "Vormerken direkt in der Drill-Session"). _(v3.2.41, Verhalten geändert v3.6.0)_

## 14. Listenauswahl in der Leitner-Session zeigt nur aktive Listen _(v3.2.45)_

[ ] `learn.php` ohne `list_id`-Vorauswahl aufrufen, mind. eine eigene Liste ist inaktiv → die Checkbox-Liste "Listen auswählen" zeigt nur aktive Listen, inaktive tauchen nicht auf. _(v3.2.45)_
[ ] Sind ALLE eigenen Listen inaktiv → Hinweis "Du hast noch keine Listen" erscheint (gleiches Verhalten wie ganz ohne Listen), kein leeres Formular. _(v3.2.45)_
[ ] Startseite → Leitner-Button einer aktiven Liste (setzt `list_id` in der URL) funktioniert weiterhin unverändert (Liste vorausgewählt, keine Checkbox-Liste sichtbar). _(v3.2.45)_

## 15. Deploy: atomares Überschreiben der Live-Dateien _(v3.2.46)_

[ ] Deploy ausführen, während gleichzeitig (zweiter Browser/Tab) aktiv eine Seite genutzt wird (z.B. Kartenupdate in `edit.php`) → kein Verbindungsabbruch, kein unerwarteter Logout während des Deploys. Manuell schwer reproduzierbar (Timing-Fenster sehr klein) — primär Code-Review-Absicherung, kein zwingender manueller Test.
[ ] Normaler Deploy ohne parallele Last funktioniert weiterhin unverändert: Statusanzeige, "Deploy starten", Erfolgsmeldung mit Anzahl kopierter/übersprungener Dateien. _(v3.2.46)_
[ ] Nach einem Deploy sind geschützte Dateien (`db-credentials.php`, `config-runtime.php`, `deploy.php`, `deploy-config.php`, `install.php`) unverändert — Regressionscheck, da die Kopierlogik angepasst wurde. _(v3.2.46)_

---

## 16. Listenübergreifende Leitner-/Drill-Buttons und Drill-Listenauswahl _(v3.2.47)_

[ ] Startseite: Button-Reihe neben "Meine Listen" zeigt in dieser Reihenfolge: "Leitner", "Drill", "Meine Listen" (mit Stift-Icon `bi-pencil` vor dem Text), "Statistik" (mit Icon `bi-bar-chart-line` vor dem Text). _(v3.2.48)_
[ ] Auf schmalem Viewport (iPhone, 430 px) bricht die Button-Reihe sauber um, keine Seite lässt sich horizontal wegschieben. _(v3.2.47)_
[ ] Klick auf "Drill" (ohne vorausgewählte Liste) → neue Auswahlseite "Drill-Session starten" mit Checkboxen aller eigenen aktiven Listen (erste vorausgewählt), Button "Drill starten". _(v3.2.47)_
[ ] Mehrere Listen ankreuzen und "Drill starten" → Session startet mit Karten aus allen ausgewählten Listen gemischt. _(v3.2.47)_
[ ] Kein Häkchen gesetzt und trotzdem absenden (z.B. per manuell verändertem POST) → zurück auf die Drill-Auswahlseite mit Fehlermeldung "Bitte mindestens eine Liste auswählen.", keine Session gestartet. _(v3.2.47)_
[ ] Sind alle eigenen Listen inaktiv bzw. keine Liste vorhanden → Hinweis "Du hast noch keine Listen" statt leerem Formular. _(v3.2.47)_
[ ] Nur inaktive Listen erscheinen in der Drill-Auswahl NICHT (analog zu Leitner, siehe Abschnitt 14). _(v3.2.47)_
[ ] Pro-Liste-Button "Drill" auf einer einzelnen Listen-Karte zeigt seit v3.3.5 ebenfalls die Konfigurationsseite (Liste als Text vorausgewählt, Richtung + Timer wählbar) statt sofort zu starten. _(v3.3.5)_
[ ] Klick auf "Leitner" (ohne vorausgewählte Liste) führt weiterhin zur bestehenden `learn.php`-Konfigurationsseite (Richtung, Kartenanzahl) — unverändert durch diese Änderung. _(v3.2.47)_
[ ] Nach Abschluss einer über die neue Drill-Auswahl gestarteten Session mit mehreren Listen: "Erneut starten"-Button auf dem Abschluss-Screen erscheint NICHT (nur bei genau einer Liste vorhanden, bestehendes Verhalten). _(v3.2.47)_

---

## 17. Hilfe-Abschnitt "Aufbau einer Lernkarte" _(v3.3.2)_

[ ] `help.php`: Abschnitt "Aufbau einer Lernkarte" liegt weiterhin zwischen "Wörter hinzufügen" und "Leitner-Modus", jetzt als 4. von 6 Abschnitten innerhalb der Gruppe "Grundlagen". _(v3.3.2, Gruppierung v3.5.0)_
[ ] Screenshot (`img/learner-karte.png`) wird angezeigt, ist scharf/nicht verzerrt und bleibt auf schmalem Viewport (iPhone) innerhalb der Seitenbreite. _(v3.3.2)_
[ ] Accordion-Abschnitte lassen sich unabhängig voneinander auf-/zuklappen — anders als bisher bleibt ein bereits geöffneter Abschnitt offen, wenn ein weiterer geöffnet wird (kein gemeinsames `data-bs-parent` mehr, bewusste Änderung). _(v3.3.2, Verhalten geändert v3.5.0)_
[ ] Als Nicht-Admin eingeloggt: 11 Abschnitte sichtbar (kein Admin-/MCP-Abschnitt), als Admin: 13 Abschnitte — siehe Abschnitt 32 für die aktuelle Struktur/Zählung. _(v3.3.9, Zählung aktualisiert v3.5.0)_

---

## 18. Hörprobe im Aussprache-Abschnitt der Hilfeseite _(v3.3.3, Text/Sprache/Button angepasst v3.3.4)_

[ ] `help.php` → Abschnitt "Aussprache: Audio & Lautschrift": Button ist vorhanden, Icon (`bi-volume-up-fill`) und Button-Stil sind identisch zum 🔊-Knopf auf den Lernkarten, zusätzlich mit Text "Klick mich" beschriftet; daneben steht `Begriff: „Can you hear me?" · Sprache: en-GB`. _(v3.3.4)_
[ ] Klick auf den Button (Gerät/Browser mit Sprachausgabe) → "Can you hear me?" wird tatsächlich vorgelesen, nach Möglichkeit mit einer britisch-englischen Stimme (`en-GB`), sonst Fallback auf eine andere `en-*`-Stimme. _(v3.3.4)_
[ ] Mehrfaches Klicken hintereinander unterbricht die vorherige Wiedergabe sauber, statt sich zu überlagern (`speechSynthesis.cancel()` vor jeder neuen Wiedergabe). _(v3.3.3)_
[ ] Browser ohne Web-Speech-API-Unterstützung: Klick auf den Button tut nichts, keine Fehlermeldung/kein JS-Fehler in der Konsole. _(v3.3.3)_

---

## 19. Lernrichtung & Timer im Drill-Modus _(v3.3.5)_

[ ] `drill.php` ohne laufende Session aufrufen (egal ob mit oder ohne `list_id`) → Konfigurationsseite zeigt zusätzlich zur Liste die Felder "Lernrichtung" (A→B, B→A, Gemischt, Zufall — untereinander, Zufall vorausgewählt seit v3.3.11) und "Timer" (Minuten-Feld mit ±5-Buttons, vorausgefüllt mit dem Wert aus den Einstellungen). _(v3.3.11)_
[ ] Bei vorausgewählter Liste (`list_id` in der URL) zeigen die Richtungs-Labels sofort die echten Sprachnamen dieser Liste (z.B. "Deutsch → English") statt "A → B". _(v3.3.5)_
[ ] Bei der listenübergreifenden Auswahl (Checkboxen) aktualisieren sich die Richtungs-Labels dynamisch je nach angehakter erster Liste — identisches Verhalten wie im Leitner-Setup. _(v3.3.5)_
[ ] Richtung "B→A" gewählt → auf der Lernkarte erscheint zuerst Sprache B, nach dem Aufdecken Sprache A; Lautschrift/🔊-Knopf erscheinen dabei oben (an Sprache B gebunden, nicht an die Position). _(v3.3.5)_
[ ] Richtung "Gemischt" gewählt, mehrere Karten in der Session → einzelne Karten zeigen A→B, andere B→A; dieselbe Karte behält ihre Richtung über die ganze Session hinweg (deterministisch über die Karten-ID, kein Wechsel bei erneutem Anzeigen). _(v3.3.5)_
[ ] Timer auf z.B. 2 Minuten gesetzt und Session gestartet → Session endet tatsächlich nach ca. 2 Minuten (Countdown in der Navbar beginnt bei 2:00), nicht beim Standardwert aus den Einstellungen. _(v3.3.5)_
[ ] Nach einer Session mit angepasstem Timer: die globale Einstellung (Einstellungen → Drill-Modus → Timer) bleibt unverändert — der Session-Timer wird nicht dauerhaft gespeichert. _(v3.3.5)_
[ ] Eingabe ausserhalb 1–120 Minuten (z.B. 0 oder 500, per manuell verändertem POST) wird serverseitig auf den gültigen Bereich begrenzt. _(v3.3.5)_
[ ] "Erneut starten" auf dem Drill-Abschluss-Screen (nur bei genau einer Liste) führt jetzt zur Konfigurationsseite mit vorausgewählter Liste statt die Session sofort neu zu starten. _(v3.3.5)_
[ ] `help.php`, Abschnitte "Aufbau einer Lernkarte" und "Drill-Modus": Texte erwähnen korrekt, dass Lernrichtung und Timer in beiden Modi wählbar sind (nicht mehr "im Drill-Modus fest A→B"). _(v3.3.5)_

---

## 20. Duplikat-Prüfung beim Import auf Sprachenpaar begrenzt _(v3.3.7)_

[ ] Liste A (Deutsch/Italienisch) enthält die Karte "Haus"/"casa". CSV mit "Haus"/"maison" in eine Liste B (Deutsch/Französisch) importieren → **kein** Duplikat-Treffer, Karte erscheint als neu importierbar. _(v3.3.7)_
[ ] Dieselbe CSV-Zeile "Haus"/"maison" existiert bereits identisch in einer anderen Deutsch/Französisch-Liste → wird weiterhin korrekt als Duplikat erkannt (Sprachenpaar identisch). _(v3.3.7)_
[ ] Archivierte Karte mit gleichem Wortpaar, aber in einer Liste mit anderem Sprachenpaar → erscheint **nicht** in der Archiv-Reaktivierungs-Abfrage, wird stattdessen normal importiert. _(v3.3.7)_
[ ] Zwei eigene Listen mit identischer Sprachbezeichnung, aber unterschiedlicher Gross-/Kleinschreibung (z.B. "Englisch" vs. "englisch") → gelten als unterschiedliches Sprachenpaar (exakter Textvergleich, keine Normalisierung) — Duplikat-Check greift dort bewusst nicht. _(v3.3.7)_
[ ] Bestehendes Verhalten unverändert: gleiches Sprachenpaar, gleiches Wortpaar → weiterhin als Duplikat erkannt, inkl. globaler Entscheidung (alle überspringen/importieren) und Ausnahmen. _(v3.3.7)_

---

## 21. Weitere Screenshots auf der Hilfeseite _(v3.3.8)_

[ ] `help.php`, Abschnitt "Wörter hinzufügen": Screenshot `neues-wort.png` erscheint nach der Aufzählung der drei Wege, zeigt das Formular für "Manuell". _(v3.3.8)_
[ ] `help.php`, Abschnitt "Leitner-Modus": Screenshot `neue-session-leitner.png` erscheint direkt nach dem einleitenden Absatz, vor der Aufzählung. _(v3.3.8)_
[ ] `help.php`, Abschnitt "Drill-Modus": Screenshot `neue-session-drill.png` erscheint direkt nach dem einleitenden Absatz, darunter ein Tipp-Hinweis "Der Timer sollte mindestens 5 Minuten laufen …". _(v3.3.8)_
[ ] `help.php`, Abschnitt "Drill-Modus": Screenshot `drill-timer.png` (Abbrechen-Symbol + Countdown) erscheint nach der Aufzählung, mit kurzem Begleittext zum Timer/Abbrechen während der Session. _(v3.3.8)_
[ ] `drill-timer.png` wird deutlich kleiner als die anderen drei Screenshots dargestellt (feste Breite 105px, nicht mehr auf 220px hochskaliert) — wirkt wie ein kleiner Navbar-Ausschnitt, nicht wie ein grosser Screenshot. _(v3.3.9)_
[ ] Alle vier neuen Bilder sind scharf, nicht verzerrt, zentriert bzw. korrekt ausgerichtet und bleiben auf schmalem Viewport (iPhone) innerhalb der Seitenbreite. _(v3.3.9)_
[ ] Bildstil (Rahmen, Schatten, abgerundete Ecken, Bildunterschrift in gedämpfter Schrift) ist einheitlich mit dem bereits vorhandenen Screenshot `learner-karte.png` im Abschnitt "Aufbau einer Lernkarte". _(v3.3.8)_

## 22. Hilfeseite: Einleitung als Fliesstext statt eigener Abschnitt _(v3.3.9, abgelöst v3.5.0)_

[ ] `help.php` zeigt die Einleitung als kurzen Fliesstext-Absatz direkt unter der Überschrift "Hilfe & Handbuch" — kein eigener, auf-/zuklappbarer Accordion-Abschnitt "Einleitung" mehr (ersetzt durch die Schnellstart-Kachel darunter, siehe Abschnitt 32). _(v3.5.0)_
[ ] Alle Accordion-Abschnitte (inkl. "Login & Person") sind beim Laden der Seite eingeklappt. _(v3.3.9)_
[ ] Der Abschnitt heisst weiterhin schlicht "Login & Person" — Inhalt inhaltlich unverändert (Login-Ablauf, Passwort/E-Mail-Verwaltung, Admin-Hinweis), Formulierung des ersten Satzes leicht präzisiert (Karten werden nie zwischen Personen geteilt). _(v3.3.9, Formulierung präzisiert v3.5.0)_
[ ] Als Nicht-Admin eingeloggt: 11 Abschnitte sichtbar, als Admin: 13 Abschnitte — siehe Abschnitt 32. _(v3.3.9, Zählung aktualisiert v3.5.0)_
[ ] Querverweis im Admin-Abschnitt ("Für Admins …") auf "Login & Person" verwendet weiterhin den korrekten Namen. _(v3.3.9)_
[ ] Auf-/Zuklappen jedes Abschnitts funktioniert weiterhin einzeln — mehrere Abschnitte können jetzt aber gleichzeitig offen sein, siehe Abschnitt 17. _(v3.3.9, Verhalten geändert v3.5.0)_

## 23. Hilfeseite: Hinweis auf mobile Heatmap-Anpassung _(v3.3.9)_

[ ] `help.php`, Abschnitt "Statistik & Heatmap im Detail" _(hiess bis v3.4.1 "Statistik & Streak")_: Punkt in der Aufzählung erwähnt, dass sich die Heatmap der Bildschirmbreite anpasst (Handy: kürzerer Zeitraum, Desktop: alle 52 Wochen). _(v3.3.9, Abschnittsname geändert v3.5.0)_
[ ] Text widerspricht nicht der bereits bestehenden, ausführlicheren technischen Beschreibung in `docs/ANFORDERUNGEN.md` (Abschnitt "Statistik-Dashboard") — reine Ergänzung auf der Hilfeseite, keine Verhaltensänderung an `stats.php` selbst. _(v3.3.9)_

---

## 24. Lernrichtung "Zufall" (Leitner & Drill) _(v3.3.11)_

[ ] `learn.php`-Setup und `drill.php`-Setup: die vier Lernrichtungs-Optionen stehen jetzt **untereinander** (nicht mehr in einer Zeile), Reihenfolge A→B, B→A, Gemischt, Zufall. _(v3.3.11)_
[ ] "Zufall" ist beim Öffnen der Konfigurationsseite vorausgewählt (nicht mehr A→B). Gilt sowohl bei vorausgewählter Liste als auch bei der Checkbox-Auswahl mehrerer Listen. _(v3.3.11)_
[ ] Session mit "Zufall" mehrfach hintereinander starten (gleiche Liste) → über mehrere Starts hinweg erscheinen alle drei Richtungen (A→B, B→A, gemischte Karten) — nicht immer dieselbe. Manuell schwer 100%ig zu verifizieren (Zufall), aber über 5–10 Starts sollte Varianz sichtbar sein. _(v3.3.11)_
[ ] Bei "Zufall" bleibt die einmal gewürfelte Richtung für die **gesamte Session** gleich — kein Wechsel der Richtung von Karte zu Karte innerhalb derselben Session (ausser die gewürfelte Richtung war selbst "Gemischt", dann wechselt es wie gewohnt pro Karte deterministisch über die Karten-ID). _(v3.3.11)_
[ ] Manuell gesendeter POST mit ungültigem `direction`-Wert (z.B. leer oder Fantasiewert) → Server fällt auf "Zufall" zurück (würfelt eine der drei Richtungen), keine Fehlermeldung, keine feste A→B-Rückstufung. _(v3.3.11)_
[ ] Explizite Auswahl A→B, B→A oder Gemischt funktioniert weiterhin unverändert wie bisher (nur wenn NICHT "Zufall" gewählt ist, greift keine zusätzliche Zufalls-Logik). _(v3.3.11)_
[ ] Verhalten ist in Leitner und Drill identisch (gemeinsame Funktion `resolve_direction()` in `includes/auth.php`). _(v3.3.11)_
[ ] `help.php`: Erwähnungen der Lernrichtung (Abschnitte "Aufbau einer Lernkarte", "Leitner-Modus", "Drill-Modus") listen jetzt alle vier Optionen inkl. Zufall als Default. _(v3.3.11)_

---

## 25. Lautschrift in der Entdecken-Vorschau _(v3.3.13)_

[ ] `discover.php?list_id=X` einer öffentlichen Liste mit Karten, die eine Lautschrift (`phonetic_b`) hinterlegt haben → Vorschau-Tabelle zeigt die Lautschrift in eckigen Klammern direkt hinter dem Begriff in Sprache B, z.B. `cinquante-neuf [sɛ̃kˈɑ̃tnˈœf]`. _(v3.3.13)_
[ ] Karten ohne Lautschrift zeigen weiterhin nur den Begriff (Sprache B), keine leeren eckigen Klammern. _(v3.3.13)_
[ ] Darstellung (Farbe, Grösse, Position) entspricht der Lautschrift-Anzeige in der Kartenübersicht (`edit.php`). _(v3.3.13)_
[ ] Nach dem Kopieren der Liste erscheint dieselbe Lautschrift unverändert in der eigenen Kartenübersicht — reine Vorschau-Ergänzung, kein neues Verhalten beim Kopieren selbst (das gab es schon vorher). _(v3.3.13)_

---

## 26. Listen bearbeiten: klare Abgrenzung zu "Neue Liste erstellen" _(v3.3.14)_

[ ] `lists.php` normal aufrufen (kein `?edit=`) → Karte "Neue Liste erstellen" ist wie gewohnt sichtbar. _(v3.3.14)_
[ ] Klick auf "Liste bearbeiten" bei einer Liste → Karte "Neue Liste erstellen" verschwindet, solange das Bearbeiten-Formular offen ist. _(v3.3.15)_
[ ] Der bearbeitete Listeneintrag ist optisch hervorgehoben (blauer Rahmen) und trägt die Überschrift "Liste bearbeiten: <Name>" über dem Formular. _(v3.3.14)_
[ ] Bearbeitet man gezielt die **unterste** Liste einer langen Übersicht → die Seite scrollt nach dem Laden automatisch zu diesem Eintrag (smooth scroll, mittig im sichtbaren Bereich), kein manuelles Suchen/Scrollen nötig. _(v3.3.14)_
[ ] Das Namensfeld im Bearbeiten-Formular ist nach dem Laden automatisch fokussiert (Cursor blinkt dort, Tippen ist sofort möglich). _(v3.3.14)_
[ ] "Abbrechen" im Bearbeiten-Formular → zurück zu `lists.php` ohne `?edit=`, "Neue Liste erstellen" erscheint wieder normal. _(v3.3.14)_
[ ] Speichern der Änderungen funktioniert weiterhin unverändert (Name, Beschreibung, Sprachen, Öffentlich-Flag, Aussprache-Sprachcode). _(v3.3.14)_

---

## 27. Icons und neue Reihenfolge der Aktionsleiste auf "Meine Listen" _(v3.3.15)_

[ ] Pro Liste erscheinen die sechs Buttons in dieser Reihenfolge, jeweils nur als Icon (kein Text im Button): "Liste bearbeiten" (`bi-pencil`), "Karten bearbeiten" (`bi-pencil-square`), "Import" (`bi-upload`), "Migrieren" (`bi-box-arrow-right`), "Export" (`bi-download`), "Löschen" (`bi-trash`). _(v3.3.17)_
[ ] Hovern über jeden der sechs Buttons zeigt einen Tooltip mit dem jeweiligen Namen (z.B. "Liste bearbeiten"), auch über "Migrieren" trotz gleichzeitigem `data-bs-toggle="modal"`. _(v3.3.17)_
[ ] Klick auf "Migrieren" öffnet weiterhin normal das Migrations-Modal (Tooltip-Init darf das nicht stören). _(v3.3.17)_
[ ] Die Navbar-Icons oben (Logout, Hilfe, Einstellungen etc.) zeigen weiterhin ihre gewohnten (nativen) Tooltips — kein Bootstrap-Tooltip-Verhalten dort durch die neue Initialisierung auf `lists.php`. _(v3.3.17)_
[ ] Buttons sind durch den Wegfall des Texts deutlich schmaler — auf schmalem Viewport passen dadurch mehr Buttons pro Zeile, bevor umgebrochen wird. _(v3.3.17)_
[ ] "Liste bearbeiten" führt weiterhin zu `lists.php?edit=X` (Inline-Formular, siehe Abschnitt 26), "Karten bearbeiten" weiterhin zu `edit.php?list_id=X`. _(v3.3.15)_
[ ] "Migrieren" fehlt weiterhin, wenn nur eine einzige eigene Liste existiert — Reihenfolge der übrigen fünf Buttons bleibt dabei unverändert (kein Lücken-Sprung). _(v3.3.15)_
[ ] Auf schmalem Viewport (iPhone) brechen die Buttons sauber um (`flex-wrap`), keine horizontale Scrollbar auf "Meine Listen". _(v3.3.15)_
[ ] Farben unverändert: "Karten bearbeiten" weiterhin blau hervorgehoben (`btn-outline-primary`), "Löschen" weiterhin rot (`btn-outline-danger`), restliche Buttons grau (`btn-outline-secondary`). _(v3.3.15)_
[ ] Alle sechs Buttons funktional unverändert — reine Icon-/Reihenfolge-/Beschriftungsänderung, kein neues Verhalten. _(v3.3.15)_

---

## 28. Kartenübersicht: klare Abgrenzung Bearbeiten vs. "Neue Karte hinzufügen" _(v3.3.16)_

[ ] `edit.php?list_id=X` normal aufrufen (kein `?edit=`) → Karte "Neue Karte hinzufügen" ist wie gewohnt sichtbar. _(v3.3.16)_
[ ] Klick auf das Stift-Icon "Bearbeiten" einer Karte → "Neue Karte hinzufügen" verschwindet, solange die Zeile bearbeitet wird; die Zeile bleibt wie bisher gelb hervorgehoben. _(v3.3.16)_
[ ] Bearbeitet man gezielt eine Karte weit unten in einer langen Liste (z.B. bei "Alle" mit 100+ Karten) → die Seite springt beim Laden direkt zur bearbeiteten Zeile, kein manuelles Suchen/Scrollen. _(v3.3.16)_
[ ] Das erste Feld (Sprache A) der Bearbeiten-Zeile ist nach dem Laden automatisch fokussiert. _(v3.3.16)_
[ ] "Abbrechen" in der Bearbeiten-Zeile → zurück zur Normalansicht, Seite springt zur selben Zeile (jetzt nicht mehr hervorgehoben), "Neue Karte hinzufügen" erscheint wieder. _(v3.3.16)_
[ ] "Speichern" → Änderungen werden übernommen, Seite springt nach dem Neuladen zur gespeicherten Zeile in der Normalansicht (nicht an eine falsche/verschobene Position, da "Neue Karte hinzufügen" jetzt wieder eingeblendet ist). _(v3.3.16)_
[ ] Archivieren/Reaktivieren/Löschen einer Karte (ausserhalb des Bearbeiten-Modus) verhalten sich weiterhin wie bisher: Seite kehrt ungefähr an dieselbe Scroll-Position zurück (bestehender Pixel-Restore-Mechanismus, unverändert). _(v3.3.16)_
[ ] "Karte ansehen" (Augen-Icon) und das Modal dahinter funktionieren unverändert. _(v3.3.16)_

---

## 29. Verfügbarkeits-Hinweis auf der Leitner-Setup-Seite _(v3.3.18, als Infobox v3.3.20, Tageslimit-Verbrauch ergänzt v3.3.21, nur bei Drosselung sichtbar v3.3.22, unterscheidet Tageslimit vs. leere Warteschlange v3.3.24)_

[ ] Genug fällige/aktivierbare Karten vorhanden, um die eingestellte Kartenanzahl zu erreichen (z.B. Kartenanzahl 10, 15 verfügbar) → Infobox bleibt unsichtbar, kein leerer Kasten mit Rahmen sichtbar. _(v3.3.22)_
[ ] Liste mit grosser Warteschlange (z.B. 100+, Warteschlange ist NICHT der Engpass) und 0 bereits fälligen/aktivierten Karten, Kartenanzahl auf 25 gestellt (Standard-Vorschlag) → Infobox erscheint mit "Pro Liste werden maximal 10 neue Karten pro Tag aus der Warteschlange aktiviert. Die Session enthält daher 10 Karten: 0 heute fällig + 10 neu aus der Warteschlange." — ohne "heute wurden davon bereits …"-Satzteil, da noch nichts vom Tageslimit verbraucht wurde. _(v3.3.22)_
[ ] Liste mit bereits teilweise verbrauchtem Tageslimit (z.B. 7 von 10, Warteschlange hat genug Karten für die restlichen 3) und Kartenanzahl über der verfügbaren Zahl → Infobox zeigt zusätzlich "— heute wurden davon bereits 7 genutzt" und danach "7 heute fällig + 3 neu aus der Warteschlange" — erklärt explizit, warum nicht die vollen 10 aus der Warteschlange kommen. _(v3.3.21)_
[ ] **Kleine Warteschlange als eigentlicher Engpass:** Liste mit nur noch 1 Karte in der Warteschlange, Tageslimit noch nicht ausgeschöpft (z.B. 0 heute genutzt), Kartenanzahl über der verfügbaren Zahl → Infobox nennt NICHT das Tageslimit, sondern "In der Warteschlange dieser Liste ist nur noch 1 Karte übrig." gefolgt von "Die Session enthält daher X Karten: Y heute fällig + 1 neu aus der Warteschlange." _(v3.3.24)_
[ ] Warteschlange dieser Liste komplett leer (0 Karten), Kartenanzahl über der verfügbaren Zahl → Infobox zeigt "Die Warteschlange ist leer." statt "… nur noch 0 Karten übrig." _(v3.3.24)_
[ ] Checkbox-Mehrfachauswahl, Warteschlange ist der Engpass → Formulierung "In der Warteschlange der ausgewählten Listen sind nur noch N Karten übrig." (Mehrzahl "sind"/"Listen"), nicht die Einzahl-Formulierung. _(v3.3.24)_
[ ] Kartenanzahl manuell per Eingabefeld auf einen Wert ≤ der verfügbaren Zahl ändern → Infobox verschwindet live, ohne Neuladen der Seite. Wieder über die verfügbare Zahl setzen → Infobox erscheint wieder. _(v3.3.22)_
[ ] Dasselbe funktioniert auch über die ±5-Buttons (nicht nur bei manueller Eingabe). _(v3.3.22)_
[ ] Vorausgewählte Liste (Klick auf "Leitner" bei einer einzelnen Liste von der Startseite) → Sichtbarkeit der Infobox ist beim Laden sofort korrekt (kein kurzes Aufblitzen einer leeren Box vor dem ersten JS-Durchlauf). _(v3.3.22)_
[ ] Listenübergreifende Auswahl (Checkboxen): Infobox-Sichtbarkeit und -Inhalt aktualisieren sich beim An-/Abwählen einer Liste live (Summe über alle aktuell angehakten Listen), ohne Neuladen der Seite. _(v3.3.18)_
[ ] Zweite Session am selben Tag mit derselben Liste (bereits 10 heute aktiviert), Kartenanzahl über 0 gestellt → Infobox zeigt "heute wurden davon bereits 10 genutzt" und "0 neu aus der Warteschlange". _(v3.3.21)_
[ ] Zwei Listen NACHEINANDER in getrennten Sessions gelernt (je eigene Session) → jede Liste bekommt ihr eigenes 10er-Tageskontingent (zusammen bis zu 20 neue Karten). Beide Listen GEMEINSAM in einer Session ausgewählt → sie teilen sich ein Kontingent von 10, die Infobox-Zahl ist entsprechend niedriger. _(v3.3.20)_
[ ] Liste ganz ohne `card_progress`-Einträge (neu angelegt, noch nie gelernt) → bei ausreichend hoher Kartenanzahl zeigt die Infobox 0/0/0 statt eines Fehlers oder fehlender Zahl (Formulierung dabei: "Die Warteschlange ist leer."). _(v3.3.24)_
[ ] Tatsächlich gestartete Session enthält so viele Karten wie in der Infobox angegeben (sofern sichtbar) bzw. die volle eingestellte Anzahl (sofern die Infobox nicht sichtbar war) — Infobox und reales Verhalten stimmen überein. _(v3.3.18)_

---

## 30. Lernrichtung bei Mathe-Listen eingeschränkt _(v3.3.25)_

[ ] Vorausgewählte Mathe-Liste (Klick auf "Leitner" bzw. "Drill" bei einer Multiplikations-/Divisionsliste von der Startseite, `list_id` in der URL) → Konfigurationsseite zeigt nur noch die Option "Aufgabe → Ergebnis", fest angehakt; die drei anderen Radio-Buttons (Ergebnis→Aufgabe, Gemischt, Zufall) sind nicht sichtbar, stattdessen der Hinweistext "Bei Mathe-Listen ist nur 'Aufgabe → Ergebnis' sinnvoll." _(v3.3.25)_
[ ] Vorausgewählte Wortliste (nicht Mathe) → Verhalten unverändert, alle vier Optionen sichtbar wie bisher. _(v3.3.25)_
[ ] Checkbox-Auswahl mehrerer Listen: nur Mathe-Listen angehakt (z.B. Multiplikation + Division gemeinsam) → beim Anhaken wechselt die Ansicht live auf nur "Aufgabe → Ergebnis" (fest angehakt) + Hinweistext, ohne Neuladen der Seite. Eine Wortliste zusätzlich anhaken → alle vier Optionen erscheinen live wieder. _(v3.3.25)_
[ ] Checkbox-Auswahl mit Mathe-Liste als Erstes vorausgewählt (Standard: erste Liste in der Sortierung ist angehakt) → Konfigurationsseite zeigt beim ersten Laden bereits korrekt nur "Aufgabe → Ergebnis" (kein kurzes Aufblitzen aller vier Optionen). _(v3.3.25)_
[ ] Session mit einer reinen Mathe-Listen-Auswahl tatsächlich starten → Karten erscheinen durchgehend mit "Aufgabe" oben und "Ergebnis" unten, nie umgekehrt und nie gemischt. _(v3.3.25)_
[ ] Manuell gesendeter POST mit `direction=b_to_a` (oder `mixed`/`random`) bei einer ausschliesslich aus Mathe-Listen bestehenden Auswahl (z.B. manipuliertes Formular) → Server erzwingt trotzdem `a_to_b`, keine Karte erscheint mit "Ergebnis" oben. Gilt für Leitner UND Drill. _(v3.3.25)_
[ ] Gilt identisch in Leitner (`learn.php`) und Drill (`drill.php`) — gleiches Verhalten in beiden Modi. _(v3.3.25)_
[ ] Wortlisten (kein `language_a = 'Aufgabe'`) sind von alldem unberührt — Verhalten und alle vier Lernrichtungs-Optionen wie vor diesem Release. _(v3.3.25)_
[ ] `help.php`, Abschnitte "Leitner-Modus" und "Drill-Modus": Sätze zur Lernrichtung erwähnen jetzt zusätzlich, dass bei Aufgabe-Listen nur "Aufgabe → Ergebnis" verfügbar ist. _(v3.4.1)_

---

## 31. Listentyp "Wortliste" / "Aufgabe" beim Erstellen _(v3.4.0)_

[ ] "Neue Liste erstellen": neues Feld "Listentyp" mit zwei Radio-Buttons "Wortliste" (vorausgewählt) und "Aufgabe (Mathe)", direkt unter Name/Beschreibung. _(v3.4.0)_
[ ] "Wortliste" ausgewählt (Standard) → Formular verhält sich exakt wie bisher: Felder Sprache A, Sprache B und Aussprache-Sprachcode sichtbar und wie gewohnt nutzbar. _(v3.4.0)_
[ ] "Aufgabe (Mathe)" ausgewählt → Felder Sprache A, Sprache B und Aussprache-Sprachcode verschwinden live, stattdessen erscheint der Hinweistext "Bei 'Aufgabe' sind die Sprachen fix (Aufgabe → Ergebnis) und können später nicht mehr geändert werden." _(v3.4.0)_
[ ] Liste mit Typ "Aufgabe" erstellen und speichern → neue Liste erscheint in "Meine Listen" mit "Aufgabe → Ergebnis" als Sprachpaar, 0 Karten (keine automatisch generierten Karten wie beim Mathe-Generator). _(v3.4.0)_
[ ] Manuell gesendeter POST mit `list_type=math`, aber abweichenden Werten für `language_a`/`language_b` (manipuliertes Formular) → Server ignoriert die übermittelten Werte, Liste wird trotzdem mit "Aufgabe"/"Ergebnis" angelegt. _(v3.4.0)_
[ ] Karten zur neu erstellten Aufgabe-Liste manuell über `edit.php` hinzufügen → funktioniert wie bei jeder anderen Liste (kein Unterschied im Karten-Formular). _(v3.4.0)_
[ ] "Liste bearbeiten" bei einer Aufgabe-Liste (egal ob über den neuen Listentyp oder über den Mathe-Generator entstanden) → Sprache A und Sprache B werden als gesperrte (ausgegraute) Felder angezeigt statt als Eingabefelder, Aussprache-Sprachcode-Feld ist durch den Text "– kein Aussprache-Feld –" ersetzt. Überschrift zeigt zusätzlich "(Aufgabe-Liste — Sprachen sind fix)". _(v3.4.0)_
[ ] Bei einer Aufgabe-Liste im Bearbeiten-Formular bleiben Name, Beschreibung und Öffentlich/Privat normal editierbar und lassen sich speichern. _(v3.4.0)_
[ ] Manuell gesendeter POST mit `action=update` und geänderten `language_a`/`language_b`-Werten für eine bestehende Aufgabe-Liste (manipuliertes Formular, z.B. `language_a=Deutsch`) → Server ignoriert die Werte, Sprache A/B bleiben unverändert "Aufgabe"/"Ergebnis". _(v3.4.0)_
[ ] Die 2 bereits bestehenden, über den Mathe-Generator erzeugten Listen sind davon ebenfalls betroffen: Bearbeiten-Formular zeigt für sie jetzt ebenfalls gesperrte Sprachfelder (vorher frei editierbar). _(v3.4.0)_
[ ] Wortlisten (Typ nicht "Aufgabe") sind vom Bearbeiten-Formular unberührt — Sprache A/B und Aussprache-Sprachcode bleiben normal editierbar wie vor diesem Release. _(v3.4.0)_
[ ] Speichern einer Wortliste ohne inhaltliche Änderung (z.B. sofort auf "Speichern" klicken) → zeigt jetzt korrekt "Liste gespeichert." statt fälschlich "Liste nicht gefunden oder keine Berechtigung." (Nebenkorrektur: die Erfolgsmeldung hing bisher an der Anzahl geänderter Datenbankzeilen, nicht an der eigentlichen Berechtigungsprüfung). _(v3.4.0)_
[ ] `help.php`, Abschnitt "Wortlisten erstellen & verwalten": Aufzählungspunkt "Listentyp — Wortliste oder Aufgabe" erklärt beide Typen sowie die Abgrenzung zum separaten Mathe-Generator. Der Hinweis, dass Aufgabe-Listen kein Aussprache-Feld haben, steht seit v3.5.0 im separaten Abschnitt "Aussprache: Audio & Lautschrift" (Fortgeschritten) statt hier. _(v3.4.1, umorganisiert v3.5.0)_

---

## 32. Hilfeseite neu strukturiert: Schnellstart, Sprungmarken, Grundlagen/Fortgeschritten _(v3.5.0)_

[ ] `help.php`: direkt unter der Überschrift steht eine hervorgehobene Kachel "Neu hier? So geht's los" mit 4 nummerierten Schritten (Anmelden, Liste wählen, Wörter ergänzen, Losdrillen) sowie den Buttons "Zur Startseite" und "Meine Listen" — immer sichtbar, ohne dass ein Accordion-Abschnitt geöffnet werden muss. _(v3.5.0)_
[ ] Buttons "Zur Startseite" / "Meine Listen" in der Kachel führen tatsächlich zu `home.php` bzw. `lists.php`. _(v3.5.0)_
[ ] Darunter zwei nebeneinanderliegende Kästchen "Grundlagen" und "Fortgeschritten & mehr entdecken", jeweils mit einer Liste anklickbarer Themen-Links; als Admin enthält das zweite Kästchen zusätzlich die Links "Für Admins: Einstellungen & Benutzerverwaltung" und "Für Technik-Fans: Karten per KI-Agent verwalten". _(v3.5.0)_
[ ] Klick auf einen Themen-Link → der zugehörige Accordion-Abschnitt weiter unten öffnet sich automatisch (falls eingeklappt) und die Seite scrollt dorthin. _(v3.5.0)_
[ ] Klick auf einen Themen-Link zu einem bereits geöffneten Abschnitt → springt direkt dorthin, ohne den Abschnitt zu schliessen. _(v3.5.0)_
[ ] Die Hilfe-Abschnitte sind sichtbar in zwei Gruppen mit eigener Überschrift aufgeteilt: "Grundlagen" (Login & Person, Wortlisten erstellen & verwalten, Wörter hinzufügen, Aufbau einer Lernkarte, Leitner-Modus, Drill-Modus — 6 Abschnitte) und "Fortgeschritten & mehr entdecken" (Aussprache: Audio & Lautschrift, Karten gezielt für Drill vormerken, Listen migrieren & organisieren, CSV-Import & KI-Prompt im Detail, Statistik & Heatmap im Detail — 5 Abschnitte, als Admin zusätzlich 2 weitere). _(v3.5.0)_
[ ] Als Nicht-Admin: 11 Abschnitte sichtbar; als Admin: 13 Abschnitte. _(v3.5.0)_
[ ] Neuer Abschnitt "Karten gezielt für Drill vormerken" (Fortgeschritten) bündelt die komplette Erklärung (Umschaltbar über Kartenübersicht und laufende Leitner-Session, Priorität aus den Einstellungen, automatisches Entfernen der Vormerkung, archivierte Karten ausgeschlossen) inkl. neuem Hinweis: eine vorgemerkte Karte wird bei "musste nachdenken" NICHT als "zu schwer für heute" pausiert, anders als eine normale Karte. _(v3.5.0)_
[ ] Neuer Abschnitt "Listen migrieren & organisieren" (Fortgeschritten) erklärt neben dem bereits bekannten Migrieren (inkl. Hinweis: keine Duplikat-Prüfung dabei, Button nur bei ≥2 eigenen Listen sichtbar) auch das Aktiv-/Inaktiv-Setzen einer Liste — mit korrektem Hinweis, dass der Umschalt-Button dafür auf der Startseite sitzt, nicht unter "Meine Listen". _(v3.5.0)_
[ ] Neuer Abschnitt "CSV-Import & KI-Prompt im Detail" (Fortgeschritten) erklärt zusätzlich zum CSV-Format: dass der KI-Prompt ohne hinterlegten Aussprache-Dialekt die KI anweist, nachzufragen; dass bei Englisch ohne Dialekt-Angabe britisches Englisch der Standard ist; sowie die drei Optionen beim erneuten Import einer bereits archivierten Karte (archiviert lassen / reaktivieren / als neue Karte importieren). _(v3.5.0)_
[ ] Auf schmalem Viewport (iPhone, 430 px): Schnellstart-Kachel bricht von 4 auf 2 Spalten um, die beiden Sprungmarken-Kästchen stehen untereinander statt nebeneinander — keine Seite lässt sich horizontal wegschieben. _(v3.5.0)_
[ ] Browser-Konsole zeigt beim Laden der Seite sowie beim Klicken der Sprungmarken keine JavaScript-Fehler. _(v3.5.0)_

---

## 33. Bugfix: MCP `add_cards` legte keinen `card_progress`-Eintrag an _(v3.5.1)_

[ ] Über den MCP-Agenten (`add_cards`) eine neue Karte in eine Liste einfügen → auf `home.php` erscheint die Liste sofort mit "⏳ 1 in Warteschlange" (nicht "Keine in Warteschlange"). _(v3.5.1)_
[ ] Dieselbe Karte auf `edit.php` → Status-Badge "Warteschlange", Filter "Warteschlange" zeigt die Karte. _(v3.5.1)_
[ ] Eine Leitner-Session für diese Liste starten (Tageslimit noch nicht ausgeschöpft) → die per MCP eingefügte Karte wird als eine der täglich aktivierten Karten berücksichtigt (`leitner_box = 1`, `next_due_date = heute`). _(v3.5.1)_
[ ] Bereits vor diesem Fix per MCP eingefügte Bestandskarten (fehlender `card_progress`-Eintrag): nach dem Deploy einmal eine beliebige Seite aufrufen (löst die Migration aus) → Karten erscheinen danach korrekt in der Warteschlange auf `home.php`. _(v3.5.1)_

---

## 34. Bugfix: Vormerken direkt in der Drill-Session _(v3.6.0)_

[ ] Laufende Drill-Session: oben links auf der Karte erscheint ein runder, **klickbarer** Pin-Button (nicht nur ein Symbol wie vorher). _(v3.6.0)_
[ ] Karte ist bereits vorgemerkt (z.B. vorher über `edit.php` gesetzt) → Klick auf den Pin-Button entfernt die Vormerkung (Icon wird zum Umriss), dieselbe Karte bleibt weiterhin angezeigt, die laufende Session (Timer, verbleibende Karten, Zähler) läuft unverändert weiter. _(v3.6.0 — der eigentliche gemeldete Bug)_
[ ] Karte ist noch nicht vorgemerkt → Klick setzt die Vormerkung (Icon wird ausgefüllt); die Karte erscheint ab sofort priorisiert gemäss der konfigurierten Vormerkungs-Priorität, ohne dass die laufende Session unterbrochen wird. _(v3.6.0)_
[ ] **Karte antippen, sodass die Übersetzung/Lösung aufgedeckt ist, dann den Pin-Button klicken** → die Kartenanzeige ändert sich dabei NICHT, die Lösung bleibt sichtbar (kein Zurückspringen auf die zugeklappte Vorderseite) — analog zum bereits bestehenden Verhalten in der Leitner-Session. Gilt in beide Richtungen (Vormerken wie Entfernen). _(v3.6.0)_
[ ] Pin-Button-Klick bei zugeklappter Karte (Lösung noch nicht aufgedeckt) → Karte bleibt weiterhin zugeklappt. _(v3.6.0)_
[ ] Klick auf den Pin-Button klappt die Karte NICHT um (kein versehentliches Aufdecken der Antwort). _(v3.6.0)_
[ ] Das Vormerken/Entfernen zählt **nicht** als Antwort — "Gewusst"/"Musste nachdenken"-Zähler sowie die verbleibende Zeit bleiben unverändert. _(v3.6.0)_
[ ] Manuell gesendeter POST mit `card_id` einer NICHT aktuell angezeigten Drill-Karte → wird ignoriert, kein `card_progress`-Eintrag entsteht/ändert sich. _(v3.6.0)_
[ ] `edit.php`-Kartenansicht, Leitner-Session und Drill-Session zeigen für dieselbe Karte konsistent denselben Vormerkungs-Status. _(v3.6.0)_
[ ] In der Drill-Session eine bisher unvorgemerkte, gerade angezeigte Karte vormerken, danach die Session bis zum Ende durchspielen → die frisch vorgemerkte Karte erscheint in dieser Session priorisiert (nicht nur ein einziges Mal), analog zu einer schon vor Sessionstart vorgemerkten Karte. _(v3.6.0)_
[ ] Vormerkung einer aktuell im Drill gezeigten Karte über einen zweiten Tab (`edit.php`) entfernen, danach im ersten Tab (laufende Drill-Session) dieselbe Karte per Pin-Button erneut vormerken wollen → funktioniert wie ein normales Neu-Vormerken, kein inkonsistenter Zustand. _(v3.6.0)_

## 35. Lautsprecher-Fix für Aussprache auf iOS/Android _(v3.6.0)_

[ ] iPhone (Safari, Klingelton-Schalter auf lautlos ODER normal): 🔊-Button auf einer Leitner-Karte antippen → Aussprache ist über den Lautsprecher hörbar, nicht nur über angeschlossene Kopfhörer/AirPods. _(v3.6.0)_
[ ] Dasselbe auf einer Drill-Karte. _(v3.6.0)_
[ ] Android-Gerät: 🔊-Button auf Leitner- und Drill-Karte → Wiedergabe ebenfalls über den Lautsprecher. _(v3.6.0)_
[ ] Erster 🔊-Klick in einer neuen Session löst keinen hörbaren zusätzlichen Ton/Knacken aus (das stumme Unlock-Audio ist wirklich lautlos). _(v3.6.0)_
[ ] Mehrfaches Antippen des 🔊-Buttons hintereinander (verschiedene Karten) → jede Aussprache ist weiterhin normal hörbar, kein Aussetzer nach dem ersten Klick. _(v3.6.0)_
[ ] Desktop-Browser (Chrome/Safari/Firefox): 🔊-Button funktioniert weiterhin wie bisher, keine erkennbare Verhaltensänderung. _(v3.6.0)_
[ ] Hörprobe-Button auf der Hilfeseite (`help.php`, Abschnitt "Aussprache: Audio & Lautschrift") bleibt unverändert vom Fix unberührt (dort weiterhin nur die bisherige Logik, kein Unlock-Trick). _(v3.6.0)_
[ ] Browser-Konsole zeigt beim Antippen des 🔊-Buttons auf iOS/Android keine JavaScript-Fehler. _(v3.6.0)_

## 36. Globale Statistik über alle Listen _(v3.6.0)_

[ ] `stats.php` ohne `list_id` aufrufen (z.B. über den "Statistik"-Button auf der Startseite oder das 🔥-Badge in der Navbar) → kein Redirect mehr auf die erste eigene Liste, stattdessen direkt die aggregierte Ansicht über alle eigenen Listen. _(v3.6.0)_
[ ] Globale Ansicht: Leitner-Übersicht (Karten pro Fach, Warteschlange, Archiviert, Richtig/Falsch-Quote) summiert korrekt über alle eigenen Listen, nicht nur eine. _(v3.6.0)_
[ ] Globale Ansicht: Drill-Übersicht (gemeisterte Karten, Gewusst-Quote) summiert ebenfalls korrekt über alle eigenen Listen. _(v3.6.0)_
[ ] Filter-Buttons oberhalb der Übersicht: zusätzlicher Button "Alle Listen" ganz links, hervorgehoben (aktiv) wenn keine `list_id` gesetzt ist. _(v3.6.0)_
[ ] Klick auf einen einzelnen Listen-Button → wie bisher listenspezifische Statistik, "Alle Listen" ist dabei nicht mehr hervorgehoben. _(v3.6.0)_
[ ] Klick auf "Alle Listen" nach vorheriger Listenauswahl → zurück zur globalen Ansicht, URL ohne `list_id`. _(v3.6.0)_
[ ] Lernaktivität (Streak, Lerntage, Beste Woche, Heatmap) zeigt in der globalen Ansicht dieselben Werte wie zuvor bei einer einzelnen Liste — war schon vorher immer global, unabhängig vom Filter, keine Verhaltensänderung dort. _(v3.6.0)_
[ ] Person ohne eigene Listen → `stats.php` zeigt die globale Ansicht mit leeren/neutralen Werten (kein Fehler, kein Redirect-Loop). _(v3.6.0)_
[ ] Person mit genau einer eigenen Liste → globale Ansicht und Einzellisten-Ansicht zeigen identische Zahlen (da inhaltlich dieselbe Menge Karten). _(v3.6.0)_

---

## 37. Bugfix: aktiver Drill-Pool an Session-Länge gekoppelt _(v3.7.0)_

[ ] Grosse Liste (deutlich mehr Karten als "Aktive Karten pro Minute" × Timer-Minuten ergibt, z.B. 50+ Karten bei Standardwerten und einer kurzen Session), noch nie gedrillt → kurze Drill-Session (z.B. 5–7 Min.) durchspielen, dabei konsequent richtig antworten → mindestens eine Karte wird "gemeistert" (vorher: praktisch nie bei grossen Listen). _(v3.7.0 — der eigentliche gemeldete Bug)_
[ ] Dieselbe grosse Liste, deutlich längerer Timer (z.B. 30–60 Min.) → spürbar mehr unterschiedliche Karten kommen im Verlauf der Session dran als bei der kurzen Session (aktiver Pool wächst mit den Minuten). _(v3.7.0)_
[ ] Kleine Liste (weniger Karten als der berechnete aktive Pool) → Verhalten unverändert zu vorher, alle eligiblen Karten sind wie gewohnt sofort im Umlauf, keine merkliche Einschränkung. _(v3.7.0)_
[ ] Einstellungen → Drill-Modus: "Aktive Karten pro Minute" ist änderbar (Dezimalwert, z.B. 0.5 oder 2.5), wirkt sich unmittelbar auf die nächste Drill-Session aus. _(v3.7.0)_
[ ] Wert ausserhalb 0.2–10 eingeben (z.B. 0 oder 15) → Fehlermeldung, Einstellungen werden nicht gespeichert (auch die anderen geänderten Felder im selben Formular nicht). _(v3.7.0)_
[ ] Wert mit Komma statt Punkt eingeben (z.B. "1,5", je nach Spracheinstellung des Browsers beim `number`-Feld möglich) → wird korrekt als 1.5 interpretiert und gespeichert, keine Fehlermeldung. _(v3.7.0)_
[ ] Vorgemerkte Karten ("für Drill vorgemerkt") sind von der Pool-Begrenzung ausgenommen — bei einer grossen Liste mit mehreren vorgemerkten Karten erscheinen weiterhin alle vorgemerkten Karten priorisiert, unabhängig vom eingestellten "Aktive Karten pro Minute"-Wert. _(v3.7.0)_
[ ] Sehr kurze Session (z.B. 1 Min.) bei niedrigem "Aktive Karten pro Minute"-Wert (z.B. 0.2) → aktiver Pool ist trotzdem nicht kleiner als 5 Karten (`DRILL_MIN_ACTIVE_CARDS`), sofern die Liste mindestens so viele Karten hat. _(v3.7.0)_
[ ] Nach einer Session mit überzähligen, nicht berücksichtigten Karten (grosse Liste, Pool begrenzt) erneut eine Session mit derselben Liste starten → vorher nicht berücksichtigte Karten sind diesmal (ggf. neben anderen) wieder wählbar, keine Karte geht dauerhaft verloren. _(v3.7.0)_
[ ] `help.php`, Abschnitt "Drill-Modus": Beschreibung "wenige Karten gleichzeitig im Umlauf" stimmt jetzt tatsächlich mit dem Verhalten überein (war zuvor bei grossen Listen bereits so beschrieben, aber nicht durchgesetzt). _(v3.7.0)_

---
