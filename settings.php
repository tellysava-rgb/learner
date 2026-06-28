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
$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        logout();
    }

    if ($action === 'save_settings') {
        $minutes = intval($_POST['drill_minutes'] ?? 0);
        if ($minutes < 1 || $minutes > 120) {
            $_SESSION['flash_error'] = 'Ungültiger Wert. Bitte gib eine Zahl zwischen 1 und 120 Minuten ein.';
        } else {
            $seconds  = $minutes * 60;
            $content  = file_get_contents($config_path);
            $new_line = "define('DRILL_SESSION_SECONDS', {$seconds}); // {$minutes} Minuten";
            $content  = preg_replace(
                "/define\('DRILL_SESSION_SECONDS',\s*\d+\);[^\n]*/",
                $new_line,
                $content
            );
            if (file_put_contents($config_path, $content) !== false) {
                $_SESSION['flash_success'] = "Drill-Timer auf {$minutes} Minuten gesetzt.";
            } else {
                $_SESSION['flash_error'] = 'Fehler beim Schreiben von config.php. Prüfe die Dateirechte.';
            }
        }
        header('Location: settings.php');
        exit;
    }
}

// Aktuellen Wert aus config lesen (nach möglichem Speichern)
$current_seconds = DRILL_SESSION_SECONDS;
$current_minutes = (int) round($current_seconds / 60);

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

<div class="container mt-2" style="max-width:560px;">

    <div class="d-flex align-items-center gap-2 mb-4">
        <h1 class="h4 mb-0">Einstellungen</h1>
        <span class="badge bg-warning text-dark">Localhost only</span>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Drill-Modus</div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">
                <div class="mb-3">
                    <label for="drill_minutes" class="form-label">Timer-Dauer (Minuten)</label>
                    <input type="number" class="form-control" id="drill_minutes" name="drill_minutes"
                           min="1" max="120" value="<?= $current_minutes ?>" style="max-width:120px;">
                    <div class="form-text">Aktuell: <?= $current_minutes ?> Minuten (<?= $current_seconds ?> Sekunden). Wert wird dauerhaft in config.php gespeichert.</div>
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
