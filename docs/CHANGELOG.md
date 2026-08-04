# Changelog

Alle relevanten Änderungen werden hier dokumentiert.
Format: `MAJOR.MINOR.PATCH` — siehe `config.php` für die aktuelle Version.

---

## [3.3.13] - 2026-08-04

### Neu
- **Lautschrift in der Entdecken-Vorschau:** Beim Vorschauen einer öffentlichen Liste (`discover.php?list_id=X`) vor dem Kopieren wird jetzt auch die hinterlegte Lautschrift angezeigt (z.B. `cinquante-neuf [sɛ̃kˈɑ̃tnˈœf]`) — gleiche Darstellung wie in der eigenen Kartenübersicht. Mitkopiert wurde sie schon vorher, war in der Vorschau davor aber nicht sichtbar.

---

## [3.3.12] - 2026-08-04

### Verbessert
- **Überflüssigen Erklärungstext neben "Zufall" entfernt** (Leitner-/Drill-Setup) — die Beschriftung war unnötig, das Verhalten selbst (einmal pro Session, nicht pro Karte) ist unverändert und weiterhin in `docs/ANFORDERUNGEN.md` sowie `help.php` dokumentiert.

---

## [3.3.11] - 2026-08-04

### Neu
- **Neue Lernrichtung "Zufall"** (Leitner & Drill): vierte Option auf der Konfigurationsseite, ganz unten in der Liste — und jetzt **Default** statt A→B. Bei "Zufall" würfelt der Server einmalig beim Start der Session eine der drei echten Richtungen (A→B, B→A, Gemischt) aus; die Session läuft danach durchgehend mit dieser einen Richtung. Verhindert einseitiges Lernen durch immer dieselbe Richtung. Gemeinsame Logik (`resolve_direction()`) in `includes/auth.php`, von `learn.php` und `drill.php` gleichermassen genutzt.

### Verbessert
- **Lernrichtungs-Auswahl steht jetzt untereinander** statt nebeneinander in einer Zeile — Reihenfolge A→B, B→A, Gemischt, Zufall.

---

## [3.3.10] - 2026-08-04

### Behoben
- **"Für Drill vormerken" in der Leitner-Session klappte eine bereits aufgedeckte Karte wieder zu.** Der Pin-Button war ein normaler Formular-Button — ein Klick löste einen vollen Seiten-Reload aus, der den rein clientseitigen "aufgedeckt"-Zustand (`flipCard()`) zurücksetzte, egal ob die Lösung schon sichtbar war. Der Klick läuft jetzt per `fetch()` statt eines normalen Form-Submits; die Kartenanzeige (auf- oder zugeklappt) ändert sich beim Vormerken/Entfernen nicht mehr.

---

## [3.3.9] - 2026-08-03

### Neu
- **Eigener Einleitungs-Abschnitt auf der Hilfeseite:** Der kurze Überblick über die Grundfunktionen stand bisher als freier Text oberhalb des Accordions und passte optisch nicht zum Rest der Seite. Er steht jetzt als erster, standardmässig aufgeklappter Accordion-Abschnitt "Einleitung". Der vormals erste Abschnitt heisst dadurch nur noch "Login & Person" (statt "Einstieg: Login & Person") und ist wie alle anderen Abschnitte standardmässig eingeklappt.

### Verbessert
- **Timer-Screenshot auf der Hilfeseite deutlich verkleinert:** `drill-timer.png` wurde bisher auf 220px Breite hochskaliert (Original nur 210×94px) und wirkte dadurch wie ein grosser Screenshot statt wie der kleine Navbar-Ausschnitt, den er zeigt. Feste Breite jetzt 105px — entspricht in etwa der tatsächlichen Grösse auf der Webseite.
- **Hilfeseite erwähnt jetzt die mobile Heatmap-Anpassung:** Im Abschnitt "Statistik & Streak" ein zusätzlicher Hinweis, dass sich die Jahres-Heatmap der Bildschirmbreite anpasst (auf dem Handy ein kürzerer, aktuellerer Zeitraum statt aller 52 Wochen). Rein textlich — das Verhalten selbst existiert bereits seit v3.2.27.

---

## [3.3.8] - 2026-08-03

### Neu
- **Vier weitere Screenshots auf der Hilfeseite** (`help.php`), im gleichen Bildstil wie der bestehende Kartenscreenshot: "Wörter hinzufügen" zeigt jetzt das Formular für die manuelle Eingabe (`neues-wort.png`); "Leitner-Modus" und "Drill-Modus" zeigen je die Konfigurationsseite vor dem Start (`neue-session-leitner.png`, `neue-session-drill.png`); "Drill-Modus" zeigt zusätzlich Timer und Abbrechen-Symbol aus der Navbar einer laufenden Session (`drill-timer.png`), bisher unbebildert.
- **Timer-Empfehlung im Drill-Modus:** Hinweis auf der Hilfeseite, dass der Timer mindestens 5 Minuten laufen sollte — bei weniger Zeit reicht es meist nicht, um dieselbe Karte oft genug zu wiederholen (3× richtig hintereinander nötig, um als gemeistert zu gelten).

---

## [3.3.7] - 2026-08-03

### Behoben
- **Duplikat-Prüfung beim CSV-Import verglich über alle Sprachen hinweg.** Beim Import wurden bisher alle eigenen Karten aller Listen unabhängig von der Sprache als möglicher Duplikat-Treffer herangezogen — ein italienisches "casa"/"Haus" wurde so beim Import einer französischen Liste fälschlich als Duplikat von "maison"/"Haus" angezeigt, obwohl die Sprachen nicht zusammenpassen. Die Prüfung berücksichtigt jetzt nur noch Karten aus Listen mit identischer Sprache A **und** Sprache B wie die Ziel-Liste.

---

## [3.3.6] - 2026-08-03

### Neu
- **Debug-Panel wegklickbar:** Das fixierte Debug-Panel in `learn.php`/`drill.php` hat jetzt einen "×"-Schliessen-Button. Bei ungewöhnlich hohen Karten konnte das Panel bisher dauerhaft die Antwort-Buttons verdecken — einzige Abhilfe war der Session-Abbruch. Nutzt Bootstraps `data-bs-dismiss="alert"` (kein zusätzliches Script nötig, da Bootstrap-JS auf beiden Seiten bereits geladen ist).

---

## [3.3.5] - 2026-08-03

### Neu
- **Lernrichtung im Drill-Modus:** `drill.php` unterstützt jetzt wie das Leitner-System die Wahl der Lernrichtung (A→B, B→A oder gemischt) auf der Konfigurationsseite vor dem Start. Frage-/Antwortseite und die Zuordnung von Audio/Lautschrift (immer an Sprache B gebunden) folgen derselben Logik wie im Leitner-System — die dafür nötige Funktion `get_question_answer()` wurde aus `learn.php` nach `includes/auth.php` verschoben, damit beide Seiten sie gemeinsam nutzen.
- **Timer pro Drill-Session frei einstellbar:** Auf derselben Konfigurationsseite lässt sich die Dauer der Session (in Minuten, ±5-Buttons) für den aktuellen Start anpassen — Standardwert kommt aus den Einstellungen, die Änderung selbst wird nicht dauerhaft gespeichert.

### Verbessert
- **Konsistentes Drill-Start-Verhalten:** Der "Drill"-Button auf einer einzelnen Listen-Karte (Startseite) sowie "Erneut starten" auf dem Abschluss-Screen zeigen jetzt wie beim Leitner-System immer die Konfigurationsseite (Liste vorausgewählt, Richtung + Timer wählbar), statt die Session ohne Zwischenschritt sofort zu starten.

### Behoben
- **`help.php` enthielt eine durch diese Änderung überholte Aussage** ("im Drill-Modus fest A→B") — korrigiert, beide Modi werden jetzt einheitlich beschrieben.

---

## [3.3.4] - 2026-08-02

### Verbessert
- **Hörprobe in `help.php` angepasst:** Beispieltext jetzt "Can you hear me?" auf Englisch (`en-GB`) statt "Mich kann man hören" (`de-CH`). Button/Icon entsprechen jetzt exakt dem 🔊-Knopf auf den Lernkarten (`bi-volume-up-fill`), zusätzlich mit Beschriftung "Klick mich".

---

## [3.3.3] - 2026-08-02

### Neu
- **Aussprache-Abschnitt in `help.php` zum Ausprobieren:** Button "Anhören" spricht den Beispielsatz "Mich kann man hören" auf Schweizerdeutsch (`de-CH`) per Web Speech API — dieselbe Technik wie die 🔊-Knöpfe auf Leitner-/Drill-Karten. Begriff und verwendete Sprache stehen direkt daneben.

---

## [3.3.2] - 2026-08-02

### Neu
- **Neuer Hilfe-Abschnitt "Aufbau einer Lernkarte"** (`help.php`), zwischen "Wörter hinzufügen" und "Leitner-Modus": erklärt anhand eines Screenshots (`img/learner-karte.png`) den Aufbau der Lernkarte — Frageseite oben, per Antippen aufgedeckte Antwortseite unten, Lautschrift und 🔊-Ausspracheknopf (immer auf der fremdsprachigen Seite), sowie das Pin-Symbol "Für Drill vormerken". Gilt übergreifend für Leitner und Drill, da die Karte in beiden Modi identisch aussieht.

---

## [3.3.1] - 2026-08-02

### Hinweis zur Versionsnummer
- **Keine inhaltliche Änderung** — reine Korrektur der Versionszählung. Am 29.07.2026 wurde `v3.3.0` getaggt (Listen-Status Aktiv/Inaktiv), die Entwicklung danach lief aber versehentlich unter `3.2.42`–`3.2.48` statt unter der `3.3.x`-Linie weiter. Ab hier zählt die Nummerierung korrekt ab `3.3.1` weiter (letzter inhaltlicher Stand entspricht `3.2.48`: Icon für den "Statistik"-Button auf der Startseite).

---

## [3.2.48] - 2026-08-02

### Verbessert
- **"Statistik"-Button auf der Startseite mit Icon** (`bi-bar-chart-line`, dasselbe Icon wie beim gleichnamigen Button auf den einzelnen Listen-Karten) statt reinem Text — konsistent zu "Meine Listen".

---

## [3.2.47] - 2026-08-02

### Neu
- **Listenübergreifende Leitner-/Drill-Buttons auf der Startseite:** Neben "Meine Listen" stehen jetzt zusätzlich "Leitner" und "Drill" — beide starten ohne vorausgewählte Liste und zeigen zuerst eine Checkbox-Auswahl aller eigenen aktiven Listen (Mehrfachauswahl möglich), analog zur bestehenden Listenauswahl in `learn.php`.
- **`drill.php` unterstützt jetzt Mehrfach-Listenauswahl:** Bisher liess sich eine Drill-Session nur direkt aus einer einzelnen Liste heraus starten. Wird `drill.php` ohne vorausgewählte Liste aufgerufen, erscheint eine neue Setup-Seite mit Checkbox-Auswahl (erste Liste vorausgewählt) — die zugrundeliegende Pool-Logik unterstützte mehrere Listen bereits zuvor, es fehlte nur der Einstiegspunkt dafür.

### Verbessert
- **"Meine Listen"-Button mit Icon** (`bi-pencil`, wie beim gleichnamigen Icon-Button auf den einzelnen Listen-Karten) statt reinem Text.
- Fehler beim Starten einer Drill-Session (keine Liste ausgewählt, keine gültige Liste, keine geeigneten Karten) führen jetzt zurück auf die Drill-Auswahlseite statt auf die Startseite.

---

## [3.2.46] - 2026-08-02

