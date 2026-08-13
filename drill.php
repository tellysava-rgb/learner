<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id   = $_SESSION['person_id'];

// Navbar-Aktionen (Logout, eigenes Konto, Person wechseln)
if (in_array($_POST['action'] ?? '', ['logout', 'switch_to_person', 'change_own_password', 'change_own_email'], true)) {
    csrf_validate();
    handle_navbar_actions($pdo);
}

// Session abbrechen
if (($_GET['action'] ?? '') === 'abort') {
    unset($_SESSION['drill']);
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

// Vorausgewählte Liste aus URL (von home.php oder "Erneut starten") — startet die Session nicht
// mehr direkt, sondern füllt nur die Setup-Seite vor (Richtung/Timer sollen wählbar bleiben).
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

// POST: Session konfigurieren und starten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin') {
    csrf_validate();
    unset($_SESSION['drill']);

    $list_ids  = array_map('intval', array_filter((array)($_POST['list_ids'] ?? [])));
    $direction = resolve_direction($_POST['direction'] ?? null);
    $default_minutes = (int) round(DRILL_SESSION_SECONDS / 60);
    $session_minutes = max(1, min(120, intval($_POST['session_minutes'] ?? $default_minutes)));

    if (!$list_ids) {
        $_SESSION['flash_error'] = 'Bitte mindestens eine Liste auswählen.';
        header('Location: drill.php');
        exit;
    }
    start_drill_session($pdo, $person_id, $list_ids, $direction, $session_minutes * 60);
}

// POST: Karte beantworten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'answer' && isset($_SESSION['drill'])) {
    csrf_validate();

    $state   = &$_SESSION['drill'];
    $card_id = intval($_POST['card_id'] ?? 0);
    $result  = $_POST['result'] ?? '';

    if (!in_array($result, ['known', 'unknown']) || $card_id !== $state['current_card_id']) {
        header('Location: drill.php');
        exit;
    }

    // Event loggen
    $stmt = $pdo->prepare("INSERT INTO learning_events (person_id, card_id, result, learn_date) VALUES (?,?,?,?)");
    $stmt->execute([$person_id, $card_id, $result, $state['today']]);

    // Debug-Modus (Einstellungen → Debug, nur Admins): Vorher-Snapshot vor allen Änderungen —
    // Nachher-Snapshot folgt unten, unverändert an der bestehenden Logik dazwischen.
    $debug_enabled = DEBUG_MODE && !empty($_SESSION['is_admin']);
    $debug_snapshot_sql = "SELECT leitner_box, next_due_date, drill_mastery, drill_too_hard, drill_pinned_correct FROM card_progress WHERE person_id=? AND card_id=?";
    $debug_before = null;
    if ($debug_enabled) {
        $stmt = $pdo->prepare($debug_snapshot_sql);
        $stmt->execute([$person_id, $card_id]);
        $debug_before = $stmt->fetch();
    }

    // Vorgemerkte Karten laufen über einen eigenen Zähler (drill_pinned_correct), komplett getrennt
    // von session_correct/drill_mastery — master_card()/mark_too_hard_card() dürfen hier NIE greifen,
    // sonst würde eine längst weit im Leitner-System fortgeschrittene Karte auf ein niedrigeres
    // Fach zurückgestuft (siehe docs/ANFORDERUNGEN.md, Abschnitt "Manuelle Vormerkung für Drill").
    $is_pinned = in_array($card_id, $state['pool_pinned'] ?? [], true);

    if ($is_pinned) {
        if ($result === 'known') {
            $state['stats']['known']++;
            pin_progress_correct($pdo, $state, $person_id, $card_id);
        } else {
            $state['stats']['unknown']++;
            pin_progress_reset($pdo, $person_id, $card_id);
        }
    } elseif ($result === 'known') {
        $state['stats']['known']++;
        $state['session_correct'][$card_id] = ($state['session_correct'][$card_id] ?? 0) + 1;

        if ($state['session_correct'][$card_id] >= DRILL_MASTERY_THRESHOLD) {
            master_card($pdo, $state, $person_id, $card_id, $state['today']);
        }
    } else {
        $state['stats']['unknown']++;
        $state['session_correct'][$card_id] = 0;
        $state['session_unknown'][$card_id] = ($state['session_unknown'][$card_id] ?? 0) + 1;

        if ($state['session_unknown'][$card_id] >= DRILL_TOO_HARD_LIMIT) {
            mark_too_hard_card($pdo, $state, $person_id, $card_id);
        }
    }

    if ($debug_enabled) {
        $stmt = $pdo->prepare($debug_snapshot_sql);
        $stmt->execute([$person_id, $card_id]);
        $debug_after = $stmt->fetch();
        $mastery_counter  = $state['session_correct'][$card_id] ?? 0;
        $too_hard_counter = $state['session_unknown'][$card_id] ?? 0;
        $_SESSION['debug_last_answer'] = debug_drill_message($pdo, $card_id, $result, $is_pinned, $debug_before, $debug_after, $mastery_counter, $too_hard_counter);
    }

    // Session-Ende: Timer abgelaufen oder keine Karten mehr
    $elapsed  = time() - $state['started_at'];
    $no_cards = empty($state['pool_known']) && empty($state['pool_new']) && empty($state['pool_pinned']);

    if ($elapsed >= $state['session_seconds'] || $no_cards) {
        finish_drill_session($pdo, $state, $person_id);
        header('Location: drill.php?done=1');
        exit;
    }

    $next = next_drill_card($state);
    if ($next === null) {
        finish_drill_session($pdo, $state, $person_id);
        header('Location: drill.php?done=1');
        exit;
    }
    $state['current_card_id'] = $next;
    lazy_reset_drill_too_hard($pdo, $person_id, $next, $state['today']);

    header('Location: drill.php');
    exit;
}

