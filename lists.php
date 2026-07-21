<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];
$error   = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

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
        $speech_raw  = trim($_POST['speech_lang_b'] ?? '');
        $speech_lang_b = $speech_raw !== '' ? normalize_speech_lang($speech_raw) : null;

        if ($name === '' || $language_a === '' || $language_b === '') {
            $error = 'Name und beide Sprachen sind Pflichtfelder.';
        } elseif ($speech_raw !== '' && $speech_lang_b === null) {
            $error = 'Ungültiger Aussprache-Sprachcode. Format: Sprache-Region, z.B. en-GB.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO lists (person_id, name, description, language_a, language_b, is_public, speech_lang_b)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$person_id, $name, $description ?: null, $language_a, $language_b, $is_public, $speech_lang_b]);
            $_SESSION['flash_success'] = 'Liste "' . $name . '" wurde erstellt.';
            header('Location: lists.php');
            exit;
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
        $speech_raw  = trim($_POST['speech_lang_b'] ?? '');
        $speech_lang_b = $speech_raw !== '' ? normalize_speech_lang($speech_raw) : null;

        if ($name === '' || $language_a === '' || $language_b === '') {
            $error = 'Name und beide Sprachen sind Pflichtfelder.';
        } elseif ($speech_raw !== '' && $speech_lang_b === null) {
            $error = 'Ungültiger Aussprache-Sprachcode. Format: Sprache-Region, z.B. en-GB.';
        } else {
            $stmt = $pdo->prepare("UPDATE lists SET name=?, description=?, language_a=?, language_b=?, is_public=?, speech_lang_b=? WHERE id=? AND person_id=?");
            $stmt->execute([$name, $description ?: null, $language_a, $language_b, $is_public, $speech_lang_b, $list_id, $person_id]);
            if ($stmt->rowCount() === 0) {
                $_SESSION['flash_error'] = 'Liste nicht gefunden oder keine Berechtigung.';
            } else {
                $_SESSION['flash_success'] = 'Liste gespeichert.';
            }
            header('Location: lists.php');
            exit;
        }
    }

    // Liste migrieren (alle Karten inkl. Fortschritt in eine andere eigene Liste verschieben)
    if ($action === 'migrate') {
        $source_id = intval($_POST['source_list_id'] ?? 0);
        $target_id = intval($_POST['target_list_id'] ?? 0);

        if (!$source_id || !$target_id || $source_id === $target_id) {
            $_SESSION['flash_error'] = 'Ungültige Auswahl.';
        } else {
            // Beide Listen müssen der aktuellen Person gehören
            $stmt = $pdo->prepare("SELECT id FROM lists WHERE id IN (?, ?) AND person_id = ?");
            $stmt->execute([$source_id, $target_id, $person_id]);
            if (count($stmt->fetchAll()) !== 2) {
                $_SESSION['flash_error'] = 'Liste nicht gefunden oder keine Berechtigung.';
            } else {
                // card_progress hängt an card_id, nicht an list_id — bleibt beim Verschieben unverändert erhalten
                $stmt = $pdo->prepare("UPDATE cards SET list_id = ? WHERE list_id = ?");
                $stmt->execute([$target_id, $source_id]);
                $moved = $stmt->rowCount();
                $_SESSION['flash_success'] = $moved . ' Karte' . ($moved != 1 ? 'n' : '') . ' migriert.';
            }
        }
        header('Location: lists.php');
        exit;
    }

    // Liste löschen
    if ($action === 'delete') {
        $list_id = intval($_POST['list_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM lists WHERE id = ? AND person_id = ?");
        $stmt->execute([$list_id, $person_id]);
        if ($stmt->rowCount() === 0) {
            $_SESSION['flash_error'] = 'Liste nicht gefunden oder keine Berechtigung.';
        } else {
            $_SESSION['flash_success'] = 'Liste wurde gelöscht.';
        }
        header('Location: lists.php');
        exit;
    }

    // Logout
    if ($action === 'logout') {
        logout();
    }
}

// Eigene Listen laden
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.description, l.language_a, l.language_b, l.is_public, l.created_at, l.speech_lang_b,
           COUNT(c.id) AS card_count
    FROM lists l
    LEFT JOIN cards c ON c.list_id = l.id
    WHERE l.person_id = ?
    GROUP BY l.id
    ORDER BY l.name
");
$stmt->execute([$person_id]);
$lists = $stmt->fetchAll();

// Vorschläge für Aussprache-Sprachcode-Datalist: kuratierte Codes + bereits verwendete Codes
$common_speech_langs = ['de-DE','de-CH','de-AT','en-US','en-GB','en-AU','en-CA','fr-FR','fr-CH','fr-CA','it-IT','es-ES','es-MX','pt-PT','pt-BR','nl-NL','pl-PL','ru-RU','ja-JP','zh-CN'];
$used_speech_langs = $pdo->query("SELECT DISTINCT speech_lang_b FROM lists WHERE speech_lang_b IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$speech_lang_options = array_unique(array_merge($common_speech_langs, $used_speech_langs));
sort($speech_lang_options);

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
            <?= streak_badge() ?>
            <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-light">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Meine Listen', '']]) ?></div>

<div class="container mt-2">

    <div class="d-flex align-items-center gap-3 mb-4">
        <h1 class="h4 mb-0 me-auto">Meine Listen</h1>
        <a href="math.php" class="btn btn-sm btn-outline-secondary">Mathe-Generator</a>
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
                <div class="col-md-6">
                    <label class="form-label">Aussprache-Sprachcode (Sprache B)</label>
                    <input type="text" name="speech_lang_b" class="form-control" list="speech-lang-options" maxlength="10" placeholder="z.B. en-US">
                    <div class="form-text">BCP-47-Format, z.B. <code>en-US</code>, <code>fr-FR</code>, <code>de-CH</code>. Optional — aktiviert den 🔊-Button beim Lernen.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Liste erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <datalist id="speech-lang-options">
        <?php foreach ($speech_lang_options as $code): ?>
        <option value="<?= htmlspecialchars($code) ?>">
        <?php endforeach; ?>
    </datalist>

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
                <div class="col-md-4">
                    <input type="text" name="speech_lang_b" class="form-control form-control-sm"
                           list="speech-lang-options" maxlength="10" placeholder="Aussprache-Code, z.B. en-US"
                           value="<?= htmlspecialchars($list['speech_lang_b'] ?? '') ?>">
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
                    <?php if (count($lists) > 1): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#migrateModal<?= $list['id'] ?>">
                        Migrieren
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="confirmDelete(<?= $list['id'] ?>, <?= htmlspecialchars(json_encode($list['name'])) ?>)">
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

<!-- Migrations-Modals (eines pro Liste) -->
<?php foreach ($lists as $list): if (count($lists) <= 1) break; ?>
<div class="modal fade" id="migrateModal<?= $list['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" onsubmit="return confirmMigrate(this)">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="migrate">
        <input type="hidden" name="source_list_id" value="<?= $list['id'] ?>">
        <input type="hidden" name="source_lang" value="<?= htmlspecialchars($list['language_a'] . ' → ' . $list['language_b']) ?>">
        <div class="modal-header">
          <h5 class="modal-title">"<?= htmlspecialchars($list['name']) ?>" migrieren</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">
            Alle Karten von "<?= htmlspecialchars($list['name']) ?>" werden inkl. Lernfortschritt (Leitner-Fach, Drill-Status) in die Zielliste verschoben.
            "<?= htmlspecialchars($list['name']) ?>" bleibt danach leer bestehen und kann bei Bedarf manuell gelöscht werden.
          </p>
          <label class="form-label">Zielliste</label>
          <select name="target_list_id" class="form-select" required>
            <?php foreach ($lists as $other): if ($other['id'] === $list['id']) continue; ?>
            <option value="<?= $other['id'] ?>" data-lang="<?= htmlspecialchars($other['language_a'] . ' → ' . $other['language_b']) ?>">
                <?= htmlspecialchars($other['name']) ?> (<?= htmlspecialchars($other['language_a']) ?> → <?= htmlspecialchars($other['language_b']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">Migrieren</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

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

function confirmMigrate(form) {
    var select     = form.querySelector('select[name="target_list_id"]');
    var targetLang = select.options[select.selectedIndex].dataset.lang;
    var sourceLang = form.querySelector('input[name="source_lang"]').value;
    if (targetLang !== sourceLang) {
        return confirm('Die Sprachpaare unterscheiden sich (' + sourceLang + ' vs. ' + targetLang + '). Trotzdem migrieren?');
    }
    return true;
}
</script>
</body>
</html>
