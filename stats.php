<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];

if (($_POST['action'] ?? '') === 'logout') {
    csrf_validate();
    logout();
}

// Eigene Listen
$stmt = $pdo->prepare("SELECT id, name, language_a, language_b FROM lists WHERE person_id = ? ORDER BY name");
$stmt->execute([$person_id]);
$own_lists = $stmt->fetchAll();

// Filter: Liste auswählen — ohne Auswahl zur ersten Liste springen
$filter_list_id = intval($_GET['list_id'] ?? 0);
if (!$filter_list_id && $own_lists) {
    header('Location: stats.php?list_id=' . $own_lists[0]['id']);
    exit;
}

// -------------------------------------------------------
// Leitner-Statistik
// -------------------------------------------------------

// Karten pro Fach (gesamt oder pro Liste)
if ($filter_list_id) {
    $stmt = $pdo->prepare("
        SELECT cp.leitner_box, cp.status, COUNT(*) AS cnt
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id = ?
        GROUP BY cp.status, cp.leitner_box
        ORDER BY cp.status, cp.leitner_box
    ");
    $stmt->execute([$person_id, $filter_list_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT cp.leitner_box, cp.status, COUNT(*) AS cnt
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        JOIN lists l ON l.id = c.list_id
        WHERE cp.person_id = ? AND l.person_id = ?
        GROUP BY cp.status, cp.leitner_box
        ORDER BY cp.status, cp.leitner_box
    ");
    $stmt->execute([$person_id, $person_id]);
}

$box_counts    = array_fill(1, 5, 0);
$queued_count  = 0;
$archived_count = 0;

foreach ($stmt->fetchAll() as $row) {
    if ($row['status'] === 'queued') {
        $queued_count += $row['cnt'];
    } elseif ($row['status'] === 'archived') {
        $archived_count += $row['cnt'];
    } elseif ($row['status'] === 'active' && $row['leitner_box'] >= 1 && $row['leitner_box'] <= 5) {
        $box_counts[$row['leitner_box']] += $row['cnt'];
    }
}

$total_active = array_sum($box_counts);

// Richtig/Falsch Statistik (Leitner)
if ($filter_list_id) {
    $stmt = $pdo->prepare("
        SELECT result, COUNT(*) AS cnt
        FROM learning_events le
        JOIN cards c ON c.id = le.card_id
        WHERE le.person_id = ? AND c.list_id = ? AND result IN ('correct','incorrect')
        GROUP BY result
    ");
    $stmt->execute([$person_id, $filter_list_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT result, COUNT(*) AS cnt
        FROM learning_events le
        WHERE le.person_id = ? AND result IN ('correct','incorrect')
        GROUP BY result
    ");
    $stmt->execute([$person_id]);
}

$leitner_stats = ['correct' => 0, 'incorrect' => 0];
foreach ($stmt->fetchAll() as $row) {
    $leitner_stats[$row['result']] = $row['cnt'];
}
$leitner_total = $leitner_stats['correct'] + $leitner_stats['incorrect'];
$leitner_pct   = $leitner_total > 0 ? round($leitner_stats['correct'] / $leitner_total * 100) : 0;

// -------------------------------------------------------
// Lernstreak
// -------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT DISTINCT learn_date FROM learning_events
    WHERE person_id = ? AND result != 'skipped'
    ORDER BY learn_date DESC
");
$stmt->execute([$person_id]);
$dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$streak = 0;
if ($dates) {
    $tz        = new DateTimeZone(TIMEZONE);
    $today_str = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');

    if ($dates[0] === $today_str || $dates[0] === $yesterday) {
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
    }
}

// -------------------------------------------------------
// Drill-Statistik
// -------------------------------------------------------
if ($filter_list_id) {
    $stmt = $pdo->prepare("
        SELECT cp.drill_mastery, COUNT(*) AS cnt
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ? AND c.list_id = ? AND cp.drill_mastery > 0
        GROUP BY cp.drill_mastery
        ORDER BY cp.drill_mastery
    ");
    $stmt->execute([$person_id, $filter_list_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT cp.drill_mastery, COUNT(*) AS cnt
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        JOIN lists l ON l.id = c.list_id
        WHERE cp.person_id = ? AND l.person_id = ? AND cp.drill_mastery > 0
        GROUP BY cp.drill_mastery
        ORDER BY cp.drill_mastery
    ");
    $stmt->execute([$person_id, $person_id]);
}

$drill_mastery = [];
foreach ($stmt->fetchAll() as $row) {
    $drill_mastery[$row['drill_mastery']] = $row['cnt'];
}

// Drill Known/Unknown Quote
if ($filter_list_id) {
    $stmt = $pdo->prepare("
        SELECT result, COUNT(*) AS cnt
        FROM learning_events le
        JOIN cards c ON c.id = le.card_id
        WHERE le.person_id = ? AND c.list_id = ? AND result IN ('known','unknown')
        GROUP BY result
    ");
    $stmt->execute([$person_id, $filter_list_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT result, COUNT(*) AS cnt
        FROM learning_events le
        WHERE le.person_id = ? AND result IN ('known','unknown')
        GROUP BY result
    ");
    $stmt->execute([$person_id]);
}

$drill_stats = ['known' => 0, 'unknown' => 0];
foreach ($stmt->fetchAll() as $row) {
    $drill_stats[$row['result']] = $row['cnt'];
}
$drill_total = $drill_stats['known'] + $drill_stats['unknown'];
$drill_pct   = $drill_total > 0 ? round($drill_stats['known'] / $drill_total * 100) : 0;

// Aktuelle Liste für Filter-Anzeige
$filter_list = null;
if ($filter_list_id) {
    foreach ($own_lists as $l) {
        if ($l['id'] === $filter_list_id) {
            $filter_list = $l;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistik — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Statistik', '']]) ?></div>

<div class="container mt-2" style="max-width:860px;">

    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <h1 class="h4 mb-0">Statistik</h1>
        <?php if ($streak > 0): ?>
        <span class="badge bg-warning text-dark fs-6">🔥 <?= $streak ?> Tag<?= $streak > 1 ? 'e' : '' ?> Streak</span>
        <?php endif; ?>
    </div>

    <!-- Listen-Filter -->
    <div class="mb-4 d-flex gap-2 flex-wrap">
        <?php foreach ($own_lists as $list): ?>
        <a href="stats.php?list_id=<?= $list['id'] ?>"
           class="btn btn-sm <?= $filter_list_id === $list['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= htmlspecialchars($list['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">

        <!-- Leitner-Übersicht -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Leitner-System</div>
                <div class="card-body">

                    <!-- Karten pro Fach -->
                    <?php foreach ([1,2,3,4,5] as $box): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Fach <?= $box ?> (<?= [1=>'täglich',2=>'2 Tage',3=>'7 Tage',4=>'14 Tage',5=>'30 Tage'][$box] ?>)</span>
                            <strong><?= $box_counts[$box] ?></strong>
                        </div>
                        <?php $pct = $total_active > 0 ? round($box_counts[$box] / $total_active * 100) : 0; ?>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-<?= ['','danger','warning','info','primary','success'][$box] ?>"
                                 style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <hr>
                    <div class="d-flex justify-content-between small">
                        <span>⏳ Warteschlange</span><strong><?= $queued_count ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>✅ Archiviert</span><strong><?= $archived_count ?></strong>
                    </div>

                    <!-- Richtig/Falsch -->
                    <?php if ($leitner_total > 0): ?>
                    <hr>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Richtig/Falsch-Quote</span>
                        <strong><?= $leitner_pct ?>%</strong>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?= $leitner_pct ?>%"></div>
                        <div class="progress-bar bg-danger" style="width:<?= 100-$leitner_pct ?>%"></div>
                    </div>
                    <div class="small text-muted mt-1">
                        <?= $leitner_stats['correct'] ?> richtig · <?= $leitner_stats['incorrect'] ?> falsch
                        (<?= $leitner_total ?> gesamt)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Drill-Übersicht -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Drill-Modus</div>
                <div class="card-body">

                    <?php if (!$drill_mastery && !$drill_total): ?>
                    <p class="text-muted small">Noch keine Drill-Daten vorhanden.</p>
                    <?php else: ?>

                    <!-- Gemeisterte Karten -->
                    <?php if ($drill_mastery): ?>
                    <p class="small fw-semibold mb-2">Gemeisterte Karten</p>
                    <?php foreach ([1,2,3] as $level): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= $level ?>× gemeistert
                            <?php if ($level < 3): ?>
                            <span class="text-muted">(→ Leitner Fach <?= [1=>2,2=>3,3=>4][$level] ?>)</span>
                            <?php endif; ?>
                        </span>
                        <strong><?= $drill_mastery[$level] ?? 0 ?></strong>
                    </div>
                    <?php endforeach; ?>
                    <hr>
                    <?php endif; ?>

                    <!-- Gewusst/Nicht-gewusst Quote -->
                    <?php if ($drill_total > 0): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Gewusst-Quote</span>
                        <strong><?= $drill_pct ?>%</strong>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?= $drill_pct ?>%"></div>
                        <div class="progress-bar bg-danger" style="width:<?= 100-$drill_pct ?>%"></div>
                    </div>
                    <div class="small text-muted mt-1">
                        <?= $drill_stats['known'] ?> gewusst · <?= $drill_stats['unknown'] ?> musste nachdenken
                        (<?= $drill_total ?> gesamt)
                    </div>
                    <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