### Behoben
- **Deploy konnte laufende Anfragen mit "Verbindung unerwartet beendet" abbrechen.** `deploy.php` kopierte neue Dateien bisher direkt per `copy()` auf die live laufenden PHP-Dateien — das ist nicht atomar und überschreibt die Zieldatei Stück für Stück. Eine parallele Anfrage (z.B. ein Kartenupdate in `edit.php`), die genau in diesem Moment dieselbe Datei einliest, konnte sie dadurch abgeschnitten oder syntaktisch kaputt zu sehen bekommen, was sich als plötzlicher Verbindungsabbruch zeigte — im Extremfall bei `includes/auth.php` auch als unerwarteter Logout. Jede Datei wird jetzt zuerst in eine temporäre Datei im selben Verzeichnis geschrieben und erst per `rename()` (auf demselben Dateisystem atomar) über die Zieldatei gelegt — eine parallele Anfrage sieht dadurch immer entweder die komplett alte oder komplett neue Datei, nie einen Zwischenzustand.

---

## [3.2.45] - 2026-08-02

### Behoben
- **Inaktive Listen erschienen in der Leitner-Listenauswahl.** Beim Starten einer Leitner-Session (`learn.php`, ohne Vorauswahl von der Startseite) tauchten bisher auch als inaktiv markierte Listen (`lists.is_active = 0`) in den auswählbaren Checkboxen auf, obwohl sie überall sonst (Startseite, Statistik) ausgeblendet werden. Die Abfrage filtert jetzt konsistent auf `is_active = 1`.

---

## [3.2.44] - 2026-08-02

### Verbessert
- **Debug-Panel jetzt fix am unteren Bildschirmrand statt im normalen Textfluss** (`position: fixed`, inkl. Platz für die iPhone-Home-Indicator-Leiste) — dadurch ohne Scrollen sichtbar, unabhängig von der Position im Seitenaufbau. Bei ungewöhnlich hohen Karten kann das Panel dadurch die Antwort-Buttons überlagern; betrifft nur Admins mit aktivem Debug-Modus.

---

## [3.2.43] - 2026-08-02

### Behoben
- **Debug-Panel verdeckte auf dem iPhone die Antwort-Buttons.** In `learn.php` und `drill.php` erschien das Debug-Panel bisher oberhalb der Karte und schob dadurch die Antwort-Buttons auf kleinen Bildschirmen unter den sichtbaren Bereich. Das Panel steht jetzt unterhalb von Karte und Buttons. Auf der Zusammenfassungs-/Abschlussseite (letzte Antwort der Session) bleibt es unverändert oben.

---

## [3.2.42] - 2026-08-02

### Verbessert
- **Drill-Debug-Panel zeigt jetzt beide Zähler gleichzeitig** statt nur den zur aktuellen Antwort passenden: "Mastery-Zähler X/3" (Folge richtiger Antworten, setzt bei Fehler zurück) und "Zu-schwer-Zähler X/5" (Gesamtzahl falscher Antworten in der Session, wird durch richtige Antworten dazwischen nicht zurückgesetzt) — vorher war nicht ersichtlich, dass es sich um zwei unabhängige Zähler mit unterschiedlichem Reset-Verhalten handelt.

---

## [3.2.41] - 2026-08-02

### Neu
- **"Für Drill vormerken" direkt in der Leitner-Session:** Das Pin-Symbol oben links auf der Lernkarte in `learn.php` ist jetzt klickbar statt nur anzeigend — ein Klick schaltet die Vormerkung sofort um, ohne die laufende Session zu unterbrechen (Queue, Fortschritt und Statistik bleiben unverändert, dieselbe Karte bleibt sichtbar). Bisher ging das nur über die Kartenansicht in `edit.php`. Neue Sicherheitsprüfung: nur die aktuell angezeigte Karte darf umgeschaltet werden. In `drill.php` bleibt das Symbol bewusst rein anzeigend.

---

## [3.2.40] - 2026-08-01

### Behoben
- **CSV-Import auf dem iPhone: "Datei auswählen"-Button reagierte nicht.** Das Datei-Feld hatte `accept=".csv"` — nur eine Datei-Endung ohne zugehörigen MIME-Type. Auf manchen iOS-Safari-Versionen öffnet sich der native Datei-Dialog dadurch beim Antippen gar nicht. `accept` enthält jetzt zusätzlich die MIME-Types `text/csv`, `text/comma-separated-values` und `application/vnd.ms-excel`. Nicht an einem echten iPhone verifiziert (kein Testgerät vorhanden) — bitte nach dem Deploy gegenprüfen.

---

## [3.2.39] - 2026-08-01

### Verbessert
- Leitner-Debug-Meldung bei falscher Antwort im 1. Versuch: "kommt nochmal dran" steht jetzt direkt bei der Antwort-Zeile ("falsch (1. Versuch) -> kommt nochmals dran") statt am Ende der Fach-Zeile — die Fach/Fälligkeits-Zeile bleibt dadurch auf die reine Statusänderung fokussiert.

---

## [3.2.38] - 2026-08-01

### Behoben
- **Drill-Meisterung konnte das Leitner-Fach fälschlich zurückstufen.** `master_card()` setzte das Fach bisher stur nach einer festen Tabelle basierend auf `drill_mastery` (Anzahl bisheriger Meisterungen), unabhängig vom tatsächlich aktuellen Fach der Karte. War eine Karte über normales Leitner-Lernen bereits weiter fortgeschritten als die Tabelle für die neue Meisterungsstufe vorsieht (z.B. Fach 4 erreicht, aber erst die zweite Drill-Meisterung, die laut Tabelle nur Fach 3 vorsieht), wurde die Karte beim erneuten Meistern im Drill auf das niedrigere Tabellen-Fach zurückgesetzt — obwohl Meistern eine Belohnung sein soll, keine Verschlechterung. Das Fach wird jetzt nur noch gesetzt, wenn das Ziel-Fach höher ist als das aktuelle; sonst bleibt es unverändert, `drill_mastery` zählt trotzdem weiter. Gefunden über das neue Debug-Panel (v3.2.34).

---

## [3.2.37] - 2026-08-01

### Neu
- `help.php`: neue Einleitung direkt unter dem Titel mit den Grundfunktionen der App (Wortlisten lernen/erstellen/kopieren, Leitner/Drill, 1×1, Audioaussprache).

### Verbessert
- `help.php`, Abschnitt "Leitner-Modus": Hinweis ergänzt, was das ausgefüllte Pin-Symbol auf einer Karte bedeutet (inkl. sichtbarem Icon in der Erklärung). Abschnitt "Drill-Modus": veralteten Verweis auf "in der Aktionsleiste" entfernt (dieser Button wurde in v3.2.31 wieder entfernt).
- **MCP-Server, Lautschrift-Regeln überarbeitet:** Deutsche Rechtschreibung ist jetzt explizit auf de-CH ausgerichtet (nie "ß", immer "ss"). Ausserdem: Muttersprache der lernenden Person wird standardmässig als Sprache A der jeweiligen Liste angenommen, statt bei jeder Liste pauschal nachzufragen — nur bei erkennbarer Unstimmigkeit (z.B. beide Sprachen sind für den User fremd) oder Widerspruch des Users fragt der Agent noch explizit nach. Zusätzlich klargestellt, dass die detaillierten Lautschrift-Regeln (rhotisch/nicht-rhotisch etc.) nur für Englisch als Sprache B ausformuliert sind — bei anderen Zielsprachen soll der Agent sinngemäss vereinfachen. Betrifft `initialize`-Instruktionen sowie die Tool-/Feld-Beschreibungen von `add_cards` und `update_card`.

---

## [3.2.36] - 2026-08-01

### Verbessert
- **Debug-Panel auf 3 Zeilen aufgeteilt** statt eines langen Fliesstext-Satzes: Karte / Antwort (Kontext) / Detail (Fach- bzw. Zähler-Änderung) — besser überflogen. Bei mehreren Infos in einer Zeile (z.B. Drill "gemeistert") bleiben Ereignis und Detail kombiniert.

---

## [3.2.35] - 2026-08-01

### Verbessert
- **Debug-Panel für vorgemerkte Karten war missverständlich:** Die Meldung zeigte nur "Zähler 0→0" ohne Kontext, sodass leicht der Eindruck entstehen konnte, es handle sich um das Leitner-Fach statt um den Vormerkungs-Zähler. Text jetzt explizit ("Vormerkungs-Zähler (richtige Antworten seit dem Vormerken)"), inkl. Hinweis, dass das Fach unverändert bleibt bzw. bei noch nicht in Leitner aktiven Karten (Warteschlange) gar nicht erst betroffen ist — vorher stand dort "(Fach )" ohne Wert.
- **"Vorgemerkt" vereinheitlicht zu "Für Drill vorgemerkt"**: Filter-Tab und Status-Badge in der Kartenübersicht (`edit.php`) verwendeten bisher nur das kontextlose Wort "Vorgemerkt" — jetzt konsistent mit den Tooltips und den Drill-Debug-Meldungen.

---

## [3.2.34] - 2026-08-01

### Neu
- **Debug-Modus:** neuer Schalter unter Einstellungen → Debug (unterhalb Deployment), nur für Admins. Zeigt bei Aktivierung in Leitner und Drill nach jeder beantworteten Karte ein Info-Panel mit dem genauen Vorher/Nachher-Status — Fach und Fälligkeit in Leitner, Zähler-Stand bzw. besondere Ereignisse (gemeistert, als zu schwer markiert, Vormerkung erreicht) in Drill. Erscheint einmalig wie eine Flash-Message, auch auf der jeweiligen Session-Zusammenfassung, falls die beantwortete Karte die letzte war. Der Schalter selbst ist global (`config-runtime.php`), das Panel wird aber ausschliesslich für Admin-Sessions gerendert, damit andere Personen im selben Haushalt keine internen Begriffe zu sehen bekommen.

---

## [3.2.33] - 2026-08-01

### Verbessert
- **Pin-Symbol für vorgemerkte Karten vereinheitlicht:** In `drill.php` und `learn.php` war das Symbol oben links auf der Karte bisher ein eckiger Badge, während die Kartenansicht in `edit.php` einen runden Button zeigt. Beide Stellen nutzen jetzt denselben runden Look (`btn btn-sm btn-primary rounded-circle`) — in `drill.php`/`learn.php` weiterhin nicht klickbar, da sich die Vormerkung während einer laufenden Session nicht umschalten lässt.

---

## [3.2.32] - 2026-08-01

### Neu
- **`deploy.php` zeigt jetzt eine Changelog-Vorschau** zwischen dem Versionsvergleich und dem "Deploy starten"-Button: eine Zeile `[X.Y.Z] - Titel` je Änderungspunkt aus `docs/CHANGELOG.md`, von der aktuell installierten Version (exklusive) bis zur GitHub-Version (inklusive) — so ist vor dem Deployen auf einen Blick sichtbar, was sich ändern würde, ohne extra in `CHANGELOG.md` nachschauen zu müssen.

---

## [3.2.31] - 2026-08-01

### Behoben
- **Kartenübersicht (`edit.php`): Aktions-Icons (Ansehen/Bearbeiten/Archivieren/Löschen) brachen auf Desktop-Breite in Safari in mehrere Zeilen um**, sobald eine Karte in der Liste vorgemerkt war (breiterer "Vorgemerkt"-Badge in der Status-Spalte drückte die Aktionen-Spalte zusammen — abhängig vom Browser-Layout-Verhalten, in Chrome nicht reproduzierbar gewesen). Der Pin-Toggle-Button, der testweise zusätzlich in der Aktionsleiste stand, wurde wieder entfernt (Vormerken bleibt über die Kartenansicht möglich) und die Aktionen-Spalte hat jetzt eine feste Mindestbreite (170px), die für die 4 Icons ausgelegt ist — dadurch kann keine andere Spalte sie mehr verengen.

---

## [3.2.30] - 2026-08-01

