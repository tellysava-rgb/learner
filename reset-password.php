<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Bereits eingeloggt → zur Startseite
if (!empty($_SESSION['authenticated'])) {
    header('Location: home.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '') {
    header('Location: forgot-password.php');
    exit;
}

$error   = '';
$success = false;

$hash = hash('sha256', $token);
$stmt = $pdo->prepare("SELECT id FROM persons WHERE reset_token_hash = ? AND reset_token_expires > NOW()");
$stmt->execute([$hash]);
$person = $stmt->fetch();

if (!$person) {
    $error = 'Dieser Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $new_pw  = $_POST['new_password']  ?? '';
    $new_pw2 = $_POST['new_password2'] ?? '';

    if (mb_strlen($new_pw) < 8) {
        $error = 'Neues Passwort muss mindestens 8 Zeichen haben.';
    } elseif ($new_pw !== $new_pw2) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE persons SET password_hash = ?, reset_token_hash = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->execute([$new_hash, $person['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Passwort zurücksetzen</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:400px; margin-top:100px;">
    <div class="text-center mb-4">
        <h1 class="h3"><?= APP_NAME ?></h1>
        <p class="text-muted">Passwort zurücksetzen</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <?php if ($success): ?>
                <div class="alert alert-success">Passwort erfolgreich geändert.</div>
                <a href="index.php" class="btn btn-primary w-100">Jetzt einloggen</a>
            <?php elseif ($error && !$person): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <a href="forgot-password.php" class="btn btn-outline-secondary w-100">Neuen Link anfordern</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Neues Passwort</label>
                        <input type="password" name="new_password" class="form-control form-control-lg"
                               autofocus required autocomplete="new-password" minlength="8">
                        <div class="form-text">Min. 8 Zeichen</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Neues Passwort (Wiederholung)</label>
                        <input type="password" name="new_password2" class="form-control form-control-lg"
                               required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Passwort setzen</button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
