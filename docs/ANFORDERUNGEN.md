# Vokabeltrainer — Anforderungen

## Technologie

- **PHP** + **MySQL**
- **Bootstrap** für responsives Design (Desktop + iPhone)

### Mobile Darstellung _(überprüft und optimiert v3.2.25)_

Referenzgerät: iPhone 15 Pro Max (430 px Breite), gegengeprüft bei 375 px. Anspruch: **keine Seite darf horizontal scrollen** — geprüft über die tatsächliche `scrollWidth` im 430-px-Viewport, nicht nur nach Augenmass. Umgesetzte Anpassungen:

- **Navbar:** Unter 576 px steckt die Icon-Leiste hinter einem Hamburger-Menü (`navbar-toggler`/`navbar-collapse`, Bootstrap-Standardmechanik) statt umzubrechen — aufgeklappt erscheint sie als linksbündige, volle-Breite-Liste, jeder Eintrag zusätzlich mit sichtbarem Text-Label neben dem Icon. Ab 576 px unverändert als kompakte reine Icon-Leiste inline mit Tooltip, Personenname bleibt dort ausgeblendet _(v3.16.1, vorher brach die Icon-Leiste einfach in eine zweite Zeile um)_
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
- **`stats.php`** akzeptiert nur eigene `list_id` — eine fremde/unbekannte ID leitet auf die globale Ansicht (ohne `list_id`) um

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
8. **Wissenschaftlich Sprachen lernen** — Icon `bi-house`, führt zu `infos/wissen.php` _(v3.11.0, Verzeichnis seit v3.14.2)_
9. **Hilfe** — Icon `bi-info-lg`, führt zu `help.php` _(v2.8.0, unverändert)_

`learn.php` nutzt während einer Session dieselbe zentrale Navbar, nur mit gesetztem `$abort_url` (dadurch erscheint das Abbruch-Icon an erster Stelle statt des Logouts). `drill.php` rendert während einer laufenden Session weiterhin eine eigene, abweichende Navbar, weil dort zusätzlich Timer und "gemeistert"-Zähler angezeigt werden — das Abbruch-Icon steht dort aus Konsistenzgründen ebenfalls an erster Stelle, vor Timer und Zähler _(v3.2.26)_.

**Mobile Darstellung der Icon-Leiste** _(v3.16.1)_: Unter 576 px (`navbar-expand-sm`) steckt die gesamte Liste hinter einem Hamburger-Button (`navbar-toggler`/`.collapse.navbar-collapse#navbarIcons`, reine Bootstrap-Standardmechanik, kein eigenes JS). Aufgeklappt erscheint sie als linksbündige Liste über die volle Breite (`flex-column` statt `flex-row`), **jeder Eintrag zusätzlich mit sichtbarem Text-Label** neben dem Icon (`d-sm-none`-Span nach dem `<i>`-Icon — ab 576 px wieder ausgeblendet, dort bleibt es bei Icon + Tooltip wie bisher). Streak-Badge und Personenname bekommen `align-self-start`, damit sie beim Aufklappen nicht ebenfalls auf volle Breite gestreckt werden. Vorher brach die Icon-Leiste bei zu vielen Icons (z.B. Admin mit mehreren Personen: bis zu 7 Icons) einfach `flex-wrap` in eine zweite Zeile um.

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
- **Aktionsleiste pro Liste, sechs Icon-only-Buttons mit Tooltip** _(Icons/Reihenfolge v3.3.15, icon-only + Tooltip statt Text v3.3.17)_, in dieser Reihenfolge: **Liste bearbeiten** (`bi-pencil`, → `lists.php?edit=X`), **Karten bearbeiten** (`bi-pencil-square`, → `edit.php?list_id=X` — bewusst anderes Icon als "Liste bearbeiten", da mehrere Einträge statt einer einzelnen Eigenschaft bearbeitet werden), **Import** (`bi-upload`, → `import.php?list_id=X`), **Migrieren** (`bi-box-arrow-right`, öffnet das Migrations-Modal, ausgeblendet wenn keine weitere eigene Liste existiert), **Export** (`bi-download`, → `export.php?list_id=X`), **Löschen** (`bi-trash`, öffnet die Lösch-Bestätigung). Beschriftung erscheint nur noch als Hover-Tooltip (Bootstrap-Tooltip-Komponente, analog zur Kartenübersicht `edit.php`), nicht mehr als Text im Button — spart Platz in der Zeile. Tooltip-Init bewusst auf `.list-group-item [title]` eingegrenzt (nicht das ganze Dokument), damit die Navbar-Icons mit ihren eigenen, nativen `title`-Tooltips unangetastet bleiben; "Migrieren" behält `data-bs-toggle="modal"` fürs Öffnen des Modals und wird daher über `[title]` statt über `[data-bs-toggle="tooltip"]` erfasst. Zeile bricht bei schmalem Viewport um (`flex-wrap`).

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
| Listentyp (Wortliste / Aufgabe) | ✅ (nur beim Erstellen) |
| Sprache A | ✅ (bei Aufgabe fix, siehe unten) |
| Sprache B | ✅ (bei Aufgabe fix, siehe unten) |
| Öffentlich / Privat | ✅ |
| Aussprache-Sprachcode (Sprache B) | optional (bei Aufgabe nicht verfügbar) |

### Listentyp: Wortliste / Aufgabe _(v3.4.0)_
- Beim **Erstellen** einer Liste (`lists.php`) wählt man zusätzlich zu Name/Beschreibung den Listentyp: **Wortliste** (Standard, wie bisher — Sprache A/B frei eingebbar) oder **Aufgabe** (Mathe)
- Bei Typ **Aufgabe**: Sprache A/B werden **automatisch und fix** auf "Aufgabe"/"Ergebnis" gesetzt (derselbe Marker, den auch der Mathe-Generator setzt, siehe `is_math_list()`) — die entsprechenden Formularfelder werden ausgeblendet, ebenso das Aussprache-Sprachcode-Feld (bei Rechenaufgaben kein 🔊 sinnvoll)
- **Danach nicht mehr änderbar:** Ist eine Liste als Mathe-Liste erkannt (`language_a === 'Aufgabe'`) — egal ob über diesen Listentyp oder über den Mathe-Generator entstanden —, zeigt das Bearbeiten-Formular (`lists.php?edit=X`) Sprache A/B nur noch als gesperrte (disabled) Felder und blendet das Aussprache-Feld aus. Serverseitig werden abweichende POST-Werte für diese Felder ignoriert (bleiben auf den gespeicherten Werten), unabhängig vom übermittelten Formularinhalt — verhindert, dass die Lernrichtungs-Sperre bei Mathe-Listen (siehe "Lernrichtung bei Mathe-Listen") durch Umbenennen ausgehebelt wird
- Name, Beschreibung und Öffentlich/Privat bleiben bei Mathe-Listen normal editierbar
- Eine über den Listentyp "Aufgabe" erstellte Liste ist zunächst **leer** (keine automatisch generierten Karten wie beim Mathe-Generator) — Karten werden wie bei jeder Liste manuell über `edit.php` oder per CSV-Import hinzugefügt
- **Karten-Formular bei Mathe-Listen** _(v3.9.0)_: In `edit.php` blendet dieselbe `is_math_list()`-Prüfung (identische Grundlage wie bei der Lernrichtungs-Einschränkung in `learn.php`/`drill.php`) zusätzlich zum Lautschrift-Feld (weiterhin über `speech_lang_b`, bei Mathe-Listen nie gesetzt) auch **Beschreibung A/B und Tags** aus dem "Neue Karte hinzufügen"- und dem Bearbeiten-Formular aus — bei Rechenaufgaben ergeben beide keinen Sinn. Serverseitig ebenfalls erzwungen (nicht nur im Formular ausgeblendet): abweichende POST-Werte werden ignoriert, beim Bearbeiten bleibt ein zuvor gesetzter Wert unverändert (analog zur Lautschrift-Regel) statt durch ein leeres Formularfeld überschrieben zu werden

### Aussprache (Audio) _(v2.2.0)_
- Pro Liste kann ein **Sprachcode für die Aussprache** hinterlegt werden — ausschliesslich für **Sprache B** (die Fremdsprache), nicht für Sprache A
- Format: **BCP-47** (Sprache-Region, z.B. `en-GB`, `de-CH`, `fr-FR`) — reine Sprachcodes ohne Region (z.B. `en`) sind nicht zulässig
- Eingabe im "Liste erstellen"/"Bearbeiten"-Formular: Textfeld mit Autovervollständigung (HTML `<datalist>`) — Vorschläge aus einer kuratierten Liste gängiger Codes **plus** allen bereits in anderen Listen verwendeten Codes; eigene Werte sind trotzdem frei eintippbar
- **Validierung beim Speichern:** Sprachteil gegen ISO-639-1, Regionsteil gegen ISO-3166-1 geprüft (z.B. `en-UK` wird abgelehnt, da "UK" kein gültiger ISO-3166-1-Code ist — korrekt ist `en-GB`). Gross-/Kleinschreibung wird automatisch normalisiert (z.B. `EN-gb` → `en-GB`)
- Keine serverseitige Prüfung ob die Kombination Sprache+Region "sinnvoll" ist (z.B. `ja-DE` wäre technisch gültig, aber unüblich) — reine Formatprüfung
- **Wiedergabe:** Auf Leitner- und Drill-Karten erscheint ein 🔊-Button überall dort, wo der Begriff in Sprache B angezeigt wird (Frage- oder Antwortseite, je nach Lernrichtung) — nutzt die browsereigene **Web Speech API** (`speechSynthesis`), liest den vorhandenen Kartentext (Sprache B) mit dem hinterlegten Code vor
- **Bekannte Einschränkung auf iOS ohne Kopfhörer** _(versucht v3.6.0, v3.7.3, v3.8.0 — alle drei wieder entfernt)_: `speechSynthesis` spielt auf iPhones (getestet iPhone 15 Pro Max, aktuelles iOS) ohne angeschlossene Kopfhörer nicht hörbar über den Lautsprecher. Drei verschiedene rein clientseitige Ansätze wurden ausprobiert und vom User auf echter Hardware verworfen, da wirkungslos: (1) einmaliges stummes `<audio>`-Element, (2) stumme `<audio>`-Endlosschleife zur Aktivierung der Audio-Session-Kategorie "playback", (3) die neue `navigator.audioSession`-API. Kein weiterer Versuch ohne neue Erkenntnisse geplant — akzeptierte Plattformgrenze. Einzige recherchierte Alternative mit stärkerer Beleg-Lage (`getUserMedia({audio:true})`, erzwingt laut mehreren Quellen zuverlässig Lautsprecher-Routing) wurde bewusst nicht umgesetzt, da sie einen für eine Vokabel-App unpassenden Mikrofon-Berechtigungsdialog auslösen würde.
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
| Tags | optional |