// -------------------------------------------------------
// POST: "Für Drill vormerken" umschalten (während Session) — analog zum Pin-Toggle in learn.php.
// Nur die aktuell angezeigte Karte darf umgeschaltet werden (verhindert Manipulation fremder
// Karten über eine gefälschte card_id). Rührt Queue/Stats der laufenden Session nicht an, ausser
// dem pool_pinned-Zustand: eine Karte gehört exklusiv zu genau einem Pool (siehe load_drill_pool),
// daher beim Vormerken zusätzlich aus pool_known/pool_new entfernen. Beim Aufheben bleibt die Karte
// analog zum automatischen Unpin in pin_progress_correct() ausserhalb aller Pools für den Rest der
// Session — sie taucht in einer künftigen Session über load_drill_pool() wieder als bekannte Karte auf.
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_pin' && isset($_SESSION['drill'])) {
    csrf_validate();

    $state   = &$_SESSION['drill'];
    $card_id = intval($_POST['card_id'] ?? 0);

    if ($card_id !== ($state['current_card_id'] ?? null)) {
        header('Location: drill.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT drill_pinned_correct FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $is_pinned = $stmt->fetchColumn() !== null;

    if ($is_pinned) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = NULL WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$person_id, $card_id]);
        $state['pool_pinned'] = array_values(array_filter($state['pool_pinned'], fn($id) => $id !== $card_id));
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = 0 WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$person_id, $card_id]);
        $state['pool_known'] = array_values(array_filter($state['pool_known'], fn($id) => $id !== $card_id));
        $state['pool_new']   = array_values(array_filter($state['pool_new'], fn($id) => $id !== $card_id));
        if (!in_array($card_id, $state['pool_pinned'], true)) {
            $state['pool_pinned'][] = $card_id;
        }
    }

    header('Location: drill.php');
    exit;
}

// -------------------------------------------------------
// Hilfsfunktionen
// -------------------------------------------------------

// Debug-Modus: baut die Vorher/Nachher-Meldung aus den beiden Snapshots. Erkennt besondere
// Ereignisse (gemeistert, zu schwer markiert, Vormerkung erreicht) an der jeweiligen Feldänderung,
// statt sie separat nachzuverfolgen — robust gegenüber Änderungen an master_card()/mark_too_hard_card().
// Rückgabe: 3-4 Zeilen [Karte, Antwort (Kontext), Detail(s)] — siehe debug_panel() in includes/auth.php.
function debug_drill_message(PDO $pdo, int $card_id, string $result, bool $was_pinned, array $before, array $after, int $mastery_counter, int $too_hard_counter): array {
    $label   = debug_card_label($pdo, $card_id);
    $antwort = $result === 'known' ? 'gewusst' : 'musste nachdenken';

    if ($was_pinned) {
        $box_note = $after['leitner_box'] !== null ? "Fach unverändert (Fach {$after['leitner_box']})" : 'noch nicht in Leitner aktiv (Warteschlange)';

        if ($before['drill_pinned_correct'] !== null && $after['drill_pinned_correct'] === null) {
            $detail = "Vormerkung entfernt, {$box_note}";
        } else {
            $detail = 'Vormerkungszähler ' . ($before['drill_pinned_correct'] ?? 0) . '→' . ($after['drill_pinned_correct'] ?? 0);
        }
        return [$label, "{$antwort} (vorgemerkt)", $detail];
    }

    if ((int)$before['drill_mastery'] !== (int)$after['drill_mastery']) {
        $detail = "gemeistert ({$after['drill_mastery']}×): Fach {$before['leitner_box']}→{$after['leitner_box']}, fällig "
            . debug_format_date($before['next_due_date']) . '→' . debug_format_date($after['next_due_date']);
        return [$label, $antwort, $detail];
    }

    if ((int)$before['drill_too_hard'] !== (int)$after['drill_too_hard']) {
        return [$label, $antwort, 'als zu schwer markiert, bis morgen pausiert'];
    }

    return [
        $label,
        $antwort,
        "Mastery-Zähler {$mastery_counter}/" . DRILL_MASTERY_THRESHOLD,
        "Zu-schwer-Zähler {$too_hard_counter}/" . DRILL_TOO_HARD_LIMIT,
    ];
}

