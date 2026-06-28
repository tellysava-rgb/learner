<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
$person_name = $_SESSION['person_name'];
$error       = '';
$success     = '';

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

// Abbrechen: Session leeren und zurück zum Upload
if (isset($_GET['cancel'])) {
    unset($_SESSION['import']);
    header('Location: import.php?list_id=' . $list_id);
    exit;
}

// Status aus Session (für mehrstufigen Import-Prozess)
$import_stage = $_POST['stage'] ?? 'upload'; // upload → review → confirm

// -------------------------------------------------------
// Hilfsfunktionen
// -------------------------------------------------------

function normalize(string $s): string {
    return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
}

function parse_csv(string $content): array {
    // Trennzeichen erkennen: Semikolon dominiert wenn mehr Semikolons als Kommas
    $first_lines = implode("\n", array_slice(explode("\n", $content), 0, 5));
    $sep = substr_count($first_lines, ';') >= substr_count($first_lines, ',') ? ';' : ',';

    $lines = array_filter(explode("\n", str_replace("\r", '', $content)), fn($l) => trim($l) !== '');
    $lines = array_values($lines);

    $rows = [];
    $header_found = false;

    foreach ($lines as $line) {
        // Kommentarzeilen überspringen (Export-Dokumentation)
        if (str_starts_with(ltrim($line), '#')) continue;

        $fields = str_getcsv($line, $sep, '"', '\\');

        if (!$header_found) {
            // Erste Nicht-Kommentar-Zeile ist immer die Kopfzeile (Sprachnamen oder a,b,...)
            $header_found = true;
            continue;
        }

        if (count($fields) < 2) continue;
        $rows[] = [
            'word_a' => trim($fields[0] ?? ''),
            'word_b' => trim($fields[1] ?? ''),
            'desc_a' => trim($fields[2] ?? ''),
            'desc_b' => trim($fields[3] ?? ''),
        ];
    }
    return $rows;
}

// -------------------------------------------------------
// STUFE 1: CSV hochladen & parsen
// -------------------------------------------------------
$parsed_rows = [];

if ($import_stage === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $file = $_FILES['csv_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Bitte wähle eine CSV-Datei aus.';
        $import_stage = 'upload';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $error = 'Die Datei ist zu gross (max. 2MB).';
        $import_stage = 'upload';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Nur .csv-Dateien sind erlaubt.';
        $import_stage = 'upload';
    } else {
        $content = file_get_contents($file['tmp_name']);
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $parsed_rows = parse_csv($content);

        if (!$parsed_rows) {
            $error = 'Die Datei enthält keine lesbaren Karten. Bitte prüfe das Format (Kopfzeile vorhanden, mindestens 2 Spalten?).';
            $import_stage = 'upload';
        } else {
            $_SESSION['import'] = [
                'list_id' => $list_id,
                'rows'    => $parsed_rows,
            ];
            header('Location: import.php?list_id=' . $list_id . '&stage=review');
            exit;
        }
    }
}

// -------------------------------------------------------
// STUFE 2: Duplikat-Review anzeigen
// -------------------------------------------------------
$import_data       = [];
$duplicates        = [];
$archived_matches  = [];
$clean_rows        = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stage']) && $_GET['stage'] === 'review' && isset($_SESSION['import'])) {
    $import_stage = 'review';
    $import_data  = $_SESSION['import'];

    if ($import_data['list_id'] !== $list_id) {
        unset($_SESSION['import']);
        header('Location: import.php?list_id=' . $list_id);
        exit;
    }

    $parsed_rows = $import_data['rows'];

    // Bestehende Karten aller Listen dieser Person laden (für listenübergreifenden Duplikat-Check)
    $stmt = $pdo->prepare("
        SELECT c.id, c.word_a, c.word_b, l.name AS list_name,
               COALESCE(cp.status, 'queued') AS status
        FROM cards c
        JOIN lists l ON l.id = c.list_id
        LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = ?
        WHERE l.person_id = ?
    ");
    $stmt->execute([$person_id, $person_id]);
    $existing = $stmt->fetchAll();

    // Normalisierte Map für Duplikat-Check
    $existing_map = [];
    foreach ($existing as $ex) {
        $key = normalize(strip_tags($ex['word_a'])) . '|||' . normalize(strip_tags($ex['word_b']));
        $existing_map[$key][] = $ex;
    }

    foreach ($parsed_rows as $i => $row) {
        if (!$row['word_a'] || !$row['word_b']) continue;
        $key = normalize($row['word_a']) . '|||' . normalize($row['word_b']);

        if (isset($existing_map[$key])) {
            $hits = $existing_map[$key];
            $archived_hit = null;
            foreach ($hits as $hit) {
                if ($hit['status'] === 'archived') $archived_hit = $hit;
            }
            if ($archived_hit) {
                $archived_matches[$i] = ['row' => $row, 'match' => $archived_hit];
            } else {
                $duplicates[$i] = ['row' => $row, 'matches' => $hits];
            }
        } else {
            $clean_rows[$i] = $row;
        }
    }
}

