<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];

// Logout
if (($_POST['action'] ?? '') === 'logout') {
    csrf_validate();
    logout();
}

// Session zurücksetzen
if (($_GET['action'] ?? '') === 'setup') {
    unset($_SESSION['drill']);
}

// Eigene Listen laden
$stmt = $pdo->prepare("SELECT id, name, language_a, language_b FROM lists WHERE person_id = ? ORDER BY last_used_at DESC, name");
$stmt->execute([$person_id]);
$all_lists = $stmt->fetchAll();

// -------------------------------------------------------
// POST: Drill-Session starten
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin') {
    csrf_validate();
    unset($_SESSION['drill']);

    $list_ids = array_map('intval', array_filter((array)($_POST['list_ids'] ?? [])));
    if (!$list_ids) {
        $setup_error = 'Bitte mindestens eine Liste auswählen.';
        goto render_setup;
    }

    // Besitzer prüfen
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $stmt = $pdo->prepare("SELECT id FROM lists WHERE id IN ($placeholders) AND person_id = ?");
    $stmt->execute(array_merge($list_ids, [$person_id]));
    $valid_ids = array_column($stmt->fetchAll(), 'id');
    if (!$valid_ids) {
        $setup_error = 'Keine gültige Liste ausgewählt.';
        goto render_setup;
    }

    $today = today();

    // Karten für Drill laden
    $pool = load_drill_pool($pdo, $person_id, $valid_ids, $today);

    // Initiale 3 Karten
    $active_cards = array_splice($pool, 0, DRILL_ACTIVE_CARDS);
    if (!$active_cards) {
        $setup_error = 'Keine geeigneten Karten für Drill vorhanden.';
        goto render_setup;
    }

    // Learning-Session anlegen
    $stmt = $pdo->prepare("INSERT INTO learning_sessions (person_id, mode, started_at) VALUES (?,?,NOW())");
    $stmt->execute([$person_id, 'drill']);
    $session_id = (int) $pdo->lastInsertId();

    $ins_sl = $pdo->prepare("INSERT INTO session_lists (session_id, list_id) VALUES (?,?)");
    foreach ($valid_ids as $lid) {
        $ins_sl->execute([$session_id, $lid]);
    }
    $upd = $pdo->prepare("UPDATE lists SET last_used_at = NOW() WHERE id = ?");
    foreach ($valid_ids as $lid) {
        $upd->execute([$lid]);
    }

    // State initialisieren
    // active: [{card_id, rounds_participated, correct_in_fixed, correct_in_mixed, phase}]
    // phase: 'fixed' | 'mixed'
    $active = [];
    foreach ($active_cards as $cid) {
        $active[] = build_card_state($cid);
    }

    $_SESSION['drill'] = [
        'session_id'  => $session_id,
        'list_ids'    => $valid_ids,
        'pool'        => $pool,
        'active'      => $active,           // 3 aktive Karten
        'queue'       => array_column($active, 'card_id'), // aktuelle Runden-Reihenfolge
        'phase'       => 'fixed',           // 'fixed' | 'mixed'
        'too_hard'    => [],                // card_ids die heute zu schwer sind
        'mastered_session' => [],           // card_ids in dieser Session gemeistert
        'stats'       => ['known' => 0, 'unknown' => 0, 'mastered' => 0],
        'started_at'  => time(),
        'today'       => $today,
    ];

    header('Location: drill.php');
    exit;
}

