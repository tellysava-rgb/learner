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

    // Liste kopieren
    if ($action === 'copy') {
        $source_list_id = intval($_POST['source_list_id'] ?? 0);

        // Quellliste laden — muss öffentlich und einer anderen Person gehören
        $stmt = $pdo->prepare("SELECT * FROM lists WHERE id = ? AND is_public = 1 AND person_id != ?");
        $stmt->execute([$source_list_id, $person_id]);
        $source = $stmt->fetch();

        if (!$source) {
            $error = 'Liste nicht gefunden oder nicht öffentlich.';
        } else {
            $pdo->beginTransaction();
            try {
                // Neue Liste für diese Person erstellen
                $stmt = $pdo->prepare("
                    INSERT INTO lists (person_id, name, description, language_a, language_b, is_public)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $person_id,
                    $source['name'],
                    $source['description'],
                    $source['language_a'],
                    $source['language_b'],
                ]);
                $new_list_id = (int) $pdo->lastInsertId();

                // Alle Karten der Quellliste kopieren
                $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ?");
                $stmt->execute([$source_list_id]);
                $source_cards = $stmt->fetchAll();

                $ins_card = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b) VALUES (?,?,?,?,?)");
                $ins_prog = $pdo->prepare("INSERT INTO card_progress (person_id, card_id, status) VALUES (?,?,'queued')");

                foreach ($source_cards as $card) {
                    $ins_card->execute([$new_list_id, $card['word_a'], $card['word_b'], $card['desc_a'], $card['desc_b']]);
                    $new_card_id = (int) $pdo->lastInsertId();
                    $ins_prog->execute([$person_id, $new_card_id]);
                }

                $pdo->commit();
                $success = 'Liste "' . htmlspecialchars($source['name']) . '" wurde kopiert und ist jetzt in deinen Listen.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Fehler beim Kopieren der Liste.';
            }
        }
    }

    if ($action === 'logout') {
        logout();
    }
}

// Ohne list_id direkt zur Startseite
$preview_list_id = intval($_GET['list_id'] ?? 0);
if (!$preview_list_id) {
    header('Location: home.php');
    exit;
}

// Öffentliche Liste anzeigen (Vorschau)
$preview_list  = null;
$preview_cards = [];

if ($preview_list_id) {
    $stmt = $pdo->prepare("
        SELECT l.*, p.name AS owner_name, COUNT(c.id) AS card_count
        FROM lists l
        JOIN persons p ON p.id = l.person_id
        LEFT JOIN cards c ON c.list_id = l.id
        WHERE l.id = ? AND l.is_public = 1 AND l.person_id != ?
        GROUP BY l.id
    ");
    $stmt->execute([$preview_list_id, $person_id]);
    $preview_list = $stmt->fetch();

    if ($preview_list) {
        $stmt = $pdo->prepare("SELECT word_a, word_b, desc_a, desc_b FROM cards WHERE list_id = ? ORDER BY created_at LIMIT 200");
        $stmt->execute([$preview_list_id]);
        $preview_cards = $stmt->fetchAll();
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entdecken — <?= APP_NAME ?></title>
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

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Entdecken', '']]) ?></div>

<div class="container mt-2" style="max-width:900px;">

    <h1 class="h4 mb-4">Öffentliche Listen entdecken</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($preview_list): ?>
    <!-- Listenvorschau -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong><?= htmlspecialchars($preview_list['name']) ?></strong>
                <span class="text-muted ms-2 small">von <?= htmlspecialchars($preview_list['owner_name']) ?></span>
            </div>
            <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="copy">
                <input type="hidden" name="source_list_id" value="<?= $preview_list['id'] ?>">
                <button type="submit" class="btn btn-success btn-sm">Kopieren & als eigene Liste übernehmen</button>
            </form>
        </div>
        <div class="card-body">
            <?php if ($preview_list['description']): ?>
            <p class="text-muted"><?= htmlspecialchars($preview_list['description']) ?></p>
            <?php endif; ?>
            <p class="small mb-3">
                <?= htmlspecialchars($preview_list['language_a']) ?> → <?= htmlspecialchars($preview_list['language_b']) ?>
                · <?= $preview_list['card_count'] ?> Karten
            </p>

            <div class="table-responsive">
                <table class="table table-sm small">
                    <thead class="table-light">
                        <tr>
                            <th><?= htmlspecialchars($preview_list['language_a']) ?></th>
                            <th><?= htmlspecialchars($preview_list['language_b']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($preview_cards as $card): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($card['word_a']) ?>
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
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$preview_list): ?>
        <div class="alert alert-warning">Liste nicht gefunden oder nicht öffentlich.</div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
