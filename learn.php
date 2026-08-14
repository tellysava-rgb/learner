<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tags.php';
require_person();

$person_id   = $_SESSION['person_id'];

// -------------------------------------------------------
// Session-State für laufende Lernsession
// Wird in $_SESSION['learn'] gespeichert
// -------------------------------------------------------

// Navbar-Aktionen (Logout, eigenes Konto, Person wechseln)
if (in_array($_POST['action'] ?? '', ['logout', 'switch_to_person', 'change_own_password', 'change_own_email'], true)) {
    csrf_validate();
    handle_navbar_actions($pdo);
}

// -------------------------------------------------------
// PHASE: Setup — Session abbrechen → zurück zur Startseite
// -------------------------------------------------------
if (($_GET['action'] ?? '') === 'setup') {
    unset($_SESSION['learn']);
    $to = $_GET['to'] ?? 'home.php';
    if (str_contains($to, '://') || str_starts_with($to, '//')) {
        $to = 'home.php';
    }
    header('Location: ' . $to);
    exit;
}

// Verfügbare eigene Listen laden (nur aktive — inaktive Listen stehen zum Lernen nicht zur Wahl)
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.language_a, l.language_b
    FROM lists l
    WHERE l.person_id = ? AND l.is_active = 1
    ORDER BY l.last_used_at DESC, l.name
");
$stmt->execute([$person_id]);
$all_lists = $stmt->fetchAll();