// -------------------------------------------------------
// POST: Karte beantworten
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'answer' && isset($_SESSION['drill'])) {
    csrf_validate();

    $state   = &$_SESSION['drill'];
    $card_id = intval($_POST['card_id'] ?? 0);
    $result  = $_POST['result'] ?? ''; // 'known' | 'unknown'
    $today   = $state['today'];
    $session_id = $state['session_id'];

    if (!in_array($result, ['known', 'unknown'])) {
        header('Location: drill.php');
        exit;
    }

    // drill_too_hard lazy reset: wenn Karte heute zum ersten Mal gezeigt wird
    // (Dieser Check passiert beim ersten Zeigen — hier beim ersten Antworten ist korrekt genug)
    lazy_reset_drill_too_hard($pdo, $person_id, $card_id, $today);

    // Event loggen
    $stmt = $pdo->prepare("INSERT INTO learning_events (session_id, person_id, card_id, result, learn_date) VALUES (?,?,?,?,?)");
    $stmt->execute([$session_id, $person_id, $card_id, $result, $today]);

    // Karte im active-Array finden
    $card_index = array_search($card_id, array_column($state['active'], 'card_id'));

    if ($result === 'known') {
        $state['stats']['known']++;
        $state['active'][$card_index]['correct_count']++;
        $state['active'][$card_index]['rounds_participated']++;

        // Prüfen ob Runde abgeschlossen (alle 3 Karten im Queue beantwortet)
        advance_drill_queue($pdo, $state, $person_id, $today, $session_id);

    } else {
        // Nicht gewusst
        $state['stats']['unknown']++;
        $state['active'][$card_index]['unknown_count']++;
        $state['active'][$card_index]['correct_count'] = 0; // Reset für diese Runde
        $state['active'][$card_index]['rounds_participated']++;

        // Karte zu schwer nach 5× nicht gewusst
        $threshold = DRILL_TOO_HARD_LIMIT;
        if ($state['active'][$card_index]['unknown_count'] >= $threshold) {
            mark_too_hard($pdo, $state, $person_id, $card_id, $card_index, $today);
        } else {
            // Ans Ende der aktuellen Queue
            $pos = array_search($card_id, $state['queue']);
            if ($pos !== false) {
                array_splice($state['queue'], $pos, 1);
            }
            $state['queue'][] = $card_id;
        }
    }

    // Session-Ende prüfen: Timer abgelaufen oder keine Karten mehr
    $elapsed = time() - $state['started_at'];
    $no_more_cards = empty($state['queue']) && empty($state['pool']);

    if ($elapsed >= DRILL_SESSION_SECONDS && empty($state['queue'])) {
        // Timer abgelaufen + aktuelle Runde beendet
        finish_drill_session($pdo, $state, $person_id);
        header('Location: drill.php?done=1');
        exit;
    } elseif ($no_more_cards) {
        finish_drill_session($pdo, $state, $person_id);
        header('Location: drill.php?done=1');
        exit;
    }

    header('Location: drill.php');
    exit;
}

// -------------------------------------------------------
// Hilfsfunktionen
// -------------------------------------------------------

function build_card_state(int $card_id): array {
    return [
        'card_id'              => $card_id,
        'rounds_participated'  => 0,
        'correct_count'        => 0, // aufeinanderfolgende korrekte Antworten in aktueller Phase
        'unknown_count'        => 0, // Anzahl "Noch nicht gewusst" in dieser Session
    ];
}

