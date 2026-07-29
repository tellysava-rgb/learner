<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$error       = $_SESSION['flash_error']   ?? '';
$success     = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// --- POST-Aktionen ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);
    $action = $_POST['action'] ?? '';

    // Täglich 10 Karten aktivieren (Button)
    if ($action === 'activate_cards' && $person_id) {
        $list_ids = array_map('intval', (array)($_POST['list_ids'] ?? []));
        if ($list_ids) {
            activate_queued_cards($pdo, $person_id, $list_ids, DAILY_CARD_LIMIT);
            $success = '10 weitere Karten wurden aktiviert.';
        }
    }

    // Liste als aktiv/inaktiv markieren
    if ($action === 'toggle_list_active') {
        $list_id = intval($_POST['list_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT is_active FROM lists WHERE id = ? AND person_id = ?");
        $stmt->execute([$list_id, $person_id]);
        $current = $stmt->fetch();
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE lists SET is_active = ? WHERE id = ?")->execute([$new_status, $list_id]);
            $_SESSION['flash_success'] = $new_status ? 'Liste wurde aktiviert.' : 'Liste wurde als inaktiv markiert.';
        }
        header('Location: home.php');
        exit;
    }
}

// Eigene Listen laden
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.description, l.language_a, l.language_b, l.is_public, l.is_active, l.last_used_at,
           COUNT(c.id) AS card_count
    FROM lists l
    LEFT JOIN cards c ON c.list_id = l.id
    WHERE l.person_id = ?
    GROUP BY l.id
    ORDER BY l.last_used_at DESC, l.name
");
$stmt->execute([$person_id]);
$own_lists = $stmt->fetchAll();

$active_lists   = array_values(array_filter($own_lists, fn($l) => $l['is_active']));
$inactive_lists = array_values(array_filter($own_lists, fn($l) => !$l['is_active']));

// Warteschlangen-Anzahl und heute fällige Karten (Leitner) pro aktiver Liste
$queued_counts    = [];
$due_today_counts = [];
foreach ($active_lists as $list) {
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN cp.status = 'queued' THEN 1 ELSE 0 END) AS queued,
            SUM(CASE WHEN cp.status = 'active' AND cp.next_due_date <= ? THEN 1 ELSE 0 END) AS due_today
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id = ?
    ");
    $stmt->execute([today(), $person_id, $list['id']]);
    $row = $stmt->fetch();
    $queued_counts[$list['id']]    = (int) ($row['queued'] ?? 0);
    $due_today_counts[$list['id']] = (int) ($row['due_today'] ?? 0);
}

// Öffentliche Listen anderer Personen (Discover-Vorschau auf Startseite)
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.description, l.language_a, l.language_b,
           p.name AS owner_name, COUNT(c.id) AS card_count
    FROM lists l
    JOIN persons p ON p.id = l.person_id
    LEFT JOIN cards c ON c.list_id = l.id
    WHERE l.is_public = 1 AND l.person_id != ?
    GROUP BY l.id
    ORDER BY l.name
    LIMIT 6
");
$stmt->execute([$person_id]);
$public_lists = $stmt->fetchAll();

