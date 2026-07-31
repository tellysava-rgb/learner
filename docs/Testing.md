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

---
