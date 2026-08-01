<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id   = $_SESSION['person_id'];
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

// Navbar-Aktionen (Logout, eigenes Konto, Person wechseln)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['logout', 'switch_to_person', 'change_own_password', 'change_own_email'], true)) {
    csrf_validate();
    handle_navbar_actions($pdo);
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
            'word_a'     => trim($fields[0] ?? ''),
            'word_b'     => trim($fields[1] ?? ''),
            'desc_a'     => trim($fields[2] ?? ''),
            'desc_b'     => trim($fields[3] ?? ''),
            'phonetic_b' => trim($fields[4] ?? ''),
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
                            $stmt = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b, phonetic_b) VALUES (?,?,?,?,?,?)");
                            $stmt->execute([$list_id, $row['word_a'], $row['word_b'], $row['desc_a'] ?: null, $row['desc_b'] ?: null, $row['phonetic_b'] ?: null]);
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
                $stmt = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b, phonetic_b) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$list_id, $row['word_a'], $row['word_b'], $row['desc_a'] ?: null, $row['desc_b'] ?: null, $row['phonetic_b'] ?: null]);
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

// -------------------------------------------------------
// Prompt für KI-generierte Wortlisten (passend zum CSV-Format dieser Liste)
// -------------------------------------------------------
$lang_a = $list['language_a'];
$lang_b = $list['language_b'];
$speech_lang = $list['speech_lang_b'] ?? '';
$non_rhotic_dialects = ['en-GB', 'en-AU', 'en-NZ', 'en-ZA'];
$is_english_b = (bool) preg_match('/englisch|english/i', trim($lang_b));

if (!$speech_lang) {
    $phonetic_instruction = "Diese Liste hat für {$lang_b} keinen Aussprache-Dialekt hinterlegt. Bevor du die Spalte \"Lautschrift\" ausfüllst: frage mich explizit, welchen Dialekt/welche Ausspracheform ich möchte (z.B. \"britisches\" oder \"amerikanisches Englisch\"), ausser das ist oben beim Thema bereits angegeben. Fülle die Lautschrift danach passend zu meiner Antwort aus (Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Zeichen; bei nicht-rhotischen Dialekten \"r\" nach Vokal vor Konsonant/am Wortende weglassen, bei rhotischen normal schreiben). Lass die Spalte nicht einfach leer.";
} elseif (in_array($speech_lang, $non_rhotic_dialects, true)) {
    $phonetic_instruction = "Fülle die Spalte \"Lautschrift\" mit einer vereinfachten Aussprache-Hilfe für den Begriff in {$lang_b} (Dialekt {$speech_lang}): Silben mit Bindestrich trennen, betonte Silbe GROSS schreiben, keine IPA-Zeichen. {$speech_lang} ist ein nicht-rhotischer Dialekt: \"r\" nach einem Vokal vor einem Konsonanten oder am Wortende NICHT mitschreiben (\"-er\"/\"-or\" wird zu \"-uh\"/\"-aw\", z.B. \"thunder\" -> \"THUN-duh\", \"storm\" -> \"stawm\"); \"r\" nur schreiben, wenn direkt ein Vokal folgt (z.B. \"rain\" -> \"rayn\").";
} else {
    $phonetic_instruction = "Fülle die Spalte \"Lautschrift\" mit einer vereinfachten Aussprache-Hilfe für den Begriff in {$lang_b} (Dialekt {$speech_lang}): Silben mit Bindestrich trennen, betonte Silbe GROSS schreiben, keine IPA-Zeichen, \"r\" normal aussprechen (rhotischer Dialekt).";
}

// Dialekt-Vorgabe nur relevant wenn Sprache B Englisch ist — verhindert das wiederkehrende Problem,
// dass US-Begriffe statt der gewünschten britischen Begriffe generiert werden.
$dialect_instruction = null;
if ($is_english_b) {
    if ($speech_lang) {
        $dialect_instruction = "Schreibweise UND Wortwahl von Begriff und Beispielsatz in {$lang_b} müssen zum hinterlegten Dialekt dieser Liste ({$speech_lang}) passen (z.B. en-GB: \"colour\", \"lorry\", \"flat\"; en-US: \"color\", \"truck\", \"apartment\") — diese Listen-Definition hat Vorrang vor allem anderen.";
    } else {
        $dialect_instruction = "Sofern beim Thema oben nicht ausdrücklich ein anderer Dialekt verlangt wird (z.B. \"amerikanisches Englisch\"), gilt als Standard BRITISCHES Englisch (en-GB) für Schreibweise UND Wortwahl von Begriff und Beispielsatz in {$lang_b} (z.B. \"colour\" statt \"color\", \"lorry\" statt \"truck\", \"flat\" statt \"apartment\") — diese Liste hat keinen Dialekt hinterlegt.";
    }
}

