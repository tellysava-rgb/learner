<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_person();

$person_id = $_SESSION['person_id'];

$list_id = intval($_GET['list_id'] ?? 0);
if (!$list_id) {
    header('Location: home.php');
    exit;
}

// Eigene Listen exportierbar: selbst erstellt oder kopiert — person_id muss stimmen
$stmt = $pdo->prepare("SELECT * FROM lists WHERE id = ? AND person_id = ?");
$stmt->execute([$list_id, $person_id]);
$list = $stmt->fetch();
if (!$list) {
    header('Location: home.php');
    exit;
}

// Karten laden
$stmt = $pdo->prepare("SELECT word_a, word_b, desc_a, desc_b, phonetic_b FROM cards WHERE list_id = ? ORDER BY created_at");
$stmt->execute([$list_id]);
$cards = $stmt->fetchAll();

// CSV ausgeben (Semikolon, UTF-8, Excel-freundlich)
$filename = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $list['name']) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// UTF-8 BOM für Excel-Kompatibilität
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Kommentarzeile zur menschenlesbaren Dokumentation (wird beim Import ignoriert)
fwrite($out, '# ' . $list['name'] . ' (' . $list['language_a'] . ' / ' . $list['language_b'] . ')' . "\n");

// Kopfzeile mit Sprachnamen
fputcsv($out, [
    $list['language_a'],
    $list['language_b'],
    'Beschreibung ' . $list['language_a'],
    'Beschreibung ' . $list['language_b'],
    'Lautschrift',
], ';', '"', '\\');

foreach ($cards as $card) {
    fputcsv($out, [
        html_entity_decode(strip_tags($card['word_a']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        html_entity_decode(strip_tags($card['word_b']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        html_entity_decode(strip_tags($card['desc_a'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        html_entity_decode(strip_tags($card['desc_b'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        html_entity_decode(strip_tags($card['phonetic_b'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ], ';', '"', '\\');
}

fclose($out);
exit;