function load_drill_pool(PDO $pdo, int $person_id, array $list_ids, string $today): array {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));

    // Karten holen: status = active, nicht archived — sortiert nach Priorität
    // Priorität 1: noch nie gedrillt (last_drill_shown IS NULL)
    // Priorität 2: höchste unknown-Quote (drill_too_hard = false)
    // Auffüllen aus queued, dann drill_too_hard, dann archived
    $params = array_merge([$person_id], $list_ids);

    $stmt = $pdo->prepare("
        SELECT cp.card_id,
               cp.drill_too_hard,
               cp.last_drill_shown,
               cp.status,
               COALESCE(
                 (SELECT COUNT(*) FROM learning_events le WHERE le.card_id = cp.card_id AND le.person_id = cp.person_id AND le.result = 'unknown'),
                 0
               ) AS unknown_count,
               COALESCE(
                 (SELECT COUNT(*) FROM learning_events le WHERE le.card_id = cp.card_id AND le.person_id = cp.person_id AND le.result IN ('known','unknown')),
                 0
               ) AS total_count
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ?
          AND c.list_id IN ($placeholders)
          AND cp.status != 'archived'
          AND cp.drill_too_hard = 0
        ORDER BY
          cp.last_drill_shown IS NOT NULL,
          CASE WHEN total_count > 0 THEN unknown_count / total_count ELSE 0 END DESC,
          cp.id
        LIMIT 50
    ");
    $stmt->execute($params);
    $primary = array_column($stmt->fetchAll(), 'card_id');

    if (count($primary) >= DRILL_ACTIVE_CARDS) return $primary;

    // Auffüllen: queued → drill_too_hard → archived
    $needed = DRILL_ACTIVE_CARDS - count($primary);
    $exclude = $primary;

    foreach (['queued', 'active', 'archived'] as $fallback_status) {
        if ($needed <= 0) break;
        $ex_placeholders = $exclude ? implode(',', array_fill(0, count($exclude), '?')) : 'NULL';
        $fallback_params = array_merge([$person_id], $list_ids);

        // drill_too_hard = 1 für den drill_too_hard-Fallback
        if ($fallback_status === 'active') {
            $stmt = $pdo->prepare("
                SELECT cp.card_id FROM card_progress cp
                JOIN cards c ON c.id = cp.card_id
                WHERE cp.person_id = ? AND c.list_id IN ($placeholders)
                  AND cp.status = 'active' AND cp.drill_too_hard = 1
                " . ($exclude ? "AND cp.card_id NOT IN ($ex_placeholders)" : "") . "
                LIMIT ?
            ");
            $fallback_params = array_merge([$person_id], $list_ids, $exclude, [$needed]);
        } else {
            $stmt = $pdo->prepare("
                SELECT cp.card_id FROM card_progress cp
                JOIN cards c ON c.id = cp.card_id
                WHERE cp.person_id = ? AND c.list_id IN ($placeholders)
                  AND cp.status = ?
                " . ($exclude ? "AND cp.card_id NOT IN ($ex_placeholders)" : "") . "
                LIMIT ?
            ");
            $fallback_params = array_merge([$person_id], $list_ids, [$fallback_status], $exclude, [$needed]);
        }

        $stmt->execute($fallback_params);
        $extra = array_column($stmt->fetchAll(), 'card_id');
        $primary = array_merge($primary, $extra);
        $exclude = $primary;
        $needed -= count($extra);
    }

    return $primary;
}

function lazy_reset_drill_too_hard(PDO $pdo, int $person_id, int $card_id, string $today): void {
    $stmt = $pdo->prepare("SELECT drill_too_hard, last_drill_shown FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    if ($cp && $cp['drill_too_hard'] && $cp['last_drill_shown'] < $today) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 0, last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    } else {
        // last_drill_shown aktualisieren
        $stmt = $pdo->prepare("UPDATE card_progress SET last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    }
}

function advance_drill_queue(PDO $pdo, array &$state, int $person_id, string $today, int $session_id): void {
    // Entfernte Karte aus Queue-Position
    $current_card = $state['queue'][0] ?? null;
    if ($current_card === null) return;

    // Prüfen ob alle Karten in dieser Runde "known" waren
    $card_index = array_search($current_card, array_column($state['active'], 'card_id'));
    $card = $state['active'][$card_index];

    // Karte aus Queue entfernen (sie kommt weiter unten eventuell wieder rein)
    array_shift($state['queue']);

    // Queue leer = Runde abgeschlossen
    if (!empty($state['queue'])) {
        return; // Noch nicht alle beantwortet in dieser Runde
    }

    // Alle 3 Karten der Runde wurden beantwortet
    // Prüfen ob alle Karten in dieser Runde "known" waren
    $all_correct = true;
    foreach ($state['active'] as $ac) {
        if ($ac['correct_count'] === 0) {
            $all_correct = false;
            break;
        }
    }

    if (!$all_correct) {
        // Nicht alle korrekt → neue Runde in gleicher Reihenfolge
        $state['queue'] = array_column($state['active'], 'card_id');
        foreach ($state['active'] as &$ac) {
            $ac['correct_count'] = 0;
        }
        return;
    }

    if ($state['phase'] === 'fixed') {
        // Alle korrekt in fixer Reihenfolge → Mischen
        $state['phase'] = 'mixed';
        $ids = array_column($state['active'], 'card_id');
        shuffle($ids);
        $state['queue'] = $ids;
        foreach ($state['active'] as &$ac) {
            $ac['correct_count'] = 0;
        }
        return;
    }

    // Alle korrekt auch gemischt → eine Karte ist gemeistert
    // Gemeistert: die mit den meisten rounds_participated, bei Gleichstand: die zuerst geladene (niedrigster index)
    $best_index = 0;
    $best_rounds = -1;
    foreach ($state['active'] as $idx => $ac) {
        if ($ac['rounds_participated'] > $best_rounds) {
            $best_rounds = $ac['rounds_participated'];
            $best_index  = $idx;
        }
    }

    $mastered_card_id = $state['active'][$best_index]['card_id'];
    $state['mastered_session'][] = $mastered_card_id;
    $state['stats']['mastered']++;

    // drill_mastery erhöhen und ggf. Leitner-Box setzen
    $stmt = $pdo->prepare("SELECT drill_mastery FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $mastered_card_id]);
    $cp = $stmt->fetch();
    $new_mastery = (int)($cp['drill_mastery'] ?? 0) + 1;

    $intervals = LEITNER_INTERVALS;
    $leitner_transitions = [1 => 2, 2 => 3, 3 => 4];
    $target_box = $leitner_transitions[$new_mastery] ?? null;

    if ($target_box) {
        $interval = $intervals[$target_box];
        $due = date('Y-m-d', strtotime($today . " +$interval days"));
        $stmt = $pdo->prepare("
            UPDATE card_progress
            SET drill_mastery = ?, leitner_box = ?, next_due_date = ?, status = 'active'
            WHERE person_id = ? AND card_id = ?
        ");
        $stmt->execute([$new_mastery, $target_box, $due, $person_id, $mastered_card_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_mastery = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$new_mastery, $person_id, $mastered_card_id]);
    }

    // Gemeisterte Karte aus active entfernen, neue nachladen
    array_splice($state['active'], $best_index, 1);

    $new_card_id = array_shift($state['pool']);
    if ($new_card_id) {
        $state['active'][] = build_card_state($new_card_id);
    }

    // Neue Queue: verbleibende 2 + neue Karte, fixer Phase zurücksetzen
    $state['phase'] = 'fixed';
    $state['queue'] = array_column($state['active'], 'card_id');
    foreach ($state['active'] as &$ac) {
        $ac['correct_count'] = 0;
    }
}

function mark_too_hard(PDO $pdo, array &$state, int $person_id, int $card_id, int $card_index, string $today): void {
    // drill_too_hard setzen
    $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 1 WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);

    $state['too_hard'][] = $card_id;

    // Aus active und queue entfernen
    array_splice($state['active'], $card_index, 1);
    $state['queue'] = array_filter($state['queue'], fn($id) => $id !== $card_id);
    $state['queue'] = array_values($state['queue']);

    // Neue Karte aus Pool nachladen
    $new_card_id = array_shift($state['pool']);
    if ($new_card_id) {
        $state['active'][] = build_card_state($new_card_id);
        $state['queue'][] = $new_card_id;
    }

    if (empty($state['active'])) {
        $state['queue'] = [];
    }
}

function finish_drill_session(PDO $pdo, array &$state, int $person_id): void {
    $stmt = $pdo->prepare("UPDATE learning_sessions SET completed_at = NOW() WHERE id = ?");
    $stmt->execute([$state['session_id']]);

    $_SESSION['drill_done'] = [
        'stats'   => $state['stats'],
        'mastered_ids' => $state['mastered_session'],
        'list_ids' => $state['list_ids'],
    ];

    // Drill-Mastery-Details für Abschlussseite laden
    if ($state['mastered_session']) {
        $ids = $state['mastered_session'];
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT card_id, drill_mastery FROM card_progress WHERE person_id = ? AND card_id IN ($ph)");
        $stmt->execute(array_merge([$person_id], $ids));
        $_SESSION['drill_done']['mastery_details'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    unset($_SESSION['drill']);
}

// -------------------------------------------------------
// STATE: Aktuelle Karte laden
// -------------------------------------------------------
$state   = $_SESSION['drill'] ?? null;
$current = null;

if ($state && !empty($state['queue'])) {
    $card_id = $state['queue'][0];
    $stmt = $pdo->prepare("
        SELECT c.*, l.language_a, l.language_b
        FROM cards c
        JOIN lists l ON l.id = c.list_id
        WHERE c.id = ?
    ");
    $stmt->execute([$card_id]);
    $current = $stmt->fetch();

    // lazy reset drill_too_hard beim Zeigen der Karte
    if ($current) {
        lazy_reset_drill_too_hard($pdo, $person_id, $card_id, $state['today']);
    }
}

// Session-Abschluss
$done_data = null;
if (isset($_GET['done']) && isset($_SESSION['drill_done'])) {
    $done_data = $_SESSION['drill_done'];
    unset($_SESSION['drill_done']);
}

// Timer berechnen
$elapsed      = $state ? (time() - $state['started_at']) : 0;
$remaining_s  = max(0, DRILL_SESSION_SECONDS - $elapsed);

$setup_error = '';

render_setup:
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drill — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php if ($state): ?>
            <span class="text-white small" id="timer"></span>
            <a href="drill.php?action=setup" class="btn btn-sm btn-outline-light">Session abbrechen</a>
            <?php else: ?>
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container mt-4" style="max-width:700px;">

<?php if ($done_data !== null): ?>
<!-- ==================== DRILL ABSCHLUSS ==================== -->
<div class="text-center">
    <div class="display-6 mb-2">
        <?php echo $done_data['stats']['mastered'] > 0 ? '🎉' : '💪'; ?>
    </div>
    <h2 class="h4 mb-4">
        <?php echo $done_data['stats']['mastered'] > 0 ? 'Super! Weiter trainieren!' : 'Gut gemacht! Regelmässiges Üben zahlt sich aus.'; ?>
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
                <div class="h3 text-danger"><?= $done_data['stats']['unknown'] ?></div>
                <div class="small text-muted">Noch nicht gewusst</div>
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
    <div class="card mb-4">
        <div class="card-header">Drill-Fortschritt gemeisterter Karten</div>
        <div class="card-body">
            <?php foreach ($done_data['mastery_details'] as $cid => $mastery): ?>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <?php
                $sc = $pdo->prepare("SELECT word_a, word_b FROM cards WHERE id = ?");
                $sc->execute([$cid]);
                $cdata = $sc->fetch();
                ?>
                <span class="small"><?= htmlspecialchars($cdata['word_a'] ?? '') ?> / <?= htmlspecialchars($cdata['word_b'] ?? '') ?></span>
                <span class="badge bg-primary"><?= $mastery ?>×</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-muted small">Für beste Resultate warte ein paar Stunden bis zur nächsten Session.</p>
    <a href="home.php" class="btn btn-primary me-2">Zur Startseite</a>
    <a href="drill.php" class="btn btn-outline-primary">Neue Drill-Session</a>

<?php elseif ($state && $current): ?>
<!-- ==================== DRILL-KARTE ==================== -->
<?php
$card_index_active = array_search($current['id'], array_column($state['active'], 'card_id'));
$card_state = $state['active'][$card_index_active] ?? null;
?>

<!-- Aktive Karten-Slots -->
<div class="d-flex gap-2 mb-3 justify-content-center">
    <?php foreach ($state['active'] as $ac): ?>
    <div class="badge <?= $ac['card_id'] === $current['id'] ? 'bg-primary' : 'bg-secondary' ?> px-3 py-2">
        #<?= $ac['card_id'] ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="text-center mb-2">
    <small class="text-muted">
        Phase: <?= $state['phase'] === 'fixed' ? 'Feste Reihenfolge' : 'Gemischt' ?>
        · <?= $remaining_s > 0 ? gmdate('i:s', $remaining_s) : '00:00' ?> verbleibend
    </small>
</div>

<!-- Karte -->
<div class="card shadow text-center mb-4" style="min-height:200px;">
    <div class="card-body d-flex flex-column justify-content-center p-4">
        <p class="text-muted small mb-2"><?= htmlspecialchars($current['language_a']) ?></p>
        <h2 class="h3 mb-1"><?= htmlspecialchars($current['word_a']) ?></h2>
        <?php if ($current['desc_a']): ?>
        <p class="text-muted"><?= htmlspecialchars($current['desc_a']) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Antwort-Buttons -->
<div class="row g-3 justify-content-center">
    <div class="col-auto">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="card_id" value="<?= $current['id'] ?>">
            <input type="hidden" name="result" value="unknown">
            <button type="submit" class="btn btn-danger btn-lg px-4">Noch nicht gewusst</button>
        </form>
    </div>
    <div class="col-auto">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="card_id" value="<?= $current['id'] ?>">
            <input type="hidden" name="result" value="known">
            <button type="submit" class="btn btn-success btn-lg px-4">Gewusst</button>
        </form>
    </div>
</div>

<div class="text-center mt-3 text-muted small">
    <?= htmlspecialchars($current['language_b']) ?>: <?= htmlspecialchars($current['word_b']) ?>
</div>

<?php else: ?>
<!-- ==================== DRILL SETUP ==================== -->
<h1 class="h4 mb-4">Drill-Session starten</h1>

<?php if ($setup_error ?? ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($setup_error) ?></div>
<?php endif; ?>

<div class="alert alert-info small">
    <strong>Wie Drill funktioniert:</strong> 3 Karten werden gleichzeitig geübt. Gewusste Karten steigen auf und verlassen die Gruppe — neue kommen nach.
    Die Session läuft <?= DRILL_SESSION_SECONDS / 60 ?> Minuten.
</div>

<?php if (!$all_lists): ?>
<p class="text-muted">Du hast noch keine Listen. <a href="lists.php">Erstelle zuerst eine Liste</a>.</p>
<?php else: ?>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="begin">

    <div class="mb-4">
        <label class="form-label fw-semibold">Listen auswählen</label>
        <?php foreach ($all_lists as $list): ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="list_ids[]"
                   value="<?= $list['id'] ?>" id="dlist_<?= $list['id'] ?>"
                   <?= $list['id'] === ($all_lists[0]['id'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="dlist_<?= $list['id'] ?>">
                <?= htmlspecialchars($list['name']) ?>
                <span class="text-muted small">(<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)</span>
            </label>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Drill starten</button>
</form>
<?php endif; ?>

<?php endif; ?>
</div>

<?php if ($state): ?>
<script>
// Countdown-Timer
let remaining = <?= $remaining_s ?>;
const timerEl = document.getElementById('timer');
function updateTimer() {
    if (!timerEl) return;
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerEl.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    if (remaining > 0) {
        remaining--;
        setTimeout(updateTimer, 1000);
    }
}
updateTimer();
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