### Lautschrift pro Karte _(v2.3.0)_
- Zusätzliches Feld `phonetic_b` pro Karte — manuell erfasste Lautschrift für den Begriff in **Sprache B**
- Eingabefeld erscheint beim Hinzufügen/Bearbeiten einer Karte (`edit.php`) **nur**, wenn die Liste einen Aussprache-Sprachcode (`speech_lang_b`) hinterlegt hat — bei Listen ohne Sprachzuordnung (z.B. Mathe-Listen) gibt es kein Lautschrift-Feld
- Anzeige: in der Kartenübersicht (`edit.php`) unter dem Begriff in Sprache B, sowie auf Leitner- und Drill-Karten unter dem Begriff in Sprache B (zusammen mit dem 🔊-Button)
- Ergänzt die Audio-Wiedergabe, ersetzt sie nicht — beide Mechanismen sind unabhängig nutzbar
- **CSV-Import/-Export** unterstützen `phonetic_b` als optionale 5. Spalte _(v2.4.0)_ — siehe Abschnitt "CSV-Format"
- **MCP `add_cards`** unterstützt `phonetik_b` als optionales Feld, mit derselben vereinfachten Lautschrift-Konvention wie manuell erfasst (Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Zeichen) — nur befüllen wenn die Liste ein `speech_lang_b` hat, reine Agent-Anweisung ohne serverseitige Validierung _(v2.4.0)_

### Tags pro Karte _(v3.9.0)_
- Freie, mit `#` eingegebene Stichworte pro Karte (z.B. `#Wetter #Business`) — Eingabefeld leerzeichengetrennt, mehrere Tags pro Karte möglich
- **Nur für Sprach-/Wortlisten** — bei Mathe-Listen (`is_math_list()`) ist das Tags-Feld ausgeblendet, siehe "Karten-Formular bei Mathe-Listen" im Abschnitt "Listentyp: Wortliste / Aufgabe"
- **Datenmodell:** eigene `tags`-Tabelle (Tags sind **pro Person eigenständig**, kein globaler Pool) + n:m-Verknüpfungstabelle `card_tags` — bewusst **kein** Freitextfeld auf `cards`, um Substring-Fehltreffer beim Filtern zu vermeiden (ein Freitextfeld mit `LIKE`-Suche würde z.B. `#Wetter` auch in `#Wetterbericht` fälschlich finden) und um Schreibweisen konsistent zu halten (sonst z.B. `wetter`/`Wetter`/`Wetter-Vokabeln` als drei getrennte Tags)
- Tag-Namen case-insensitiv dedupliziert (DB-Standard-Collation `utf8mb4_*_ci`) — die zuerst erfasste Schreibweise eines Tags wird bei Wiederverwendung beibehalten
- Verwaiste Tags (letzte Karte entfernt) bleiben bewusst in der `tags`-Tabelle bestehen statt automatisch gelöscht zu werden — leichter wiederverwendbar
- **Bearbeitbar über `edit.php`** (im selben Formular wie Begriff A/B, Beschreibung A/B, Lautschrift — Hinzufügen- und Inline-Bearbeiten-Formular) **und über MCP** (`add_cards`/`update_card`, seit v3.10.0 — siehe Abschnitt "MCP-Server")
- **Autovervollständigung beim Eintippen in `edit.php`** _(v3.10.0)_: schlägt bestehende Tags der Person vor (`get_person_tags()`, über sämtliche ihre Listen hinweg, nicht nur die aktuelle) — token-bewusstes Vanilla-JS (kein `<datalist>`, da das bei mehreren Tags im selben Feld nur den ersten sauber vorschlagen könnte), filtert nach dem Wort an der aktuellen Cursorposition, per Klick oder Pfeiltasten+Enter übernehmbar. Beugt Schreibweisen-Divergenz vor (`#Reise` vs. `#Reisen`), erzwingt aber nichts — eigene, neue Tags bleiben jederzeit frei eintippbar
- **Filter in `edit.php`:** anklickbare Tag-Leiste unter dem Status-/Fach-Filter, zeigt nur tatsächlich in der Liste vorkommende Tags. Gilt **zusätzlich** zum Status-/Fach-Filter (beide Dimensionen gleichzeitig), nicht als Ersatz
- **CSV-Export/-Import** unterstützen Tags als optionale, zusätzliche 6. Spalte (gleiches Format wie das Eingabefeld: leerzeichengetrennt mit `#`-Präfix) _(Import seit v3.16.0)_ — beim Import werden die Tags der jeweiligen Zeile auf der neu angelegten bzw. reaktivierten Karte gesetzt; ein einzelner zu langer Tag-Name lässt nur die Tags dieser einen Zeile leer statt den ganzen Import abzubrechen. Die Vorschau (Duplikat-Review) zeigt die Tags-Spalte mit an
- **Kopieren einer öffentlichen Liste** (`discover.php`): Tags der Quellkarten werden für die kopierende Person übernommen (als deren eigene Tags neu angelegt/verknüpft) — konsistent damit, dass auch der übrige Karteninhalt kopiert wird
- Anzeige als Badges (`#Tag`) unter dem Begriff in Sprache A, in der Kartenübersicht von `edit.php`

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

### Lernrichtung bei Mathe-Listen _(v3.3.25, Typ-Sperre + Sektion ausgeblendet v3.9.0)_
- Bei Mathe-Listen (siehe "Mathe-Generator") ist nur "Aufgabe → Ergebnis" sinnvoll — es gibt dafür serverseitig gar keinen anderen gültigen Wert
- **Die komplette "Lernrichtung"-Sektion (Leitner **und** Drill) ist ausgeblendet, sobald eine Mathe-Liste ausgewählt ist** — nicht nur die drei anderen Optionen (Ergebnis→Aufgabe, Gemischt, Zufall), auch "Aufgabe → Ergebnis" selbst wird nicht mehr als (eingefrorene) Auswahl angezeigt, da sie ohnehin die einzig mögliche ist. Bei einer Einzellisten-Vorauswahl (`?list_id=…`) serverseitig direkt per `style="display:none;"` auf der Sektion (`#direction-section`), bei Checkbox-Mehrfachauswahl per JS (`updateDirLabels()`), sobald ausschliesslich Mathe-Listen angehakt sind — verschwindet die Sektion wieder, sobald stattdessen eine Sprachliste gewählt wird
- **Mathe- und Sprachlisten sind seit v3.9.0 nicht mehr gleichzeitig auswählbar** (siehe "Listenauswahl: Typ-Sperre" unter "Decks mischen") — bei einer Mathe-Auswahl ist immer ausschliesslich "Aufgabe → Ergebnis" relevant
- Erkennung einer Mathe-Liste: `language_a === 'Aufgabe'` (Marker, den `math.php` beim Erstellen setzt) — kein eigenes DB-Feld, bewusst einfach gehalten, da `math.php` aktuell die einzige Stelle ist, die solche Listen erzeugt (`is_math_list()` in `includes/auth.php`)
- **Serverseitig ohnehin erzwungen, unabhängig vom Formular:** Ist die tatsächlich ausgewählte Listen-Kombination beim Session-Start ausschliesslich Mathe, wird die Richtung auf `a_to_b` gesetzt — egal ob überhaupt ein `direction`-Wert übermittelt wurde (die Sektion kann komplett fehlen) oder welcher

### Karten-Identität
- Jede Karte erhält beim Erstellen eine **stabile `card_id`** in der Datenbank
- Jede Person lernt nur mit **eigenen Karten** — entweder selbst erstellt oder als Kopie einer öffentlichen Liste
- Keine geteilten Karten zwischen Personen — kein Fortschrittsverlust durch fremde Änderungen

### Decks mischen
- Beim Sessionstart können **mehrere eigene Listen gleichzeitig** ausgewählt werden — aber nur innerhalb desselben Typs, siehe "Listenauswahl: Typ-Sperre" unten
- Lernfortschritt ist immer **persönlich** pro Person und pro `card_id`

### Setup-Seite: Mathe/Sprachen/Thema als Segmentbuttons _(v3.9.0, Segmentbuttons v3.9.1)_
- Auf der Leitner-/Drill-Setup-Seite (kein `list_id`-Preset) sind die möglichen Wege, eine Session zusammenzustellen, als **segmentierte Toggle-Buttons** (Bootstrap `btn-check`, gleiches Muster wie bei "Lernrichtung") oben in der Karte "Was lernen" dargestellt: **Mathe**, **Sprachen**, **Thema** — jeder Button nur sichtbar, wenn dafür überhaupt etwas existiert (z.B. kein "Mathe"-Button ohne Mathe-Listen). Existiert nur eine einzige Option insgesamt, entfällt die Button-Leiste komplett, der Inhalt steht direkt da
- Jeder Modus hat eine eigene Sektion (`.mode-pane`, nur eine gleichzeitig sichtbar) — Mathe- und Sprachlisten stehen dadurch nie in derselben Auswahl, ganz ohne eine JS-Sperre einzelner Checkboxen (Vorgängerlösung bis v3.9.0: gemeinsame Liste mit `disabled`-Checkboxen des jeweils anderen Typs — durch die getrennten Sektionen hinfällig)
- **Moduswechsel setzt die Auswahl zurück**: Umschalten auf einen anderen Button leert alle Listen-Checkboxen und die Tag-Auswahl — verhindert, dass eine unsichtbare, aber weiterhin angehakte Liste/ein Tag aus dem vorherigen Modus unbemerkt mit abgeschickt wird
- Default-Modus: ein per URL vorgewählter Tag (z.B. über den "Nochmal"-Link nach einer Themen-Session) > erster verfügbarer Nicht-Thema-Modus in der Reihenfolge Mathe → Sprachen > Thema als letzter Fallback
- Bei vorausgewählter Einzelliste (`?list_id=…`) entfällt die Mathe/Sprachen-Aufteilung (es gibt nur die eine, bereits feststehende Liste) — dort ggf. weiterhin "Liste" + "Thema" als zwei Modi, falls die Liste Tags hat
- Innerhalb eines Listen-Modus: Listen als volle, klickbare Zeilen (`list-group`) — anklickbar ist die ganze Zeile, nicht nur die kleine Checkbox
- Rein clientseitige Komfortfunktion (kein Sicherheitsmerkmal) — bei einer über ein manipuliertes Formular dennoch eingereichten gemischten Auswahl greift serverseitig nur die bestehende Regel "ausschliesslich Mathe erzwingt Aufgabe→Ergebnis" (siehe "Lernrichtung bei Mathe-Listen"), eine gemischte Kombination selbst wird nicht zurückgewiesen — betrifft nur die eigenen Karten der einreichenden Person, keine Datenintegrität anderer