// -------------------------------------------------------
// STUFE 3: Import bestätigen & durchführen
// -------------------------------------------------------
if ($import_stage === 'confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    if (!isset($_SESSION['import']) || $_SESSION['import']['list_id'] !== $list_id) {
        $error = 'Session abgelaufen. Bitte erneut importieren.';
        $import_stage = 'upload';
    } else {
        $import_data = $_SESSION['import'];
        $parsed_rows = $import_data['rows'];

        // Entscheidungen aus Formular lesen
        $dup_action      = $_POST['dup_action'] ?? 'skip';      // skip | import
        $dup_exceptions  = array_map('intval', (array)($_POST['dup_exceptions'] ?? []));
        $archived_decisions = $_POST['archived'] ?? [];          // [index => 'keep'|'reactivate'|'new']

        // Bestehende Karten aller Listen dieser Person laden (Duplikat-Check wiederholen)
        $stmt = $pdo->prepare("
            SELECT c.id, c.word_a, c.word_b,
                   COALESCE(cp.status, 'queued') AS status
            FROM cards c
            JOIN lists l ON l.id = c.list_id
            LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = ?
            WHERE l.person_id = ?
        ");
        $stmt->execute([$person_id, $person_id]);
        $existing = $stmt->fetchAll();

        $existing_map = [];
        foreach ($existing as $ex) {
            $key = normalize(strip_tags($ex['word_a'])) . '|||' . normalize(strip_tags($ex['word_b']));
            $existing_map[$key][] = $ex;
        }

        $today        = today();
        $imported     = 0;

        $pdo->beginTransaction();
        try {
            foreach ($parsed_rows as $i => $row) {
                if (!$row['word_a'] || !$row['word_b']) continue;
                $key = normalize($row['word_a']) . '|||' . normalize($row['word_b']);

                if (isset($existing_map[$key])) {
                    $hits = $existing_map[$key];
                    $archived_hit = null;
                    foreach ($hits as $hit) {
                        if ($hit['status'] === 'archived') $archived_hit = $hit;
                    }

                    if ($archived_hit) {
                        // Archivierte Karte — Entscheidung aus Formular
                        $decision = $archived_decisions[$i] ?? 'keep';

                        if ($decision === 'reactivate') {
                            $stmt = $pdo->prepare("UPDATE card_progress SET status='active', leitner_box=1, next_due_date=? WHERE person_id=? AND card_id=?");
                            $stmt->execute([$today, $person_id, $archived_hit['id']]);
                            $imported++;
                        } elseif ($decision === 'new') {
                            // Neue Karte mit neuer ID
                            $stmt = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b) VALUES (?,?,?,?,?)");
                            $stmt->execute([$list_id, $row['word_a'], $row['word_b'], $row['desc_a'] ?: null, $row['desc_b'] ?: null]);
                            $new_id = (int) $pdo->lastInsertId();
                            $stmt = $pdo->prepare("INSERT INTO card_progress (person_id, card_id, status) VALUES (?,?,'queued')");
                            $stmt->execute([$person_id, $new_id]);
                            $imported++;
                        }
                        // 'keep' → nichts tun
                        continue;
                    }

                    // Normales Duplikat — globale Entscheidung, ausser Ausnahmen
                    $in_exceptions = in_array($i, $dup_exceptions);
                    $should_import = ($dup_action === 'import') !== $in_exceptions; // XOR

                    if (!$should_import) continue;
                }

                // Neue Karte importieren
                $stmt = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b) VALUES (?,?,?,?,?)");
                $stmt->execute([$list_id, $row['word_a'], $row['word_b'], $row['desc_a'] ?: null, $row['desc_b'] ?: null]);
                $new_id = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO card_progress (person_id, card_id, status) VALUES (?,?,'queued')");
                $stmt->execute([$person_id, $new_id]);
                $imported++;
            }

            $pdo->commit();
            unset($_SESSION['import']);
            $success = "$imported Karte" . ($imported !== 1 ? 'n' : '') . " wurden importiert.";
            $import_stage = 'done';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Fehler beim Import. Bitte versuche es erneut.';
            $import_stage = 'upload';
        }
    }
}

// Logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    logout();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSV Import — <?= APP_NAME ?></title>
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

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Meine Listen', 'lists.php'], [$list['name'], 'edit.php?list_id=' . $list_id], ['Importieren', '']]) ?></div>