function start_drill_session(PDO $pdo, int $person_id, array $list_ids, string $direction, int $session_seconds): void {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, language_a FROM lists WHERE id IN ($placeholders) AND person_id = ?");
    $stmt->execute(array_merge($list_ids, [$person_id]));
    $valid_rows = $stmt->fetchAll();
    $valid_ids = array_column($valid_rows, 'id');

    if (!$valid_ids) {
        $_SESSION['flash_error'] = 'Keine gültige Liste ausgewählt.';
        header('Location: drill.php');
        exit;
    }

    // Besteht die Auswahl ausschliesslich aus Mathe-Listen, ist nur "Aufgabe → Ergebnis" sinnvoll —
    // unabhängig vom Formularwert erzwingen (Formular blendet die anderen Optionen zwar aus, das
    // ersetzt aber keine serverseitige Prüfung).
    if (!array_filter($valid_rows, fn($r) => !is_math_list($r))) {
        $direction = 'a_to_b';
    }

    $today = today();
    ['known' => $pool_known, 'new' => $pool_new, 'pinned' => $pool_pinned] = load_drill_pool($pdo, $person_id, $valid_ids, $today);
    limit_active_pool($pool_known, $pool_new, $session_seconds);

    if (!$pool_known && !$pool_new && !$pool_pinned) {
        $_SESSION['flash_error'] = 'Keine geeigneten Karten für Drill in dieser Liste.';
        header('Location: drill.php');
        exit;
    }

    $upd = $pdo->prepare("UPDATE lists SET last_used_at = NOW() WHERE id = ?");
    foreach ($valid_ids as $lid) {
        $upd->execute([$lid]);
    }

    $state = [
        'list_ids'        => $valid_ids,
        'direction'       => $direction,
        'session_seconds' => $session_seconds,
        'pool_known'      => $pool_known,
        'pool_new'        => $pool_new,
        'pool_pinned'     => $pool_pinned,
        'cycle_pos'       => 0,
        'pin_cycle_pos'   => 0,
        'current_card_id' => null,
        'session_correct' => [],
        'session_unknown' => [],
        'mastered_cards'  => [],
        'too_hard'        => [],
        'stats'           => ['known' => 0, 'unknown' => 0, 'mastered' => 0],
        'started_at'      => time(),
        'today'           => $today,
    ];

    $first = next_drill_card($state);
    if ($first === null) {
        $_SESSION['flash_error'] = 'Keine geeigneten Karten für Drill in dieser Liste.';
        header('Location: drill.php');
        exit;
    }
    $state['current_card_id'] = $first;
    $_SESSION['drill'] = $state;

    lazy_reset_drill_too_hard($pdo, $person_id, $first, $today);

    header('Location: drill.php');
    exit;
}