### Bearbeitung im Browser
- Einzelne Einträge **hinzufügen**, **ändern**, **löschen**
- Einträge direkt als **archiviert** markieren (erscheinen nicht mehr im Training)
- Aktionsbuttons pro Karte als **Icon-only** (Bootstrap Icons) mit Tooltip: Ansehen (Auge), Bearbeiten (Stift), Archivieren (Archiv-Box), Reaktivieren (Pfeil zurück), Löschen (Mülleimer)
- **Direktlink pro Karte** _(v2.5.0, überarbeitet v2.5.2, Icon v2.5.3)_: erster Button in der Aktionsleiste (Augen-Symbol, Tooltip "Karte ansehen"), ein normaler Link (`edit.php?list_id=X&highlight=cardID`) — öffnet die Karte **als Lernkarte** in einem Modal (gleiche Flip-Kartenoptik wie Leitner/Drill: Vorderseite Sprache A, Tippen deckt Sprache B inkl. Lautschrift und 🔊-Button auf), nicht nur eine markierte Position in der Tabelle. Funktioniert unabhängig vom aktuell gewählten Filter, da die Karte direkt aus den geladenen Kartendaten der Liste gesucht wird.
- CSV Import / Export im Header: Icon + Text
- Container-Breite ohne eigenes `max-width` (analog `home.php`/`lists.php`) _(v2.3.0)_
- Beschreibungsfelder (A/B) als mehrzeilige `<textarea>` statt einzeiliger Inputs — Beschreibungen können ausführlich sein _(v2.3.0)_
- **Filter** über der Kartenliste: Status-Filter (Alle / Aktiv / Warteschlange / Archiviert) sowie zusätzlich, optisch getrennt, **Fach-Filter** (Fach 1–5) _(v2.7.8)_ — zeigt nur aktive Karten der gewählten Leitner-Box, jeweils mit Anzahl-Badge wie bei den Status-Filtern
- **"Neue Karte hinzufügen" klar vom Bearbeiten-Formular abgegrenzt** _(v3.3.16, analog zu `lists.php` v3.3.14)_: Klick auf das Stift-Icon "Bearbeiten" öffnet ein Inline-Formular direkt in der Tabellenzeile (gelb hervorgehoben, `table-warning`). Die Karte "Neue Karte hinzufügen" oben wird währenddessen ausgeblendet. Die Seite springt per URL-Fragment (`#edit-card-row`) direkt zur bearbeiteten Zeile und fokussiert deren erstes Feld; "Abbrechen" und "Speichern" springen ebenso per Fragment (`#card-row-<ID>`) zurück zur betroffenen Zeile in der Normalansicht, statt sich auf die vorher gemerkte Pixel-Scrollposition zu verlassen (die durch das Ein-/Ausblenden von "Neue Karte hinzufügen" nicht mehr zuverlässig auf die richtige Zeile zeigen würde). Der bestehende Pixel-Scroll-Restore (`sessionStorage`) bleibt für Aktionen ohne Layoutänderung (Archivieren, Reaktivieren, Löschen) unverändert bestehen.

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
a,b,desc_a,desc_b,phonetic_b,tags
Diagnose,diagnosis,medizinischer Begriff,"A conclusion, reached by examination",dy-ug-NOH-sis,#Medizin
Behandlung,treatment,,,,
```
- Trennzeichen: **Komma oder Semikolon** — App erkennt automatisch
- **Encoding: UTF-8**
- Erste Zeile ist die Kopfzeile (Sprachnamen oder beliebige Spaltenbezeichnungen) — wird beim Import immer übersprungen
- Felder mit Kommas/Semikolons müssen in **doppelte Anführungszeichen** gesetzt werden
- 5. Spalte `phonetic_b` (Lautschrift) ist **optional** — fehlt sie (nur 4 Spalten), bleibt das Feld leer; rückwärtskompatibel mit alten CSV-Dateien _(v2.4.0)_
- 6. Spalte `tags` (Tags) ist **optional** — fehlt sie (nur 4 oder 5 Spalten), bleibt die Karte ohne Tags; rückwärtskompatibel mit alten CSV-Dateien _(v3.16.0)_. Format identisch zum Tags-Eingabefeld: leerzeichengetrennt mit `#`-Präfix
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
- **Zusätzlich begrenzt auf das Restplatz-Kontingent der gewählten Kartenanzahl** (Bugfix 19.08.2026): war die Kartenanzahl z.B. exakt auf die Zahl der bereits fälligen Karten gesetzt, aktivierte das Tageslimit bis dahin trotzdem weitere Karten aus der Warteschlange — die dann fällig, aber vom festen Session-Limit nicht mehr erfasst wurden und unbeantwortet als "heute fällig" hängen blieben. `activate_daily_cards()` aktiviert seither höchstens `Kartenanzahl − bereits fällige Karten` neue Karten, nie mehr als die Session tatsächlich abholen kann.
- **Gilt pro Listen-Auswahl der jeweiligen Session, nicht global über den ganzen Account** — die Prüfung "wie viele wurden heute schon aktiviert" zählt nur Karten aus genau den Listen, die für die aktuelle Session ausgewählt sind. Lernt man zwei Listen in getrennten Sessions, hat jede ihr eigenes 10er-Kontingent (zusammen bis zu 20 neue Karten/Tag); wählt man beide gemeinsam in einer Session, teilen sie sich ein gemeinsames Kontingent von 10. Bewusst so belassen (Stand v3.3.20).
- Warteschlange zeigt wie viele Karten noch warten
- Beim Upload von 100 Karten → nur 10 sofort aktiv, 90 in Warteschlange
- **Verfügbarkeits-Hinweis auf der Leitner-Setup-Seite** _(v3.3.18, Formulierung vereinfacht v3.3.19, als Infobox v3.3.20, Tageslimit-Verbrauch ergänzt v3.3.21, nur bei tatsächlicher Drosselung sichtbar v3.3.22, unterscheidet Tageslimit vs. leere Warteschlange v3.3.24)_: Unter dem Feld "Kartenanzahl" steht als eigene Infobox (`alert alert-info`, wie andere Meldungen in der App), die den tatsächlich zutreffenden Engpass benennt — es gibt zwei unabhängige, sich gegenseitig ausschliessende Gründe, warum weniger als das Tageslimit aus der Warteschlange kommt:
  - **Warteschlange ist der Engpass** (weniger Karten in der Warteschlange als vom Tageslimit noch erlaubt wären): "In der Warteschlange dieser Liste ist nur noch N Karte(n) übrig." (bzw. "Die Warteschlange ist leer." bei 0) — das Tageslimit selbst wird in diesem Fall gar nicht erwähnt, da es nicht die Ursache ist.
  - **Tageslimit ist der Engpass** (Warteschlange hätte genug Karten, aber das Tageslimit erlaubt nicht mehr): "Pro Liste werden maximal N neue Karten pro Tag aus der Warteschlange aktiviert — heute wurden davon bereits A genutzt." (der "heute wurden davon bereits …"-Halbsatz nur wenn A > 0)
  - In beiden Fällen folgt: "Die Session enthält daher X Karten: Y heute fällig + Z neu aus der Warteschlange."
  - Welcher Fall zutrifft, wird über denselben Vergleich ermittelt, der auch die Zahl selbst bestimmt (`min(Warteschlange, verbleibendes Tageslimit)`) — keine separate Zusatzlogik, daher immer konsistent mit der angezeigten Zahl.

  **Die Box selbst erscheint nur, wenn die eingestellte Kartenanzahl grösser ist als die tatsächlich verfügbare Zahl (X)** — sind genug fällige/aktivierbare Karten vorhanden, um die gewünschte Kartenanzahl zu erreichen, bleibt die Box unsichtbar (`d-none`). Reagiert live auf Änderungen sowohl der Listenauswahl als auch der Kartenanzahl (inkl. der ±5-Buttons). Bei Checkbox-Mehrfachauswahl aktualisiert sich die Box automatisch beim An-/Abwählen (Summe über alle ausgewählten Listen, serverseitig pro Liste vorberechnet, keine Nachlade-Anfrage nötig). Das Tageslimit gilt dabei **pro Listen-Auswahl der jeweiligen Session**, nicht global über den ganzen Account — zwei Listen einzeln gelernt ergeben bis zu 2×N neue Karten am Tag, gemeinsam ausgewählt teilen sie sich ein Kontingent von N (bewusst so belassen). Rein informativ — die Mechanik (Tageslimit, Drosselung neuer Karten) selbst bleibt unverändert, siehe Abschnitt oben.

### Themen-Session (Tag-Cloud) _(v3.9.0)_
- Auf der Leitner-Setup-Seite steht eine **Tag-Cloud** als eigener Modus "Thema" neben Mathe/Sprachen zur Wahl (siehe "Setup-Seite: Mathe/Sprachen/Thema als Segmentbuttons") — alle Wege bestehen nebeneinander, keiner ersetzt die anderen dauerhaft, pro Session ist aber immer nur einer aktiv
- Nur **ein Tag pro Session** wählbar (Radio-Buttons, kein UND/ODER mehrerer Tags)
- Wird ein Tag gewählt, hat er **serverseitig Vorrang** vor einer evtl. zusätzlich angehakten Listenauswahl — die Session läuft dann **listenübergreifend** über alle eigenen Karten (aus aktiven Listen) mit diesem Tag, unabhängig davon aus welcher Liste sie stammen
- Tag-Cloud zeigt nur Tags, die auf mindestens einer Karte einer **aktiven** eigenen Liste vorkommen (`get_person_tags()` in `includes/tags.php`) — inaktive Listen stehen auch hier nicht zur Wahl, analog zur normalen Listenauswahl
- **Kontextabhängige Vorschläge:** Wird die Setup-Seite über eine bestimmte Liste aufgerufen (`?list_id=…`, z.B. über den "Leitner"/"Drill"-Button einer einzelnen Liste), zeigt die Tag-Cloud **nur die Tags dieser Liste** (`get_list_tags()`) statt aller Tags der Person — ohne `list_id` (allgemeiner Einstieg über `learn.php`/`drill.php`) werden weiterhin alle Tags über sämtliche eigenen aktiven Listen hinweg angeboten. Betrifft nur die angebotene Auswahl — einmal gewählt, läuft die Session immer listenübergreifend über alle eigenen Karten mit diesem Tag, unabhängig vom Einstiegskontext
- **Kein Vermischen mit der Listenauswahl mehr nötig** _(seit Segmentbuttons v3.9.1)_: Thema ist ein eigener, exklusiver Modus neben Mathe/Sprachen (siehe "Setup-Seite: Mathe/Sprachen/Thema als Segmentbuttons") — die Tag-Cloud zeigt deshalb immer einfach `$available_tags` wie server-seitig ermittelt, ohne zusätzliche client-seitige Einschränkung nach angehakten Listen (diese Mechanik aus v3.9.0, `get_person_tags_by_list()`, entfiel mit der Trennung in eigene Modi als gegenstandslos)
- **Tageslimit im Tag-Modus überschreibbar, mit expliziter Bestätigung — ohne eigenes Zahlenfeld** _(v3.9.0, auf bestehendes Kartenanzahl-Feld vereinfacht v3.9.2)_: Das Override sitzt bewusst **nicht** in der Tag-Cloud-Sektion (die bleibt rein die Themenauswahl), sondern erscheint — sobald ein Thema gewählt ist — unterhalb von "Kartenanzahl" als eigener Hinweisblock mit **nur einer Checkbox** ("Ich bin einverstanden, dass heute mehr als N neue Karten geladen werden (bis zur oben eingestellten Kartenanzahl)"). Kein separates "Wie viele?"-Feld — die Menge wird direkt vom ohnehin vorhandenen "Kartenanzahl"-Feld übernommen, ein zweites, redundantes Zahlenfeld entfällt dadurch. Serverseitig: ist die Checkbox (`daily_limit_override`) gesetzt, wird `$daily_limit = $card_limit` (Wert aus "Kartenanzahl") verwendet, sonst bleibt es beim festen `DAILY_CARD_LIMIT`-Default — ohne die Checkbox wird das Feld "Kartenanzahl" für die Tageslimit-Frage ignoriert, auch bei einem manipulierten Formular. **Gilt nur im Tag-Modus** — die normale listenbasierte Session hat weiterhin ein festes, nicht überschreibbares Tageslimit
- Das Tageslimit gilt im Tag-Modus als **ein gemeinsamer Topf über alle beteiligten Listen** (nicht pro Liste wie im normalen Listen-Modus) — verhindert, dass ein Thema mit vielen beteiligten Listen das Tageslimit faktisch aushebelt. Kann dazu führen, dass eine grosse Themen-Session sich über mehrere Tage aufbaut statt an Tag 1 vollständig verfügbar zu sein
- `last_used_at` wird für **alle** Listen aktualisiert, die mindestens eine Karte mit dem gewählten Tag haben — unabhängig davon, ob an diesem Tag tatsächlich eine Karte aus jeder einzelnen Liste gezogen wurde (konsistent mit dem bestehenden Mehrfach-Listen-Verhalten bei Checkbox-Auswahl)
- **"Neue Session"-Button nach einer Themen-Session** verlinkt wieder auf dasselbe Thema (`learn.php?tag=…`), analog zum bestehenden `?list_id=…` bei einer Einzellisten-Session
- Implementiert über einen optionalen `card_ids_filter`-Parameter in `activate_daily_cards()`/`build_leitner_queue()` (Filter-Fragment `card_id_filter_sql()` in `includes/tags.php`, gemeinsam mit `drill.php` genutzt) — schränkt zusätzlich zur Listen-Zugehörigkeit auf die konkret getaggten Karten ein, sonst identische Scheduling-Logik wie im normalen Listen-Modus

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

