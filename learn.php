<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];

// -------------------------------------------------------
// Session-State für laufende Lernsession
// Wird in $_SESSION['learn'] gespeichert
// -------------------------------------------------------

// Logout
if (($_POST['action'] ?? '') === 'logout') {
    csrf_validate();
    logout();
}

// -------------------------------------------------------
// PHASE: Setup — Session abbrechen → zurück zur Startseite
// -------------------------------------------------------
if (($_GET['action'] ?? '') === 'setup') {
    unset($_SESSION['learn']);
    header('Location: home.php');
    exit;
}

// Verfügbare eigene Listen laden
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.language_a, l.language_b
    FROM lists l
    WHERE l.person_id = ?
    ORDER BY l.last_used_at DESC, l.name
");
$stmt->execute([$person_id]);
$all_lists = $stmt->fetchAll();

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

// -------------------------------------------------------
// POST: Session konfigurieren und starten
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin') {
    csrf_validate();

    $list_ids  = array_map('intval', array_filter((array)($_POST['list_ids'] ?? [])));
    $direction = $_POST['direction'] ?? 'a_to_b';
    $card_limit = max(1, intval($_POST['card_limit'] ?? 20));

    if (!$list_ids) {
        $setup_error = 'Bitte mindestens eine Liste auswählen.';
        goto render_setup;
    }
    if (!in_array($direction, ['a_to_b', 'b_to_a', 'mixed'])) {
        $direction = 'a_to_b';
    }

    // Eigentümerschaft prüfen
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $stmt = $pdo->prepare("SELECT id FROM lists WHERE id IN ($placeholders) AND person_id = ?");
    $stmt->execute(array_merge($list_ids, [$person_id]));
    $valid_ids = array_column($stmt->fetchAll(), 'id');
    if (!$valid_ids) {
        $setup_error = 'Keine gültige Liste ausgewählt.';
        goto render_setup;
    }

    $today = today();

    // Täglich 10 Karten aktivieren (queued → active)
    activate_daily_cards($pdo, $person_id, $valid_ids, $today);

    // Karten für diese Session laden (mit Priorisierung)
    $queue = build_leitner_queue($pdo, $person_id, $valid_ids, $today, $card_limit);

    if (!$queue) {
        $_SESSION['learn_done'] = ['message' => 'Keine fälligen Karten heute. Gut gemacht!', 'stats' => []];
        header('Location: learn.php?done=1&list_id=' . $valid_ids[0]);
        exit;
    }

    // Learning-Session in DB anlegen
    $stmt = $pdo->prepare("INSERT INTO learning_sessions (person_id, mode, direction, started_at) VALUES (?,?,?,NOW())");
    $stmt->execute([$person_id, 'leitner', $direction]);
    $session_id = (int) $pdo->lastInsertId();

    // session_lists befüllen
    $ins_sl = $pdo->prepare("INSERT INTO session_lists (session_id, list_id) VALUES (?,?)");
    foreach ($valid_ids as $lid) {
        $ins_sl->execute([$session_id, $lid]);
    }

    // last_used_at für alle beteiligten Listen aktualisieren
    $upd = $pdo->prepare("UPDATE lists SET last_used_at = NOW() WHERE id = ?");
    foreach ($valid_ids as $lid) {
        $upd->execute([$lid]);
    }

    // Session-State initialisieren
    $_SESSION['learn'] = [
        'session_id'    => $session_id,
        'list_ids'      => $valid_ids,
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
// POST: Karte beantworten (während Session)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'answer' && isset($_SESSION['learn'])) {
    csrf_validate();

    $state     = &$_SESSION['learn'];
    $card_id   = intval($_POST['card_id'] ?? 0);
    $result    = $_POST['result'] ?? ''; // 'correct' | 'incorrect' | 'skip'
    $today     = $state['today'];
    $session_id = $state['session_id'];

    if (!in_array($result, ['correct', 'incorrect', 'skip'])) {
        header('Location: learn.php');
        exit;
    }

    $intervals = LEITNER_INTERVALS;

    if ($result === 'skip') {
        // Übersprungen → ans Ende der Queue, next_due_date unverändert
        $state['queue'][] = $card_id;
        $stmt = $pdo->prepare("INSERT INTO learning_events (session_id, person_id, card_id, result, learn_date) VALUES (?,?,?,?,?)");
        $stmt->execute([$session_id, $person_id, $card_id, 'skipped', $today]);
        array_shift($state['queue']);
        header('Location: learn.php');
        exit;
    }

    // Bestimmen ob erster oder zweiter Versuch
    $is_retry = isset($state['answered'][$card_id]);
    $state['answered'][$card_id] = ($state['answered'][$card_id] ?? 0) + 1;

    // Aktuellen Leitner-Stand laden
    $stmt = $pdo->prepare("SELECT leitner_box, next_due_date FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    $current_box = (int) ($cp['leitner_box'] ?? 1);

    if ($result === 'correct') {
        $state['stats']['correct']++;

        if ($is_retry) {
            // Zweiter Versuch richtig → bleibt in Fach 1, due = morgen
            $due = date('Y-m-d', strtotime($today . ' +1 day'));
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
        }
        // Zweiter Fehler → kein weiterer Versuch, Karte bleibt in Fach 1

        $db_result = 'incorrect';
    }

    // Event loggen
    $stmt = $pdo->prepare("INSERT INTO learning_events (session_id, person_id, card_id, result, learn_date) VALUES (?,?,?,?,?)");
    $stmt->execute([$session_id, $person_id, $card_id, $db_result, $today]);

    // Nächste Karte
    array_shift($state['queue']);

    // Session beendet?
    if (!$state['queue']) {
        // Session abschliessen
        $stmt = $pdo->prepare("UPDATE learning_sessions SET completed_at = NOW() WHERE id = ?");
        $stmt->execute([$session_id]);

        $summary = $state['stats'];
        $list_ids = $state['list_ids'];
        unset($_SESSION['learn']);

        // Streak berechnen
        $streak = get_learn_streak($pdo, $person_id);

        $_SESSION['learn_done'] = [
            'stats'   => $summary,
            'streak'  => $streak,
            'list_ids' => $list_ids,
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

function activate_daily_cards(PDO $pdo, int $person_id, array $list_ids, string $today): void {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $params = array_merge([$person_id], $list_ids, [DAILY_CARD_LIMIT]);

    // Wie viele wurden heute schon aktiviert?
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id IN ($placeholders)
          AND cp.status = 'active' AND cp.next_due_date = ?
          AND cp.leitner_box = 1
    ");
    $check_params = array_merge([$person_id], $list_ids, [$today]);
    $stmt->execute($check_params);
    $already_activated = (int) $stmt->fetchColumn();
    $to_activate = DAILY_CARD_LIMIT - $already_activated;
    if ($to_activate <= 0) return;

    $params = array_merge([$person_id], $list_ids);
    $stmt = $pdo->prepare("
        SELECT cp.card_id FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id IN ($placeholders) AND cp.status = 'queued'
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

function build_leitner_queue(PDO $pdo, int $person_id, array $list_ids, string $today, int $limit): array {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $params = array_merge([$person_id], $list_ids, [$today, $today]);

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
          AND c.list_id IN ($placeholders)
          AND cp.status = 'active'
          AND cp.next_due_date <= ?
        ORDER BY priority, RAND()
        LIMIT {$limit}
    ");
    $stmt->execute(array_merge([$today, $today, $person_id], $list_ids, [$today]));
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
            SELECT c.*, cp.leitner_box, cp.next_due_date,
                   l.language_a, l.language_b
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
function get_question_answer(array $card, string $direction): array {
    if ($direction === 'b_to_a') {
        return ['q' => $card['word_b'], 'a' => $card['word_a'], 'q_desc' => $card['desc_b'], 'a_desc' => $card['desc_a'], 'q_lang' => $card['language_b'], 'a_lang' => $card['language_a']];
    }
    if ($direction === 'mixed') {
        // Deterministisch gemischt anhand der card_id
        if ($card['id'] % 2 === 0) {
            return ['q' => $card['word_b'], 'a' => $card['word_a'], 'q_desc' => $card['desc_b'], 'a_desc' => $card['desc_a'], 'q_lang' => $card['language_b'], 'a_lang' => $card['language_a']];
        }
    }
    return ['q' => $card['word_a'], 'a' => $card['word_b'], 'q_desc' => $card['desc_a'], 'a_desc' => $card['desc_b'], 'q_lang' => $card['language_a'], 'a_lang' => $card['language_b']];
}

$setup_error = '';

render_setup:
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leitner — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <?php if ($state): ?>
            <a href="learn.php?action=setup" class="btn btn-sm btn-outline-light">Session abbrechen</a>
            <?php else: ?>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Leitner', '']]) ?></div>

<div class="container mt-2" style="max-width:700px;">

<?php if ($done_data !== null): ?>
<!-- ==================== SESSION-ZUSAMMENFASSUNG ==================== -->
<div class="text-center">
    <div class="display-6 mb-2">
        <?php
        $pct = $done_data['stats']['correct'] + $done_data['stats']['incorrect'];
        $pct = $pct > 0 ? round($done_data['stats']['correct'] / $pct * 100) : 0;
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

    <div class="row g-3 mb-4 justify-content-center">
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-success"><?= $done_data['stats']['correct'] ?></div>
                <div class="small text-muted">Gewusst</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-danger"><?= $done_data['stats']['incorrect'] ?></div>
                <div class="small text-muted">Nicht gewusst</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-primary"><?= $done_data['stats']['promoted'] ?></div>
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

    <?php
    $repeat_ids = $done_data['list_ids'] ?? [];
    $repeat_url = count($repeat_ids) === 1 ? 'learn.php?list_id=' . $repeat_ids[0] : 'learn.php';
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
<div class="learn-card mx-auto mb-4"
     id="learn-card" style="max-width:540px; cursor:pointer;" onclick="flipCard()">
    <div class="text-center p-5" style="min-height:280px;">
        <p class="text-muted small mb-2"><?= htmlspecialchars($qa['q_lang']) ?></p>
        <div class="fw-bold fs-1 mb-1"><?= htmlspecialchars($qa['q']) ?></div>
        <?php if ($qa['q_desc']): ?>
        <p class="text-muted mb-0"><?= htmlspecialchars($qa['q_desc']) ?></p>
        <?php endif; ?>
        <div id="learn-answer" style="display:none;">
            <hr class="my-3">
            <p class="text-muted small mb-1"><?= htmlspecialchars($qa['a_lang']) ?></p>
            <div class="fw-bold fs-2 text-success mb-0"><?= htmlspecialchars($qa['a']) ?></div>
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

<?php else: ?>
<!-- ==================== SETUP ==================== -->
<h1 class="h4 mb-4">Leitner-Session starten</h1>

<?php if ($setup_error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($setup_error) ?></div>
<?php endif; ?>

<?php if (!$all_lists): ?>
<p class="text-muted">Du hast noch keine Listen. <a href="lists.php">Erstelle zuerst eine Liste</a>.</p>
<?php else: ?>
<?php
$lang_a = $preset_list ? htmlspecialchars($preset_list['language_a']) : 'A';
$lang_b = $preset_list ? htmlspecialchars($preset_list['language_b']) : 'B';
?>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="begin">

    <?php if ($preset_list): ?>
    <!-- Vorausgewählte Liste (von Startseite) -->
    <input type="hidden" name="list_ids[]" value="<?= $preset_list['id'] ?>">
    <div class="mb-4">
        <div class="fw-semibold mb-1">Liste</div>
        <div class="text-muted"><?= htmlspecialchars($preset_list['name']) ?> <span class="small">(<?= $lang_a ?> / <?= $lang_b ?>)</span></div>
    </div>
    <?php else: ?>
    <!-- Alle Listen zur Auswahl -->
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
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="direction" id="dir_ab" value="a_to_b" checked>
                <label class="form-check-label" for="dir_ab" id="label_ab"><?= $lang_a ?> → <?= $lang_b ?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="direction" id="dir_ba" value="b_to_a">
                <label class="form-check-label" for="dir_ba" id="label_ba"><?= $lang_b ?> → <?= $lang_a ?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="direction" id="dir_mix" value="mixed">
                <label class="form-check-label" for="dir_mix">Gemischt</label>
            </div>
        </div>
    </div>

    <!-- Kartenanzahl -->
    <div class="mb-4">
        <label class="form-label fw-semibold">Kartenanzahl</label>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="adjustCards(-5)">−5</button>
            <input type="number" name="card_limit" id="card_limit" class="form-control text-center" value="20" min="1" max="200" style="width:80px;">
            <button type="button" class="btn btn-outline-secondary" onclick="adjustCards(5)">+5</button>
        </div>
        <div class="form-text">App zeigt alle fälligen Karten. Du kannst die Zahl anpassen.</div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Session starten</button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
        if (target) window.location.href = target;
    });
})();
<?php endif; ?>

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
}

<?php if (!$preset_list && $all_lists): ?>
// Richtungs-Labels bei Mehrfach-Listenauswahl dynamisch aktualisieren
const langMap = <?= json_encode(array_combine(
    array_column($all_lists, 'id'),
    array_map(fn($l) => ['a' => $l['language_a'], 'b' => $l['language_b']], $all_lists)
)) ?>;

function updateDirLabels() {
    const first = document.querySelector('input[name="list_ids[]"]:checked');
    const langs = first && langMap[first.value] ? langMap[first.value] : {a: 'A', b: 'B'};
    document.getElementById('label_ab').textContent = langs.a + ' → ' + langs.b;
    document.getElementById('label_ba').textContent = langs.b + ' → ' + langs.a;
}

document.querySelectorAll('input[name="list_ids[]"]').forEach(cb => {
    cb.addEventListener('change', updateDirLabels);
});
updateDirLabels();
<?php endif; ?>
</script>
</body>
</html>
