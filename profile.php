<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/levels.php';
require_person();

$person_id = $_SESSION['person_id'];
$error     = $_SESSION['flash_error']   ?? '';
$success   = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$valid_directions = ['a_to_b', 'b_to_a', 'mixed', 'random'];

// --- POST-Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo); // deckt nur noch logout/switch_to_person ab
    $action = $_POST['action'] ?? '';

    // Passwort ändern — 1:1 übernommen aus dem bisherigen handle_navbar_actions()-Block
    // (Modal wurde durch diese Seite ersetzt, siehe includes/auth.php).
    if ($action === 'change_own_password') {
        $cur_pw  = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
        $new_pw2 = $_POST['new_password2']    ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM persons WHERE id = ?");
        $stmt->execute([$person_id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($cur_pw, $hash)) {
            $_SESSION['flash_error'] = 'Aktuelles Passwort ist falsch.';
        } elseif (mb_strlen($new_pw) < 8) {
            $_SESSION['flash_error'] = 'Neues Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($new_pw !== $new_pw2) {
            $_SESSION['flash_error'] = 'Die neuen Passwörter stimmen nicht überein.';
        } else {
            $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE persons SET password_hash = ? WHERE id = ?")->execute([$new_hash, $person_id]);
            $_SESSION['flash_success'] = 'Passwort erfolgreich geändert.';
        }
        header('Location: profile.php');
        exit;
    }

    // E-Mail ändern — 1:1 übernommen aus dem bisherigen handle_navbar_actions()-Block.
    if ($action === 'change_own_email') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $pdo->prepare("UPDATE persons SET email = NULL WHERE id = ?")->execute([$person_id]);
            $_SESSION['flash_success'] = 'E-Mail-Adresse entfernt.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
        } else {
            try {
                $pdo->prepare("UPDATE persons SET email = ? WHERE id = ?")->execute([$email, $person_id]);
                $_SESSION['flash_success'] = 'E-Mail-Adresse gespeichert.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = $e->getCode() === '23000'
                    ? 'Diese E-Mail-Adresse wird bereits von einer anderen Person verwendet.'
                    : 'Fehler beim Speichern der E-Mail-Adresse.';
            }
        }
        header('Location: profile.php');
        exit;
    }

    if ($action === 'save_direction') {
        $direction = $_POST['default_direction'] ?? 'random';
        if (!in_array($direction, $valid_directions, true)) $direction = 'random';
        $pdo->prepare("UPDATE persons SET default_direction = ? WHERE id = ?")->execute([$direction, $person_id]);
        $_SESSION['flash_success'] = 'Standard-Lernrichtung gespeichert.';
        header('Location: profile.php');
        exit;
    }

    if ($action === 'add_level') {
        $language = trim($_POST['language'] ?? '');
        $cefr     = $_POST['cefr_level'] ?? 'A1';
        if ($language === '') {
            $_SESSION['flash_error'] = 'Sprache darf nicht leer sein.';
        } elseif (!in_array($cefr, CEFR_LEVELS, true)) {
            $_SESSION['flash_error'] = 'Ungültiges Niveau.';
        } else {
            set_person_language_level($pdo, $person_id, $language, $cefr);
            $_SESSION['flash_success'] = 'Sprachlevel gespeichert.';
        }
        header('Location: profile.php');
        exit;
    }

    if ($action === 'delete_level') {
        delete_person_language_level($pdo, $person_id, intval($_POST['level_id'] ?? 0));
        $_SESSION['flash_success'] = 'Sprachlevel entfernt.';
        header('Location: profile.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT name, email, default_direction FROM persons WHERE id = ?");
$stmt->execute([$person_id]);
$person = $stmt->fetch();

$levels = get_person_language_levels($pdo, $person_id);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Profil', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:720px;">

    <h1 class="h4 mb-4">Profil — <?= htmlspecialchars($person['name']) ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Konto -->
    <div class="card shadow-sm mb-3">
        <div class="card-header">Konto</div>
        <div class="card-body">
            <div class="mb-4 pb-4 border-bottom">
                <label class="form-label fw-medium">Benutzername</label>
                <!-- Nur Anzeige, nicht änderbar — dient als Login-Kennung, Umbenennen bleibt eine
                     Admin-Funktion (users.php). -->
                <p class="form-control-plaintext"><?= htmlspecialchars($person['name']) ?></p>
            </div>

            <form method="post" class="mb-4 pb-4 border-bottom">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_own_email">
                <label class="form-label fw-medium">E-Mail-Adresse</label>
                <div class="form-text mb-2">Optional — nur nötig, um das Passwort selbst per E-Mail zurücksetzen zu können.</div>
                <div class="d-flex gap-2">
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($person['email'] ?? '') ?>" placeholder="name@beispiel.ch">
                    <button type="submit" class="btn btn-outline-primary flex-shrink-0">Speichern</button>
                </div>
            </form>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_own_password">
                <label class="form-label fw-medium mb-2">Passwort ändern</label>
                <div class="mb-3">
                    <input type="password" name="current_password" class="form-control" placeholder="Aktuelles Passwort" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="new_password" class="form-control" placeholder="Neues Passwort (min. 8 Zeichen)" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="new_password2" class="form-control" placeholder="Neues Passwort (Wiederholung)" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary">Passwort ändern</button>
            </form>
        </div>
    </div>

    <!-- Sprachlevel -->
    <div class="card shadow-sm mb-3">
        <div class="card-header">Sprachlevel</div>
        <div class="card-body">
            <p class="text-muted small">Eigenes CEFR-Niveau pro Sprache — wird u.a. auf der Startseite sowie im Lernplan-Rechner und der Methoden-Skala berücksichtigt. Ohne Eintrag gilt A1 (Anfänger) als Standard.</p>

            <?php if ($levels): ?>
            <table class="table table-sm align-middle">
                <tbody>
                <?php foreach ($levels as $lvl): ?>
                <tr>
                    <td><?= htmlspecialchars($lvl['language']) ?></td>
                    <td><span class="badge bg-primary"><?= htmlspecialchars($lvl['cefr_level']) ?></span></td>
                    <td class="text-end">
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_level">
                            <input type="hidden" name="level_id" value="<?= $lvl['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Entfernen" aria-label="Entfernen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_level">
                <div class="col-auto">
                    <label class="form-label small mb-1" for="lvl-language">Sprache</label>
                    <input type="text" name="language" id="lvl-language" class="form-control form-control-sm" maxlength="50" placeholder="z.B. Englisch" required>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1" for="lvl-cefr">Niveau</label>
                    <select name="cefr_level" id="lvl-cefr" class="form-select form-select-sm">
                        <?php foreach (CEFR_LEVELS as $c): ?>
                        <option value="<?= $c ?>"><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <!-- Gleiche Sprache erneut eintragen überschreibt den bestehenden Wert (siehe
                         set_person_language_level) — dient zugleich als "Ändern". -->
                    <button type="submit" class="btn btn-sm btn-primary">Hinzufügen / Ändern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Standard-Lernrichtung -->
    <div class="card shadow-sm mb-3">
        <div class="card-header">Standard-Lernrichtung</div>
        <div class="card-body">
            <p class="text-muted small">Diese Richtung ist beim Öffnen von Leitner/Drill vorausgewählt (dort weiterhin pro Session änderbar).</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_direction">
                <div class="row row-cols-2 g-2 mb-3">
                    <div class="col">
                        <input type="radio" class="btn-check" name="default_direction" id="dd_ab" value="a_to_b" autocomplete="off" <?= $person['default_direction'] === 'a_to_b' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100" for="dd_ab">Sprache A → Sprache B</label>
                    </div>
                    <div class="col">
                        <input type="radio" class="btn-check" name="default_direction" id="dd_ba" value="b_to_a" autocomplete="off" <?= $person['default_direction'] === 'b_to_a' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100" for="dd_ba">Sprache B → Sprache A</label>
                    </div>
                    <div class="col">
                        <input type="radio" class="btn-check" name="default_direction" id="dd_mix" value="mixed" autocomplete="off" <?= $person['default_direction'] === 'mixed' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100" for="dd_mix">Gemischt</label>
                    </div>
                    <div class="col">
                        <input type="radio" class="btn-check" name="default_direction" id="dd_random" value="random" autocomplete="off" <?= $person['default_direction'] === 'random' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary w-100" for="dd_random">Zufall (Standard)</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
