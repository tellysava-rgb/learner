<?php
require_once __DIR__ . '/auth.php';

// install.php-Schutz: falls install.php noch existiert → App sperren
if (file_exists(__DIR__ . '/install.php')) {
    die('<div style="font-family:sans-serif;max-width:500px;margin:60px auto;padding:20px;border:2px solid #dc3545;border-radius:8px;color:#dc3545;">
        <strong>Sicherheitswarnung:</strong> install.php existiert noch auf dem Server. Bitte lösche diese Datei manuell, bevor du die App nutzt.
    </div>');
}

// Bereits eingeloggt → zur Startseite
if (!empty($_SESSION['authenticated'])) {
    header('Location: home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'password_hash'");
    $stmt->execute();
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['value'])) {
        session_regenerate_id(true);
        $_SESSION['authenticated']  = true;
        $_SESSION['last_activity']  = time();
        header('Location: home.php');
        exit;
    }

    $error = 'Falsches Passwort.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:400px; margin-top:100px;">
    <div class="text-center mb-4">
        <h1 class="h3"><?= APP_NAME ?></h1>
        <p class="text-muted">Vokabeltrainer</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <?php if (!empty($_GET['timeout'])): ?>
                <div class="alert alert-warning small">Du wurdest wegen Inaktivität ausgeloggt.</div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Passwort</label>
                    <input type="password" name="password" class="form-control form-control-lg"
                           autofocus required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg">Einloggen</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
