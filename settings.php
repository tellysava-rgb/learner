<?php
require_once __DIR__ . '/auth.php';
require_login();

// Nur auf Localhost zugänglich
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array(strtolower(explode(':', $host)[0]), ['localhost', '127.0.0.1'], true)) {
    http_response_code(403);
    die('Diese Seite ist nur in der lokalen Entwicklungsumgebung verfügbar.');
}

$person_name = $_SESSION['person_name'] ?? '';
$config_path = __DIR__ . '/config.php';
$success     = $_SESSION['flash_success'] ?? '';
$errors      = $_SESSION['flash_errors']  ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    if (($_POST['action'] ?? '') === 'logout') {
        logout();
    }

    if (($_POST['action'] ?? '') === 'save_settings') {
        $fields = [
            'session_timeout_min' => ['min' => 1,  'max' => 480, 'label' => 'Session-Timeout'],
            'daily_card_limit'    => ['min' => 1,  'max' => 100, 'label' => 'Tägliches Karten-Limit'],
            'drill_minutes'       => ['min' => 1,  'max' => 120, 'label' => 'Drill-Timer'],
            'drill_too_hard'      => ['min' => 1,  'max' => 20,  'label' => '«Musste nachdenken»-Limit'],
            'drill_mastery'       => ['min' => 1,  'max' => 10,  'label' => 'Mastery-Schwelle'],
            'drill_known_ratio'   => ['min' => 1,  'max' => 30,  'label' => 'Bekannt/Neu-Verhältnis'],
        ];

        $vals   = [];
        $errs   = [];
        foreach ($fields as $key => $spec) {
            $v = intval($_POST[$key] ?? 0);
            if ($v < $spec['min'] || $v > $spec['max']) {
                $errs[] = "{$spec['label']}: Wert muss zwischen {$spec['min']} und {$spec['max']} liegen.";
            }
            $vals[$key] = $v;
        }

        if (empty($errs)) {
            $c = file_get_contents($config_path);

            $timeout_sec = $vals['session_timeout_min'] * 60;
            $c = preg_replace(
                "/define\('SESSION_TIMEOUT',\s*\d+\)[^\n]*/",
                "define('SESSION_TIMEOUT', {$timeout_sec}); // {$vals['session_timeout_min']} Minuten",
                $c
            );
            $c = preg_replace(
                "/define\('DAILY_CARD_LIMIT',\s*\d+\)[^\n]*/",
                "define('DAILY_CARD_LIMIT', {$vals['daily_card_limit']});",
                $c
            );
            $drill_sec = $vals['drill_minutes'] * 60;
            $c = preg_replace(
                "/define\('DRILL_SESSION_SECONDS',\s*\d+\)[^\n]*/",
                "define('DRILL_SESSION_SECONDS', {$drill_sec}); // {$vals['drill_minutes']} Minuten",
                $c
            );
            $c = preg_replace(
                "/define\('DRILL_TOO_HARD_LIMIT',\s*\d+\)[^\n]*/",
                "define('DRILL_TOO_HARD_LIMIT', {$vals['drill_too_hard']});",
                $c
            );
            $c = preg_replace(
                "/define\('DRILL_MASTERY_THRESHOLD',\s*\d+\)[^\n]*/",
                "define('DRILL_MASTERY_THRESHOLD', {$vals['drill_mastery']}); // {$vals['drill_mastery']}× hintereinander korrekt = gemeistert",
                $c
            );
            $c = preg_replace(
                "/define\('DRILL_KNOWN_RATIO',\s*\d+\)[^\n]*/",
                "define('DRILL_KNOWN_RATIO', {$vals['drill_known_ratio']}); // {$vals['drill_known_ratio']} bekannte Karten pro 1 neue/unbekannte",
                $c
            );

            if (file_put_contents($config_path, $c) !== false) {
                $_SESSION['flash_success'] = 'Einstellungen gespeichert.';
            } else {
                $_SESSION['flash_errors'] = ['Fehler beim Schreiben von config.php. Prüfe die Dateirechte.'];
            }
        } else {
            $_SESSION['flash_errors'] = $errs;
        }
        header('Location: settings.php');
        exit;
    }
}

