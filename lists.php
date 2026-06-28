<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];
$error       = '';
$success     = '';

// --- POST-Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // Liste erstellen
    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $language_a  = trim($_POST['language_a'] ?? '');
        $language_b  = trim($_POST['language_b'] ?? '');
        $is_public   = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '' || $language_a === '' || $language_b === '') {
            $error = 'Name und beide Sprachen sind Pflichtfelder.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO lists (person_id, name, description, language_a, language_b, is_public)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$person_id, $name, $description ?: null, $language_a, $language_b, $is_public]);
            $success = 'Liste "' . htmlspecialchars($name) . '" wurde erstellt.';
        }
    }

    // Liste umbenennen / bearbeiten
    if ($action === 'update') {
        $list_id     = intval($_POST['list_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $language_a  = trim($_POST['language_a'] ?? '');
        $language_b  = trim($_POST['language_b'] ?? '');
        $is_public   = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '' || $language_a === '' || $language_b === '') {
            $error = 'Name und beide Sprachen sind Pflichtfelder.';
        } else {
            // Nur eigene Listen dürfen bearbeitet werden
            $stmt = $pdo->prepare("UPDATE lists SET name=?, description=?, language_a=?, language_b=?, is_public=? WHERE id=? AND person_id=?");
            $stmt->execute([$name, $description ?: null, $language_a, $language_b, $is_public, $list_id, $person_id]);
            if ($stmt->rowCount() === 0) {
                $error = 'Liste nicht gefunden oder keine Berechtigung.';
            } else {
                $success = 'Liste gespeichert.';
            }
        }
    }

    // Liste löschen
    if ($action === 'delete') {
        $list_id = intval($_POST['list_id'] ?? 0);
        // Nur eigene Listen
        $stmt = $pdo->prepare("DELETE FROM lists WHERE id = ? AND person_id = ?");
        $stmt->execute([$list_id, $person_id]);
        if ($stmt->rowCount() === 0) {
            $error = 'Liste nicht gefunden oder keine Berechtigung.';
        } else {
            $success = 'Liste wurde gelöscht.';
        }
    }

    // Logout
    if ($action === 'logout') {
        logout();
    }
}

// Eigene Listen laden
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.description, l.language_a, l.language_b, l.is_public, l.created_at,
           COUNT(c.id) AS card_count
    FROM lists l
    LEFT JOIN cards c ON c.list_id = l.id
    WHERE l.person_id = ?
    GROUP BY l.id
    ORDER BY l.name
");
$stmt->execute([$person_id]);
$lists = $stmt->fetchAll();

// Bearbeitungsformular: welche Liste?
$edit_id = intval($_GET['edit'] ?? 0);
$edit_list = null;
if ($edit_id) {
    foreach ($lists as $l) {
        if ($l['id'] === $edit_id) {
            $edit_list = $l;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listen — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-4" style="max-width:800px;">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="home.php" class="btn btn-sm btn-outline-secondary">← Startseite</a>
        <h1 class="h4 mb-0">Meine Listen</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Neue Liste erstellen -->
    <div class="card mb-4">
        <div class="card-header">Neue Liste erstellen</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="200">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Beschreibung</label>
                    <input type="text" name="description" class="form-control" maxlength="500">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sprache A <span class="text-danger">*</span></label>
                    <input type="text" name="language_a" class="form-control" required maxlength="100" placeholder="z.B. Deutsch">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sprache B <span class="text-danger">*</span></label>
                    <input type="text" name="language_b" class="form-control" required maxlength="100" placeholder="z.B. Englisch">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_public" id="create_public">
                        <label class="form-check-label" for="create_public">Öffentlich</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Liste erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bestehende Listen -->
    <?php if (!$lists): ?>
        <p class="text-muted">Noch keine Listen vorhanden.</p>
    <?php else: ?>
    <div class="list-group">
        <?php foreach ($lists as $list): ?>
        <div class="list-group-item">

            <?php if ($edit_list && $edit_list['id'] === $list['id']): ?>
            <!-- Bearbeitungsformular inline -->
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                <div class="col-md-5">
                    <input type="text" name="name" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($list['name']) ?>" required maxlength="200">
                </div>
                <div class="col-md-5">
                    <input type="text" name="description" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($list['description'] ?? '') ?>" maxlength="500" placeholder="Beschreibung">
                </div>
                <div class="col-md-3">
                    <input type="text" name="language_a" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($list['language_a']) ?>" required maxlength="100">
                </div>
                <div class="col-md-3">
                    <input type="text" name="language_b" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($list['language_b']) ?>" required maxlength="100">
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" id="pub_<?= $list['id'] ?>"
                               <?= $list['is_public'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="pub_<?= $list['id'] ?>">Öffentlich</label>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-success">Speichern</button>
                    <a href="lists.php" class="btn btn-sm btn-outline-secondary">Abbrechen</a>
                </div>
            </form>

            <?php else: ?>
            <!-- Normale Ansicht -->
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong><?= htmlspecialchars($list['name']) ?></strong>
                    <?php if (!$list['is_public']): ?>
                    <span class="badge bg-secondary ms-1 small">privat</span>
                    <?php else: ?>
                    <span class="badge bg-success ms-1 small">öffentlich</span>
                    <?php endif; ?>
                    <br>
                    <span class="small text-muted">
                        <?= htmlspecialchars($list['language_a']) ?> → <?= htmlspecialchars($list['language_b']) ?>
                        · <?= $list['card_count'] ?> Karte<?= $list['card_count'] != 1 ? 'n' : '' ?>
                        <?php if ($list['description']): ?>
                        · <?= htmlspecialchars($list['description']) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-1 ms-2">
                    <a href="edit.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-primary">Karten</a>
                    <a href="import.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary">Import</a>
                    <a href="export.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary">Export</a>
                    <a href="lists.php?edit=<?= $list['id'] ?>" class="btn btn-sm btn-outline-secondary">Umbenennen</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="confirmDelete(<?= $list['id'] ?>, '<?= htmlspecialchars(addslashes($list['name'])) ?>')">
                        Löschen
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Lösch-Bestätigungsformular (versteckt) -->
<form method="post" id="delete-form" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="list_id" id="delete-list-id">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) {
    if (confirm('Liste "' + name + '" wirklich löschen? Alle Karten und Lernfortschritte werden unwiderruflich gelöscht.')) {
        document.getElementById('delete-list-id').value = id;
        document.getElementById('delete-form').submit();
    }
}
</script>
</body>
</html>