function load_drill_pool(PDO $pdo, int $person_id, array $list_ids, string $today): array {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $params = array_merge([$person_id], $list_ids, [$today]);

    // Vorgemerkte Karten (drill_pinned_correct IS NOT NULL) sind von der drill_too_hard-Tagessperre
    // ausgenommen — sie sollen trotz wiederholtem "Musste nachdenken" im Pool bleiben.
    $stmt = $pdo->prepare("
        SELECT cp.card_id, cp.drill_mastery, cp.drill_pinned_correct
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ?
          AND c.list_id IN ($placeholders)
          AND cp.status != 'archived'
          AND (cp.drill_pinned_correct IS NOT NULL
               OR cp.drill_too_hard = 0
               OR (cp.drill_too_hard = 1 AND (cp.last_drill_shown IS NULL OR cp.last_drill_shown < ?)))
        ORDER BY RAND()
    ");
    $stmt->execute($params);

    // Eine Karte landet ausschliesslich in einem der drei Pools — pinned hat Vorrang vor
    // known/new, damit der Antwort-Handler eindeutig weiss, welcher Zweig zuständig ist.
    $known  = [];
    $new    = [];
    $pinned = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['drill_pinned_correct'] !== null) {
            $pinned[] = (int)$row['card_id'];
        } elseif ((int)$row['drill_mastery'] >= 1) {
            $known[] = (int)$row['card_id'];
        } else {
            $new[] = (int)$row['card_id'];
        }
    }
    return ['known' => $known, 'new' => $new, 'pinned' => $pinned];
}

// Begrenzt den aktiven Pool (known+new, ohne pinned) auf eine an die Session-Länge gekoppelte
// Grösse — sonst wird bei einer grossen Liste sofort die komplette Liste in die Rotation geladen
// und die Wiederholung jeder einzelnen Karte verdünnt sich so stark, dass die Mastery-Schwelle
// (X× hintereinander richtig) in einer kurzen Session praktisch nie erreicht wird (siehe
// DRILL_CARDS_PER_MINUTE in config.php). Beide Arrays kommen bereits per SQL RAND() sortiert aus
// load_drill_pool() — ein einfaches Abschneiden ist damit schon eine Zufallsauswahl, kein erneutes
// Mischen nötig. Bekannte Karten (bereits mit Fortschritt) werden bevorzugt behalten, neue Karten
// füllen den verbleibenden Platz auf; überzählige Karten aus beiden Pools bleiben für diese Session
// einfach unberücksichtigt und werden beim nächsten Sessionstart über load_drill_pool() neu geladen.
function limit_active_pool(array &$pool_known, array &$pool_new, int $session_seconds): void {
    $minutes = $session_seconds / 60;
    $max_active = max(DRILL_MIN_ACTIVE_CARDS, (int) round($minutes * DRILL_CARDS_PER_MINUTE));

    if (count($pool_known) + count($pool_new) <= $max_active) {
        return;
    }

    $keep_known = min(count($pool_known), $max_active);
    $pool_known = array_slice($pool_known, 0, $keep_known);
    $pool_new   = array_slice($pool_new, 0, max(0, $max_active - $keep_known));
}

// Wählt die nächste Karte. Vorgemerkte Karten (pool_pinned) werden priorisiert eingeschoben:
// Modus 'absolute' = immer zuerst, solange welche vorgemerkt sind; Modus 'weighted' = alle
// DRILL_PIN_RATIO Karten eine vorgemerkte einschieben, das bekannte 9:1-Prinzip (Known-Pool
// rotierend, jede 9. Karte neu) läuft für die übrigen Karten unverändert parallel weiter.
function next_drill_card(array &$state): ?int {
    $has_pinned = !empty($state['pool_pinned']);
    $has_known  = !empty($state['pool_known']);
    $has_new    = !empty($state['pool_new']);

    if (!$has_pinned && !$has_known && !$has_new) return null;

    $pin_due = $has_pinned && (
        DRILL_PIN_MODE === 'absolute'
        || $state['pin_cycle_pos'] >= DRILL_PIN_RATIO
        || (!$has_known && !$has_new)
    );

    if ($pin_due) {
        $state['pin_cycle_pos'] = 0;
        $id = array_shift($state['pool_pinned']);
        $state['pool_pinned'][] = $id;
        return $id;
    }

    $ratio    = DRILL_KNOWN_RATIO;
    $pick_new = ($state['cycle_pos'] >= $ratio) || !$has_known;

    if ($pick_new && $has_new) {
        $state['cycle_pos'] = 0;
        $id = array_shift($state['pool_new']);
        $state['pool_known'][] = $id;
    } else {
        $state['cycle_pos']++;
        $id = array_shift($state['pool_known']);
        $state['pool_known'][] = $id;
    }
    if ($has_pinned) $state['pin_cycle_pos']++;
    return $id;
}

