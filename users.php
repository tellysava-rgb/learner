<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_admin();

$person_id   = $_SESSION['person_id'];
$error       = $_SESSION['flash_error']   ?? '';
$success     = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// --- POST-Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);
    $action = $_POST['action'] ?? '';

    // Neue Person anlegen
    if ($action === 'create_person') {
        $name       = trim($_POST['name'] ?? '');
        $password   = $_POST['password']  ?? '';
        $password2  = $_POST['password2'] ?? '';
        $email      = trim($_POST['email'] ?? '');
        $make_admin = isset($_POST['is_admin']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['flash_error'] = 'Name darf nicht leer sein.';
        } elseif (mb_strlen($password) < 8) {
            $_SESSION['flash_error'] = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($password !== $password2) {
            $_SESSION['flash_error'] = 'Die Passwörter stimmen nicht überein.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO persons (name, password_hash, email, is_admin) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $hash, $email !== '' ? $email : null, $make_admin]);
                $_SESSION['flash_success'] = 'Person „' . $name . '" wurde angelegt.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $_SESSION['flash_error'] = 'Dieser Name oder diese E-Mail-Adresse ist bereits vergeben.';
                } else {
                    $_SESSION['flash_error'] = 'Fehler beim Anlegen der Person.';
                }
            }
        }
        header('Location: users.php');
        exit;
    }

    // E-Mail-Adresse einer Person setzen/ändern
    if ($action === 'set_email') {
        $target_id = intval($_POST['person_id'] ?? 0);
        $email     = trim($_POST['email'] ?? '');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE persons SET email = ? WHERE id = ?");
                $stmt->execute([$email !== '' ? $email : null, $target_id]);
                $_SESSION['flash_success'] = 'E-Mail-Adresse gespeichert.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = $e->getCode() === '23000'
                    ? 'Diese E-Mail-Adresse wird bereits von einer anderen Person verwendet.'
                    : 'Fehler beim Speichern der E-Mail-Adresse.';
            }
        }
        header('Location: users.php');
        exit;
    }

    // Passwort einer Person zurücksetzen
    if ($action === 'reset_password') {
        $target_id = intval($_POST['person_id'] ?? 0);
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (mb_strlen($password) < 8) {
            $_SESSION['flash_error'] = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($password !== $password2) {
            $_SESSION['flash_error'] = 'Die Passwörter stimmen nicht überein.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE persons SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $target_id]);
            $_SESSION['flash_success'] = 'Passwort wurde zurückgesetzt.';
        }
        header('Location: users.php');
        exit;
    }

    // Person vollständig löschen (Listen, Karten, Fortschritt, Statistikdaten — per DB-Kaskade)
    if ($action === 'delete_person') {
        $target_id = intval($_POST['person_id'] ?? 0);
        $confirmed = ($_POST['confirm'] ?? '') === '1';

        $stmt = $pdo->prepare("SELECT name, is_admin FROM persons WHERE id = ?");
        $stmt->execute([$target_id]);
        $target = $stmt->fetch();

        if (!$target) {
            $_SESSION['flash_error'] = 'Person nicht gefunden.';
        } elseif ($target_id == $person_id) {
            $_SESSION['flash_error'] = 'Du kannst dich nicht selbst löschen.';
        } elseif (!$confirmed) {
            $_SESSION['flash_error'] = 'Löschung nicht bestätigt, Person wurde nicht gelöscht.';
        } elseif ((int) $target['is_admin'] === 1
            && (int) $pdo->query("SELECT COUNT(*) FROM persons WHERE is_admin = 1")->fetchColumn() <= 1) {
            $_SESSION['flash_error'] = 'Der letzte verbleibende Admin kann nicht gelöscht werden.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM persons WHERE id = ?")->execute([$target_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Person „' . $target['name'] . '" wurde vollständig gelöscht.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Fehler beim Löschen der Person.';
            }
        }
        header('Location: users.php');
        exit;
    }

    // Admin-Status umschalten
    if ($action === 'toggle_admin') {
        $target_id = intval($_POST['person_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT is_admin FROM persons WHERE id = ?");
        $stmt->execute([$target_id]);
        $target = $stmt->fetch();

        if (!$target) {
            $_SESSION['flash_error'] = 'Person nicht gefunden.';
        } elseif ((int) $target['is_admin'] === 1) {
            $admin_count = (int) $pdo->query("SELECT COUNT(*) FROM persons WHERE is_admin = 1")->fetchColumn();
            if ($admin_count <= 1) {
                $_SESSION['flash_error'] = 'Der letzte verbleibende Admin kann nicht entfernt werden.';
            } else {
                $pdo->prepare("UPDATE persons SET is_admin = 0 WHERE id = ?")->execute([$target_id]);
                $_SESSION['flash_success'] = 'Admin-Status entfernt.';
            }
        } else {
            $pdo->prepare("UPDATE persons SET is_admin = 1 WHERE id = ?")->execute([$target_id]);
            $_SESSION['flash_success'] = 'Admin-Status vergeben.';
        }
        header('Location: users.php');
        exit;
    }
}

// Alle Personen laden
$persons     = $pdo->query("SELECT id, name, email, is_admin FROM persons ORDER BY name")->fetchAll();
$admin_count = (int) $pdo->query("SELECT COUNT(*) FROM persons WHERE is_admin = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Benutzerverwaltung — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Benutzerverwaltung', '']]) ?></div>

<div class="container mt-2" style="max-width:760px;">

    <h1 class="h4 mb-4">Benutzerverwaltung</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($persons as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?><?= $p['id'] == $person_id ? ' <span class="text-muted small">(du)</span>' : '' ?></td>
                        <td class="small">
                            <?php if ($p['email']): ?>
                                <?= htmlspecialchars($p['email']) ?>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['is_admin']): ?>
                            <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Person</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#emailModal<?= $p['id'] ?>"
                                        title="E-Mail" aria-label="E-Mail"><i class="bi bi-envelope-plus"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#resetModal<?= $p['id'] ?>"
                                        title="Passwort zurücksetzen" aria-label="Passwort zurücksetzen"><i class="bi bi-key"></i></button>
                                <?php if ($p['is_admin'] && $admin_count <= 1): ?>
                                <button type="button" class="btn btn-sm invisible" tabindex="-1" aria-hidden="true"><i class="bi bi-person-dash"></i></button>
                                <?php else: ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                                    <?php if ($p['is_admin']): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                            title="Admin entfernen" aria-label="Admin entfernen"><i class="bi bi-person-dash"></i></button>
                                    <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                            title="Zu Admin machen" aria-label="Zu Admin machen"><i class="bi bi-person-lock"></i></button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                                <?php if ($p['id'] != $person_id): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal<?= $p['id'] ?>"
                                        title="Person löschen" aria-label="Person löschen"><i class="bi bi-trash"></i></button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm invisible" tabindex="-1" aria-hidden="true"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal: E-Mail-Adresse -->
                    <div class="modal fade" id="emailModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_email">
                            <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                            <div class="modal-header">
                              <h5 class="modal-title">E-Mail-Adresse — <?= htmlspecialchars($p['name']) ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                            </div>
                            <div class="modal-body">
                              <label class="form-label fw-medium">E-Mail-Adresse</label>
                              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($p['email'] ?? '') ?>" placeholder="name@beispiel.ch">
                              <div class="form-text">Optional — leer lassen, um sie zu entfernen. Wird für den eigenständigen Passwort-Reset benötigt.</div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                              <button type="submit" class="btn btn-primary">Speichern</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Modal: Passwort zurücksetzen -->
                    <div class="modal fade" id="resetModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                            <div class="modal-header">
                              <h5 class="modal-title">Passwort zurücksetzen — <?= htmlspecialchars($p['name']) ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                            </div>
                            <div class="modal-body">
                              <label class="form-label fw-medium">Neues Passwort</label>
                              <input type="password" name="password" class="form-control mb-2" autocomplete="new-password" minlength="8" required>
                              <label class="form-label fw-medium">Neues Passwort (Wiederholung)</label>
                              <input type="password" name="password2" class="form-control" autocomplete="new-password" required>
                              <div class="form-text">Min. 8 Zeichen</div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                              <button type="submit" class="btn btn-primary">Zurücksetzen</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <?php if ($p['id'] != $person_id): ?>
                    <!-- Modal: Person löschen -->
                    <div class="modal fade" id="deleteModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_person">
                            <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                            <div class="modal-header">
                              <h5 class="modal-title text-danger">Person löschen — <?= htmlspecialchars($p['name']) ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                            </div>
                            <div class="modal-body">
                              <p>Dies löscht <strong><?= htmlspecialchars($p['name']) ?></strong> unwiderruflich — inklusive aller eigenen Listen, Karten, Lernfortschritt und Statistikdaten. Diese Aktion kann nicht rückgängig gemacht werden.</p>
                              <div class="form-check">
                                <input type="checkbox" name="confirm" value="1" class="form-check-input" id="confirmDelete<?= $p['id'] ?>" required>
                                <label class="form-check-label" for="confirmDelete<?= $p['id'] ?>">
                                    Ich bin mir sicher, dass <strong><?= htmlspecialchars($p['name']) ?></strong> unwiderruflich gelöscht werden soll.
                                </label>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                              <button type="submit" class="btn btn-danger">Endgültig löschen</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Neue Person anlegen</div>
        <div class="card-body">
            <form method="post" class="row g-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_person">
                <div class="col-md-3">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Name" required maxlength="100">
                </div>
                <div class="col-md-3">
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Passwort (min. 8 Zeichen)" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="col-md-3">
                    <input type="password" name="password2" class="form-control form-control-sm" placeholder="Passwort (Wiederholung)" autocomplete="new-password" required>
                </div>
                <div class="col-md-3">
                    <input type="email" name="email" class="form-control form-control-sm" placeholder="E-Mail (optional)">
                </div>
                <div class="col-md-2 form-check">
                    <input type="checkbox" name="is_admin" class="form-check-input" id="new-is-admin">
                    <label class="form-check-label small" for="new-is-admin">Admin</label>
                </div>
                <div class="col-md-10">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Anlegen</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