### Themen-Session (Tag-Cloud) _(v3.9.0)_
- Gleiches Prinzip wie im Leitner-System (siehe dort) — ergänzt die Listenauswahl auf der Drill-Setup-Seite um eine Tag-Cloud, nur ein Tag pro Session wählbar, Tag hat serverseitig Vorrang vor einer zusätzlich angehakten Listenauswahl
- Session läuft listenübergreifend über alle eigenen Karten (aus aktiven Listen) mit diesem Tag — inkl. vorgemerkter Karten: eine "für Drill vorgemerkte" Karte **ohne** den gewählten Tag ist in dieser Themen-Session ebenfalls aussen vor (gleiche strikte Tag-Einschränkung wie für alle anderen Karten, keine Ausnahme für Pins)
- **Kein Tageslimit-Pendant** — anders als beim Leitner-System gibt es im Drill-Modus keine "neue Karten pro Tag"-Bremse, die im Tag-Modus überschreibbar wäre; die Session-Länge (Timer) begrenzt die Kartenzahl bereits ausreichend (siehe "Aktiver Pool an Session-Länge gekoppelt")
- `last_used_at` wird für alle Listen aktualisiert, die mindestens eine Karte mit dem gewählten Tag haben (analog Leitner-System)
- "Erneut starten" nach einer Themen-Session verlinkt wieder auf dasselbe Thema (`drill.php?tag=…`)
- Implementiert über einen optionalen `card_ids_filter`-Parameter in `load_drill_pool()` (geteilte Filter-Logik mit `learn.php` über `card_id_filter_sql()` in `includes/tags.php`)

### Ablauf (eine Karte nach der anderen)
1. Karte wird angezeigt (nur Vorderseite / Frage, gemäss gewählter Lernrichtung)
2. User denkt nach, tippt/klickt auf die Karte → Karte dreht sich um (Flip-Animation)
3. Antwort erscheint, darunter: Button **"Gewusst"** (grün) und **"Musste nachdenken"** (orange)
4. User bewertet → nächste Karte erscheint sofort

### Karten-Reihenfolge (9:1-Verhältnis)
- Bekannte Karten (`drill_mastery >= 1`) bilden einen Pool, aus dem **zufällig** gezogen wird _(v3.8.0; vorher strikt reihum/FIFO)_ — dieselbe Karte erscheint dabei nie zweimal direkt hintereinander (`pick_random_known_card()` schliesst die gerade gezeigte Karte von der Auswahl aus, sofern eine Alternative existiert)
- Neue/unbekannte Karten werden einzeln eingeführt: nach jeweils 9 bekannten Karten erscheint 1 neue
- Neu eingeführte Karten wandern in den Pool und werden ab dann gemeinsam zufällig wiederholt
- Das Mischen passiert im Hintergrund — der User sieht nur eine Karte nach der anderen
- **Hintergrund der Umstellung auf Zufallsauswahl** _(v3.8.0)_: Bei striktem Reihum bekamen alle Karten eines Decks exakt gleich oft die Chance auf eine richtige Antwort in Folge — sie erreichten die Mastery-Schwelle dadurch fast gleichzeitig ("Batch-Meistern", z.B. alle 5 Karten eines Decks kurz hintereinander), wonach `replenish_active_pool()` das ganze Deck auf einen Schlag aus der Reserve ersetzte. Zusätzlich war die Reihenfolge innerhalb einer Session komplett vorhersehbar. Betrifft nur die Auswahl-Reihenfolge — Einführungstempo neuer Karten (9:1) und Vormerkungs-Priorität bleiben unverändert.

### Aktiver Pool an Session-Länge gekoppelt _(v3.7.0)_
- **Problem vorher:** Beim Sessionstart wurden **alle** infrage kommenden Karten der ausgewählten Liste(n) auf einmal in die Rotation geladen, unabhängig von der gewählten Timer-Dauer. Bei einer grossen Liste verdünnte sich dadurch die Wiederholung jeder einzelnen Karte so stark (Round-Robin über eine grosse Kartenmenge), dass die Mastery-Schwelle (Standard 3× hintereinander richtig) in einer kurzen Session praktisch nie erreicht wurde — selbst bei vielen richtig beantworteten Karten insgesamt.
- **Lösung:** Der aktive Pool (bekannte + neue Karten, ohne vorgemerkte) wird beim Sessionstart auf `max(DRILL_MIN_ACTIVE_CARDS, Timer-Minuten × DRILL_CARDS_PER_MINUTE)` Karten begrenzt (`limit_active_pool()` in `drill.php`). Bekannte Karten (bereits mit Fortschritt) werden dabei bevorzugt behalten, neue Karten füllen den verbleibenden Platz. Überzählige Karten werden nicht verworfen, sondern als Reserve im Session-State gehalten (`reserve_known`/`reserve_new`).
- `DRILL_CARDS_PER_MINUTE` (Default 1.0, Einstellungen → Drill-Modus "Aktive Karten pro Minute", Bereich 0.2–10, Dezimalwert) ist konfigurierbar — höher bedeutet mehr Abwechslung pro Session, niedriger bedeutet häufigere Wiederholung derselben Karte und damit eine realistischere Chance, sie innerhalb einer Session zu meistern. `DRILL_MIN_ACTIVE_CARDS` (fix 5, nicht in den Einstellungen) verhindert ein zu kleines Deck bei sehr kurzen Sessions.
- **Nachschub aus der Reserve** _(v3.7.1)_: Sinkt der aktive Pool während der Session unter die ursprüngliche Zielgrösse (weil eine Karte gemeistert oder als "zu schwer" markiert wurde), füllt `replenish_active_pool()` ihn aus der Reserve wieder auf — bekannte Reserve-Karten zuerst, analog zur Priorisierung beim Sessionstart. Ohne das würde eine schnelle/genaue Person ihr bewusst klein gehaltenes Deck vor Ablauf des Timers komplett leeren und die Session bräche vorzeitig ab, obwohl die Liste noch längst nicht ausgeschöpft ist — der Timer entscheidet weiterhin allein über das Sessionende. Ist auch die Reserve leer, schrumpft der Pool einfach weiter (dann ist tatsächlich die komplette Liste in Bearbeitung). Kein Datenverlust in keinem Fall: nicht in dieser Session berücksichtigte Karten werden beim nächsten Sessionstart über `load_drill_pool()` neu geladen.
- Vorgemerkte Karten (`pool_pinned`) sind von Begrenzung und Nachschub ausgenommen — sie laufen weiterhin über ihre eigene Priorisierung (siehe "Manuelle Vormerkung für Drill").

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
- **Direkt während einer laufenden Leitner- oder Drill-Session** _(v3.2.41, per Fetch statt
  Seiten-Reload seit v3.3.10; in `drill.php` nachgezogen v3.6.0 — vorher dort nur rein anzeigend,
  Vormerkung liess sich innerhalb einer laufenden Drill-Session nicht aufheben)_: derselbe runde
  Pin-Button oben links auf der Lernkarte in `learn.php` bzw. `drill.php`, ausgefüllt wenn
  vorgemerkt. Klick schaltet die Vormerkung sofort um, ohne die laufende Session zu unterbrechen
  (Queue/Fortschritt/Statistik bleiben unverändert, dieselbe Karte wird danach weiterhin gezeigt).
  Läuft über `fetch()` statt eines normalen Form-Submits, damit ein bereits aufgedeckter
  Kartenstatus (rein clientseitig über `flipCard()`) beim Umschalten **nicht** zurückgesetzt wird —
  ein normaler Seiten-Reload hätte die sichtbare Übersetzung sonst wieder versteckt (Bugfix
  v3.3.10, analog in `drill.php` seit v3.6.0). In `drill.php` wird eine neu vorgemerkte Karte dabei
  zusätzlich serverseitig aus dem laufenden `pool_known`/`pool_new` der Session entfernt (jede Karte
  gehört exklusiv zu einem Pool), eine entfernte Vormerkung bleibt für den Rest der Session ausserhalb
  aller Pools und taucht erst in der nächsten Session wieder auf.

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
| Drill | Aktive Karten pro Minute | `DRILL_CARDS_PER_MINUTE` | Wie viele Karten pro Timer-Minute gleichzeitig in die Rotation genommen werden _(v3.7.0)_ | 0.2–10 (Dezimalwert) |

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
  - Leitner: Fach vorher→nachher, Fälligkeit vorher→nachher; bei Übersprungen "nichts geändert"; bei 2. Versuch entsprechend vermerkt. Beim Aufsteigen (1. Versuch richtig) zusätzliche Zeile "Intervall Fach X: Y Tage, Basis: Z" _(v3.3.23)_ — zeigt explizit, welches Intervall aus `LEITNER_INTERVALS` nachgeschlagen und von welchem Datum aus gerechnet wurde, damit sich `next_due_date` (Basis + Intervall) direkt am Bildschirm nachrechnen lässt statt den Code lesen zu müssen. Nur bei tatsächlichem Aufstieg gezeigt (2. Versuch nutzt ein festes +1-Tag-Intervall ohne Tabellen-Lookup, daher keine Zusatzzeile nötig).
  - Drill: bei besonderen Ereignissen (gemeistert, als zu schwer markiert, Vormerkung erreicht) eine Antwort-Zeile ("gewusst"/"musste nachdenken") plus eine hervorgehobene Zeile mit Fach-/Zähler-Änderung; sonst **beide** Session-Zähler gleichzeitig, je auf eigener Zeile — "Mastery-Zähler X/`DRILL_MASTERY_THRESHOLD`" (Folge richtiger Antworten, setzt bei falscher Antwort auf 0 zurück) und "Zu-schwer-Zähler X/`DRILL_TOO_HARD_LIMIT`" (Gesamtzahl falscher Antworten in dieser Session, wird durch richtige Antworten dazwischen NICHT zurückgesetzt) _(v3.2.42)_. Statt einer eigenständigen Antwort-Zeile trägt **nur der Zähler, der durch diese Antwort hochgezählt hat**, einen Suffix _(v3.7.8, nur am erhöhten Zähler seit v3.7.9)_: bei richtiger Antwort "Mastery-Zähler 1/3 **- gewusst**", bei falscher "Zu-schwer-Zähler 1/5 **- nicht gewusst**" — der jeweils andere Zähler steht ohne Suffix da.
  - Drill, feste zweite Zeile in jeder Variante _(v3.7.2, Format überarbeitet v3.7.5, Total-Definition korrigiert v3.7.6, Reihenfolge/Pin-Zusatz v3.7.7)_: **"Karten der Session: T total · D im Deck · G gemeistert · P pausiert · R Reserve [· (mit Pin: N)]"** — macht Pool-Begrenzung und Reserve-Nachschub direkt beim Testen sichtbar, ohne die DB inspizieren zu müssen. Aufbau (`debug_deck_line()` in `drill.php`):
    - **T (total)** = D+G+P — die Karten, die diese Session **tatsächlich in der Rotation hatte**: startet bei der Deckgrösse (abhängig vom gewählten Timer, 5 Min. → 5, 10 Min. → 10 bei Standardwerten) und **wächst** mit jeder gemeisterten/pausierten Karte, für die eine neue aus der Reserve nachrückt. Bewusst **ohne** die Reserve _(Korrektur v3.7.6: in v3.7.5 zählte die Reserve mit, wodurch T bei grossen Listen einfach die Listengrösse zeigte — statisch, Timer-unabhängig und ohne Aussagekraft für die Session)_.
    - **D (im Deck)** = aktuell rotierende Karten (`pool_known` + `pool_new`), erfasst **nach** einem eventuellen Nachschub aus der Reserve. Entspricht der Zielgrösse `max(DRILL_MIN_ACTIVE_CARDS, Minuten × DRILL_CARDS_PER_MINUTE)`, solange die Reserve nicht leer ist; danach schrumpft D mit jeder gemeisterten/pausierten Karte.
    - **G (gemeistert)** / **P (pausiert)** = Karten, die das Deck in dieser Session verlassen haben (`DRILL_MASTERY_THRESHOLD`× richtig in Folge → Leitner bzw. `DRILL_TOO_HARD_LIMIT`× "musste nachdenken" → bis morgen gesperrt).
    - **R (Reserve)** = wartende Karten (`reserve_known` + `reserve_new`), sinkt mit jedem Nachschub ins Deck. Steht bewusst als eigene Zahl am Ende der Aufzählung (nicht in T eingerechnet) — zeigt, wie viel Vorrat die Liste noch hat.
    - **"(mit Pin: N)"** steht als eigenständiger Zusatz ganz am Schluss und erscheint nur, wenn vorgemerkte Karten in der Session sind — sie laufen ausserhalb von Begrenzung, Nachschub und T-Rechnung (eigener Topf, rotiert immer aktiv mit, siehe "Manuelle Vormerkung für Drill"); ein unerwartet grosses Deck lässt sich damit sofort auf Vormerkungen zurückführen.
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