<div class="container mt-2" style="max-width:960px;">

    <h1 class="h4 mb-4">CSV Import — <?= htmlspecialchars($list['name']) ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($import_stage === 'done'): ?>
    <!-- Fertig -->
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <a href="edit.php?list_id=<?= $list_id ?>" class="btn btn-primary">Zur Kartenliste</a>

    <?php elseif ($import_stage === 'review'): ?>
    <!-- Duplikat-Review -->
    <div class="alert alert-info">
        <strong><?= count($parsed_rows) ?> Karte<?= count($parsed_rows) !== 1 ? 'n' : '' ?> in der Datei</strong>
        — <?= count($clean_rows) ?> neu · <?= count($duplicates) ?> Duplikat<?= count($duplicates) !== 1 ? 'e' : '' ?> · <?= count($archived_matches) ?> archiviert
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="stage" value="confirm">
        <input type="hidden" name="list_id" value="<?= $list_id ?>">

        <!-- Normale Duplikate -->
        <?php if ($duplicates): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <?= count($duplicates) ?> Duplikat<?= count($duplicates) !== 1 ? 'e' : '' ?> gefunden
            </div>
            <div class="card-body">
                <p class="small text-muted">Diese Karten existieren bereits in deinen Listen.</p>
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="dup_action" id="dup_skip" value="skip" checked>
                        <label class="form-check-label" for="dup_skip">Alle überspringen</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="dup_action" id="dup_import" value="import">
                        <label class="form-check-label" for="dup_import">Alle trotzdem importieren</label>
                    </div>
                </div>
                <table class="table table-sm small">
                    <thead><tr><th><?= htmlspecialchars($list['language_a']) ?></th><th><?= htmlspecialchars($list['language_b']) ?></th><th>Existiert in</th><th>Ausnahme</th></tr></thead>
                    <tbody>
                    <?php foreach ($duplicates as $i => $dup): ?>
                    <tr>
                        <td><?= htmlspecialchars($dup['row']['word_a']) ?></td>
                        <td><?= htmlspecialchars($dup['row']['word_b']) ?></td>
                        <td><?= htmlspecialchars($dup['matches'][0]['list_name'] ?? '?') ?></td>
                        <td>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="dup_exceptions[]" value="<?= $i ?>">
                                <label class="form-check-label small">Ausnahme</label>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Archivierte Karten -->
        <?php if ($archived_matches): ?>
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <?= count($archived_matches) ?> archivierte Karte<?= count($archived_matches) !== 1 ? 'n' : '' ?> gefunden
            </div>
            <div class="card-body">
                <p class="small text-muted">Diese Karten sind bei dir bereits archiviert. Was soll passieren?</p>
                <?php foreach ($archived_matches as $i => $am): ?>
                <div class="border rounded p-2 mb-2">
                    <strong><?= htmlspecialchars($am['row']['word_a']) ?></strong> / <?= htmlspecialchars($am['row']['word_b']) ?>
                    <div class="mt-1 d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="archived[<?= $i ?>]" value="keep" checked>
                            <label class="form-check-label small">Archiviert lassen</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="archived[<?= $i ?>]" value="reactivate">
                            <label class="form-check-label small">Reaktivieren (Fach 1)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="archived[<?= $i ?>]" value="new">
                            <label class="form-check-label small">Als neue Karte importieren</label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Neue Karten Vorschau -->
        <?php if ($clean_rows): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white"><?= count($clean_rows) ?> neue Karte<?= count($clean_rows) !== 1 ? 'n' : '' ?></div>
            <div class="card-body">
                <table class="table table-sm small mb-0">
                    <thead><tr><th><?= htmlspecialchars($list['language_a']) ?></th><th><?= htmlspecialchars($list['language_b']) ?></th><th>Beschreibung A</th><th>Beschreibung B</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($clean_rows, 0, 20) as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['word_a']) ?></td>
                        <td><?= htmlspecialchars($row['word_b']) ?></td>
                        <td><?= htmlspecialchars($row['desc_a']) ?></td>
                        <td><?= htmlspecialchars($row['desc_b']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($clean_rows) > 20): ?>
                    <tr><td colspan="4" class="text-muted">… und <?= count($clean_rows) - 20 ?> weitere</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary">Import bestätigen</button>
            <a href="import.php?list_id=<?= $list_id ?>&cancel=1" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>

    <?php else: ?>
    <!-- Upload-Formular -->
    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">CSV-Datei hochladen</div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="stage" value="upload">
                        <input type="hidden" name="list_id" value="<?= $list_id ?>">

                        <p class="text-muted small mb-3">
                            Import in Liste: <strong><?= htmlspecialchars($list['name']) ?></strong>
                            (<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)
                        </p>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">CSV-Datei <span class="text-danger">*</span></label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <div class="form-text">Max. 2MB · nur .csv · Encoding: UTF-8</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Hochladen & prüfen</button>
                        <a href="templates/vorlage.csv" download class="btn btn-outline-secondary ms-2">Vorlage herunterladen</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card bg-light">
                <div class="card-header">CSV-Format</div>
                <div class="card-body small">
                    <p>Trennzeichen: <strong>Komma</strong> oder <strong>Semikolon</strong> (wird automatisch erkannt)</p>
                    <p>Encoding: <strong>UTF-8</strong></p>
                    <p>Die erste Zeile ist die Kopfzeile (Spaltentitel, z.B. Sprachnamen) und wird übersprungen.</p>
                    <p>Felder mit Kommas oder Semikolons müssen in <strong>doppelte Anführungszeichen</strong> gesetzt werden.</p>
                    <pre class="bg-white border rounded p-2 small"><code>Deutsch;Englisch;Beschreibung Deutsch;Beschreibung Englisch
Diagnose;diagnosis;medizinisch;"A conclusion"
Behandlung;treatment;;</code></pre>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
