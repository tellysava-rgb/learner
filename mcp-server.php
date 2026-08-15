<?php
declare(strict_types=1);

ob_start();

set_error_handler(function (): bool { return true; });
set_exception_handler(function (): never {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32603, 'message' => 'Interner Fehler']]);
    exit;
});

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tags.php';

$_mcp_cfg = __DIR__ . '/includes/mcp-config.php';
if (!file_exists($_mcp_cfg)) {
    mcp_die(null, -32603, 'Serverkonfiguration fehlt', 500);
}
require $_mcp_cfg;
unset($_mcp_cfg);

// HTTPS-Pflicht auf Produktion
if (APP_ENV === 'prod') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if (!$https) {
        mcp_die(null, -32600, 'HTTPS erforderlich', 403);
    }
}

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mcp_die(null, -32600, 'Nur POST erlaubt', 405);
}

// Bearer-Token-Prüfung
if (!defined('MCP_TOKEN') || !hash_equals(MCP_TOKEN, mcp_bearer_token())) {
    mcp_die(null, -32600, 'Ungültiger Token', 401);
}

// JSON-RPC Body parsen
$body = file_get_contents('php://input');
$req  = json_decode($body ?: '', true);
if (!is_array($req) || ($req['jsonrpc'] ?? '') !== '2.0' || !isset($req['method'])) {
    mcp_die(null, -32700, 'Parse-Fehler: ungültige JSON-RPC-Anfrage', 400);
}

$id     = $req['id'] ?? null;
$method = (string)$req['method'];
$params = is_array($req['params'] ?? null) ? $req['params'] : [];

mcp_log($method, $params);

