<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_admin();

$runtime_path   = __DIR__ . '/includes/config-runtime.php';
$success        = $_SESSION['flash_success'] ?? '';
$errors      = $_SESSION['flash_errors']  ?? [];
$is_local    = in_array(strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]), ['localhost', '127.0.0.1'], true);
unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);

    if (($_POST['action'] ?? '') === 'save_settings') {
        $int_fields = [
            'session_timeout_min'  => ['min' => 1,  'max' => 1440, 'label' => 'Session-Timeout'],
            'daily_card_limit'     => ['min' => 1,  'max' => 100, 'label' => 'Tägliches Karten-Limit'],
            'leitner_default_cards'=> ['min' => 1,  'max' => 200, 'label' => 'Default Kartenanzahl'],
            'drill_minutes'        => ['min' => 1,  'max' => 120, 'label' => 'Drill-Timer'],
            'drill_too_hard'       => ['min' => 1,  'max' => 20,  'label' => '«Musste nachdenken»-Limit'],
            'drill_mastery'        => ['min' => 1,  'max' => 10,  'label' => 'Mastery-Schwelle'],
            'drill_known_ratio'    => ['min' => 1,  'max' => 30,  'label' => 'Bekannt/Neu-Verhältnis'],
            'drill_pin_ratio'      => ['min' => 2,  'max' => 50,  'label' => 'Vormerkungs-Häufigkeit'],
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

        // Basis-URL (String-Feld, darf leer bleiben — dann fällt der Mailversand auf Dev zurück,
        // siehe app_base_url() in auth.php). Muss absolut sein, damit sie in Mails funktioniert.
        $base_url = rtrim(trim($_POST['app_base_url'] ?? ''), '/');
        if ($base_url !== '' && !preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?(/[A-Za-z0-9._~\-/]*)?$#', $base_url)) {
            $errs[] = "Basis-URL: Muss mit http:// oder https:// beginnen und eine gültige Adresse sein (z.B. https://example.com/learner).";
        }

        // Absenderadresse (String-Feld, darf leer bleiben — dann 'no-reply@' + Host der Basis-URL)
        $mail_from = trim($_POST['mail_from'] ?? '');
        if ($mail_from !== '' && !filter_var($mail_from, FILTER_VALIDATE_EMAIL)) {
            $errs[] = "Absender-E-Mail: Keine gültige E-Mail-Adresse.";
        }

        // Vormerkungs-Priorität (Radio, manueller POST mit ungültigem Wert fällt auf den
        // sichereren Default 'weighted' zurück statt einen Fehler zu zeigen)
        $pin_mode = $_POST['drill_pin_mode'] ?? 'weighted';
        if (!in_array($pin_mode, ['absolute', 'weighted'], true)) {
            $pin_mode = 'weighted';
        }

        // Karten pro Minute (Dezimalwert, daher ausserhalb der int_fields-Schleife)
        $cards_per_minute = (float) str_replace(',', '.', trim($_POST['drill_cards_per_minute'] ?? ''));
        if ($cards_per_minute < 0.2 || $cards_per_minute > 10) {
            $errs[] = 'Aktive Karten pro Minute: Wert muss zwischen 0.2 und 10 liegen.';
        }

        if (empty($errs)) {
            $drill_sec   = $vals['drill_minutes'] * 60;

            $runtime = [
                'APP_NAME'               => $app_name,
                'APP_BASE_URL'           => $base_url,
                'MAIL_FROM'              => $mail_from,
                'SESSION_TIMEOUT'        => $vals['session_timeout_min'],
                'DAILY_CARD_LIMIT'       => $vals['daily_card_limit'],
                'LEITNER_DEFAULT_CARDS'  => $vals['leitner_default_cards'],
                'DRILL_SESSION_SECONDS'  => $drill_sec,
                'DRILL_TOO_HARD_LIMIT'   => $vals['drill_too_hard'],
                'DRILL_MASTERY_THRESHOLD'=> $vals['drill_mastery'],
                'DRILL_KNOWN_RATIO'      => $vals['drill_known_ratio'],
                'DRILL_CARDS_PER_MINUTE' => $cards_per_minute,
                'DRILL_PIN_MODE'         => $pin_mode,
                'DRILL_PIN_RATIO'        => $vals['drill_pin_ratio'],
                'DEBUG_MODE'             => DEBUG_MODE,
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

    if (($_POST['action'] ?? '') === 'send_test_mail') {
        $test_email = trim($_POST['test_email'] ?? '');

        if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_errors'] = ['Ungültige E-Mail-Adresse.'];
        } else {
            $subject = mb_encode_mimeheader(APP_NAME . ': Test-E-Mail', 'UTF-8', 'B');
            $body    = "Dies ist eine Test-E-Mail von " . APP_NAME . ".\n\n"
                     . "Wenn du diese Nachricht erhältst, funktioniert der E-Mail-Versand (inkl. Umlaut-Kodierung) auf diesem Server korrekt.";
            // Gleiche Absenderadresse wie beim Passwort-Reset (siehe mail_from_address()).
            $from_address = mail_from_address();
            $headers = "From: " . APP_NAME . " <" . $from_address . ">\r\n"
                     . "Content-Type: text/plain; charset=utf-8";

            $sent = mail($test_email, $subject, $body, $headers, '-f ' . $from_address);
            if ($sent) {
                $_SESSION['flash_success'] = 'Test-E-Mail an ' . $test_email . ' wurde übergeben.';
            } else {
                error_log('settings.php: Test-Mail-Versand fehlgeschlagen für ' . $test_email);
                $_SESSION['flash_errors'] = ['Versand der Test-E-Mail ist fehlgeschlagen.'];
            }
        }
        header('Location: settings.php');
        exit;
    }

    if (($_POST['action'] ?? '') === 'save_debug') {
        // Eigenes kleines Formular statt Teil des grossen Einstellungs-Formulars — Checkbox
        // schickt bei "aus" gar kein Feld mit. Andere Runtime-Werte bleiben unverändert, da sie
        // aus den aktuell geladenen Konstanten übernommen werden (nicht aus $_POST).
        $debug_mode = isset($_POST['debug_mode']);

        $runtime = [
            'APP_NAME'               => APP_NAME,
            'APP_BASE_URL'           => APP_BASE_URL,
            'MAIL_FROM'              => MAIL_FROM,
            'SESSION_TIMEOUT'        => SESSION_TIMEOUT,
            'DAILY_CARD_LIMIT'       => DAILY_CARD_LIMIT,
            'LEITNER_DEFAULT_CARDS'  => LEITNER_DEFAULT_CARDS,
            'DRILL_SESSION_SECONDS'  => DRILL_SESSION_SECONDS,
            'DRILL_TOO_HARD_LIMIT'   => DRILL_TOO_HARD_LIMIT,
            'DRILL_MASTERY_THRESHOLD'=> DRILL_MASTERY_THRESHOLD,
            'DRILL_KNOWN_RATIO'      => DRILL_KNOWN_RATIO,
            'DRILL_CARDS_PER_MINUTE' => DRILL_CARDS_PER_MINUTE,
            'DRILL_PIN_MODE'         => DRILL_PIN_MODE,
            'DRILL_PIN_RATIO'        => DRILL_PIN_RATIO,
            'DEBUG_MODE'             => $debug_mode,
        ];

        $lines = "<?php return [\n";
        foreach ($runtime as $k => $v) {
            $lines .= is_int($v)
                ? "    '{$k}' => {$v},\n"
                : "    '{$k}' => " . var_export($v, true) . ",\n";
        }
        $lines .= "];\n";

        if (file_put_contents($runtime_path, $lines) !== false) {
            $_SESSION['flash_success'] = $debug_mode ? 'Debug-Modus aktiviert.' : 'Debug-Modus deaktiviert.';
        } else {
            $_SESSION['flash_errors'] = ['Fehler beim Schreiben von config-runtime.php. Prüfe die Dateirechte.'];
        }
        header('Location: settings.php');
        exit;
    }
}

// Aktuelle Werte (frisch aus config, nach PRG-Redirect)
$cur_app_name    = APP_NAME;
// Noch nicht konfiguriert: aktuelle Adresse als Vorschlag anzeigen, damit ein einmaliges Speichern
// den Wert festschreibt (danach unabhängig vom fälschbaren Host-Header der jeweiligen Anfrage).
$cur_base_url    = APP_BASE_URL !== '' ? APP_BASE_URL : current_base_url();
$cur_mail_from   = MAIL_FROM;
// Läuft die App auf einer Subdomain, ist die abgeleitete Absenderadresse ein häufiger Grund für
// nicht zugestellte Mails (kein eigener SPF-Record, DMARC der Hauptdomain greift trotzdem).
$mail_host       = parse_url(app_base_url(), PHP_URL_HOST) ?: '';
$mail_host_parts = $mail_host !== '' ? explode('.', $mail_host) : [];
$mail_is_subdomain = count($mail_host_parts) > 2;
$mail_root_domain  = $mail_is_subdomain ? implode('.', array_slice($mail_host_parts, -2)) : $mail_host;
$cur_timeout_min = (int) SESSION_TIMEOUT;
$cur_daily       = DAILY_CARD_LIMIT;
$cur_default_cards = LEITNER_DEFAULT_CARDS;
$cur_drill_min   = (int) round(DRILL_SESSION_SECONDS / 60);
$cur_too_hard    = DRILL_TOO_HARD_LIMIT;
$cur_mastery     = DRILL_MASTERY_THRESHOLD;
$cur_known_ratio = DRILL_KNOWN_RATIO;
$cur_cards_per_minute = DRILL_CARDS_PER_MINUTE;
$cur_pin_mode    = DRILL_PIN_MODE;
$cur_pin_ratio   = DRILL_PIN_RATIO;
$cur_debug_mode  = DEBUG_MODE;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einstellungen — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Einstellungen', '']]) ?></div>

<div class="container mt-2 mb-5">

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

    <div class="row g-4">
    <div class="col-md-6">

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="card">
            <div class="list-group list-group-flush">

                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Allgemein</span>
                </div>

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Basis-URL</span>
                        <span class="text-muted small ms-2">Für Links in E-Mails (Passwort-Reset) — ohne Slash am Ende</span>
                        <?php if (APP_BASE_URL === ''): ?>
                        <div class="text-warning small mt-1">
                            <i class="bi bi-exclamation-triangle"></i>
                            Noch nicht gespeichert. Solange sie fehlt, verschickt „Passwort vergessen" auf dem Server keine Mail.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-shrink-0">
                        <input type="text" class="form-control form-control-sm"
                               name="app_base_url" value="<?= htmlspecialchars($cur_base_url) ?>"
                               maxlength="200" style="width:260px;" placeholder="https://example.com/learner">
                    </div>
                </div>

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Absender-E-Mail</span>
                        <span class="text-muted small ms-2">Absender für Passwort-Reset und Test-Mail</span>
                        <?php if (MAIL_FROM === '' && $mail_is_subdomain): ?>
                        <div class="text-warning small mt-1">
                            <i class="bi bi-exclamation-triangle"></i>
                            Leer — es wird <code>no-reply@<?= htmlspecialchars($mail_host) ?></code> verwendet.
                            Das ist eine Subdomain und hat in der Regel keinen eigenen SPF-Record, wodurch Mails
                            trotz „erfolgreichem" Versand beim Empfänger aussortiert werden. Besser eine Adresse
                            der Hauptdomain eintragen, z.B. <code>no-reply@<?= htmlspecialchars($mail_root_domain) ?></code>.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-shrink-0">
                        <input type="text" class="form-control form-control-sm"
                               name="mail_from" value="<?= htmlspecialchars($cur_mail_from) ?>"
                               maxlength="200" style="width:260px;"
                               placeholder="<?= $mail_root_domain !== '' ? 'no-reply@' . htmlspecialchars($mail_root_domain) : 'no-reply@example.com' ?>">
                    </div>
                </div>

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Session-Timeout</span>
                        <span class="text-muted small ms-2">Minuten Inaktivität bis zur automatischen Abmeldung</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="session_timeout_min" value="<?= $cur_timeout_min ?>"
                               min="1" max="1440" style="width:68px;">
                        <span class="text-muted small">Min.</span>
                    </div>
                </div>

                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Leitner</span>
                </div>

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
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

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Aktive Karten pro Minute</span>
                        <span class="text-muted small ms-2">Wie viele Karten pro Timer-Minute gleichzeitig in die Rotation genommen werden (Rest folgt in einer künftigen Session) — höher = mehr Abwechslung, niedriger = häufigere Wiederholung derselben Karte und damit realistischere Chance auf "gemeistert" innerhalb einer Session</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="drill_cards_per_minute" value="<?= $cur_cards_per_minute ?>"
                               min="0.2" max="10" step="0.1" style="width:68px;">
                        <span class="text-muted small">Karten/Min.</span>
                    </div>
                </div>

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Vormerkungs-Priorität</span>
                        <span class="text-muted small ms-2">Wie stark "für Drill vorgemerkte" Karten im Drill bevorzugt werden</span>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" name="drill_pin_mode" id="pin_weighted"
                                   value="weighted" <?= $cur_pin_mode !== 'absolute' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pin_weighted">Gewichtet</label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" name="drill_pin_mode" id="pin_absolute"
                                   value="absolute" <?= $cur_pin_mode === 'absolute' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pin_absolute">Absolut</label>
                        </div>
                    </div>
                </div>

                <div class="list-group-item settings-row d-flex align-items-center gap-3 py-2">
                    <div class="flex-grow-1">
                        <span class="fw-medium">Vormerkungs-Häufigkeit</span>
                        <span class="text-muted small ms-2">Bei "Gewichtet": vorgemerkte Karte alle N Karten einschieben (bei "Absolut" ohne Wirkung)</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <input type="number" class="form-control form-control-sm text-end"
                               name="drill_pin_ratio" value="<?= $cur_pin_ratio ?>"
                               min="2" max="50" style="width:68px;">
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

    </div>
    <div class="col-md-6">

    <div class="card">
            <div class="list-group list-group-flush">
                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">E-Mail-Test</span>
                </div>
                <div class="list-group-item py-3">
                    <p class="text-muted small mb-2">Verschickt eine Test-E-Mail mit derselben Methode wie "Passwort vergessen" — nützlich um den Mailversand auf diesem Server zu prüfen.</p>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_test_mail">
                        <input type="email" name="test_email" class="form-control form-control-sm" placeholder="name@beispiel.ch" required style="max-width:260px;">
                        <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">Test-E-Mail senden</button>
                    </form>
                </div>
            </div>
    </div>

    <?php
    $deploy_exists = file_exists(__DIR__ . '/deploy.php');
    $deploy_config = __DIR__ . '/includes/deploy-config.php';
    $deploy_token  = '';
    $github_version = null;
    if ($deploy_exists && file_exists($deploy_config)) {
        require_once $deploy_config;
        $deploy_token = defined('DEPLOY_TOKEN') ? DEPLOY_TOKEN : '';

        // GitHub-Version via cURL abrufen
        if (defined('GITHUB_OWNER') && defined('GITHUB_REPO') && function_exists('curl_init')) {
            $ch = curl_init('https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/main/includes/config.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_USERAGENT      => 'PHP-Deploy/1.0',
            ]);
            $remote = curl_exec($ch);
            unset($ch);
            if ($remote && preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $remote, $m)) {
                $github_version = $m[1];
            }
        }
    }
    ?>
    <?php if ($deploy_exists && $deploy_token !== ''): ?>
    <div class="mt-4">
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
                        <?php
                        $up_to_date = ($github_version !== null && $github_version === APP_VERSION);
                        ?>
                        <div class="text-muted fs-5"><?= $up_to_date ? '=' : '←' ?></div>
                        <div class="text-center">
                            <div class="text-muted small mb-1">GitHub (main)</div>
                            <?php if ($github_version !== null): ?>
                                <span class="badge fs-6 <?= $up_to_date ? 'bg-success' : 'bg-primary' ?>">
                                    v<?= htmlspecialchars($github_version) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6">unbekannt</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($up_to_date): ?>
                    <div class="text-success small mb-2">✓ Bereits auf dem neuesten Stand</div>
                    <?php endif; ?>
                    <a href="deploy.php?token=<?= urlencode($deploy_token) ?>" class="btn btn-sm btn-outline-primary">Deploy-Status öffnen</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-4">
        <div class="card">
            <div class="list-group list-group-flush">
                <div class="list-group-item bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">Debug</span>
                </div>
                <div class="list-group-item py-3">
                    <p class="text-muted small mb-2">Zeigt nach jeder Antwort in Leitner und Drill ein Panel mit dem Vorher/Nachher-Status der Karte (Fach, Fälligkeit, Zähler) — nur für Admins sichtbar.</p>
                    <form method="post" class="d-flex align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_debug">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="debug_mode" id="debug_mode" <?= $cur_debug_mode ? 'checked' : '' ?>>
                            <label class="form-check-label" for="debug_mode">Debug-Modus aktiv</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary ms-auto">Speichern</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
