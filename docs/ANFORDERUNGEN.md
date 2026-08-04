# Vokabeltrainer — Anforderungen

## Technologie

- **PHP** + **MySQL**
- **Bootstrap** für responsives Design (Desktop + iPhone)

### Mobile Darstellung _(überprüft und optimiert v3.2.25)_

Referenzgerät: iPhone 15 Pro Max (430 px Breite), gegengeprüft bei 375 px. Anspruch: **keine Seite darf horizontal scrollen** — geprüft über die tatsächliche `scrollWidth` im 430-px-Viewport, nicht nur nach Augenmass. Umgesetzte Anpassungen:

- **Navbar:** Die Icon-Leiste darf umbrechen (`flex-wrap`), der Personenname wird unter 576 px ausgeblendet — sonst schoben Marke + Name + bis zu sechs Icons die gesamte Seite breiter als den Viewport
- **Kartenübersicht (`edit.php`):** Die vier Aktions-Icons pro Zeile brechen auf schmalen Screens in zwei Reihen um, statt aus der Tabelle zu laufen. Die Status-Spalte bleibt erhalten
- **Einstellungen:** Beschriftung steht auf schmalen Screens über dem Eingabefeld statt daneben (Klasse `settings-row`), Textfelder nutzen die volle Breite, Zahlenfelder bleiben schmal
- **Import:** Das CSV-Beispiel scrollt innerhalb seines eigenen Rahmens, statt die Seite zu verbreitern
- **Statistik:** siehe Heatmap-Abschnitt — auf dem Handy nur die letzten ~4 Monate
- Läuft auf einem Webserver, Deployment via Datei-Upload
- Kein Framework nötig
- **Zeitzone:** Europe/Zurich — gilt für alle Datumsberechnungen (Leitner, Streak, Drill-Reset)

## Sicherheit

- Passwort jeder Person wird als **Hash** gespeichert (kein Klartext in DB) — siehe Abschnitt "Zugang / Benutzerverwaltung" für das Login-Modell ab v3.0.0
- **Session-Timeout:** konfigurierbar 1–1440 Min. (Standard 30 Min.), siehe Einstellungsseite — Inaktivität führt zum automatischen Logout
- **Session-Lebensdauer serverseitig durchgesetzt** _(v2.7.13)_: `SESSION_TIMEOUT` steuert zusätzlich zum eigenen Inaktivitäts-Check auch `session.gc_maxlifetime` (PHP-Garbage-Collection) sowie die Cookie-Lebensdauer (`session_set_cookie_params`) — verhindert, dass PHPs Standard-Wert (`gc_maxlifetime` = 1440 Sekunden = 24 Min.) die Session serverseitig löscht, lange bevor der konfigurierte (ggf. viel längere) Timeout erreicht ist. Cookie zusätzlich mit `HttpOnly` und `SameSite=Lax`, `Secure` automatisch bei HTTPS.
- **Eigenes Session-Verzeichnis** _(v2.7.14)_: Sessions werden in `includes/sessions/` gespeichert statt im System-Standardpfad, geschützt durch eigene `.htaccess` (`Require all denied`, kein Direktzugriff via URL). Grund: viele Hoster (v.a. Debian/Ubuntu) räumen den System-Standardpfad per eigenem Cron-Job auf — basierend auf dem globalen `php.ini`-Wert, unabhängig von jedem `ini_set()` der App — und löschen Sessions dadurch oft schon nach ~24 Min., egal was `SESSION_TIMEOUT` sagt. Ausserhalb des System-Pfads greift dieser Cron nicht mehr. Da Debian/Ubuntu aus demselben Grund meist PHPs eigene Garbage-Collection global deaktivieren (`gc_probability=0`), wird sie für das eigene Verzeichnis explizit wieder aktiviert (`gc_probability=1`, `gc_divisor=100`), sonst würden alte Sessions dort nie gelöscht.
- **Logout-Funktion** auf jeder Seite verfügbar
- **CSRF-Schutz** für alle schreibenden Aktionen (Löschen, Import, Bearbeiten, Erstellen)
- **SQL-Injection-Schutz** via Prepared Statements — konsequent überall
- **Upload-Beschränkung:** max. 2MB, nur `.csv` Dateiendung erlaubt
- **Fehlerbehandlung:** DB-Verbindungsfehler zeigt benutzerfreundliche Meldung — kein PHP-Stacktrace sichtbar

### Härtung aus der Sicherheitsprüfung _(v3.2.23)_

