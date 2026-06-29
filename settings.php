<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$person_name    = $_SESSION['person_name'] ?? '';
$runtime_path   = __DIR__ . '/config-runtime.php';
$success        = $_SESSION['flash_success'] ?? '';
$errors      = $_SESSION['flash_errors']  ?? [];
$is_local    = in_array(strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]), ['localhost', '127.0.0.1'], true);
unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    if (($_POST['action'] ?? '') === 'logout') {
        logout();
    }

    if (($_POST['action'] ?? '') === 'change_password') {
        $cur_pw  = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
        $new_pw2 = $_POST['new_password2']    ?? '';

        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'password_hash'");
        $stmt->execute();
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($cur_pw, $hash)) {
            $_SESSION['flash_errors'] = ['Aktuelles Passwort ist falsch.'];
        } elseif (mb_strlen($new_pw) < 8) {
            $_SESSION['flash_errors'] = ['Neues Passwort muss mindestens 8 Zeichen haben.'];
        } elseif ($new_pw !== $new_pw2) {
            $_SESSION['flash_errors'] = ['Die neuen Passwörter stimmen nicht überein.'];
        } else {
            $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'password_hash'");
            $stmt->execute([$new_hash]);
            $_SESSION['flash_success'] = 'Passwort erfolgreich geändert.';
        }
        header('Location: settings.php');
        exit;
    }

    if (($_POST['action'] ?? '') === 'save_settings') {
        $int_fields = [
            'session_timeout_min'  => ['min' => 1,  'max' => 480, 'label' => 'Session-Timeout'],
            'daily_card_limit'     => ['min' => 1,  'max' => 100, 'label' => 'Tägliches Karten-Limit'],
            'leitner_default_cards'=> ['min' => 1,  'max' => 200, 'label' => 'Default Kartenanzahl'],
            'drill_minutes'        => ['min' => 1,  'max' => 120, 'label' => 'Drill-Timer'],
            'drill_too_hard'       => ['min' => 1,  'max' => 20,  'label' => '«Musste nachdenken»-Limit'],
            'drill_mastery'        => ['min' => 1,  'max' => 10,  'label' => 'Mastery-Schwelle'],
            'drill_known_ratio'    => ['min' => 1,  'max' => 30,  'label' => 'Bekannt/Neu-Verhältnis'],
        ];

        $vals   = [];
        $errs   = [];
        foreach ($int_fields as $key => $spec) {
            $v = intval($_POST[$key] ?? 0);
            if ($v < $spec['min'] || $v > $spec['max']) {
                $errs[] = "{$spec['label']}: Wert muss zwischen {$spec['min']} und {$spec['max']} liegen.";
            }
            $vals[$key] = $v;
        }

        // Seitentitel (String-Feld)
        $app_name = trim($_POST['app_name'] ?? '');
        if ($app_name === '' || mb_strlen($app_name) > 50 || str_contains($app_name, "'")) {
            $errs[] = "Seitentitel: Darf nicht leer sein, max. 50 Zeichen, keine Anführungszeichen.";
        }

        if (empty($errs)) {
            $timeout_sec = $vals['session_timeout_min'] * 60;
            $drill_sec   = $vals['drill_minutes'] * 60;

            $runtime = [
                'APP_NAME'               => $app_name,
                'SESSION_TIMEOUT'        => $timeout_sec,
                'DAILY_CARD_LIMIT'       => $vals['daily_card_limit'],
                'LEITNER_DEFAULT_CARDS'  => $vals['leitner_default_cards'],
                'DRILL_SESSION_SECONDS'  => $drill_sec,
                'DRILL_TOO_HARD_LIMIT'   => $vals['drill_too_hard'],
                'DRILL_MASTERY_THRESHOLD'=> $vals['drill_mastery'],
                'DRILL_KNOWN_RATIO'      => $vals['drill_known_ratio'],
            ];

            $lines = "<?php return [\n";
            foreach ($runtime as $k => $v) {
                $lines .= is_int($v)
                    ? "    '{$k}' => {$v},\n"
                    : "    '{$k}' => " . var_export($v, true) . ",\n";
            }
            $lines .= "];\n";

            if (file_put_contents($runtime_path, $lines) !== false) {
                $_SESSION['flash_success'] = 'Einstellungen gespeichert.';
            } else {
                $_SESSION['flash_errors'] = ['Fehler beim Schreiben von config-runtime.php. Prüfe die Dateirechte.'];
            }
        } else {
            $_SESSION['flash_errors'] = $errs;
        }
        header('Location: settings.php');
        exit;
    }
}

// Aktuelle Werte (frisch aus config, nach PRG-Redirect)
$cur_app_name    = APP_NAME;
$cur_timeout_min = (int) round(SESSION_TIMEOUT / 60);
$cur_daily       = DAILY_CARD_LIMIT;
$cur_default_cards = LEITNER_DEFAULT_CARDS;
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
            <?= streak_badge() ?>
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

