<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tags.php';
require_person();

$person_id   = $_SESSION['person_id'];
$error       = '';
$success     = '';

// --- POST-Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    handle_navbar_actions($pdo);
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
                    INSERT INTO lists (person_id, name, description, language_a, language_b, is_public, speech_lang_b)
                    VALUES (?, ?, ?, ?, ?, 0, ?)
                ");
                $stmt->execute([
                    $person_id,
                    $source['name'],
                    $source['description'],
                    $source['language_a'],
                    $source['language_b'],
                    $source['speech_lang_b'],
                ]);
                $new_list_id = (int) $pdo->lastInsertId();

                // Alle Karten der Quellliste kopieren
                $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ?");
                $stmt->execute([$source_list_id]);
                $source_cards = $stmt->fetchAll();

                // Tags aller Quellkarten vorab in einer Abfrage laden (statt pro Karte einzeln) —
                // werden unten für die kopierende Person neu angelegt/verknüpft (Tags sind pro
                // Person eigenständig, siehe includes/tags.php).
                $stmt = $pdo->prepare("
                    SELECT ct.card_id, t.name
                    FROM card_tags ct
                    JOIN tags t ON t.id = ct.tag_id
                    WHERE ct.card_id IN (SELECT id FROM cards WHERE list_id = ?)
                ");
                $stmt->execute([$source_list_id]);
                $source_tags_by_card = [];
                foreach ($stmt->fetchAll() as $row) {
                    $source_tags_by_card[$row['card_id']][] = $row['name'];
                }

                $ins_card = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b, phonetic_b) VALUES (?,?,?,?,?,?)");
                $ins_prog = $pdo->prepare("INSERT INTO card_progress (person_id, card_id, status) VALUES (?,?,'queued')");

                foreach ($source_cards as $card) {
                    $ins_card->execute([$new_list_id, $card['word_a'], $card['word_b'], $card['desc_a'], $card['desc_b'], $card['phonetic_b']]);
                    $new_card_id = (int) $pdo->lastInsertId();
                    $ins_prog->execute([$person_id, $new_card_id]);
                    if (!empty($source_tags_by_card[$card['id']])) {
                        set_card_tags($pdo, $person_id, $new_card_id, $source_tags_by_card[$card['id']]);
                    }
                }

                $pdo->commit();
                $success = 'Liste "' . htmlspecialchars($source['name']) . '" wurde kopiert und ist jetzt in deinen Listen.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Fehler beim Kopieren der Liste.';
            }
        }
    }
}

$preview_list_id = intval($_GET['list_id'] ?? 0);

// Öffentliche Liste anzeigen (Vorschau) — ohne list_id wird stattdessen die Galerie weiter unten
// gerendert (siehe $gallery_lists).
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
        // Tags per korrelierter Subquery mitladen (Muster wie edit.php/export.php) — reine
        // Anzeige in der Vorschau, das Kopieren selbst übernimmt Tags bereits unabhängig davon.
        $stmt = $pdo->prepare("
            SELECT word_a, word_b, desc_a, desc_b, phonetic_b,
                   (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ' ')
                    FROM card_tags ct JOIN tags t ON t.id = ct.tag_id
                    WHERE ct.card_id = cards.id) AS tags
            FROM cards WHERE list_id = ? ORDER BY created_at LIMIT 200
        ");
        $stmt->execute([$preview_list_id]);
        $preview_cards = $stmt->fetchAll();
    }
}

// Galerie aller öffentlichen Listen (nur wenn keine list_id gewählt ist) — mit optionalem Filter
// nach Ausgangs-/Zielsprache. Bookmarkbar per GET, kein POST nötig.
$gallery_lists   = [];
$lang_a_options  = [];
$lang_b_options  = [];
$filter_lang_a   = '';
$filter_lang_b   = '';