- **Basis-URL statt Host-Header** (`APP_BASE_URL`, Einstellungen → Allgemein): Links in ausgehenden E-Mails (Passwort-Reset) sowie die Absenderdomain werden ausschliesslich aus der konfigurierten Basis-URL gebaut, nie aus `$_SERVER['HTTP_HOST']`. Grund: der Host-Header kommt vom Client und ist fälschbar — ohne diese Trennung könnte jemand einen Reset für eine fremde Adresse anfordern und dem Opfer eine echte Mail mit Link auf eine Angreifer-Domain zustellen (Token-Diebstahl). Ist die Basis-URL nicht gesetzt, wird auf dem Server **keine** Reset-Mail verschickt (Vermerk im PHP-Error-Log); der Fallback auf die aktuelle Adresse greift nur für lokale Clients (`REMOTE_ADDR` = 127.0.0.1/::1), damit lokales Testen ohne Konfiguration funktioniert. Bewusst **nicht** an `APP_ENV` gekoppelt, da dieses in `db.php` selbst aus `HTTP_HOST` abgeleitet wird
- **Rate-Limiting** (Tabelle `auth_attempts`): max. 10 Login-Fehlversuche pro IP in 15 Minuten, max. 5 „Passwort vergessen"-Anfragen pro IP in 60 Minuten. Bewusst **pro IP statt pro Konto** — eine Konto-Sperre liesse sich zum Aussperren fremder Personen missbrauchen. Ein erfolgreicher Login löscht die Fehlversuche der IP; alte Einträge werden gelegentlich aufgeräumt. Fehlt die Tabelle (z.B. direkt nach einem Deploy vor der Migration), wird nie blockiert
- **`install.php` abgesichert:** CSRF-geschützt und verweigert **jede** Aktion (auch „Tabellen erneut prüfen"), sobald mindestens eine Person existiert — auf jedem eingerichteten System ist die Seite damit funktionslos. Bleibt ohne Login erreichbar, weil bei frischer Datenbank noch niemand existiert, der sich einloggen könnte (nötig für die Ersteinrichtung auf Prod). Einen Localhost-Guard gibt es bewusst nicht, er wäre damit unvereinbar
- **Kein Direktzugriff auf Logdateien und `includes/`:** eigene `.htaccess` sperrt `*.log` im Web-Root (`mcp.log` enthält Kartentexte, `list_id` und `person_id`) sowie das ganze `includes/`-Verzeichnis (`Require all denied`) — dort lag der Schutz vorher allein darin, dass PHP die Dateien ausführt; bei ausgefallenem PHP-Handler wären `db-credentials.php`, `mcp-config.php` und `deploy-config.php` im Klartext ausgeliefert worden
- **Sicherheits-Header** (`.htaccess`, via `mod_headers`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`. Kein HSTS in der `.htaccess` — das gehört auf Hoster-/vHost-Ebene, sonst landet es auch auf der HTTP-Dev-Umgebung
- **Subresource Integrity (SRI)** für alle CDN-Einbindungen (Bootstrap CSS/JS, Bootstrap Icons): jede Einbindung trägt `integrity` + `crossorigin="anonymous"`, damit ein manipuliertes CDN-Auslieferung keinen fremden Code ausführen kann
- **E-Mail-Format serverseitig validiert** — auch im „Konto"-Modal (`change_own_email`), nicht nur in `users.php`: der Wert wird später als Empfänger an `mail()` übergeben
- **Redirect-Ziele auf die eigene Anwendung begrenzt** (`safe_redirect_target()` in `auth.php`, genutzt für die Rücksprung-URL der Navbar-Aktionen) — absolute und protokoll-relative Ziele sowie CR/LF werden verworfen, gleiche Absicherung wie in `learn.php`/`drill.php`
- **Kartenbesitz auch bei Fortschritts-Aktionen geprüft** (`edit.php`, Archivieren/Reaktivieren): die `card_id` muss zur geprüften Liste gehören, sonst liessen sich eigene `card_progress`-Einträge für fremde Karten anlegen
- **`stats.php`** akzeptiert nur eigene `list_id` — eine fremde/unbekannte ID leitet auf die erste eigene Liste um (statt in einen „alle Listen"-Modus zu fallen, den es nicht gibt)

---

## Zugang / Benutzerverwaltung _(Login-Modell überarbeitet v3.0.0)_

- **Kein globales Passwort mehr.** Jede Person hat ein eigenes Login: **Name + Passwort** (Name ist das bestehende eindeutige `persons.name`-Feld, kein separates Username-Feld)
- Login führt **direkt** auf die Startseite der jeweiligen Person — kein separater "Person auswählen"-Schritt mehr
- **Personenname muss eindeutig sein** — beim Anlegen (über `users.php`) wird geprüft ob der Name bereits existiert, sonst Fehlermeldung
- **Admin-Rolle** (`persons.is_admin`): nur Admins dürfen `settings.php`, `deploy.php` und `users.php` öffnen, sowie als andere Person agieren ("Person wechseln"). Der Admin-Status wird beim Login in die Session geladen — Änderungen wirken erst ab dem nächsten Login der betroffenen Person
- **Eigenes Passwort ändern**: jede Person kann ihr eigenes Passwort über ein Modal ("Konto") auf der Startseite ändern (aktuelles Passwort zur Bestätigung nötig, min. 8 Zeichen)
- **Optionale E-Mail-Adresse pro Person** _(v3.0.0)_: im selben "Konto"-Modal kann jede Person selbst eine E-Mail-Adresse hinterlegen/entfernen (eindeutig über alle Personen, sonst Fehlermeldung). Ausschliesslicher Zweck: **eigenständiges Zurücksetzen des Passworts** per E-Mail, falls es vergessen wurde — ohne hinterlegte E-Mail ist ein Reset nur über den Admin (`users.php`) möglich
- **Passwort vergessen** (`forgot-password.php`, `reset-password.php`) _(v3.0.0)_: Link auf der Login-Seite → E-Mail-Adresse eingeben → falls sie einer Person zugeordnet ist, wird ein Link mit Einmal-Token per E-Mail verschickt (60 Min. gültig, Versand über PHPs `mail()`). Antwort ist immer dieselbe generische Meldung, unabhängig davon ob die E-Mail existiert (verhindert Enumeration). Token wird nur gehasht in der DB gespeichert, ist einmalig verwendbar und wird nach erfolgreichem Reset gelöscht
  - **`mail()`-Aufruf RFC-konform** _(v3.2.3)_: Subject-Header mit Umlauten wird per `mb_encode_mimeheader()` (RFC 1342) kodiert — rohe Non-ASCII-Zeichen in Mail-Headern werden von manchen Hostern (bestätigt bei HostFactory) als Spam eingestuft und die Nachricht kommentarlos nicht zugestellt. Zusätzlich `-f`-Parameter (Return-Path/Envelope-Sender) gesetzt sowie `Content-Type: text/plain; charset=utf-8` ergänzt. Fehlschläge landen im PHP-Error-Log statt stillschweigend unterdrückt zu werden (`@mail()` entfernt)
- **Benutzerverwaltung** (`users.php`, nur Admin): Personen anlegen (Name + initiales Passwort + optionale E-Mail + optionales Admin-Flag), E-Mail-Adresse einer Person setzen/ändern, Passwort einer Person zurücksetzen (ohne deren altes Passwort zu kennen), Admin-Status umschalten — der letzte verbleibende Admin kann nicht entfernt werden (verhindert Aussperren)
- **"Person wechseln"** (nur Admin, in der zentralen Navbar auf jeder Seite _(v3.0.0)_): Dropdown aller Personen, um vorübergehend als eine andere Person zu agieren (z.B. für Support). Übernimmt dabei **exakt deren Berechtigungen** — als Nicht-Admin-Person agieren verbirgt "Einstellungen"/"Benutzerverwaltung" und sperrt diese Seiten genauso wie für die echte Person. Einzige Ausnahme: **das Recht, die Person erneut zu wechseln bleibt erhalten**, damit man sich nicht selbst aussperrt — dafür merkt sich die Session getrennt, wer *wirklich* Admin ist (`real_is_admin`), unabhängig von den gerade angezeigten Berechtigungen (`is_admin`) _(v3.0.0)_
- Jede Person hat **eigene Listen** und **eigenen Lernfortschritt** (Fortschritt ist nicht öffentlich)

---

## Navigation

- Jede Seite zeigt eine **Breadcrumb-Navigation** in einem eigenen Container direkt unterhalb der Navbar — Position ist auf allen Seiten identisch, unabhängig von der Inhaltsbreite
- Breadcrumb zeigt immer den vollständigen Pfad zur aktuellen Seite, z.B.:
  - `Startseite > Meine Listen > Spanisch > Importieren`
  - `Startseite > Leitner`
  - `Startseite > Statistik`
- Das letzte Element (aktuelle Seite) ist nicht anklickbar — alle übergeordneten Stufen sind Links
- Breadcrumbs **ersetzen** die Zurück-Buttons — es gibt keine separaten Zurück-Buttons mehr
- Startseite (home.php) zeigt Breadcrumb mit nur `Startseite` (nicht verlinkt)
- Leitner und Drill: Breadcrumb zeigt immer `Startseite > Leitner` bzw. `Startseite > Drill` — unabhängig von Phase (Setup, aktive Session, Zusammenfassung)
- **Session-Verlassen-Warnung:** Während einer aktiven Leitner- oder Drill-Session (Karte wird angezeigt) löst jeder Link-Klick einen Bestätigungsdialog aus: "Achtung: die laufende Session wird dadurch automatisch beendet" — mit "Verlassen" und "Abbrechen"
- **Session-Abbruch:** Bei Bestätigung wird die Session server-seitig beendet (`$_SESSION['drill']` bzw. `$_SESSION['learn']` wird gelöscht) bevor zur Zielseite navigiert wird — verhindert Geisterzustände im Hintergrund
- **Streak-Badge in Navbar:** Das 🔥-Badge mit Streak-Anzahl wird auf allen Seiten angezeigt (via Session-Cache, einmal täglich berechnet auf home.php). Verschwindet wenn heute und gestern kein Lerntag war. Klickbar — führt zu `stats.php` (Lernaktivität/Heatmap) _(v3.2.15)_
- **Container-Breite:** `lists.php` nutzt dieselbe (Bootstrap-Standard-)Container-Breite wie die Startseite `home.php` — kein eigenes `max-width` mehr _(v2.1.0)_

### Zentrale Navbar _(v3.0.0)_

Die Navbar wird über eine einzige Funktion `render_navbar($pdo)` in `includes/auth.php` gerendert und auf jeder Seite mit Person-Kontext aufgerufen (`<?php render_navbar($pdo); ?>`) — Icons, Reihenfolge und Verhalten müssen dadurch nur an einer Stelle gepflegt werden, nicht auf jeder Seite einzeln. Zugehörige Aktionen (Logout, eigenes Konto, Person wechseln) laufen ebenfalls über eine gemeinsame Funktion `handle_navbar_actions($pdo)`, die jede Seite direkt nach `csrf_validate()` aufruft.

Reihenfolge der Elemente (rechtsbündig, in dieser Reihenfolge):
0. **Session abbrechen** _(nur während einer laufenden Leitner-Session)_ — Icon `bi-x-lg`, bewusst an **erster Stelle**, weil der Abbruch dann die wichtigste Aktion ist und nicht zwischen den übrigen Icons gesucht werden soll. Ersetzt in diesem Zustand den Logout-Button _(Icon statt Text-Button, Position vorgezogen: v3.2.26)_
1. **Streak-Badge** (🔥 N Tage)
2. **Personenname**
3. **Passwort ändern** — Icon `bi-key`, öffnet das "Konto"-Modal (eigenes Passwort + eigene E-Mail-Adresse)
4. **Person wechseln** _(nur Admin, nur wenn mehr als eine Person existiert)_ — Icon `bi-person-lines-fill` als Dropdown-Toggle, Auswahlliste aller Personen bleibt wie gehabt per Klick erreichbar
5. **Benutzerverwaltung** _(nur Admin)_ — Icon `bi-person-gear`, führt zu `users.php`
6. **Einstellungen** _(nur Admin)_ — Icon `bi-gear`, führt zu `settings.php`
7. **Logout** — Icon `bi-box-arrow-right` (ersetzt den bisherigen Text-Button)
8. **Hilfe** — Icon `bi-info-lg`, führt zu `help.php` _(v2.8.0, unverändert)_

`learn.php` nutzt während einer Session dieselbe zentrale Navbar, nur mit gesetztem `$abort_url` (dadurch erscheint das Abbruch-Icon an erster Stelle statt des Logouts). `drill.php` rendert während einer laufenden Session weiterhin eine eigene, abweichende Navbar, weil dort zusätzlich Timer und "gemeistert"-Zähler angezeigt werden — das Abbruch-Icon steht dort aus Konsistenzgründen ebenfalls an erster Stelle, vor Timer und Zähler _(v3.2.26)_.

---

## Startseite

- Zeigt alle **eigenen Listen** der aktuellen Person (selbst erstellt oder kopiert)
- Öffentliche Listen anderer Personen sind über den Bereich "Entdecken" auf der Startseite zugänglich
- **Zuletzt verwendete Liste** wird automatisch vorgeschlagen (in DB gespeichert → browserübergreifend)
- Wahl des **Lernmodus** pro Session: Leitner oder Drill
  - **Pro Liste** (Button auf der Listen-Karte): sowohl Leitner als auch Drill zeigen vor dem Start eine kurze Konfigurationsseite mit der jeweils bereits vorausgewählten Liste (Leitner: Richtung + Kartenanzahl; Drill: Richtung + Timer, siehe Abschnitt "Drill-Modus") _(Drill zeigt diese Seite seit v3.3.5 — vorher startete Drill hier ohne Zwischenschritt sofort)_
  - **Listenübergreifend** (Buttons "Leitner"/"Drill" oben neben "Meine Listen", ohne Liste vorausgewählt) _(v3.2.46)_: dieselbe Konfigurationsseite, zusätzlich mit Checkbox-Auswahl aller eigenen aktiven Listen (Mehrfachauswahl möglich, erste Liste vorausgewählt)
- Navigation zur Startseite jederzeit über die Breadcrumb-Navigation möglich
- Pro Liste zusätzlich zur Warteschlangen-Anzahl (⏳) eine Anzeige **"📚 N heute fällig"** _(v2.7.0)_ — Anzahl aktiver Leitner-Karten mit `next_due_date <= heute`
- Beide Zeilen sind **immer sichtbar**, auch bei 0 _(v2.7.2)_: "⏳ Keine in Warteschlange" bzw. "✅ Keine heute fällig" (Häkchen-Icon statt 📚, sobald für heute nichts mehr ansteht — bewusste positive Rückmeldung statt einfach nichts anzuzeigen)
- Pro Liste drei Icon-Buttons oben rechts neben dem Namen _(v2.7.0, ergänzt v2.7.6)_: **Liste bearbeiten** (Stift, führt zu `lists.php?edit=X` — Name/Sprachen/Einstellungen der Liste selbst), **Karten bearbeiten** (Stift-im-Quadrat `bi-pencil-square`, führt zu `edit.php` — bewusst anderes Icon als "Liste bearbeiten" (`bi-pencil`), da mehrere Einträge statt einer einzelnen Eigenschaft bearbeitet werden) _(Icon final v2.7.7)_ und **Statistik** (Balkendiagramm, führt direkt zu `stats.php?list_id=X` — die exakte Statistik dieser Liste, nicht die allgemeine Übersicht). Leitner/Drill bleiben als grosse Buttons im Footer.
- **Listen-Status Aktiv/Inaktiv** (`lists.is_active`) _(v3.3.0)_: Jede Liste hat einen Status, standardmässig aktiv. Aktive Listen werden wie gewohnt angezeigt (Warteschlange, "heute fällig", Leitner-/Drill-Buttons). Inaktive Listen erscheinen in einem eigenen, kompakteren Bereich "Inaktive Listen" unterhalb der aktiven Listen — ohne Warteschlangen-/Fällig-Anzeige und ohne Leitner-/Drill-Buttons. Umschalt-Button unten rechts auf jeder Listen-Karte (aktiv wie inaktiv): `bi-check-circle-fill` ("Inaktiv setzen") bei aktiven Listen, `bi-circle text-secondary` ("Aktiv setzen") bei inaktiven Listen. Betrifft nur Anzeige/Sichtbarkeit — Leitner-Fortschritt und Warteschlangen-Mechanik einer inaktiven Liste laufen im Hintergrund unverändert weiter.
- **MCP-Server und Listen-Status** _(v3.3.0)_: `list_lists` gibt standardmässig nur aktive Listen zurück und erwähnt inaktive nicht proaktiv. Nennt der User eine Liste explizit beim Namen, ruft der Agent `list_lists` mit `include_inactive=true` erneut auf, um auch inaktive Listen zu finden — Karten dürfen weiterhin gezielt in eine benannte inaktive Liste eingefügt werden (`add_cards` prüft den Status nicht).
- **Listenauswahl in der Leitner-Session** (`learn.php`) _(v3.2.45)_: Die Checkbox-Auswahl beim Starten einer Session zeigt nur aktive Listen (`is_active = 1`) — analog zur Statistik-Auswahl (siehe oben) und zu den Leitner-/Drill-Buttons auf der Startseite.
- **Button-Reihe oben neben der Überschrift "Meine Listen"** _(v3.2.46, Icons ergänzt v3.2.48)_: **Leitner** und **Drill** (starten listenübergreifend mit Checkbox-Auswahl, siehe oben), **Meine Listen** (Icon `bi-pencil` + Text, führt zu `lists.php`) und **Statistik** (Icon `bi-bar-chart-line` + Text, führt zu `stats.php`, allgemeine Übersicht aller Listen). Zeile bricht bei schmalem Viewport um (`flex-wrap`).
- **Konfigurationsseite in der Drill-Session** (`drill.php`) _(v3.2.46, Richtung + Timer ergänzt v3.3.5, Zufall-Option v3.3.11)_: Wird `drill.php` ohne laufende Session aufgerufen, erscheint immer die Konfigurationsseite — mit `list_id` in der URL (z.B. Startseite oder "Erneut starten") mit vorausgewählter Liste als Text, sonst mit Checkbox-Auswahl aller eigenen aktiven Listen (erste vorausgewählt). Zusätzlich wählbar: Lernrichtung (A→B, B→A, Gemischt oder Zufall — Zufall voreingestellt, siehe Abschnitt "Lernrichtung: Zufall-Option") und Timer in Minuten für diese eine Session (Standard aus den Einstellungen, nicht dauerhaft gespeichert). Fehlerfälle (keine Liste ausgewählt, keine gültige Liste, keine geeigneten Karten) führen zurück auf diese Seite statt auf die Startseite, mit Fehlermeldung über der Auswahl.

---

## Wortlisten

### Verwaltung
- Listen **erstellen**, **umbenennen**, **löschen**
- Listen **exportieren** (CSV)
- Listen **importieren** (CSV)
- Downloadbare **CSV-Vorlage** mit Vokabel-Beispiel (nur Karten, kein Metadaten-Header)
- **Besitzer:** Die Person die eine Liste erstellt ist automatisch Besitzer — keine Übertragung möglich
- **Nur der Besitzer** kann seine Liste bearbeiten, umbenennen, löschen, importieren und exportieren
- **Privat-Flag** pro Liste — private Listen sind für andere Personen nicht sichtbar
- **Bearbeiten-Formular klar von "Neue Liste erstellen" abgegrenzt** _(v3.3.14)_: Klick auf "Liste bearbeiten" (`lists.php?edit=X`) öffnet ein Inline-Formular direkt beim jeweiligen Listeneintrag. Die Karte "Neue Liste erstellen" oben wird währenddessen ausgeblendet, da sie dieselben Felder zeigt und sonst mit dem Bearbeiten-Formular verwechselt werden kann. Der bearbeitete Eintrag ist zusätzlich farblich hervorgehoben (blauer Rahmen) und trägt die Überschrift "Liste bearbeiten: <Name>"; die Seite scrollt beim Laden automatisch zu diesem Eintrag und fokussiert das Namensfeld — wichtig bei vielen Listen, wenn der bearbeitete Eintrag weit unten steht.
- **Aktionsleiste pro Liste, sechs Buttons mit Icon + Text** _(Icons/Reihenfolge v3.3.15)_, in dieser Reihenfolge: **Liste bearbeiten** (`bi-pencil`, → `lists.php?edit=X`), **Karten bearbeiten** (`bi-pencil-square`, → `edit.php?list_id=X` — bewusst anderes Icon als "Liste bearbeiten", da mehrere Einträge statt einer einzelnen Eigenschaft bearbeitet werden), **Import** (`bi-upload`, → `import.php?list_id=X`), **Migrieren** (`bi-box-arrow-right`, öffnet das Migrations-Modal, ausgeblendet wenn keine weitere eigene Liste existiert), **Export** (`bi-download`, → `export.php?list_id=X`), **Löschen** (`bi-trash`, öffnet die Lösch-Bestätigung). Zeile bricht bei schmalem Viewport um (`flex-wrap`).

### Liste migrieren _(v2.1.0)_
- Auf **Meine Listen** steht pro Liste ein Button **"Migrieren"** zwischen "Import" und "Export" (siehe Aktionsleiste oben) — ausgeblendet wenn keine weitere eigene Liste existiert
- Öffnet ein Auswahlfenster: Zielliste wählen (nur eigene Listen, die Quellliste selbst ist ausgeschlossen)
- Alle Karten der Quellliste werden per `list_id`-Änderung in die Zielliste verschoben — der komplette Lernfortschritt pro Karte (`card_progress`: Leitner-Fach, `next_due_date`, Drill-Mastery, `drill_too_hard`) bleibt erhalten, da er an `card_id` hängt, nicht an `list_id`
- **Sprachpaar-Mismatch:** Unterscheiden sich die Sprachpaare von Quelle und Ziel (z.B. Deutsch→Englisch vs. Deutsch→Französisch), erscheint eine Warnung die der User bestätigen muss, bevor migriert wird
- **Duplikate in der Zielliste** (gleiche Wörter bereits vorhanden) werden nicht geprüft — beide Einträge bleiben nebeneinander bestehen
- Migration ist nur zwischen **eigenen** Listen derselben Person möglich — keine Vermischung mit Listen anderer Personen
- Die Quellliste bleibt nach der Migration **leer bestehen** — der User löscht sie bei Bedarf manuell

### Metadaten pro Liste
| Feld | Pflicht |
|---|---|
| Name | ✅ |
| Beschreibung (z.B. "Französisch Vokabeln Klasse 2a") | optional |
| Sprache A | ✅ |
| Sprache B | ✅ |
| Öffentlich / Privat | ✅ |
| Aussprache-Sprachcode (Sprache B) | optional |

### Aussprache (Audio) _(v2.2.0)_
- Pro Liste kann ein **Sprachcode für die Aussprache** hinterlegt werden — ausschliesslich für **Sprache B** (die Fremdsprache), nicht für Sprache A
- Format: **BCP-47** (Sprache-Region, z.B. `en-GB`, `de-CH`, `fr-FR`) — reine Sprachcodes ohne Region (z.B. `en`) sind nicht zulässig
- Eingabe im "Liste erstellen"/"Bearbeiten"-Formular: Textfeld mit Autovervollständigung (HTML `<datalist>`) — Vorschläge aus einer kuratierten Liste gängiger Codes **plus** allen bereits in anderen Listen verwendeten Codes; eigene Werte sind trotzdem frei eintippbar
- **Validierung beim Speichern:** Sprachteil gegen ISO-639-1, Regionsteil gegen ISO-3166-1 geprüft (z.B. `en-UK` wird abgelehnt, da "UK" kein gültiger ISO-3166-1-Code ist — korrekt ist `en-GB`). Gross-/Kleinschreibung wird automatisch normalisiert (z.B. `EN-gb` → `en-GB`)
- Keine serverseitige Prüfung ob die Kombination Sprache+Region "sinnvoll" ist (z.B. `ja-DE` wäre technisch gültig, aber unüblich) — reine Formatprüfung
- **Wiedergabe:** Auf Leitner- und Drill-Karten erscheint ein 🔊-Button überall dort, wo der Begriff in Sprache B angezeigt wird (Frage- oder Antwortseite, je nach Lernrichtung) — nutzt die browsereigene **Web Speech API** (`speechSynthesis`), liest den vorhandenen Kartentext (Sprache B) mit dem hinterlegten Code vor
- Button erscheint **nur**, wenn die Liste einen Aussprache-Code hinterlegt hat — sonst kein Button
- Bestehende Listen ohne Code: Button bleibt einfach aus, bis der Besitzer den Code einmalig über "Bearbeiten" nachträgt
- Beim Kopieren einer öffentlichen Liste ("Entdecken") wird der Aussprache-Code der Quellliste automatisch mitkopiert
- Zusätzlich zur Audio-Wiedergabe: manuell erfassbares **Lautschrift-Feld pro Karte** (`phonetic_b`) — siehe Abschnitt "Lautschrift pro Karte" weiter unten

### Öffentliche Listen entdecken
- Startseite zeigt zwei Bereiche:
  - **Oben:** eigene Listen
  - **Unten:** öffentliche Listen anderer Personen (Name, Beschreibung, Besitzer, Sprachen, Anzahl Karten)
- Öffentliche Liste anklicken → öffnet `discover.php?list_id=X` mit **Vorschau** aller Karten (Sprache A + Sprache B). Vorhandene Lautschrift (`phonetic_b`) wird dabei bereits in der Vorschau angezeigt (in eckigen Klammern hinter dem Begriff, z.B. `cinquante-neuf [sɛ̃kˈɑ̃tnˈœf]`) — gleiche Darstellung wie in der Kartenübersicht (`edit.php`) _(v3.3.13)_
- Button **"Kopieren"** → Liste wird als eigene unabhängige Kopie übernommen
- `discover.php` ohne `list_id` → Weiterleitung zur Startseite (kein eigener Überblick)
- Alle kopierten Karten erhalten `status = queued` — Tageslimit gilt wie bei CSV-Import
- Pro Karte wird auch die Lautschrift (`phonetic_b`) mitkopiert, falls die Quellkarte eine hat
- Nach dem Kopieren erscheint sie normal in der eigenen Listen-Übersicht
- Änderungen des Besitzers an der Originalliste haben keinen Einfluss auf die Kopie
- Eigene Kopie kann in beiden Modi genutzt werden (Leitner + Drill)

### Felder pro Karte
| Feld | Pflicht |
|---|---|
| Sprache A | ✅ |
| Sprache B | ✅ |
| Beschreibung A | optional |
| Beschreibung B | optional |
| Lautschrift (Sprache B) | optional |

### Lautschrift pro Karte _(v2.3.0)_
- Zusätzliches Feld `phonetic_b` pro Karte — manuell erfasste Lautschrift für den Begriff in **Sprache B**
- Eingabefeld erscheint beim Hinzufügen/Bearbeiten einer Karte (`edit.php`) **nur**, wenn die Liste einen Aussprache-Sprachcode (`speech_lang_b`) hinterlegt hat — bei Listen ohne Sprachzuordnung (z.B. Mathe-Listen) gibt es kein Lautschrift-Feld
- Anzeige: in der Kartenübersicht (`edit.php`) unter dem Begriff in Sprache B, sowie auf Leitner- und Drill-Karten unter dem Begriff in Sprache B (zusammen mit dem 🔊-Button)
- Ergänzt die Audio-Wiedergabe, ersetzt sie nicht — beide Mechanismen sind unabhängig nutzbar
- **CSV-Import/-Export** unterstützen `phonetic_b` als optionale 5. Spalte _(v2.4.0)_ — siehe Abschnitt "CSV-Format"
- **MCP `add_cards`** unterstützt `phonetik_b` als optionales Feld, mit derselben vereinfachten Lautschrift-Konvention wie manuell erfasst (Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Zeichen) — nur befüllen wenn die Liste ein `speech_lang_b` hat, reine Agent-Anweisung ohne serverseitige Validierung _(v2.4.0)_

### Sprachen
- Frei definierbar pro Liste (z.B. Deutsch/Englisch, Deutsch/Japanisch)
- Lernrichtung wählbar pro Session: A→B, B→A, Gemischt oder Zufall (Details siehe "Lernrichtung: Zufall-Option" weiter unten)

### Lernrichtung: Zufall-Option _(v3.3.11)_
- Vierte Option auf der Konfigurationsseite (Leitner **und** Drill, identisches Verhalten in beiden Modi), am Ende der Liste: A→B, B→A, Gemischt, **Zufall**
- **Zufall ist der Default-Wert** — sowohl bei fehlender/ungültiger Auswahl (z.B. manipuliertes Formular) als auch beim erstmaligen Öffnen der Konfigurationsseite
- Wird "Zufall" gewählt, würfelt der Server **einmalig beim Start der Session** eine der drei echten Richtungen aus (A→B, B→A oder Gemischt, je 1/3 Wahrscheinlichkeit, `random_int()`) — die Session läuft danach durchgehend mit dieser einen Richtung, kein erneutes Auswürfeln pro Karte. "Zufall" selbst ist also kein eigener Anzeige-Modus, sondern wird vor dem eigentlichen Session-Start in einen der drei bestehenden Werte aufgelöst (`resolve_direction()` in `includes/auth.php`, gemeinsam genutzt von `learn.php` und `drill.php`)
- Ziel: verhindert einseitiges Lernen durch immer dieselbe, vom User (unbewusst) bevorzugte Richtung
- Radio-Buttons stehen untereinander (nicht mehr nebeneinander in einer Zeile) — Reihenfolge von oben nach unten: A→B, B→A, Gemischt, Zufall
- Die tatsächlich ausgewürfelte Richtung wird nirgends separat angezeigt (kein Debug- oder Info-Hinweis) — für den User macht sich das nur darin bemerkbar, welche Sprache auf der jeweiligen Karte oben bzw. unten erscheint

### Karten-Identität
- Jede Karte erhält beim Erstellen eine **stabile `card_id`** in der Datenbank
- Jede Person lernt nur mit **eigenen Karten** — entweder selbst erstellt oder als Kopie einer öffentlichen Liste
- Keine geteilten Karten zwischen Personen — kein Fortschrittsverlust durch fremde Änderungen

### Decks mischen
- Beim Sessionstart können **mehrere eigene Listen gleichzeitig** ausgewählt werden
- Lernfortschritt ist immer **persönlich** pro Person und pro `card_id`

### Bearbeitung im Browser
- Einzelne Einträge **hinzufügen**, **ändern**, **löschen**
- Einträge direkt als **archiviert** markieren (erscheinen nicht mehr im Training)
- Aktionsbuttons pro Karte als **Icon-only** (Bootstrap Icons) mit Tooltip: Ansehen (Auge), Bearbeiten (Stift), Archivieren (Archiv-Box), Reaktivieren (Pfeil zurück), Löschen (Mülleimer)
- **Direktlink pro Karte** _(v2.5.0, überarbeitet v2.5.2, Icon v2.5.3)_: erster Button in der Aktionsleiste (Augen-Symbol, Tooltip "Karte ansehen"), ein normaler Link (`edit.php?list_id=X&highlight=cardID`) — öffnet die Karte **als Lernkarte** in einem Modal (gleiche Flip-Kartenoptik wie Leitner/Drill: Vorderseite Sprache A, Tippen deckt Sprache B inkl. Lautschrift und 🔊-Button auf), nicht nur eine markierte Position in der Tabelle. Funktioniert unabhängig vom aktuell gewählten Filter, da die Karte direkt aus den geladenen Kartendaten der Liste gesucht wird.
- CSV Import / Export im Header: Icon + Text
- Container-Breite ohne eigenes `max-width` (analog `home.php`/`lists.php`) _(v2.3.0)_
- Beschreibungsfelder (A/B) als mehrzeilige `<textarea>` statt einzeiliger Inputs — Beschreibungen können ausführlich sein _(v2.3.0)_
- **Filter** über der Kartenliste: Status-Filter (Alle / Aktiv / Warteschlange / Archiviert) sowie zusätzlich, optisch getrennt, **Fach-Filter** (Fach 1–5) _(v2.7.8)_ — zeigt nur aktive Karten der gewählten Leitner-Box, jeweils mit Anzahl-Badge wie bei den Status-Filtern

### Duplikat-Prüfung beim Import
- Gilt **nur beim CSV-Import** — nicht beim Kopieren einer öffentlichen Liste
- Beim Kopieren werden immer neue Karten mit neuen IDs erstellt, keine Duplikat-Prüfung
- Beim Import wird auf Duplikate geprüft anhand von:
  - Normalisierter Text A (Kleinschreibung, Leerzeichen getrimmt, mehrfache Leerzeichen reduziert)
  - Normalisierter Text B (gleiche Normalisierung)
  - Prüfung ist **listenübergreifend, aber auf das Sprachenpaar begrenzt** _(v3.3.7)_ — nur eigene Karten aus Listen mit identischer Sprache A **und** Sprache B (exakter Textvergleich der Sprachbezeichnung) werden berücksichtigt. Eine Karte "casa"/"Haus" aus einer Italienisch-Liste zählt beim Import einer Französisch-Liste nicht als Duplikat, selbst wenn dieselbe deutsche Übersetzung existiert.
  - `colour` vs. `color` gilt als unterschiedlich — kein automatischer Ausgleich
  - `7 × 8 = ?` vs. `7x8=?` gilt als unterschiedlich — Verantwortung beim User
- Warnung zeigt Übersicht aller gefundenen Duplikate (in welcher Liste sie existieren)
- User entscheidet **einmal global:** alle überspringen oder alle importieren
- Optional: einzelne Karten aus der globalen Entscheidung herausnehmen
- Beim Import einer bereits `archived` Karte → **Warnung mit drei Optionen:**
  - **Archiviert lassen** — Karte bleibt archiviert, wird nicht importiert
  - **Reaktivieren** — `status = active`, `leitner_box = 1`
  - **Als neue Karte importieren** — separate Karte mit eigener ID, archivierte bleibt unberührt

### CSV-Format
```
a,b,desc_a,desc_b,phonetic_b
Diagnose,diagnosis,medizinischer Begriff,"A conclusion, reached by examination",dy-ug-NOH-sis
Behandlung,treatment,,,
```
- Trennzeichen: **Komma oder Semikolon** — App erkennt automatisch
- **Encoding: UTF-8**
- Erste Zeile ist die Kopfzeile (Sprachnamen oder beliebige Spaltenbezeichnungen) — wird beim Import immer übersprungen
- Felder mit Kommas/Semikolons müssen in **doppelte Anführungszeichen** gesetzt werden
- 5. Spalte `phonetic_b` (Lautschrift) ist **optional** — fehlt sie (nur 4 Spalten), bleibt das Feld leer; rückwärtskompatibel mit alten CSV-Dateien _(v2.4.0)_
- Kommas/Semikolons innerhalb von Feldern sind nur erlaubt wenn das Feld korrekt gequotet ist
- Kein Listenname und keine Sprachen in der CSV — die Liste wird vorher in der App erstellt
- Import-Seite enthält ausführliche Erklärung und Beispiel
- **Prompt für KI-generierte Wortlisten** _(v2.7.9, Dialekt-Standard ergänzt v2.7.11, Lautschrift-Rückfrage v2.7.12)_: `import.php` zeigt im CSV-Format-Bereich einen fertigen, in ein Textfeld kopierbaren Prompt (Button "Prompt kopieren"), den man einer KI (Claude, ChatGPT etc.) geben kann, um eine passende CSV-Wortliste erzeugen zu lassen. Wird dynamisch pro Liste generiert: Sprachnamen (`language_a`/`language_b`) exakt wie in der CSV-Kopfzeile, sowie eine Lautschrift-Anweisung passend zum hinterlegten `speech_lang_b` der Liste — inkl. der nicht-rhotischen Aussprache-Konvention bei `en-GB`/`en-AU`/`en-NZ`/`en-ZA` (siehe MCP-Server-Instruktionen), rhotischer Hinweis bei anderen Dialekten. **Hat die Liste kein `speech_lang_b`** _(v2.7.12)_: der Prompt weist die KI an, den User explizit nach dem gewünschten Aussprache-Dialekt zu fragen (ausser er ist bereits beim Thema-Platzhalter angegeben) und die Lautschrift danach passend auszufüllen — nicht mehr einfach leer zu lassen. Platzhalter `[Thema einfügen]`/`[Anzahl]` müssen vor dem Absenden an die KI manuell ersetzt werden. **Ist Sprache B Englisch** _(v2.7.11)_: Prompt enthält zusätzlich eine Dialekt-Regel für Schreibweise/Wortwahl — hat die Liste ein `speech_lang_b`, gilt dessen Dialekt (Vorrang vor allem anderen); ohne `speech_lang_b` gilt als Standard **britisches Englisch (en-GB)**, ausser beim Thema-Platzhalter wird ausdrücklich ein anderer Dialekt verlangt. Verhindert das wiederkehrende Problem, dass KIs standardmässig US-Begriffe statt der gewünschten britischen Begriffe liefern. Diese Regel betrifft nur Schreibweise/Wortwahl, nicht die Lautschrift-Rückfrage oben. **MCP-Server bleibt unverändert** _(v2.7.12)_: `phonetik_b` wird dort weiterhin nur ausgefüllt, wenn die Liste ein `speech_lang_b` hat — keine Rückfrage-Logik im MCP, da der Agent die Karten dort ohnehin vor dem Einfügen zur Bestätigung vorlegt.

---

## Karten-Status

Jede Karte hat pro Person zwei separate Felder in der Datenbank:

### Status-Feld (`status`)
| Wert | Bedeutung |
|---|---|
| `queued` | Importiert, noch nicht aktiv — wartet in der Warteschlange |
| `active` | Aktiv im Leitner-System |
| `archived` | Gelernt — erscheint in keinem Modus mehr |

### Leitner-Feld (`leitner_box`)
| Wert | Bedeutung |
|---|---|
| 1–5 | Aktuelles Fach (nur relevant wenn `status = active`) |
| — | Leer wenn `queued` oder `archived` |

### Zusätzliche Felder pro Karte/Person
```
card_progress Tabelle:
- person_id
- card_id
- status            → 'queued', 'active', 'archived'
- leitner_box       → 1-5 (nur wenn status = 'active')
- next_due_date     → Datum der nächsten Fälligkeit (nur wenn status = 'active')
- drill_mastery     → 0-3 (Anzahl gemeisterter Drill-Sessions)
- drill_too_hard    → boolean, wird auf true gesetzt nach 5× "Noch nicht gewusst" in einer Session
                      wird zurückgesetzt zu false beim ersten Zugriff eines neuen Kalendertags (Zeitzone: Europe/Zurich)
- drill_pinned_correct → NULL = nicht "für Drill vorgemerkt", 0..N-1 = korrekte Antworten seit dem
                      Vormerken. Eigenständig von drill_mastery, siehe Abschnitt
                      "Manuelle Vormerkung für Drill" _(v3.2.30)_
```

### Ablauf Warteschlange
- Beim Upload von 100 Karten → alle erhalten `status = queued`
- Täglich werden 10 Karten aktiviert: `queued` → `active`, `leitner_box = 1` — automatisch beim Start einer Leitner-Session, kein manueller Button

### Archiv-Regeln
- Karten können manuell als `archived` markiert werden
- Kein automatisches Reaktivieren — User behält immer die Kontrolle

---

## Lernmodus 1: Leitner-System (5 Fächer)

### Fächer & Intervalle
| Fach | Intervall |
|---|---|
| Fach 1 | täglich |
| Fach 2 | alle 2 Tage |
| Fach 3 | alle 7 Tage |
| Fach 4 | alle 14 Tage |
| Fach 5 | alle 30 Tage (monatliche Auffrischung, bleibt in Fach 5) |

### Scheduling-Regeln
- Importierte Karten erhalten zuerst `status = queued` — noch nicht fällig
- Bei Aktivierung (täglich 10 Stück): `status = active`, `leitner_box = 1`, `next_due_date = heute`
- `next_due_date` wird berechnet ab **Datum der richtigen Antwort** + Intervall des neuen Fachs
- Fach-5-Karten bleiben in Fach 5, bekommen `next_due_date` = heute + 30 Tage
- **Falsche Antwort → sofort zurück in Fach 1**, `next_due_date = morgen`
- Falsch beantwortete Karte wandert ans **Ende der Session-Queue** — erscheint einmal nochmal
- Beim zweiten Versuch gewusst → bleibt in Fach 1, kein Aufstieg, `next_due_date = morgen`
- Beim zweiten Versuch wieder falsch → bleibt in Fach 1, kein weiterer Versuch in dieser Session
- Übersprungene Karten wandern ans Ende der Queue, `next_due_date` bleibt unverändert

### Priorisierung innerhalb einer Session
```
1. Überfällige Karten       (next_due_date < heute)
2. Heute fällige Karten     (next_due_date = heute)
3. Neu aktivierte Karten    (status = active, leitner_box = 1, noch nie beantwortet, bis Tageslimit)
4. Weitere Karten           (nur wenn User Anzahl manuell erhöht)
```

### Neue Karten / Tageslimit
- **Standard: 10 neue Karten pro Tag** aus der Warteschlange
- Aktivierung läuft automatisch beim Start einer Leitner-Session (`activate_daily_cards()`) — kein manueller Button, Tageslimit wird dabei serverseitig berücksichtigt (bereits heute aktivierte Karten werden mitgezählt)
- Warteschlange zeigt wie viele Karten noch warten
- Beim Upload von 100 Karten → nur 10 sofort aktiv, 90 in Warteschlange

### Kartendarstellung
- Karte zentriert, max. Breite 540px (`max-width:540px; margin: 0 auto`)
- Innenabstand `p-5`, Mindesthöhe 280px
- Frage in `fs-2`, Antwort in `fs-3` — bewusst kleiner als `fs-1` damit lange Texte max. 2 Zeilen benötigen
- Flip-Animation: Karte faltet sich horizontal (`scaleX(0)` → Inhalt tauschen → `scaleX(1)`, 150ms)
- Antwort-Buttons erscheinen erst nach Abschluss der Animation (300ms)
- `pageshow`-Listener verhindert dass bfcache eine bereits aufgeklappte Karte wiederherstellt
- Gleiche Karte und Animation wie im Drill-Modus

### Session
- **Kartenanzahl** wählbar — App macht Vorschlag (alle fälligen), User kann ändern via:
  - Button **-5** / Eingabefeld (Zahl) / Button **+5**
- **Lernrichtung** wählbar: A→B, B→A, Gemischt oder Zufall (Default, siehe "Lernrichtung: Zufall-Option")
- **Letzte verwendete Liste** wird automatisch vorgeschlagen
- Session-Ende: motivierende Zusammenfassung mit:
  - Anzahl gewusst
  - Anzahl nicht gewusst
  - Anzahl Karten aufgestiegen
  - Aktueller Lernstreak (z.B. "5 Tage in Folge!")
  - Kurzer Motivationstext (z.B. "Super gemacht!")
- **Keine Karten fällig:** statt leerer 0/0/0-Zusammenfassung wird eine eigene Meldung angezeigt (✅) mit dem Datum, wann die nächsten Karten fällig werden


---

## Lernmodus 2: Drill-Modus (Incremental Rehearsal)

Basiert auf **Incremental Rehearsal** und **Mastery Learning**. Der Drill-Modus dient als **Eingangstor ins Leitner-System** — Karten beweisen zuerst im Drill ihre Automatizität und steigen dann progressiv ins Leitner-System ein.

### Ziel
Automatizität — die Antwort soll nicht errechnet oder überlegt, sondern **sofort gewusst** werden.
Terminologie: "Gewusst" / "Musste nachdenken" (kein Richtig/Falsch).
Geeignet für: Mathe-Fakten, häufig vergessene Vokabeln, neue Wörter festigen.

### Karten-Auswahl (automatisch)
- Karten mit `drill_mastery = 0` (noch nie gemeistert) → neue/unbekannte Karten
- Karten mit `drill_mastery >= 1` (mindestens einmal früher gemeistert) → bekannte Karten
- Archivierte Karten erscheinen **nicht** im Drill
- Keine manuelle Karten-Auswahl — stattdessen ungewünschte Karten einfach archivieren

### Lernrichtung & Timer (Session-Konfiguration) _(v3.3.5, Zufall-Option v3.3.11)_
- Auf der Konfigurationsseite vor dem Start wählbar, analog zum Leitner-System: **Lernrichtung** (A→B, B→A, Gemischt oder Zufall — bei "Gemischt" pro Karte deterministisch über die Karten-ID bestimmt, damit dieselbe Karte innerhalb einer Session nicht zwischen den Richtungen hin- und herspringt; Details zu "Zufall" siehe eigener Abschnitt weiter unten) sowie **Timer** in Minuten.
- Beide Werte gelten **nur für die jeweilige Session** und werden nicht dauerhaft gespeichert — der Timer-Standardwert stammt aus den Einstellungen (`DRILL_SESSION_SECONDS`), lässt sich aber pro Start frei anpassen (1–120 Min.).
- Frage-/Antwortseite und die Zuordnung von Audio/Lautschrift (immer an Sprache B gebunden, unabhängig von Frage- oder Antwortposition) folgen derselben Logik wie im Leitner-System (`get_question_answer()`, gemeinsam genutzt von `learn.php` und `drill.php`).

### Ablauf (eine Karte nach der anderen)
1. Karte wird angezeigt (nur Vorderseite / Frage, gemäss gewählter Lernrichtung)
2. User denkt nach, tippt/klickt auf die Karte → Karte dreht sich um (Flip-Animation)
3. Antwort erscheint, darunter: Button **"Gewusst"** (grün) und **"Musste nachdenken"** (orange)
4. User bewertet → nächste Karte erscheint sofort

### Karten-Reihenfolge (9:1-Verhältnis)
- Bekannte Karten (`drill_mastery >= 1`) bilden einen rotierenden Pool
- Neue/unbekannte Karten werden einzeln eingeführt: nach jeweils 9 bekannten Karten erscheint 1 neue
- Neu eingeführte Karten wandern in den rotierenden Pool und werden ab dann gemeinsam wiederholt
- Das Mischen passiert im Hintergrund — der User sieht nur eine Karte nach der anderen

### "Gemeistert"-Definition
Eine Karte gilt als in dieser Session gemeistert wenn sie **3× hintereinander** mit "Gewusst" beantwortet wurde. "Musste nachdenken" setzt den Zähler auf 0 zurück.

### "Musste nachdenken"-Behandlung
- Nach **5× "Musste nachdenken"** in einer Session → Karte als "zu schwer für heute" markiert (`drill_too_hard = 1`) und aus dem Pool entfernt
- Gilt für alle Karten gleichermassen (bekannte wie neue)
- Reset von `drill_too_hard`: lazy — beim ersten Zeigen der Karte wenn `last_drill_shown < heute`

### Navbar während der Session
- **Timer** (MM:SS, rückwärts) und **X gemeistert** werden nebeneinander angezeigt — aktualisieren sich nach jeder Kartenbewertung (PRG-Redirect)

### Session-Ende
- Endet nach dem beim Start gewählten Timer (Standard aus den Einstellungen, aktuell 10 Minuten) — nach Ablauf wird die aktuelle Karte noch fertig gespielt (Flip + Bewertung), dann Abschluss
- Oder früher wenn alle Karten gemeistert oder als "zu schwer" markiert wurden
- Abschlussmeldung:
  - Anzahl Gewusst / Musste nachdenken / Gemeistert
  - Drill-Fortschritt pro gemeisterter Karte (1×, 2×, 3×)
  - Kurzer Motivationstext
  - Hinweis: "Für beste Resultate warte ein paar Stunden bis zur nächsten Session"

### Progressiver Übergang ins Leitner-System
Gemeisterte Drill-Karten steigen je nach `drill_mastery` ins Leitner ein:

| drill_mastery (nach Session) | Einstieg Leitner |
|---|---|
| 1× gemeistert | Fach 2, next_due_date = heute + 2 |
| 2× gemeistert | Fach 3, next_due_date = heute + 7 |
| 3× gemeistert | Fach 4, next_due_date = heute + 14 |

Fach 5 wird ausschliesslich durch echte Leitner-Wiederholungen erreicht.

- Drill-Fortschritt (`drill_mastery`) wird **separat** gespeichert
- Leitner-Fächer werden nur durch den obigen Übergang beeinflusst, nie durch Drill-Fehler
- **Nie rückstufend** _(Bugfix v3.2.38)_: Der Übergang setzt das Fach nur, wenn das per Tabelle berechnete Ziel-Fach **höher** ist als das aktuelle. Ist die Karte über normales Leitner-Lernen (unabhängig vom Drill) bereits weiter fortgeschritten als die Tabelle für die neue `drill_mastery`-Stufe vorsieht, bleibt das Fach unverändert — nur `drill_mastery` zählt weiter. Vorher konnte eine erneute Meisterung im Drill eine bereits weiter fortgeschrittene Karte fälschlich zurückstufen (z.B. Fach 4 → Fach 3), obwohl Meistern eine Belohnung und keine Verschlechterung sein soll.

### Manuelle Vormerkung für Drill _(v3.2.30)_
Zusätzlich zur automatischen Karten-Auswahl kann jede Karte einzeln manuell "für Drill vormerken"
werden — umschaltbar an zwei Stellen:
- Kartenübersicht `edit.php`: Pin-Icon oben links auf der Karte in der Kartenansicht (Direktlink
  `edit.php?...&highlight=<id>`)
- **Direkt während einer laufenden Leitner-Session** _(v3.2.41, per Fetch statt Seiten-Reload seit v3.3.10)_: derselbe runde Pin-Button oben
  links auf der Lernkarte in `learn.php`, ausgefüllt wenn vorgemerkt. Klick schaltet die Vormerkung
  sofort um, ohne die laufende Session zu unterbrechen (Queue/Fortschritt/Statistik bleiben
  unverändert, dieselbe Karte wird danach weiterhin angezeigt). Läuft über `fetch()` statt eines
  normalen Form-Submits, damit ein bereits aufgedeckter Kartenstatus (rein clientseitig über
  `flipCard()`) beim Umschalten **nicht** zurückgesetzt wird — ein normaler Seiten-Reload hätte die
  sichtbare Übersetzung sonst wieder versteckt (Bugfix v3.3.10). In `drill.php` bleibt das Symbol
  bewusst rein anzeigend (nicht klickbar), da man dort bereits mitten im Drill ist.

- Eigenes Feld `drill_pinned_correct` — **unabhängig von `drill_mastery`**. Grund: `drill_mastery`
  steuert über eine feste Fach-Zuordnung (`master_card()`) den Einstieg ins Leitner-System (siehe
  Tabelle oben). Würde Vormerken denselben Zähler nutzen, könnte eine Karte, die über normales
  Leitner-Lernen bereits in einem hohen Fach steht, beim Vormerken/Drillen auf ein niedrigeres Fach
  zurückgestuft werden.
- Vorgemerkte Karten erscheinen im Drill-Modus **priorisiert**, Modus in den Einstellungen
  konfigurierbar:
  - **Absolut:** werden immer zuerst gezeigt, solange mindestens eine vorgemerkte Karte übrig ist
  - **Gewichtet:** alle `DRILL_PIN_RATIO` Karten wird eine vorgemerkte Karte eingeschoben, die
    normale 9:1-Rotation (known/new) läuft für die übrigen Karten parallel unverändert weiter
- Bei `DRILL_MASTERY_THRESHOLD`× richtiger Antwort **in Folge seit dem Vormerken** wird die
  Vormerkung automatisch entfernt (`drill_pinned_correct = NULL`) — **ohne** jeden Einfluss auf
  `leitner_box`, `status` oder `drill_mastery`. Das Leitner-System läuft während der gesamten
  Vormerkzeit unverändert normal weiter, die Karte wird nicht "eingefroren".
- Falsche Antwort auf eine vorgemerkte Karte setzt den Zähler auf 0 zurück (wie beim normalen
  Session-Zähler), aber **ohne** die `drill_too_hard`-Tagessperre — die Karte bleibt trotz
  wiederholtem "Musste nachdenken" im aktiven Drill-Pool.
- Vormerkung kann jederzeit auch manuell wieder entfernt werden (gleiches Icon).
- Archivierte Karten können nicht vorgemerkt werden.
- Listen-Scoping gilt wie gewohnt: eine vorgemerkte Karte erscheint im Drill nur, wenn ihre Liste
  für die Session ausgewählt wurde.

### Gilt für
- Mathe-Listen (Multiplikation, Division)
- Vokabel-Listen — generisch, kein Unterschied im Code

---

## Einstellungsseite

- **Nur für Admins zugänglich** _(v3.0.0)_ — `require_admin()`, auf allen Umgebungen
- Icon-Button (`bi-gear`, Tooltip "Einstellungen") in der zentralen Navbar, nur für Admins sichtbar _(v3.0.0)_
- Einstellungen werden **dauerhaft in `config-runtime.php`** geschrieben (gitignored, wird nie per Deploy überschrieben)
- Auf Localhost: zusätzlicher "Localhost"-Badge sichtbar
- PRG-Muster: nach Speichern Redirect auf GET, Flash-Meldung via Session
- **Zweispaltiges Layout** _(v3.2.14)_: Seite nutzt die breite Container-Variante (wie `home.php`/`lists.php`, kein `max-width`-Limit mehr). Links (halbe Breite): Allgemein/Leitner/Drill-Einstellungen. Rechts (halbe Breite): E-Mail-Test und Deployment

### Konfigurierbare Werte (Gruppen: Allgemein / Leitner / Drill)
| Gruppe | Einstellung | Konstante | Beschreibung | Bereich |
|---|---|---|---|---|
| Allgemein | Seitentitel | `APP_NAME` | Anzeigename oben links in der Navbar | max. 50 Zeichen, keine Anführungszeichen (`'`) |
| Allgemein | Basis-URL | `APP_BASE_URL` | Adresse der Installation für Links in E-Mails (Passwort-Reset) — ohne Slash am Ende _(v3.2.23)_ | muss mit `http://` oder `https://` beginnen; leer = auf dem Server werden keine Reset-Mails verschickt (Warnhinweis in den Einstellungen) |
| Allgemein | Absender-E-Mail | `MAIL_FROM` | Absenderadresse für Passwort-Reset und Test-Mail _(v3.2.24)_ | gültige E-Mail-Adresse; leer = `no-reply@` + Host der Basis-URL (Warnhinweis, falls das eine Subdomain ist — siehe unten) |
| Allgemein | Session-Timeout | `SESSION_TIMEOUT` | Inaktivitäts-Timeout in Minuten | 1–1440 _(bis 24 Std., v2.7.4)_ |
| Leitner | Tägliches Karten-Limit | `DAILY_CARD_LIMIT` | Neue Karten pro Tag aus der Warteschlange | 1–100 |
| Leitner | Default Kartenanzahl | `LEITNER_DEFAULT_CARDS` | Voreingestellte Anzahl Karten beim Session-Start | 1–200 |
| Drill | Timer | `DRILL_SESSION_SECONDS` | Dauer einer Drill-Session in Minuten | 1–120 |
| Drill | «Musste nachdenken»-Limit | `DRILL_TOO_HARD_LIMIT` | Bewertungen bis Karte aus Session entfernt wird | 1–20 |
| Drill | Mastery-Schwelle | `DRILL_MASTERY_THRESHOLD` | Aufeinanderfolgende Korrekt-Antworten für «gemeistert» | 1–10 |
| Drill | Bekannt/Neu-Verhältnis | `DRILL_KNOWN_RATIO` | Bekannte Karten pro neuer Karte in der Rotation | 1–30 |

### Benutzerverwaltung _(v3.0.0)_
- Kein eigener Passwort-Änderungs-Abschnitt mehr — das eigene Passwort ändert jede Person selbst über das "Konto"-Modal in der Navbar, siehe Abschnitt "Zugang / Benutzerverwaltung"
- Kein Link auf `users.php` mehr innerhalb der Einstellungsseite — direkt über das Icon (`bi-person-gear`) in der zentralen Navbar erreichbar, siehe Abschnitt "Benutzerverwaltung"

### Absenderadresse und Zustellbarkeit _(v3.2.24)_

- Die Absenderadresse ausgehender Mails ist über die Einstellung **Absender-E-Mail** (`MAIL_FROM`) frei wählbar. Ohne Konfiguration wird `no-reply@` + Host der Basis-URL verwendet
- **Hintergrund (echter Fehlerfall auf Produktion):** Die App lief unter der Subdomain `lernen.springpunkt.ch` und verschickte dadurch als `no-reply@lernen.springpunkt.ch`. Diese Subdomain hat **keinen eigenen SPF-Record** — SPF wird nicht von der Hauptdomain vererbt. Die DMARC-Policy der Hauptdomain (`p=quarantine`) gilt für Subdomains hingegen sehr wohl. Ergebnis: SPF-Prüfung ohne Ergebnis, kein DKIM, DMARC schlägt fehl → der Empfänger (Gmail) sortiert die Mail aus oder verwirft sie, **obwohl `mail()` Erfolg meldet** (der Hoster hat die Nachricht ja angenommen — verloren geht sie erst beim Empfänger). Genau deshalb war der Mailversand auf Prod nie erfolgreich, ohne dass die App einen Fehler anzeigte
- **Regel:** Die Absenderadresse muss zu einer Domain gehören, deren SPF-Record den Mailserver des Hosters abdeckt — im Regelfall die Hauptdomain (hier: `no-reply@springpunkt.ch`, deren SPF `include:spf.hostfactory.ch` enthält). Alternativ könnte man der Subdomain einen eigenen SPF-Record geben; die Absenderadresse umzustellen ist aber der Weg ohne DNS-Änderung
- Die Einstellungsseite warnt aktiv, wenn keine Absenderadresse gesetzt ist **und** die Basis-URL auf eine Subdomain zeigt, und schlägt die Hauptdomain-Variante vor

### E-Mail-Test _(v3.2.12)_
- Eigene Karte auf der Einstellungsseite: E-Mail-Adresse eingeben, "Test-E-Mail senden" klicken → verschickt eine Test-Mail mit derselben Versandmethode wie "Passwort vergessen" (`mb_encode_mimeheader()` für den Subject, `-f`-Parameter, `Content-Type: text/plain; charset=utf-8`)
- Erfolgsmeldung ("Test-E-Mail an ... wurde übergeben.") oder Fehlermeldung, je nach Rückgabewert von `mail()` — Fehlschläge zusätzlich im PHP-Error-Log
- Zweck: Mailversand auf einem Server (v.a. nach Umzug/Hosterwechsel) unabhängig vom Passwort-Reset-Ablauf prüfen können

### Debug-Modus _(v3.2.34)_
- Eigene Karte auf der Einstellungsseite, unterhalb "Deployment": Ein/Aus-Schalter "Debug-Modus aktiv" → Konstante `DEBUG_MODE`, eigenes kleines Formular (`action=save_debug`), unabhängig vom grossen Einstellungs-Formular
- **Global, aber nur für Admins sichtbar:** Der Schalter selbst gilt serverweit (in `config-runtime.php`), das Debug-Panel wird aber nur gerendert, wenn die eingeloggte Person zusätzlich Admin ist (`$_SESSION['is_admin']`) — andere Personen sehen bei aktivem Debug-Modus keinerlei Unterschied. Bleibt auch bei "Person wechseln" korrekt, da der Admin-Status dabei erhalten bleibt (siehe Abschnitt "Zugang / Benutzerverwaltung")
- **Wirkung:** In `learn.php` und `drill.php` erscheint nach jeder beantworteten Karte ein `alert alert-info`-Panel (einmalig, wie eine Flash-Message) mit dem Vorher/Nachher-Status der gerade beantworteten Karte:
  - Leitner: Fach vorher→nachher, Fälligkeit vorher→nachher; bei Übersprungen "nichts geändert"; bei 2. Versuch entsprechend vermerkt
  - Drill: bei besonderen Ereignissen (gemeistert, als zu schwer markiert, Vormerkung erreicht) eine hervorgehobene Zeile mit Fach-/Zähler-Änderung; sonst **beide** Session-Zähler gleichzeitig, je auf eigener Zeile — "Mastery-Zähler X/`DRILL_MASTERY_THRESHOLD`" (Folge richtiger Antworten, setzt bei falscher Antwort auf 0 zurück) und "Zu-schwer-Zähler X/`DRILL_TOO_HARD_LIMIT`" (Gesamtzahl falscher Antworten in dieser Session, wird durch richtige Antworten dazwischen NICHT zurückgesetzt) _(v3.2.42)_
  - Erscheint auch auf der jeweiligen Abschluss-/Zusammenfassungsseite, wenn die beantwortete Karte die letzte der Session war
- Betrifft nur `learn.php`/`drill.php`, nicht `math.php`
- **Position & Schliessen-Button** _(v3.2.44, Schliessen-Button v3.3.6)_: Panel ist per `position: fixed` am unteren Bildschirmrand fixiert statt im normalen Textfluss, damit es ohne Scrollen sichtbar ist. Bei Karten die den Viewport sprengen kann es dadurch die Antwort-Buttons überlagern — dagegen ein "×"-Schliessen-Button rechts im Panel (`data-bs-dismiss="alert"`, nutzt die auf `learn.php`/`drill.php` bereits geladene Bootstrap-JS, kein zusätzliches Script). Panel bleibt danach für den Rest der Kartenanzeige ausgeblendet, erscheint bei der nächsten beantworteten Karte wieder neu.

---

## Benutzerverwaltung (`users.php`) _(v3.0.0)_

- Nur für Admins zugänglich (`require_admin()`)
- Icon-Button (`bi-person-gear`, Tooltip "Benutzerverwaltung") direkt in der zentralen Navbar auf jeder Seite, neben "Einstellungen" — nur für Admins sichtbar, führt direkt zu `users.php`. Nicht mehr aus `settings.php` verlinkt (entfernt)
- Breadcrumb: `Startseite > Benutzerverwaltung` — nicht unter "Einstellungen" verschachtelt, da eigenständig aus der Navbar erreichbar
- Tabelle aller Personen: Name, E-Mail _(v3.0.0)_, Status-Badge (Admin/Person), Aktionen
- **Aktions-Buttons als Icons mit Tooltip** _(v3.0.1)_: E-Mail (`bi-envelope-plus`), Passwort zurücksetzen (`bi-key`), Admin-Status umschalten (`bi-person-dash` wenn aktuell Admin/"Admin entfernen", `bi-person-lock` wenn aktuell Person/"Zu Admin machen") — statt Text-Buttons
- **E-Mail-Adresse setzen/ändern** _(v3.0.0)_: Modal pro Person, optional, leer lassen entfernt sie — dient dem eigenständigen Passwort-Reset dieser Person. Format wird serverseitig validiert (`FILTER_VALIDATE_EMAIL`) — bei ungültigem Format Fehlermeldung, keine Speicherung _(v3.1.1)_
- **Passwort zurücksetzen**: Admin setzt direkt ein neues Passwort für eine Person, ohne deren altes Passwort zu kennen (Modal, min. 8 Zeichen)
- **Admin-Status umschalten**: Button pro Person — der letzte verbleibende Admin kann nicht entfernt werden. Ist eine Person der letzte verbleibende Admin, ist "Admin entfernen" gar nicht erst sichtbar (unsichtbar/nicht interagierbar wie beim Lösch-Icon, reserviert aber denselben Platz für bündige Ausrichtung) statt sich erst nach Klick per Fehlermeldung abzuweisen _(v3.2.6)_
- **Neue Person anlegen**: Name (eindeutig) + initiales Passwort (min. 8 Zeichen) + optionale E-Mail-Adresse (ebenfalls formatvalidiert) _(v3.0.0, Validierung v3.1.1)_ + optionales Admin-Flag
- **Person löschen** _(v3.2.0)_: Icon-Button (`bi-trash`) pro Person; bei der eigenen Zeile unsichtbar/nicht interagierbar (kein Selbstlöschen möglich, weder Button noch serverseitig), reserviert aber denselben Platz, damit die Aktions-Icons aller Zeilen bündig bleiben. Löscht die Person **unwiderruflich und vollständig** — eigene Listen, Karten (via Listen-Kaskade), gesamter Lernfortschritt (`card_progress`), Lernereignisse (`learning_events`), alles über die bestehenden DB-Fremdschlüssel-Kaskaden (`ON DELETE CASCADE`), ausgeführt innerhalb einer expliziten Transaktion (Rollback bei Fehler). Bestätigung per Modal: Checkbox "Ich bin mir sicher..." muss angehakt werden (native HTML5-Pflichtfeld-Validierung, kein Custom-JS nötig) _(vereinfacht von Namenseingabe auf Checkbox in v3.2.8)_ — serverseitig zusätzlich geprüft (`confirm=1` muss mitgesendet werden, sonst Fehlermeldung ohne Löschung). Der letzte verbleibende Admin kann nicht gelöscht werden (gleicher Schutz wie beim Entfernen des Admin-Status)
- CSRF-geschützt, PRG-Muster wie überall sonst

---

## Passwort vergessen (`forgot-password.php`, `reset-password.php`) _(v3.0.0)_

- Link "Passwort vergessen?" auf der Login-Seite (`index.php`), nur für nicht eingeloggte Nutzer relevant
- `forgot-password.php`: E-Mail-Adresse eingeben → Server sucht eine Person mit exakt dieser E-Mail
  - Treffer: Einmal-Token generiert (`random_bytes(32)`, als SHA-256-Hash in `persons.reset_token_hash` gespeichert, nie im Klartext), Ablaufzeit `persons.reset_token_expires` = jetzt + 60 Minuten, Link per E-Mail verschickt (`mail()`, kein SMTP/keine externe Bibliothek)
  - Kein Treffer: keine E-Mail verschickt
  - **In beiden Fällen dieselbe generische Erfolgsmeldung** — verhindert, dass sich per Ausprobieren herausfinden lässt, welche E-Mail-Adressen registriert sind
- `reset-password.php?token=...`: Token wird gehasht und gegen `reset_token_hash` geprüft (muss existieren UND `reset_token_expires` in der Zukunft liegen)
  - Ungültiger/abgelaufener Token: Fehlermeldung, Link zu `forgot-password.php` um einen neuen anzufordern
  - Gültiger Token: Formular für neues Passwort (min. 8 Zeichen, Wiederholung muss übereinstimmen) — bei Erfolg wird `password_hash` gesetzt und `reset_token_hash`/`reset_token_expires` sofort geleert (Token ist danach unbrauchbar, auch bei erneutem Aufruf desselben Links)
- Ohne hinterlegte E-Mail-Adresse ist für eine Person kein eigenständiger Reset möglich — nur der Admin kann über `users.php` das Passwort zurücksetzen
- E-Mail-Adresse ist über alle Personen eindeutig (DB-Unique-Index, erlaubt aber beliebig viele Personen ohne E-Mail)

---

## Mathe-Generator

- Erreichbar über **Meine Listen** (lists.php) — nicht mehr direkt von der Startseite
- Einmaliger Generator für **Multiplikationstabellen** und **Divisionstabellen** — Bereich konfigurierbar, Standard 1×1 bis 10×10, maximal bis 20×20
- **Duplikat-Prüfung (typ-basiert):** Existiert bereits eine Liste desselben Typs (Multiplikation oder Division), erscheint eine Warnung mit Checkbox-Bestätigung — erst mit Bestätigung wird ein zweites Deck erstellt. Listenname spielt dabei keine Rolle.
- Multiplikation und Division werden als **separate Decks** generiert:
  - Deck Multiplikation: `7 × 8 = ?`
  - Deck Division: `56 ÷ 7 = ?`
- Erstellte Listen laufen normal durch beide Lernmodi (Leitner + Drill)
- Einträge können manuell als `archived` markiert werden (z.B. 1×1, 1×2 zu einfach)
- Später erweiterbar: Addition, Subtraktion

---

## Statistik-Dashboard

Statistik startet mit der ersten eigenen Liste vorausgewählt — kein globaler "Alle Listen"-Modus. Auswahl per Button oben — zeigt nur **aktive** Listen (`is_active = 1`), inaktive Listen erscheinen dort nicht zur Auswahl _(v3.2.13)_.

**Lernaktivität** _(v3.0.3)_ — eigene Karte oben, unabhängig vom Listen-Filter (zählt über alle Listen der Person):
- Drei Kennzahlen nebeneinander: 🔥 Aktueller Streak, Lerntage gesamt (Anzahl distinkter Tage mit mindestens einer beantworteten Karte, je über alle Zeit), Beste Woche (maximale Anzahl Lerntage in einer einzelnen Kalenderwoche, Mo–So, über alle Zeit)
- Lerntag-Definition (gilt für Streak, Gesamt und Heatmap):
  - Leitner und Drill zählen beide
  - Mindestens eine Karte beantwortet (gewusst oder nicht gewusst) = Lerntag
  - Überspringen allein zählt nicht
  - Abgebrochene Session zählt wenn mindestens eine Karte beantwortet wurde
- Heatmap der letzten 52 Kalenderwochen bis heute (GitHub-Contribution-Graph-Stil): Spalten = Kalenderwochen (links = älteste), Zeilen = Mo–So, 5-stufige Grün-Skala nach Anzahl beantworteter Karten am jeweiligen Tag relativ zum eigenen Maximum im sichtbaren Zeitraum (kein Tag = leer/grau), Monatsbeschriftung über den Spalten, Wochentag-Labels links (nur Mo/Mi/Fr), Tooltip beim Hover zeigt Datum (Format `TT.MM.JJJJ`, z.B. `29.07.2026`) _(Format korrigiert v3.2.10, vorher `YYYY-MM-DD`)_ + Anzahl gelernter Karten bzw. "nicht gelernt". Zukünftige Tage der laufenden Woche bleiben leer. Reine CSS-Grid/HTML-Lösung ohne externe Charting-Library, horizontal zentriert innerhalb der Karte _(zentriert seit v3.1.1)_. **Der angezeigte Zeitraum passt sich automatisch an die verfügbare Breite an** _(v3.2.27; zuvor feste 18 Wochen auf Mobilgeräten, v3.2.25)_ — vorher musste man auf dem Handy erst seitlich scrollen, um überhaupt den aktuellen Zeitraum zu sehen. Der Server rendert immer alle 52 Wochen; ein kleines Skript am Seitenende berechnet aus der tatsächlichen Breite (`clientWidth` minus Wochentagsspalte, geteilt durch 14 px pro Woche), wie viele Wochen hineinpassen, blendet nur so viele der ältesten aus wie nötig (immer volle Wochen, damit jede Spalte weiter mit Montag beginnt) und rückt die Monatsbeschriftung entsprechend nach. Dadurch zeigt jedes Gerät so viel Verlauf wie es darstellen kann — Minimum 4 Wochen, Maximum die vorhandenen 52 — und die Ansicht passt sich beim Drehen des Geräts neu an. **Ohne JavaScript** bleiben alle 52 Wochen sichtbar und die Heatmap ist wie früher horizontal scrollbar; sie ist eine Zusatzinformation, keine Funktion, die dadurch ausfällt. **Bewusst kein eigenes Dark-Mode-Farbschema** _(entfernt v3.2.18, vorher per `prefers-color-scheme` v3.0.3–v3.2.17)_ — die Heatmap zeigt immer die helle Farbpalette, da der Rest der Anwendung ebenfalls kein Dark-Mode-Theme hat; ein Dark Mode nur für die Heatmap wäre inkonsistent mit dem übrigen (durchgehend hellen) UI.

**Leitner-Übersicht:**
- Anzahl Karten pro Fach (Fach 1–5 + archiviert)
- Richtig/Falsch-Statistik
- Anzahl Karten in Warteschlange

**Drill-Übersicht:**
- Anzahl Karten gemeistert (1×, 2×, 3×)
- Gesamtquote "Gewusst" / "Musste nachdenken" pro Liste

---

## Import-Seite

- Ausführliche Erklärung des CSV-Formats mit Beispiel
- Hinweis auf erlaubte Trennzeichen (Komma oder Semikolon)
- Downloadbare CSV-Vorlage (Vokabeln)
- Duplikat-Warnung vor dem Import mit Entscheidungsmöglichkeit
- **Datei-Auswahl-Feld** _(v3.2.40)_: `accept` gibt neben der Endung `.csv` zusätzlich die MIME-Types (`text/csv`, `text/comma-separated-values`, `application/vnd.ms-excel`) an — reine Endungs-Filter ohne MIME-Type können auf manchen iOS-Safari-Versionen dazu führen, dass sich der native Datei-Dialog beim Antippen gar nicht öffnet

---

## Hilfeseite _(v2.8.0)_

- `help.php` — Handbuch/Hilfeseite, erreichbar über das Info-Icon (`bi-info-lg`) ganz rechts in der Navbar auf jeder Seite (siehe Abschnitt "Navigation")
- Erfordert Login (`require_person()`) — Login löst seit v3.0.0 immer direkt eine Person auf, daher kein separater "keine Person gewählt"-Zustand mehr nötig
- Inhalt als Bootstrap-Accordion (erster Abschnitt initial aufgeklappt): **Einleitung** _(v3.3.9)_, Login & Person, Wortlisten verwalten, Wörter hinzufügen, **Aufbau einer Lernkarte** _(v3.3.2)_, Leitner-Modus, Drill-Modus, Aussprache (Audio & Lautschrift), Statistik & Streak (inkl. Erklärung der Lernaktivitäts-Heatmap: Kennzahlen, Wochen-/Wochentag-Layout, Grünstufen, Tooltip) _(v3.2.11)_, **Für Admins: Einstellungen & Benutzerverwaltung** _(v3.0.0)_, MCP-Server (für technisch interessierte Nutzer — Setup-Hinweis ohne Zugangsdetails wie Token, plus konkrete Beispiele was der Agent tun kann: Personen/Listen abfragen, Karten hinzufügen/korrigieren, Import-CSV im richtigen Format erstellen) _(erweitert v3.2.16)_
- **"Einleitung" als eigener, standardmässig geöffneter Abschnitt** _(v3.3.9)_: Der kurze Überblick über die Grundfunktionen (bisher als freier Text oberhalb des Accordions, passte optisch nicht zum Rest der Seite) steht jetzt als erster Accordion-Abschnitt "Einleitung" — dieser ist per Default aufgeklappt, alle anderen Abschnitte inkl. des vormals ersten sind standardmässig eingeklappt. Der bisherige erste Abschnitt "Einstieg: Login & Person" heisst dadurch nur noch **"Login & Person"** (der Begriff "Einstieg" gehört jetzt zur neuen Einleitung).
- **Aufbau einer Lernkarte** _(v3.3.2)_: eigener Abschnitt zwischen "Wörter hinzufügen" und "Leitner-Modus", da die Lernkarte in beiden Lernmodi identisch aussieht. Bebildert (Screenshot `img/learner-karte.png`) erklärt: oberer Teil = Frageseite, unterer Teil = per Antippen aufgedeckte Antwortseite (Sprachname, Begriff, optionale Beschreibung je Seite), Lautschrift + 🔊-Ausspracheknopf gehören immer zur fremdsprachigen Seite (Sprache B) unabhängig davon ob diese oben oder unten erscheint, Pin-Symbol oben links für "Für Drill vormerken".
- **Admin-only Abschnitte** _(v3.2.16)_: "Für Admins: Einstellungen & Benutzerverwaltung" und "Für Technik-Fans: Karten per KI-Agent verwalten" sind nur für Personen mit Admin-Status sichtbar (`$_SESSION['is_admin']`) — dadurch **11 Abschnitte für Admins, 9 für alle anderen** _(Stand v3.3.9)_
- **Drill-Modus-Erklärung ergänzt** _(v3.2.16)_: Hinweis, dass bei einer neuen Liste dieselbe Karte am Anfang mehrmals kurz hintereinander gezeigt werden kann (beabsichtigt, dient der Einprägung, entspannt sich je mehr Karten im Umlauf sind) — sowie Klarstellung, dass es **keine** Leitner-Fach-Obergrenze für den Drill-Modus gibt (Karten aus jedem Fach können weiterhin im Drill auftauchen, solange nicht archiviert)
- **Aussprache-Erklärung ergänzt** _(v3.2.16)_: Hinweis, dass Stimme/Klang vom Gerät bzw. Betriebssystem kommen, nicht von der App selbst — kann daher mechanisch klingen, hilft aber bei der Betonung
- **Live-Ausprobieren im Aussprache-Abschnitt** _(v3.3.3, Text/Sprache angepasst v3.3.4)_: Button nutzt dieselbe `speakWord()`-Logik und denselben Button-/Icon-Stil (`bi-volume-up-fill`) wie die 🔊-Knöpfe auf Leitner-/Drill-Karten, zusätzlich mit Text "Klick mich" beschriftet. Spricht den Beispieltext "Can you hear me?" auf `en-GB`. Begriff und verwendete Sprache stehen direkt daneben als Text.
- **Weitere Screenshots** _(v3.3.8)_ — gleicher Bildstil wie `learner-karte.png` (zentriert, `img-fluid rounded border shadow-sm`, kurze Bildunterschrift):
  - `img/neues-wort.png` im Abschnitt "Wörter hinzufügen": zeigt das Formular für "Manuell" auf der Kartenübersicht.
  - `img/neue-session-leitner.png` im Abschnitt "Leitner-Modus": zeigt die Konfigurationsseite (Liste, Lernrichtung, Kartenanzahl) vor dem Start.
  - `img/neue-session-drill.png` im Abschnitt "Drill-Modus": zeigt die Konfigurationsseite (Liste, Lernrichtung, Timer) vor dem Start. Direkt darunter ein Tipp: der Timer sollte **mindestens 5 Minuten** betragen, sonst reicht die Zeit meist nicht, um dieselbe Karte oft genug zu wiederholen (Begründung: `DRILL_MASTERY_THRESHOLD` verlangt 3× richtig hintereinander — bei sehr kurzer Laufzeit kommt kaum eine Karte auf genug Wiederholungen).
  - `img/drill-timer.png` im Abschnitt "Drill-Modus": zeigt Ausschnitt aus der Navbar einer laufenden Session (Abbrechen-Symbol, Countdown-Timer) — bisher unbebilderter Lücke in der Hilfeseite, ergänzt mit kurzem Begleittext. Feste Breite `105px` statt `img-fluid`-Skalierung nach oben _(v3.3.9)_ — das Original ist nur 210×94px, entspricht in etwa der tatsächlichen Grösse dieses Navbar-Ausschnitts auf der Webseite selbst, statt als grosser Screenshot zu wirken.
- **Mobile Heatmap-Anpassung im Statistik-Abschnitt erwähnt** _(v3.3.9)_: Zusätzlicher Aufzählungspunkt bei "Statistik & Streak" — die Heatmap zeigt auf dem Handy nur die letzten paar Monate statt aller 52 Wochen, passt sich also der Bildschirmbreite an statt zu schrumpfen. Textliche Ergänzung, kein neues Bild (das Verhalten selbst existiert bereits seit v3.2.27, siehe Abschnitt "Statistik-Dashboard").
- Nutzerorientiert mit Kernmechanik (z.B. 5 Leitner-Fächer, Warteschlangen-Prinzip, Drill-Übergang ins Leitner-System), aber ohne Datenbank-/Code-Details
- **Leitner-/Drill-Abschnitte zeigen die tatsächlich konfigurierten Werte live im Text** _(v3.0.2)_ — z.B. Leitner-Intervalle pro Fach (`LEITNER_INTERVALS`), tägliches Karten-Limit (`DAILY_CARD_LIMIT`), Default-Kartenanzahl (`LEITNER_DEFAULT_CARDS`), Drill-Timer in Minuten (`DRILL_SESSION_SECONDS`), Mastery-Schwelle (`DRILL_MASTERY_THRESHOLD`), «Musste nachdenken»-Limit (`DRILL_TOO_HARD_LIMIT`), Bekannt/Neu-Verhältnis (`DRILL_KNOWN_RATIO`) — passen sich automatisch an, wenn diese Werte in den Einstellungen geändert werden, keine statischen/veralteten Angaben mehr
- Kein eigener Datenbankzugriff für Inhalte (Konfigurationswerte kommen aus PHP-Konstanten) — `db.php` wird nur für die zentrale Navbar (`render_navbar($pdo)`) benötigt, keine Formulare ausser Logout/Konto/Person-wechseln (Navbar-Aktionen)

---

## Installation

- Einmaliges `install.php` Script das:
  1. Datenbankverbindung prüft
  2. Alle Tabellen automatisch erstellt (idempotent — `IF NOT EXISTS`), inkl. aller Spalten aus dem Login-Modell ab v3.0.0 (`password_hash`, `is_admin`, `email`, Reset-Token-Felder) — eine komplette Neuinstallation braucht daher KEINE Migrationen, das Schema ist von Anfang an vollständig
  3. Die erste Person direkt in `persons` anlegt (Name + Passwort), automatisch als Admin — nur möglich solange noch keine Person existiert; sobald eine Person existiert, verweist Schritt 2 stattdessen auf die Benutzerverwaltung (`users.php`) _(v3.2.1, ersetzt das frühere globale Passwort in der `settings`-Tabelle, das seit dem Login-Modell v3.0.0 nicht mehr existierte)_
- Nach der Ersteinrichtung muss `install.php` **manuell vom Produktiv-Server gelöscht** werden
- `index.php` erkennt ob `install.php` noch existiert und sperrt die App auf Produktion bis sie gelöscht ist — auf Localhost kein Block
- `install.php` ist im Git-Repo (nicht gitignored) — beim Deploy wird sie automatisch übersprungen (in `deploy.php` Skip-Liste)
- **DB-Migrationen:** `migrations.php` wird bei jedem Request automatisch aufgerufen — fehlende Spalten/Tabellen werden ergänzt ohne manuellen SQL-Eingriff. Die konkrete Migrations-Liste ist bewusst historisch: alte Migrationen werden entfernt, sobald alle bekannten Installationen (Dev + Prod) sie durchlaufen haben UND `install.php` den Zielzustand von Anfang an abbildet — zuletzt geschehen in v3.2.21 (Migrationen 1–13 entfernt, nachzulesen in der Git-Historie). Neue Migrationen bauen auf der zuletzt erreichten `db_version` auf (aktuell 13, nächste Migration beginnt bei 14)
- **Wichtig für zukünftige Änderungen:** Ändert sich das DB-Schema oder der Login-/Auth-Ablauf, muss `install.php` (Tabellen-Definition UND Ersteinrichtungs-Flow) im selben Zug geprüft/angepasst werden — sonst bricht eine komplette Neuinstallation, obwohl migrierte Bestandssysteme unauffällig weiterlaufen (siehe `CLAUDE.md`, Abschnitt Release-Prozess)

---

## Deployment auf Produktiv-Server

Neue Versionen werden via ZIP-Download von GitHub eingespielt (kein `shell_exec`/`exec` nötig):

- `deploy.php` ist im Git-Repo versioniert _(v2.0.3)_ — schützt sich aber über die eigene Skip-Liste selbst vor Überschreiben, muss also bei Änderungen weiterhin manuell per FTP auf den Produktiv-Server kopiert werden
- Aufruf: Button "Deploy-Status öffnen" in den Einstellungen (settings.php) führt zu `deploy.php?token=...` — löst noch **kein** Deployment aus, zeigt nur die Statusanzeige (installierte vs. GitHub-Version) _(zweistufig seit v3.2.7)_
- **Zweistufiger Ablauf** _(v3.2.7)_: `deploy.php` zeigt beim reinen Aufruf (GET) nur den Versionsvergleich und einen eigenen Button "Deploy starten". Erst ein Klick auf diesen Button (POST, CSRF-geschützt, `action=run_deploy`) lädt das ZIP herunter und kopiert die Dateien. Verhindert, dass ein Klick auf der Einstellungsseite versehentlich sofort ein echtes Deployment auslöst
- Nach einem erfolgreichen Deploy liest die Seite `includes/config.php` erneut ein und zeigt die tatsächlich installierte Versionsnummer — "Installiert" und "GitHub" zeigen dann `=`, kein scheinbarer Widerspruch zur Erfolgsmeldung mehr
- **Warnung bei "Downgrade"** _(v3.2.9)_: Ist die Version auf GitHub älter als die aktuell installierte (`version_compare`, z.B. weil lokale Änderungen noch nicht gepusht wurden), löst der Klick auf "Deploy starten" das Deployment NICHT sofort aus, sondern zeigt zuerst eine Warnung ("Ein Deployment würde die neuere lokale Version mit dem älteren GitHub-Stand überschreiben...") mit einem separaten Bestätigungs-Button ("Ja, trotzdem deployen") sowie einer Abbrechen-Möglichkeit. Bereits auf der reinen Statusanzeige wird dieser Fall entsprechend anders formuliert als der normale "Update verfügbar"-Hinweis
- **Zurück-Link** _(v3.2.13)_: Auf jeder Ansicht von `deploy.php` (Statusanzeige, Downgrade-Warnung, Ergebnis) führt ein Link "← Zurück zu Einstellungen" zurück zu `settings.php`
- **Changelog-Vorschau** _(v3.2.32)_: Unterhalb des Versionsvergleichs zeigt die reine Statusanzeige (vor einem Deploy) je Änderungspunkt aus `docs/CHANGELOG.md` eine Zeile `[X.Y.Z] - Titel` — geladen per `raw.githubusercontent.com` vom `main`-Branch, eingegrenzt auf Versionen neuer als die installierte bis einschliesslich der GitHub-Version (installierte Version selbst wird nicht mit aufgeführt, es geht nur um das, was ein Deploy an Neuem bringen würde). Titel = fett hervorgehobener Text am Zeilenanfang eines Änderungspunkts; hat ein Punkt keine Hervorhebung, wird bis zum ersten Satzende bzw. max. 100 Zeichen als Fallback verwendet. Bei bereits aktuellem Stand oder wenn `CHANGELOG.md` nicht geladen werden kann, bleibt der Block schlicht leer — kein Fehler.
- **Zusätzlich zum Token ist eine aktive Admin-Session erforderlich** _(v3.0.0)_ — ein reiner Token-Aufruf per Lesezeichen ohne eingeloggten Admin funktioniert nicht mehr (`403`). Der Button in `settings.php` funktioniert unverändert, da die Browser-Session automatisch mitgeschickt wird
- Script lädt das GitHub-Repo als ZIP via cURL herunter, entpackt es und kopiert die Dateien
- **Atomares Überschreiben** _(v3.2.46)_: Jede Datei wird beim Kopieren zuerst in eine temporäre Datei im selben Zielverzeichnis geschrieben und erst per `rename()` über die eigentliche Zieldatei gelegt (`rename()` ist auf demselben Dateisystem atomar), statt sie per `copy()` direkt zu überschreiben. Grund: `copy()` schreibt Stück für Stück in die bestehende Datei hinein — eine parallele Anfrage, die genau währenddessen dieselbe Datei einliest (z.B. ein Kartenupdate in `edit.php`, während ein Deploy läuft), konnte sie dadurch abgeschnitten/kaputt zu sehen bekommen und brach mit einem Verbindungsabbruch ab (bei `includes/auth.php` im schlimmsten Fall als unerwarteter Logout sichtbar)
- Token wird in `deploy-config.php` konfiguriert (bleibt in `.gitignore` — Trennung von Logik und Geheimnis)

**Dateien die nie per Deploy überschrieben werden (Skip-Liste in deploy.php):**
- `db-credentials.php` — Datenbankzugangsdaten
- `config-runtime.php` — Laufzeit-Einstellungen (Prod-spezifisch)
- `deploy.php` — das Deploy-Script selbst
- `deploy-config.php` — Deploy-Token und GitHub-Konfiguration
- `install.php` — Erstinstallations-Script (manuell verwalten)

**Voraussetzungen auf dem Server:**
- PHP mit cURL-Extension (auf den meisten Hostern verfügbar)
- GitHub-Repo muss **public** sein (kein Token für Download nötig)
- Schreibrechte im App-Verzeichnis

**Konfiguration (`deploy-config.php`):**
- `DEPLOY_TOKEN` — schützt die deploy.php-URL, zufällig generieren: `php -r "echo bin2hex(random_bytes(32));"`
- `GITHUB_OWNER` — GitHub-Benutzername
- `GITHUB_REPO` — Repository-Name

**Versions-Vergleich in Einstellungen:**
- settings.php zeigt installierte Version und GitHub-Version nebeneinander
- Grün = aktuell, Blau = Update verfügbar
- Pfeil zwischen den Versionen zeigt die Update-Richtung: `←` (Updates fliessen von GitHub zur Installation) — bei identischen Versionen wird stattdessen `=` angezeigt _(v3.0.1)_
- Dieselbe `=`-Logik gilt auch auf der Statusseite von `deploy.php` selbst, sowohl vor als auch nach einem tatsächlich ausgeführten Deploy _(v3.0.1, zweistufiger Ablauf v3.2.7)_
- **`deploy.php`-Statusseite** _(v3.2.4, überarbeitet v3.2.7)_: Erfolgsmeldung nennt explizit die installierte Versionsnummer ("Version vX.Y.Z wurde erfolgreich auf Prod installiert.") statt nur generisch "Erfolgreich deployed". Die obere Vergleichsanzeige liest nach einem Deploy `config.php` erneut ein und zeigt daher den tatsächlichen Stand danach (`=` bei Erfolg) — kein Widerspruch mehr zwischen Anzeige und Erfolgsmeldung

---

## Versionsverwaltung & GitHub

- **Öffentliches GitHub-Repository** (public — ermöglicht ZIP-Download ohne Token)
- **Semantic Versioning:** `MAJOR.MINOR.PATCH` (Start: `1.0.0`)
- **CHANGELOG.md** mit Versionshistorie aller Änderungen
- **`.gitignore`** schliesst aus: `db-credentials.php`, `config-runtime.php`, `deploy-config.php`, `mcp-config.php`, `.mcp.json`, `*.log`, `Checkliste.md` (lokale Aufgabenliste, nie committet), temporäre Dateien _(`deploy.php` seit v2.0.3 im Repo versioniert)_

---

## Datenbankmodell

| Tabelle | Inhalt |
|---|---|
| `persons` | Personen (Name, Passwort-Hash, Admin-Flag, E-Mail, Reset-Token+Ablauf, erstellt am) _(seit v3.0.0)_ |
| `lists` | Wortlisten (Name, Beschreibung, Sprachen, Besitzer, öffentlich/privat) |
| `cards` | Karten (Sprache A/B, Beschreibung A/B, Liste, erstellt am) |
| `card_progress` | Fortschritt pro Person/Karte (status, leitner_box, next_due_date, drill_mastery, drill_too_hard) |
| `learning_events` | Einzelne Karten-Antworten — Person, Karte, Ergebnis, Datum (für Statistik und Streak-Berechnung) _(bis v3.2.19 zusätzlich über `learning_sessions`/`session_lists` gruppiert, seit v3.2.20 direkter Fremdschlüssel auf `persons`, da die Session-Gruppierung nie ausgewertet wurde)_ |
| `auth_attempts` | Fehlversuche für das Rate-Limiting von Login und „Passwort vergessen" (Scope, IP, Zeitpunkt) — keine Personenzuordnung _(v3.2.23)_ |

### Lösch-Verhalten
- Karte löschen → `card_progress` Einträge dieser Karte werden **physisch mitgelöscht** (kaskadierend)
- Liste löschen → alle Karten + deren `card_progress` werden **physisch mitgelöscht**
- Kopien anderer Personen sind unabhängig — nicht betroffen

---

## CSV-Export

- Exportiert nur **Kartendaten** (Sprache A, Sprache B, Beschreibung A, Beschreibung B)
- Erste Zeile: Kommentar `# Listenname (Sprache A / Sprache B)` — zur menschenlesbaren Dokumentation, wird beim Import ignoriert
- Zweite Zeile: Kopfzeile mit echten Sprachnamen (z.B. `Deutsch;Englisch;Beschreibung Deutsch;Beschreibung Englisch`)
- Dateiname = Listenname (Sonderzeichen ersetzt durch `_`)
- HTML-Tags und Entities werden vor dem Export bereinigt (z.B. `<p>`, `<br>`, `&amp;`) — Export ist plain text
- Kein Fortschritt im Export
- Nur **eigene Listen** exportierbar (selbst erstellt oder kopiert — beides gilt als eigene Liste)
- Encoding: **UTF-8** mit BOM (Excel-kompatibel)
- Trennzeichen: **Semikolon** (Excel-freundlich in der Schweiz)
- Export-Datei kann direkt wieder importiert werden (Roundtrip-kompatibel)

---

## Projektstruktur

```
/learner/
  index.php                ← Login (Name + eigenes Passwort pro Person)
  home.php                 ← Startseite / Dashboard der eingeloggten Person
  learn.php                ← Leitner-Session
  drill.php                ← Drill-Modus (Incremental Rehearsal)
  lists.php                ← Listen verwalten (erstellen, umbenennen, löschen)
  edit.php                 ← Karte hinzufügen / bearbeiten / löschen
  discover.php             ← Öffentliche Listen entdecken & kopieren
  import.php               ← CSV Upload mit Formatbeschreibung
  export.php               ← CSV Export
  stats.php                ← Statistik-Dashboard
  math.php                 ← Mathe-Generator (Multiplikation + Division)
  help.php                 ← Hilfe/Handbuch, erreichbar über Info-Icon in der Navbar
  settings.php             ← Einstellungsseite (nur Admin, schreibt in config-runtime.php)
  users.php                ← Benutzerverwaltung: Personen anlegen, Passwort zurücksetzen, Admin-Flag (nur Admin)
  forgot-password.php      ← Passwort-Reset anfordern (E-Mail eingeben, Link erhalten)
  reset-password.php       ← Neues Passwort setzen (via Link aus forgot-password.php)
  install.php               ← Erstinstallation: Tabellen erstellen, ersten Admin anlegen (manuell löschen nach Setup)
  mcp-server.php            ← MCP-Endpoint für Agenten (JSON-RPC über HTTP)
  deploy.php                ← ZIP-Deploy via Browser (im Repo versioniert, schützt sich selbst vor Überschreiben)
  /assets/                  ← CSS, JS
  /templates/               ← CSV-Vorlage zum Download
  /includes/                ← reine Library-/Config-Dateien, nie direkt per URL aufgerufen
    config.php                 ← Statische Konfiguration (Zeitzone, Intervalle, Version, Standardwerte)
    config-runtime.php         ← Laufzeit-Einstellungen pro Umgebung (gitignored, nie deployed, schreibt settings.php)
    migrations.php             ← Auto-Migrationen: fehlende DB-Spalten werden beim Start automatisch ergänzt
    auth.php                   ← Session-Start, Timeout, CSRF-Funktionen, require_login/person, today()
    db.php                     ← Umgebungserkennung + DB-Verbindung + Migrationen
    db-credentials.php         ← Zugangsdaten Dev + Prod (gitignored, nie committen)
    db-credentials.example.php ← Vorlage für db-credentials.php (committet)
    deploy-config.php          ← Deploy-Token + GitHub-Konfiguration (gitignored)
    mcp-config.php             ← MCP-Token (gitignored)
    mcp-config.example.php     ← Vorlage für mcp-config.php (committet)
  /docs/                    ← Dokumentation ausser CLAUDE.md
    ANFORDERUNGEN.md, CHANGELOG.md, Testing.md, mcp-einrichtung.md — Checkliste.md liegt ebenfalls hier, ist aber gitignored und nie committet, reine lokale Aufgabenliste
```

---

## MCP-Server _(v2.0.0, erweitert v2.0.1)_

`mcp-server.php` stellt einen MCP-Endpoint bereit (JSON-RPC 2.0 über HTTP POST, Streamable-HTTP im synchronen Modus — kein SSE). Clients sind Claude Code, Claude Desktop (via `mcp-remote`), ChatGPT und n8n Cloud. claude.ai Browser-Konnektoren funktionieren aktuell **nicht** (siehe unten).

### Protokoll & Authentifizierung
- Protokoll: MCP über HTTP, nur synchroner JSON-Response (kein Streaming/SSE)
- Stateless: kein serverseitiger Session-Store
- 3 JSON-RPC-Methoden: `initialize`, `tools/list`, `tools/call`
- Bearer-Token im `Authorization`-Header — Pflicht auf jedem Request
- Fallback _(v2.0.1)_: Token als `?token=`-Query-Parameter, für Clients ohne Header-Unterstützung (ChatGPT)
- Token in `mcp-config.php` (gitignored, analog `deploy-config.php`)
- HTTPS verpflichtend auf Produktion (HTTP → HTTP 403)
- **claude.ai Browser-Konnektoren verlangen OAuth** — mit reinem Bearer-/Query-Token nicht nutzbar. Ohne OAuth-Implementierung bleibt claude.ai als Client aussen vor _(v2.0.1)_

### Tools

**`list_persons`** — keine Parameter
- Gibt alle Personen zurück: `[{ id, name }]`

**`list_lists(person_id, include_inactive?)`** — Pflichtfeld: `person_id` (integer), optional: `include_inactive` (boolean, Standard `false`)
- Gibt alle Listen einer Person zurück: `{ person: { id, name }, lists: [{ id, name, language_a, language_b, speech_lang_b, is_active }] }` _(`speech_lang_b` seit v2.2.0, `include_inactive`/`is_active` seit v3.3.0)_
- Standardmässig nur aktive Listen (`is_active = 1`) — mit `include_inactive = true` auch inaktive

**`add_cards(list_id, cards[], force?)`**
- Fügt eine oder mehrere Vokabelkarten in eine Liste ein
- `cards[]` = Array aus `{ sprache_a_begriff, sprache_b_begriff, beschreibung_a?, beschreibung_b?, phonetik_b? }` (`phonetik_b` seit v2.4.0, max. 200 Zeichen)
- Feld-Regeln für den Agent (in Tool-Beschreibung, Feld-Beschreibungen und `initialize`-Instructions — keine serverseitige Validierung, reine Agent-Anweisung) _(v2.0.2, verschärft v2.0.4)_:
  - Begriff (Fremdsprache): exakt — bei Verben Grundform (Infinitiv), bei unregelmässigen Verben alle drei Formen (z.B. "go / went / gone")
  - Begriff (Deutsch): exakt, Gross-/Kleinschreibung nach deutscher Rechtschreibung _(v2.7.10)_ — Nomen **immer** gross (z.B. "Haus", "Tisch"), alle anderen Wortarten (Verben in Grundform, Adjektive, Adverbien etc.) klein (z.B. "laufen", "schnell", "oft"), ausser am Satzanfang bei mehrteiligen Begriffen/Wendungen (nur das erste Wort gross, unabhängig von der Wortart). Rechtschreibung ist auf **de-CH** ausgerichtet: nie "ß", immer "ss" (z.B. "Strasse") _(v3.2.37)_
  - Beschreibung (Fremdsprache): Beispielsatz mit dem exakten fremdsprachigen Begriff
  - Beschreibung (Deutsch): beschreibt die Bedeutung genauer, **ohne den fremdsprachigen Begriff zu nennen** — bei unregelmässigen Verben ggf. vermerken, bei Mehrdeutigkeit den Verwendungskontext klären
  - Bekannter Fehlerfall, der zur Verschärfung führte: Agent schrieb den fremdsprachigen Begriff versehentlich in die deutsche Beschreibung (z.B. `bounced` in der Beschreibung zu `unzustellbar`) — jetzt explizit verboten
  - **Dialekt-Konsistenz** _(v2.2.0, Standard-Fallback v2.7.11)_: Ist Sprache B Englisch und die Zielliste hat einen `speech_lang_b`-Code (z.B. `en-GB` vs. `en-US`) gesetzt, müssen Schreibweise und Wortwahl des Begriffs sowie des Beispielsatzes zu diesem Dialekt passen (z.B. `en-GB` → "colour", "lorry", "flat"; `en-US` → "color", "truck", "apartment") — diese Listen-Definition hat Vorrang vor allem anderen. Ist **kein** `speech_lang_b` gesetzt, gilt als Standard **britisches Englisch (en-GB)**, nicht US-Englisch, ausser der User verlangt im Gespräch ausdrücklich einen anderen Dialekt — behebt das wiederkehrende Fehlerbild, dass der Agent ohne explizite Vorgabe US-Begriffe verwendete. Wie bei den übrigen Feld-Regeln reine Agent-Anweisung, keine serverseitige Validierung
  - **Lautschrift** _(v2.4.0, nicht-rhotische Regel v2.6.1)_: Hat die Zielliste ein `speech_lang_b`, soll `phonetik_b` mit vereinfachter Lautschrift befüllt werden (Silben mit Bindestrich getrennt, betonte Silbe in GROSSBUCHSTABEN, keine IPA-Sonderzeichen, z.B. `toh-ken-eye-ZAY-shun`) — passend zum Dialekt der Liste. Hat die Liste kein `speech_lang_b`, bleibt `phonetik_b` leer.
    Bei **nicht-rhotischen** Dialekten (`en-GB`, `en-AU`, `en-NZ`, `en-ZA`): "r" nach Vokal vor Konsonant oder am Wortende wird **nicht** mitgeschrieben — `-er`/`-or` wird zu `-uh`/`aw` (z.B. `thunder` → `THUN-duh`, `forecast` → `FAW-kahst`, `storm` → `stawm`). "r" nur schreiben wenn direkt ein Vokal folgt (Silbenanfang wie `rain` → `rayn`, oder verbindendes R zwischen Wörtern wie `for a` → `fer uh`). Bei **rhotischen** Dialekten (z.B. `en-US`) wird "r" normal geschrieben.
    Diese detaillierten Regeln (nicht-rhotisch etc.) gelten für **Englisch als Sprache B** — bei anderen Zielsprachen soll der Agent sinngemäss eine vereinfachte, zur jeweiligen Zielsprache passende Lautschrift verwenden (keine ausformulierten Detailregeln dafür hinterlegt) _(v3.2.37)_.
  - **Muttersprache für die Lautschrift-Lesekonventionen** _(v3.2.29, Standardannahme v3.2.37)_: `phonetik_b` muss in den Lesekonventionen der Muttersprache der lernenden Person geschrieben sein, damit sie die Aussprache intuitiv lesen kann. Standardannahme: Muttersprache = **Sprache A der Liste** — der Agent fragt nicht bei jeder Liste pauschal nach, sondern nur wenn diese Annahme keinen Sinn ergibt (z.B. beide Sprachen der Liste sind für die Person fremd) oder der User im Gespräch widerspricht.
- Karten werden nur in `cards`-Tabelle eingefügt — **kein `card_progress`-Eintrag** (lazy-init beim nächsten Leitner-Session-Start)
- Duplikatprüfung: exakter Vergleich (case-insensitive, getrimmt) auf `word_a + word_b` innerhalb der Ziel-Liste
- Duplikat + `force = false`: Karte wird nicht eingefügt, Warnung mit gefundener Karte zurückgegeben
- Duplikat + `force = true`: Karte wird trotzdem eingefügt
- Limits: max. 50 Karten/Aufruf, Begriff max. 500 Zeichen, Beschreibung max. 1000 Zeichen
- Antwort: `{ summary, list: { id, name }, results: [{ index, status, card, message? }] }`
  - `status`: `inserted` / `duplicate` / `error`

**`list_cards(list_id)`** _(v2.6.0)_ — Pflichtfeld: `list_id` (integer)
- Gibt Listen-Metadaten plus alle bestehenden Karten zurück: `{ list: { id, name, language_a, language_b, speech_lang_b }, cards: [{ card_id, sprache_a_begriff, sprache_b_begriff, beschreibung_a, beschreibung_b, phonetik_b }] }`
- Dient zum Prüfen bestehender Karten (z.B. Schreibweise, fehlende Lautschrift) vor gezielten `update_card`-Aufrufen — reines Lese-Tool, keine Änderung

**`update_card(card_id, ...)`** _(v2.6.0)_ — Pflichtfeld: `card_id` (integer)
- Ändert gezielt einzelne Felder einer bestehenden Karte: `sprache_a_begriff?`, `sprache_b_begriff?`, `beschreibung_a?`, `beschreibung_b?`, `phonetik_b?` — nur übergebene Felder werden geändert, alle anderen bleiben unverändert
- `sprache_a_begriff`/`sprache_b_begriff` dürfen, falls angegeben, nicht leer sein — `beschreibung_a/b`/`phonetik_b` können mit leerem String geleert werden
- Gleiche Feld-Regeln wie `add_cards` (Dialekt-Konsistenz, Lautschrift-Stil), gleiche Zeichenlimits
- **Agent-Pflicht:** vor dem Aufruf dem User pro Karte zeigen was sich ändert (alt → neu) und Bestätigung abwarten — niemals `list_cards`-Ergebnisse ungefragt automatisch mit `update_card` ändern
- Antwort: `{ summary, card: { card_id, sprache_a_begriff, sprache_b_begriff, beschreibung_a, beschreibung_b, phonetik_b } }` (Werte nach der Änderung)

### Sicherheit
- Prepared Statements für alle DB-Zugriffe
- Keine PHP-Stacktraces nach aussen (generische Fehlermeldungen)
- Input-Validierung: Pflichtfelder, Typ, Längen, max. Karten-Anzahl
- Logging: `mcp.log` (gitignored via `*.log`) — Zeitstempel, Umgebung, Methode, Tool, Argumente. Kein Token im Log. Datei liegt im Web-Root, ist aber per `.htaccess` gegen Direktabruf gesperrt _(v3.2.23 — vorher öffentlich lesbar)_
- **Der MCP-Token gewährt bewusst Vollzugriff auf alle Personen**: `list_persons`, `list_lists`, `add_cards` und `update_card` prüfen keine Personenzuordnung, da der Endpoint als Admin-/Agenten-Werkzeug gedacht ist. Der Token ist deshalb wie ein Admin-Passwort zu behandeln und ausschliesslich in `mcp-config.php` (gitignored) zu halten _(explizit dokumentiert v3.2.23)_

### Client-Einrichtung
- `.mcp.json.example` für Claude Code / VS Code (HTTP-Transport, Token-Header, Dev + Prod)
- `mcp-einrichtung.md`: Setup-Anleitung inkl. n8n AI Agent Node, ChatGPT (Query-Token), Claude Desktop (`mcp-remote`), Apache `.htaccess`-Workaround

### n8n vs. Claude Code — Duplikat-Verhalten
| Client | Duplikat-Reaktion |
|---|---|
| Claude Code | Warnung anzeigen, erst nach Bestätigung mit `force=true` erneut aufrufen |
| n8n | Sofort mit `force=true` erneut aufrufen (kein Mensch anwesend) |

---

## Version 1 — bewusst weggelassen

- Keine Gamification (Punkte, Badges)

---

## Offen / Später