### Neu
- **"Für Drill vormerken":** Jede Karte lässt sich auf der Kartenübersicht (`edit.php`) einzeln manuell für den Drill-Modus vormerken (Pin-Icon in der Aktionsleiste sowie oben links auf der Karte in der Kartenansicht). Vorgemerkte Karten werden im Drill priorisiert gezeigt — Priorität konfigurierbar in den Einstellungen: "Absolut" (immer zuerst, solange welche vorgemerkt sind) oder "Gewichtet" (alle N Karten eine vorgemerkte einschieben, N konfigurierbar), während die normale 9:1-Rotation für die übrigen Karten parallel weiterläuft. Bei der konfigurierten Anzahl korrekter Antworten in Folge seit dem Vormerken wird die Vormerkung automatisch entfernt — ohne jeden Einfluss auf Leitner-Fach, Status oder den regulären Drill-Fortschritt (`drill_mastery`): das Leitner-System läuft für diese Karte während der gesamten Vormerkzeit unverändert normal weiter. Vorgemerkte Karten sind zudem von der `drill_too_hard`-Tagessperre ausgenommen. Neues Feld `card_progress.drill_pinned_correct`, bewusst getrennt von `drill_mastery` — sonst könnte eine bereits weit im Leitner-System fortgeschrittene Karte beim Vormerken/Drillen auf ein niedrigeres Fach zurückgestuft werden (siehe `docs/ANFORDERUNGEN.md`, Abschnitt "Manuelle Vormerkung für Drill").

---

## [3.2.29] - 2026-08-01

### Verbessert
- **MCP-Server: Lautschrift (phonetik_b) berücksichtigt jetzt explizit die Muttersprache der lernenden Person.** Bisher gingen die Anleitung und Feldbeschreibungen für `phonetik_b` stillschweigend von einer deutschsprachigen lernenden Person aus. Die Instruktionen in `initialize`, `add_cards` und `update_card` verlangen jetzt: ist zu Beginn eines Gesprächs nicht klar, in welcher Sprache bzw. mit welchen Lesekonventionen die Lautschrift geschrieben werden soll, muss dies explizit beim User erfragt werden, bevor `phonetik_b` befüllt wird.

---

## [3.2.28] - 2026-07-31

### Behoben
- **Modals in der Benutzerverwaltung waren auf dem iPhone nicht bedienbar** (E-Mail-Adresse setzen, Passwort zurücksetzen, Person löschen): Das Fenster öffnete sich zwar, liess sich aber weder ausfüllen noch schliessen. Ursache: die Modals standen im Markup zwischen den `<tr>`-Elementen der Personentabelle. Das ist ungültiges HTML — der Browser verschiebt die `<div>`s aus der Tabelle heraus, wodurch sie im umgebenden `.table-responsive`-Container mit `overflow-x: auto` landeten. Innerhalb eines Scroll-Containers verhält sich `position: fixed` auf iOS Safari anders als am Desktop, weshalb der Fehler dort nicht auftrat. Die Modals stehen jetzt ausserhalb von Tabelle und Scroll-Container.

Geprüft: im ausgelieferten HTML liegt keines der 12 Modals mehr innerhalb der Tabelle oder des `.table-responsive`-Containers; E-Mail setzen, Passwort zurücksetzen und Person löschen funktionieren unverändert; Darstellung im 430-px-Viewport kontrolliert. Andere Seiten mit Modals (`edit.php`, `lists.php`, `learn.php`, `drill.php`, Navbar-Konto-Modal) waren nicht betroffen — dort lagen die Modals schon immer ausserhalb.

---

## [3.2.27] - 2026-07-31

### Behoben
- `deploy.php` war auf dem iPhone winzig dargestellt: der Seite fehlte als einziger im Projekt das `<meta name="viewport">`-Tag. Mobiles Safari layoutet ohne dieses Tag mit rund 980 px Breite und skaliert die ganze Seite herunter. Da `deploy.php` als einzige Seite ohne Bootstrap auskommt (eigenes, dunkles Standalone-Layout), war das lange nicht aufgefallen — auch bei der Responsive-Prüfung in v3.2.25 nicht, weil die Seite einen Token benötigt und deshalb nicht mitgeprüft wurde.

### Verbessert
- `deploy.php` zusätzlich für schmale Screens angepasst: kleinere Aussenabstände, umbrechende Versionsblöcke, Buttons über die volle Breite (besser treffbar), Log-Ausgabe mit kleinerer Schrift und eigenem horizontalem Scrollbereich.
- **Statistik-Heatmap füllt jetzt die verfügbare Breite aus:** statt der festen 18 Wochen auf Mobilgeräten (v3.2.25) berechnet die Seite im Browser, wie viele Wochen tatsächlich hineinpassen, und blendet nur so viele der ältesten Wochen aus wie nötig. Dadurch zeigt jedes Gerät so viel Verlauf wie es darstellen kann — ein grosses Handy mehr als ein kleines, der Desktop weiterhin alle 52 Wochen. Passt sich beim Drehen des Geräts automatisch an. Ohne JavaScript bleiben alle Wochen sichtbar (dann horizontal scrollbar), die Heatmap ist eine Zusatzinfo und keine Funktion, die dadurch ausfällt.

**Hinweis zum Ausrollen:** `deploy.php` steht in der eigenen Skip-Liste und wird deshalb nie per Deployment überschrieben — diese Änderung muss manuell per FTP auf den Produktiv-Server kopiert werden.

---

## [3.2.26] - 2026-07-31

### Verbessert
- "Session abbrechen" ist jetzt ein Icon (`bi-x-lg`) statt eines Text-Buttons und steht während einer laufenden Session an **erster Stelle** in der Navbar — sowohl im Leitner-Modus (zentrale Navbar mit `$abort_url`) als auch im Drill-Modus (eigene Navbar, vor Timer und "gemeistert"-Zähler). Nebeneffekt auf dem Handy: die Drill-Navbar passt dadurch wieder in eine einzige Zeile, statt umzubrechen.

---

## [3.2.25] - 2026-07-31

### Verbessert
- **Statistik-Heatmap auf dem Handy:** zeigt unter 576 px nur noch die letzten 18 Wochen (~4 Monate) statt 52. Vorher musste man auf dem Handy erst seitlich scrollen, um überhaupt den aktuellen Zeitraum zu sehen. Umgesetzt per CSS-Media-Query auf einer einzigen Markup-Variante; die Monatsbeschriftung gibt es in zwei Varianten, weil sie bei gekürzter Ansicht anders positioniert werden muss. Auf dem Desktop unverändert alle 52 Wochen.
- **Responsive Design für iPhone überarbeitet** (Referenz iPhone 15 Pro Max, 430 px, gegengeprüft bei 375 px). Gemessen wurde die tatsächliche `scrollWidth` im Handy-Viewport — vorher schob die Navbar jede Seite breiter als das Display, sodass sich die gesamte App horizontal wegschieben liess:
  - Navbar: Icon-Leiste darf umbrechen, Personenname wird unter 576 px ausgeblendet (auf dem Desktop weiterhin sichtbar)
  - Kartenübersicht (`edit.php`): die vier Aktions-Icons brechen auf schmalen Screens in zwei Reihen um, statt aus der Tabelle zu laufen — die Status-Spalte bleibt erhalten
  - Einstellungen: Beschriftung steht auf dem Handy über dem Eingabefeld statt daneben, Textfelder über die volle Breite, Zahlenfelder schmal
  - Import: das CSV-Beispiel scrollt innerhalb seines eigenen Rahmens, statt die Seite zu verbreitern

Verifiziert mit Screenshots und Overflow-Messung bei 430 px und 375 px: Startseite, Statistik, Meine Listen, Kartenübersicht, Import, Einstellungen, Benutzerverwaltung, Hilfe, Mathe, Leitner und Drill haben keinen horizontalen Überlauf mehr.

---

## [3.2.24] - 2026-07-31

### Behoben
- **Mailversand auf Produktion kam nie beim Empfänger an**, obwohl `mail()` und der E-Mail-Test Erfolg meldeten. Ursache war nicht der Versand, sondern die Absenderadresse: die App läuft unter der Subdomain `lernen.springpunkt.ch` und verschickte dadurch als `no-reply@lernen.springpunkt.ch`. Diese Subdomain hat keinen eigenen SPF-Record (SPF wird nicht von der Hauptdomain vererbt), während die DMARC-Policy der Hauptdomain (`p=quarantine`) auch für Subdomains gilt — SPF ohne Ergebnis, kein DKIM, DMARC schlägt fehl, der Empfänger (Gmail) sortiert die Mail aus. Der Hoster hatte die Nachricht da längst angenommen, deshalb meldete die App Erfolg.

### Neu
- **Einstellung "Absender-E-Mail"** (`MAIL_FROM`, Einstellungen → Allgemein): Absenderadresse für Passwort-Reset und Test-Mail ist jetzt frei konfigurierbar und wird sowohl als `From:`-Header als auch als Envelope-Sender (`-f`) verwendet. Damit lässt sich als Hauptdomain senden, deren SPF den Mailserver des Hosters abdeckt. Leer = bisheriges Verhalten (`no-reply@` + Host der Basis-URL).
- Die Einstellungsseite warnt, wenn keine Absenderadresse gesetzt ist und die Basis-URL auf eine Subdomain zeigt — inklusive konkretem Vorschlag für die Hauptdomain-Adresse.

### Dokumentation
- `ANFORDERUNGEN.md`: neuer Abschnitt "Absenderadresse und Zustellbarkeit" mit dem konkreten Fehlerbild (SPF/DMARC bei Subdomains) als Referenz für künftige Umzüge.

---

## [3.2.23] - 2026-07-30

Behebt alle Punkte der Sicherheitsprüfung aus `Checkliste.md`. Alle Änderungen auf der lokalen Dev-Umgebung verifiziert (Migration, Login, beide Rate-Limits, Leitner-/Drill-Session, Einstellungen, Konto-Modal, Berechtigungsprüfungen, Host-Header-Fälschung).

### Behoben
- **`mcp.log` war über HTTP öffentlich lesbar** (Kartentexte, `list_id`, `person_id` im Klartext — Tokens waren nicht betroffen). Neue `.htaccess`-Regel sperrt alle `*.log`-Dateien im Web-Root.
- **Host-Header-Poisoning beim Passwort-Reset**: Link und Absenderdomain wurden aus `$_SERVER['HTTP_HOST']` gebaut, das vom Client kommt und fälschbar ist — ein Angreifer konnte einen Reset für eine fremde Adresse anfordern und dem Opfer eine echte Mail mit Link auf seine eigene Domain zustellen (Token-Diebstahl). Beides kommt jetzt aus der neuen Einstellung **Basis-URL** (`APP_BASE_URL`); ohne Konfiguration verschickt der Server keine Reset-Mail (Hinweis im Error-Log), der Fallback auf die aktuelle Adresse greift nur für lokale Clients.
- **`install.php` war ungeschützt**: kein CSRF-Token, und Aktionen liessen sich auf einem laufenden System auslösen. Jetzt CSRF-geschützt und komplett funktionslos, sobald eine Person existiert. Die Behauptung eines "Localhost-Guards" in `CLAUDE.md` war falsch und ist korrigiert — ein solcher Guard wäre mit der Ersteinrichtung auf Prod unvereinbar.
- **E-Mail-Adresse im "Konto"-Modal wurde nicht auf Format geprüft** (`change_own_email`), obwohl `users.php` das tut — der Wert wird später als Empfänger an `mail()` übergeben. Jetzt `FILTER_VALIDATE_EMAIL` an beiden Stellen.
- **`edit.php`**: Archivieren und Reaktivieren prüften nicht, ob die `card_id` zur geprüften Liste gehört — damit liessen sich eigene Fortschrittseinträge für fremde Karten anlegen (kein Zugriff auf fremde Daten, aber fehlende Bereichsprüfung).
- **`math.php`** gab die Duplikat-Warnung unescaped aus (`<?= $warning ?>`); der Listenname wird jetzt erst bei der Ausgabe escaped, statt halb-HTML in der Variable zu halten.
- **`stats.php`** akzeptierte eine beliebige `list_id` aus der URL — jetzt wird auf die erste eigene Liste umgeleitet.