// Verfügbarkeit pro Liste für den Setup-Hinweis ("Heute maximal N Karten verfügbar", siehe unten):
// bereits fällige Karten + wie viele neue Karten das Tageslimit (DAILY_CARD_LIMIT) für diese Liste
// aus der Warteschlange noch zulassen würde. Ohne diese Zahlen wirkt eine kleiner als gewünscht
// ausgefallene Session wie ein Fehler, obwohl sie beabsichtigt gedrosselt ist.
$list_availability = [];
if ($all_lists) {
    $list_ids_all = array_column($all_lists, 'id');
    $ph = implode(',', array_fill(0, count($list_ids_all), '?'));
    $today_str = today();
    $stmt = $pdo->prepare("
        SELECT c.list_id,
               SUM(CASE WHEN cp.status = 'queued' THEN 1 ELSE 0 END) AS queued,
               SUM(CASE WHEN cp.status = 'active' AND cp.next_due_date <= ? THEN 1 ELSE 0 END) AS due_today,
               SUM(CASE WHEN cp.status = 'active' AND cp.next_due_date = ? AND cp.leitner_box = 1 THEN 1 ELSE 0 END) AS already_activated
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id IN ($ph)
        GROUP BY c.list_id
    ");
    $stmt->execute(array_merge([$today_str, $today_str, $person_id], $list_ids_all));
    foreach ($stmt->fetchAll() as $row) {
        $list_availability[(int)$row['list_id']] = [
            'queued'    => (int) $row['queued'],
            'due_today' => (int) $row['due_today'],
            'already_activated' => (int) $row['already_activated'],
        ];
    }
    // Listen ohne card_progress-Zeilen (z.B. gerade erst angelegt, noch nie gelernt) fehlen
    // oben in der Abfrage komplett — mit Nullen auffüllen statt sie stillschweigend zu überspringen.
    foreach ($list_ids_all as $lid) {
        if (!isset($list_availability[$lid])) {
            $list_availability[$lid] = ['queued' => 0, 'due_today' => 0, 'already_activated' => 0];
        }
    }
}

// Vorausgewählte Liste aus URL (von home.php)
$preset_list_id = intval($_GET['list_id'] ?? 0);
$preset_list    = null;
if ($preset_list_id) {
    foreach ($all_lists as $l) {
        if ((int)$l['id'] === $preset_list_id) {
            $preset_list = $l;
            break;
        }
    }
}

// Themen-Session: ergänzt die Listenauswahl oben, ersetzt sie nicht. Angebotene Tags richten sich
// nach dem Kontext — mit vorausgewählter Liste (?list_id=…) nur deren eigene Tags (passend zum
// gerade betrachteten Deck), ohne Preset alle Tags über sämtliche eigene aktiven Listen hinweg.
// Ausgewählt wird trotzdem immer listenübergreifend (siehe get_person_tag_card_ids() unten) — nur
// die Vorschlagsliste ist eingeschränkt. Vorausgewählter Tag aus URL (z.B. "Nochmal"-Link nach
// einer Themen-Session, siehe $repeat_url unten) — nur übernehmen, wenn der Tag tatsächlich
// noch existiert, sonst wortlos ignorieren statt eine ungültige Auswahl vorzubelegen.
$available_tags = $preset_list ? get_list_tags($pdo, (int) $preset_list['id']) : get_person_tags($pdo, $person_id);
$preset_tag  = trim($_GET['tag'] ?? '');
if ($preset_tag !== '' && !in_array($preset_tag, $available_tags, true)) {
    $preset_tag = '';
}

// -------------------------------------------------------
// POST: Session konfigurieren und starten
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin') {
    csrf_validate();

    $direction  = resolve_direction($_POST['direction'] ?? null);
    $card_limit = max(1, intval($_POST['card_limit'] ?? LEITNER_DEFAULT_CARDS));
    $tag        = trim($_POST['tag'] ?? '');

    // Nur im Tag-Modus gesetzt: schränkt beide Hilfsfunktionen unten zusätzlich auf die
    // getaggten Karten ein (statt alle Karten der beteiligten Listen) — die eigentliche
    // Themen-Session-Logik.
    $card_ids_filter = null;
    $daily_limit     = DAILY_CARD_LIMIT;

    if ($tag !== '') {
        // Themen-Session: listenübergreifend, nur eigene Karten mit diesem Tag (aktive Listen)
        $card_ids_filter = get_person_tag_card_ids($pdo, $person_id, $tag);
        if (!$card_ids_filter) {
            $setup_error = 'Keine Karten mit diesem Tag gefunden.';
            goto render_setup;
        }
        $card_ph = implode(',', array_fill(0, count($card_ids_filter), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT l.id, l.language_a FROM lists l JOIN cards c ON c.list_id = l.id WHERE c.id IN ($card_ph)");
        $stmt->execute($card_ids_filter);
        $valid_rows = $stmt->fetchAll();
        $valid_ids  = array_column($valid_rows, 'id');
        // Tageslimit ist im Tag-Modus bewusst überschreibbar (geschützter Default, siehe
        // ANFORDERUNGEN.md "Themen-Session") — ausserhalb des Tag-Modus fest bei DAILY_CARD_LIMIT.
        // Kein eigenes Zahlenfeld dafür: die Menge steuert sich über das bestehende "Kartenanzahl"
        // ($card_limit) — die Checkbox (daily_limit_override) bestätigt nur, dass dafür auch mehr
        // als DAILY_CARD_LIMIT neue Karten aus der Warteschlange aktiviert werden dürfen. max() mit
        // DAILY_CARD_LIMIT verhindert, dass eine bewusst klein gewählte Kartenanzahl (< 10) das
        // Tageslimit versehentlich UNTER den Default drückt — die Checkbox darf das Limit nur
        // anheben, nie verschärfen. Ohne die Checkbox bleibt es beim geschützten Default, auch bei
        // einem manipulierten Formular.
        if (!empty($_POST['daily_limit_override'])) {
            $daily_limit = max($card_limit, DAILY_CARD_LIMIT);
        }
    } else {
        $list_ids = array_map('intval', array_filter((array)($_POST['list_ids'] ?? [])));
        if (!$list_ids) {
            $setup_error = 'Bitte mindestens eine Liste auswählen.';
            goto render_setup;
        }

        // Eigentümerschaft prüfen
        $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, language_a FROM lists WHERE id IN ($placeholders) AND person_id = ?");
        $stmt->execute(array_merge($list_ids, [$person_id]));
        $valid_rows = $stmt->fetchAll();
        $valid_ids  = array_column($valid_rows, 'id');
    }

    if (!$valid_ids) {
        $setup_error = 'Keine gültige Liste ausgewählt.';
        goto render_setup;
    }

    // Besteht die Auswahl ausschliesslich aus Mathe-Listen, ist nur "Aufgabe → Ergebnis" sinnvoll —
    // unabhängig vom Formularwert erzwingen (Formular blendet die anderen Optionen zwar aus, das
    // ersetzt aber keine serverseitige Prüfung).
    if (!array_filter($valid_rows, fn($r) => !is_math_list($r))) {
        $direction = 'a_to_b';
    }

    $today = today();

    // Karten ohne card_progress-Eintrag für diese Person initialisieren (lazy init). Im Tag-Modus
    // bewusst für ALLE Karten der beteiligten Listen (nicht nur die getaggten) — unschädlich,
    // gleiches Lazy-Init wie im Listen-Modus, hält den Code hier einfach.
    $init_ph = implode(',', array_fill(0, count($valid_ids), '?'));
    $pdo->prepare("
        INSERT IGNORE INTO card_progress (person_id, card_id, status)
        SELECT ?, c.id, 'queued' FROM cards c WHERE c.list_id IN ($init_ph)
    ")->execute(array_merge([$person_id], $valid_ids));

    // Täglich (im Tag-Modus: einstellbar) neue Karten aktivieren (queued → active)
    activate_daily_cards($pdo, $person_id, $valid_ids, $today, $card_ids_filter, $daily_limit);

    // Karten für diese Session laden (mit Priorisierung)
    $queue = build_leitner_queue($pdo, $person_id, $valid_ids, $today, $card_limit, $card_ids_filter);

    if (!$queue) {
        // Nächsten Fälligkeitstermin bestimmen (im Tag-Modus nur unter den getaggten Karten)
        $card_filter_sql = '';
        $card_filter_params = [];
        if ($card_ids_filter !== null) {
            $cph = implode(',', array_fill(0, count($card_ids_filter), '?'));
            $card_filter_sql = " AND cp.card_id IN ($cph)";
            $card_filter_params = $card_ids_filter;
        }
        $next_stmt = $pdo->prepare("
            SELECT MIN(cp.next_due_date) FROM card_progress cp
            JOIN cards c ON c.id = cp.card_id
            WHERE cp.person_id = ? AND c.list_id IN ($init_ph){$card_filter_sql}
              AND cp.status = 'active' AND cp.next_due_date > ?
        ");
        $next_stmt->execute(array_merge([$person_id], $valid_ids, $card_filter_params, [$today]));
        $next_due = $next_stmt->fetchColumn();
        $_SESSION['learn_done'] = [
            'stats'    => ['correct' => 0, 'incorrect' => 0, 'promoted' => 0],
            'next_due' => $next_due ?: null,
        ];
        header('Location: learn.php?done=1' . ($tag === '' ? '&list_id=' . $valid_ids[0] : ''));
        exit;
    }

    // last_used_at für alle beteiligten Listen aktualisieren — im Tag-Modus für alle Listen, die
    // mindestens eine Karte mit diesem Tag haben, unabhängig davon ob an diesem Tag tatsächlich
    // eine ihrer Karten gezogen wurde (konsistent mit dem bestehenden Mehrfach-Listen-Verhalten).
    $upd = $pdo->prepare("UPDATE lists SET last_used_at = NOW() WHERE id = ?");
    foreach ($valid_ids as $lid) {
        $upd->execute([$lid]);
    }

    // Session-State initialisieren
    $_SESSION['learn'] = [
        'list_ids'      => $valid_ids,
        'tag'           => $tag !== '' ? $tag : null,
        'direction'     => $direction,
        'queue'         => $queue,
        'current_index' => 0,
        'answered'      => [], // card_id => attempts: 1 oder 2
        'stats'         => ['correct' => 0, 'incorrect' => 0, 'promoted' => 0],
        'today'         => $today,
        'retry_queue'   => [], // card_ids die nochmal kommen (nach falscher Antwort)
    ];

    header('Location: learn.php');
    exit;
}

// -------------------------------------------------------
// POST: "Für Drill vormerken" direkt während der Leitner-Session umschalten
// Rührt Queue/Stats/Fortschritt der laufenden Session nicht an — nur der Pin-Status der
// aktuell angezeigten Karte ändert sich, danach wird dieselbe Karte erneut angezeigt.
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_pin' && isset($_SESSION['learn'])) {
    csrf_validate();

    $card_id = intval($_POST['card_id'] ?? 0);
    $queue_state = $_SESSION['learn'];

    // Nur die aktuell angezeigte Karte darf umgeschaltet werden — verhindert, dass über eine
    // manipulierte card_id der Pin-Status fremder Karten geändert wird.
    if ($card_id !== ($queue_state['queue'][0] ?? null)) {
        header('Location: learn.php');
        exit;
    }

    // Zeile könnte noch nicht existieren (z.B. Karte frisch importiert) — vorher sicherstellen.
    $pdo->prepare("
        INSERT INTO card_progress (person_id, card_id, status)
        VALUES (?, ?, 'queued')
        ON DUPLICATE KEY UPDATE status = status
    ")->execute([$person_id, $card_id]);

    $stmt = $pdo->prepare("SELECT drill_pinned_correct FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $is_pinned = $stmt->fetchColumn() !== null;

    $new_value = $is_pinned ? null : 0;
    $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = ? WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$new_value, $person_id, $card_id]);

    header('Location: learn.php');
    exit;
}

// -------------------------------------------------------
// POST: Karte beantworten (während Session)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'answer' && isset($_SESSION['learn'])) {
    csrf_validate();

    $state     = &$_SESSION['learn'];
    $card_id   = intval($_POST['card_id'] ?? 0);
    $result    = $_POST['result'] ?? ''; // 'correct' | 'incorrect' | 'skip'
    $today     = $state['today'];

    if (!in_array($result, ['correct', 'incorrect', 'skip'])) {
        header('Location: learn.php');
        exit;
    }

    $intervals     = LEITNER_INTERVALS;
    $debug_enabled = DEBUG_MODE && !empty($_SESSION['is_admin']);

    if ($result === 'skip') {
        // Übersprungen → ans Ende der Queue, next_due_date unverändert
        $state['queue'][] = $card_id;
        $stmt = $pdo->prepare("INSERT INTO learning_events (person_id, card_id, result, learn_date) VALUES (?,?,?,?)");
        $stmt->execute([$person_id, $card_id, 'skipped', $today]);
        array_shift($state['queue']);
        if ($debug_enabled) {
            $_SESSION['debug_last_answer'] = [debug_card_label($pdo, $card_id), 'übersprungen', 'nichts geändert'];
        }
        header('Location: learn.php');
        exit;
    }

    // Bestimmen ob erster oder zweiter Versuch
    $is_retry = isset($state['answered'][$card_id]);
    $state['answered'][$card_id] = ($state['answered'][$card_id] ?? 0) + 1;

    // Aktuellen Leitner-Stand laden (next_due_date nur für die Vorher/Nachher-Anzeige im Debug-Modus nötig)
    $stmt = $pdo->prepare("SELECT leitner_box, next_due_date FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    $current_box  = (int) ($cp['leitner_box'] ?? 1);
    $due_before   = $cp['next_due_date'] ?? null;

    if ($result === 'correct') {
        $state['stats']['correct']++;

        if ($is_retry) {
            // Zweiter Versuch richtig → bleibt in Fach 1, due = morgen
            $due     = date('Y-m-d', strtotime($today . ' +1 day'));
            $new_box = 1;
            $stmt = $pdo->prepare("UPDATE card_progress SET leitner_box=1, next_due_date=? WHERE person_id=? AND card_id=?");
            $stmt->execute([$due, $person_id, $card_id]);
        } else {
            // Erster Versuch richtig → aufsteigen
            $new_box = min(5, $current_box + 1);
            $interval = $intervals[$new_box];
            $due = date('Y-m-d', strtotime($today . " +$interval days"));

            if ($new_box > $current_box) {
                $state['stats']['promoted']++;
            }

            $stmt = $pdo->prepare("UPDATE card_progress SET leitner_box=?, next_due_date=? WHERE person_id=? AND card_id=?");
            $stmt->execute([$new_box, $due, $person_id, $card_id]);
        }

        $db_result = 'correct';

        if ($debug_enabled) {
            $versuch = $is_retry ? '2. Versuch' : '1. Versuch';
            $debug_lines = [
                debug_card_label($pdo, $card_id),
                "richtig ({$versuch})",
                "Fach {$current_box}→{$new_box}, fällig " . debug_format_date($due_before) . '→' . debug_format_date($due),
            ];
            // Nur beim Aufsteigen (1. Versuch) wird das Intervall aus LEITNER_INTERVALS nachgeschlagen —
            // zeigen, welches Fach/Intervall/Basisdatum konkret verwendet wurde, damit sich das neue
            // next_due_date direkt nachrechnen lässt, ohne den Code lesen zu müssen.
            if (!$is_retry) {
                $debug_lines[] = "Intervall Fach {$new_box}: {$interval} Tag" . ($interval !== 1 ? 'e' : '') . ', Basis: ' . debug_format_date($today);
            }
            $_SESSION['debug_last_answer'] = $debug_lines;
        }

    } else {
        // Falsch
        $state['stats']['incorrect']++;

        if (!$is_retry) {
            // Erster Fehler → zurück in Fach 1, due = morgen, nochmal ans Ende
            $due = date('Y-m-d', strtotime($today . ' +1 day'));
            $stmt = $pdo->prepare("UPDATE card_progress SET leitner_box=1, next_due_date=? WHERE person_id=? AND card_id=?");
            $stmt->execute([$due, $person_id, $card_id]);

            // Ans Ende der Queue für zweiten Versuch
            $state['queue'][] = $card_id;

            if ($debug_enabled) {
                $_SESSION['debug_last_answer'] = [
                    debug_card_label($pdo, $card_id),
                    'falsch (1. Versuch) -> kommt nochmals dran',
                    "Fach {$current_box}→1, fällig " . debug_format_date($due_before) . '→' . debug_format_date($due),
                ];
            }
        } else {
            // Zweiter Fehler → kein weiterer Versuch, Karte bleibt in Fach 1
            if ($debug_enabled) {
                $_SESSION['debug_last_answer'] = [
                    debug_card_label($pdo, $card_id),
                    'falsch (2. Versuch)',
                    'bleibt Fach 1, kein weiterer Versuch in dieser Session',
                ];
            }
        }

        $db_result = 'incorrect';
    }

    // Event loggen
    $stmt = $pdo->prepare("INSERT INTO learning_events (person_id, card_id, result, learn_date) VALUES (?,?,?,?)");
    $stmt->execute([$person_id, $card_id, $db_result, $today]);

    // Nächste Karte
    array_shift($state['queue']);

    // Session beendet?
    if (!$state['queue']) {
        // Session abschliessen
        $summary  = $state['stats'];
        $list_ids = $state['list_ids'];
        $tag      = $state['tag'] ?? null;
        unset($_SESSION['learn']);

        // Streak berechnen
        $streak = get_learn_streak($pdo, $person_id);

        $_SESSION['learn_done'] = [
            'stats'    => $summary,
            'streak'   => $streak,
            'list_ids' => $list_ids,
            'tag'      => $tag,
        ];
        header('Location: learn.php?done=1');
        exit;
    }

    header('Location: learn.php');
    exit;
}

// -------------------------------------------------------
// Hilfsfunktionen
// -------------------------------------------------------

// $card_ids_filter: nur im Tag-Modus gesetzt — schränkt zusätzlich zu $list_ids auf diese
// Karten-IDs ein (statt alle Karten der übergebenen Listen). $daily_limit: im Tag-Modus vom User
// überschreibbar (siehe begin-Handler), sonst immer DAILY_CARD_LIMIT.
function activate_daily_cards(PDO $pdo, int $person_id, array $list_ids, string $today, ?array $card_ids_filter = null, int $daily_limit = DAILY_CARD_LIMIT): void {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    [$card_filter_sql, $card_filter_params] = card_id_filter_sql($card_ids_filter);

    // Wie viele wurden heute schon aktiviert?
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id IN ($placeholders){$card_filter_sql}
          AND cp.status = 'active' AND cp.next_due_date = ?
          AND cp.leitner_box = 1
    ");
    $check_params = array_merge([$person_id], $list_ids, $card_filter_params, [$today]);
    $stmt->execute($check_params);
    $already_activated = (int) $stmt->fetchColumn();
    $to_activate = $daily_limit - $already_activated;
    if ($to_activate <= 0) return;

    $params = array_merge([$person_id], $list_ids, $card_filter_params);
    $stmt = $pdo->prepare("
        SELECT cp.card_id FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id IN ($placeholders){$card_filter_sql} AND cp.status = 'queued'
        ORDER BY RAND()
        LIMIT {$to_activate}
    ");
    $stmt->execute($params);
    $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$card_ids) return;

    $upd = $pdo->prepare("UPDATE card_progress SET status='active', leitner_box=1, next_due_date=? WHERE person_id=? AND card_id=?");
    foreach ($card_ids as $cid) {
        $upd->execute([$today, $person_id, $cid]);
    }
}

function build_leitner_queue(PDO $pdo, int $person_id, array $list_ids, string $today, int $limit, ?array $card_ids_filter = null): array {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    [$card_filter_sql, $card_filter_params] = card_id_filter_sql($card_ids_filter);

    $stmt = $pdo->prepare("
        SELECT cp.card_id,
               CASE
                 WHEN cp.next_due_date < ?   THEN 1
                 WHEN cp.next_due_date = ?   THEN 2
                 ELSE 3
               END AS priority
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ?
          AND c.list_id IN ($placeholders){$card_filter_sql}
          AND cp.status = 'active'
          AND cp.next_due_date <= ?
        ORDER BY priority, RAND()
        LIMIT {$limit}
    ");
    $stmt->execute(array_merge([$today, $today, $person_id], $list_ids, $card_filter_params, [$today]));
    return array_column($stmt->fetchAll(), 'card_id');
}

function get_learn_streak(PDO $pdo, int $person_id): int {
    $stmt = $pdo->prepare("
        SELECT DISTINCT learn_date FROM learning_events
        WHERE person_id = ? AND result != 'skipped'
        ORDER BY learn_date DESC
    ");
    $stmt->execute([$person_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$dates) return 0;

    $tz        = new DateTimeZone(TIMEZONE);
    $today     = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');

    if ($dates[0] !== $today && $dates[0] !== $yesterday) return 0;

    $streak   = 1;
    $expected = (new DateTimeImmutable($dates[0], $tz))->modify('-1 day')->format('Y-m-d');
    for ($i = 1; $i < count($dates); $i++) {
        if ($dates[$i] === $expected) {
            $streak++;
            $expected = (new DateTimeImmutable($dates[$i], $tz))->modify('-1 day')->format('Y-m-d');
        } else {
            break;
        }
    }
    return $streak;
}

// -------------------------------------------------------
// STATE: Aktuelle Karte laden
// -------------------------------------------------------
$state    = $_SESSION['learn'] ?? null;
$current  = null;

if ($state) {
    $card_id = $state['queue'][0] ?? null;
    if ($card_id) {
        $stmt = $pdo->prepare("
            SELECT c.*, cp.leitner_box, cp.drill_pinned_correct,
                   l.language_a, l.language_b, l.speech_lang_b
            FROM cards c
            JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = ?
            JOIN lists l ON l.id = c.list_id
            WHERE c.id = ?
        ");
        $stmt->execute([$person_id, $card_id]);
        $current = $stmt->fetch();
    }
}

// Session-Abschluss anzeigen
$done_data = null;
if (isset($_GET['done']) && isset($_SESSION['learn_done'])) {
    $done_data = $_SESSION['learn_done'];
    unset($_SESSION['learn_done']);
}

// Lernrichtung auf Karte anwenden
// Aussprache-Button (Web Speech API) gilt ausschliesslich für Sprache B — q_audio/a_audio
// ist der vorzulesende Text (word_b) auf der Seite, wo Sprache B angezeigt wird, sonst null.
$setup_error = '';

render_setup:
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leitner — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo, $state ? 'learn.php?action=setup' : null); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Leitner', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:700px;">

<?php if ($done_data !== null): ?>
<!-- ==================== SESSION-ZUSAMMENFASSUNG ==================== -->
<?php
$total_answered = ($done_data['stats']['correct'] ?? 0) + ($done_data['stats']['incorrect'] ?? 0);
$no_cards_due   = ($total_answered === 0);
?>
<?= debug_panel() ?>
<div class="text-center">
    <?php if ($no_cards_due): ?>
    <div class="display-6 mb-2">✅</div>
    <h2 class="h4 mb-2">Keine Karten fällig</h2>
    <?php if (!empty($done_data['next_due'])): ?>
    <p class="text-muted mb-4">Nächste Karten fällig am <?= htmlspecialchars(date('d.m.Y', strtotime($done_data['next_due']))) ?>.</p>
    <?php else: ?>
    <p class="text-muted mb-4">Alle Karten sind erledigt oder noch nicht aktiv.</p>
    <?php endif; ?>
    <?php else: ?>
    <div class="display-6 mb-2">
        <?php
        $pct = $total_answered > 0 ? round(($done_data['stats']['correct'] ?? 0) / $total_answered * 100) : 0;
        echo $pct >= 80 ? '🎉' : ($pct >= 50 ? '💪' : '📚');
        ?>
    </div>
    <h2 class="h4 mb-4">
        <?php
        if ($pct >= 80) echo 'Super gemacht!';
        elseif ($pct >= 50) echo 'Weiter so!';
        else echo 'Üben macht den Meister!';
        ?>
    </h2>
    <?php endif; ?>

    <?php if (!$no_cards_due): ?>
    <div class="row g-3 mb-4 justify-content-center">
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-success"><?= $done_data['stats']['correct'] ?? 0 ?></div>
                <div class="small text-muted">Gewusst</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-danger"><?= $done_data['stats']['incorrect'] ?? 0 ?></div>
                <div class="small text-muted">Nicht gewusst</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-primary"><?= $done_data['stats']['promoted'] ?? 0 ?></div>
                <div class="small text-muted">Aufgestiegen</div>
            </div>
        </div>
        <?php if (!empty($done_data['streak'])): ?>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-warning">🔥 <?= $done_data['streak'] ?></div>
                <div class="small text-muted">Tag<?= $done_data['streak'] > 1 ? 'e' : '' ?> in Folge</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $repeat_ids = $done_data['list_ids'] ?? [];
    $repeat_tag = $done_data['tag'] ?? null;
    $repeat_url = $repeat_tag
        ? 'learn.php?tag=' . urlencode($repeat_tag)
        : (count($repeat_ids) === 1 ? 'learn.php?list_id=' . $repeat_ids[0] : 'learn.php');
    ?>
    <a href="<?= $repeat_url ?>" class="btn btn-primary">Neue Session</a>
</div>

<?php elseif ($state && $current): ?>
<!-- ==================== LERNKARTE ==================== -->
<?php
$qa        = get_question_answer($current, $state['direction']);
$remaining = count($state['queue']);
$total     = $remaining + $state['stats']['correct'] + $state['stats']['incorrect'];
$is_retry  = isset($state['answered'][$current['id']]);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <small class="text-muted">
        Fach <?= $current['leitner_box'] ?>
        <?php if ($is_retry): ?><span class="badge bg-warning text-dark ms-1">2. Versuch</span><?php endif; ?>
    </small>
    <small class="text-muted"><?= $total - $remaining + 1 ?> / <?= $total ?></small>
</div>

<!-- Fortschrittsbalken -->
<div class="progress mb-4" style="height:6px;">
    <div class="progress-bar" style="width: <?= $total > 0 ? round(($total - $remaining) / $total * 100) : 0 ?>%"></div>
</div>

<!-- Karte (klicken zum Aufdecken) -->
<div class="learn-card mx-auto mb-4 position-relative"
     id="learn-card" style="max-width:540px; cursor:pointer;" onclick="flipCard()">
    <?php $is_pinned = $current['drill_pinned_correct'] !== null; ?>
    <form method="post" id="pin-form" class="position-absolute" style="top:8px; left:8px; z-index:2;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_pin">
        <input type="hidden" name="card_id" value="<?= $current['id'] ?>">
        <button type="submit" class="btn btn-sm <?= $is_pinned ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-circle"
                onclick="event.stopPropagation();"
                title="<?= $is_pinned ? 'Vormerkung entfernen' : 'Für Drill vormerken' ?>">
            <i class="bi <?= $is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' ?>"></i>
        </button>
    </form>
    <div class="text-center p-5" style="min-height:280px;">
        <p class="text-muted small mb-2"><?= htmlspecialchars($qa['q_lang']) ?></p>
        <div class="fw-bold fs-2 mb-1">
            <?= htmlspecialchars($qa['q']) ?>
            <?php if ($qa['q_audio']): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary align-middle ms-1"
                    onclick="event.stopPropagation(); speakWord(this)"
                    data-speak="<?= htmlspecialchars($qa['q_audio']) ?>" data-lang="<?= htmlspecialchars($current['speech_lang_b']) ?>">
                <i class="bi bi-volume-up-fill"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php if ($qa['q_phonetic']): ?>
        <p class="text-muted small mb-1">[<?= htmlspecialchars($qa['q_phonetic']) ?>]</p>
        <?php endif; ?>
        <?php if ($qa['q_desc']): ?>
        <p class="text-muted mb-0"><?= htmlspecialchars($qa['q_desc']) ?></p>
        <?php endif; ?>
        <div id="learn-answer" style="display:none;">
            <hr class="my-3">
            <p class="text-muted small mb-1"><?= htmlspecialchars($qa['a_lang']) ?></p>
            <div class="fw-bold fs-3 text-success mb-0">
                <?= htmlspecialchars($qa['a']) ?>
                <?php if ($qa['a_audio']): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary align-middle ms-1"
                        onclick="event.stopPropagation(); speakWord(this)"
                        data-speak="<?= htmlspecialchars($qa['a_audio']) ?>" data-lang="<?= htmlspecialchars($current['speech_lang_b']) ?>">
                    <i class="bi bi-volume-up-fill"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php if ($qa['a_phonetic']): ?>
            <p class="text-muted small mb-1">[<?= htmlspecialchars($qa['a_phonetic']) ?>]</p>
            <?php endif; ?>
            <?php if ($qa['a_desc']): ?>
            <p class="text-muted mt-1 mb-0"><?= htmlspecialchars($qa['a_desc']) ?></p>
            <?php endif; ?>
        </div>
        <p class="text-muted small mt-4 mb-0" id="learn-tap-hint">Tippen zum Aufdecken</p>
    </div>
</div>

<!-- Überspringen (vor Aufdecken) -->
<div id="learn-skip" class="text-center mb-3">
    <form method="post" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="answer">
        <input type="hidden" name="card_id" value="<?= $current['id'] ?>">
        <input type="hidden" name="result" value="skip">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Überspringen</button>
    </form>
</div>

<!-- Antwort-Buttons (nach Aufdecken) -->
<div id="learn-answer-buttons" class="row g-3 justify-content-center" style="display:none;">
    <div class="col-auto">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="card_id" value="<?= $current['id'] ?>">
            <input type="hidden" name="result" value="incorrect">
            <button type="submit" class="btn btn-danger btn-lg px-4">Nicht gewusst</button>
        </form>
    </div>
    <div class="col-auto">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="card_id" value="<?= $current['id'] ?>">
            <input type="hidden" name="result" value="correct">
            <button type="submit" class="btn btn-success btn-lg px-4">Gewusst</button>
        </form>
    </div>
</div>

<?= debug_panel() ?>

<?php else: ?>
<!-- ==================== SETUP ==================== -->
<h1 class="h4 mb-4"><i class="bi bi-collection text-primary me-2"></i>Leitner-Session starten</h1>

<?php if ($setup_error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($setup_error) ?></div>
<?php endif; ?>

<?php if (!$all_lists): ?>
<p class="text-muted">Du hast noch keine Listen. <a href="lists.php">Erstelle zuerst eine Liste</a>.</p>
<?php else: ?>
<?php
$lang_a = $preset_list ? htmlspecialchars($preset_list['language_a']) : 'A';
$lang_b = $preset_list ? htmlspecialchars($preset_list['language_b']) : 'B';
// Bei Mathe-Listen (siehe is_math_list()) ist nur "Aufgabe → Ergebnis" sinnvoll — die anderen
// Richtungen werden ausgeblendet und a_to_b fest vorausgewählt.
$is_math_preset = $preset_list && is_math_list($preset_list);
?>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="begin">

    <?php
    // data-Attribute für den Verfügbarkeits-Hinweis unten (Kartenanzahl) — dieselben Zahlen für
    // Preset- und Checkbox-Auswahl, damit eine einzige JS-Funktion beide Fälle bedienen kann.
    $avail_attrs = function (int $list_id) use ($list_availability): string {
        $a = $list_availability[$list_id] ?? ['due_today' => 0, 'queued' => 0, 'already_activated' => 0];
        return 'data-due="' . $a['due_today'] . '" data-queued="' . $a['queued'] . '" data-activated="' . $a['already_activated'] . '"';
    };
    ?>

    <?php
    // "Was lernen": Mathe / Sprachen / Thema als segmentierte Toggle-Buttons (dasselbe btn-check-
    // Muster wie bei Lernrichtung weiter unten) statt gestapelter Sektionen — macht auf einen Blick
    // klar, dass es drei alternative Wege sind. Mathe/Sprachen sind wegen der Typ-Sperre ohnehin nie
    // gleichzeitig kombinierbar, deshalb hier gleich als getrennte Modi statt einer gemeinsamen
    // Listen-Sektion mit Sperr-Logik. Bei vorausgewählter Einzelliste (?list_id=…) entfällt die
    // Mathe/Sprachen-Aufteilung (es gibt nur die eine Liste) — dort ggf. nur "Liste" + "Thema".
    $math_lists = $preset_list ? [] : array_filter($all_lists, fn($l) => is_math_list($l));
    $word_lists = $preset_list ? [] : array_filter($all_lists, fn($l) => !is_math_list($l));

    $modes = [];
    if ($preset_list) {
        $modes['liste'] = ['icon' => 'bi-collection', 'label' => 'Liste'];
    } else {
        if ($math_lists) $modes['math'] = ['icon' => 'bi-calculator', 'label' => 'Mathe'];
        if ($word_lists) $modes['word'] = ['icon' => 'bi-translate', 'label' => 'Sprachen'];
    }
    if ($available_tags) $modes['tag'] = ['icon' => 'bi-tags', 'label' => 'Thema'];

    // Default: gewählter Tag (z.B. "Nochmal"-Link) > erster nicht-Thema-Modus > Thema als letzter Fallback.
    $default_mode = $preset_tag !== '' ? 'tag' : (array_key_first(array_diff_key($modes, ['tag' => 1])) ?? 'tag');
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <?php if (count($modes) > 1): ?>
            <div class="row row-cols-<?= count($modes) ?> g-2 mb-3" id="mode-select">
                <?php foreach ($modes as $key => $m): ?>
                <div class="col">
                    <input type="radio" class="btn-check" name="mode_select" id="mode_<?= $key ?>" autocomplete="off"
                           data-pane="mode-<?= $key ?>-pane" <?= $default_mode === $key ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary w-100" for="mode_<?= $key ?>"><i class="bi <?= $m['icon'] ?> me-1"></i><?= $m['label'] ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($preset_list): ?>
            <!-- Vorausgewählte Liste (von Startseite) -->
            <div id="mode-liste-pane" class="mode-pane" style="<?= $default_mode === 'liste' ? '' : 'display:none;' ?>">
                <input type="hidden" name="list_ids[]" value="<?= $preset_list['id'] ?>" <?= $avail_attrs($preset_list['id']) ?>>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text text-muted fs-4"></i>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($preset_list['name']) ?></div>
                        <div class="text-muted small"><?= $lang_a ?> / <?= $lang_b ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Listen je Typ als volle, klickbare Zeilen (list-group). Initial ist bewusst keine
                 Liste angehakt (siehe Checkliste). -->
            <?php if ($math_lists): ?>
            <div id="mode-math-pane" class="mode-pane" style="<?= $default_mode === 'math' ? '' : 'display:none;' ?>">
                <div class="list-group">
                    <?php foreach ($math_lists as $list): ?>
                    <label class="list-group-item d-flex align-items-center gap-2" for="list_<?= $list['id'] ?>">
                        <input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="list_ids[]"
                               value="<?= $list['id'] ?>" id="list_<?= $list['id'] ?>" data-math="1"
                               <?= $avail_attrs($list['id']) ?>>
                        <span><?= htmlspecialchars($list['name']) ?>
                            <span class="text-muted small">(<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($word_lists): ?>
            <div id="mode-word-pane" class="mode-pane" style="<?= $default_mode === 'word' ? '' : 'display:none;' ?>">
                <div class="list-group">
                    <?php foreach ($word_lists as $list): ?>
                    <label class="list-group-item d-flex align-items-center gap-2" for="list_<?= $list['id'] ?>">
                        <input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="list_ids[]"
                               value="<?= $list['id'] ?>" id="list_<?= $list['id'] ?>" data-math="0"
                               <?= $avail_attrs($list['id']) ?>>
                        <span><?= htmlspecialchars($list['name']) ?>
                            <span class="text-muted small">(<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($available_tags): ?>
            <!-- Themen-Session: listenübergreifend über alle eigenen Karten mit diesem Tag, siehe
                 begin-Handler. Bei Checkbox-Mehrfachauswahl (kein Preset) blendet JS unten Tags aus,
                 die zu keiner der gerade angehakten Listen gehören. -->
            <div id="mode-tag-pane" class="mode-pane" style="<?= $default_mode === 'tag' ? '' : 'display:none;' ?>">
                <div id="tag-cloud-section">
                    <div class="d-flex gap-2 flex-wrap mb-2" id="tag-cloud">
                        <?php foreach ($available_tags as $t): $tid = 'tag_' . md5($t); ?>
                        <div data-tag="<?= htmlspecialchars($t) ?>">
                            <input type="radio" class="btn-check" name="tag" id="<?= $tid ?>" autocomplete="off"
                                   value="<?= htmlspecialchars($t) ?>" <?= $preset_tag === $t ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm rounded-pill" for="<?= $tid ?>">#<?= htmlspecialchars($t) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">
                        Gilt listenübergreifend über alle eigenen Karten mit diesem Tag — unabhängig von der Liste, aus der sie stammen.
                        <a href="#" id="clear-tag-link" style="<?= $preset_tag === '' ? 'display:none;' : '' ?>">Thema entfernen</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lernrichtung: bei Mathe-Auswahl ist sie immer dieselbe (Aufgabe→Ergebnis) — die ganze
         Sektion ist dann überflüssig statt nur eine "eingefrorene" Auswahl zu zeigen. Serverseitig
         ohnehin erzwungen (begin-Handler), unabhängig davon ob überhaupt ein direction-Wert
         übermittelt wird — das Formularfeld darf also komplett fehlen. Segmentierte Toggle-Buttons
         (Bootstrap btn-check) statt gestapelter Radios — kompakter, bekanntes Muster für eine
         kleine Menge sich ausschliessender Optionen. -->
    <div class="card shadow-sm mb-3" id="direction-section" style="<?= $is_math_preset ? 'display:none;' : '' ?>">
        <div class="card-body">
            <label class="form-label fw-semibold">Lernrichtung</label>
            <div class="row row-cols-2 g-2">
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_ab" value="a_to_b" autocomplete="off">
                    <label class="btn btn-outline-primary w-100" for="dir_ab" id="label_ab"><?= $lang_a ?> → <?= $lang_b ?></label>
                </div>
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_ba" value="b_to_a" autocomplete="off">
                    <label class="btn btn-outline-primary w-100" for="dir_ba" id="label_ba"><?= $lang_b ?> → <?= $lang_a ?></label>
                </div>
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_mix" value="mixed" autocomplete="off">
                    <label class="btn btn-outline-primary w-100" for="dir_mix">Gemischt</label>
                </div>
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_random" value="random" autocomplete="off" checked>
                    <label class="btn btn-outline-primary w-100" for="dir_random">Zufall</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Kartenanzahl -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold">Kartenanzahl</label>
            <div class="input-group" style="max-width:220px;">
                <button type="button" class="btn btn-outline-secondary" onclick="adjustCards(-5)">−5</button>
                <input type="number" name="card_limit" id="card_limit" class="form-control text-center" value="<?= LEITNER_DEFAULT_CARDS ?>" min="1" max="200">
                <button type="button" class="btn btn-outline-secondary" onclick="adjustCards(5)">+5</button>
            </div>
            <div class="form-text">App zeigt alle fälligen Karten. Du kannst die Zahl anpassen.</div>

            <?php if ($available_tags): ?>
            <!-- Tageslimit-Hinweis/-Bestätigung nur relevant, solange ein Thema gewählt ist (siehe
                 Modus "Thema" oben) — bewusst nicht dort platziert, damit der Modus rein die
                 Themenauswahl bleibt. Kein separates Zahlenfeld: die Menge steuert sich über das
                 ohnehin vorhandene "Kartenanzahl"-Feld oben — die Checkbox bestätigt nur, dass
                 dafür auch mehr als die empfohlenen <?= DAILY_CARD_LIMIT ?> neuen Karten aus der
                 Warteschlange geladen werden dürfen, statt ein zweites, redundantes Feld danach zu
                 fragen. -->
            <div class="mt-3 p-3 bg-body-tertiary rounded" id="daily-limit-block" style="<?= $preset_tag === '' ? 'display:none;' : '' ?>">
                <div class="d-flex gap-2">
                    <i class="bi bi-info-circle text-muted"></i>
                    <div class="form-text mb-0">
                        Bei einer Themen-Session werden für den besten Lerneffekt standardmässig nur <?= DAILY_CARD_LIMIT ?> neue Karten pro Tag aus der Warteschlange aktiviert — das schützt vor zu vielen neuen Karten auf einmal. Bereits fällige Karten sind davon nicht betroffen. Falls "Kartenanzahl" oben höher als <?= DAILY_CARD_LIMIT ?> eingestellt ist, kannst du diesen Schutz für diese Session bewusst umgehen:
                    </div>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="daily_limit_override" name="daily_limit_override" value="1">
                    <label class="form-check-label" for="daily_limit_override">Ich bin einverstanden, dass heute mehr als <?= DAILY_CARD_LIMIT ?> neue Karten geladen werden (bis zur oben eingestellten Kartenanzahl)</label>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info small d-none" id="availability-hint"></div>

    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-play-fill me-1"></i>Session starten</button>
</form>
<?php endif; ?>

<?php endif; ?>
</div>

<?php if ($state && $current): ?>
<div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Session verlassen?</h5>
            </div>
            <div class="modal-body">
                Achtung: die laufende Session wird dadurch automatisch beendet.
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="confirmLeave">Verlassen</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
});

<?php if ($state && $current): ?>
(function () {
    var modal = new bootstrap.Modal(document.getElementById('leaveModal'));
    var target = null;
    document.querySelectorAll('a[href]').forEach(function (link) {
        var href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:')) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            target = href;
            modal.show();
        });
    });
    document.getElementById('confirmLeave').addEventListener('click', function () {
        if (target) window.location.href = 'learn.php?action=setup&to=' + encodeURIComponent(target);
    });
})();

// Vormerken per Fetch statt normalem Form-Submit — ein voller Seiten-Reload würde die
// aufgedeckte Antwort (rein clientseitiger Zustand, siehe flipCard()) wieder verstecken.
(function () {
    var form = document.getElementById('pin-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = form.querySelector('button');
        var icon = btn.querySelector('i');
        fetch('learn.php', { method: 'POST', body: new FormData(form) }).then(function (res) {
            if (!res.ok) return;
            var wasPinned = icon.classList.contains('bi-pin-angle-fill');
            icon.classList.toggle('bi-pin-angle-fill', !wasPinned);
            icon.classList.toggle('bi-pin-angle', wasPinned);
            btn.classList.toggle('btn-primary', !wasPinned);
            btn.classList.toggle('btn-outline-secondary', wasPinned);
            btn.title = wasPinned ? 'Für Drill vormerken' : 'Vormerkung entfernen';
        });
    });
})();
<?php endif; ?>

function speakWord(btn) {
    if (!('speechSynthesis' in window)) return;
    var text = btn.dataset.speak;
    var lang = btn.dataset.lang;
    var u = new SpeechSynthesisUtterance(text);
    u.lang = lang;
    // utterance.lang allein wird von manchen Browsern/Geräten ignoriert und fällt auf die
    // Standardstimme des Systems zurück — passende Stimme explizit suchen und setzen.
    var voices = window.speechSynthesis.getVoices();
    var match = voices.find(function (v) { return v.lang === lang; })
             || voices.find(function (v) { return v.lang.split('-')[0] === lang.split('-')[0]; });
    if (match) u.voice = match;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(u);
}

function flipCard() {
    var card = document.getElementById('learn-card');
    card.style.transform = 'scaleX(0)';
    setTimeout(function () {
        document.getElementById('learn-answer').style.display = 'block';
        document.getElementById('learn-tap-hint').style.display = 'none';
        document.getElementById('learn-skip').style.display = 'none';
        card.style.transform = 'scaleX(1)';
    }, 150);
    setTimeout(function () {
        document.getElementById('learn-answer-buttons').style.display = 'flex';
        card.style.cursor = 'default';
        card.onclick = null;
    }, 300);
}

function adjustCards(delta) {
    const el = document.getElementById('card_limit');
    if (el) el.value = Math.max(1, parseInt(el.value || 20) + delta);
    updateAvailabilityHint();
}

// "Was lernen": Mathe/Sprachen/Thema-Segmentbuttons schalten die zugehörige Sektion sichtbar,
// alle anderen aus — genau ein mode-pane ist je Auswahl sichtbar. Kein PHP-Guard nötig: existiert
// die Radiogruppe nicht (nur ein einziger Modus verfügbar), ist die NodeList einfach leer.
// Ein Moduswechsel setzt Listen- und Themenauswahl komplett zurück — sonst könnte eine unsichtbare,
// aber weiterhin angehakte Liste/ein Tag aus dem vorherigen Modus mit abgeschickt werden (z.B.
// Mathe-Liste + Sprachliste gleichzeitig, obwohl sie nie zusammen sichtbar sind).
document.querySelectorAll('input[name="mode_select"]').forEach(function (r) {
    r.addEventListener('change', function () {
        document.querySelectorAll('.mode-pane').forEach(function (p) { p.style.display = 'none'; });
        var pane = document.getElementById(r.dataset.pane);
        if (pane) pane.style.display = '';

        document.querySelectorAll('input[name="list_ids[]"]').forEach(function (cb) { cb.checked = false; });
        document.querySelectorAll('input[name="tag"]').forEach(function (t) { t.checked = false; });

        if (typeof updateAvailabilityHint === 'function') updateAvailabilityHint();
        if (typeof updateDirLabels === 'function') updateDirLabels();
        if (typeof window.__updateTagUI === 'function') window.__updateTagUI();
    });
});

<?php if (!$state && $all_lists): ?>
// Verfügbarkeits-Hinweis (Infobox unter "Kartenanzahl") — summiert über alle ausgewählten Listen
// (Preset: das einzelne versteckte Feld; Checkbox-Auswahl: alle angehakten). Macht sichtbar, warum
// eine Session kleiner als gewünscht ausfallen kann, statt dass es wie ein Fehler wirkt — dafür gibt
// es zwei unabhängige Gründe: das Tageslimit für neue Karten (DAILY_CARD_LIMIT) oder schlicht eine
// zu kleine Warteschlange. Erscheint nur, wenn die eingestellte Kartenanzahl tatsächlich mehr
// verlangt, als heute verfügbar ist — sonst bleibt sie unnötig.
const DAILY_CARD_LIMIT = <?= (int) DAILY_CARD_LIMIT ?>;

function updateAvailabilityHint() {
    const hint = document.getElementById('availability-hint');
    if (!hint) return;
    const inputs = document.querySelectorAll('input[name="list_ids[]"]:checked, input[name="list_ids[]"][type="hidden"]');
    // Initial ist keine Liste angehakt (siehe Checkliste) — ohne Auswahl gäbe es sonst fälschlich
    // "Die Warteschlange ist leer", obwohl schlicht noch nichts gewählt wurde.
    if (inputs.length === 0) {
        hint.classList.add('d-none');
        hint.innerHTML = '';
        return;
    }
    let due = 0, queued = 0, activated = 0;
    inputs.forEach(function (el) {
        due       += parseInt(el.dataset.due || '0', 10);
        queued    += parseInt(el.dataset.queued || '0', 10);
        activated += parseInt(el.dataset.activated || '0', 10);
    });
    const remaining     = Math.max(0, DAILY_CARD_LIMIT - activated);
    const willActivate  = Math.min(queued, remaining);
    const maxAvailable  = due + willActivate;

    const cardLimitInput = document.getElementById('card_limit');
    const requested = cardLimitInput ? parseInt(cardLimitInput.value || '0', 10) : 0;

    if (requested <= maxAvailable) {
        hint.classList.add('d-none');
        hint.innerHTML = '';
        return;
    }

    hint.classList.remove('d-none');

    // Zwei mögliche Gründe, warum weniger als das Tageslimit aus der Warteschlange kommt: entweder
    // ist das Tageslimit selbst der Engpass, oder die Warteschlange hat schlicht nicht genug Karten.
    // min(queued, remaining) verrät, welcher der beiden Fälle tatsächlich zutrifft.
    var reason;
    if (queued <= remaining) {
        reason = queued === 0
            ? 'Die Warteschlange ist leer.'
            : 'In der Warteschlange ' + (inputs.length > 1 ? 'der ausgewählten Listen sind' : 'dieser Liste ist') + ' nur noch ' + queued + ' Karte' + (queued !== 1 ? 'n' : '') + ' übrig.';
    } else {
        reason = 'Pro Liste werden maximal ' + DAILY_CARD_LIMIT + ' neue Karten pro Tag aus der Warteschlange aktiviert'
            + (activated > 0 ? ' — heute wurden davon bereits ' + activated + ' genutzt' : '') + '.';
    }

    hint.innerHTML = reason + ' Die Session enthält daher <strong>' + maxAvailable + '</strong> Karten: '
        + due + ' heute fällig + ' + willActivate + ' neu aus der Warteschlange.';
}

document.querySelectorAll('input[name="list_ids[]"]').forEach(cb => {
    cb.addEventListener('change', updateAvailabilityHint);
});
const cardLimitField = document.getElementById('card_limit');
if (cardLimitField) cardLimitField.addEventListener('input', updateAvailabilityHint);
updateAvailabilityHint();
<?php endif; ?>

<?php if (!$preset_list && $all_lists): ?>
// Richtungs-Labels bei Mehrfach-Listenauswahl dynamisch aktualisieren
const langMap = <?= json_encode(array_combine(
    array_column($all_lists, 'id'),
    array_map(fn($l) => ['a' => $l['language_a'], 'b' => $l['language_b'], 'math' => is_math_list($l)], $all_lists)
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function updateDirLabels() {
    const checked = Array.from(document.querySelectorAll('input[name="list_ids[]"]:checked'));
    const first = checked[0];
    const langs = first && langMap[first.value] ? langMap[first.value] : {a: 'A', b: 'B'};
    document.getElementById('label_ab').textContent = langs.a + ' → ' + langs.b;
    document.getElementById('label_ba').textContent = langs.b + ' → ' + langs.a;

    // Nur wenn AUSSCHLIESSLICH Mathe-Listen ausgewählt sind, ist "Aufgabe → Ergebnis" die einzig
    // sinnvolle Richtung — die ganze Lernrichtung-Sektion ist dann überflüssig (serverseitig
    // ohnehin erzwungen, siehe begin-Handler). Bei Mischauswahl mit Wortlisten (aktuell durch die
    // Typ-Sperre ausgeschlossen) bliebe sie sichtbar.
    const allMath = checked.length > 0 && checked.every(cb => langMap[cb.value] && langMap[cb.value].math);
    document.getElementById('direction-section').style.display = allMath ? 'none' : '';
}

document.querySelectorAll('input[name="list_ids[]"]').forEach(cb => {
    cb.addEventListener('change', updateDirLabels);
});
updateDirLabels();
<?php endif; ?>

<?php if (!$state && $available_tags): ?>
// Themen-Session: Tageslimit-Block (Hinweis + Bestätigungs-Checkbox) nur einblenden, solange ein
// Thema gewählt ist, und den listenbasierten Verfügbarkeits-Hinweis währenddessen ausblenden (er
// würde sonst Zahlen zur Listenauswahl zeigen, die im Tag-Modus gar nicht gelten — der Tag hat
// serverseitig Vorrang).
(function () {
    var tagRadios     = document.querySelectorAll('input[name="tag"]');
    var dailyBlock     = document.getElementById('daily-limit-block');
    var dailyOverride  = document.getElementById('daily_limit_override');
    var clearLink      = document.getElementById('clear-tag-link');
    var availHint      = document.getElementById('availability-hint');

    function updateTagUI() {
        var anyChecked = Array.from(tagRadios).some(function (r) { return r.checked; });
        if (dailyBlock) dailyBlock.style.display = anyChecked ? '' : 'none';
        if (clearLink)  clearLink.style.display  = anyChecked ? '' : 'none';
        if (!anyChecked && dailyOverride) {
            // Auswahl zurückgesetzt: Bestätigung nicht "scharf" stehen lassen, sonst würde ein
            // wieder unsichtbares, aber weiterhin angehaktes Kästchen beim erneuten Wählen eines
            // Themas ungewollt sofort das Tageslimit überschreiten.
            dailyOverride.checked = false;
        }
        if (anyChecked) {
            if (availHint) { availHint.classList.add('d-none'); availHint.innerHTML = ''; }
        } else if (typeof updateAvailabilityHint === 'function') {
            updateAvailabilityHint();
        }
    }

    tagRadios.forEach(function (r) { r.addEventListener('change', updateTagUI); });
    if (clearLink) {
        clearLink.addEventListener('click', function (e) {
            e.preventDefault();
            tagRadios.forEach(function (r) { r.checked = false; });
            updateTagUI();
        });
    }

    // Vom mode_select-Handler (Mathe/Sprachen/Thema-Umschalter) aufgerufen, wenn der Thema-Modus
    // verlassen/betreten wird und die Tag-Auswahl entsprechend zurückgesetzt werden muss.
    window.__updateTagUI = updateTagUI;
})();
<?php endif; ?>
</script>
</body>
</html>