switch ($method) {
    case 'initialize':
        mcp_ok($id, [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => ['tools' => new stdClass()],
            'serverInfo'      => ['name' => 'learner-mcp', 'version' => APP_VERSION],
            'instructions'    => 'Workflow zum Hinzufügen von Vokabeln: '
                . '1. list_persons aufrufen, dem User die Personen zeigen und fragen für wen. '
                . '2. list_lists aufrufen (zeigt standardmässig nur AKTIVE Listen), dem User diese anzeigen und explizit fragen in welche Liste. Anhand language_a/language_b bestimmen, welche Seite Deutsch ist (relevant für die Rechtschreibregeln in Punkt 4 — die Rollen von Beschreibung A/B in Punkt 5 sind davon unabhängig fest). Nennt der User eine Liste beim Namen, die nicht in den aktiven Listen auftaucht: list_lists erneut mit include_inactive=true aufrufen, bevor angenommen wird die Liste existiere nicht — Karten dürfen auch in eine explizit genannte inaktive Liste eingefügt werden. '
                . '3. Mehrdeutigkeit klären: Hat ein Begriff mehrere stark unterschiedliche Bedeutungen (z.B. "bank" = Geldinstitut vs. Flussufer), zuerst beim User nachfragen welche Bedeutung gemeint ist, bevor übersetzt wird. Bei nur minimalen Nuancen nicht nachfragen. '
                . '4. Begriff A und Begriff B: KEIN isoliertes Einzelwort, sondern immer eine natürliche Phrase/ein Chunk mit realistischem Verwendungskontext (mindestens ein Adjektiv oder eine Ergänzung) — z.B. nicht "Entscheid" sondern "einen wichtigen Entscheid treffen". Das ist der DEFAULT, kein starres Muss: Verlangt der User im Gespräch ausdrücklich ein einzelnes Wort statt eines Chunks (z.B. "nur das Wort", "ohne Kontextsatz", "einfach nur X"), gilt diese explizite Anweisung — nicht gegen den ausdrücklichen Wunsch des Users einen Chunk erzwingen. Der jeweils andere (Lösungs-)Begriff darf im Chunk nicht so vorkommen, dass er die Antwort preisgibt. Begriff A und Begriff B müssen dieselbe Bedeutung tragen — exakte Bedeutungsgleichheit ist die WICHTIGSTE Regel überhaupt, das Fundament des Sprachenlernens: eine Karte mit abweichender Bedeutung bringt der lernenden Person eine falsche Übersetzung bei. Ausnahme NUR bei Sprichwörtern/Redewendungen ohne wörtliche Entsprechung (z.B. "once in a blue moon" ↔ "alle Jubeljahre"): dort ist eine sinngemässe statt wörtliche Übersetzung zulässig, aber auch dort muss die Kernaussage exakt übereinstimmen, nur nicht der Wortlaut — bei normalem Wortschatz (kein Sprichwort/keine Redewendung) gilt immer wörtliche/exakte Bedeutungsgleichheit, keine freie Näherung. Rückübersetzungs-Konsistenz-Check ist PFLICHT vor jeder Bestätigung: zurückübersetzt muss Begriff B bedeutungsgleich mit Begriff A sein — weicht die Bedeutung ab und handelt es sich NICHT um eine Redewendung, ist das ein Fehler, den der Agent VOR der Bestätigung korrigiert, nicht nur meldet. Ist der Kernbegriff des Chunks in der Fremdsprache ein Verb: Grundform (Infinitiv); bei unregelmässigen Verben alle drei Formen (z.B. "go / went / gone"). Für den deutschen Anteil gilt weiterhin exakte de-CH-Rechtschreibung: Nomen IMMER gross (z.B. "Haus"), alle anderen Wortarten (Verben Grundform, Adjektive, Adverbien etc.) klein, ausser am Satzanfang bei mehrteiligen Chunks (dann nur das erste Wort gross) — NIE "ß", immer "ss" (z.B. "Strasse" nicht "Straße"). Fremdsprachige Chunks: Originalschreibweise, kein automatisches Grossschreiben ausser bei echtem Satzanfang. Übersetzungen müssen natürlich und idiomatisch klingen, dürfen dabei aber nie von der exakten Bedeutung abweichen — bei Unsicherheit den User fragen, nicht raten. '
                . '5. Beschreibung A und Beschreibung B haben FESTE, sprachunabhängige Rollen (gilt unabhängig davon, welche Seite laut Punkt 2 Deutsch ist): Beschreibung A ist ein kognitiver Hinweis zur aktiven Selbstkorrektur — KEINE direkte Lösung, der Begriff selbst darf darin nicht erscheinen (z.B. "Gegenteil von X", "unregelmässige Form von Y", "wird verwendet wenn man Z ausdrücken will"), bei Mehrdeutigkeit den konkreten Verwendungskontext angeben. Beschreibung B ist ein natürlicher, alltagstauglicher Beispielsatz mit dem EXAKTEN Begriff aus Begriff B — kein Lehrbuchsatz, insbesondere keine reinen Konjugations-Sätze ohne echten Inhalt (z.B. "wir werden kaufen" ist unzulässig), der Satz muss eine echte Situation beschreiben. '
                . '6. Dialekt-Logik (gilt für Begriff B, Beschreibung B und Phonetik): Hat die Liste ein speech_lang_b (z.B. "en-GB" vs. "en-US") gesetzt, müssen Schreibweise und Wortwahl in Sprache B zu diesem Dialekt passen (z.B. en-GB → "colour", "lorry", "flat"; en-US → "color", "truck", "apartment") — diese Listen-Definition hat Vorrang vor allem anderen. Ist Sprache B Englisch und KEIN speech_lang_b gesetzt: Standard ist BRITISCHES Englisch (en-GB), ausser der User verlangt im Gespräch ausdrücklich einen anderen Dialekt. '
                . '7. Phonetik (phonetik_b): NUR befüllen wenn die Liste ein speech_lang_b gesetzt hat, sonst leer lassen — auch bei Sprachen ausser Englisch. Stil ableiten: list_cards der Zielliste aufrufen und vorhandene phonetik_b-Einträge analysieren — werden IPA-Zeichen verwendet (z.B. "/biːt/"), IPA-Stil weiterführen; wird vereinfachte Lautschrift verwendet (z.B. "biit"), diesen Stil weiterführen; Konsistenz innerhalb der Liste hat Vorrang. Existieren noch keine Einträge: dem User ein Beispiel BEIDER Varianten für den aktuellen Begriff zeigen und EINMALIG fragen, ob "einfach" (vereinfachte Lautschrift) oder "eindeutig" (IPA) gewünscht ist — der Entscheid gilt dann für die ganze Liste/Session, nicht erneut pro Karte fragen. Vereinfachte Lautschrift: Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Sonderzeichen, geschrieben in der Lesekonvention der Muttersprache der lernenden Person (Standardannahme: Sprache A der Liste — nur bei Unstimmigkeit, z.B. beide Sprachen sind für die Person fremd, oder bei Widerspruch des Users nachfragen, nicht bei jeder Liste pauschal). Bei nicht-rhotischen Dialekten (en-GB/en-AU/en-NZ/en-ZA): "r" nach Vokal vor Konsonant/am Wortende weglassen ("thunder" → "THUN-duh", "storm" → "stawm"); bei rhotischen Dialekten (z.B. en-US) "r" normal schreiben. Diese detaillierten rhotisch/nicht-rhotisch-Regeln gelten für Englisch als Sprache B — bei anderen Zielsprachen sinngemäss eine vereinfachte, zur jeweiligen Zielsprache passende Lautschrift verwenden (keine ausformulierten Detailregeln dafür hinterlegt). IPA: Standard-IPA-Notation (z.B. "/biːt/"), Dialekt muss zu speech_lang_b passen. Bei Unsicherheit über die korrekte Phonetik (egal welcher Stil): leer lassen — lieber kein Eintrag als ein falscher. '
                . '8. Tags: list_person_tags(person_id) aufrufen und vorhandene Tags DIESER PERSON über ALLE ihre Listen hinweg prüfen, bevor ein Tag gesetzt wird — einen passenden vorhandenen Tag wiederverwenden statt einen neuen mit leicht abweichender Schreibweise zu erfinden (Ziel: die Tag-Liste der Person bleibt überschaubar und konsistent). Passt keiner der vorhandenen Tags: den User fragen, welchen Tag er verwenden möchte — NIEMALS selbst einen neuen Tag erfinden ohne Rückfrage. Tags sind thematische Schlagworte (z.B. "Wetter", "Business", "Reise"), immer auf Deutsch (de-CH), unabhängig von der Lernsprache der Karte. Nur setzen wenn sinnvoll, nicht zwingend bei jeder Karte. Mehrere Tags pro Karte möglich, gleiches Format wie in der Web-Oberfläche: leerzeichengetrennt mit "#"-Präfix (z.B. "#Wetter #Reise"). '
                . '9. Alle Felder der einzufügenden Karten (Begriff A, Begriff B, Beschreibung A, Beschreibung B, Tags, Phonetik) dem User vollständig zur Bestätigung zeigen, explizit auf Korrektheit der Übersetzung hinweisen, BEVOR add_cards aufgerufen wird. '
                . '10. Erst nach expliziter Bestätigung des Users add_cards aufrufen. '
                . 'Workflow zum Prüfen/Korrigieren BESTEHENDER Karten (z.B. Schreibweise, Gross-/Kleinschreibung des deutschen Begriffs, fehlende Lautschrift, fehlende/inkonsistente Tags): list_cards(list_id) aufrufen, Änderungen (alt → neu) dem User pro Karte zeigen und Bestätigung abwarten, danach erst update_card je Karte aufrufen. Niemals list_cards-Ergebnisse ungefragt automatisch mit update_card ändern. Gleiche Feld-Regeln wie bei add_cards gelten auch für update_card. '
                . 'Duplikat-Behandlung bei add_cards: in Claude Code erst nach Rückfrage mit force=true, in n8n immer direkt force=true.',
        ]);

    case 'notifications/initialized':
        ob_end_clean();
        http_response_code(204);
        exit;

    case 'tools/list':
        mcp_ok($id, ['tools' => mcp_tools_schema()]);

    case 'tools/call':
        $name = (string)($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $result = match ($name) {
            'list_persons'     => tool_list_persons($pdo),
            'list_lists'       => tool_list_lists($pdo, $args),
            'list_person_tags' => tool_list_person_tags($pdo, $args),
            'add_cards'        => tool_add_cards($pdo, $args),
            'list_cards'       => tool_list_cards($pdo, $args),
            'update_card'      => tool_update_card($pdo, $args),
            default            => tool_error("Unbekanntes Tool: $name"),
        };
        mcp_ok($id, $result);

    default:
        mcp_die($id, -32601, 'Methode nicht gefunden', 404);
}

// -------------------------------------------------------
// Tools
// -------------------------------------------------------

function tool_list_persons(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT id, name FROM persons ORDER BY name");
    $stmt->execute();
    $persons = $stmt->fetchAll();
    return mcp_text(['persons' => $persons]);
}

function tool_list_lists(PDO $pdo, array $args): array {
    $person_id = isset($args['person_id']) ? (int)$args['person_id'] : 0;
    if ($person_id <= 0) {
        return tool_error('person_id ist erforderlich (positive Ganzzahl)');
    }
    $include_inactive = !empty($args['include_inactive']);

    $stmt = $pdo->prepare("SELECT id, name FROM persons WHERE id = ?");
    $stmt->execute([$person_id]);
    $person = $stmt->fetch();
    if (!$person) {
        return tool_error("Person mit id=$person_id nicht gefunden");
    }

    $sql = "SELECT id, name, language_a, language_b, speech_lang_b, is_active FROM lists WHERE person_id = ?"
         . ($include_inactive ? "" : " AND is_active = 1")
         . " ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$person_id]);
    $lists = $stmt->fetchAll();

    return mcp_text(['person' => $person, 'lists' => $lists]);
}

// Alle Tags dieser Person über sämtliche ihre Listen hinweg — dient dazu, vor dem Setzen eines
// Tags in add_cards/update_card einen passenden vorhandenen wiederzuverwenden statt einen neuen,
// leicht abweichend geschriebenen zu erfinden (Tags sind pro Person eigenständig, kein globaler
// Pool — siehe includes/tags.php).
function tool_list_person_tags(PDO $pdo, array $args): array {
    $person_id = isset($args['person_id']) ? (int)$args['person_id'] : 0;
    if ($person_id <= 0) {
        return tool_error('person_id ist erforderlich (positive Ganzzahl)');
    }

    $stmt = $pdo->prepare("SELECT id, name FROM persons WHERE id = ?");
    $stmt->execute([$person_id]);
    $person = $stmt->fetch();
    if (!$person) {
        return tool_error("Person mit id=$person_id nicht gefunden");
    }

    $stmt = $pdo->prepare("SELECT name FROM tags WHERE person_id = ? ORDER BY name");
    $stmt->execute([$person_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return mcp_text(['person' => $person, 'tags' => $tags]);
}

function tool_add_cards(PDO $pdo, array $args): array {
    $list_id = isset($args['list_id']) ? (int)$args['list_id'] : 0;
    $cards   = $args['cards'] ?? [];
    $force   = (bool)($args['force'] ?? false);

    if ($list_id <= 0) {
        return tool_error('list_id ist erforderlich (positive Ganzzahl)');
    }
    if (!is_array($cards) || count($cards) === 0) {
        return tool_error('cards muss ein nicht-leeres Array sein');
    }
    if (count($cards) > 50) {
        return tool_error('Maximal 50 Karten pro Aufruf erlaubt');
    }

    $stmt = $pdo->prepare("SELECT id, person_id, name, language_a, language_b, speech_lang_b FROM lists WHERE id = ?");
    $stmt->execute([$list_id]);
    $list = $stmt->fetch();
    if (!$list) {
        return tool_error("Liste mit id=$list_id nicht gefunden");
    }

    // Bestehende Karten laden (Duplikat-Prüfung: word_a + word_b, case-insensitive, getrimmt)
    $stmt = $pdo->prepare("SELECT LOWER(TRIM(word_a)) AS a, LOWER(TRIM(word_b)) AS b, word_a, word_b FROM cards WHERE list_id = ?");
    $stmt->execute([$list_id]);
    $existing = [];
    foreach ($stmt->fetchAll() as $row) {
        $existing[$row['a'] . '§' . $row['b']] = ['word_a' => $row['word_a'], 'word_b' => $row['word_b']];
    }

    $insert = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b, phonetic_b) VALUES (?,?,?,?,?,?)");
    // Ohne diesen Eintrag würde die Karte in edit.php als "Warteschlange" angezeigt (COALESCE-Default),
    // aber nie über activate_daily_cards() aktiviert und nie in home.php mitgezählt (dort INNER JOIN).
    $insert_progress = $pdo->prepare("
        INSERT INTO card_progress (person_id, card_id, status)
        VALUES (?, ?, 'queued')
        ON DUPLICATE KEY UPDATE status = status
    ");

    $results = [];
    foreach ($cards as $i => $card) {
        $wa = trim((string)($card['sprache_a_begriff'] ?? ''));
        $wb = trim((string)($card['sprache_b_begriff'] ?? ''));
        $da = trim((string)($card['beschreibung_a'] ?? ''));
        $db = trim((string)($card['beschreibung_b'] ?? ''));
        $ph = trim((string)($card['phonetik_b'] ?? ''));
        $tags_parsed = parse_tag_input((string)($card['tags'] ?? ''));

        if ($wa === '' || $wb === '') {
            $results[] = ['index' => $i, 'status' => 'error', 'message' => 'sprache_a_begriff und sprache_b_begriff sind Pflichtfelder'];
            continue;
        }
        if (mb_strlen($wa) > 500 || mb_strlen($wb) > 500) {
            $results[] = ['index' => $i, 'status' => 'error', 'message' => 'Begriff darf maximal 500 Zeichen haben'];
            continue;
        }
        if (mb_strlen($da) > 1000 || mb_strlen($db) > 1000) {
            $results[] = ['index' => $i, 'status' => 'error', 'message' => 'Beschreibung darf maximal 1000 Zeichen haben'];
            continue;
        }
        if (mb_strlen($ph) > 200) {
            $results[] = ['index' => $i, 'status' => 'error', 'message' => 'phonetik_b darf maximal 200 Zeichen haben'];
            continue;
        }
        if ($tags_parsed['error']) {
            $results[] = ['index' => $i, 'status' => 'error', 'message' => $tags_parsed['error']];
            continue;
        }

        $key = strtolower($wa) . '§' . strtolower($wb);
        if (isset($existing[$key]) && !$force) {
            $dup = $existing[$key];
            $results[] = [
                'index'   => $i,
                'status'  => 'duplicate',
                'message' => "Duplikat: «{$dup['word_a']}» / «{$dup['word_b']}» — mit force=true trotzdem einfügen",
                'card'    => ['sprache_a_begriff' => $wa, 'sprache_b_begriff' => $wb],
            ];
            continue;
        }

        $insert->execute([$list_id, $wa, $wb, $da !== '' ? $da : null, $db !== '' ? $db : null, $ph !== '' ? $ph : null]);
        $card_id = (int) $pdo->lastInsertId();
        $insert_progress->execute([(int) $list['person_id'], $card_id]);
        set_card_tags($pdo, (int) $list['person_id'], $card_id, $tags_parsed['names']);
        $existing[$key] = ['word_a' => $wa, 'word_b' => $wb];
        $results[] = ['index' => $i, 'status' => 'inserted', 'card' => ['sprache_a_begriff' => $wa, 'sprache_b_begriff' => $wb, 'tags' => $tags_parsed['names']]];
    }

    $n_inserted  = count(array_filter($results, fn($r) => $r['status'] === 'inserted'));
    $n_duplicate = count(array_filter($results, fn($r) => $r['status'] === 'duplicate'));
    $n_error     = count(array_filter($results, fn($r) => $r['status'] === 'error'));

    return mcp_text([
        'summary' => "$n_inserted eingefügt, $n_duplicate Duplikate übersprungen, $n_error Fehler",
        'list'    => ['id' => (int)$list['id'], 'name' => $list['name']],
        'results' => $results,
    ]);
}

function tool_list_cards(PDO $pdo, array $args): array {
    $list_id = isset($args['list_id']) ? (int)$args['list_id'] : 0;
    if ($list_id <= 0) {
        return tool_error('list_id ist erforderlich (positive Ganzzahl)');
    }

    $stmt = $pdo->prepare("SELECT id, name, language_a, language_b, speech_lang_b FROM lists WHERE id = ?");
    $stmt->execute([$list_id]);
    $list = $stmt->fetch();
    if (!$list) {
        return tool_error("Liste mit id=$list_id nicht gefunden");
    }

    $stmt = $pdo->prepare("
        SELECT c.id, c.word_a, c.word_b, c.desc_a, c.desc_b, c.phonetic_b,
               (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ' ')
                FROM card_tags ct JOIN tags t ON t.id = ct.tag_id
                WHERE ct.card_id = c.id) AS tags
        FROM cards c WHERE c.list_id = ? ORDER BY c.created_at
    ");
    $stmt->execute([$list_id]);
    $cards = array_map(fn($c) => [
        'card_id'           => (int)$c['id'],
        'sprache_a_begriff' => $c['word_a'],
        'sprache_b_begriff' => $c['word_b'],
        'beschreibung_a'    => $c['desc_a'],
        'beschreibung_b'    => $c['desc_b'],
        'phonetik_b'        => $c['phonetic_b'],
        'tags'              => $c['tags'] ? explode(' ', $c['tags']) : [],
    ], $stmt->fetchAll());

    return mcp_text(['list' => $list, 'cards' => $cards]);
}

function tool_update_card(PDO $pdo, array $args): array {
    $card_id = isset($args['card_id']) ? (int)$args['card_id'] : 0;
    if ($card_id <= 0) {
        return tool_error('card_id ist erforderlich (positive Ganzzahl)');
    }

    $stmt = $pdo->prepare("SELECT id, list_id, word_a, word_b, desc_a, desc_b, phonetic_b FROM cards WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch();
    if (!$card) {
        return tool_error("Karte mit id=$card_id nicht gefunden");
    }

    // Nur übergebene Felder aktualisieren — Rest bleibt unverändert
    $fields = [
        'sprache_a_begriff' => ['word_a', 500],
        'sprache_b_begriff' => ['word_b', 500],
        'beschreibung_a'    => ['desc_a', 1000],
        'beschreibung_b'    => ['desc_b', 1000],
        'phonetik_b'        => ['phonetic_b', 200],
    ];

    foreach (['sprache_a_begriff', 'sprache_b_begriff'] as $required_key) {
        if (array_key_exists($required_key, $args) && trim((string)$args[$required_key]) === '') {
            return tool_error("$required_key darf nicht leer sein");
        }
    }

    // tags ist keine Spalte auf cards (n:m über card_tags) — separat behandelt, greift beim
    // Speichern unten nicht in dieselbe dynamische UPDATE-Spaltenliste ein.
    $tags_parsed = null;
    if (array_key_exists('tags', $args)) {
        $tags_parsed = parse_tag_input((string) $args['tags']);
        if ($tags_parsed['error']) {
            return tool_error($tags_parsed['error']);
        }
    }

    $updates = [];
    $params  = [];
    foreach ($fields as $arg_key => [$column, $max_len]) {
        if (!array_key_exists($arg_key, $args)) continue;
        $val = trim((string)$args[$arg_key]);
        if (mb_strlen($val) > $max_len) {
            return tool_error("$arg_key darf maximal $max_len Zeichen haben");
        }
        $updates[] = "`$column` = ?";
        $params[]  = $val !== '' ? $val : null;
    }

    if (!$updates && $tags_parsed === null) {
        return tool_error('Mindestens ein zu änderndes Feld ist erforderlich');
    }

    if ($updates) {
        $params[] = $card_id;
        $stmt = $pdo->prepare("UPDATE cards SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
    }

    if ($tags_parsed !== null) {
        // person_id kommt über die Liste — cards hat keine eigene person_id-Spalte.
        $stmt = $pdo->prepare("SELECT person_id FROM lists WHERE id = ?");
        $stmt->execute([$card['list_id']]);
        $person_id = (int) $stmt->fetchColumn();
        set_card_tags($pdo, $person_id, $card_id, $tags_parsed['names']);
    }

    $stmt = $pdo->prepare("
        SELECT c.id, c.word_a, c.word_b, c.desc_a, c.desc_b, c.phonetic_b,
               (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ' ')
                FROM card_tags ct JOIN tags t ON t.id = ct.tag_id
                WHERE ct.card_id = c.id) AS tags
        FROM cards c WHERE c.id = ?
    ");
    $stmt->execute([$card_id]);
    $updated = $stmt->fetch();

    return mcp_text([
        'summary' => "Karte $card_id aktualisiert",
        'card'    => [
            'card_id'           => (int)$updated['id'],
            'sprache_a_begriff' => $updated['word_a'],
            'sprache_b_begriff' => $updated['word_b'],
            'beschreibung_a'    => $updated['desc_a'],
            'beschreibung_b'    => $updated['desc_b'],
            'phonetik_b'        => $updated['phonetic_b'],
            'tags'              => $updated['tags'] ? explode(' ', $updated['tags']) : [],
        ],
    ]);
}

// -------------------------------------------------------
// Tool-Schema (tools/list)
// -------------------------------------------------------

function mcp_tools_schema(): array {
    return [
        [
            'name'        => 'list_persons',
            'description' => 'Gibt alle Personen zurück. Erster Schritt: Person per Name auflösen, dann list_lists aufrufen.',
            'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
        ],
        [
            'name'        => 'list_lists',
            'description' => 'Gibt die Vokabellisten einer Person zurück (id, name, Sprachen, speech_lang_b, is_active). Zeigt standardmässig NUR aktive Listen (is_active=true) — inaktive Listen werden dem User nicht proaktiv erwähnt. Nennt der User beim Namen eine Liste, die hier nicht auftaucht, dieses Tool erneut mit include_inactive=true aufrufen, bevor angenommen wird die Liste existiere nicht — Karten dürfen auch in eine explizit genannte inaktive Liste eingefügt werden. Zweiter Schritt: (aktive) Listen dem User anzeigen und explizit fragen welche Liste verwendet werden soll — niemals eine Liste ohne Rückfrage auswählen. speech_lang_b (z.B. "en-GB") gibt den Dialekt vor, falls gesetzt — Schreibweise/Wortwahl in add_cards muss dazu passen. Ist Sprache B Englisch und speech_lang_b NICHT gesetzt: Standarddialekt ist BRITISCHES Englisch (en-GB), nicht US-Englisch.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'person_id'        => ['type' => 'integer', 'description' => 'ID der Person (von list_persons)'],
                    'include_inactive' => ['type' => 'boolean', 'description' => 'Falls true: auch inaktive Listen zurückgeben. Standard false — nur aktive Listen.'],
                ],
                'required' => ['person_id'],
            ],
        ],
        [
            'name'        => 'list_person_tags',
            'description' => 'Gibt alle Tags dieser Person zurück, über sämtliche ihre Listen hinweg (Tags sind pro Person eigenständig, kein globaler Pool). Vor dem Setzen eines Tags in add_cards/update_card IMMER zuerst hier prüfen, ob ein passender Tag schon existiert, und diesen wiederverwenden — nie einen inhaltlich passenden, aber leicht anders geschriebenen neuen Tag erfinden (z.B. "Reise" vs. "Reisen"). Passt keiner der vorhandenen Tags zur aktuellen Karte, den User fragen, welchen Tag er verwenden möchte, statt selbst einen neuen zu erfinden.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'person_id' => ['type' => 'integer', 'description' => 'ID der Person (von list_persons)'],
                ],
                'required' => ['person_id'],
            ],
        ],
        [
            'name'        => 'add_cards',
            'description' => 'Fügt Vokabelkarten in eine Liste ein. Begriff A/B: kein isoliertes Einzelwort, sondern per Default eine natürliche Phrase/ein Chunk mit realistischem Verwendungskontext (mind. ein Adjektiv oder eine Ergänzung) — z.B. nicht "Entscheid" sondern "einen wichtigen Entscheid treffen". Verlangt der User ausdrücklich ein einzelnes Wort statt eines Chunks, gilt diese Anweisung — kein Chunk gegen den ausdrücklichen Wunsch erzwingen. Der jeweils andere (Lösungs-)Begriff darf im Chunk nicht so vorkommen, dass er die Antwort preisgibt. Begriff A und Begriff B müssen exakt dieselbe Bedeutung tragen (WICHTIGSTE Regel, Fundament des Sprachenlernens) — Ausnahme nur bei Sprichwörtern/Redewendungen ohne wörtliche Entsprechung, dort sinngemäss statt wörtlich, aber Kernaussage weiterhin exakt gleich. Rückübersetzungs-Konsistenz-Check ist Pflicht vor jeder Bestätigung; weicht die Bedeutung ab (ausser bei Redewendungen), ist das vor der Bestätigung zu korrigieren, nicht nur zu melden. Bei Verben als Kernbegriff in der Fremdsprache: Grundform, bei unregelmässigen Verben alle drei Formen. Deutscher Anteil: de-CH-Rechtschreibung (NIE "ß", immer "ss"), Nomen IMMER gross, alle anderen Wortarten klein, ausser am Satzanfang bei mehrteiligen Chunks. Beschreibung A und Beschreibung B haben FESTE, sprachunabhängige Rollen: Beschreibung A ist ein kognitiver Hinweis zur aktiven Selbstkorrektur (KEINE direkte Lösung, der Begriff selbst darf nicht erscheinen), Beschreibung B ist ein natürlicher Beispielsatz mit dem EXAKTEN Begriff aus Begriff B (kein Lehrbuchsatz, keine reinen Konjugations-Sätze ohne Inhalt). Ist die Zielliste (aus list_lists) Englisch als Sprache B: Hat sie ein speech_lang_b gesetzt, müssen Schreibweise/Wortwahl in Sprache B dazu passen; ist KEIN speech_lang_b gesetzt, gilt Standard BRITISCHES Englisch (en-GB). phonetik_b NUR befüllen wenn die Zielliste ein speech_lang_b gesetzt hat (siehe Feldbeschreibung für Stilwahl einfach/IPA). tags: vor dem Setzen mit list_person_tags prüfen, ob ein passender vorhandener Tag der Person wiederverwendet werden kann. WICHTIG: Alle Karten (Begriff A/B, Beschreibungen, Tags, Lautschrift) dem User zur Sichtprüfung vorlegen und Bestätigung abwarten, bevor dieses Tool aufgerufen wird. Bei Duplikat-Warnung: in Claude Code erst nach Rückfrage mit force=true, in n8n immer direkt force=true.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'list_id' => ['type' => 'integer', 'description' => 'ID der Ziel-Liste (von list_lists)'],
                    'cards'   => [
                        'type'     => 'array',
                        'maxItems' => 50,
                        'items'    => [
                            'type'       => 'object',
                            'properties' => [
                                'sprache_a_begriff' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Begriff A als natürliche Phrase/Chunk mit realistischem Verwendungskontext (kein isoliertes Einzelwort) — z.B. nicht "Entscheid" sondern "einen wichtigen Entscheid treffen". Das ist der Default; verlangt der User ausdrücklich ein einzelnes Wort statt eines Chunks, gilt diese Anweisung. Muss denselben Kontext wie sprache_b_begriff abbilden. Ist der Kernbegriff in der Fremdsprache ein Verb: Grundform (Infinitiv); bei unregelmässigen Verben alle drei Formen (z.B. "go / went / gone"). Ist Sprache A Deutsch: de-CH-Rechtschreibung (NIE "ß", immer "ss"), Nomen IMMER gross (z.B. "Haus"), alle anderen Wortarten klein, ausser am Satzanfang bei mehrteiligen Chunks. Ist Sprache A die Fremdsprache: Originalschreibweise, kein automatisches Grossschreiben ausser bei echtem Satzanfang.'],
                                'sprache_b_begriff' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Begriff B als natürliche Phrase/Chunk (Default, siehe sprache_a_begriff für die Ausnahme bei explizitem User-Wunsch nach einem Einzelwort) — gleiche Regeln wie sprache_a_begriff. Muss exakt dieselbe Bedeutung wie sprache_a_begriff tragen (wichtigste Regel, Fundament des Sprachenlernens) — Ausnahme nur bei Sprichwörtern/Redewendungen ohne wörtliche Entsprechung, dort sinngemäss statt wörtlich, aber Kernaussage weiterhin exakt gleich. Rückübersetzung muss bedeutungsgleich mit sprache_a_begriff sein; weicht sie ab (ausser bei Redewendungen), vor der Bestätigung korrigieren, nicht nur melden. Ist Sprache B Englisch: Dialekt richtet sich nach speech_lang_b der Liste, ohne speech_lang_b gilt BRITISCHES Englisch (en-GB) als Standard.'],
                                'beschreibung_a'    => ['type' => 'string', 'maxLength' => 1000, 'description' => 'Kognitiver Hinweis zur aktiven Selbstkorrektur (feste Rolle, unabhängig davon welche Sprache A ist) — KEINE direkte Lösung, der Begriff (sprache_a_begriff) darf hier nicht erscheinen. Regt zum Nachdenken an, z.B. "Gegenteil von X", "unregelmässige Form von Y", "wird verwendet wenn man Z ausdrücken will". Bei Mehrdeutigkeit den konkreten Verwendungskontext angeben.'],
                                'beschreibung_b'    => ['type' => 'string', 'maxLength' => 1000, 'description' => 'Natürlicher, alltagstauglicher Beispielsatz mit dem EXAKTEN Begriff aus sprache_b_begriff (feste Rolle, unabhängig davon welche Sprache B ist) — kein Lehrbuchsatz, keine reinen Konjugations-Sätze ohne echten Inhalt (z.B. "wir werden kaufen" ist unzulässig). Muss eine echte Situation beschreiben.'],
                                'phonetik_b'        => ['type' => 'string', 'maxLength' => 200, 'description' => 'Lautschrift für sprache_b_begriff — NUR ausfüllen wenn die Zielliste (aus list_lists) ein speech_lang_b gesetzt hat, sonst leer lassen. Stil (einfach oder IPA) aus vorhandenen phonetik_b-Einträgen der Liste ableiten (list_cards aufrufen); existieren noch keine, den User einmalig pro Liste fragen ("einfach" vs. "eindeutig"/IPA). Vereinfachte Lautschrift: Silben mit Bindestrich, betonte Silbe GROSS, keine IPA-Zeichen, in der Lesekonvention der Muttersprache der lernenden Person (Standardannahme: Sprache A der Liste), z.B. "toh-ken-eye-ZAY-shun". Bei nicht-rhotischen Dialekten (en-GB, en-AU, en-NZ, en-ZA): "r" nach Vokal vor Konsonant/am Wortende weglassen ("thunder"→"THUN-duh", "storm"→"stawm"); bei rhotischen Dialekten (z.B. en-US) "r" normal schreiben. Diese rhotisch/nicht-rhotisch-Regeln gelten für Englisch als Sprache B — bei anderen Zielsprachen sinngemäss eine vereinfachte, passende Lautschrift verwenden. IPA: Standard-IPA-Notation (z.B. "/biːt/"), Dialekt muss zu speech_lang_b passen. Bei Unsicherheit über die korrekte Phonetik: leer lassen.'],
                                'tags'              => ['type' => 'string', 'maxLength' => 300, 'description' => 'Optionale Tags, leerzeichengetrennt mit "#"-Präfix (z.B. "#Wetter #Reise") — gleiches Format wie im Web. Vor dem Setzen mit list_person_tags(person_id) prüfen, ob ein passender vorhandener Tag der Person wiederverwendet werden kann, statt einen neuen mit leicht abweichender Schreibweise zu erfinden. Passt keiner, den User fragen statt selbst zu entscheiden. Tags immer auf Deutsch (de-CH), unabhängig von der Lernsprache. Nur setzen wenn sinnvoll, nicht zwingend bei jeder Karte.'],
                            ],
                            'required' => ['sprache_a_begriff', 'sprache_b_begriff'],
                        ],
                    ],
                    'force' => ['type' => 'boolean', 'description' => 'Duplikate trotzdem einfügen wenn true (default: false)'],
                ],
                'required' => ['list_id', 'cards'],
            ],
        ],
        [
            'name'        => 'list_cards',
            'description' => 'Gibt alle bestehenden Karten einer Liste zurück (inkl. card_id, Begriffe, Beschreibungen, phonetik_b, tags). Zum Prüfen/Korrigieren bestehender Karten (z.B. Schreibweise, fehlende Lautschrift, fehlende/inkonsistente Tags) — danach update_card pro zu ändernder Karte aufrufen. Auch nützlich, um VOR dem Hinzufügen neuer Karten den in dieser Liste bereits verwendeten Lautschrift-Stil (einfach vs. IPA) an vorhandenen phonetik_b-Werten abzulesen. NIEMALS Karten ungefragt automatisch ändern: dem User immer zuerst zeigen was sich ändern würde und Bestätigung abwarten, bevor update_card aufgerufen wird.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'list_id' => ['type' => 'integer', 'description' => 'ID der Liste (von list_lists)'],
                ],
                'required' => ['list_id'],
            ],
        ],
        [
            'name'        => 'update_card',
            'description' => 'Ändert einzelne Felder einer bestehenden Karte (von list_cards). Nur die übergebenen Felder werden geändert, alle anderen bleiben unverändert. sprache_a_begriff/sprache_b_begriff dürfen nicht leer sein falls angegeben. Gleiche Feld-Regeln wie bei add_cards (Chunk-Modell für Begriff A/B, feste Beschreibung-Rollen A=Hinweis/B=Beispielsatz, Dialekt-Konsistenz, Lautschrift-Stil, de-CH-Rechtschreibung, Tags über list_person_tags abgleichen). WICHTIG: dem User vor dem Aufruf immer zeigen, was sich pro Karte ändert (alt → neu), und Bestätigung abwarten.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'card_id'           => ['type' => 'integer', 'description' => 'ID der Karte (card_id von list_cards)'],
                    'sprache_a_begriff' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Neuer Begriff A (optional, nicht leer falls angegeben) — gleiche Chunk-Regeln wie bei add_cards (natürliche Phrase mit Kontext statt Einzelwort), gleiche Gross-/Kleinschreibungs-Regel falls Deutsch (de-CH: NIE "ß", immer "ss").'],
                    'sprache_b_begriff' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Neuer Begriff B (optional, nicht leer falls angegeben) — gleiche Chunk-Regeln wie bei add_cards, muss weiterhin denselben Kontext wie sprache_a_begriff abbilden.'],
                    'beschreibung_a'    => ['type' => 'string', 'maxLength' => 1000, 'description' => 'Neue Beschreibung A (optional, leerer String löscht sie) — feste Rolle wie bei add_cards: kognitiver Hinweis, keine direkte Lösung.'],
                    'beschreibung_b'    => ['type' => 'string', 'maxLength' => 1000, 'description' => 'Neue Beschreibung B (optional, leerer String löscht sie) — feste Rolle wie bei add_cards: natürlicher Beispielsatz mit dem exakten Begriff B.'],
                    'phonetik_b'        => ['type' => 'string', 'maxLength' => 200, 'description' => 'Neue Lautschrift (optional, leerer String löscht sie), gleicher Stil wie bei add_cards (einfach oder IPA, konsistent mit den übrigen Karten der Liste)'],
                    'tags'              => ['type' => 'string', 'maxLength' => 300, 'description' => 'Neue Tags, leerzeichengetrennt mit "#"-Präfix (optional, leerer String entfernt alle Tags der Karte) — ersetzt die komplette bisherige Tag-Zuordnung dieser Karte. Vor dem Setzen mit list_person_tags(person_id) vorhandene Tags der Person prüfen.'],
                ],
                'required' => ['card_id'],
            ],
        ],
    ];
}

// -------------------------------------------------------
// Hilfsfunktionen
// -------------------------------------------------------

function mcp_bearer_token(): string {
    $auth = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if (!$auth && function_exists('getallheaders')) {
        $h    = getallheaders();
        $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) {
        return $m[1];
    }
    // Fallback: Token als Query-Parameter (für claude.ai Browser-Connector)
    return isset($_GET['token']) ? trim((string)$_GET['token']) : '';
}

function mcp_ok(mixed $id, array $result): never {
    ob_end_clean();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

function mcp_die(mixed $id, int $code, string $message, int $http = 400): never {
    ob_end_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    exit;
}

function mcp_text(array $data): array {
    return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]]];
}

function tool_error(string $message): array {
    return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
}

function mcp_log(string $method, array $params): void {
    $log  = __DIR__ . '/mcp.log';
    $tool = ($method === 'tools/call') ? ($params['name'] ?? '?') : '-';
    $args = ($method === 'tools/call' && isset($params['arguments']))
        ? json_encode($params['arguments'], JSON_UNESCAPED_UNICODE)
        : '-';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . APP_ENV . ' | ' . $method . ' | ' . $tool . ' | ' . $args . "\n";
    error_log($line, 3, $log);
}