### Neu
- **Rate-Limiting** für Login (10 Fehlversuche pro IP / 15 Min.) und "Passwort vergessen" (5 Anfragen pro IP / 60 Min.) über die neue Tabelle `auth_attempts`. Bewusst pro IP statt pro Konto, damit sich damit niemand fremde Personen aussperren kann; ein erfolgreicher Login löscht die Fehlversuche. Fehlt die Tabelle, wird nie blockiert.
- **Einstellung "Basis-URL"** (Einstellungen → Allgemein): Adresse der Installation für Links in E-Mails. Zeigt bei leerer Konfiguration die aktuelle Adresse als Vorschlag und einen Warnhinweis.

### Geändert
- Eigene `.htaccess` für `includes/` (`Require all denied`): der Schutz von `db-credentials.php`, `mcp-config.php` und `deploy-config.php` hing vorher allein daran, dass PHP die Dateien ausführt — bei ausgefallenem PHP-Handler wären sie im Klartext ausgeliefert worden.
- Sicherheits-Header in der `.htaccess`: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` (kein HSTS — das gehört auf vHost-Ebene).
- Subresource Integrity (`integrity` + `crossorigin`) für alle 40 CDN-Einbindungen von Bootstrap und Bootstrap Icons.
- Redirect-Ziele der Navbar-Aktionen laufen über die neue Funktion `safe_redirect_target()` (verwirft absolute, protokoll-relative sowie CR/LF-Ziele) — dieselbe Absicherung, die `learn.php`/`drill.php` schon hatten.
- `install.php` und `migrations.php` (Migration 14) legen die Tabelle `auth_attempts` an.

### Dokumentation
- `ANFORDERUNGEN.md`: neuer Abschnitt "Härtung aus der Sicherheitsprüfung", Basis-URL in der Einstellungstabelle, `auth_attempts` im Datenbankmodell, und explizit dokumentiert, dass der MCP-Token bewusst Vollzugriff auf alle Personen gewährt und wie ein Admin-Passwort zu behandeln ist.

---

## [3.2.22] - 2026-07-30

### Entfernt
- `ANFORDERUNGEN.md`: veralteten Absatz zur alten Bootstrap-Passwort-Migration ("Migration von der alten Version" — für Personen ohne Zugangsdaten) entfernt. Der zugehörige Code (ehemals Migration 6) wurde bereits in v3.2.21 aus `migrations.php` entfernt, der Absatz beschrieb daher ein Verhalten, das es im Code nicht mehr gibt.

---

## [3.2.21] - 2026-07-30

### Entfernt
- `includes/migrations.php`: historische Migrationen 1–13 (v3.0.0-Login-Modell bis zur `learning_sessions`/`session_lists`-Entfernung in v3.2.20) entfernt. Beide bekannten Installationen (Dev + Prod) sind bereits auf dem aktuellen Schema, und `install.php` bildet dieses Schema seit v3.2.20 vollständig von Grund auf ab — die alten Migrationen waren damit für jeden realistischen Fall reine No-Ops. Der Migrations-Mechanismus selbst bleibt bestehen (leere Liste, bereit für künftige Änderungen ab ID 14); die alten Schritte bleiben in der Git-Historie nachvollziehbar, falls je ein Backup von vor v3.2.20 wiederhergestellt werden muss.


---

## [3.2.20] - 2026-07-30

### Entfernt
- DB-Tabellen `learning_sessions` und `session_lists` entfernt — sie wurden bei jeder Leitner-/Drill-Session befüllt, aber nirgends gelesen (Streak/Heatmap in `stats.php` liefen schon immer ausschliesslich über `learning_events`). `learning_events.session_id` (Fremdschlüssel auf `learning_sessions`) entfällt; die Kaskade beim Personen-Löschen läuft jetzt über einen direkten Fremdschlüssel `learning_events.person_id → persons(id) ON DELETE CASCADE`.
- `drill.php`/`learn.php`: alle Schreibzugriffe auf `learning_sessions`/`session_lists` sowie die `session_id`-Verwaltung im Session-State entfernt.

### Geändert
- `install.php`: Tabellen-Definitionen für `learning_sessions`/`session_lists` entfernt, `learning_events` ohne `session_id`-Spalte, dafür mit direktem Fremdschlüssel auf `persons`. Zusätzlich `created_at`-Spalte ergänzt (existierte bereits live, fehlte aber bisher in `install.php`).
- `includes/migrations.php`: zwei neue Migrationen (12, 13) für bestehende Installationen — bauen das Schema automatisch auf den schlankeren Stand um, ohne Datenverlust in `learning_events`.

Getestet auf der lokalen Dev-Datenbank: Migration lief fehlerfrei durch (609 bestehende `learning_events`-Zeilen blieben erhalten), Leitner- und Drill-Session funktionieren mit dem neuen Schema, `stats.php` liefert weiterhin korrekte Werte, Personen-Löschen löscht die Lernhistorie weiterhin vollständig über die neue Kaskade.

---

## [3.2.19] - 2026-07-30

### Behoben
- `discover.php`: Beim Kopieren einer öffentlichen Liste ging die Lautschrift (`phonetic_b`) der einzelnen Karten stillschweigend verloren, obwohl sie beim Original vorhanden war — jetzt wird sie korrekt mitkopiert.
- `install.php`: `speech_lang_b` (Listen) und `phonetic_b` (Karten) fehlten in den `CREATE TABLE`-Definitionen und wurden erst über `migrations.php` nachgezogen — eine komplette Neuinstallation hatte dadurch entgegen der Doku-Aussage doch einen Migrationsschritt nötig. Beide Spalten sind jetzt direkt im Schema enthalten.

### Entfernt
- `home.php`: toter Code für einen "10 weitere Karten aktivieren"-Button (`action=activate_cards`, Funktion `activate_queued_cards()`) — es gab dafür im UI keinen Aufrufer mehr, die Aktivierung läuft seit Längerem automatisch beim Start einer Leitner-Session.

### Dokumentation
- `ANFORDERUNGEN.md` an mehreren Stellen an den tatsächlichen Code-Stand angeglichen (u.a. MCP-Tool `list_lists`, Leitner-Session-Zusammenfassung, Mathe-Generator-Obergrenze, `.gitignore`-Übersicht, Projektstruktur).

---

## [3.2.18] - 2026-07-30

### Behoben
- Statistik-Heatmap: Dark-Mode-Farbschema (`prefers-color-scheme: dark`) komplett entfernt, statt die Grautöne weiter anzupassen. Grund: der Rest der Anwendung hat kein Dark-Mode-Theme, ein Dark Mode nur für die Heatmap war inkonsistent und wirkte je nach Gerät/Browser (z.B. bei aktiviertem "Force Dark" ohne echten System-Dark-Mode) verwirrend. Die Heatmap zeigt jetzt immer die helle Farbpalette.

---

## [3.2.17] - 2026-07-30

### Behoben
- Statistik-Heatmap: Leerzellen-Farbe im Dark Mode war mit `#161b22` fast schwarz und kaum von der dunkelsten Grünstufe (`lvl-1`, `#0e4429`) zu unterscheiden. Jetzt ein klar erkennbares mittleres Grau (`#484f58`).

---

## [3.2.16] - 2026-07-30

### Neu
- `help.php`: Abschnitte "Für Admins: Einstellungen & Benutzerverwaltung" und "Für Technik-Fans: Karten per KI-Agent verwalten" sind jetzt nur noch für Admins sichtbar.

### Verbessert
- `help.php`, Drill-Modus: Hinweis ergänzt, dass bei neuen Listen dieselbe Karte anfangs mehrfach hintereinander gezeigt werden kann (beabsichtigt, kein Fehler) und dass es keine Leitner-Fach-Obergrenze für den Drill-Modus gibt.
- `help.php`, Aussprache: Hinweis ergänzt, dass Stimme/Klang vom Gerät bzw. Betriebssystem kommen, nicht von der App selbst.
- `help.php`, MCP-Server: ausführlichere Erklärung zur Einrichtung (ohne Zugangsdetails) sowie konkrete Beispiele der möglichen Aktionen (Personen/Listen abfragen, Karten hinzufügen/korrigieren, Import-CSV erstellen).

---

## [3.2.15] - 2026-07-29

### Verbessert
- Streak-Badge (🔥) in der Navbar ist jetzt klickbar und führt zu `stats.php`.

---

## [3.2.14] - 2026-07-29

### Verbessert
- `settings.php`: zweispaltiges Layout — links Allgemein/Leitner/Drill-Einstellungen, rechts E-Mail-Test und Deployment, beide Spalten gleich breit. Seite nutzt dafür die breite Container-Variante wie `home.php`/`lists.php` (kein `max-width`-Limit mehr).

---

## [3.2.13] - 2026-07-29

### Neu
- `deploy.php`: Link "← Zurück zu Einstellungen" auf jeder Ansicht (Status, Downgrade-Warnung, Ergebnis).

### Verbessert
- `stats.php`: Listen-Filter unterhalb der Heatmap zeigt nur noch aktive Listen — inaktive Listen erscheinen dort nicht mehr zur Auswahl.

---

## [3.2.12] - 2026-07-29

### Neu
- Einstellungen: neue Karte "E-Mail-Test" — E-Mail-Adresse eingeben und eine Test-Mail mit derselben Versandmethode wie "Passwort vergessen" verschicken (RFC-konform: kodierter Subject, `-f`-Parameter, UTF-8-Content-Type), Erfolgs-/Fehlermeldung je nach Ergebnis. Erlaubt, den Mailversand auf einem Server unabhängig vom Passwort-Reset-Ablauf zu prüfen.

---

## [3.2.11] - 2026-07-29

### Verbessert
- `help.php`: Abschnitt "Statistik & Streak" erklärt jetzt auch die Lernaktivitäts-Heatmap (Kennzahlen, Wochen-/Wochentag-Layout, Grünstufen nach Lernintensität, Hover-Tooltip) statt nur den Streak-Badge zu erwähnen.

---

## [3.2.10] - 2026-07-29

### Behoben
- Statistik-Heatmap: Datum im Hover-Tooltip zeigt jetzt `TT.MM.JJJJ` (z.B. "29.07.2026") statt `YYYY-MM-DD`.

---

## [3.2.9] - 2026-07-29

### Neu
- `deploy.php`: Warnt jetzt, bevor eine ältere GitHub-Version über eine neuere lokal installierte Version deployed würde (z.B. weil lokale Änderungen noch nicht gepusht wurden). Klick auf "Deploy starten" zeigt in diesem Fall zuerst eine Warnung mit separater Bestätigung ("Ja, trotzdem deployen") statt sofort zu deployen. Die Statusanzeige formuliert diesen Fall auch schon vor dem Klick korrekt, statt fälschlich "Neue Version auf GitHub verfügbar" zu zeigen.

---

## [3.2.8] - 2026-07-29

### Behoben
- `users.php`: "Endgültig löschen"-Button beim Personen-Löschen liess sich bei einem Nutzer trotz korrekt eingetipptem Namen nicht aktivieren (JS-Fehler `Cannot read properties of null`, reproduzierbar in Safari und Vivaldi). Statt die Ursache am bestehenden Mechanismus zu reparieren, wurde die Namenseingabe komplett durch eine Pflicht-Checkbox ersetzt ("Ich bin mir sicher...") — native HTML5-Validierung, kein Custom-JS mehr nötig, serverseitig zusätzlich über `confirm=1` abgesichert.

