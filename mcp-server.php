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
            'list_persons' => tool_list_persons($pdo),
            'list_lists'   => tool_list_lists($pdo, $args),
            'add_cards'    => tool_add_cards($pdo, $args),
            default        => tool_error("Unbekanntes Tool: $name"),
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

    $stmt = $pdo->prepare("SELECT id, name FROM persons WHERE id = ?");
    $stmt->execute([$person_id]);
    $person = $stmt->fetch();
    if (!$person) {
        return tool_error("Person mit id=$person_id nicht gefunden");
    }

    $stmt = $pdo->prepare("SELECT id, name, language_a, language_b FROM lists WHERE person_id = ? ORDER BY name");
    $stmt->execute([$person_id]);
    $lists = $stmt->fetchAll();

    return mcp_text(['person' => $person, 'lists' => $lists]);
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

    $stmt = $pdo->prepare("SELECT id, name, language_a, language_b FROM lists WHERE id = ?");
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

    $insert = $pdo->prepare("INSERT INTO cards (list_id, word_a, word_b, desc_a, desc_b) VALUES (?,?,?,?,?)");

    $results = [];
    foreach ($cards as $i => $card) {
        $wa = trim((string)($card['sprache_a_begriff'] ?? ''));
        $wb = trim((string)($card['sprache_b_begriff'] ?? ''));
        $da = trim((string)($card['beschreibung_a'] ?? ''));
        $db = trim((string)($card['beschreibung_b'] ?? ''));

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

        $insert->execute([$list_id, $wa, $wb, $da !== '' ? $da : null, $db !== '' ? $db : null]);
        $existing[$key] = ['word_a' => $wa, 'word_b' => $wb];
        $results[] = ['index' => $i, 'status' => 'inserted', 'card' => ['sprache_a_begriff' => $wa, 'sprache_b_begriff' => $wb]];
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
            'description' => 'Gibt alle Vokabellisten einer Person zurück (id, name, Sprachen). Zweiter Schritt: Liste per Name auflösen, dann add_cards aufrufen.',
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
            'description' => 'Fügt Vokabelkarten in eine Liste ein. Fehlende Felder (Übersetzung, Beschreibung) sollen vor dem Aufruf ergänzt werden. Bei Duplikat-Warnung: in Claude Code erst nach Rückfrage mit force=true, in n8n immer direkt force=true.',
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
                                'sprache_a_begriff' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Begriff in Sprache A'],
                                'sprache_b_begriff' => ['type' => 'string', 'maxLength' => 500, 'description' => 'Begriff in Sprache B'],
                                'beschreibung_a'    => ['type' => 'string', 'maxLength' => 1000, 'description' => 'Optionale Beschreibung zu Sprache A'],
                                'beschreibung_b'    => ['type' => 'string', 'maxLength' => 1000, 'description' => 'Optionale Beschreibung zu Sprache B'],
                            ],
                            'required' => ['sprache_a_begriff', 'sprache_b_begriff'],
                        ],
                    ],
                    'force' => ['type' => 'boolean', 'description' => 'Duplikate trotzdem einfügen wenn true (default: false)'],
                ],
                'required' => ['list_id', 'cards'],
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
    return '';
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