$rules = [
    "Spalte 1 ({$lang_a}): exakter Begriff. Bei Verben die Grundform (Infinitiv); bei unregelmässigen Verben alle drei Formen (z.B. \"gehen / ging / gegangen\").",
    "Spalte 2 ({$lang_b}): exakter Begriff, analog zu Spalte 1 (bei unregelmässigen Verben ebenfalls alle drei Formen).",
    "Spalte 3 (Beschreibung {$lang_a}): Beispielsatz mit dem exakten Begriff aus Spalte 1.",
    "Spalte 4 (Beschreibung {$lang_b}): beschreibt die Bedeutung genauer, OHNE den Begriff aus Spalte 2 zu wiederholen. Bei mehrdeutigen Begriffen den konkreten Verwendungskontext angeben.",
    $dialect_instruction,
    $phonetic_instruction,
    'Felder mit Komma oder Semikolon in doppelte Anführungszeichen setzen.',
    'Keine Duplikate innerhalb der Liste.',
];
$rules_text = implode("\n", array_map(fn($r) => "- $r", array_filter($rules)));

$ai_prompt = <<<PROMPT
Erstelle eine Vokabelliste zum Thema "[Thema einfügen]" mit [Anzahl] Wörtern für die Sprachen {$lang_a} / {$lang_b}.

Gib das Ergebnis AUSSCHLIESSLICH als CSV-Codeblock aus, exakt in diesem Format (Semikolon-getrennt, erste Zeile ist die Kopfzeile, kein zusätzlicher Text davor oder danach):

{$lang_a};{$lang_b};Beschreibung {$lang_a};Beschreibung {$lang_b};Lautschrift

Regeln:
{$rules_text}
PROMPT;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSV Import — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php render_navbar($pdo); ?>

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
                    <thead><tr><th><?= htmlspecialchars($list['language_a']) ?></th><th><?= htmlspecialchars($list['language_b']) ?></th><th>Beschreibung A</th><th>Beschreibung B</th><th>Lautschrift</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($clean_rows, 0, 20) as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['word_a']) ?></td>
                        <td><?= htmlspecialchars($row['word_b']) ?></td>
                        <td><?= htmlspecialchars($row['desc_a']) ?></td>
                        <td><?= htmlspecialchars($row['desc_b']) ?></td>
                        <td><?= htmlspecialchars($row['phonetic_b']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($clean_rows) > 20): ?>
                    <tr><td colspan="5" class="text-muted">… und <?= count($clean_rows) - 20 ?> weitere</td></tr>
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
                            <label class="form-label fw-semibold" for="csv_file">CSV-Datei <span class="text-danger">*</span></label>
                            <!-- accept nur mit Datei-Endung (".csv" allein) öffnet den nativen Datei-Dialog
                                 auf manchen iOS-Safari-Versionen gar nicht — zusätzlich MIME-Types angeben. -->
                            <input type="file" id="csv_file" name="csv_file" class="form-control"
                                   accept=".csv,text/csv,text/comma-separated-values,application/vnd.ms-excel" required>
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
                    <p>5. Spalte <strong>Lautschrift</strong> (nur Sprache B) ist optional — sinnvoll nur bei Listen mit hinterlegtem Aussprache-Sprachcode.</p>
                    <!-- overflow-auto: die Beispielzeilen sind länger als ein Handy-Display breit ist;
                         sie sollen im Block scrollen statt die ganze Seite breiter zu machen -->
                    <pre class="bg-white border rounded p-2 small overflow-auto"><code>Deutsch;Englisch;Beschreibung Deutsch;Beschreibung Englisch;Lautschrift
Diagnose;diagnosis;medizinisch;"A conclusion";dy-ug-NOH-sis
Behandlung;treatment;;;</code></pre>

                    <hr>
                    <h6 class="mb-2">🤖 Prompt für KI-generierte Wortlisten</h6>
                    <p class="text-muted small mb-2">Thema und Anzahl anpassen und einer KI (z.B. Claude, ChatGPT) geben — die Antwort kann als <code>.csv</code>-Datei gespeichert und oben hochgeladen werden.</p>
                    <textarea id="aiPrompt" class="form-control form-control-sm font-monospace" rows="8" style="font-size:0.75rem;" readonly><?= htmlspecialchars($ai_prompt) ?></textarea>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="copyAiPrompt()">📋 Prompt kopieren</button>
                    <span id="aiPromptCopied" class="text-success small ms-2" style="display:none;">Kopiert!</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
function copyAiPrompt() {
    const ta = document.getElementById('aiPrompt');
    navigator.clipboard.writeText(ta.value).then(() => {
        const msg = document.getElementById('aiPromptCopied');
        msg.style.display = 'inline';
        setTimeout(() => msg.style.display = 'none', 2000);
    });
}
</script>
</body>
</html>