---

## [3.2.7] - 2026-07-29

### Verbessert
- Deploy-Ablauf zweistufig gemacht: Klick auf "Deploy-Status öffnen" in `settings.php` (früher "Deploy starten") navigiert nur noch zu `deploy.php` und löst kein Deployment mehr aus. `deploy.php` zeigt beim reinen Aufruf den Versionsvergleich sowie einen eigenen, CSRF-geschützten "Deploy starten"-Button — erst dessen Klick lädt das ZIP herunter und kopiert die Dateien. Nach erfolgreichem Deploy liest die Seite `config.php` erneut ein, sodass "Installiert" die tatsächlich neue Versionsnummer mit `=` zu GitHub zeigt, statt weiterhin den Stand von vorher anzuzeigen.

---

## [3.2.6] - 2026-07-29

### Verbessert
- `users.php`: "Admin entfernen"-Button ist jetzt unsichtbar (reserviert aber weiterhin den Platz für bündige Ausrichtung), wenn die Person der letzte verbleibende Admin ist — statt den Klick zuzulassen und erst danach per Fehlermeldung abzuweisen. Serverseitige Prüfung bleibt zusätzlich als Absicherung bestehen.

---

## [3.2.5] - 2026-07-29

### Behoben
- Dokumentation zur Aktiv/Inaktiv-Funktion (v3.3.0) war nach einem versehentlichen lokalen Deploy-Lauf (siehe unten) aus `CHANGELOG.md`/`ANFORDERUNGEN.md`/`Testing.md` verschwunden, obwohl der Code selbst über die Git-Historie unversehrt war — Einträge wiederhergestellt.

---

## [3.2.4] - 2026-07-29

### Verbessert
- `deploy.php`-Statusseite: obere Versionsanzeige jetzt mit "Bisher installiert" beschriftet (Stand vor diesem Deploy-Lauf), Erfolgsmeldung nennt explizit die neu installierte Versionsnummer statt nur generisch "Erfolgreich deployed" — vermeidet den Eindruck eines Widerspruchs zwischen oberer Anzeige und Erfolgsmeldung.

---

## [3.3.0] - 2026-07-29

### Neu
- Wortlisten haben jetzt einen Status Aktiv/Inaktiv (`lists.is_active`, Standard: aktiv). Auf der Startseite erscheinen inaktive Listen in einem eigenen, kompakteren Bereich unterhalb der aktiven Listen — ohne Warteschlangen-/Fällig-Anzeige und ohne Leitner-/Drill-Buttons. Umschalt-Button pro Liste (`bi-check-circle-fill`/"Inaktiv setzen" bzw. `bi-circle`/"Aktiv setzen"). Betrifft nur die Anzeige — Leitner-Fortschritt läuft im Hintergrund unverändert weiter.
- MCP-Server: `list_lists` zeigt standardmässig nur aktive Listen; ein neuer Parameter `include_inactive` erlaubt gezieltes Nachschlagen, wenn der User eine inaktive Liste explizit beim Namen nennt. `add_cards` bleibt davon unberührt und funktioniert weiterhin auch für inaktive Listen.

---

## [3.2.3] - 2026-07-29

### Behoben
- `forgot-password.php`: Reset-E-Mails kamen auf Produktion (HostFactory) nie an. Ursache: der Subject-Header enthielt ein rohes Umlaut-Zeichen ("zurücksetzen") statt RFC-1342-kodiertem Text — HostFactorys Outbound-Filter stuft nicht-ASCII-Zeichen in Mail-Headern als Spam ein und stellt die Nachricht kommentarlos nicht zu. Ausserdem fehlte der von HostFactory verlangte `-f`-Parameter (Return-Path/Envelope-Sender). Subject wird jetzt per `mb_encode_mimeheader()` kodiert, `-f` gesetzt, `Content-Type: text/plain; charset=utf-8` ergänzt.

---

## [3.2.2] - 2026-07-29

### Verbessert
- `forgot-password.php`: `mail()`-Fehler werden nicht mehr stillschweigend unterdrückt (`@` entfernt), Fehlschläge landen jetzt im PHP-Error-Log (`mail() fehlgeschlagen für person_id=...`) — Grundlage zur Diagnose des gemeldeten Problems, dass auf Produktion keine Reset-E-Mail ankommt.

---

## [3.2.1] - 2026-07-29

### Behoben
- `install.php`: Schritt 2 war seit dem Login-Modell-Umbau (v3.0.0) veraltet und schrieb ein globales Passwort in die `settings`-Tabelle, das von `index.php` gar nicht mehr gelesen wird — eine komplette Neuinstallation legte dadurch nie eine Person an und niemand konnte sich einloggen. Schritt 2 legt jetzt die erste Person direkt in `persons` an (automatisch als Admin), solange noch keine Person existiert; danach verweist die Seite auf die Benutzerverwaltung (`users.php`).
- `CLAUDE.md`: neue Regel im Dokumentations-Abschnitt — Änderungen am DB-Schema oder Login-/Auth-Ablauf müssen künftig immer gegen `install.php` geprüft werden, damit eine Neuinstallation ohne Migrationspfad nicht erneut kaputtgeht.

---

## [3.2.0] - 2026-07-29

### Neu
- `users.php`: Personen können jetzt vollständig gelöscht werden (Icon `bi-trash`, nicht bei der eigenen Zeile). Löscht unwiderruflich Listen, Karten, Lernfortschritt, Sessions und Events der Person über die bestehenden DB-Kaskaden, ausgeführt innerhalb einer expliziten Transaktion. Bestätigung per Modal mit Namenseingabe (Button erst aktiv, wenn der Name exakt eingetippt wurde). Selbstlöschen und Löschen des letzten verbleibenden Admins sind blockiert.

---

## [3.1.1] - 2026-07-29

### Behoben
- `users.php`: E-Mail-Adresse (beim Anlegen einer Person und beim Setzen/Ändern über das E-Mail-Modal) wird jetzt serverseitig auf gültiges Format geprüft (`FILTER_VALIDATE_EMAIL`) — ungültige Eingaben werden mit einer Fehlermeldung abgelehnt statt ungeprüft gespeichert zu werden.

### Verbessert
- `assets/style.css` wird auf allen Seiten jetzt mit Versions-Query-String (`?v=<?= APP_VERSION ?>`) eingebunden, damit Browser nach einem Release mit CSS-Änderungen die neue Datei zuverlässig laden statt eine veraltete Version aus dem Cache zu verwenden.
- Statistik: Lernaktivitäts-Heatmap wird jetzt horizontal zentriert dargestellt statt linksbündig (bleibt bei Überbreite weiterhin scrollbar).

---

## [3.1.0] - 2026-07-29

### Neu
- Statistik-Seite: neue Karte "Lernaktivität" (oberhalb des Listen-Filters, listenübergreifend) mit drei Kennzahlen (🔥 Aktueller Streak, Lerntage gesamt, Beste Woche) und einer Jahres-Heatmap der letzten 52 Kalenderwochen im GitHub-Contribution-Graph-Stil (5-stufige Grünskala je nach Lernintensität pro Tag, Monats- und Wochentag-Beschriftung, Hover-Tooltip mit Datum/Kartenanzahl). Reine CSS-Grid-Lösung ohne externe Library, horizontal scrollbar auf Mobile, hell/dunkel-kompatibel.

---

## [3.0.2] - 2026-07-29

### Verbessert
- `help.php`: Abschnitte "Leitner-Modus" und "Drill-Modus" ausführlicher formuliert und binden jetzt die tatsächlich konfigurierten Werte live in den Text ein (`LEITNER_INTERVALS`, `DAILY_CARD_LIMIT`, `LEITNER_DEFAULT_CARDS`, `DRILL_SESSION_SECONDS`, `DRILL_MASTERY_THRESHOLD`, `DRILL_TOO_HARD_LIMIT`, `DRILL_KNOWN_RATIO`) — die Hilfeseite zeigt dadurch immer die aktuell wirksamen Zahlen, statt statischer Beispielwerte.

---

## [3.0.1] - 2026-07-29

### Verbessert
- `users.php`: Aktions-Buttons pro Person sind jetzt Icons mit Tooltip statt Text-Buttons — E-Mail (`bi-envelope-plus`), Passwort zurücksetzen (`bi-key`), Admin-Status umschalten (`bi-person-dash`/„Admin entfernen" bzw. `bi-person-lock`/„Zu Admin machen").
- Versions-Vergleich (Einstellungen und `deploy.php`-Statusseite): zeigt jetzt `=` statt Pfeil, wenn installierte Version und GitHub-Version identisch sind.

---

## [3.0.0] - 2026-07-29

### Breaking
- **Globales Passwort entfernt.** Jede Person hat jetzt ein eigenes Login (bestehendes eindeutiges Namensfeld + eigenes Passwort) — der bisherige "Wer bist du?"-Auswahlschritt nach dem Login entfällt, Login führt direkt auf die eigene Startseite.
- **Admin-Rolle eingeführt** (`persons.is_admin`): nur Admins dürfen `settings.php`, `deploy.php` und die neue Benutzerverwaltung (`users.php`) öffnen, sowie als andere Person agieren ("Person wechseln", jetzt auf Admins beschränkt).
- **Migration**: bestehende Personen bekommen einmalig automatisch  gesetzt (per DB-Migration, läuft beim ersten Request nach dem Update auf beiden Umgebungen) — jede Person sollte es danach selbst ändern. Die Person "Beat" wird automatisch Admin.
- `deploy.php` verlangt jetzt zusätzlich zum Token eine aktive Admin-Session — ein reiner Token-Aufruf per Lesezeichen ohne Login funktioniert nicht mehr.

### Neu
- Neue Seite `users.php` (nur Admin): Personen anlegen, Passwort zurücksetzen, Admin-Status umschalten (mit Schutz gegen Entfernen des letzten Admins) — direkt über ein Icon (`bi-person-gear`) in der Navbar erreichbar, neben "Einstellungen".
- Jede Person kann ihr eigenes Passwort selbst ändern (Modal "Konto").
- Optionale **E-Mail-Adresse pro Person** — jede Person kann sie selbst im "Konto"-Modal setzen/ändern/entfernen, Admins zusätzlich für jede Person über `users.php`.
- **Passwort vergessen** (`forgot-password.php`, `reset-password.php`): Link auf der Login-Seite, E-Mail eingeben → falls einer Person zugeordnet, wird ein 60 Minuten gültiger Einmal-Link per E-Mail verschickt (PHPs `mail()`, kein SMTP). Token wird nur gehasht gespeichert, ist nach Gebrauch entwertet. Antwort ist immer identisch, unabhängig davon ob die E-Mail existiert (verhindert Enumeration). Ohne hinterlegte E-Mail bleibt der Reset weiterhin nur über den Admin (`users.php`) möglich.
- `help.php`: neuer Abschnitt "Für Admins: Einstellungen & Benutzerverwaltung", Login-Beschreibung auf das neue Modell aktualisiert.
- **Zentrale Navbar**: eine einzige Funktion `render_navbar($pdo)` in `includes/auth.php` rendert die Navbar auf jeder Seite (statt pro Seite dupliziertem HTML) — Icons/Reihenfolge werden nur an einer Stelle gepflegt. Reihenfolge: Streak-Badge, Personenname, Passwort ändern (`bi-key`), Person wechseln (`bi-person-lines-fill`, nur Admin), Benutzerverwaltung (`bi-person-gear`, nur Admin), Einstellungen (`bi-gear`, nur Admin), Logout (`bi-box-arrow-right`), Hilfe (`bi-info-lg`). Zugehörige Aktionen laufen über `handle_navbar_actions($pdo)`, ebenfalls zentral.
- **"Person wechseln" übernimmt jetzt exakt die Berechtigungen der Zielperson** (sieht z.B. keine Admin-Icons mehr, `settings.php`/`users.php` sind blockiert, wenn als Nicht-Admin agiert wird) — einzige Ausnahme: das Recht, die Person erneut zu wechseln, bleibt erhalten (verhindert Selbst-Aussperren). Technisch über ein separates Session-Flag `real_is_admin`, das beim Wechseln unverändert bleibt.

