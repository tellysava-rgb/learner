<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];
$error       = '';
$success     = '';
$warning     = '';

// Formularwerte (Defaults)
$type      = 'multiplication';
$from      = 1;
$to        = 10;
$list_name = '';

if (($_POST['action'] ?? '') === 'logout') {
    csrf_validate();
    logout();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    csrf_validate();

    $type      = $_POST['type'] ?? 'multiplication';
    $from      = max(1, intval($_POST['from'] ?? 1));
    $to        = min(20, intval($_POST['to'] ?? 10));
    $list_name = trim($_POST['list_name'] ?? '');

    if ($from > $to) {
        $error = 'Von-Wert muss kleiner oder gleich Bis-Wert sein.';
    } elseif (!$list_name) {
        $error = 'Bitte gib einen Listennamen ein.';
    } else {
        // Typ-basierter Duplikat-Check
        $desc_prefix = ($type === 'multiplication') ? 'Multiplikation%' : 'Division%';
        $stmt_check = $pdo->prepare("SELECT id, name FROM lists WHERE person_id = ? AND description LIKE ?");
        $stmt_check->execute([$person_id, $desc_prefix]);
        $existing = $stmt_check->fetch();

        if ($existing && !($_POST['confirmed'] ?? false)) {
            $type_label = ($type === 'multiplication') ? 'Multiplikations' : 'Divisions';
            $warning = 'Es existiert bereits eine ' . $type_label . 'liste: "' . htmlspecialchars($existing['name']) . '". Möchtest du trotzdem eine neue erstellen?';
        }
    }

    if (!$error && !$warning) {
        $pdo->beginTransaction();
        try {
            if ($type === 'multiplication') {
                $lang_a = 'Aufgabe';
                $lang_b = 'Ergebnis';
                $stmt_list = $pdo->prepare("INSERT INTO lists (person_id, name, description, language_a, language_b, is_public) VALUES (?,?,?,?,?,0)");
                $stmt_list->execute([$person_id, $list_name, "Multiplikation {$from}×{$from} bis {$to}×{$to}", $lang_a, $lang_b]);
                $list_id = (int) $pdo->lastInsertId();

                $ins_card = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b) VALUES (?,?,?)");
                $ins_prog = $pdo->prepare("INSERT INTO card_progress (person_id, card_id, status) VALUES (?,?,'queued')");

                for ($a = $from; $a <= $to; $a++) {
                    for ($b = $from; $b <= $to; $b++) {
                        $ins_card->execute([$list_id, "{$a} × {$b} = ?", (string)($a * $b)]);
                        $ins_prog->execute([$person_id, (int) $pdo->lastInsertId()]);
                    }
                }
                $count = ($to - $from + 1) ** 2;
                $success = "Multiplikationstabelle erstellt: $count Karten in der Liste \"$list_name\".";

            } else {
                $lang_a = 'Aufgabe';
                $lang_b = 'Ergebnis';
                $stmt_list = $pdo->prepare("INSERT INTO lists (person_id, name, description, language_a, language_b, is_public) VALUES (?,?,?,?,?,0)");
                $stmt_list->execute([$person_id, $list_name, "Division {$from}÷{$from} bis {$to}÷{$to}", $lang_a, $lang_b]);
                $list_id = (int) $pdo->lastInsertId();

                $ins_card = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b) VALUES (?,?,?)");
                $ins_prog = $pdo->prepare("INSERT INTO card_progress (person_id, card_id, status) VALUES (?,?,'queued')");

                $count = 0;
                for ($a = $from; $a <= $to; $a++) {
                    for ($b = $from; $b <= $to; $b++) {
                        $product = $a * $b;
                        $ins_card->execute([$list_id, "{$product} ÷ {$a} = ?", (string)$b]);
                        $ins_prog->execute([$person_id, (int) $pdo->lastInsertId()]);
                        $count++;
                    }
                }
                $success = "Divisionstabelle erstellt: $count Karten in der Liste \"$list_name\".";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Fehler beim Erstellen der Liste.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mathe-Generator — <?= APP_NAME ?></title>
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

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Meine Listen', 'lists.php'], ['Mathe-Generator', '']]) ?></div>

<div class="container mt-2" style="max-width:960px;">

    <h1 class="h4 mb-4">Mathe-Generator</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Aufgaben generieren</div>
        <div class="card-body">

            <p class="text-muted small mb-4">
                Generiert eine Karten-Liste für Multiplikation oder Division.
                Die Karten erscheinen normal in Leitner und Drill.
                Zu einfache Karten (z.B. 1×1) können in der Kartenansicht einzeln archiviert werden.
            </p>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Typ</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type" id="type_mul" value="multiplication"
                                   <?= $type === 'multiplication' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="type_mul">Multiplikation (×)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type" id="type_div" value="division"
                                   <?= $type === 'division' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="type_div">Division (÷)</label>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Von</label>
                        <input type="number" name="from" class="form-control" value="<?= $from ?>" min="1" max="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Bis</label>
                        <input type="number" name="to" class="form-control" value="<?= $to ?>" min="1" max="20">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Listenname</label>
                    <input type="text" name="list_name" class="form-control"
                           value="<?= htmlspecialchars($list_name) ?>"
                           placeholder="z.B. Einmaleins 1-10" required maxlength="200">
                </div>

                <?php if ($warning): ?>
                <div class="alert alert-warning mb-3">
                    <?= $warning ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="confirmed" id="confirmed" value="1" required>
                        <label class="form-check-label" for="confirmed">Ja, trotzdem neue Liste erstellen</label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="alert alert-light border small mb-4">
                    <strong>Beispiel (Multiplikation 1–3):</strong><br>
                    1 × 1 = ? → 1<br>
                    1 × 2 = ? → 2<br>
                    2 × 3 = ? → 6<br>
                    <strong>Beispiel (Division 1–3):</strong><br>
                    2 ÷ 1 = ? → 2<br>
                    6 ÷ 2 = ? → 3
                </div>

                <button type="submit" class="btn btn-primary">Liste generieren</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
