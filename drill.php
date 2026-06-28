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

// Session abbrechen
if (($_GET['action'] ?? '') === 'abort') {
    unset($_SESSION['drill']);
    header('Location: home.php');
    exit;
}

// POST: Session starten (von home.php Formular)
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

// GET: Direkt aus Startseite starten
if (!isset($_SESSION['drill']) && isset($_GET['list_id']) && !isset($_GET['done'])) {
    start_drill_session($pdo, $person_id, [intval($_GET['list_id'])]);
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
    $stmt = $pdo->prepare("INSERT INTO learning_events (session_id, person_id, card_id, result, learn_date) VALUES (?,?,?,?,?)");
    $stmt->execute([$state['session_id'], $person_id, $card_id, $result, $state['today']]);

    if ($result === 'known') {
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

    // Session-Ende: Timer abgelaufen oder keine Karten mehr
    $elapsed  = time() - $state['started_at'];
    $no_cards = empty($state['pool_known']) && empty($state['pool_new']);

    if ($elapsed >= DRILL_SESSION_SECONDS || $no_cards) {
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
// Hilfsfunktionen
// -------------------------------------------------------

function start_drill_session(PDO $pdo, int $person_id, array $list_ids): void {
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
    ['known' => $pool_known, 'new' => $pool_new] = load_drill_pool($pdo, $person_id, $valid_ids, $today);

    if (!$pool_known && !$pool_new) {
        $_SESSION['flash_error'] = 'Keine geeigneten Karten für Drill in dieser Liste.';
        header('Location: home.php');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO learning_sessions (person_id, mode, started_at) VALUES (?,?,NOW())");
    $stmt->execute([$person_id, 'drill']);
    $session_id = (int) $pdo->lastInsertId();

    $ins_sl = $pdo->prepare("INSERT INTO session_lists (session_id, list_id) VALUES (?,?)");
    $upd    = $pdo->prepare("UPDATE lists SET last_used_at = NOW() WHERE id = ?");
    foreach ($valid_ids as $lid) {
        $ins_sl->execute([$session_id, $lid]);
        $upd->execute([$lid]);
    }

    $state = [
        'session_id'      => $session_id,
        'list_ids'        => $valid_ids,
        'pool_known'      => $pool_known,
        'pool_new'        => $pool_new,
        'cycle_pos'       => 0,
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
        header('Location: home.php');
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

    $stmt = $pdo->prepare("
        SELECT cp.card_id, cp.drill_mastery
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ?
          AND c.list_id IN ($placeholders)
          AND cp.status != 'archived'
          AND (cp.drill_too_hard = 0
               OR (cp.drill_too_hard = 1 AND (cp.last_drill_shown IS NULL OR cp.last_drill_shown < ?)))
        ORDER BY RAND()
    ");
    $stmt->execute($params);

    $known = [];
    $new   = [];
    foreach ($stmt->fetchAll() as $row) {
        if ((int)$row['drill_mastery'] >= 1) {
            $known[] = (int)$row['card_id'];
        } else {
            $new[] = (int)$row['card_id'];
        }
    }
    return ['known' => $known, 'new' => $new];
}

// Wählt die nächste Karte nach dem 9:1-Prinzip:
// 9 Karten aus dem Known-Pool (rotierend), dann 1 aus dem New-Pool.
// Neu eingeführte Karten wandern in den Known-Pool und rotieren mit.
function next_drill_card(array &$state): ?int {
    $ratio = DRILL_KNOWN_RATIO;

    $has_known = !empty($state['pool_known']);
    $has_new   = !empty($state['pool_new']);

    if (!$has_known && !$has_new) return null;

    $pick_new = ($state['cycle_pos'] >= $ratio) || !$has_known;

    if ($pick_new && $has_new) {
        $state['cycle_pos'] = 0;
        $id = array_shift($state['pool_new']);
        $state['pool_known'][] = $id;
        return $id;
    }

    if ($has_known) {
        $state['cycle_pos']++;
        $id = array_shift($state['pool_known']);
        $state['pool_known'][] = $id;
        return $id;
    }

    return null;
}

function master_card(PDO $pdo, array &$state, int $person_id, int $card_id, string $today): void {
    $state['mastered_cards'][] = $card_id;
    $state['stats']['mastered']++;
    remove_from_pools($state, $card_id);

    $stmt = $pdo->prepare("SELECT drill_mastery FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    $new_mastery = (int)($cp['drill_mastery'] ?? 0) + 1;

    $leitner_transitions = [1 => 2, 2 => 3, 3 => 4];
    $target_box = $leitner_transitions[$new_mastery] ?? null;
    $intervals  = LEITNER_INTERVALS;

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
    $stmt = $pdo->prepare("UPDATE learning_sessions SET completed_at = NOW() WHERE id = ?");
    $stmt->execute([$state['session_id']]);

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

// Veralteten Session-State (anderes Format) verwerfen
if (isset($_SESSION['drill']) && !array_key_exists('current_card_id', $_SESSION['drill'])) {
    unset($_SESSION['drill']);
}

// -------------------------------------------------------
// Anzeige-State
// -------------------------------------------------------
$state     = $_SESSION['drill'] ?? null;
$card_data = null;

if ($state && $state['current_card_id']) {
    $stmt = $pdo->prepare("SELECT c.*, l.language_a, l.language_b FROM cards c JOIN lists l ON l.id = c.list_id WHERE c.id = ?");
    $stmt->execute([$state['current_card_id']]);
    $card_data = $stmt->fetch() ?: null;
}

$remaining_s = 0;
if ($state) {
    $remaining_s = max(0, DRILL_SESSION_SECONDS - (time() - $state['started_at']));
}

$done_data = null;
if (isset($_GET['done']) && isset($_SESSION['drill_done'])) {
    $done_data = $_SESSION['drill_done'];
    unset($_SESSION['drill_done']);
}

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
    <style>
    #flip-card { transition: transform 0.15s ease; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php if ($state): ?>
            <span class="text-white small fw-semibold" id="drill-timer"></span>
            <span class="text-white small opacity-75">·</span>
            <span class="text-white small"><?= (int)($state['stats']['mastered'] ?? 0) ?> gemeistert</span>
            <a href="drill.php?action=abort" class="btn btn-sm btn-outline-light">Session abbrechen</a>
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

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Drill', '']]) ?></div>

<div class="container mt-2" style="max-width:700px;">

<?php if ($done_data !== null): ?>
<!-- ==================== ABSCHLUSS ==================== -->
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

<div class="learn-card mx-auto mb-4" id="flip-card" style="max-width:540px; cursor:pointer;" onclick="flipCard()">
    <div class="text-center p-5">
        <p class="text-muted small mb-2"><?= htmlspecialchars($card_data['language_a']) ?></p>
        <div class="fw-bold fs-1 mb-1"><?= htmlspecialchars($card_data['word_a']) ?></div>
        <?php if ($card_data['desc_a']): ?>
        <p class="text-muted mb-0"><?= htmlspecialchars($card_data['desc_a']) ?></p>
        <?php endif; ?>

        <div id="card-back" style="display:none;">
            <hr class="my-3">
            <p class="text-muted small mb-1"><?= htmlspecialchars($card_data['language_b']) ?></p>
            <div class="fw-bold fs-2 text-success mb-0"><?= htmlspecialchars($card_data['word_b']) ?></div>
            <?php if ($card_data['desc_b']): ?>
            <p class="text-muted mt-1 mb-0"><?= htmlspecialchars($card_data['desc_b']) ?></p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
        if (target) window.location.href = target;
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