### Entfernt
- Die bisherige globale Passwortänderung in `settings.php` (bezog sich auf das jetzt entfernte globale Passwort).
- Die Personenwahl-Ansicht ("Wer bist du?") sowie "Neuen Benutzer hinzufügen" auf der Startseite — Personen werden jetzt ausschliesslich über `users.php` angelegt.
- Die "Benutzerverwaltung"-Karte in `settings.php` (ersetzt durch das direkte Navbar-Icon).
- Text-Beschriftungen "Einstellungen", "Person wechseln" und "Logout" in der Navbar (jetzt reine Icon-Buttons mit Tooltip).

### Behoben
- `users.php`: "Neue Person anlegen" und "Passwort zurücksetzen" verlangten das neue Passwort nur einmal (Tippfehler unbemerkt möglich) — beide Formulare verlangen es jetzt zweimal und prüfen auf Übereinstimmung, analog zu den bereits bestehenden Passwort-Formularen auf der Startseite und in `reset-password.php`.

---

## [2.8.0] - 2026-07-29

### Neu
- Neue Hilfeseite `help.php`: kompaktes Handbuch als Bootstrap-Accordion (Einstieg/Login, Wortlisten, Wörter hinzufügen, Leitner, Drill, Aussprache, Statistik/Streak, MCP-Server kurz erwähnt inkl. Hinweis auf nötige Konfiguration).
- Neuer Icon-Button (`bi-info-lg`, Tooltip "Hilfe") ganz rechts in der Navbar auf jeder Seite, nach dem Logout-Button — führt zu `help.php`.

---

## [2.7.14] - 2026-07-28

### Behoben
- Trotz v2.7.13 weiterhin vorzeitige Logouts auf Prod: Ursache war ein hoster-seitiger Cron-Job (typisch bei Debian/Ubuntu-Servern), der Sessions im System-Standardpfad unabhängig von jedem `ini_set()` der App aufräumt, basierend auf dem globalen `php.ini`-Wert. Sessions werden jetzt in einem eigenen Verzeichnis (`includes/sessions/`, per `.htaccess` vor Direktzugriff geschützt) gespeichert, das dieser Cron nicht anfasst. Da Debian/Ubuntu aus demselben Grund meist PHPs eigene Garbage-Collection global deaktivieren, wird sie für dieses Verzeichnis explizit wieder aktiviert, damit alte Sessions dennoch bereinigt werden.

---

## [2.7.13] - 2026-07-24

### Behoben
- Session-Timeout wurde trotz hoher Konfiguration (z.B. 1440 Min.) auf Prod schon nach kurzer Inaktivität abgemeldet: PHPs Standard-`session.gc_maxlifetime` (1440 **Sekunden** = 24 Min.) hat die Session serverseitig gelöscht, bevor der eigene, viel grosszügigere Inaktivitäts-Check greifen konnte. `SESSION_TIMEOUT` steuert jetzt zusätzlich `session.gc_maxlifetime` sowie die Cookie-Lebensdauer (`session_set_cookie_params`), inkl. `HttpOnly`, `SameSite=Lax` und automatisch `Secure` bei HTTPS.

---

## [2.7.12] - 2026-07-24

### Verbessert
- KI-Prompt in `import.php`: hat die Liste keinen hinterlegten Aussprache-Dialekt (`speech_lang_b`), weist der Prompt die KI jetzt an, den User explizit danach zu fragen und die Lautschrift-Spalte anschliessend passend auszufüllen — statt sie wie bisher stillschweigend leer zu lassen. MCP-Server (`add_cards`/`update_card`) bleibt bewusst unverändert (dort weiterhin: nur ausfüllen wenn `speech_lang_b` gesetzt ist).

---

## [2.7.11] - 2026-07-24

### Verbessert
- KI-Prompt in `import.php` und MCP-Server-Instruktionen (`initialize`, `list_lists`, `add_cards`) enthalten jetzt einen Dialekt-Standard für Englisch: hat die Liste ein `speech_lang_b` gesetzt, hat diese Definition Vorrang; ohne `speech_lang_b` gilt standardmässig britisches Englisch (en-GB) statt US-Englisch, ausser ausdrücklich anders gewünscht. Behebt das wiederkehrende Problem, dass US-Begriffe statt der gewünschten britischen Begriffe generiert wurden.

---

## [2.7.10] - 2026-07-24

### Verbessert
- MCP-Server: Agent-Anweisungen für den deutschen Begriff um eine Gross-/Kleinschreibungs-Regel ergänzt (`initialize`-Instructions, `add_cards`-/`update_card`-Beschreibungen und Feldbeschreibungen) — Nomen werden immer gross geschrieben, alle anderen Wortarten (Verben in Grundform, Adjektive, Adverbien etc.) klein, ausser am Satzanfang bei mehrteiligen Begriffen.
- CSV-Export (`export.php`) auf Lautschrift-Unterstützung geprüft: `phonetic_b` wird bereits seit v2.4.0 korrekt als 5. Spalte exportiert, keine Änderung nötig.

---

## [2.7.9] - 2026-07-24

### Neu
- `import.php`: fertiger, kopierbarer Prompt für KI-generierte Wortlisten im CSV-Format-Bereich — passt sich automatisch an Sprachpaar und Aussprache-Dialekt (`speech_lang_b`) der jeweiligen Liste an, inkl. nicht-rhotischer bzw. rhotischer Lautschrift-Regel.

---

## [2.7.8] - 2026-07-24

### Neu
- `edit.php`: zusätzliche Filter-Buttons "Fach 1"–"Fach 5" (analog zu den bestehenden Status-Filtern), zeigen nur aktive Karten der jeweiligen Leitner-Box mit Anzahl-Badge.

### Behoben
- Ungültige/manuell editierte `filter`-URL-Parameter werden jetzt auf einen Whitelist geprüft und fallen sicher auf "Alle" zurück (schliesst nebenbei eine ungeprüfte Reflektion des Parameters in Links).

---

## [2.7.7] - 2026-07-24

### Verbessert
- Startseite: Icon für "Karten bearbeiten" von `bi-card-list` auf `bi-pencil-square` geändert — hebt sich klarer von "Liste bearbeiten" (`bi-pencil`) ab.

---

## [2.7.6] - 2026-07-24

### Neu
- Startseite: zusätzlicher Icon-Button **"Liste bearbeiten"** (Stift) pro Liste, führt zu `lists.php?edit=X` (Name/Sprachen/Einstellungen der Liste selbst).

### Verbessert
- Der bisherige "Bearbeiten"-Button (Karten dieser Liste) hat ein neues Icon (`bi-card-list` statt Stift), um ihn vom neuen "Liste bearbeiten"-Button optisch zu unterscheiden — zeigt jetzt erkennbar, dass mehrere Einträge bearbeitet werden, nicht die Liste selbst.

---

## [2.7.5] - 2026-07-23

### Verbessert
- `SESSION_TIMEOUT` wird jetzt intern in **Minuten** gespeichert (vorher Sekunden) — entspricht damit direkt dem Wert, der in den Einstellungen eingegeben wird, keine verwirrende Umrechnung mehr beim Blick in `config-runtime.php`. Bestehende lokale Konfiguration entsprechend migriert.

---

## [2.7.4] - 2026-07-23

### Verbessert
- Einstellungen: Session-Timeout erlaubt jetzt Werte bis 1440 Minuten (24 Std.) statt bisher maximal 480 Minuten (8 Std.).

---

## [2.7.3] - 2026-07-22

### Behoben
- `deploy.php`: derselbe falsch gerichtete Pfeil im Versions-Vergleich wie zuvor in den Einstellungen (v2.7.1) — ebenfalls auf ← gedreht.

---

## [2.7.2] - 2026-07-22

### Verbessert
- Startseite: "⏳ in Warteschlange" und "📚 heute fällig" wurden bisher ganz ausgeblendet wenn 0 — jetzt immer sichtbar ("Keine in Warteschlange" / "Keine heute fällig"), mit ✅ statt 📚 als positive Rückmeldung sobald für eine Liste heute nichts mehr ansteht.

---

## [2.7.1] - 2026-07-22

### Behoben
- Einstellungen: Pfeil im Versions-Vergleich zeigte von "Installiert" zu "GitHub", obwohl die neuere Version ja von GitHub zur Installation fliesst — Richtung gedreht (← statt →).

---

## [2.7.0] - 2026-07-22

### Neu
- **Startseite:** Pro Liste wird zusätzlich zur Warteschlangen-Anzahl jetzt angezeigt, wie viele Leitner-Karten heute fällig sind ("📚 N heute fällig"). Ausserdem stehen oben rechts neben dem Listennamen zwei neue Icon-Buttons: "Bearbeiten" (Stift) und "Statistik" (führt direkt zur Statistik dieser einen Liste, nicht zur Übersicht). Der bisherige "Bearbeiten"-Textbutton im Footer entfällt dadurch.

---

## [2.6.2] - 2026-07-22

### Behoben
- `edit.php`: Klick auf "Bearbeiten" oder "Abbrechen" bei einem Eintrag weiter unten in der Liste liess die Seite an den Anfang springen — Scroll-Position bleibt jetzt erhalten (nutzt denselben Mechanismus wie bereits bei Speichern/Löschen).

### Verbessert
- `drill.php`: Veraltete Prüfung auf ein altes `$_SESSION['drill']`-Format entfernt — nicht mehr relevant, da immer nur eine einzige aktuelle Version im Einsatz ist.

---

## [2.6.1] - 2026-07-21

### Behoben
- Lautschrift-Konvention für nicht-rhotische Dialekte (`en-GB` u.ä.) präzisiert: "r" nach Vokal vor Konsonant/am Wortende wird nicht mehr mitgeschrieben (z.B. "thunder" → "THUN-duh" statt "THUN-der"), "r" bleibt nur vor einem folgenden Vokal (Silbenanfang oder verbindendes R). Betraf bisher generierte Lautschrift, die zu amerikanisch/rhotisch klang. Regel in den MCP-Agent-Anweisungen ergänzt, damit künftig generierte Lautschrift konsistent ist.

---

## [2.6.0] - 2026-07-21

### Neu
- **MCP: bestehende Karten lesen/ändern.** Zwei neue Tools ergänzen `add_cards`: `list_cards(list_id)` liest alle Karten einer Liste (inkl. `card_id` und `phonetik_b`), `update_card(card_id, ...)` ändert gezielt einzelne Felder einer bestehenden Karte, ohne die übrigen anzutasten. Ermöglicht Agenten (z.B. bei Wartungsarbeiten wie Rechtschreib-Korrekturen oder nachträglichem Ergänzen von Lautschrift) auch bestehende Karten zu bearbeiten, nicht nur neue anzulegen. Agent muss dem User vor jeder Änderung alt → neu zeigen und Bestätigung abwarten.

---

## [2.5.3] - 2026-07-21

### Verbessert
- Direktlink-Button in `edit.php`: Icon von Kette (🔗) auf Auge (👁) geändert, passend zur tatsächlichen Funktion (Karte ansehen statt Link kopieren/teilen).

---

## [2.5.2] - 2026-07-21