if (!$preview_list_id) {
    $filter_lang_a = trim($_GET['lang_a'] ?? '');
    $filter_lang_b = trim($_GET['lang_b'] ?? '');

    $stmt = $pdo->prepare("SELECT DISTINCT language_a FROM lists WHERE is_public = 1 AND person_id != ? ORDER BY language_a");
    $stmt->execute([$person_id]);
    $lang_a_options = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT DISTINCT language_b FROM lists WHERE is_public = 1 AND person_id != ? ORDER BY language_b");
    $stmt->execute([$person_id]);
    $lang_b_options = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $where  = "l.is_public = 1 AND l.person_id != ?";
    $params = [$person_id];
    if ($filter_lang_a !== '') { $where .= " AND l.language_a = ?"; $params[] = $filter_lang_a; }
    if ($filter_lang_b !== '') { $where .= " AND l.language_b = ?"; $params[] = $filter_lang_b; }

    $stmt = $pdo->prepare("
        SELECT l.id, l.name, l.description, l.language_a, l.language_b,
               p.name AS owner_name, COUNT(c.id) AS card_count
        FROM lists l
        JOIN persons p ON p.id = l.person_id
        LEFT JOIN cards c ON c.list_id = l.id
        WHERE $where
        GROUP BY l.id
        ORDER BY l.name
    ");
    $stmt->execute($params);
    $gallery_lists = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entdecken — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Entdecken', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:960px;">

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
                            <?php if ($card['tags']): ?>
                            <br><?php foreach (explode(' ', $card['tags']) as $t): ?>
                            <span class="badge bg-light text-dark border">#<?= htmlspecialchars($t) ?></span>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($card['word_b']) ?>
                            <?php if ($card['phonetic_b']): ?>
                            <span class="text-muted small">[<?= htmlspecialchars($card['phonetic_b']) ?>]</span>
                            <?php endif; ?>
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

    <?php if ($preview_list_id && !$preview_list): ?>
        <div class="alert alert-warning">Liste nicht gefunden oder nicht öffentlich.</div>
    <?php endif; ?>

    <?php if (!$preview_list_id): ?>
    <!-- Galerie aller öffentlichen Listen -->
    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="lang_a" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Alle Ausgangssprachen</option>
                <?php foreach ($lang_a_options as $l): ?>
                <option value="<?= htmlspecialchars($l) ?>" <?= $filter_lang_a === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="lang_b" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Alle Zielsprachen</option>
                <?php foreach ($lang_b_options as $l): ?>
                <option value="<?= htmlspecialchars($l) ?>" <?= $filter_lang_b === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($filter_lang_a !== '' || $filter_lang_b !== ''): ?>
        <div class="col-auto">
            <a href="discover.php" class="btn btn-sm btn-outline-secondary">Filter zurücksetzen</a>
        </div>
        <?php endif; ?>
    </form>

    <?php if (!$gallery_lists): ?>
        <p class="text-muted">Keine öffentlichen Listen gefunden<?= ($filter_lang_a !== '' || $filter_lang_b !== '') ? ' für diese Filterauswahl' : '' ?>.</p>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
        <?php foreach ($gallery_lists as $list): ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-0 bg-light">
                <div class="card-body">
                    <h5 class="card-title h6"><?= htmlspecialchars($list['name']) ?></h5>
                    <?php if ($list['description']): ?>
                    <p class="card-text text-muted small"><?= htmlspecialchars($list['description']) ?></p>
                    <?php endif; ?>
                    <p class="small text-muted mb-0">
                        <?= htmlspecialchars($list['language_a']) ?> → <?= htmlspecialchars($list['language_b']) ?>
                        &nbsp;·&nbsp; <?= $list['card_count'] ?> Karten
                        &nbsp;·&nbsp; von <?= htmlspecialchars($list['owner_name']) ?>
                    </p>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3">
                    <a href="discover.php?list_id=<?= $list['id'] ?>" class="btn btn-sm btn-outline-primary">Vorschau</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