// Richtige Antwort auf eine vorgemerkte Karte: eigener Zähler, rührt leitner_box/status/
// drill_mastery nie an. Das UPDATE ist bedingt auf "noch vorgemerkt" — wurde die Vormerkung
// zwischenzeitlich manuell entfernt (z.B. zweiter Tab während laufender Session), wird dieses
// Increment zum No-Op statt die Vormerkung ungewollt wiederzubeleben.
function pin_progress_correct(PDO $pdo, array &$state, int $person_id, int $card_id): void {
    $stmt = $pdo->prepare("
        UPDATE card_progress SET drill_pinned_correct = drill_pinned_correct + 1
        WHERE person_id = ? AND card_id = ? AND drill_pinned_correct IS NOT NULL
    ");
    $stmt->execute([$person_id, $card_id]);

    $stmt = $pdo->prepare("SELECT drill_pinned_correct FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $value = $stmt->fetchColumn();

    if ($value !== false && $value !== null && (int)$value >= DRILL_MASTERY_THRESHOLD) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = NULL WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$person_id, $card_id]);
        $state['pool_pinned'] = array_values(array_filter($state['pool_pinned'], fn($id) => $id !== $card_id));
    }
}

// Falsche Antwort auf eine vorgemerkte Karte: Zähler zurück auf 0 (wie session_correct bei
// known/new), aber keine drill_too_hard-Sperre — Karte bleibt im pool_pinned.
function pin_progress_reset(PDO $pdo, int $person_id, int $card_id): void {
    $stmt = $pdo->prepare("
        UPDATE card_progress SET drill_pinned_correct = 0
        WHERE person_id = ? AND card_id = ? AND drill_pinned_correct IS NOT NULL
    ");
    $stmt->execute([$person_id, $card_id]);
}

function master_card(PDO $pdo, array &$state, int $person_id, int $card_id, string $today): void {
    $state['mastered_cards'][] = $card_id;
    $state['stats']['mastered']++;
    remove_from_pools($state, $card_id);

    $stmt = $pdo->prepare("SELECT drill_mastery, leitner_box FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    $new_mastery = (int)($cp['drill_mastery'] ?? 0) + 1;
    $current_box = (int)($cp['leitner_box'] ?? 1);

    $leitner_transitions = [1 => 2, 2 => 3, 3 => 4];
    $target_box = $leitner_transitions[$new_mastery] ?? null;
    $intervals  = LEITNER_INTERVALS;

    // Meistern ist eine Belohnung und darf das Fach nie zurückstufen. Ist die Karte über normales
    // Leitner-Lernen (unabhängig vom Drill) bereits weiter als die feste Zieltabelle vorsieht,
    // bleibt das Fach unverändert — nur drill_mastery zählt weiter (Bugfix: bisher konnte eine
    // erneute Meisterung eine bereits weiter fortgeschrittene Karte zurückstufen).
    if ($target_box !== null && $target_box <= $current_box) {
        $target_box = null;
    }

    if ($target_box) {
        $due = date('Y-m-d', strtotime($today . ' +' . $intervals[$target_box] . ' days'));
        $stmt = $pdo->prepare("
            UPDATE card_progress
            SET drill_mastery = ?, leitner_box = ?, next_due_date = ?, status = 'active'
            WHERE person_id = ? AND card_id = ?
        ");
        $stmt->execute([$new_mastery, $target_box, $due, $person_id, $card_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_mastery = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$new_mastery, $person_id, $card_id]);
    }
}

function mark_too_hard_card(PDO $pdo, array &$state, int $person_id, int $card_id): void {
    $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 1 WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $state['too_hard'][] = $card_id;
    remove_from_pools($state, $card_id);
}

function remove_from_pools(array &$state, int $card_id): void {
    $state['pool_known'] = array_values(array_filter($state['pool_known'], fn($id) => $id !== $card_id));
    $state['pool_new']   = array_values(array_filter($state['pool_new'],   fn($id) => $id !== $card_id));
}

function lazy_reset_drill_too_hard(PDO $pdo, int $person_id, int $card_id, string $today): void {
    $stmt = $pdo->prepare("SELECT drill_too_hard, last_drill_shown FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    if ($cp && $cp['drill_too_hard'] && $cp['last_drill_shown'] < $today) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 0, last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    }
}

function finish_drill_session(PDO $pdo, array &$state, int $person_id): void {
    $_SESSION['drill_done'] = [
        'stats'        => $state['stats'],
        'mastered_ids' => $state['mastered_cards'],
        'list_ids'     => $state['list_ids'],
    ];

    if ($state['mastered_cards']) {
        $ids = $state['mastered_cards'];
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT card_id, drill_mastery FROM card_progress WHERE person_id = ? AND card_id IN ($ph)");
        $stmt->execute(array_merge([$person_id], $ids));
        $_SESSION['drill_done']['mastery_details'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    unset($_SESSION['drill']);
}

// -------------------------------------------------------
// Anzeige-State
// -------------------------------------------------------
$state     = $_SESSION['drill'] ?? null;
$card_data = null;

$qa = null;
if ($state && $state['current_card_id']) {
    $stmt = $pdo->prepare("
        SELECT c.*, l.language_a, l.language_b, l.speech_lang_b, cp.drill_pinned_correct
        FROM cards c
        JOIN lists l ON l.id = c.list_id
        LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$person_id, $state['current_card_id']]);
    $card_data = $stmt->fetch() ?: null;
    if ($card_data) {
        $qa = get_question_answer($card_data, $state['direction']);
    }
}

$remaining_s = 0;
if ($state) {
    $remaining_s = max(0, $state['session_seconds'] - (time() - $state['started_at']));
}

$done_data = null;
if (isset($_GET['done']) && isset($_SESSION['drill_done'])) {
    $done_data = $_SESSION['drill_done'];
    unset($_SESSION['drill_done']);
}

// SETUP: weder laufende Session noch Abschluss → Listenauswahl/Richtung/Timer anzeigen
$setup_error = '';
if (!$state && !$done_data) {
    $setup_error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_error']);
}
$default_drill_minutes = (int) round(DRILL_SESSION_SECONDS / 60);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drill — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php if ($state): ?>
<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap justify-content-end">
            <!-- Abbruch an erster Stelle, gleiche Position wie in der zentralen Navbar (auth.php) -->
            <a href="drill.php?action=abort" class="btn btn-sm btn-outline-light"
               title="Session abbrechen" aria-label="Session abbrechen"><i class="bi bi-x-lg"></i></a>
            <span class="text-white small fw-semibold" id="drill-timer"></span>
            <span class="text-white small opacity-75">·</span>
            <span class="text-white small"><?= (int)($state['stats']['mastered'] ?? 0) ?> gemeistert</span>
            <a href="help.php" class="btn btn-sm btn-outline-light" title="Hilfe" aria-label="Hilfe"><i class="bi bi-info-lg"></i></a>
        </div>
    </div>
</nav>
<?php else: ?>
<?php render_navbar($pdo); ?>
<?php endif; ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Drill', '']]) ?></div>

<div class="container mt-2" style="max-width:700px;">

<?php if ($done_data !== null): ?>
<!-- ==================== ABSCHLUSS ==================== -->
<?= debug_panel() ?>
<div class="text-center">
    <div class="display-6 mb-2">
        <?= ($done_data['stats']['mastered'] ?? 0) > 0 ? '🎉' : '💪' ?>
    </div>
    <h2 class="h4 mb-4">
        <?= ($done_data['stats']['mastered'] ?? 0) > 0
            ? 'Super! Weiter so!'
            : 'Gut gemacht! Regelmässiges Üben zahlt sich aus.' ?>
    </h2>

    <div class="row g-3 mb-4 justify-content-center">
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-success"><?= $done_data['stats']['known'] ?></div>
                <div class="small text-muted">Gewusst</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-warning"><?= $done_data['stats']['unknown'] ?></div>
                <div class="small text-muted">Musste nachdenken</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-primary"><?= $done_data['stats']['mastered'] ?></div>
                <div class="small text-muted">Gemeistert</div>
            </div>
        </div>
    </div>

    <?php if (!empty($done_data['mastery_details'])): ?>
    <div class="card mb-4 text-start">
        <div class="card-header">Drill-Fortschritt gemeisterter Karten</div>
        <div class="card-body">
            <?php foreach ($done_data['mastery_details'] as $cid => $mastery):
                $sc = $pdo->prepare("SELECT word_a, word_b FROM cards WHERE id = ?");
                $sc->execute([$cid]);
                $cdata = $sc->fetch();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small"><?= htmlspecialchars($cdata['word_a'] ?? '') ?> / <?= htmlspecialchars($cdata['word_b'] ?? '') ?></span>
                <span class="badge bg-primary"><?= (int)$mastery ?>×</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-muted small">Für beste Resultate warte ein paar Stunden bis zur nächsten Session.</p>

    <?php if (count($done_data['list_ids'] ?? []) === 1): ?>
    <a href="drill.php?list_id=<?= (int)$done_data['list_ids'][0] ?>" class="btn btn-primary">Erneut starten</a>
    <?php endif; ?>
</div>

<?php elseif ($state && $card_data): ?>
<!-- ==================== KARTE ==================== -->

<div class="learn-card mx-auto mb-4 position-relative" id="flip-card" style="max-width:540px; cursor:pointer;" onclick="flipCard()">
    <?php $is_pinned = $card_data['drill_pinned_correct'] !== null; ?>
    <form method="post" id="pin-form" class="position-absolute" style="top:8px; left:8px; z-index:2;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_pin">
        <input type="hidden" name="card_id" value="<?= (int)$card_data['id'] ?>">
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
                    data-speak="<?= htmlspecialchars($qa['q_audio']) ?>" data-lang="<?= htmlspecialchars($card_data['speech_lang_b']) ?>">
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

        <div id="card-back" style="display:none;">
            <hr class="my-3">
            <p class="text-muted small mb-1"><?= htmlspecialchars($qa['a_lang']) ?></p>
            <div class="fw-bold fs-3 text-success mb-0">
                <?= htmlspecialchars($qa['a']) ?>
                <?php if ($qa['a_audio']): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary align-middle ms-1"
                        onclick="event.stopPropagation(); speakWord(this)"
                        data-speak="<?= htmlspecialchars($qa['a_audio']) ?>" data-lang="<?= htmlspecialchars($card_data['speech_lang_b']) ?>">
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

        <p class="text-muted small mt-4 mb-0" id="flip-hint">Tippen zum Aufdecken</p>
    </div>
</div>

<div id="answer-btns" class="d-none d-flex gap-3 justify-content-center">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="answer">
        <input type="hidden" name="card_id" value="<?= (int)$card_data['id'] ?>">
        <input type="hidden" name="result" value="unknown">
        <button type="submit" class="btn btn-warning btn-lg">Musste nachdenken</button>
    </form>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="answer">
        <input type="hidden" name="card_id" value="<?= (int)$card_data['id'] ?>">
        <input type="hidden" name="result" value="known">
        <button type="submit" class="btn btn-success btn-lg">Gewusst</button>
    </form>
</div>

<?= debug_panel() ?>

<?php elseif (!$state && !$done_data): ?>
<!-- ==================== SETUP ==================== -->
<h1 class="h4 mb-4">Drill-Session starten</h1>

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

    <?php if ($preset_list): ?>
    <!-- Vorausgewählte Liste (von Startseite oder "Erneut starten") -->
    <input type="hidden" name="list_ids[]" value="<?= $preset_list['id'] ?>">
    <div class="mb-4">
        <div class="fw-semibold mb-1">Liste</div>
        <div class="text-muted"><?= htmlspecialchars($preset_list['name']) ?> <span class="small">(<?= $lang_a ?> / <?= $lang_b ?>)</span></div>
    </div>
    <?php else: ?>
    <!-- Alle aktiven Listen zur Auswahl -->
    <div class="mb-4">
        <label class="form-label fw-semibold">Listen auswählen</label>
        <?php foreach ($all_lists as $list): ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="list_ids[]"
                   value="<?= $list['id'] ?>" id="list_<?= $list['id'] ?>"
                   <?= $list['id'] === ($all_lists[0]['id'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="list_<?= $list['id'] ?>">
                <?= htmlspecialchars($list['name']) ?>
                <span class="text-muted small">(<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)</span>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Lernrichtung -->
    <div class="mb-4">
        <label class="form-label fw-semibold">Lernrichtung</label>
        <div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="direction" id="dir_ab" value="a_to_b" <?= $is_math_preset ? 'checked' : '' ?>>
                <label class="form-check-label" for="dir_ab" id="label_ab"><?= $lang_a ?> → <?= $lang_b ?></label>
            </div>
            <div id="dir-other-options" class="<?= $is_math_preset ? 'd-none' : '' ?>">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="direction" id="dir_ba" value="b_to_a">
                    <label class="form-check-label" for="dir_ba" id="label_ba"><?= $lang_b ?> → <?= $lang_a ?></label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="direction" id="dir_mix" value="mixed">
                    <label class="form-check-label" for="dir_mix">Gemischt</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="direction" id="dir_random" value="random" <?= $is_math_preset ? '' : 'checked' ?>>
                    <label class="form-check-label" for="dir_random">Zufall</label>
                </div>
            </div>
            <p class="text-muted small mb-0 <?= $is_math_preset ? '' : 'd-none' ?>" id="dir-math-hint">Bei Mathe-Listen ist nur "Aufgabe → Ergebnis" sinnvoll.</p>
        </div>
    </div>

    <!-- Timer (nur für diese Session, wird nicht dauerhaft gespeichert) -->
    <div class="mb-4">
        <label class="form-label fw-semibold">Timer</label>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="adjustMinutes(-5)">−5</button>
            <input type="number" name="session_minutes" id="session_minutes" class="form-control text-center" value="<?= $default_drill_minutes ?>" min="1" max="120" style="width:80px;">
            <span class="text-muted">Min.</span>
            <button type="button" class="btn btn-outline-secondary" onclick="adjustMinutes(5)">+5</button>
        </div>
        <div class="form-text">Gilt nur für diese Session. Standard aus den Einstellungen: <?= $default_drill_minutes ?> Min.</div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Drill starten</button>
</form>
<?php endif; ?>

<?php endif; ?>
</div>

<?php if ($state && $card_data): ?>
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
// Verhindert dass bfcache eine aufgeklappte Karte mit sichtbarer Antwort wiederherstellt
window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
});

// speechSynthesis spielt auf iOS/Android sonst nur über Kopfhörer bzw. den Ohrhörer statt über den
// Lautsprecher, weil die Audio-Session des Geräts dafür nicht aktiviert ist. Trick: einmalig (im
// selben Klick-Handler, iOS verlangt eine User-Geste) ein kurzes stummes <audio>-Element abspielen —
// das zwingt die Audio-Session auf die Kategorie "playback", danach läuft speechSynthesis normal
// über den Lautsprecher. Auf Desktop-Browsern tritt das Problem nicht auf, dort bleibt es beim
// bisherigen Verhalten.
var isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
var isAndroid = /Android/i.test(navigator.userAgent);
var audioSessionUnlocked = false;

function unlockAudioSession() {
    if (audioSessionUnlocked) return;
    audioSessionUnlocked = true;
    var silence = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=');
    silence.play().catch(function () {});
}

function speakWord(btn) {
    if (!('speechSynthesis' in window)) return;
    if (isIOS || isAndroid) unlockAudioSession();
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

function adjustMinutes(delta) {
    const el = document.getElementById('session_minutes');
    if (el) el.value = Math.max(1, Math.min(120, parseInt(el.value || <?= $default_drill_minutes ?>) + delta));
}

<?php if (!$state && !$done_data && !$preset_list && $all_lists): ?>
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
    // sinnvolle Richtung — bei Mischauswahl mit Wortlisten bleiben alle Optionen verfügbar.
    const allMath = checked.length > 0 && checked.every(cb => langMap[cb.value] && langMap[cb.value].math);
    document.getElementById('dir-other-options').classList.toggle('d-none', allMath);
    document.getElementById('dir-math-hint').classList.toggle('d-none', !allMath);
    if (allMath) {
        document.getElementById('dir_ab').checked = true;
    }
}

document.querySelectorAll('input[name="list_ids[]"]').forEach(cb => {
    cb.addEventListener('change', updateDirLabels);
});
updateDirLabels();
<?php endif; ?>

<?php if ($state && $card_data): ?>
function flipCard() {
    var card = document.getElementById('flip-card');
    card.style.transform = 'scaleX(0)';
    setTimeout(function () {
        document.getElementById('card-back').style.display = 'block';
        document.getElementById('flip-hint').style.display = 'none';
        card.style.transform = 'scaleX(1)';
    }, 150);
    setTimeout(function () {
        document.getElementById('answer-btns').classList.remove('d-none');
        card.style.cursor = 'default';
        card.onclick = null;
    }, 300);
}

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
        if (target) window.location.href = 'drill.php?action=abort&to=' + encodeURIComponent(target);
    });
})();

// Vormerken per Fetch statt normalem Form-Submit — ein voller Seiten-Reload würde die aufgedeckte
// Antwort (rein clientseitiger Zustand, siehe flipCard()) wieder verstecken. Analog zu learn.php.
(function () {
    var form = document.getElementById('pin-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = form.querySelector('button');
        var icon = btn.querySelector('i');
        fetch('drill.php', { method: 'POST', body: new FormData(form) }).then(function (res) {
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

(function () {
    let remaining = <?= (int)$remaining_s ?>;
    const el = document.getElementById('drill-timer');
    if (!el || remaining <= 0) return;
    function tick() {
        if (remaining < 0) remaining = 0;
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        if (remaining > 0) { remaining--; setTimeout(tick, 1000); }
    }
    tick();
})();
</script>
</body>
</html>