### Behoben
- Direktlink pro Karte zeigte nur eine markierte Position in der `edit.php`-Tabelle statt die Karte wie im Lernmodus. Jetzt öffnet der Link ein Modal mit der Karte im gleichen Flip-Kartenstil wie Leitner/Drill (inkl. Lautschrift und 🔊-Button auf der Rückseite).

---

## [2.5.1] - 2026-07-21

### Verbessert
- Direktlink pro Karte: statt Link in die Zwischenablage zu kopieren, ruft ein Klick die Karte jetzt direkt auf (einfacher `<a>`-Link statt Clipboard-API + JS).

---

## [2.5.0] - 2026-07-21

### Neu
- **Direktlink pro Karte:** Neuer Kettensymbol-Button ganz vorne in der Aktionsleiste jeder Karte (`edit.php`) kopiert eine URL in die Zwischenablage, die direkt zu dieser Karte springt — unabhängig vom aktuell gewählten Filter (wechselt automatisch auf "Alle"), die Zielkarte wird angesprungen und kurz hervorgehoben.

---

## [2.4.0] - 2026-07-21

### Neu
- **Lautschrift jetzt auch in CSV-Import/-Export und MCP:** CSV-Format um optionale 5. Spalte `phonetic_b` erweitert (rückwärtskompatibel — alte 4-spaltige CSVs funktionieren weiterhin), Vorlage und Import-Erklärung aktualisiert. MCP-Tool `add_cards` unterstützt neu `phonetik_b` mit derselben vereinfachten Lautschrift-Konvention (Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Zeichen) wie die manuelle Erfassung — Agent befüllt es nur bei Listen mit gesetztem `speech_lang_b`.

---

## [2.3.0] - 2026-07-21

### Neu
- **Lautschrift pro Karte:** Zusätzlich zur Audio-Wiedergabe kann pro Karte manuell eine Lautschrift (`phonetic_b`) erfasst werden — Eingabefeld erscheint in `edit.php` nur, wenn die Liste einen Aussprache-Sprachcode hat. Anzeige unter dem Begriff in Sprache B, sowohl in der Kartenübersicht als auch auf Leitner- und Drill-Karten.

### Verbessert
- `lists.php`: Button "Umbenennen" heisst neu "Bearbeiten" (passt besser, da dort auch Sprachen und Aussprache-Code geändert werden).
- `lists.php`: Eingabefeld für den Aussprache-Sprachcode auf realistische Breite verkleinert.
- `edit.php`: Container-Breite an home.php/lists.php angeglichen (kein eigenes `max-width` mehr), Beschreibungsfelder A/B sind jetzt mehrzeilige Textareas statt einzeiliger Inputs.

### Behoben
- Audio-Wiedergabe (🔊-Button): `utterance.lang` allein wurde von manchen Browsern/Geräten ignoriert, wodurch trotz z.B. `en-GB` die Standardstimme des Systems (teils Deutsch) erklang. Passende Stimme wird jetzt explizit über `speechSynthesis.getVoices()` gesucht und gesetzt.

---

## [2.2.0] - 2026-07-21

### Neu
- **Aussprache-Sprachcode & Audio:** Pro Liste kann ein BCP-47-Sprachcode (z.B. `en-GB`) für Sprache B hinterlegt werden — Eingabe mit Autovervollständigung (kuratierte Vorschläge + bereits verwendete Codes), serverseitig gegen ISO-639-1/ISO-3166-1 validiert und automatisch normalisiert (z.B. `EN-gb` → `en-GB`). Auf Leitner- und Drill-Karten erscheint dort, wo Sprache B angezeigt wird, ein 🔊-Button, der den Begriff per Web Speech API vorliest. Kein Button ohne hinterlegten Code. Der Code wird beim Kopieren einer öffentlichen Liste ("Entdecken") automatisch mitübernommen.
- **MCP:** `list_lists` gibt neu `speech_lang_b` zurück; der Agent wird angewiesen, Schreibweise und Wortwahl beim Hinzufügen von Karten an den hinterlegten Dialekt anzupassen (z.B. `en-GB` vs. `en-US`).

---

## [2.1.0] - 2026-07-20

### Neu
- **Liste migrieren:** Auf "Meine Listen" können alle Karten einer eigenen Liste per Button "Migrieren" (zwischen "Umbenennen" und "Löschen") in eine andere eigene Liste verschoben werden. Der komplette Lernfortschritt pro Karte (Leitner-Fach, Fälligkeitsdatum, Drill-Mastery) bleibt erhalten, da er an die Karte selbst hängt. Bei unterschiedlichen Sprachpaaren zwischen Quelle und Ziel erscheint eine Bestätigungswarnung. Duplikate in der Zielliste werden nicht geprüft. Die Quellliste bleibt danach leer bestehen.

### Verbessert
- `lists.php` nutzt jetzt dieselbe Container-Breite wie die Startseite (kein eigenes `max-width` mehr).

---

## [2.0.7] - 2026-07-04

### Behoben
- Toten Code entfernt: `stats.php` — ungenutzte Variable `$filter_list`; `learn.php` — `next_due_date` wurde an zwei Stellen mitgeladen, aber nie gelesen; `edit.php` — `created_at` und `next_due_date` wurden mitgeladen, aber nie gelesen (kein Verhaltensunterschied, nur überflüssige SELECT-Spalten/Variable entfernt).

---

## [2.0.6] - 2026-07-04

### Behoben
- `deploy.php`: veralteter Kommentar entfernt ("NICHT im Git-Repo: in .gitignore eingetragen" — stimmte seit v2.0.3 nicht mehr, die Datei ist seither versioniert).

---

## [2.0.5] - 2026-07-04

### Verbessert
- Reine Versions-Erhöhung ohne inhaltliche Code-Änderung (auf ausdrücklichen Wunsch).

---

## [2.0.4] - 2026-07-03

### Behoben
- MCP-Server: Agent-Anweisungen verschärft — der fremdsprachige Begriff darf nicht mehr in der deutschen Beschreibung auftauchen (beobachteter Fehlerfall: "bounced" in der Beschreibung zu "unzustellbar"). Neue klare Regeln pro Feld: Begriff (Fremdsprache) exakt, bei Verben Grundform, bei unregelmässigen Verben alle drei Formen; Begriff (Deutsch) exakt; Beschreibung (Fremdsprache) Beispielsatz mit dem exakten Begriff; Beschreibung (Deutsch) beschreibt die Bedeutung ohne den fremdsprachigen Begriff zu nennen, vermerkt ggf. unregelmässiges Verb, klärt bei Mehrdeutigkeit den Verwendungskontext.

---

## [2.0.3] - 2026-07-03

### Verbessert
- `deploy.php` ist nicht mehr in `.gitignore` — jetzt im Git-Repo versioniert (Historie/Diffs nachvollziehbar). Das Deploy-Token bleibt getrennt in `includes/deploy-config.php`, weiterhin gitignored und über die Skip-Liste geschützt. Produktions-Kopie von `deploy.php` muss bei Änderungen weiterhin manuell per FTP aktualisiert werden (schützt sich selbst vor Überschreiben durch den eigenen Deploy-Lauf).

---

## [2.0.2] - 2026-07-03

### Verbessert
- MCP-Server: Agent-Anweisungen (`initialize`-Instructions und `add_cards`-Tool-Beschreibung) ergänzt — bei Verben soll die Grundform (Infinitiv) in der Beschreibung ergänzt werden, bei unregelmässigen Verben zusätzlich ein Vermerk in der deutschen Beschreibung.

---

## [2.0.1] - 2026-07-03

### Behoben
- MCP-Server: Token wurde nur im `Authorization`-Header akzeptiert — ChatGPT und Claude Desktop (Browser-basierte Konnektoren) können diesen Header nicht setzen und scheiterten mit "Ungültiger Token". Fallback: Token wird jetzt zusätzlich als `?token=`-Query-Parameter akzeptiert.
- `.mcp.json` / `.mcp.json.example`: Prod-URL zeigte noch auf `/learner/mcp-server.php` (404 seit Verzeichnis-Refactoring) — korrigiert auf `mcp-server.php` an der Domain-Wurzel.

### Verbessert
- MCP `initialize`-Response enthält jetzt `instructions` mit dem Vokabel-Workflow (Person → Liste → Bestätigung → add_cards).
- `mcp-einrichtung.md`: Anleitungen für ChatGPT (Query-Token) und Claude Desktop (`mcp-remote`) ergänzt; Hinweis dass claude.ai Browser-Konnektoren OAuth voraussetzen und aktuell nicht funktionieren.

---

## [2.0.0] - 2026-06-30

### Neu
- MCP-Server (`mcp-server.php`): JSON-RPC 2.0 über HTTP POST (Streamable HTTP, sync), zustandslos
- Tool `list_persons`: gibt alle Personen zurück
- Tool `list_lists(person_id)`: gibt Listen einer Person zurück
- Tool `add_cards(list_id, cards[], force?)`: fügt Vokabelkarten ein, mit Duplikatprüfung
- Bearer-Token-Authentifizierung via `mcp-config.php` (gitignored)
- HTTPS-Pflicht auf Produktion
- Logging in `mcp.log` (gitignored)
- `.mcp.json.example` für Claude Code / VS Code (HTTP-Transport, Dev + Prod)
- `mcp-config.example.php` als Token-Vorlage
- `mcp-einrichtung.md`: Setup-Anleitung für Claude Code und n8n Cloud

---

## [1.4.3] - 2026-06-30

### Behoben
- settings.php: Deprecation-Warning `curl_close()` (seit PHP 8.5 deprecated, ohne Effekt seit PHP 8.0) — durch `unset($ch)` ersetzt

---

## [1.4.2] - 2026-06-30

### Behoben
- learn.php: Session startet ohne Karten wenn Karten keine card_progress-Einträge haben — lazy-Init stellt sicher dass alle Karten der Liste bei Session-Start als «queued» registriert werden
- learn.php: Statt leerer Zusammenfassung mit 0/0/0 wird jetzt «Keine Karten fällig» angezeigt, mit dem Datum wann die nächsten Karten fällig werden

---

## [1.4.1] - 2026-06-30

### Behoben
- learn.php: Undefined-array-key-Warnings auf der Session-Zusammenfassung wenn keine fälligen Karten vorhanden waren (stats-Array war leer)
- learn.php: XSS-Lücke — json_encode in script-Block ohne JSON_HEX_TAG; Listensprachnamen mit `</script>` hätten Script-Kontext brechen können
- auth.php: htmlspecialchars() in breadcrumb() ohne ENT_QUOTES — defensiver Flag ergänzt
- home.php: session_regenerate_id() fehlte beim switch_person-Handler (inkonsistent mit select_person/create_person)
- deploy.php: Vorhersagbarer Temp-Pfad via time() ersetzt durch tempnam() und random_bytes(8)
- deploy.php / settings.php: Deploy-Token wird nicht mehr als GET-Parameter übergeben (war in Server-Logs sichtbar) — Deploy-Button nutzt jetzt POST-Formular

---

## [1.4.0] - 2026-06-29

### Neu
- config-runtime.php (gitignored): speichert laufzeitspezifische Einstellungen — wird nie per Deploy überschrieben
- Einstellungen-Form auf allen Umgebungen sichtbar (nicht mehr localhost-only)

### Verbessert
- settings.php schreibt in config-runtime.php statt config.php — Prod-Einstellungen überleben jeden Deploy

---

## [1.3.0] - 2026-06-29

### Neu
- Einstellungen: Versions-Vergleich im Deployment-Bereich (installierte Version vs. GitHub-Version)
- deploy.php: install.php wird nie deployed — muss einmalig manuell hochgeladen und danach gelöscht werden