Statistik startet ohne `list_id` mit der globalen Gesamtstatistik über alle eigenen Listen (Button "Alle Listen") _(v3.6.0; zuvor Auto-Redirect auf die erste eigene Liste, kein globaler Modus)_. Auswahl per Button oben — neben "Alle Listen" nur **aktive** Listen (`is_active = 1`), inaktive Listen erscheinen dort nicht zur Auswahl _(v3.2.13)_. Die globale Ansicht berücksichtigt Leitner- und Drill-Übersicht (Kartenanzahl pro Fach, Richtig/Falsch- bzw. Gewusst-Quote) über alle eigenen Listen; Lernaktivität (Streak, Heatmap) war schon zuvor immer global, unabhängig vom Filter.

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
- **Struktur ausgerichtet auf zwei Personas** _(v3.5.0, siehe `docs/Personas.md`)_ — "der Einsteiger" (will rasch die wichtigsten Grundfunktionen sehen) und "die Geübte" (will gezielt zu einem bestimmten, tiefer gehenden Thema springen). Aufbau von oben nach unten:
  1. Kurzer Einleitungstext (ein bis zwei Sätze, kein eigener Accordion-Abschnitt mehr)
  2. **Schnellstart-Kachel** "Neu hier? So geht's los" — immer sichtbar, kein Aufklappen nötig: 4 nummerierte Schritte (Anmelden, Liste wählen, Wörter ergänzen, Losdrillen) plus Buttons zu `home.php` und `lists.php`
  3. **Sprungmarken-Navigation**: zwei Kästchen "Grundlagen" und "Fortgeschritten & mehr entdecken" mit anklickbaren Themen-Links zu allen Accordion-Abschnitten weiter unten — ein Klick öffnet den Ziel-Abschnitt (falls eingeklappt) und scrollt automatisch dorthin (`shown.bs.collapse`-Event abwarten, da ein reiner Anker-Link bei eingeklapptem `display:none`-Ziel nicht zuverlässig scrollt)
  4. Zwei separat überschriebene Accordion-Gruppen: **Grundlagen** (Login & Person, Wortlisten erstellen & verwalten, Wörter hinzufügen, Aufbau einer Lernkarte, Leitner-Modus, Drill-Modus) und **Fortgeschritten & mehr entdecken** (Aussprache: Audio & Lautschrift, Karten gezielt für Drill vormerken, Listen migrieren & organisieren, CSV-Import & KI-Prompt im Detail, Statistik & Heatmap im Detail, plus Admin-only: Einstellungen & Benutzerverwaltung, MCP-Server)
  - Alle Abschnitte sind beim Laden eingeklappt und lassen sich **unabhängig voneinander** öffnen — bewusst **kein** gemeinsames `data-bs-parent` mehr (anders als bis v3.4.1): mehrere Abschnitte können gleichzeitig offen bleiben, praktisch beim Vergleichen mehrerer Themen
- **Aufbau einer Lernkarte** _(v3.3.2)_: Abschnitt innerhalb "Grundlagen" zwischen "Wörter hinzufügen" und "Leitner-Modus", da die Lernkarte in beiden Lernmodi identisch aussieht. Bebildert (Screenshot `img/learner-karte.png`) erklärt: oberer Teil = Frageseite, unterer Teil = per Antippen aufgedeckte Antwortseite (Sprachname, Begriff, optionale Beschreibung je Seite), Lautschrift + 🔊-Ausspracheknopf gehören immer zur fremdsprachigen Seite (Sprache B) unabhängig davon ob diese oben oder unten erscheint, Pin-Symbol oben links für "Für Drill vormerken".
- **Karten gezielt für Drill vormerken** _(v3.5.0, vorher auf Leitner-/Drill-Abschnitt verteilt; Umschalt-Stellen-Text v3.6.0 von zwei auf drei aktualisiert)_: eigener Abschnitt innerhalb "Fortgeschritten" bündelt die komplette Erklärung — alle Umschalt-Stellen (Kartenübersicht, laufende Leitner-Session, laufende Drill-Session), Priorität aus den Einstellungen, automatisches Entfernen der Vormerkung bei `DRILL_MASTERY_THRESHOLD`× richtiger Antwort in Folge, sowie neu ergänzt: eine vorgemerkte Karte wird bei "musste nachdenken" **nicht** als "zu schwer für heute" pausiert (anders als eine normale Karte) — dieser Unterschied fehlte vorher auf der Hilfeseite.
- **Listen migrieren & organisieren** _(v3.5.0)_: eigener Abschnitt innerhalb "Fortgeschritten", bündelt das bereits dokumentierte Migrieren (inkl. Hinweis: keine Duplikat-Prüfung dabei, Button nur bei ≥2 eigenen Listen sichtbar) mit dem bisher auf der Hilfeseite gar nicht erklärten Aktiv-/Inaktiv-Setzen einer Liste (`lists.is_active`, siehe Abschnitt "Startseite") — inkl. Hinweis, dass der Umschalt-Button dafür auf der **Startseite** sitzt, nicht unter "Meine Listen" (wo der Migrieren-Button ist).
- **CSV-Import & KI-Prompt im Detail** _(v3.5.0)_: eigener Abschnitt innerhalb "Fortgeschritten", ergänzt gegenüber der bisherigen Kurzfassung: dass der KI-Prompt ohne hinterlegten Aussprache-Dialekt die KI anweist nachzufragen statt die Lautschrift wegzulassen, dass bei Englisch ohne Dialekt-Angabe britisches Englisch der Standard ist, sowie die drei Optionen beim Reimport einer bereits archivierten Karte.
- **Admin-only Abschnitte** _(v3.2.16)_: "Für Admins: Einstellungen & Benutzerverwaltung" und "Für Technik-Fans: Karten per KI-Agent verwalten" sind nur für Personen mit Admin-Status sichtbar (`$_SESSION['is_admin']`) — dadurch **13 Abschnitte für Admins, 11 für alle anderen** _(Stand v3.5.0; vorher 11/9 bis v3.4.1 — Zunahme durch die drei neuen/aufgeteilten Fortgeschritten-Abschnitte, nicht durch den Wegfall der "Einleitung" als Abschnitt)_
- **Drill-Modus-Erklärung ergänzt** _(v3.2.16)_: Hinweis, dass bei einer neuen Liste dieselbe Karte am Anfang mehrmals kurz hintereinander gezeigt werden kann (beabsichtigt, dient der Einprägung, entspannt sich je mehr Karten im Umlauf sind) — sowie Klarstellung, dass es **keine** Leitner-Fach-Obergrenze für den Drill-Modus gibt (Karten aus jedem Fach können weiterhin im Drill auftauchen, solange nicht archiviert)
- **Aussprache-Erklärung ergänzt** _(v3.2.16)_: Hinweis, dass Stimme/Klang vom Gerät bzw. Betriebssystem kommen, nicht von der App selbst — kann daher mechanisch klingen, hilft aber bei der Betonung
- **Live-Ausprobieren im Aussprache-Abschnitt** _(v3.3.3, Text/Sprache angepasst v3.3.4)_: Button nutzt dieselbe `speakWord()`-Logik und denselben Button-/Icon-Stil (`bi-volume-up-fill`) wie die 🔊-Knöpfe auf Leitner-/Drill-Karten, zusätzlich mit Text "Klick mich" beschriftet. Spricht den Beispieltext "Can you hear me?" auf `en-GB`. Begriff und verwendete Sprache stehen direkt daneben als Text.
- **Weitere Screenshots** _(v3.3.8)_ — gleicher Bildstil wie `learner-karte.png` (zentriert, `img-fluid rounded border shadow-sm`, kurze Bildunterschrift):
  - `img/neues-wort.png` im Abschnitt "Wörter hinzufügen": zeigt das Formular für "Manuell" auf der Kartenübersicht.
  - `img/neue-session-leitner.png` im Abschnitt "Leitner-Modus": zeigt die Konfigurationsseite (Liste, Lernrichtung, Kartenanzahl) vor dem Start.
  - `img/neue-session-drill.png` im Abschnitt "Drill-Modus": zeigt die Konfigurationsseite (Liste, Lernrichtung, Timer) vor dem Start. Direkt darunter ein Tipp: der Timer sollte **mindestens 5 Minuten** betragen, sonst reicht die Zeit meist nicht, um dieselbe Karte oft genug zu wiederholen (Begründung: `DRILL_MASTERY_THRESHOLD` verlangt 3× richtig hintereinander — bei sehr kurzer Laufzeit kommt kaum eine Karte auf genug Wiederholungen).
  - `img/drill-timer.png` im Abschnitt "Drill-Modus": zeigt Ausschnitt aus der Navbar einer laufenden Session (Abbrechen-Symbol, Countdown-Timer) — bisher unbebilderter Lücke in der Hilfeseite, ergänzt mit kurzem Begleittext. Feste Breite `105px` statt `img-fluid`-Skalierung nach oben _(v3.3.9)_ — das Original ist nur 210×94px, entspricht in etwa der tatsächlichen Grösse dieses Navbar-Ausschnitts auf der Webseite selbst, statt als grosser Screenshot zu wirken.
