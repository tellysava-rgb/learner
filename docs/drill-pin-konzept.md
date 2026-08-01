# Konzept: „Für Drill vormerken" — Stand der Vorbesprechung

## Idee

Eine Karte im Leitner-System kann manuell mit „Für Drill vormerken" markiert werden.
Sie erscheint danach im Drill-Modus, wird dort priorisiert geübt und die Markierung
wird automatisch entfernt, sobald die konfigurierte Anzahl korrekter Antworten erreicht ist.
Leitner läuft während dieser Zeit **unverändert normal weiter** — die Karte wird nicht eingefroren.

Status kann jederzeit auch manuell wieder entfernt werden.

## Identifizierte Konflikte mit bestehender Logik

1. **`master_card()` koppelt Drill-Fortschritt bereits an den Leitner-Kasten**
   ([drill.php:219-245](../drill.php#L219-L245)): Beim Erreichen der Mastery-Schwelle
   wird heute automatisch `leitner_box` per fixer Tabelle `[1=>2, 2=>3, 3=>4]` gesetzt.
   Würde „Vormerken" denselben Zähler (`drill_mastery`) nutzen, könnte eine Karte, die über
   normales Leitner-Lernen bereits in einem hohen Fach steht, beim Vormerken/Drillen auf ein
   niedrigeres Fach **zurückgestuft** werden. → Vormerken braucht einen eigenen, komplett
   von `drill_mastery` getrennten Zähler, der `leitner_box`/`status`/`drill_mastery` nie anfasst.

2. **Der Drill-Pool ist heute bereits „alles ausser archiviert"**
   ([drill.php:160-187](../drill.php#L160-L187)): Jede aktive/queued Karte einer Liste ist
   schon Teil der known/new-Rotation (9:1). Eine vorgemerkte Karte mit `drill_mastery >= 1` würde
   darin untergehen und nur alle ~9-10 Karten drankommen — kein „intensives Üben". → Braucht einen
   eigenen, priorisierten dritten Pool statt nur eines Flags in der bestehenden Logik.

3. **`drill_too_hard`-Tagessperre würde die Karte gerade dann ausblenden, wenn sie am meisten
   Übung braucht** ([drill.php:259-270](../drill.php#L259-L270)). Bei 5× falsch verschwindet
   eine Karte normalerweise bis zum nächsten Tag. Für eine bewusst vorgemerkte Karte kontraproduktiv.

## Entschiedene Design-Fragen

| Frage | Entscheidung |
|---|---|
| Priorität im Drill | **Konfigurierbar**: Modus „Absolut" (immer zuerst, verdrängt known/new komplett solange Pool nicht leer) oder „Gewichtet" (alle N Karten eine vorgemerkte einschieben, N konfigurierbar) |
| Listen-Scoping | Bestehendes Verhalten bleibt — Karte erscheint nur, wenn ihre Liste für die Drill-Session ausgewählt wurde |
| `drill_too_hard`-Ausnahme | Vorgemerkte Karten sind davon ausgenommen — bleiben trotz wiederholtem Scheitern im aktiven Pool |

## Finales Datenmodell (vereinfacht — ein Feld statt zwei)

Ursprünglich zwei Spalten (`drill_pinned` Boolean + `drill_pinned_correct` Zähler) analog zum
Muster `drill_mastery`/`drill_too_hard`. Das Muster passt hier aber nicht: Bei Mastery/Too-Hard
sind Zähler und Flag unabhängige Achsen; beim Vormerken existiert der Zähler nur, *während* die
Karte vorgemerkt ist — beide Zustände sind immer synchron. Deshalb genügt ein Feld mit Sentinel-Wert:

```
ALTER TABLE card_progress ADD COLUMN drill_pinned_correct TINYINT NULL DEFAULT NULL;
```

- `NULL` = nicht vorgemerkt
- `0 .. N-1` = vorgemerkt, so viele korrekte Antworten seit dem Vormerken
- Schwelle (`DRILL_MASTERY_THRESHOLD`, bereits konfigurierbar) erreicht → zurück auf `NULL`
  (automatisches Entfernen des Status)
- Manuelles Entfernen in `edit.php` → `NULL`
- Manuelles Vormerken in `edit.php` → `0`

Kein Eingriff in `leitner_box`, `status` oder `drill_mastery` an irgendeiner Stelle dieser Funktion.

## Settings (`settings.php`, Block „Drill-Modus")

Neue Einstellungen, analog zum bestehenden Muster (`drill_known_ratio` etc.):

- **Vormerkungs-Priorität**: Radio „Absolut" / „Gewichtet"
- Bei „Gewichtet": Zahlenfeld „vorgemerkte Karte alle N Karten"

Neue Runtime-Konstanten (analog `DRILL_KNOWN_RATIO`), z.B. `DRILL_PIN_MODE`, `DRILL_PIN_RATIO`.

## Ablauf `drill.php`

- `load_drill_pool()` liefert neu drei Arrays: `known` / `new` / `pinned`
  (`pinned` = Karten mit `drill_pinned_correct IS NOT NULL`, `status != 'archived'`,
  innerhalb der für die Session gewählten Listen)
  **Eine Karte landet ausschliesslich in `pinned` — nie zusätzlich in `known`/`new`.**
  Split-Reihenfolge: zuerst auf `drill_pinned_correct IS NOT NULL` prüfen, erst danach
  (nur für die übrigen Karten) nach `drill_mastery` in `known`/`new` aufteilen.
- `next_drill_card()`: dritter Pool `pool_pinned`
  - Modus „Absolut": solange `pool_pinned` nicht leer, immer daraus ziehen
  - Modus „Gewichtet": eigener Zyklus-Zähler schiebt alle N Karten eine gepinnte Karte ein,
    known/new-9:1-Rotation läuft parallel normal weiter
- **Antwort-Handler verzweigt zuerst nach Pin-Status, bevor irgendeine Zähler-Logik läuft:**
  „Ist `card_id` aktuell in `pool_pinned`?" → ja: eigener Zweig unten. Nein: bestehende
  known/new-Logik (`session_correct`/`session_unknown`, `master_card()`, `mark_too_hard_card()`)
  unverändert wie heute.
- **Pin-Zweig, richtig:** `drill_pinned_correct++`; bei Erreichen der Schwelle →
  `drill_pinned_correct = NULL`, Karte raus aus `pool_pinned`. `master_card()` wird in diesem
  Zweig **nie** aufgerufen — `leitner_box`/`status`/`drill_mastery` bleiben in jedem Fall unverändert.
- **Pin-Zweig, falsch:** `drill_pinned_correct = 0` (Reset, wie `session_correct`),
  **keine** `drill_too_hard`-Sperre — Karte bleibt im `pool_pinned`. `mark_too_hard_card()` wird
  in diesem Zweig ebenfalls nie aufgerufen.

## UI

- `edit.php`: Toggle-Button „Für Drill vormerken" / „Vormerkung entfernen" im bestehenden
  Button-Stil (neben Archivieren/Reaktivieren), gesperrt bei archivierten Karten. Neuer
  Filter-Tab „vorgemerkt".
- `drill.php`: kleines Badge auf der Karte, wenn sie wegen Vormerkung priorisiert gezeigt wird.

## Weitere Implementierungs-Risiken

**A) Fehlende `card_progress`-Zeile beim Vormerken**
Zeilen werden bisher lazy angelegt — erst beim ersten Leitner-Sessionstart für die Liste
([learn.php:86-89](../learn.php#L86-L89)). Vormerken vor der ersten Leitner-Session (z.B. direkt
nach CSV-Import) träfe sonst 0 Zeilen und liefe lautlos ins Leere. Toggle in `edit.php` muss vorher
`INSERT IGNORE ... status='queued'` ausführen, wie beim Leitner-Start.

**B) Race Condition: Vormerkung während laufender Drill-Session entfernen**
Der Drill-Pool wird einmalig bei Sessionstart in `$_SESSION['drill']` geladen. Wird die Vormerkung
über `edit.php` (z.B. zweiter Tab) mitten in der Session entfernt, weiss die laufende Session davon
nichts. Eine danach im Drill richtig beantwortete Karte darf die Vormerkung **nicht wiederbeleben**.
Lösung: Increment bedingt ausführen — `UPDATE card_progress SET drill_pinned_correct = ...
WHERE person_id=? AND card_id=? AND drill_pinned_correct IS NOT NULL` — wird dann bei
zwischenzeitlich entfernter Vormerkung automatisch zum No-Op.

**C) Rotation bei mehreren gleichzeitig vorgemerkten Karten**
`pool_pinned` muss wie `pool_known` als FIFO rotieren (`array_shift` + wieder anhängen), sonst
würde bei mehreren gleichzeitig vorgemerkten Karten immer nur dieselbe erste gezogen statt reihum.

**D) Absolut-Modus kann eine ganze Session dominieren**
Sind viele Karten gleichzeitig vorgemerkt, verdrängen sie im Absolut-Modus für die komplette
Session alles andere (known/new kommt nicht mehr dran). Korrekt gemäss Definition, aber potenziell
überraschend — Hinweis im UI erwägen (z.B. „X Karten aktuell vorgemerkt — verdrängen im
Absolut-Modus alle anderen").

**E) Pin-Fortschritt ist bewusst sitzungsübergreifend**
Anders als der normale Mastery-Zähler `session_correct` (nur `$_SESSION`, startet jede Session bei 0)
ist `drill_pinned_correct` in der DB persistiert — Fortschritt bleibt über mehrere Drill-Sessions
hinweg erhalten, bis die Schwelle erreicht ist. Bewusste Design-Entscheidung, hier festgehalten
damit sie später nicht als Inkonsistenz zum Session-Zähler missverstanden wird.

## Folgeaufgaben bei Umsetzung (laut CLAUDE.md-Pflicht)

- `install.php`: `CREATE TABLE card_progress` um `drill_pinned_correct` ergänzen (Neuinstallation
  durchläuft keine Migrationen)
- `includes/migrations.php`: Spalte für bestehende Installationen nachziehen
- `docs/ANFORDERUNGEN.md`: Abschnitt zu Drill-Kartenauswahl und Leitner-Status ergänzen
- `docs/Testing.md`: neue Testfälle in Sektion 4 (Leitner) und 5 (Drill), Release-Verweis ergänzen
- `CHANGELOG.md` + `APP_VERSION` beim Release
