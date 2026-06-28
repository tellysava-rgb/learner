<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$person_id   = $_SESSION['person_id']   ?? null;
$person_name = $_SESSION['person_name'] ?? null;
$error       = $_SESSION['flash_error'] ?? '';
$success     = '';
unset($_SESSION['flash_error']);

// --- POST-Aktionen ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // Person neu erstellen
    if ($action === 'create_person') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Name darf nicht leer sein.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO persons (name) VALUES (?)");
                $stmt->execute([$name]);
                $new_id = (int) $pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['person_id']   = $new_id;
                $_SESSION['person_name'] = $name;
                header('Location: home.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Dieser Name ist bereits vergeben. Bitte wähle einen anderen.';
                } else {
                    $error = 'Fehler beim Erstellen der Person.';
                }
            }
        }
    }

    // Person auswählen
    if ($action === 'select_person') {
        $id = intval($_POST['person_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, name FROM persons WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if ($p) {
            session_regenerate_id(true);
            $_SESSION['person_id']   = $p['id'];
            $_SESSION['person_name'] = $p['name'];
            header('Location: home.php');
            exit;
        }
        $error = 'Person nicht gefunden.';
    }

    // Person wechseln (abmelden als Person)
    if ($action === 'switch_person') {
        unset($_SESSION['person_id'], $_SESSION['person_name']);
        header('Location: home.php');
        exit;
    }

    // App-Logout
    if ($action === 'logout') {
        logout();
    }

    // Täglich 10 Karten aktivieren (Button)
    if ($action === 'activate_cards' && $person_id) {
        $list_ids = array_map('intval', (array)($_POST['list_ids'] ?? []));
        if ($list_ids) {
            activate_queued_cards($pdo, $person_id, $list_ids, DAILY_CARD_LIMIT);
            $success = '10 weitere Karten wurden aktiviert.';
        }
    }
}

// Personenliste laden (immer, für Auswahlmaske)
$persons = $pdo->query("SELECT id, name FROM persons ORDER BY name")->fetchAll();

// Wenn Person eingeloggt: eigene Listen laden
$own_lists       = [];
$queued_counts   = [];

if ($person_id) {
    $stmt = $pdo->prepare("
        SELECT l.id, l.name, l.description, l.language_a, l.language_b, l.is_public, l.last_used_at,
               COUNT(c.id) AS card_count
        FROM lists l
        LEFT JOIN cards c ON c.list_id = l.id
        WHERE l.person_id = ?
        GROUP BY l.id
        ORDER BY l.last_used_at DESC, l.name
    ");
    $stmt->execute([$person_id]);
    $own_lists = $stmt->fetchAll();

    // Warteschlangen-Anzahl pro Liste
    foreach ($own_lists as $list) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM card_progress cp
            JOIN cards c ON c.id = cp.card_id
            WHERE cp.person_id = ? AND c.list_id = ? AND cp.status = 'queued'
        ");
        $stmt->execute([$person_id, $list['id']]);
        $queued_counts[$list['id']] = (int) $stmt->fetchColumn();
    }

    // Letzte verwendete Listen für Vorschlag
    $last_used_ids = array_column(
        array_filter($own_lists, fn($l) => $l['last_used_at'] !== null),
        'id'
    );
}

// Öffentliche Listen anderer Personen (Discover-Vorschau auf Startseite)
$public_lists = [];
if ($person_id) {
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
}

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
        ORDER BY cp.id
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

$streak = ($person_id) ? get_streak($pdo, $person_id) : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Startseite</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <?php if ($person_id): ?>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <?php if ($streak > 0): ?>
            <span class="badge bg-warning text-dark">🔥 <?= $streak ?> Tag<?= $streak > 1 ? 'e' : '' ?></span>
            <?php endif; ?>
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="switch_person">
                <button type="submit" class="btn btn-sm btn-outline-light">Person wechseln</button>
            </form>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
            </form>
        </div>
        <?php else: ?>
        <form method="post" class="ms-auto">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
        </form>
        <?php endif; ?>
    </div>
</nav>

<div class="container mt-4">

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!$person_id): ?>
<!-- ==================== PERSONENWAHL ==================== -->
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h2 class="h4 mb-4">Wer bist du?</h2>

        <?php if ($persons): ?>
        <div class="list-group mb-4">
            <?php foreach ($persons as $p): ?>
            <form method="post" class="d-block">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="select_person">
                <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                <button type="submit" class="list-group-item list-group-item-action">
                    <?= htmlspecialchars($p['name']) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title h6">Neue Person erstellen</h5>
                <form method="post" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_person">
                    <input type="text" name="name" class="form-control" placeholder="Name" required maxlength="100" autofocus>
                    <button type="submit" class="btn btn-primary">Erstellen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ==================== STARTSEITE ==================== -->

<div class="row g-4">
    <!-- Eigene Listen -->
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Meine Listen</h2>
            <div class="d-flex gap-2">
                <a href="lists.php" class="btn btn-sm btn-outline-primary">Verwalten</a>
                <a href="stats.php" class="btn btn-sm btn-outline-secondary">Statistik</a>
                <a href="math.php" class="btn btn-sm btn-outline-secondary">Mathe-Generator</a>
            </div>
        </div>

        <?php if (!$own_lists): ?>
            <p class="text-muted">Du hast noch keine Listen. <a href="lists.php">Erstelle jetzt deine erste Liste</a>.</p>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
            <?php foreach ($own_lists as $list): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title h6">
                            <?= htmlspecialchars($list['name']) ?>
                            <?php if (!$list['is_public']): ?>
                            <span class="badge bg-secondary ms-1 small">privat</span>
                            <?php endif; ?>
                        </h5>
                        <?php if ($list['description']): ?>
                        <p class="card-text text-muted small"><?= htmlspecialchars($list['description']) ?></p>
                        <?php endif; ?>
                        <p class="small mb-1">
                            <span class="text-muted"><?= htmlspecialchars($list['language_a']) ?> → <?= htmlspecialchars($list['language_b']) ?></span>
                            &nbsp;·&nbsp; <?= $list['card_count'] ?> Karte<?= $list['card_count'] != 1 ? 'n' : '' ?>
                        </p>
                        <?php if ($queued_counts[$list['id']] > 0): ?>
                        <p class="small text-info mb-2">⏳ <?= $queued_counts[$list['id']] ?> in Warteschlange</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="learn.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-primary">Leitner</a>
                            <a href="drill.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-primary">Drill</a>
                            <a href="edit.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary">Bearbeiten</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
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

<?php endif; ?>
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