- **Mobile Heatmap-Anpassung im Statistik-Abschnitt erwähnt** _(v3.3.9)_: Zusätzlicher Aufzählungspunkt bei "Statistik & Heatmap im Detail" _(Abschnitt hiess bis v3.4.1 "Statistik & Streak", umbenannt v3.5.0)_ — die Heatmap zeigt auf dem Handy nur die letzten paar Monate statt aller 52 Wochen, passt sich also der Bildschirmbreite an statt zu schrumpfen. Textliche Ergänzung, kein neues Bild (das Verhalten selbst existiert bereits seit v3.2.27, siehe Abschnitt "Statistik-Dashboard").
- Nutzerorientiert mit Kernmechanik (z.B. 5 Leitner-Fächer, Warteschlangen-Prinzip, Drill-Übergang ins Leitner-System), aber ohne Datenbank-/Code-Details
- **Leitner-/Drill-Abschnitte zeigen die tatsächlich konfigurierten Werte live im Text** _(v3.0.2)_ — z.B. Leitner-Intervalle pro Fach (`LEITNER_INTERVALS`), tägliches Karten-Limit (`DAILY_CARD_LIMIT`), Default-Kartenanzahl (`LEITNER_DEFAULT_CARDS`), Drill-Timer in Minuten (`DRILL_SESSION_SECONDS`), Mastery-Schwelle (`DRILL_MASTERY_THRESHOLD`), «Musste nachdenken»-Limit (`DRILL_TOO_HARD_LIMIT`), Bekannt/Neu-Verhältnis (`DRILL_KNOWN_RATIO`) — passen sich automatisch an, wenn diese Werte in den Einstellungen geändert werden, keine statischen/veralteten Angaben mehr
- Kein eigener Datenbankzugriff für Inhalte (Konfigurationswerte kommen aus PHP-Konstanten) — `db.php` wird nur für die zentrale Navbar (`render_navbar($pdo)`) benötigt, keine Formulare ausser Logout/Konto/Person-wechseln (Navbar-Aktionen)

---

## Wissenschaftlich Sprachen lernen _(v3.11.0)_

- Eigener Bereich im Unterverzeichnis `/infos/` mit Übersichtsseite `wissen.php` und fünf Unterseiten, erreichbar über das Haus-Icon (`bi-house`) in der Navbar (siehe Abschnitt "Navigation") sowie über Kacheln auf `wissen.php` selbst. Alle Seiten erfordern Login (`require_person()`), kein eigener Datenbankzugriff für Inhalte (`db.php` nur für die zentrale Navbar). `includes/auth.php` erkennt Seiten im `infos/`-Unterverzeichnis automatisch (`app_root_prefix()`) und passt Navbar-Links sowie Login-/Berechtigungs-Redirects entsprechend an (`../home.php` statt `home.php` usw.) — unabhängig vom Installationspfad der App.
- **`infos/wissen.php`** — Einstiegsseite: kurzer Einleitungstext plus fünf Kacheln (Bootstrap-Cards) zu den Unterseiten.
- **`infos/mythen.php`** — Mythos-Check als Accordion (erstes Element standardmässig aufgeklappt, Rest zu): Café-Plaudern ohne Feedback, Sprachduschen (passives Berieseln), Filme/Serien mit falschen Erwartungen, Vokabellernen im Schlaf, "einfach eintauchen reicht" (Grammatik ignorieren), "je früher desto besser" (früher Unterrichtsbeginn), Birkenbihl-Methode. Je Mythos: Behauptung → Was die Forschung zeigt → Was stattdessen hilft.
- **`infos/studien.php`** — Fliesstext-Zusammenfassung der Studienlage je Fertigkeit (Wortschatz, Chunks & Kollokationen, Lesen, Vorlesen & Geschichten bei Kindern, Hören, Sprechen & Interaktion, Schreiben, Grammatik & Zeitformen, Aussprache, Immersion/Study-Abroad/CLIL, Spiele/Gamification/Technologie) — bewusst ohne Inline-Zitate im Fliesstext, Quellen nur in der Quellenliste am Seitenende.
- **`infos/skala.php`** — Eigene 1–10-Wirksamkeits-Skala, mit deutlichem Hinweis-Banner, dass es sich um eine **eigene kommunikative Vereinfachung** handelt, keine wissenschaftlich validierte Kennzahl (Effektstärken verschiedener Meta-Analysen sind laut den Ausgangsstudien selbst nicht direkt gegeneinander ranking-fähig). 14 generische, medium-/tool-unabhängige Methoden (z.B. „Leitner-System / Spaced-Repetition-Karteikarten" statt „Karteikarten-App XY" — ob App oder Papier spielt für den Lernmechanismus keine Rolle) in sechs einklappbaren Bereichen (Bootstrap-Accordion, standardmässig zugeklappt): "Wörter lernen", "Chunks lernen" (inkl. Chunk-Definition in eigener Info-Box), "Lesen", "Hören", "Sprechen", "Weitere Methoden im Vergleich". Bewusst ausgeschlossen: rein altersbasierte Methoden (Chunks/Vorlesen bei Kindern, CLIL), benannte Marken-Komplettmethoden (Birkenbihl) und reine Mythos-Themen (Schlaf-Audio) — diese bleiben `infos/mythen.php`/`infos/studien.php` vorbehalten, damit jede Zeile hier eine mit anderen kombinierbare, niveaubasierte Methode ist statt eines Forschungsartefakts. Gamification erscheint nicht als eigene Zeile, sondern als Hinweis bei „Game-Based Learning" (wirkt nur indirekt über Motivation). Oben ein Niveau-Wähler (Buttons A1–C2): die Werte aller Methoden werden clientseitig per JS (`METHODEN`-Array + `skalaRender()`) auf drei Stufen berechnet (A1–A2/B1–B2/C1–C2 = Anfänger/Mittel/Fortgeschritten) und passen sich beim Klick live an, z.B. „Leitner-System" konstant stark auf allen Niveaus, „Chunks lernen" holt ab B1/B2 auf und überholt bis C1/C2. Methoden ohne belegten Niveau-Unterschied zeigen bewusst denselben Wert auf allen drei Stufen. Zusätzlicher Hinweis, dass sich die Methoden nicht gegenseitig ausschliessen, sondern als Werkzeugkasten zum Kombinieren gedacht sind. Jede Zeile zusätzlich mit „So wendest du es an" (konkrete Anleitung) und Evidenz-Badge (hohe/durchwachsene/schwache Evidenz, bewusst in Blau/Grau statt Ampelfarben, um Verwechslung mit dem Wirkungs-Balken zu vermeiden).
- **`infos/lernplan.php`** — Interaktiver, rein clientseitiger (JS) Rechner: Umschalter Erwachsene/Kinder (6–12), Niveau-Dropdown (A1–C2 bzw. Altersgruppe 6–8/9–12, Optionen abhängig vom Umschalter), Minuten-Eingabefeld plus Schnellwahl-Buttons (10/15/30/60/90 Min.). Zeitverteilung je Niveau-Stufe/Altersgruppe als feste Prozent-Tabelle im JS (`PLAENE`-Objekt), abgeleitet aus den Beispiel-Zeitbudgets der Studienlage — ausdrücklich als Heuristik gekennzeichnet. Minuten pro Kategorie werden per Grösster-Rest-Verfahren gerundet, damit die Summe exakt der eingegebenen Gesamtzeit entspricht (kein Verlust/Überschuss durch simples Runden je Zeile). Bei weniger als 15 Minuten erscheint ein zusätzlicher Hinweis auf Regelmässigkeit statt Vollständigkeit.
- **`infos/podcasts.php`** — Praktische Suchbegriffe, Suchmaschinen (Listen Notes, Podchaser) und Verständlichkeits-/Tempo-/Themenkriterien, um passendes Hörmaterial zu finden — bewusst **keine** konkreten Podcast-Titel-Empfehlungen (ändern sich laufend, Qualität schwer pauschal beurteilbar).
- **Zentrales Quellenverzeichnis** `includes/quellen-daten.php`: assoziatives PHP-Array `$QUELLEN` (Key → Autor/Titel/Journal/DOI-Link/Kennzahl/Status) plus Helper-Funktion `render_quellenliste(array $keys, string $accordionId): string`, die daraus ein zugeklapptes Bootstrap-Accordion "Quellen zu dieser Seite (N)" rendert. Jede der fünf Inhaltsseiten (`infos/mythen.php`, `infos/studien.php`, `infos/skala.php`, `infos/lernplan.php`, `infos/podcasts.php`) ruft das am Seitenende mit ihrer eigenen Teilmenge an Keys auf. Status je Quelle: `verifiziert` (Autor/Jahr/Journal/DOI + genannte Kennzahlen gegen die Originalpublikation bestätigt), `identifiziert` (Publikation eindeutig gefunden, Kennzahlen nicht einzeln nachgeprüft — z.B. Paywall), `unklar` (Themengebiet real, aber keine einzelne Studie zweifelsfrei als DIE Quelle zuordenbar — wird entsprechend vorsichtig referenziert statt mit falscher Präzision zitiert).
- Inhaltliche Grundlage: zwei separate Recherche-Dokumente zum wissenschaftlich fundierten Sprachenlernen (Erwachsene bzw. Kinder 6–12), die als Referenz im Projekt liegen (`studien/*.md`, `studien/*.pdf`) — nicht Teil der Web-App selbst, dienen nur als Quellmaterial für die oben genannten Seiten.

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
- **PHP-Caches werden nach dem Kopieren geleert** _(v3.7.10)_: Nach erfolgreichem Deploy ruft `deploy.php` `clearstatcache(true)` und — falls verfügbar — `opcache_reset()` auf (Log-Zeile "PHP-OPcache geleert" bzw. Hinweis, wenn nicht möglich). Grund: OPcache hält kompilierten Bytecode der alten Dateien im Speicher und führt ihn je nach Hosting-Konfiguration (`opcache.validate_timestamps=0` oder hohe `revalidate_freq`) auch nach dem Überschreiben weiter aus — die App blieb dann scheinbar auf der alten Version, obwohl die Dateien auf der Platte längst neu waren (auf Prod beobachtet: Deploy meldete korrekt die neue Version, Verhalten und Versionsanzeige der App blieben trotzdem alt)
- **`Cache-Control: no-store` auf allen Ansichten von `deploy.php`** _(v3.7.10)_: Ohne den Header darf der Browser die Statusseite aus seinem Cache beantworten — ein erneuter Aufruf nach einem Deploy zeigte dann scheinbar wieder die alte "Installiert"-Version, obwohl der Server längst die neue melden würde
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
| `tags` | Stichworte pro Person (Name, Besitzer) — kein globaler Pool, jede Person hat ihre eigenen Tags _(v3.9.0)_ |
| `card_tags` | n:m-Verknüpfung Karte ↔ Tag _(v3.9.0)_ |

