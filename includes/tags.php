<?php
// Tag-Verwaltung für Karten. Tags sind pro Person eigenständig (kein globaler Pool) — jede
// Person hat ihre eigene "tags"-Zeile pro Name, verknüpft über "card_tags" (n:m) mit ihren
// eigenen Karten. Verwaiste Tags (letzte Karte entfernt) bleiben bewusst bestehen statt
// automatisch gelöscht zu werden — leichter wiederverwendbar, siehe Diskussion in der
// Konzeptphase dieses Features.
//
// Eigene Tabelle statt Freitextfeld auf cards: verhindert Substring-Fehltreffer beim Filtern
// (z.B. "#Wetter" würde in einem Freitextfeld auch "#Wetterbericht" matchen) und hält
// Schreibweisen konsistent (Autocomplete/Wiederverwendung statt "wetter"/"Wetter"/"Wetter-Vokabeln"
// als drei getrennte Tags).

// Parst Rohtext wie "#Wetter #Business" in eine bereinigte, deduplizierte Liste von Tag-Namen
// (ohne "#", getrimmt, case-insensitiv dedupliziert — erste Schreibweise gewinnt). Gibt bei einem
// zu langen Einzel-Tag eine Fehlermeldung zurück statt den Namen still abzuschneiden, weil
// tags.name als VARCHAR(TAG_NAME_MAX_LENGTH) sonst beim INSERT hart fehlschlagen würde.
// Rückgabe: ['names' => string[], 'error' => string|null]
function parse_tag_input(string $raw): array {
    $tokens = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
    $names  = [];
    $seen   = [];
    foreach ($tokens as $token) {
        $name = trim(ltrim($token, '#'));
        if ($name === '') continue;
        if (mb_strlen($name) > TAG_NAME_MAX_LENGTH) {
            return ['names' => [], 'error' => "Tag \"$name\" ist zu lang (max. " . TAG_NAME_MAX_LENGTH . " Zeichen)."];
        }
        $key = mb_strtolower($name);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $names[] = $name;
    }
    return ['names' => $names, 'error' => null];
}

// Liefert die (ggf. neu angelegte) Tag-ID für diese Person. Dank case-insensitiver
// Spalten-Collation (utf8mb4_*_ci, DB-Standard) matcht die SELECT-Abfrage unabhängig von
// Gross-/Kleinschreibung — die zuerst erfasste Schreibweise eines Tags bleibt so erhalten.
function find_or_create_tag(PDO $pdo, int $person_id, string $name): int {
    $stmt = $pdo->prepare("SELECT id FROM tags WHERE person_id = ? AND name = ?");
    $stmt->execute([$person_id, $name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;

    $stmt = $pdo->prepare("INSERT INTO tags (person_id, name) VALUES (?, ?)");
    $stmt->execute([$person_id, $name]);
    return (int) $pdo->lastInsertId();
}

// Ersetzt die komplette Tag-Zuordnung einer Karte durch die übergebenen Namen (leeres Array =
// alle Tags entfernen).
function set_card_tags(PDO $pdo, int $person_id, int $card_id, array $tag_names): void {
    $tag_ids = [];
    foreach ($tag_names as $name) {
        $tag_ids[] = find_or_create_tag($pdo, $person_id, $name);
    }

    $pdo->prepare("DELETE FROM card_tags WHERE card_id = ?")->execute([$card_id]);
    if ($tag_ids) {
        $insert = $pdo->prepare("INSERT IGNORE INTO card_tags (card_id, tag_id) VALUES (?, ?)");
        foreach ($tag_ids as $tag_id) {
            $insert->execute([$card_id, $tag_id]);
        }
    }
}

// Formatiert die per GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ' ') geladenen Tag-Namen
// einer Karte für die Anzeige im Eingabefeld — wieder mit '#'-Präfix wie bei der Eingabe.
function format_tags_for_input(?string $tags_concat): string {
    if (!$tags_concat) return '';
    return implode(' ', array_map(fn($t) => '#' . $t, explode(' ', $tags_concat)));
}

// -------------------------------------------------------
// Themen-Sessions (Leitner/Drill über mehrere Listen hinweg, siehe learn.php/drill.php)
// -------------------------------------------------------

// Alle Tags, die auf mindestens einer Karte einer AKTIVEN eigenen Liste dieser Person vorkommen —
// Grundlage für die Tag-Cloud beim Session-Start. Inaktive Listen stehen auch beim normalen
// Listen-Modus nicht zur Wahl, deshalb hier ebenso ausgeschlossen.
function get_person_tags(PDO $pdo, int $person_id): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.name
        FROM tags t
        JOIN card_tags ct ON ct.tag_id = t.id
        JOIN cards c ON c.id = ct.card_id
        JOIN lists l ON l.id = c.list_id
        WHERE t.person_id = ? AND l.person_id = ? AND l.is_active = 1
        ORDER BY t.name
    ");
    $stmt->execute([$person_id, $person_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Alle Tags, die auf mindestens einer Karte EINER bestimmten Liste vorkommen — für die Tag-Cloud,
// wenn die Setup-Seite über eine konkrete Liste aufgerufen wurde (?list_id=…). Zeigt dort bewusst
// nur die im jeweiligen Deck tatsächlich vorkommenden Tags statt aller Tags der Person, damit die
// Vorschläge zum Kontext passen — ausgewählt wird trotzdem weiterhin listenübergreifend (siehe
// get_person_tag_card_ids()), nur die angebotene Auswahl ist eingeschränkt.
function get_list_tags(PDO $pdo, int $list_id): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.name
        FROM tags t
        JOIN card_tags ct ON ct.tag_id = t.id
        JOIN cards c ON c.id = ct.card_id
        WHERE c.list_id = ?
        ORDER BY t.name
    ");
    $stmt->execute([$list_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Alle Karten-IDs dieser Person (aus aktiven eigenen Listen) mit dem gegebenen Tag — die
// listenübergreifende Kartenmenge einer Themen-Session.
function get_person_tag_card_ids(PDO $pdo, int $person_id, string $tag_name): array {
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM cards c
        JOIN lists l ON l.id = c.list_id
        JOIN card_tags ct ON ct.card_id = c.id
        JOIN tags t ON t.id = ct.tag_id
        WHERE t.person_id = ? AND t.name = ? AND l.person_id = ? AND l.is_active = 1
    ");
    $stmt->execute([$person_id, $tag_name, $person_id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Baut das zusätzliche "AND cp.card_id IN (...)"-Fragment für eine Themen-Session (learn.php und
// drill.php) — null bedeutet "keine Einschränkung", wie im normalen Listen-Modus.
function card_id_filter_sql(?array $card_ids_filter): array {
    if ($card_ids_filter === null) return ['', []];
    $ph = implode(',', array_fill(0, count($card_ids_filter), '?'));
    return [" AND cp.card_id IN ($ph)", $card_ids_filter];
}
