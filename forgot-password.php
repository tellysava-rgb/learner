<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Bereits eingeloggt → zur Startseite
if (!empty($_SESSION['authenticated'])) {
    header('Location: home.php');
    exit;
}

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $email = trim($_POST['email'] ?? '');

    // Bremse pro IP: verhindert, dass über dieses Formular beliebig viele Mails ausgelöst werden
    // (Flooding beim Empfänger, Reputationsschaden der Absenderdomain).
    $rate_limited = auth_limit_reached($pdo, 'forgot');
    if ($rate_limited) {
        error_log('forgot-password.php: Rate-Limit erreicht für IP ' . client_ip());
    }

    if ($email !== '' && !$rate_limited) {
        auth_attempt_record($pdo, 'forgot');
        $stmt = $pdo->prepare("SELECT id, name FROM persons WHERE email = ?");
        $stmt->execute([$email]);
        $person = $stmt->fetch();

        // Basis-URL muss konfiguriert sein (Einstellungen → Basis-URL). Ohne sie wird bewusst
        // keine Mail verschickt, statt einen Link aus dem fälschbaren Host-Header zu bauen.
        $base_url = app_base_url();
        if ($person && $base_url === '') {
            error_log('forgot-password.php: APP_BASE_URL ist nicht konfiguriert — keine Reset-Mail verschickt.');
        } elseif ($person) {
            $raw_token = bin2hex(random_bytes(32));
            $hash      = hash('sha256', $raw_token);
            $expires   = (new DateTimeImmutable('+60 minutes'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("UPDATE persons SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?");
            $stmt->execute([$hash, $expires, $person['id']]);

            $link = $base_url . '/reset-password.php?token=' . $raw_token;

            $subject = mb_encode_mimeheader(APP_NAME . ': Passwort zurücksetzen', 'UTF-8', 'B');
            $body    = "Hallo " . $person['name'] . ",\n\n"
                     . "Hier ist dein Link zum Zurücksetzen deines Passworts (gültig 60 Minuten):\n\n"
                     . $link . "\n\n"
                     . "Falls du das nicht angefordert hast, kannst du diese E-Mail ignorieren.";
            // Absenderadresse aus der Konfiguration (nie aus dem Host-Header) — landet zusätzlich
            // im -f-Parameter von mail() als Envelope-Sender, der für SPF/DMARC ausgewertet wird.
            $from_address = mail_from_address();
            $headers = "From: " . APP_NAME . " <" . $from_address . ">\r\n"
                     . "Content-Type: text/plain; charset=utf-8";

            $sent = mail($email, $subject, $body, $headers, '-f ' . $from_address);
            if (!$sent) {
                error_log('forgot-password.php: mail() fehlgeschlagen für person_id=' . $person['id']);
            }
        }
    }

    // Immer dieselbe Meldung, unabhängig davon ob die E-Mail einer Person zugeordnet ist
    // (verhindert, dass sich herausfinden lässt, welche E-Mail-Adressen registriert sind)
    $success = 'Falls diese E-Mail-Adresse einer Person zugeordnet ist, wurde ein Link zum Zurücksetzen des Passworts gesendet.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Passwort vergessen</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body class="bg-light">
<div class="container" style="max-width:400px; margin-top:100px;">
    <div class="text-center mb-4">
        <h1 class="h3"><?= APP_NAME ?></h1>
        <p class="text-muted">Passwort vergessen</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <a href="index.php" class="btn btn-outline-secondary w-100">Zurück zum Login</a>
            <?php else: ?>
                <p class="text-muted small">Gib deine hinterlegte E-Mail-Adresse ein. Falls eine Person mit dieser Adresse existiert, erhältst du einen Link zum Zurücksetzen deines Passworts. Ohne hinterlegte E-Mail-Adresse ist ein Zurücksetzen nur über den Admin möglich.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">E-Mail</label>
                        <input type="email" name="email" class="form-control form-control-lg" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Link anfordern</button>
                </form>
                <a href="index.php" class="d-block text-center small mt-3 text-muted">Zurück zum Login</a>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