### Lösch-Verhalten
- Karte löschen → `card_progress`- und `card_tags`-Einträge dieser Karte werden **physisch mitgelöscht** (kaskadierend)
- Liste löschen → alle Karten + deren `card_progress`/`card_tags` werden **physisch mitgelöscht**
- Tag löschen: geschieht **nie automatisch** — ein Tag ohne verknüpfte Karte bleibt in `tags` bestehen (leichter wiederverwendbar)
- Kopien anderer Personen sind unabhängig — nicht betroffen

---

## CSV-Export

- Exportiert nur **Kartendaten** (Sprache A, Sprache B, Beschreibung A, Beschreibung B, Lautschrift, Tags)
- Tags-Spalte dient nur der Portabilität/dem Backup — beim Reimport wird sie **nicht** eingelesen (siehe "Tags pro Karte")
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
  /infos/                   ← Bereich "Wissenschaftlich Sprachen lernen" (v3.14.2, zuvor im Root)
    wissen.php                 ← Übersicht, erreichbar über Haus-Icon in der Navbar (infos/wissen.php)
    mythen.php                 ← Mythos-Check zu Sprachlern-Versprechen
    studien.php                ← Zusammenfassung der Studienlage je Sprachfertigkeit
    skala.php                  ← Methoden-Wirksamkeit als eigene 1–10-Skala
    lernplan.php               ← Interaktiver Lernplan-Rechner (Zielgruppe/Niveau/Zeit → Aktivitätenplan)
    podcasts.php               ← Suchstrategien für passendes Hörmaterial
  /includes/                ← reine Library-/Config-Dateien, nie direkt per URL aufgerufen
    config.php                 ← Statische Konfiguration (Zeitzone, Intervalle, Version, Standardwerte)
    config-runtime.php         ← Laufzeit-Einstellungen pro Umgebung (gitignored, nie deployed, schreibt settings.php)
    migrations.php             ← Auto-Migrationen: fehlende DB-Spalten werden beim Start automatisch ergänzt
    auth.php                   ← Session-Start, Timeout, CSRF-Funktionen, require_login/person, today()
    tags.php                   ← Tag-Verwaltung: Parsing, Find-or-Create pro Person, Zuordnung zu Karten (v3.9.0)
    db.php                     ← Umgebungserkennung + DB-Verbindung + Migrationen
    db-credentials.php         ← Zugangsdaten Dev + Prod (gitignored, nie committen)
    db-credentials.example.php ← Vorlage für db-credentials.php (committet)
    deploy-config.php          ← Deploy-Token + GitHub-Konfiguration (gitignored)
    mcp-config.php             ← MCP-Token (gitignored)
    mcp-config.example.php     ← Vorlage für mcp-config.php (committet)
    quellen-daten.php          ← Zentrales Quellenverzeichnis für "Wissenschaftlich Sprachen lernen" (v3.11.0)
  /docs/                    ← Dokumentation ausser CLAUDE.md
    ANFORDERUNGEN.md, CHANGELOG.md, Testing.md, mcp-einrichtung.md — Checkliste.md liegt ebenfalls hier, ist aber gitignored und nie committet, reine lokale Aufgabenliste
```

---

## MCP-Server _(v2.0.0, erweitert v2.0.1, Chunk-Modell + Tags + Phonetik-Stilwahl v3.10.0)_

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

**`list_person_tags(person_id)`** _(v3.10.0)_ — Pflichtfeld: `person_id` (integer)
- Gibt alle Tags dieser Person zurück, über sämtliche ihre Listen hinweg: `{ person: { id, name }, tags: [tag, ...] }` (alphabetisch sortiert, Tags mit `#`-Präfix wie im Web-Formular _(v3.10.2)_)
- Reines Lese-Tool. Zweck: Vor dem Setzen eines Tags in `add_cards`/`update_card` prüft der Agent hier immer zuerst, ob ein passender Tag der Person schon existiert, und verwendet ihn wieder — statt einen inhaltlich gleichen, aber leicht anders geschriebenen neuen Tag anzulegen (Tags sind pro Person eigenständig, siehe Abschnitt "Tags pro Karte"). Passt keiner der vorhandenen Tags, fragt der Agent den User, statt selbst einen neuen zu erfinden

**`add_cards(list_id, cards[], force?)`**
- Fügt eine oder mehrere Vokabelkarten in eine Liste ein
- **Mehrdeutigkeit klären** _(v3.10.0)_: Hat ein Begriff mehrere stark unterschiedliche Bedeutungen (z.B. "bank" = Geldinstitut vs. Flussufer), fragt der Agent zuerst welche Bedeutung gemeint ist, bevor er übersetzt. Bei nur minimalen Nuancen nicht nachfragen
- `cards[]` = Array aus `{ sprache_a_begriff, sprache_b_begriff, beschreibung_a?, beschreibung_b?, phonetik_b?, tags? }` (`phonetik_b` seit v2.4.0, max. 200 Zeichen; `tags` seit v3.10.0, max. 300 Zeichen)
- Feld-Regeln für den Agent (in Tool-Beschreibung, Feld-Beschreibungen und `initialize`-Instructions — serverseitig nur für `tags` validiert wie beim Web-Formular, sonst reine Agent-Anweisung) _(v2.0.2, verschärft v2.0.4, Chunk-Modell + feste Beschreibung-Rollen v3.10.0)_:
  - **Begriff A/B — Chunk statt Einzelwort, als Default** _(v3.10.0)_: kein isoliertes Einzelwort mehr, sondern standardmässig eine natürliche Phrase/ein Chunk mit realistischem Verwendungskontext (mind. ein Adjektiv oder eine Ergänzung) — z.B. nicht "Entscheid" sondern "einen wichtigen Entscheid treffen". Kein starres Muss: verlangt der User im Gespräch ausdrücklich ein einzelnes Wort statt eines Chunks (z.B. "nur das Wort", "ohne Kontextsatz"), gilt diese explizite Anweisung — der Agent erzwingt keinen Chunk gegen den ausdrücklichen Wunsch. Der jeweils andere (Lösungs-)Begriff darf im Chunk nicht so vorkommen, dass er die Antwort preisgibt. **Bedeutungsgleichheit ist die wichtigste Regel überhaupt** _(v3.10.1)_: Begriff A und Begriff B müssen exakt dieselbe Bedeutung tragen — Fundament des Sprachenlernens, eine Karte mit abweichender Bedeutung bringt der lernenden Person eine falsche Übersetzung bei. Ausnahme nur bei Sprichwörtern/Redewendungen ohne wörtliche Entsprechung (z.B. "once in a blue moon" ↔ "alle Jubeljahre"): dort sinngemäss statt wörtlich, aber die Kernaussage muss weiterhin exakt übereinstimmen, nur nicht der Wortlaut — bei normalem Wortschatz gilt immer exakte Bedeutungsgleichheit, keine freie Näherung. Rückübersetzungs-Konsistenz-Check ist Pflicht vor jeder Bestätigung: übersetzt man Begriff B zurück, muss das Ergebnis bedeutungsgleich mit Begriff A sein — weicht es ab und ist es keine Redewendung, korrigiert der Agent das vor der Bestätigung, statt es nur zu melden. Ist der Kernbegriff des Chunks in der Fremdsprache ein Verb: weiterhin Grundform (Infinitiv), bei unregelmässigen Verben alle drei Formen (z.B. "go / went / gone")
  - Für den deutschen Anteil des Chunks gilt weiterhin die bisherige Gross-/Kleinschreibung _(v2.7.10)_ — Nomen **immer** gross (z.B. "Haus", "Tisch"), alle anderen Wortarten klein, ausser am Satzanfang bei mehrteiligen Chunks. Rechtschreibung auf **de-CH** ausgerichtet: nie "ß", immer "ss" (z.B. "Strasse") _(v3.2.37)_. Fremdsprachige Chunks: Originalschreibweise, kein automatisches Grossschreiben ausser bei echtem Satzanfang
  - **Übersetzungsqualität** _(v3.10.0, Bedeutungsgleichheit vs. Sprichwort-Ausnahme präzisiert v3.10.1)_: Übersetzungen müssen natürlich und idiomatisch klingen, dürfen dabei aber nie von der exakten Bedeutung abweichen (Sprichwort-Ausnahme siehe oben bei Begriff A/B) — falsche Übersetzungen sind inakzeptabel. Bei Unsicherheit über die korrekte Übersetzung fragt der Agent den User, statt zu raten. Gilt für alle Textfelder (Begriff A/B, Beschreibung A/B)
  - **Beschreibung A/B — feste, sprachunabhängige Rollen statt Fremdsprache/Deutsch-Logik** _(v3.10.0, ersetzt die bisherige Regel "Fremdsprachen-Seite = Beispielsatz, Deutsch-Seite = Erklärung")_: Beschreibung A ist immer ein kognitiver Hinweis zur aktiven Selbstkorrektur — **keine direkte Lösung**, der Begriff selbst darf nicht erscheinen (z.B. "Gegenteil von X", "unregelmässige Form von Y", "wird verwendet wenn man Z ausdrücken will"), bei Mehrdeutigkeit den konkreten Verwendungskontext angeben. Beschreibung B ist immer ein natürlicher, alltagstauglicher Beispielsatz mit dem **exakten** Begriff aus Begriff B — kein Lehrbuchsatz, insbesondere keine reinen Konjugations-Sätze ohne echten Inhalt (z.B. "wir werden kaufen" unzulässig), muss eine echte Situation beschreiben. Diese Rollen gelten unabhängig davon, welche Seite (A oder B) laut `language_a`/`language_b` Deutsch ist
  - Bekannter Fehlerfall aus der Vorgänger-Regel, weiterhin gültig: der fremdsprachige Begriff darf **niemals** in Beschreibung A wiederholt werden (z.B. `bounced` in der Beschreibung zu `unzustellbar`) — jetzt Teil der "keine direkte Lösung"-Regel
  - **Dialekt-Konsistenz** _(v2.2.0, Standard-Fallback v2.7.11)_: Ist Sprache B Englisch und die Zielliste hat einen `speech_lang_b`-Code (z.B. `en-GB` vs. `en-US`) gesetzt, müssen Schreibweise und Wortwahl von Begriff B sowie Beschreibung B zu diesem Dialekt passen (z.B. `en-GB` → "colour", "lorry", "flat"; `en-US` → "color", "truck", "apartment") — diese Listen-Definition hat Vorrang vor allem anderen. Ist **kein** `speech_lang_b` gesetzt, gilt als Standard **britisches Englisch (en-GB)**, nicht US-Englisch, ausser der User verlangt im Gespräch ausdrücklich einen anderen Dialekt. Reine Agent-Anweisung, keine serverseitige Validierung
  - **Lautschrift, jetzt mit Stilwahl einfach/IPA** _(v2.4.0, nicht-rhotische Regel v2.6.1, IPA-Option v3.10.0)_: Hat die Zielliste ein `speech_lang_b`, wird `phonetik_b` befüllt — hat sie keins, bleibt es leer (unverändert). **Neu:** zwei mögliche Stile. Der Agent leitet den Stil aus vorhandenen `phonetik_b`-Einträgen der Liste ab (`list_cards` aufrufen) — enthalten sie IPA-Zeichen (z.B. `/biːt/`), wird IPA weitergeführt; enthalten sie vereinfachte Lautschrift (z.B. `biit`), wird dieser Stil weitergeführt; Konsistenz innerhalb der Liste hat Vorrang. Existieren noch keine Einträge, zeigt der Agent dem User einmalig ein Beispiel beider Varianten für den aktuellen Begriff und fragt "einfach" (vereinfachte Lautschrift) oder "eindeutig" (IPA) — der Entscheid gilt dann für die ganze Liste/Session, nicht erneut pro Karte erfragen. Vereinfachte Lautschrift wie bisher: Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Sonderzeichen (z.B. `toh-ken-eye-ZAY-shun`), passend zum Dialekt; bei nicht-rhotischen Dialekten (`en-GB`, `en-AU`, `en-NZ`, `en-ZA`) "r" nach Vokal vor Konsonant/am Wortende weglassen (`thunder` → `THUN-duh`, `storm` → `stawm`), bei rhotischen (z.B. `en-US`) normal schreiben. Diese rhotisch/nicht-rhotisch-Regeln gelten für Englisch als Sprache B — bei anderen Zielsprachen verwendet der Agent sinngemäss eine vereinfachte, zur jeweiligen Zielsprache passende Lautschrift (keine ausformulierten Detailregeln dafür hinterlegt). IPA: Standard-IPA-Notation (z.B. `/biːt/`), Dialekt muss zu `speech_lang_b` passen. Bei Unsicherheit über die korrekte Phonetik (egal welcher Stil): leer lassen
  - **Muttersprache für die Lautschrift-Lesekonventionen** _(v3.2.29, Standardannahme v3.2.37)_: gilt nur für den Stil "einfach" — muss in den Lesekonventionen der Muttersprache der lernenden Person geschrieben sein. Standardannahme: Muttersprache = **Sprache A der Liste** — der Agent fragt nicht bei jeder Liste pauschal nach, sondern nur wenn diese Annahme keinen Sinn ergibt oder der User widerspricht
  - **Tags** _(v3.10.0)_: optionales Feld, leerzeichengetrennt mit `#`-Präfix, gleiches Format wie im Web-Formular (z.B. `#Wetter #Reise`) — mehrere Tags pro Karte möglich. Vor dem Setzen ruft der Agent `list_person_tags` auf und verwendet einen passenden vorhandenen Tag der Person wieder, statt einen neuen zu erfinden; passt keiner, fragt er den User. Tags immer auf **Deutsch (de-CH)**, unabhängig von der Lernsprache der Karte — nur setzen wenn inhaltlich sinnvoll, nicht zwingend bei jeder Karte. Serverseitig über dieselbe Logik wie das Web-Formular geparst/validiert (`includes/tags.php`) — nicht nur Agent-Anweisung, echte Validierung inkl. Längenlimit pro Einzeltag
  - **Verbotene Muster — schnelle Referenz** _(v3.10.0)_:

    | ❌ Verboten | ✅ Korrekt |
    |---|---|
    | Einzelwort als Begriff (z.B. "Entscheid"), ausser User verlangt es explizit | Chunk/Phrase mit Kontext (z.B. "einen wichtigen Entscheid treffen") |
    | Lösungsbegriff in Beschreibung A | Hinweis ohne direkten Begriff |
    | Lehrbuch-Beispielsatz (z.B. "wir werden kaufen") | Echter Alltagssatz mit Kontext |
    | Wörtliche Übersetzung | Idiomatische, natürliche Übersetzung |
    | Inkonsistenter Chunk-Kontext (Begriff A ≠ Begriff B) | Sinngleicher Kontext in beiden Sprachen |
    | "ß" in deutschen Texten | Immer "ss" (de-CH) |
    | Phonetik raten bei Unsicherheit | Leer lassen |
    | Tag selbst erfinden ohne Prüfung | Mit `list_person_tags` abgleichen, sonst User fragen |
    | Grossschreiben von Fremdwort-Chunks | Originalschreibweise der Zielsprache |
