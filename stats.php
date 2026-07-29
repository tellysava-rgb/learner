<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id   = $_SESSION['person_id'];

if (in_array($_POST['action'] ?? '', ['logout', 'switch_to_person', 'change_own_password', 'change_own_email'], true)) {
    csrf_validate();
    handle_navbar_actions($pdo);
}

// Eigene Listen — nur aktive stehen zur Auswahl (Filter-Buttons unterhalb der Heatmap)
$stmt = $pdo->prepare("SELECT id, name, language_a, language_b FROM lists WHERE person_id = ? AND is_active = 1 ORDER BY name");
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

$tz        = new DateTimeZone(TIMEZONE);
$today     = new DateTimeImmutable('now', $tz);
$today_str = $today->format('Y-m-d');
$yesterday = $today->modify('-1 day')->format('Y-m-d');

$streak = 0;
if ($dates) {
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
// Lernaktivität (Kennzahlen + Heatmap) — global, unabhängig vom Listen-Filter
// -------------------------------------------------------
$total_learn_days = count($dates);

$best_week = 0;
if ($dates) {
    $week_counts = [];
    foreach ($dates as $d) {
        $key = (new DateTimeImmutable($d, $tz))->format('o-W');
        $week_counts[$key] = ($week_counts[$key] ?? 0) + 1;
    }
    $best_week = max($week_counts);
}

// Heatmap: letzte 52 Wochen (Mo-So) bis heute
$heatmap_weeks = 52;
$this_monday   = $today->modify('monday this week');
$heatmap_start = $this_monday->modify('-' . ($heatmap_weeks - 1) . ' weeks');

$stmt = $pdo->prepare("
    SELECT learn_date, COUNT(*) AS cnt
    FROM learning_events
    WHERE person_id = ? AND result != 'skipped' AND learn_date >= ?
    GROUP BY learn_date
");
$stmt->execute([$person_id, $heatmap_start->format('Y-m-d')]);
$day_counts = [];
foreach ($stmt->fetchAll() as $row) {
    $day_counts[$row['learn_date']] = (int) $row['cnt'];
}
$max_day_count = $day_counts ? max($day_counts) : 0;

$heatmap_cells = [];
$month_labels  = [];
$last_month    = null;
for ($w = 0; $w < $heatmap_weeks; $w++) {
    $week_start = $heatmap_start->modify("+{$w} weeks");
    $month_num  = (int) $week_start->format('n');
    if ($month_num !== $last_month) {
        $month_labels[$w] = $week_start->format('M');
        $last_month = $month_num;
    }
    for ($d = 0; $d < 7; $d++) {
        $date     = $week_start->modify("+{$d} days");
        $date_str = $date->format('Y-m-d');
        if ($date > $today) {
            $heatmap_cells[] = null;
            continue;
        }
        $cnt   = $day_counts[$date_str] ?? 0;
        $level = 0;
        if ($cnt > 0) {
            $level = $max_day_count > 0 ? (int) min(4, max(1, ceil($cnt / $max_day_count * 4))) : 1;
        }
        $heatmap_cells[] = ['date' => $date_str, 'date_display' => $date->format('d.m.Y'), 'cnt' => $cnt, 'level' => $level];
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistik — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Statistik', '']]) ?></div>

<div class="container mt-2" style="max-width:960px;">

    <h1 class="h4 mb-4">Statistik</h1>

    <!-- Lernaktivität -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Lernaktivität</div>
        <div class="card-body">
            <div class="row g-3 mb-4 text-center">
                <div class="col-4">
                    <div class="fs-3">🔥 <?= $streak ?></div>
                    <div class="small text-muted">Aktueller Streak</div>
                </div>
                <div class="col-4">
                    <div class="fs-3"><?= $total_learn_days ?></div>
                    <div class="small text-muted">Lerntage gesamt</div>
                </div>
                <div class="col-4">
                    <div class="fs-3"><?= $best_week ?></div>
                    <div class="small text-muted">Beste Woche</div>
                </div>
            </div>

            <div class="heatmap-wrap">
                <div class="heatmap-inner">
                    <div class="heatmap-months">
                        <?php foreach ($month_labels as $w => $label): ?>
                        <span style="left:<?= $w * 14 ?>px;"><?= htmlspecialchars($label) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="heatmap-body">
                        <div class="heatmap-weekday-labels">
                            <span>Mo</span><span></span><span>Mi</span><span></span><span>Fr</span><span></span><span></span>
                        </div>
                        <div class="heatmap-grid">
                            <?php foreach ($heatmap_cells as $cell): ?>
                                <?php if ($cell === null): ?>
                                <div class="heatmap-cell heatmap-cell-empty"></div>
                                <?php else: ?>
                                <div class="heatmap-cell lvl-<?= $cell['level'] ?>"
                                     title="<?= htmlspecialchars($cell['date_display']) ?><?= $cell['cnt'] > 0 ? ' — ' . $cell['cnt'] . ' Karte' . ($cell['cnt'] == 1 ? '' : 'n') . ' gelernt' : ' — nicht gelernt' ?>"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
