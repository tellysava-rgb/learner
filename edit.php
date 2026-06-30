<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];
$error   = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Liste laden und Besitzer prüfen
$list_id = intval($_GET['list_id'] ?? $_POST['list_id'] ?? 0);
if (!$list_id) {
    header('Location: home.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM lists WHERE id = ? AND person_id = ?");
$stmt->execute([$list_id, $person_id]);
$list = $stmt->fetch();
if (!$list) {
    header('Location: home.php');
    exit;
}

$filter = $_GET['filter'] ?? 'all';

// --- POST-Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // Karte hinzufügen
    if ($action === 'add') {
        $word_a = trim($_POST['word_a'] ?? '');
        $word_b = trim($_POST['word_b'] ?? '');
        $desc_a = trim($_POST['desc_a'] ?? '');
        $desc_b = trim($_POST['desc_b'] ?? '');

        if ($word_a === '' || $word_b === '') {
            $error = 'Beide Sprachfelder sind Pflicht.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$list_id, $word_a, $word_b, $desc_a ?: null, $desc_b ?: null]);
                $card_id = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare("
                    INSERT INTO card_progress (person_id, card_id, status)
                    VALUES (?, ?, 'queued')
                    ON DUPLICATE KEY UPDATE status = status
                ");
                $stmt->execute([$person_id, $card_id]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Karte wurde hinzugefügt.';
                header("Location: edit.php?list_id={$list_id}&filter={$filter}");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Fehler beim Hinzufügen der Karte.';
            }
        }
    }

    // Karte bearbeiten
    if ($action === 'update') {
        $card_id = intval($_POST['card_id'] ?? 0);
        $word_a  = trim($_POST['word_a'] ?? '');
        $word_b  = trim($_POST['word_b'] ?? '');
        $desc_a  = trim($_POST['desc_a'] ?? '');
        $desc_b  = trim($_POST['desc_b'] ?? '');

        if ($word_a === '' || $word_b === '') {
            $error = 'Beide Sprachfelder sind Pflicht.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM cards WHERE id=? AND list_id=?");
            $stmt->execute([$card_id, $list_id]);
            if (!$stmt->fetch()) {
                $error = 'Karte nicht gefunden.';
            } else {
                $stmt = $pdo->prepare("UPDATE cards SET word_a=?, word_b=?, desc_a=?, desc_b=? WHERE id=? AND list_id=?");
                $stmt->execute([$word_a, $word_b, $desc_a ?: null, $desc_b ?: null, $card_id, $list_id]);
                $_SESSION['flash_success'] = 'Karte gespeichert.';
                header("Location: edit.php?list_id={$list_id}&filter={$filter}");
                exit;
            }
        }
    }

    // Karte löschen
    if ($action === 'delete') {
        $card_id = intval($_POST['card_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ? AND list_id = ?");
        $stmt->execute([$card_id, $list_id]);
        if ($stmt->rowCount() === 0) {
            $_SESSION['flash_error'] = 'Karte nicht gefunden.';
        } else {
            $_SESSION['flash_success'] = 'Karte wurde gelöscht.';
        }
        header("Location: edit.php?list_id={$list_id}&filter={$filter}");
        exit;
    }

    // Karte archivieren
    if ($action === 'archive') {
        $card_id = intval($_POST['card_id'] ?? 0);
        $stmt = $pdo->prepare("
            INSERT INTO card_progress (person_id, card_id, status)
            VALUES (?, ?, 'archived')
            ON DUPLICATE KEY UPDATE status = 'archived'
        ");
        $stmt->execute([$person_id, $card_id]);
        $_SESSION['flash_success'] = 'Karte wurde archiviert.';
        header("Location: edit.php?list_id={$list_id}&filter={$filter}");
        exit;
    }

    // Karte reaktivieren (archiviert → aktiv)
    if ($action === 'reactivate') {
        $card_id = intval($_POST['card_id'] ?? 0);
        $today   = today();
        $stmt = $pdo->prepare("
            INSERT INTO card_progress (person_id, card_id, status, leitner_box, next_due_date)
            VALUES (?, ?, 'active', 1, ?)
            ON DUPLICATE KEY UPDATE status = 'active', leitner_box = 1, next_due_date = ?
        ");
        $stmt->execute([$person_id, $card_id, $today, $today]);
        $_SESSION['flash_success'] = 'Karte wurde reaktiviert.';
        header("Location: edit.php?list_id={$list_id}&filter={$filter}");
        exit;
    }

    // Logout
    if ($action === 'logout') {
        logout();
    }
}

// Karten laden mit Fortschritt dieser Person
$stmt = $pdo->prepare("
    SELECT c.id, c.word_a, c.word_b, c.desc_a, c.desc_b, c.created_at,
           COALESCE(cp.status, 'queued') AS status,
           cp.leitner_box, cp.next_due_date, cp.drill_mastery
    FROM cards c
    LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = ?
    WHERE c.list_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$person_id, $list_id]);
$cards = $stmt->fetchAll();

// Edit-Formular: welche Karte?
$edit_card_id = intval($_GET['edit'] ?? 0);

$filtered_cards = match($filter) {
    'active'   => array_filter($cards, fn($c) => $c['status'] === 'active'),
    'queued'   => array_filter($cards, fn($c) => $c['status'] === 'queued'),
    'archived' => array_filter($cards, fn($c) => $c['status'] === 'archived'),
    default    => $cards,
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($list['name']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?= streak_badge() ?>
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="list_id" value="<?= $list_id ?>">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Meine Listen', 'lists.php'], [$list['name'], '']]) ?></div>

<div class="container mt-2" style="max-width:960px;">

    <div class="d-flex align-items-center gap-3 mb-1">
        <h1 class="h4 mb-0"><?= htmlspecialchars($list['name']) ?></h1>
        <span class="text-muted small"><?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?></span>
    </div>
    <div class="d-flex gap-2 mb-4">
        <a href="import.php?list_id=<?= $list_id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-upload me-1"></i>CSV Import</a>
        <a href="export.php?list_id=<?= $list_id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>CSV Export</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Neue Karte hinzufügen -->
    <div class="card mb-4">
        <div class="card-header">Neue Karte hinzufügen</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="list_id" value="<?= $list_id ?>">
                <div class="col-md-3">
                    <input type="text" name="word_a" class="form-control form-control-sm"
                           placeholder="<?= htmlspecialchars($list['language_a']) ?> *" required maxlength="500">
                </div>
                <div class="col-md-3">
                    <input type="text" name="word_b" class="form-control form-control-sm"
                           placeholder="<?= htmlspecialchars($list['language_b']) ?> *" required maxlength="500">
                </div>
                <div class="col-md-3">
                    <input type="text" name="desc_a" class="form-control form-control-sm"
                           placeholder="Beschreibung <?= htmlspecialchars($list['language_a']) ?>" maxlength="1000">
                </div>
                <div class="col-md-2">
                    <input type="text" name="desc_b" class="form-control form-control-sm"
                           placeholder="Beschreibung <?= htmlspecialchars($list['language_b']) ?>" maxlength="1000">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">+</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filter -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <?php
        $counts = ['all' => count($cards), 'active' => 0, 'queued' => 0, 'archived' => 0];
        foreach ($cards as $c) $counts[$c['status']]++;
        $filters = ['all' => 'Alle', 'active' => 'Aktiv', 'queued' => 'Warteschlange', 'archived' => 'Archiviert'];
        foreach ($filters as $key => $label):
        ?>
        <a href="edit.php?list_id=<?= $list_id ?>&filter=<?= $key ?>"
           class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= $label ?> <span class="badge bg-light text-dark ms-1"><?= $counts[$key] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Kartenliste -->
    <?php if (!$filtered_cards): ?>
        <p class="text-muted">Keine Karten in dieser Ansicht.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle small">
            <thead class="table-light">
                <tr>
                    <th><?= htmlspecialchars($list['language_a']) ?></th>
                    <th><?= htmlspecialchars($list['language_b']) ?></th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filtered_cards as $card): ?>
                <?php if ($edit_card_id === $card['id']): ?>
                <tr class="table-warning">
                    <td colspan="4">
                        <form method="post" class="row g-2 py-1 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="list_id" value="<?= $list_id ?>">
                            <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                            <div class="col">
                                <input type="text" name="word_a" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($card['word_a']) ?>" required>
                            </div>
                            <div class="col">
                                <input type="text" name="word_b" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($card['word_b']) ?>" required>
                            </div>
                            <div class="col">
                                <input type="text" name="desc_a" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($card['desc_a'] ?? '') ?>" placeholder="Beschreibung A">
                            </div>
                            <div class="col">
                                <input type="text" name="desc_b" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($card['desc_b'] ?? '') ?>" placeholder="Beschreibung B">
                            </div>
                            <div class="col-auto d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-success"
                                        data-bs-toggle="tooltip" title="Speichern"><i class="bi bi-check-lg"></i></button>
                                <a href="edit.php?list_id=<?= $list_id ?>&filter=<?= $filter ?>" class="btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="tooltip" title="Abbrechen"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($card['word_a']) ?></strong>
                        <?php if ($card['desc_a']): ?>
                        <br><span class="text-muted"><?= htmlspecialchars($card['desc_a']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($card['word_b']) ?>
                        <?php if ($card['desc_b']): ?>
                        <br><span class="text-muted"><?= htmlspecialchars($card['desc_b']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($card['status'] === 'active'): ?>
                            <span class="badge bg-success">Fach <?= $card['leitner_box'] ?></span>
                            <?php if ($card['drill_mastery'] > 0): ?>
                            <span class="badge bg-info text-dark">Drill <?= $card['drill_mastery'] ?>×</span>
                            <?php endif; ?>
                        <?php elseif ($card['status'] === 'queued'): ?>
                            <span class="badge bg-warning text-dark">Warteschlange</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Archiviert</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="edit.php?list_id=<?= $list_id ?>&edit=<?= $card['id'] ?>&filter=<?= $filter ?>"
                               class="btn btn-sm btn-outline-primary"
                               data-bs-toggle="tooltip" title="Bearbeiten"><i class="bi bi-pencil"></i></a>

                            <?php if ($card['status'] !== 'archived'): ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="list_id" value="<?= $list_id ?>">
                                <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="tooltip" title="Archivieren"><i class="bi bi-archive"></i></button>
                            </form>
                            <?php else: ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="list_id" value="<?= $list_id ?>">
                                <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="tooltip" title="Reaktivieren"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="tooltip" title="Löschen"
                                    onclick="confirmDelete(<?= $card['id'] ?>)"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<!-- Lösch-Bestätigungsformular -->
<form method="post" id="delete-form" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="list_id" value="<?= $list_id ?>">
    <input type="hidden" name="card_id" id="delete-card-id">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
});

(function() {
    const _key = 'edit_scroll_<?= $list_id ?>';
    const saved = sessionStorage.getItem(_key);
    if (saved !== null) {
        window.scrollTo(0, parseInt(saved, 10));
        sessionStorage.removeItem(_key);
    }
    document.addEventListener('submit', function() {
        sessionStorage.setItem(_key, window.scrollY);
    });
})();

function confirmDelete(id) {
    if (confirm('Karte wirklich löschen? Der Lernfortschritt dieser Karte geht verloren.')) {
        sessionStorage.setItem('edit_scroll_<?= $list_id ?>', window.scrollY);
        document.getElementById('delete-card-id').value = id;
        document.getElementById('delete-form').submit();
    }
}
</script>
</body>
</html>