<div class="container mt-2" style="max-width:960px;">

    <div class="d-flex align-items-center gap-2 mb-4">
        <h1 class="h4 mb-0">Einstellungen</h1>
        <span class="badge bg-secondary">v<?= APP_VERSION ?></span>
        <?php if ($is_local): ?>
        <span class="badge bg-warning text-dark">Localhost</span>
        <?php endif; ?>
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

    <form method="post" style="max-width:640px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="card">
            <div class="list-group list-group-flush">

                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Allgemein</span>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Seitentitel</span>
                        <span class="text-muted small ms-2">Wird oben links in der Navbar angezeigt</span>
                    </div>
                    <div class="flex-shrink-0">
                        <input type="text" class="form-control form-control-sm"
                               name="app_name" value="<?= htmlspecialchars($cur_app_name) ?>"
                               maxlength="50" style="width:160px;">
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Session-Timeout</span>
                        <span class="text-muted small ms-2">Minuten Inaktivität bis zur automatischen Abmeldung</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="session_timeout_min" value="<?= $cur_timeout_min ?>"
                               min="1" max="480" style="width:68px;">
                        <span class="text-muted small">Min.</span>
                    </div>
                </div>

                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Leitner</span>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Tägliches Karten-Limit</span>
                        <span class="text-muted small ms-2">Neue Karten pro Tag aus der Warteschlange</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="daily_card_limit" value="<?= $cur_daily ?>"
                               min="1" max="100" style="width:68px;">
                        <span class="text-muted small">Karten</span>
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Default Kartenanzahl</span>
                        <span class="text-muted small ms-2">Voreingestellte Anzahl Karten beim Session-Start</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="leitner_default_cards" value="<?= $cur_default_cards ?>"
                               min="1" max="200" style="width:68px;">
                        <span class="text-muted small">Karten</span>
                    </div>
                </div>

                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Drill-Modus</span>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Timer</span>
                        <span class="text-muted small ms-2">Dauer einer Drill-Session</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="drill_minutes" value="<?= $cur_drill_min ?>"
                               min="1" max="120" style="width:68px;">
                        <span class="text-muted small">Min.</span>
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">«Musste nachdenken»-Limit</span>
                        <span class="text-muted small ms-2">Bewertungen bis Karte aus der Session entfernt wird</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="drill_too_hard" value="<?= $cur_too_hard ?>"
                               min="1" max="20" style="width:68px;">
                        <span class="text-muted small">×</span>
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Mastery-Schwelle</span>
                        <span class="text-muted small ms-2">Aufeinanderfolgende Korrekt-Antworten für «gemeistert»</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="drill_mastery" value="<?= $cur_mastery ?>"
                               min="1" max="10" style="width:68px;">
                        <span class="text-muted small">×</span>
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Bekannt/Neu-Verhältnis</span>
                        <span class="text-muted small ms-2">Bekannte Karten pro neuer Karte in der Rotation</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="drill_known_ratio" value="<?= $cur_known_ratio ?>"
                               min="1" max="30" style="width:68px;">
                        <span class="text-muted small">Karten</span>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Alle speichern</button>
            <span class="text-muted small ms-3">Dauerhaft in config-runtime.php geschrieben.</span>
        </div>

    </form>

    <form method="post" class="mt-4" style="max-width:640px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="card">
            <div class="list-group list-group-flush">

                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Sicherheit</span>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Aktuelles Passwort</span>
                    </div>
                    <div class="flex-shrink-0">
                        <input type="password" class="form-control form-control-sm"
                               name="current_password" autocomplete="current-password"
                               style="width:200px;" required>
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Neues Passwort</span>
                        <span class="text-muted small ms-2">Min. 8 Zeichen</span>
                    </div>
                    <div class="flex-shrink-0">
                        <input type="password" class="form-control form-control-sm"
                               name="new_password" autocomplete="new-password"
                               minlength="8" style="width:200px;" required>
                    </div>
                </div>

                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Neues Passwort (Wiederholung)</span>
                    </div>
                    <div class="flex-shrink-0">
                        <input type="password" class="form-control form-control-sm"
                               name="new_password2" autocomplete="new-password"
                               style="width:200px;" required>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-outline-danger">Passwort ändern</button>
        </div>

    </form>

    <?php
    $deploy_exists = file_exists(__DIR__ . '/deploy.php');
    $deploy_config = __DIR__ . '/deploy-config.php';
    $deploy_token  = '';
    $github_version = null;
    if ($deploy_exists && file_exists($deploy_config)) {
        require_once $deploy_config;
        $deploy_token = defined('DEPLOY_TOKEN') ? DEPLOY_TOKEN : '';

        // GitHub-Version via cURL abrufen
        if (defined('GITHUB_OWNER') && defined('GITHUB_REPO') && function_exists('curl_init')) {
            $ch = curl_init('https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/main/config.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_USERAGENT      => 'PHP-Deploy/1.0',
            ]);
            $remote = curl_exec($ch);
            curl_close($ch);
            if ($remote && preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $remote, $m)) {
                $github_version = $m[1];
            }
        }
    }
    ?>
    <?php if ($deploy_exists && $deploy_token !== ''): ?>
    <div class="mt-4" style="max-width:640px;">
        <div class="card">
            <div class="list-group list-group-flush">
                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Deployment</span>
                </div>
                <div class="list-group-item py-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="text-center">
                            <div class="text-muted small mb-1">Installiert</div>
                            <span class="badge bg-secondary fs-6">v<?= htmlspecialchars(APP_VERSION) ?></span>
                        </div>
                        <div class="text-muted fs-5">→</div>
                        <div class="text-center">
                            <div class="text-muted small mb-1">GitHub (main)</div>
                            <?php if ($github_version !== null): ?>
                                <?php $up_to_date = ($github_version === APP_VERSION); ?>
                                <span class="badge fs-6 <?= $up_to_date ? 'bg-success' : 'bg-primary' ?>">
                                    v<?= htmlspecialchars($github_version) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6">unbekannt</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (isset($up_to_date) && $up_to_date): ?>
                    <div class="text-success small mb-2">✓ Bereits auf dem neuesten Stand</div>
                    <?php endif; ?>
                    <a href="deploy.php?token=<?= htmlspecialchars($deploy_token) ?>" class="btn btn-sm btn-outline-primary">Deploy starten</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
