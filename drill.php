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

// Session abbrechen → Startseite
if (($_GET['action'] ?? '') === 'setup') {
    unset($_SESSION['drill']);
    header('Location: home.php');
    exit;
}

// -------------------------------------------------------
// POST: Drill-Session starten
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin') {
    csrf_validate();
    unset($_SESSION['drill']);

    $list_ids = array_map('intval', array_filter((array)($_POST['list_ids'] ?? [])));
    if (!$list_ids) {
        $_SESSION['flash_error'] = 'Bitte mindestens eine Liste auswählen.';
        header('Location: home.php');
        exit;
    }
    start_drill_session($pdo, $person_id, $list_ids);
}

// -------------------------------------------------------
// GET: Drill direkt aus Startseite starten
// -------------------------------------------------------
if (!isset($_SESSION['drill']) && isset($_GET['list_id']) && !isset($_GET['done'])) {
    $list_ids = [intval($_GET['list_id'])];
    start_drill_session($pdo, $person_id, $list_ids);
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

    // drill_too_hard lazy reset
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

        advance_drill_queue($pdo, $state, $person_id, $today, $session_id, $card_id);

    } else {
        $state['stats']['unknown']++;
        $state['active'][$card_index]['unknown_count']++;
        $state['active'][$card_index]['correct_count'] = 0;
        $state['active'][$card_index]['rounds_participated']++;

        $threshold = DRILL_TOO_HARD_LIMIT;
        if ($state['active'][$card_index]['unknown_count'] >= $threshold) {
            mark_too_hard($pdo, $state, $person_id, $card_id, $card_index, $today);
        } else {
            $pos = array_search($card_id, $state['queue']);
            if ($pos !== false) {
                array_splice($state['queue'], $pos, 1);
            }
            $state['queue'][] = $card_id;
        }
    }

    // Session-Ende prüfen
    $elapsed = time() - $state['started_at'];
    $no_more_cards = empty($state['queue']) && empty($state['pool']);

    if ($elapsed >= DRILL_SESSION_SECONDS && empty($state['queue'])) {
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

function start_drill_session(PDO $pdo, int $person_id, array $list_ids): void {
    // Besitzer prüfen
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $stmt = $pdo->prepare("SELECT id FROM lists WHERE id IN ($placeholders) AND person_id = ?");
    $stmt->execute(array_merge($list_ids, [$person_id]));
    $valid_ids = array_column($stmt->fetchAll(), 'id');

    if (!$valid_ids) {
        $_SESSION['flash_error'] = 'Keine gültige Liste ausgewählt.';
        header('Location: home.php');
        exit;
    }

    $today = today();
    $pool  = load_drill_pool($pdo, $person_id, $valid_ids, $today);
    shuffle($pool);

    $active_cards = array_splice($pool, 0, DRILL_ACTIVE_CARDS);
    if (!$active_cards) {
        $_SESSION['flash_error'] = 'Keine geeigneten Karten für Drill in dieser Liste.';
        header('Location: home.php');
        exit;
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

    $active = [];
    foreach ($active_cards as $cid) {
        $active[] = build_card_state($cid);
    }

    $_SESSION['drill'] = [
        'session_id'       => $session_id,
        'list_ids'         => $valid_ids,
        'pool'             => $pool,
        'active'           => $active,
        'queue'            => array_column($active, 'card_id'),
        'phase'            => 'fixed',
        'too_hard'         => [],
        'mastered_session' => [],
        'stats'            => ['known' => 0, 'unknown' => 0, 'mastered' => 0],
        'started_at'       => time(),
        'today'            => $today,
    ];

    header('Location: drill.php');
    exit;
}

function build_card_state(int $card_id): array {
    return [
        'card_id'             => $card_id,
        'rounds_participated' => 0,
        'correct_count'       => 0,
        'unknown_count'       => 0,
    ];
}

function load_drill_pool(PDO $pdo, int $person_id, array $list_ids, string $today): array {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
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
          RAND()
        LIMIT 50
    ");
    $stmt->execute($params);
    $primary = array_column($stmt->fetchAll(), 'card_id');

    if (count($primary) >= DRILL_ACTIVE_CARDS) return $primary;

    // Auffüllen: queued mit drill_too_hard=1 → active mit drill_too_hard=1
    $needed = DRILL_ACTIVE_CARDS - count($primary);
    $exclude = $primary;

    foreach (['queued', 'active'] as $fallback_status) {
        if ($needed <= 0) break;
        $ex_placeholders = $exclude ? implode(',', array_fill(0, count($exclude), '?')) : 'NULL';

        if ($fallback_status === 'active') {
            $stmt = $pdo->prepare("
                SELECT cp.card_id FROM card_progress cp
                JOIN cards c ON c.id = cp.card_id
                WHERE cp.person_id = ? AND c.list_id IN ($placeholders)
                  AND cp.status = 'active' AND cp.drill_too_hard = 1
                " . ($exclude ? "AND cp.card_id NOT IN ($ex_placeholders)" : "") . "
                ORDER BY RAND()
                LIMIT {$needed}
            ");
            $fallback_params = array_merge([$person_id], $list_ids, $exclude);
        } else {
            $stmt = $pdo->prepare("
                SELECT cp.card_id FROM card_progress cp
                JOIN cards c ON c.id = cp.card_id
                WHERE cp.person_id = ? AND c.list_id IN ($placeholders)
                  AND cp.status = ?
                " . ($exclude ? "AND cp.card_id NOT IN ($ex_placeholders)" : "") . "
                ORDER BY RAND()
                LIMIT {$needed}
            ");
            $fallback_params = array_merge([$person_id], $list_ids, [$fallback_status], $exclude);
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
        $stmt = $pdo->prepare("UPDATE card_progress SET last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    }
}

function advance_drill_queue(PDO $pdo, array &$state, int $person_id, string $today, int $session_id, int $card_id): void {
    $pos = array_search($card_id, $state['queue']);
    if ($pos === false) return;

    array_splice($state['queue'], $pos, 1);

    if (!empty($state['queue'])) {
        return;
    }

    // Alle 3 Karten der Runde beantwortet — alle korrekt?
    $all_correct = true;
    foreach ($state['active'] as $ac) {
        if ($ac['correct_count'] === 0) {
            $all_correct = false;
            break;
        }
    }

    if (!$all_correct) {
        $state['queue'] = array_column($state['active'], 'card_id');
        foreach ($state['active'] as &$ac) {
            $ac['correct_count'] = 0;
        }
        return;
    }

    if ($state['phase'] === 'fixed') {
        $state['phase'] = 'mixed';
        $ids = array_column($state['active'], 'card_id');
        shuffle($ids);
        $state['queue'] = $ids;
        foreach ($state['active'] as &$ac) {
            $ac['correct_count'] = 0;
        }
        return;
    }

    // Alle korrekt gemischt → eine Karte gemeistert
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

    array_splice($state['active'], $best_index, 1);

    $new_card_id = array_shift($state['pool']);
    if ($new_card_id) {
        $state['active'][] = build_card_state($new_card_id);
    }

    $state['phase'] = 'fixed';
    $state['queue'] = array_column($state['active'], 'card_id');
    foreach ($state['active'] as &$ac) {
        $ac['correct_count'] = 0;
    }
}

function mark_too_hard(PDO $pdo, array &$state, int $person_id, int $card_id, int $card_index, string $today): void {
    $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 1 WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);

    $state['too_hard'][] = $card_id;

    array_splice($state['active'], $card_index, 1);
    $state['queue'] = array_filter($state['queue'], fn($id) => $id !== $card_id);
    $state['queue'] = array_values($state['queue']);

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
        'stats'        => $state['stats'],
        'mastered_ids' => $state['mastered_session'],
        'list_ids'     => $state['list_ids'],
    ];

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
// STATE: Alle Karten der aktuellen Runde laden
// -------------------------------------------------------
$state       = $_SESSION['drill'] ?? null;
$active_data = [];

if ($state && !empty($state['queue'])) {
    $queue_ids = array_values(array_unique($state['queue']));
    $ph = implode(',', array_fill(0, count($queue_ids), '?'));
    $stmt = $pdo->prepare("SELECT c.*, l.language_a, l.language_b FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id IN ($ph)");
    $stmt->execute($queue_ids);
    $cards_by_id = [];
    foreach ($stmt->fetchAll() as $row) {
        $cards_by_id[(int)$row['id']] = $row;
    }
    foreach ($queue_ids as $cid) {
        lazy_reset_drill_too_hard($pdo, $person_id, (int)$cid, $state['today']);
        if (isset($cards_by_id[$cid])) {
            $active_data[] = $cards_by_id[$cid];
        }
    }
}

$remaining_s = 0;
if ($state) {
    $elapsed = time() - $state['started_at'];
    $remaining_s = max(0, DRILL_SESSION_SECONDS - $elapsed);
}

// Session-Abschluss
$done_data = null;
if (isset($_GET['done']) && isset($_SESSION['drill_done'])) {
    $done_data = $_SESSION['drill_done'];
    unset($_SESSION['drill_done']);
}

// Kein Session-State und keine Abschlussseite → Startseite
if (!$state && !$done_data) {
    header('Location: home.php');
    exit;
}
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
            <span class="text-white small fw-semibold" id="drill-timer"></span>
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
    <?php
    $repeat_ids = $done_data['list_ids'] ?? [];
    if (count($repeat_ids) === 1):
    ?>
    <a href="drill.php?list_id=<?= $repeat_ids[0] ?>" class="btn btn-outline-primary">Erneut starten</a>
    <?php endif; ?>

</div>

<?php elseif ($state && $active_data): ?>
<!-- ==================== DRILL-KARTEN ==================== -->

<div class="row g-3">
    <?php foreach ($active_data as $card): ?>
    <div class="col-md-4 col-12">
        <div class="learn-card text-center mb-2"
             id="card-<?= $card['id'] ?>"
             style="cursor:pointer;"
             onclick="flipDrillCard(<?= $card['id'] ?>)">
            <div class="d-flex flex-column justify-content-center p-4" style="min-height:220px;">
                <p class="text-muted small mb-2"><?= htmlspecialchars($card['language_a']) ?></p>
                <div class="fw-bold fs-2 mb-1"><?= htmlspecialchars($card['word_a']) ?></div>
                <?php if ($card['desc_a']): ?>
                <p class="text-muted small mb-0"><?= htmlspecialchars($card['desc_a']) ?></p>
                <?php endif; ?>
                <div id="answer-<?= $card['id'] ?>" style="display:none;">
                    <hr>
                    <p class="text-muted small mb-1"><?= htmlspecialchars($card['language_b']) ?></p>
                    <div class="fw-bold fs-3 text-success mb-0"><?= htmlspecialchars($card['word_b']) ?></div>
                    <?php if ($card['desc_b']): ?>
                    <p class="text-muted small mt-1 mb-0"><?= htmlspecialchars($card['desc_b']) ?></p>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mt-3 mb-0" id="hint-<?= $card['id'] ?>">Tippen zum Aufdecken</p>
            </div>
        </div>
        <div id="btns-<?= $card['id'] ?>" class="d-flex gap-2 justify-content-center" style="display:none;">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="answer">
                <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                <input type="hidden" name="result" value="unknown">
                <button type="submit" class="btn btn-danger">Musste nachdenken</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="answer">
                <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                <input type="hidden" name="result" value="known">
                <button type="submit" class="btn btn-success">Gewusst</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Flip-Zustand pro Drill-Session in sessionStorage merken
const _drillKey = 'drill_revealed_<?= $state ? (int)$state['session_id'] : 0 ?>';

function _getRevealed() {
    try { return JSON.parse(sessionStorage.getItem(_drillKey) || '[]'); } catch(e) { return []; }
}
function _setRevealed(ids) {
    try { sessionStorage.setItem(_drillKey, JSON.stringify(ids)); } catch(e) {}
}

function flipDrillCard(id) {
    document.getElementById('answer-' + id).style.display = 'block';
    const hint = document.getElementById('hint-' + id);
    if (hint) hint.style.display = 'none';
    document.getElementById('btns-' + id).style.display = 'flex';
    const card = document.getElementById('card-' + id);
    card.style.cursor = 'default';
    card.onclick = null;
    // Flip-Zustand speichern
    const rev = _getRevealed();
    if (!rev.includes(id)) { rev.push(id); _setRevealed(rev); }
}

// Beim Absenden "Nicht gewusst": Karte aus Flip-Zustand entfernen (soll face-down neu erscheinen)
document.querySelectorAll('input[name="result"][value="unknown"]').forEach(function(input) {
    input.closest('form').addEventListener('submit', function() {
        const id = parseInt(this.querySelector('[name="card_id"]').value);
        _setRevealed(_getRevealed().filter(function(x) { return x !== id; }));
    });
});

// Beim Laden: bereits aufgedeckte Karten automatisch wiederherstellen
(function() {
    const revealed = _getRevealed();
    if (!revealed.length) return;
    const currentIds = <?= json_encode(array_map(fn($c) => (int)$c['id'], $active_data)) ?>;
    // Alle Karten bereits aufgedeckt + volle Gruppe → neues Runde, Zustand zurücksetzen
    if (currentIds.length === <?= DRILL_ACTIVE_CARDS ?> && currentIds.every(function(id) { return revealed.includes(id); })) {
        _setRevealed([]);
        return;
    }
    revealed.forEach(function(id) {
        if (document.getElementById('card-' + id)) flipDrillCard(id);
    });
})();

// Countdown-Timer
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