### Verbessert
- Lernkarten: Rahmen dunkler (#adb5bd statt #dee2e6), Schatten stärker — auf iPhone besser sichtbar

---

## [1.2.0] - 2026-06-29

### Neu
- Auto-Migration: fehlende DB-Spalten werden beim ersten Seitenaufruf automatisch ergänzt
- migrations.php: versionierte Migrationsliste — neue Migrationen am Ende anfügen
- Migration 1: `completed_at` in `learning_sessions` (behebt Fehler am Ende einer Leitner/Drill-Session)

---

## [1.1.0] - 2026-06-29

### Neu
- Einstellungen auf Produktion zugänglich (Login + CSRF-Schutz) — Config-Werte bleiben Localhost-only
- Einstellungen: Deploy-Bereich mit Link zu deploy.php (erscheint nur wenn deploy.php vorhanden)
- Einstellungen-Link in Navbar auf allen Umgebungen sichtbar

---

## [1.0.0] - 2026-06-29

### Erste stabile, getestete Version

- Leitner-System (5 Fächer, konfigurierbare Intervalle, Mehrfach-Listen, Click-to-Flip)
- Drill-Modus (Incremental Rehearsal, 9:1-Verhältnis, Timer, Leitner-Übergang)
- Listen verwalten, Karten bearbeiten, CSV-Import/Export, öffentliche Listen entdecken
- Mathe-Generator (Multiplikation + Division)
- Statistik-Dashboard, Streak-Badge in Navbar
- Einstellungen (Localhost): alle Konfigurationswerte, Passwort ändern
- install.php für Ersteinrichtung auf Produktion, db-credentials.example.php als Vorlage
- CSRF-Schutz, Prepared Statements, Session-Timeout, Passwort als Hash

---

## [0.8.0] - 2026-06-29

### Neu
- Einstellungen: Passwort ändern — aktuelles Passwort bestätigen, neues Passwort setzen (CSRF-geschützt, Login erforderlich)

### Behoben
- install.php: Localhost-Guard entfernt — Datei muss für Ersteinrichtung auf Produktion aufrufbar sein, wird danach manuell gelöscht
- index.php: Sicherheitswarnung blockiert App nur auf Produktion wenn install.php noch existiert — auf Localhost kein Block

---

## [0.7.1] - 2026-06-29

### Behoben
- Drill + Leitner: Karte erscheint aufgeklappt wenn Browser sie aus bfcache wiederherstellt — pageshow-Listener erzwingt Reload

### Verbessert
- Karten: Schriftgrösse Frage fs-2 (statt fs-1), Antwort fs-3 (statt fs-2) — lange Texte benötigen max. 2 Zeilen

---

## [0.7.0] - 2026-06-28

### Neu
- Streak-Badge (🔥) in Navbar auf allen Seiten sichtbar — Session-Cache, einmal täglich berechnet
- Einstellungen: Seitentitel (APP_NAME) konfigurierbar — wird in Navbar aller Seiten angezeigt
- Einstellungen: Default Kartenanzahl für Leitner-Session konfigurierbar (LEITNER_DEFAULT_CARDS)
- Einstellungen: 3 Gruppen (Allgemein / Leitner / Drill-Modus)

### Verbessert
- edit.php: Inline-Edit-Felder gleichbreit (flex col statt feste col-md-N), Speichern/Abbrechen als Icon-Buttons

---

## [0.6.5] - 2026-06-28

### Behoben
- Drill + Leitner: Session-Abbruch nach Bestätigung des Verlassen-Dialogs löscht jetzt korrekt den Session-Zustand — verhindert Geisterzustände im Hintergrund

### Verbessert
- Alle Content-Seiten (Listen, Bearbeiten, Mathe, Statistik, Einstellungen, Import, Entdecken) auf einheitliche Breite 960px vereinheitlicht

---

## [0.6.4] - 2026-06-28

### Verbessert
- settings.php: Alle 6 Konfigurationswerte auf einen Blick — Session-Timeout, Tägliches Karten-Limit, Drill-Timer, «Musste nachdenken»-Limit, Mastery-Schwelle, Bekannt/Neu-Verhältnis
- Zwei-Spalten-Layout (Desktop), gestapelt auf Mobile
- Ein "Alle speichern"-Button für alle Einstellungen

---

## [0.6.3] - 2026-06-28

### Neu
- settings.php: Localhost-only Einstellungsseite — Drill-Timer in Minuten anpassen, dauerhaft in config.php gespeichert
- Startseite (Navbar): "Einstellungen"-Link erscheint nur auf Localhost

---

## [0.6.2] - 2026-06-28

### Verbessert
- Leitner-Karte: identisches Design und Animation wie Drill-Karte (max-width 540px, p-5, fs-1/fs-2)
- Flip-Animation in Leitner: scaleX-Transform identisch zum Drill-Modus

---

## [0.6.1] - 2026-06-28

### Verbessert
- edit.php: Aktionsbuttons (Bearbeiten, Archivieren, Reaktivieren, Löschen) als Icon-only mit Tooltip — kompaktere Kartenliste
- edit.php: CSV Import / Export mit Icon + Text (Bootstrap Icons eingebunden)

---

## [0.6.0] - 2026-06-28

### Neu
- Drill-Modus komplett neu: Incremental Rehearsal — eine Karte nach der anderen statt 3 gleichzeitig
- 9:1-Verhältnis: bekannte Karten (drill_mastery >= 1) rotieren, neue Karten werden einzeln eingeführt
- Flip-Animation beim Aufdecken der Karte (CSS scaleX-Transform)
- "Gemeistert" = 3× hintereinander korrekt; "Musste nachdenken" setzt Zähler auf 0 zurück

### Verbessert
- Drill: "Musste nachdenken"-Limit gilt für alle Karten gleichermassen (bekannte und neue)
- Drill: drill_too_hard-Reset (lazy) jetzt auch beim Laden des Pools berücksichtigt — Karten die gestern zu schwer waren erscheinen heute wieder
- Drill: Abschluss zeigt "Musste nachdenken" in orange (statt rot) passend zum Button

### Behoben
- Drill: config-Konstante `DRILL_ACTIVE_CARDS` entfernt (war für 3-Karten-Modus, nicht mehr nötig)

---

## [0.5.0] - 2026-06-28

### Neu
- Warnung beim Verlassen einer aktiven Leitner- oder Drill-Session: Klick auf beliebigen Link zeigt Bestätigungs-Dialog ("Session wird beendet") mit Abbrechen-Option
- Breadcrumb-Navigation auf allen Seiten (inkl. Startseite): zeigt immer den vollständigen Pfad zur aktuellen Seite (z.B. `Startseite > Listen > Spanisch > Importieren`)
- Breadcrumb steht in eigenem Container — Position ist auf allen Seiten identisch, unabhängig von der Inhaltsbreite
- Mathe-Generator von Startseite nach "Meine Listen" (lists.php) verschoben

### Verbessert
- Redundante Zurück-Buttons und "Zur Startseite"-Links entfernt — Navigation läuft ausschliesslich über Breadcrumbs
- Startseite: Button "Meine Listen" (war "Verwalten") — Bezeichnung entspricht jetzt der Zielseite
- Import: Duplikat-Prüfung listenübergreifend — alle eigenen Karten werden verglichen, mit Wahl ob Duplikate importiert oder übersprungen werden
- Import: Duplikat-Vergleich ignoriert HTML-Tags in DB-Werten (strip_tags vor normalize) — Export→Import Roundtrip erkennt Duplikate korrekt

### Behoben
- Import: Export reimportieren schlug mit 0 importierten Karten fehl, weil Duplikate in anderen Listen die Prüfung blockierten

---

## [0.4.0] - 2026-06-28

### Neu
- Leitner-Setup: "← Startseite"-Button vor dem Sessionstart (kein Zurück-Button während aktiver Session)

### Verbessert
- Listen umbenennen (lists.php): PRG-Redirect nach Speichern — Editierformular verschwindet zuverlässig
- Karten bearbeiten (edit.php): PRG-Redirect für alle Aktionen (Bearbeiten, Archivieren, Reaktivieren, Löschen)
- Export: Kopfzeile enthält echte Sprachnamen statt "a;b;desc_a;desc_b"; Dateiname = Listenname; HTML-Tags bereinigt; Roundtrip-importierbar
- Import: Erste Nicht-Kommentar-Zeile wird immer als Kopfzeile behandelt (unabhängig vom Inhalt)
- Statistik: Direkt mit erster Liste vorausgewählt — kein globaler "Alle Listen"-Modus
- Öffentliche Listen (discover.php): Vorschau-Liste erscheint nicht mehr doppelt im Grid darunter
- Drill: Button- und Label-Text "Nicht gewusst" → "Musste nachdenken" (inkl. Abschlussseite und Statistik)
- Lernreihenfolge: Warteschlangen-Aktivierung (home.php, learn.php) und Leitner-Queue per `ORDER BY RAND()` gemischt
- Mathe-Generator: Typ-basierter Duplikat-Check (Multiplikation/Division) statt Namensprüfung — zweites Deck desselben Typs erfordert explizite Checkbox-Bestätigung; Formularwerte bleiben bei Warnung erhalten

### Behoben
- Discover: Angezeigte Vorschau-Liste erscheint nicht mehr im Grid der weiteren öffentlichen Listen

---

## [0.3.0] - 2026-06-28

### Verbessert
- Personenwahl: Formular "Neue Person erstellen" ist hinter Button "Neuen Benutzer hinzufügen" versteckt — verhindert versehentliches Erstellen einer neuen Person
- Mathe-Generator: Duplikat-Prüfung beim Listennamen — Warnung wenn eine Liste mit dem gewählten Namen bereits existiert
- Karten bearbeiten (edit.php): Scrollposition wird nach Archivieren, Bearbeiten und Löschen wiederhergestellt — kein Sprung an den Seitenanfang mehr

---

## [0.2.0] - 2026-06-28

### Neu
- Drill: Alle 3 aktiven Karten werden gleichzeitig angezeigt, in beliebiger Reihenfolge beantwortbar
- Drill + Leitner: Click-to-Flip — Karte per Klick/Tippen umdrehen, kein "Antwort zeigen"-Button mehr
- Drill: Countdown-Timer in der Navbar (verbleibende Zeit der Session)
- Testing.md mit strukturierten manuellen Testfällen eingeführt

### Verbessert
- Karten-Design einheitlich in Drill und Leitner (Rahmen, Rundungen, Schatten, grössere Schrift)
- Drill-Abschlussseite: "Erneut starten"-Button direkt zur gleichen Liste
- Leitner-Abschlussseite: "Neue Session"-Button behält die list_id
- Leitner-Setup: Richtungs-Labels zeigen echte Sprachnamen statt "A → B"

### Behoben
- Drill: Aufgedeckte Karten bleiben nach dem Beantworten einer anderen Karte sichtbar (Flip-Zustand via sessionStorage)
- Drill: Archivierte Karten erscheinen nicht mehr als Fallback wenn zu wenig aktive Karten vorhanden sind
- XSS-Lücke im Listen-Löschen-Dialog behoben (json_encode statt addslashes)

---

## [0.1.0] - 2026-06-01

### Initiales Release
- Leitner-System (5 Fächer, konfigurierbare Intervalle, Mehrfach-Listen)
- Drill-Modus (Incremental Rehearsal, Übergang ins Leitner)
- Listen verwalten (erstellen, umbenennen, löschen, öffentlich/privat)
- Karten bearbeiten (hinzufügen, bearbeiten, löschen, archivieren)
- CSV-Import mit Duplikat-Erkennung und Archiviert-Warnung
- CSV-Export (UTF-8 mit BOM, Semikolon)
- Öffentliche Listen entdecken & kopieren
- Statistik-Dashboard (Leitner-Fächer, Streak, Drill-Fortschritt)
- Mathe-Generator (Multiplikation + Division)
- Globales Passwort + Personenwahl
- CSRF-Schutz, Prepared Statements, Session-Timeout