function activate_queued_cards(PDO $pdo, int $person_id, array $list_ids, int $limit): void {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $params = array_merge([$person_id], $list_ids);

    // Karten aus den Listen holen die queued sind und dieser Person gehören
    $stmt = $pdo->prepare("
        SELECT cp.card_id
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ?
          AND c.list_id IN ($placeholders)
          AND cp.status = 'queued'
        ORDER BY RAND()
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$card_ids) return;

    $today = (new DateTimeImmutable('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d');
    $upd = $pdo->prepare("
        UPDATE card_progress
        SET status = 'active', leitner_box = 1, next_due_date = ?
        WHERE person_id = ? AND card_id = ?
    ");
    foreach ($card_ids as $cid) {
        $upd->execute([$today, $person_id, $cid]);
    }
}

// Lernstreak berechnen (learn_date ist in Europe/Zurich, von PHP gesetzt)
function get_streak(PDO $pdo, int $person_id): int {
    $stmt = $pdo->prepare("
        SELECT DISTINCT learn_date
        FROM learning_events
        WHERE person_id = ? AND result != 'skipped'
        ORDER BY learn_date DESC
    ");
    $stmt->execute([$person_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$dates) return 0;

    $tz        = new DateTimeZone(TIMEZONE);
    $today     = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');

    // Streak nur aktiv wenn heute oder gestern gelernt wurde
    if ($dates[0] !== $today && $dates[0] !== $yesterday) {
        return 0;
    }

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

$streak = get_streak($pdo, $person_id);
$_SESSION['streak']      = $streak;
$_SESSION['streak_date'] = today();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Startseite</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', '']]) ?></div>

<div class="container mt-2">

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Eigene Listen -->
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Meine Listen</h2>
            <div class="d-flex gap-2">
                <a href="lists.php" class="btn btn-sm btn-outline-primary">Meine Listen</a>
                <a href="stats.php" class="btn btn-sm btn-outline-secondary">Statistik</a>
            </div>
        </div>

        <?php if (!$own_lists): ?>
            <p class="text-muted">Du hast noch keine Listen. <a href="lists.php">Erstelle jetzt deine erste Liste</a>.</p>
        <?php else: ?>

        <?php if (!$active_lists): ?>
            <p class="text-muted">Keine aktiven Listen.</p>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
            <?php foreach ($active_lists as $list): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title h6 mb-2">
                                <?= htmlspecialchars($list['name']) ?>
                                <?php if (!$list['is_public']): ?>
                                <span class="badge bg-secondary ms-1 small">privat</span>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-1 flex-shrink-0 ms-2">
                                <a href="lists.php?edit=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="tooltip" title="Liste bearbeiten"><i class="bi bi-pencil"></i></a>
                                <a href="edit.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="tooltip" title="Karten bearbeiten"><i class="bi bi-pencil-square"></i></a>
                                <a href="stats.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="tooltip" title="Statistik"><i class="bi bi-bar-chart-line"></i></a>
                            </div>
                        </div>
                        <?php if ($list['description']): ?>
                        <p class="card-text text-muted small"><?= htmlspecialchars($list['description']) ?></p>
                        <?php endif; ?>
                        <p class="small mb-1">
                            <span class="text-muted"><?= htmlspecialchars($list['language_a']) ?> → <?= htmlspecialchars($list['language_b']) ?></span>
                            &nbsp;·&nbsp; <?= $list['card_count'] ?> Karte<?= $list['card_count'] != 1 ? 'n' : '' ?>
                        </p>
                        <?php if ($queued_counts[$list['id']] > 0): ?>
                        <p class="small text-info mb-1">⏳ <?= $queued_counts[$list['id']] ?> in Warteschlange</p>
                        <?php else: ?>
                        <p class="small text-muted mb-1">⏳ Keine in Warteschlange</p>
                        <?php endif; ?>
                        <?php if ($due_today_counts[$list['id']] > 0): ?>
                        <p class="small text-primary mb-2">📚 <?= $due_today_counts[$list['id']] ?> heute fällig</p>
                        <?php else: ?>
                        <p class="small text-success mb-2">✅ Keine heute fällig</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="learn.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-primary">Leitner</a>
                                <a href="drill.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-primary">Drill</a>
                            </div>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_list_active">
                                <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        title="Inaktiv setzen" aria-label="Inaktiv setzen"><i class="bi bi-check-circle-fill"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($inactive_lists): ?>
        <h3 class="h6 text-muted mb-2">Inaktive Listen</h3>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-2">
            <?php foreach ($inactive_lists as $list): ?>
            <div class="col">
                <div class="card h-100 border-secondary-subtle">
                    <div class="card-body p-2">
                        <p class="small fw-medium mb-1">
                            <?= htmlspecialchars($list['name']) ?>
                            <?php if (!$list['is_public']): ?>
                            <span class="badge bg-secondary ms-1">privat</span>
                            <?php endif; ?>
                        </p>
                        <p class="small text-muted mb-0">
                            <?= htmlspecialchars($list['language_a']) ?> → <?= htmlspecialchars($list['language_b']) ?>
                            &nbsp;·&nbsp; <?= $list['card_count'] ?> Karte<?= $list['card_count'] != 1 ? 'n' : '' ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-0 p-2 pt-0 d-flex justify-content-end">
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_list_active">
                            <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                    title="Aktiv setzen" aria-label="Aktiv setzen"><i class="bi bi-circle text-secondary"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Öffentliche Listen entdecken -->
    <?php if ($public_lists): ?>
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Entdecken</h2>
            <a href="discover.php" class="btn btn-sm btn-outline-secondary">Alle anzeigen</a>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
            <?php foreach ($public_lists as $list): ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 bg-light">
                    <div class="card-body">
                        <h5 class="card-title h6"><?= htmlspecialchars($list['name']) ?></h5>
                        <?php if ($list['description']): ?>
                        <p class="card-text text-muted small"><?= htmlspecialchars($list['description']) ?></p>
                        <?php endif; ?>
                        <p class="small text-muted mb-0">
                            <?= htmlspecialchars($list['language_a']) ?> → <?= htmlspecialchars($list['language_b']) ?>
                            &nbsp;·&nbsp; <?= $list['card_count'] ?> Karten
                            &nbsp;·&nbsp; von <?= htmlspecialchars($list['owner_name']) ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <a href="discover.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-primary">Vorschau & Kopieren</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
});
</script>
</body>
</html>