// Aktuelle Werte (frisch aus config, nach PRG-Redirect)
$cur_timeout_min = (int) round(SESSION_TIMEOUT / 60);
$cur_daily       = DAILY_CARD_LIMIT;
$cur_drill_min   = (int) round(DRILL_SESSION_SECONDS / 60);
$cur_too_hard    = DRILL_TOO_HARD_LIMIT;
$cur_mastery     = DRILL_MASTERY_THRESHOLD;
$cur_known_ratio = DRILL_KNOWN_RATIO;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einstellungen — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php if ($person_name): ?>
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <?php endif; ?>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Einstellungen', '']]) ?></div>

<div class="container mt-2" style="max-width:760px;">

    <div class="d-flex align-items-center gap-2 mb-4">
        <h1 class="h4 mb-0">Einstellungen</h1>
        <span class="badge bg-warning text-dark">Localhost only</span>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="row g-4">

            <!-- Allgemein & Leitner -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Allgemein & Leitner</div>
                    <div class="card-body d-flex flex-column gap-4">

                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-medium">Session-Timeout</div>
                                <div class="text-muted small">Minuten Inaktivität bis zur automatischen Abmeldung.</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <input type="number" class="form-control form-control-sm text-end"
                                       name="session_timeout_min" value="<?= $cur_timeout_min ?>"
                                       min="1" max="480" style="width:72px;">
                                <div class="text-muted" style="font-size:0.7rem;">Min. (1–480)</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-medium">Tägliches Karten-Limit</div>
                                <div class="text-muted small">Wie viele neue Karten täglich aus der Warteschlange aktiviert werden.</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <input type="number" class="form-control form-control-sm text-end"
                                       name="daily_card_limit" value="<?= $cur_daily ?>"
                                       min="1" max="100" style="width:72px;">
                                <div class="text-muted" style="font-size:0.7rem;">Karten (1–100)</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Drill -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Drill-Modus</div>
                    <div class="card-body d-flex flex-column gap-4">

                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-medium">Timer</div>
                                <div class="text-muted small">Dauer einer Drill-Session. Nach Ablauf wird die aktuelle Karte noch fertig gespielt.</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <input type="number" class="form-control form-control-sm text-end"
                                       name="drill_minutes" value="<?= $cur_drill_min ?>"
                                       min="1" max="120" style="width:72px;">
                                <div class="text-muted" style="font-size:0.7rem;">Min. (1–120)</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-medium">«Musste nachdenken»-Limit</div>
                                <div class="text-muted small">Wie oft eine Karte in einer Session als zu schwer bewertet werden darf, bevor sie entfernt wird.</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <input type="number" class="form-control form-control-sm text-end"
                                       name="drill_too_hard" value="<?= $cur_too_hard ?>"
                                       min="1" max="20" style="width:72px;">
                                <div class="text-muted" style="font-size:0.7rem;">× (1–20)</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-medium">Mastery-Schwelle</div>
                                <div class="text-muted small">Wie viele Male hintereinander eine Karte korrekt beantwortet werden muss, um als «gemeistert» zu gelten.</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <input type="number" class="form-control form-control-sm text-end"
                                       name="drill_mastery" value="<?= $cur_mastery ?>"
                                       min="1" max="10" style="width:72px;">
                                <div class="text-muted" style="font-size:0.7rem;">× (1–10)</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-medium">Bekannt/Neu-Verhältnis</div>
                                <div class="text-muted small">Wie viele bekannte Karten zwischen zwei neuen Karten rotieren (9 = 9:1-Verhältnis).</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <input type="number" class="form-control form-control-sm text-end"
                                       name="drill_known_ratio" value="<?= $cur_known_ratio ?>"
                                       min="1" max="30" style="width:72px;">
                                <div class="text-muted" style="font-size:0.7rem;">Karten (1–30)</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div><!-- /row -->

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Alle speichern</button>
            <span class="text-muted small ms-3">Werte werden dauerhaft in config.php geschrieben.</span>
        </div>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