- Legt pro eingefügter Karte zusätzlich einen `card_progress`-Eintrag (`status = 'queued'`) für die Besitzer:in der Liste an — wie bei den übrigen Einfüge-Wegen (`edit.php`, `import.php`, `discover.php`, Mathe-Generator) _(v3.5.1; zuvor fehlte dieser Eintrag, wodurch Karten in `edit.php` fälschlich als "Warteschlange" erschienen, in `home.php` aber nicht mitgezählt und nie aktiviert wurden)_
- Duplikatprüfung: exakter Vergleich (case-insensitive, getrimmt) auf `word_a + word_b` innerhalb der Ziel-Liste (Tags fliessen nicht in den Duplikat-Vergleich ein)
- Duplikat + `force = false`: Karte wird nicht eingefügt, Warnung mit gefundener Karte zurückgegeben
- Duplikat + `force = true`: Karte wird trotzdem eingefügt
- Limits: max. 50 Karten/Aufruf, Begriff max. 500 Zeichen, Beschreibung max. 1000 Zeichen, Tags-Feld max. 300 Zeichen (Einzeltag zusätzlich auf `TAG_NAME_MAX_LENGTH`, 100 Zeichen, begrenzt)
- Antwort: `{ summary, list: { id, name }, results: [{ index, status, card, message?, warnings? }] }` — `card` enthält bei `inserted` zusätzlich `tags: [tag, ...]`
  - `status`: `inserted` / `duplicate` / `error`
- **Agent-Pflicht:** alle Felder der einzufügenden Karten (Begriff A/B, Beschreibung A/B, Tags, Phonetik) inkl. sichtbarer Rückübersetzung von Begriff B dem User vollständig zur Bestätigung zeigen — erst nach expliziter Bestätigung wird `add_cards` aufgerufen. Enthält die Antwort `warnings`, diese dem User zeigen statt zu übergehen _(v3.10.3)_
- **Serverseitige Absicherungen** _(v3.10.3, gelten identisch für `update_card`)_:
  - **Parameter-Normalisierung**: unbekannte Feldnamen (Tippfehler, falsche Gross-/Kleinschreibung wie `sprache_b_Begriff`) werden nicht mehr stillschweigend verworfen — case-insensitive Treffer werden automatisch korrekt zugeordnet, alles andere landet als `warnings`-Eintrag mit Formulierungsvorschlag (Levenshtein-Distanz), der Feldwert wird dabei NICHT übernommen. Vorher: der Agent konnte ein Feld absichtlich ändern wollen, ohne dass etwas passierte — kein Fehler, keine Rückmeldung
  - **Kernbegriff-Leck-Check**: prüft, ob Kernbegriffe aus Begriff A **oder** Begriff B (Stoppwörter/Wörter ≤ 3 Zeichen ausgeschlossen: "a, an, the, to, for, of, on, in, at, by, with, from, up, out, it, is, as, be, do, go, get") wörtlich in Beschreibung A auftauchen und dort die Lösung verraten — reine Warnung, blockiert die Operation nicht
  - **Unbekannte-Tags-Warnung**: Tags, die bei dieser Person noch nicht existieren, werden weiterhin normal angelegt/gesetzt (Tags bleiben frei erfindbar, siehe Abschnitt "Tags pro Karte") — zusätzlich aber als `warnings`-Eintrag gemeldet, inkl. Liste der bereits bekannten Tags, damit Schreibweisen-Divergenz auffällt, falls `list_person_tags` nicht vorher geprüft wurde

**`list_cards(list_id)`** _(v2.6.0, Tags v3.10.0)_ — Pflichtfeld: `list_id` (integer)
- Gibt Listen-Metadaten plus alle bestehenden Karten zurück: `{ list: { id, name, language_a, language_b, speech_lang_b }, cards: [{ card_id, sprache_a_begriff, sprache_b_begriff, beschreibung_a, beschreibung_b, phonetik_b, tags: [tag, ...] }] }` (Tags mit `#`-Präfix _(v3.10.2)_)
- Dient zum Prüfen bestehender Karten (z.B. Schreibweise, fehlende Lautschrift, fehlende/inkonsistente Tags) vor gezielten `update_card`-Aufrufen — reines Lese-Tool, keine Änderung. Auch Grundlage, um vor neuen Karten den in dieser Liste bereits verwendeten Lautschrift-Stil (einfach vs. IPA) abzulesen

**`update_card(card_id, ...)`** _(v2.6.0, Tags v3.10.0)_ — Pflichtfeld: `card_id` (integer)
- Ändert gezielt einzelne Felder einer bestehenden Karte: `sprache_a_begriff?`, `sprache_b_begriff?`, `beschreibung_a?`, `beschreibung_b?`, `phonetik_b?`, `tags?` — nur übergebene Felder werden geändert, alle anderen bleiben unverändert
- `sprache_a_begriff`/`sprache_b_begriff` dürfen, falls angegeben, nicht leer sein — `beschreibung_a/b`/`phonetik_b`/`tags` können mit leerem String geleert werden (bei `tags`: entfernt alle Tags der Karte)
- `tags`, falls angegeben, **ersetzt die komplette bisherige Tag-Zuordnung** der Karte (kein Hinzufügen/Entfernen einzelner Tags) — gleiche Parsing-/Validierungslogik wie `add_cards` (`includes/tags.php`)
- Gleiche Feld-Regeln wie `add_cards` (Chunk-Modell, feste Beschreibung-Rollen, Dialekt-Konsistenz, Lautschrift-Stil), gleiche Zeichenlimits
- **Agent-Pflicht:** vor dem Aufruf dem User pro Karte zeigen was sich ändert (alt → neu) und Bestätigung abwarten — niemals `list_cards`-Ergebnisse ungefragt automatisch mit `update_card` ändern. Enthält die Antwort `warnings`, diese ebenfalls zeigen _(v3.10.3)_
- Antwort: `{ summary, changed_fields: [feld, ...], card: { card_id, sprache_a_begriff, sprache_b_begriff, beschreibung_a, beschreibung_b, phonetik_b, tags: [tag, ...] }, warnings? }` (Werte nach der Änderung) _(`changed_fields`/`warnings` seit v3.10.3)_
  - `changed_fields` listet nur Felder, deren **Wert sich tatsächlich geändert hat** (Alt- vs. Neu-Vergleich) — wird z.B. derselbe Text erneut übergeben, taucht das Feld dort NICHT auf, obwohl es im Request stand

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


