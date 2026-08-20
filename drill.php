<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tags.php';
require_person();

$person_id   = $_SESSION['person_id'];

// Navbar-Aktionen (Logout, eigenes Konto, Person wechseln)
if (in_array($_POST['action'] ?? '', ['logout', 'switch_to_person', 'change_own_password', 'change_own_email'], true)) {
    csrf_validate();
    handle_navbar_actions($pdo);
}

// Session abbrechen
if (($_GET['action'] ?? '') === 'abort') {
    unset($_SESSION['drill']);
    $to = $_GET['to'] ?? 'home.php';
    if (str_contains($to, '://') || str_starts_with($to, '//')) {
        $to = 'home.php';
    }
    header('Location: ' . $to);
    exit;
}

// Verfügbare eigene Listen laden (nur aktive — inaktive Listen stehen zum Lernen nicht zur Wahl)
$stmt = $pdo->prepare("
    SELECT l.id, l.name, l.language_a, l.language_b
    FROM lists l
    WHERE l.person_id = ? AND l.is_active = 1
    ORDER BY l.last_used_at DESC, l.name
");
$stmt->execute([$person_id]);
$all_lists = $stmt->fetchAll();

// Vorausgewählte Liste aus URL (von home.php oder "Erneut starten") — startet die Session nicht
// mehr direkt, sondern füllt nur die Setup-Seite vor (Richtung/Timer sollen wählbar bleiben).
$preset_list_id = intval($_GET['list_id'] ?? 0);
$preset_list    = null;
if ($preset_list_id) {
    foreach ($all_lists as $l) {
        if ((int)$l['id'] === $preset_list_id) {
            $preset_list = $l;
            break;
        }
    }
}

// Themen-Session: siehe learn.php, identisches Prinzip (ergänzt die Listenauswahl, ersetzt sie
// nicht bei Vorrang für den Tag). Angebotene Tags mit vorausgewählter Liste (?list_id=…) nur
// deren eigene Tags, sonst alle Tags über sämtliche eigene aktiven Listen hinweg — ausgewählt wird
// trotzdem immer listenübergreifend. Anders als beim Leitner-Tageslimit gibt es im Drill-Modus
// keine analoge Einstellung zu überschreiben — die Session-Länge (Timer) begrenzt hier bereits alles.
$available_tags = $preset_list ? get_list_tags($pdo, (int) $preset_list['id']) : get_person_tags($pdo, $person_id);
$preset_tag  = trim($_GET['tag'] ?? '');
if ($preset_tag !== '' && !in_array($preset_tag, $available_tags, true)) {
    $preset_tag = '';
}

// POST: Session konfigurieren und starten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin') {
    csrf_validate();
    unset($_SESSION['drill']);

    $direction = resolve_direction($_POST['direction'] ?? null);
    $default_minutes = (int) round(DRILL_SESSION_SECONDS / 60);
    $session_minutes = max(1, min(120, intval($_POST['session_minutes'] ?? $default_minutes)));
    $tag = trim($_POST['tag'] ?? '');

    // Themen-Session (siehe learn.php, identisches Prinzip): Tag hat Vorrang vor der
    // Listen-Checkbox-Auswahl, Session läuft listenübergreifend über die getaggten Karten.
    $card_ids_filter = null;
    if ($tag !== '') {
        $card_ids_filter = get_person_tag_card_ids($pdo, $person_id, $tag);
        if (!$card_ids_filter) {
            $_SESSION['flash_error'] = 'Keine Karten mit diesem Tag gefunden.';
            header('Location: drill.php');
            exit;
        }
        $ph = implode(',', array_fill(0, count($card_ids_filter), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT list_id FROM cards WHERE id IN ($ph)");
        $stmt->execute($card_ids_filter);
        $list_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } else {
        $list_ids = array_map('intval', array_filter((array)($_POST['list_ids'] ?? [])));
        if (!$list_ids) {
            $_SESSION['flash_error'] = 'Bitte mindestens eine Liste auswählen.';
            header('Location: drill.php');
            exit;
        }
    }

    start_drill_session($pdo, $person_id, $list_ids, $direction, $session_minutes * 60, $card_ids_filter, $tag !== '' ? $tag : null);
}

// POST: Karte beantworten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'answer' && isset($_SESSION['drill'])) {
    csrf_validate();

    $state   = &$_SESSION['drill'];
    $card_id = intval($_POST['card_id'] ?? 0);
    $result  = $_POST['result'] ?? '';

    if (!in_array($result, ['known', 'unknown']) || $card_id !== $state['current_card_id']) {
        header('Location: drill.php');
        exit;
    }

    // Event loggen
    $stmt = $pdo->prepare("INSERT INTO learning_events (person_id, card_id, result, learn_date) VALUES (?,?,?,?)");
    $stmt->execute([$person_id, $card_id, $result, $state['today']]);

    // Debug-Modus (Einstellungen → Debug, nur Admins): Vorher-Snapshot vor allen Änderungen —
    // Nachher-Snapshot folgt unten, unverändert an der bestehenden Logik dazwischen.
    $debug_enabled = DEBUG_MODE && !empty($_SESSION['is_admin']);
    $debug_snapshot_sql = "SELECT leitner_box, next_due_date, drill_mastery, drill_too_hard, drill_pinned_correct FROM card_progress WHERE person_id=? AND card_id=?";
    $debug_before = null;
    if ($debug_enabled) {
        $stmt = $pdo->prepare($debug_snapshot_sql);
        $stmt->execute([$person_id, $card_id]);
        $debug_before = $stmt->fetch();
    }

    // Vorgemerkte Karten laufen über einen eigenen Zähler (drill_pinned_correct), komplett getrennt
    // von session_correct/drill_mastery — master_card()/mark_too_hard_card() dürfen hier NIE greifen,
    // sonst würde eine längst weit im Leitner-System fortgeschrittene Karte auf ein niedrigeres
    // Fach zurückgestuft (siehe docs/ANFORDERUNGEN.md, Abschnitt "Manuelle Vormerkung für Drill").
    $is_pinned = in_array($card_id, $state['pool_pinned'] ?? [], true);

    if ($is_pinned) {
        if ($result === 'known') {
            $state['stats']['known']++;
            pin_progress_correct($pdo, $state, $person_id, $card_id);
        } else {
            $state['stats']['unknown']++;
            pin_progress_reset($pdo, $person_id, $card_id);
        }
    } elseif ($result === 'known') {
        $state['stats']['known']++;
        $state['session_correct'][$card_id] = ($state['session_correct'][$card_id] ?? 0) + 1;

        if ($state['session_correct'][$card_id] >= DRILL_MASTERY_THRESHOLD) {
            master_card($pdo, $state, $person_id, $card_id, $state['today']);
        }
    } else {
        $state['stats']['unknown']++;
        $state['session_correct'][$card_id] = 0;
        $state['session_unknown'][$card_id] = ($state['session_unknown'][$card_id] ?? 0) + 1;

        if ($state['session_unknown'][$card_id] >= DRILL_TOO_HARD_LIMIT) {
            mark_too_hard_card($pdo, $state, $person_id, $card_id);
        }
    }

    // Reserve-Nachschub direkt hier (statt erst bei der Session-Ende-Prüfung unten) auslösen —
    // damit die Debug-Nachricht bereits die ggf. angepasste (aufgefüllte) Deckgrösse zeigt, nicht
    // den Stand von vor dem Nachschub.
    replenish_active_pool($state);

    if ($debug_enabled) {
        $stmt = $pdo->prepare($debug_snapshot_sql);
        $stmt->execute([$person_id, $card_id]);
        $debug_after = $stmt->fetch();
        $mastery_counter  = $state['session_correct'][$card_id] ?? 0;
        $too_hard_counter = $state['session_unknown'][$card_id] ?? 0;
        // Kennzahlen für die Deckgrössen-Zeile, erfasst NACH dem Reserve-Nachschub oben. Jede nicht
        // vorgemerkte Karte steckt zu jedem Zeitpunkt in genau einem der vier Töpfe (Deck rotiert,
        // Reserve wartet, gemeistert → Leitner, pausiert → bis morgen gesperrt); wie daraus die
        // Total-Zahl entsteht, entscheidet debug_deck_line().
        $deck = [
            'deck'     => count($state['pool_known']) + count($state['pool_new']),
            'reserve'  => count($state['reserve_known'] ?? []) + count($state['reserve_new'] ?? []),
            'mastered' => count($state['mastered_cards']),
            'paused'   => count($state['too_hard']),
            'pinned'   => count($state['pool_pinned']),
        ];
        $_SESSION['debug_last_answer'] = debug_drill_message($pdo, $card_id, $result, $is_pinned, $debug_before, $debug_after, $mastery_counter, $too_hard_counter, $deck);
    }

    // Session-Ende: Timer abgelaufen oder keine Karten mehr
    $elapsed  = time() - $state['started_at'];
    $no_cards = empty($state['pool_known']) && empty($state['pool_new']) && empty($state['pool_pinned']);

    if ($elapsed >= $state['session_seconds'] || $no_cards) {
        finish_drill_session($pdo, $state, $person_id);
        header('Location: drill.php?done=1');
        exit;
    }

    $next = next_drill_card($state);
    if ($next === null) {
        finish_drill_session($pdo, $state, $person_id);
        header('Location: drill.php?done=1');
        exit;
    }
    $state['current_card_id'] = $next;
    lazy_reset_drill_too_hard($pdo, $person_id, $next, $state['today']);

    header('Location: drill.php');
    exit;
}

// -------------------------------------------------------
// POST: "Für Drill vormerken" umschalten (während Session) — analog zum Pin-Toggle in learn.php.
// Nur die aktuell angezeigte Karte darf umgeschaltet werden (verhindert Manipulation fremder
// Karten über eine gefälschte card_id). Rührt Queue/Stats der laufenden Session nicht an, ausser
// dem pool_pinned-Zustand: eine Karte gehört exklusiv zu genau einem Pool (siehe load_drill_pool),
// daher beim Vormerken zusätzlich aus pool_known/pool_new entfernen. Beim Aufheben bleibt die Karte
// analog zum automatischen Unpin in pin_progress_correct() ausserhalb aller Pools für den Rest der
// Session — sie taucht in einer künftigen Session über load_drill_pool() wieder als bekannte Karte auf.
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_pin' && isset($_SESSION['drill'])) {
    csrf_validate();

    $state   = &$_SESSION['drill'];
    $card_id = intval($_POST['card_id'] ?? 0);

    if ($card_id !== ($state['current_card_id'] ?? null)) {
        header('Location: drill.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT drill_pinned_correct FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $is_pinned = $stmt->fetchColumn() !== null;

    if ($is_pinned) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = NULL WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$person_id, $card_id]);
        $state['pool_pinned'] = array_values(array_filter($state['pool_pinned'], fn($id) => $id !== $card_id));
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = 0 WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$person_id, $card_id]);
        $state['pool_known'] = array_values(array_filter($state['pool_known'], fn($id) => $id !== $card_id));
        $state['pool_new']   = array_values(array_filter($state['pool_new'], fn($id) => $id !== $card_id));
        if (!in_array($card_id, $state['pool_pinned'], true)) {
            $state['pool_pinned'][] = $card_id;
        }
    }

    header('Location: drill.php');
    exit;
}

// -------------------------------------------------------
// Hilfsfunktionen
// -------------------------------------------------------

// Debug-Modus: baut die Vorher/Nachher-Meldung aus den beiden Snapshots. Erkennt besondere
// Ereignisse (gemeistert, zu schwer markiert, Vormerkung erreicht) an der jeweiligen Feldänderung,
// statt sie separat nachzuverfolgen — robust gegenüber Änderungen an master_card()/mark_too_hard_card().
// $deck ['deck' => aktuell rotierend (known+new), 'reserve' => wartend, 'mastered' => in dieser
// Session gemeistert, 'paused' => als zu schwer pausiert, 'pinned' => vorgemerkt] wird zur festen
// zweiten Zeile aufbereitet (siehe debug_deck_line()), damit sich Pool-Begrenzung und Nachschub
// (siehe DRILL_CARDS_PER_MINUTE) direkt beim Testen nachvollziehen lassen. Rückgabe: 4 Zeilen —
// Normalfall [Karte, Deckgrösse, "Mastery-Zähler X/Y - gewusst", "Zu-schwer-Zähler X/Y - nicht
// gewusst"], besondere Ereignisse [Karte, Deckgrösse, Antwort (Kontext), Detail] — siehe
// debug_panel() in includes/auth.php.
function debug_drill_message(PDO $pdo, int $card_id, string $result, bool $was_pinned, array $before, array $after, int $mastery_counter, int $too_hard_counter, array $deck): array {
    $label      = debug_card_label($pdo, $card_id);
    $deck_line  = debug_deck_line($deck);
    $antwort    = $result === 'known' ? 'gewusst' : 'musste nachdenken';

    if ($was_pinned) {
        $box_note = $after['leitner_box'] !== null ? "Fach unverändert (Fach {$after['leitner_box']})" : 'noch nicht in Leitner aktiv (Warteschlange)';

        if ($before['drill_pinned_correct'] !== null && $after['drill_pinned_correct'] === null) {
            $detail = "Vormerkung entfernt, {$box_note}";
        } else {
            $detail = 'Vormerkungszähler ' . ($before['drill_pinned_correct'] ?? 0) . '→' . ($after['drill_pinned_correct'] ?? 0);
        }
        return [$label, $deck_line, "{$antwort} (vorgemerkt)", $detail];
    }

    if ((int)$before['drill_mastery'] !== (int)$after['drill_mastery']) {
        $detail = "gemeistert ({$after['drill_mastery']}×): Fach {$before['leitner_box']}→{$after['leitner_box']}, fällig "
            . debug_format_date($before['next_due_date']) . '→' . debug_format_date($after['next_due_date']);
        return [$label, $deck_line, $antwort, $detail];
    }

    if ((int)$before['drill_too_hard'] !== (int)$after['drill_too_hard']) {
        return [$label, $deck_line, $antwort, 'als zu schwer markiert, bis morgen pausiert'];
    }

    // Normalfall (kein besonderes Ereignis): keine eigenständige Antwort-Zeile — der Suffix steht
    // nur am Zähler, der durch diese Antwort hochgezählt hat: "- gewusst" am Mastery-Zähler bei
    // richtiger Antwort, "- nicht gewusst" am Zu-schwer-Zähler bei falscher (v3.7.8/v3.7.9).
    return [
        $label,
        $deck_line,
        "Mastery-Zähler {$mastery_counter}/" . DRILL_MASTERY_THRESHOLD . ($result === 'known' ? ' - gewusst' : ''),
        "Zu-schwer-Zähler {$too_hard_counter}/" . DRILL_TOO_HARD_LIMIT . ($result === 'known' ? '' : ' - nicht gewusst'),
    ];
}

// Baut die Deckgrössen-Zeile des Debug-Panels:
//   "Karten der Session: T total · D im Deck · G gemeistert · P pausiert · R Reserve [· (mit Pin: N)]"
// T = D+G+P — die Karten, die diese Session tatsächlich in der Rotation hatte: startet bei der
// Deckgrösse (Timer-Minuten × DRILL_CARDS_PER_MINUTE, min. DRILL_MIN_ACTIVE_CARDS) und wächst mit
// jeder gemeisterten/pausierten Karte, für die eine neue aus der Reserve nachrückt. Bewusst OHNE
// die Reserve (Korrektur v3.7.6): bei grossen Listen ist die riesig (ganze Liste minus Deck) und
// machte T zu einer statischen, Timer-unabhängigen Zahl ohne Aussagekraft für die Session — die
// Reserve steht als eigene Zahl am Ende der Aufzählung. Vorgemerkte Karten laufen ausserhalb
// dieser Rechnung (eigener Topf ohne Begrenzung, rotiert immer aktiv mit) und erscheinen als
// eigenständiger "(mit Pin: N)"-Zusatz ganz am Schluss — nur, wenn welche vorhanden sind.
function debug_deck_line(array $deck): string {
    $total = $deck['deck'] + $deck['mastered'] + $deck['paused'];
    $line  = "Karten der Session: {$total} total · {$deck['deck']} im Deck"
           . " · {$deck['mastered']} gemeistert · {$deck['paused']} pausiert · {$deck['reserve']} Reserve";
    if ($deck['pinned'] > 0) {
        $line .= " · (mit Pin: {$deck['pinned']})";
    }
    return $line;
}

// $card_ids_filter/$tag: nur im Themen-Modus gesetzt (siehe begin-Handler oben) — $list_ids ist in
// diesem Fall bereits auf die Listen der getaggten Karten aufgelöst, $card_ids_filter schränkt
// load_drill_pool() zusätzlich auf genau diese Karten ein (analog zu learn.php).
function start_drill_session(PDO $pdo, int $person_id, array $list_ids, string $direction, int $session_seconds, ?array $card_ids_filter = null, ?string $tag = null): void {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, language_a FROM lists WHERE id IN ($placeholders) AND person_id = ?");
    $stmt->execute(array_merge($list_ids, [$person_id]));
    $valid_rows = $stmt->fetchAll();
    $valid_ids = array_column($valid_rows, 'id');

    if (!$valid_ids) {
        $_SESSION['flash_error'] = 'Keine gültige Liste ausgewählt.';
        header('Location: drill.php');
        exit;
    }

    // Besteht die Auswahl ausschliesslich aus Mathe-Listen, ist nur "Aufgabe → Ergebnis" sinnvoll —
    // unabhängig vom Formularwert erzwingen (Formular blendet die anderen Optionen zwar aus, das
    // ersetzt aber keine serverseitige Prüfung).
    if (!array_filter($valid_rows, fn($r) => !is_math_list($r))) {
        $direction = 'a_to_b';
    }

    $today = today();
    ['known' => $pool_known, 'new' => $pool_new, 'pinned' => $pool_pinned] = load_drill_pool($pdo, $person_id, $valid_ids, $today, $card_ids_filter);
    $reserve_known = [];
    $reserve_new   = [];
    $max_active_cards = limit_active_pool($pool_known, $pool_new, $reserve_known, $reserve_new, $session_seconds);

    if (!$pool_known && !$pool_new && !$pool_pinned) {
        $_SESSION['flash_error'] = 'Keine geeigneten Karten für Drill in dieser Liste.';
        header('Location: drill.php');
        exit;
    }

    // last_used_at für alle beteiligten Listen — im Themen-Modus für alle Listen, die mindestens
    // eine Karte mit diesem Tag haben, unabhängig davon ob an diesem Tag tatsächlich eine ihrer
    // Karten in der Rotation war (konsistent mit dem Verhalten in learn.php).
    $upd = $pdo->prepare("UPDATE lists SET last_used_at = NOW() WHERE id = ?");
    foreach ($valid_ids as $lid) {
        $upd->execute([$lid]);
    }

    $state = [
        'list_ids'        => $valid_ids,
        'tag'             => $tag,
        'direction'       => $direction,
        'session_seconds' => $session_seconds,
        'pool_known'      => $pool_known,
        'pool_new'        => $pool_new,
        'pool_pinned'     => $pool_pinned,
        'reserve_known'   => $reserve_known,
        'reserve_new'     => $reserve_new,
        'max_active_cards'=> $max_active_cards,
        'cycle_pos'       => 0,
        'pin_cycle_pos'   => 0,
        'current_card_id' => null,
        'session_correct' => [],
        'session_unknown' => [],
        'mastered_cards'  => [],
        'too_hard'        => [],
        'stats'           => ['known' => 0, 'unknown' => 0, 'mastered' => 0],
        'started_at'      => time(),
        'today'           => $today,
    ];

    $first = next_drill_card($state);
    if ($first === null) {
        $_SESSION['flash_error'] = 'Keine geeigneten Karten für Drill in dieser Liste.';
        header('Location: drill.php');
        exit;
    }
    $state['current_card_id'] = $first;
    $_SESSION['drill'] = $state;

    lazy_reset_drill_too_hard($pdo, $person_id, $first, $today);

    header('Location: drill.php');
    exit;
}

// $card_ids_filter: nur im Themen-Modus gesetzt — schränkt zusätzlich zu $list_ids auf diese
// Karten-IDs ein (auch vorgemerkte Karten ohne diesen Tag bleiben dann aussen vor, siehe
// start_drill_session()).
function load_drill_pool(PDO $pdo, int $person_id, array $list_ids, string $today, ?array $card_ids_filter = null): array {
    $placeholders = implode(',', array_fill(0, count($list_ids), '?'));
    [$card_filter_sql, $card_filter_params] = card_id_filter_sql($card_ids_filter);
    $params = array_merge([$person_id], $list_ids, $card_filter_params, [$today]);

    // Vorgemerkte Karten (drill_pinned_correct IS NOT NULL) sind von der drill_too_hard-Tagessperre
    // ausgenommen — sie sollen trotz wiederholtem "Musste nachdenken" im Pool bleiben.
    $stmt = $pdo->prepare("
        SELECT cp.card_id, cp.drill_mastery, cp.drill_pinned_correct
        FROM card_progress cp
        JOIN cards c ON c.id = cp.card_id
        WHERE cp.person_id = ?
          AND c.list_id IN ($placeholders){$card_filter_sql}
          AND cp.status != 'archived'
          AND (cp.drill_pinned_correct IS NOT NULL
               OR cp.drill_too_hard = 0
               OR (cp.drill_too_hard = 1 AND (cp.last_drill_shown IS NULL OR cp.last_drill_shown < ?)))
        ORDER BY RAND()
    ");
    $stmt->execute($params);

    // Eine Karte landet ausschliesslich in einem der drei Pools — pinned hat Vorrang vor
    // known/new, damit der Antwort-Handler eindeutig weiss, welcher Zweig zuständig ist.
    $known  = [];
    $new    = [];
    $pinned = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['drill_pinned_correct'] !== null) {
            $pinned[] = (int)$row['card_id'];
        } elseif ((int)$row['drill_mastery'] >= 1) {
            $known[] = (int)$row['card_id'];
        } else {
            $new[] = (int)$row['card_id'];
        }
    }
    return ['known' => $known, 'new' => $new, 'pinned' => $pinned];
}

// Begrenzt den aktiven Pool (known+new, ohne pinned) auf eine an die Session-Länge gekoppelte
// Grösse und legt den Rest als Reserve beiseite (siehe replenish_active_pool()) — sonst wird bei
// einer grossen Liste sofort die komplette Liste in die Rotation geladen und die Wiederholung
// jeder einzelnen Karte verdünnt sich so stark, dass die Mastery-Schwelle (X× hintereinander
// richtig) in einer kurzen Session praktisch nie erreicht wird (siehe DRILL_CARDS_PER_MINUTE in
// config.php). Beide Arrays kommen bereits per SQL RAND() sortiert aus load_drill_pool() — ein
// einfaches Abschneiden ist damit schon eine Zufallsauswahl, kein erneutes Mischen nötig. Bekannte
// Karten (bereits mit Fortschritt) werden bevorzugt behalten, neue Karten füllen den verbleibenden
// Platz auf. Gibt die berechnete Zielgrösse zurück, damit replenish_active_pool() später denselben
// Wert als Ziel für den Nachschub aus der Reserve nutzt.
function limit_active_pool(array &$pool_known, array &$pool_new, array &$reserve_known, array &$reserve_new, int $session_seconds): int {
    $minutes = $session_seconds / 60;
    $max_active = max(DRILL_MIN_ACTIVE_CARDS, (int) round($minutes * DRILL_CARDS_PER_MINUTE));

    if (count($pool_known) + count($pool_new) > $max_active) {
        $keep_known    = min(count($pool_known), $max_active);
        $reserve_known = array_slice($pool_known, $keep_known);
        $pool_known    = array_slice($pool_known, 0, $keep_known);

        $keep_new    = max(0, $max_active - $keep_known);
        $reserve_new = array_slice($pool_new, $keep_new);
        $pool_new    = array_slice($pool_new, 0, $keep_new);
    }

    return $max_active;
}

// Füllt den aktiven Pool während der Session aus der beim Start beiseitegelegten Reserve wieder auf,
// sobald er unter die ursprüngliche Zielgrösse gefallen ist (z.B. weil eine Karte gemeistert oder
// als zu schwer markiert wurde). Ohne das würde eine schnelle/genaue Person ihr bewusst klein
// gehaltenes Deck (siehe limit_active_pool()) vor Ablauf des Timers komplett leeren und die Session
// bräche vorzeitig ab, obwohl die grosse Liste noch längst nicht ausgeschöpft ist — der Timer soll
// weiterhin allein über das Sessionende entscheiden. Bekannte Reserve-Karten zuerst, analog zur
// Priorisierung in limit_active_pool(). Ist auch die Reserve leer, bleibt der Pool einfach kleiner
// — dann ist tatsächlich die komplette Liste in Bearbeitung, kein Bug. Auf ältere, bereits laufende
// Sessions ohne diese Felder (Deploy mitten in einer Session) wirkt die Funktion als No-Op.
function replenish_active_pool(array &$state): void {
    if (!isset($state['max_active_cards'], $state['reserve_known'], $state['reserve_new'])) {
        return;
    }

    $need = $state['max_active_cards'] - (count($state['pool_known']) + count($state['pool_new']));
    if ($need <= 0) {
        return;
    }

    $take_known = min($need, count($state['reserve_known']));
    if ($take_known > 0) {
        array_push($state['pool_known'], ...array_splice($state['reserve_known'], 0, $take_known));
        $need -= $take_known;
    }

    if ($need > 0 && $state['reserve_new']) {
        $take_new = min($need, count($state['reserve_new']));
        array_push($state['pool_new'], ...array_splice($state['reserve_new'], 0, $take_new));
    }
}

// Wählt die nächste Karte. Vorgemerkte Karten (pool_pinned) werden priorisiert eingeschoben:
// Modus 'absolute' = immer zuerst, solange welche vorgemerkt sind; Modus 'weighted' = alle
// DRILL_PIN_RATIO Karten eine vorgemerkte einschieben. Für die übrigen Karten steuert das
// bekannte 9:1-Prinzip weiterhin, wie oft eine neue (bisher ungezeigte) Karte eingeführt wird —
// WELCHE bereits im Deck befindliche Karte dabei gezogen wird, ist seit v3.8.0 zufällig statt
// striktem Reihum (siehe pick_random_known_card()). Eine feste Rotation liess alle Karten eines
// Decks gleich oft dran kommen, wodurch sie fast gleichzeitig die Mastery-Schwelle erreichten
// ("Batch-Meistern", danach wurde das ganze Deck auf einen Schlag über die Reserve ersetzt) und
// die Reihenfolge vorhersehbar war (Bugreport, siehe docs/Checkliste.md-Historie).
function next_drill_card(array &$state): ?int {
    $has_pinned = !empty($state['pool_pinned']);
    $has_known  = !empty($state['pool_known']);
    $has_new    = !empty($state['pool_new']);

    if (!$has_pinned && !$has_known && !$has_new) return null;

    $pin_due = $has_pinned && (
        DRILL_PIN_MODE === 'absolute'
        || $state['pin_cycle_pos'] >= DRILL_PIN_RATIO
        || (!$has_known && !$has_new)
    );

    if ($pin_due) {
        $state['pin_cycle_pos'] = 0;
        $id = array_shift($state['pool_pinned']);
        $state['pool_pinned'][] = $id;
        return $id;
    }

    $ratio    = DRILL_KNOWN_RATIO;
    $pick_new = ($state['cycle_pos'] >= $ratio) || !$has_known;

    if ($pick_new && $has_new) {
        $state['cycle_pos'] = 0;
        $id = array_shift($state['pool_new']);
        $state['pool_known'][] = $id;
    } else {
        $state['cycle_pos']++;
        $id = pick_random_known_card($state);
    }
    if ($has_pinned) $state['pin_cycle_pos']++;
    return $id;
}

// Zieht eine zufällige Karte aus pool_known statt striktem Reihum — pool_known ist dadurch ein
// ungeordneter Vorrat, kein FIFO mehr (kein array_shift()/Wiederanhängen nötig, die Reihenfolge
// im Array ist bedeutungslos). Schliesst die gerade angezeigte Karte von der Auswahl aus, sofern
// es eine Alternative gibt, damit dieselbe Karte nicht zweimal direkt hintereinander erscheint.
function pick_random_known_card(array $state): int {
    $pool = $state['pool_known'];
    if (count($pool) > 1) {
        $rest = array_values(array_filter($pool, fn($id) => $id !== $state['current_card_id']));
        if ($rest) $pool = $rest;
    }
    return $pool[array_rand($pool)];
}

// Richtige Antwort auf eine vorgemerkte Karte: eigener Zähler, rührt leitner_box/status/
// drill_mastery nie an. Das UPDATE ist bedingt auf "noch vorgemerkt" — wurde die Vormerkung
// zwischenzeitlich manuell entfernt (z.B. zweiter Tab während laufender Session), wird dieses
// Increment zum No-Op statt die Vormerkung ungewollt wiederzubeleben.
function pin_progress_correct(PDO $pdo, array &$state, int $person_id, int $card_id): void {
    $stmt = $pdo->prepare("
        UPDATE card_progress SET drill_pinned_correct = drill_pinned_correct + 1
        WHERE person_id = ? AND card_id = ? AND drill_pinned_correct IS NOT NULL
    ");
    $stmt->execute([$person_id, $card_id]);

    $stmt = $pdo->prepare("SELECT drill_pinned_correct FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $value = $stmt->fetchColumn();

    if ($value !== false && $value !== null && (int)$value >= DRILL_MASTERY_THRESHOLD) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_pinned_correct = NULL WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$person_id, $card_id]);
        $state['pool_pinned'] = array_values(array_filter($state['pool_pinned'], fn($id) => $id !== $card_id));
    }
}

// Falsche Antwort auf eine vorgemerkte Karte: Zähler zurück auf 0 (wie session_correct bei
// known/new), aber keine drill_too_hard-Sperre — Karte bleibt im pool_pinned.
function pin_progress_reset(PDO $pdo, int $person_id, int $card_id): void {
    $stmt = $pdo->prepare("
        UPDATE card_progress SET drill_pinned_correct = 0
        WHERE person_id = ? AND card_id = ? AND drill_pinned_correct IS NOT NULL
    ");
    $stmt->execute([$person_id, $card_id]);
}

function master_card(PDO $pdo, array &$state, int $person_id, int $card_id, string $today): void {
    $state['mastered_cards'][] = $card_id;
    $state['stats']['mastered']++;
    remove_from_pools($state, $card_id);

    $stmt = $pdo->prepare("SELECT drill_mastery, leitner_box FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    $new_mastery = (int)($cp['drill_mastery'] ?? 0) + 1;
    $current_box = (int)($cp['leitner_box'] ?? 1);

    $leitner_transitions = [1 => 2, 2 => 3, 3 => 4];
    $target_box = $leitner_transitions[$new_mastery] ?? null;
    $intervals  = LEITNER_INTERVALS;

    // Meistern ist eine Belohnung und darf das Fach nie zurückstufen. Ist die Karte über normales
    // Leitner-Lernen (unabhängig vom Drill) bereits weiter als die feste Zieltabelle vorsieht,
    // bleibt das Fach unverändert — nur drill_mastery zählt weiter (Bugfix: bisher konnte eine
    // erneute Meisterung eine bereits weiter fortgeschrittene Karte zurückstufen).
    if ($target_box !== null && $target_box <= $current_box) {
        $target_box = null;
    }

    if ($target_box) {
        $due = date('Y-m-d', strtotime($today . ' +' . $intervals[$target_box] . ' days'));
        $stmt = $pdo->prepare("
            UPDATE card_progress
            SET drill_mastery = ?, leitner_box = ?, next_due_date = ?, status = 'active'
            WHERE person_id = ? AND card_id = ?
        ");
        $stmt->execute([$new_mastery, $target_box, $due, $person_id, $card_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_mastery = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$new_mastery, $person_id, $card_id]);
    }
}

function mark_too_hard_card(PDO $pdo, array &$state, int $person_id, int $card_id): void {
    $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 1 WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $state['too_hard'][] = $card_id;
    remove_from_pools($state, $card_id);
}

function remove_from_pools(array &$state, int $card_id): void {
    $state['pool_known'] = array_values(array_filter($state['pool_known'], fn($id) => $id !== $card_id));
    $state['pool_new']   = array_values(array_filter($state['pool_new'],   fn($id) => $id !== $card_id));
}

function lazy_reset_drill_too_hard(PDO $pdo, int $person_id, int $card_id, string $today): void {
    $stmt = $pdo->prepare("SELECT drill_too_hard, last_drill_shown FROM card_progress WHERE person_id = ? AND card_id = ?");
    $stmt->execute([$person_id, $card_id]);
    $cp = $stmt->fetch();
    if ($cp && $cp['drill_too_hard'] && $cp['last_drill_shown'] < $today) {
        $stmt = $pdo->prepare("UPDATE card_progress SET drill_too_hard = 0, last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE card_progress SET last_drill_shown = ? WHERE person_id = ? AND card_id = ?");
        $stmt->execute([$today, $person_id, $card_id]);
    }
}

function finish_drill_session(PDO $pdo, array &$state, int $person_id): void {
    $_SESSION['drill_done'] = [
        'stats'        => $state['stats'],
        'mastered_ids' => $state['mastered_cards'],
        'list_ids'     => $state['list_ids'],
        'tag'          => $state['tag'] ?? null,
    ];

    if ($state['mastered_cards']) {
        $ids = $state['mastered_cards'];
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT card_id, drill_mastery FROM card_progress WHERE person_id = ? AND card_id IN ($ph)");
        $stmt->execute(array_merge([$person_id], $ids));
        $_SESSION['drill_done']['mastery_details'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    unset($_SESSION['drill']);
}

// -------------------------------------------------------
// Anzeige-State
// -------------------------------------------------------
$state     = $_SESSION['drill'] ?? null;
$card_data = null;

$qa = null;
if ($state && $state['current_card_id']) {
    $stmt = $pdo->prepare("
        SELECT c.*, l.language_a, l.language_b, l.speech_lang_b, cp.drill_pinned_correct
        FROM cards c
        JOIN lists l ON l.id = c.list_id
        LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$person_id, $state['current_card_id']]);
    $card_data = $stmt->fetch() ?: null;
    if ($card_data) {
        $qa = get_question_answer($card_data, $state['direction']);
    }
}

$remaining_s = 0;
if ($state) {
    $remaining_s = max(0, $state['session_seconds'] - (time() - $state['started_at']));
}

$done_data = null;
if (isset($_GET['done']) && isset($_SESSION['drill_done'])) {
    $done_data = $_SESSION['drill_done'];
    unset($_SESSION['drill_done']);
}

// SETUP: weder laufende Session noch Abschluss → Listenauswahl/Richtung/Timer anzeigen
$setup_error = '';
if (!$state && !$done_data) {
    $setup_error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_error']);
}
$default_drill_minutes = (int) round(DRILL_SESSION_SECONDS / 60);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drill — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php if ($state): ?>
<nav class="navbar navbar-expand-sm navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap justify-content-end">
            <!-- Abbruch an erster Stelle, gleiche Position wie in der zentralen Navbar (auth.php) -->
            <a href="drill.php?action=abort" class="btn btn-sm btn-outline-light"
               title="Session abbrechen" aria-label="Session abbrechen"><i class="bi bi-x-lg"></i></a>
            <span class="text-white small fw-semibold" id="drill-timer"></span>
            <span class="text-white small opacity-75">·</span>
            <span class="text-white small"><?= (int)($state['stats']['mastered'] ?? 0) ?> gemeistert</span>
            <a href="help.php" class="btn btn-sm btn-outline-light" title="Hilfe" aria-label="Hilfe"><i class="bi bi-info-lg"></i></a>
        </div>
    </div>
</nav>
<?php else: ?>
<?php render_navbar($pdo); ?>
<?php endif; ?>

<div class="container mt-3"><?= breadcrumb([['Startseite', 'home.php'], ['Drill', '']]) ?></div>

<div class="container mt-2 mb-5" style="max-width:700px;">

<?php if ($done_data !== null): ?>
<!-- ==================== ABSCHLUSS ==================== -->
<?= debug_panel() ?>
<div class="text-center">
    <div class="display-6 mb-2">
        <?= ($done_data['stats']['mastered'] ?? 0) > 0 ? '🎉' : '💪' ?>
    </div>
    <h2 class="h4 mb-4">
        <?= ($done_data['stats']['mastered'] ?? 0) > 0
            ? 'Super! Weiter so!'
            : 'Gut gemacht! Regelmässiges Üben zahlt sich aus.' ?>
    </h2>

    <div class="row g-3 mb-4 justify-content-center">
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-success"><?= $done_data['stats']['known'] ?></div>
                <div class="small text-muted">Gewusst</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-warning"><?= $done_data['stats']['unknown'] ?></div>
                <div class="small text-muted">Musste nachdenken</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card text-center px-4 py-3">
                <div class="h3 text-primary"><?= $done_data['stats']['mastered'] ?></div>
                <div class="small text-muted">Gemeistert</div>
            </div>
        </div>
    </div>

    <?php if (!empty($done_data['mastery_details'])): ?>
    <div class="card mb-4 text-start">
        <div class="card-header">Drill-Fortschritt gemeisterter Karten</div>
        <div class="card-body">
            <?php foreach ($done_data['mastery_details'] as $cid => $mastery):
                $sc = $pdo->prepare("SELECT word_a, word_b FROM cards WHERE id = ?");
                $sc->execute([$cid]);
                $cdata = $sc->fetch();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small"><?= htmlspecialchars($cdata['word_a'] ?? '') ?> / <?= htmlspecialchars($cdata['word_b'] ?? '') ?></span>
                <span class="badge bg-primary"><?= (int)$mastery ?>×</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-muted small">Für beste Resultate warte ein paar Stunden bis zur nächsten Session.</p>

    <?php if (!empty($done_data['tag'])): ?>
    <a href="drill.php?tag=<?= urlencode($done_data['tag']) ?>" class="btn btn-primary">Erneut starten</a>
    <?php elseif (count($done_data['list_ids'] ?? []) === 1): ?>
    <a href="drill.php?list_id=<?= (int)$done_data['list_ids'][0] ?>" class="btn btn-primary">Erneut starten</a>
    <?php endif; ?>
</div>

<?php elseif ($state && $card_data): ?>
<!-- ==================== KARTE ==================== -->

<div class="learn-card mx-auto mb-4 position-relative" id="flip-card" style="max-width:540px; cursor:pointer;" onclick="flipCard()">
    <?php $is_pinned = $card_data['drill_pinned_correct'] !== null; ?>
    <form method="post" id="pin-form" class="position-absolute" style="top:8px; left:8px; z-index:2;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_pin">
        <input type="hidden" name="card_id" value="<?= (int)$card_data['id'] ?>">
        <button type="submit" class="btn btn-sm <?= $is_pinned ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-circle"
                onclick="event.stopPropagation();"
                title="<?= $is_pinned ? 'Vormerkung entfernen' : 'Für Drill vormerken' ?>">
            <i class="bi <?= $is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' ?>"></i>
        </button>
    </form>
    <div class="text-center p-5" style="min-height:280px;">
        <p class="text-muted small mb-2"><?= htmlspecialchars($qa['q_lang']) ?></p>
        <div class="fw-bold fs-2 mb-1">
            <?= htmlspecialchars($qa['q']) ?>
            <?php if ($qa['q_audio']): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary align-middle ms-1"
                    onclick="event.stopPropagation(); speakWord(this)"
                    data-speak="<?= htmlspecialchars($qa['q_audio']) ?>" data-lang="<?= htmlspecialchars($card_data['speech_lang_b']) ?>">
                <i class="bi bi-volume-up-fill"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php if ($qa['q_phonetic']): ?>
        <p class="text-muted small mb-1">[<?= htmlspecialchars($qa['q_phonetic']) ?>]</p>
        <?php endif; ?>
        <?php if ($qa['q_desc']): ?>
        <p class="text-muted mb-0"><?= htmlspecialchars($qa['q_desc']) ?></p>
        <?php endif; ?>

        <div id="card-back" style="display:none;">
            <hr class="my-3">
            <p class="text-muted small mb-1"><?= htmlspecialchars($qa['a_lang']) ?></p>
            <div class="fw-bold fs-3 text-success mb-0">
                <?= htmlspecialchars($qa['a']) ?>
                <?php if ($qa['a_audio']): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary align-middle ms-1"
                        onclick="event.stopPropagation(); speakWord(this)"
                        data-speak="<?= htmlspecialchars($qa['a_audio']) ?>" data-lang="<?= htmlspecialchars($card_data['speech_lang_b']) ?>">
                    <i class="bi bi-volume-up-fill"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php if ($qa['a_phonetic']): ?>
            <p class="text-muted small mb-1">[<?= htmlspecialchars($qa['a_phonetic']) ?>]</p>
            <?php endif; ?>
            <?php if ($qa['a_desc']): ?>
            <p class="text-muted mt-1 mb-0"><?= htmlspecialchars($qa['a_desc']) ?></p>
            <?php endif; ?>
        </div>

        <p class="text-muted small mt-4 mb-0" id="flip-hint">Tippen zum Aufdecken</p>
    </div>
</div>

<div id="answer-btns" class="d-none d-flex gap-3 justify-content-center">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="answer">
        <input type="hidden" name="card_id" value="<?= (int)$card_data['id'] ?>">
        <input type="hidden" name="result" value="unknown">
        <button type="submit" class="btn btn-warning btn-lg">Musste nachdenken</button>
    </form>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="answer">
        <input type="hidden" name="card_id" value="<?= (int)$card_data['id'] ?>">
        <input type="hidden" name="result" value="known">
        <button type="submit" class="btn btn-success btn-lg">Gewusst</button>
    </form>
</div>

<?= debug_panel() ?>

<?php elseif (!$state && !$done_data): ?>
<!-- ==================== SETUP ==================== -->
<h1 class="h4 mb-4"><i class="bi bi-stopwatch text-primary me-2"></i>Drill-Session starten</h1>

<?php if ($setup_error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($setup_error) ?></div>
<?php endif; ?>

<?php if (!$all_lists): ?>
<p class="text-muted">Du hast noch keine Listen. <a href="lists.php">Erstelle zuerst eine Liste</a>.</p>
<?php else: ?>
<?php
$lang_a = $preset_list ? htmlspecialchars($preset_list['language_a']) : 'A';
$lang_b = $preset_list ? htmlspecialchars($preset_list['language_b']) : 'B';

// Vorauswahl der Lernrichtung aus dem Profil (profile.php) statt hartcodiert "Zufall" — Fallback
// bleibt 'random', falls nichts gesetzt (Bestandspersonen / DB-Default).
$stmt = $pdo->prepare("SELECT default_direction FROM persons WHERE id = ?");
$stmt->execute([$person_id]);
$default_direction = $stmt->fetchColumn() ?: 'random';

// Bei Mathe-Listen (siehe is_math_list()) ist nur "Aufgabe → Ergebnis" sinnvoll — die anderen
// Richtungen werden ausgeblendet und a_to_b fest vorausgewählt.
$is_math_preset = $preset_list && is_math_list($preset_list);
?>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="begin">

    <?php
    // "Was lernen": Mathe / Sprachen / Thema als segmentierte Toggle-Buttons (dasselbe btn-check-
    // Muster wie bei Lernrichtung weiter unten) statt gestapelter Sektionen. Mathe/Sprachen sind
    // wegen der Typ-Sperre ohnehin nie gleichzeitig kombinierbar, deshalb hier gleich als getrennte
    // Modi. Bei vorausgewählter Einzelliste (?list_id=…) entfällt die Mathe/Sprachen-Aufteilung.
    $math_lists = $preset_list ? [] : array_filter($all_lists, fn($l) => is_math_list($l));
    $word_lists = $preset_list ? [] : array_filter($all_lists, fn($l) => !is_math_list($l));

    $modes = [];
    if ($preset_list) {
        $modes['liste'] = ['icon' => 'bi-collection', 'label' => 'Liste'];
    } else {
        if ($math_lists) $modes['math'] = ['icon' => 'bi-calculator', 'label' => 'Mathe'];
        if ($word_lists) $modes['word'] = ['icon' => 'bi-translate', 'label' => 'Sprachen'];
    }
    if ($available_tags) $modes['tag'] = ['icon' => 'bi-tags', 'label' => 'Thema'];

    $default_mode = $preset_tag !== '' ? 'tag' : (array_key_first(array_diff_key($modes, ['tag' => 1])) ?? 'tag');
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <?php if (count($modes) > 1): ?>
            <div class="row row-cols-<?= count($modes) ?> g-2 mb-3" id="mode-select">
                <?php foreach ($modes as $key => $m): ?>
                <div class="col">
                    <input type="radio" class="btn-check" name="mode_select" id="mode_<?= $key ?>" autocomplete="off"
                           data-pane="mode-<?= $key ?>-pane" <?= $default_mode === $key ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary w-100" for="mode_<?= $key ?>"><i class="bi <?= $m['icon'] ?> me-1"></i><?= $m['label'] ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($preset_list): ?>
            <!-- Vorausgewählte Liste (von Startseite oder "Erneut starten") -->
            <div id="mode-liste-pane" class="mode-pane" style="<?= $default_mode === 'liste' ? '' : 'display:none;' ?>">
                <input type="hidden" name="list_ids[]" value="<?= $preset_list['id'] ?>">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text text-muted fs-4"></i>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($preset_list['name']) ?></div>
                        <div class="text-muted small"><?= $lang_a ?> / <?= $lang_b ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Listen je Typ als volle, klickbare Zeilen (list-group). Initial ist bewusst keine
                 Liste angehakt (siehe Checkliste). -->
            <?php if ($math_lists): ?>
            <div id="mode-math-pane" class="mode-pane" style="<?= $default_mode === 'math' ? '' : 'display:none;' ?>">
                <div class="list-group">
                    <?php foreach ($math_lists as $list): ?>
                    <label class="list-group-item d-flex align-items-center gap-2" for="list_<?= $list['id'] ?>">
                        <input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="list_ids[]"
                               value="<?= $list['id'] ?>" id="list_<?= $list['id'] ?>" data-math="1">
                        <span><?= htmlspecialchars($list['name']) ?>
                            <span class="text-muted small">(<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($word_lists): ?>
            <div id="mode-word-pane" class="mode-pane" style="<?= $default_mode === 'word' ? '' : 'display:none;' ?>">
                <div class="list-group">
                    <?php foreach ($word_lists as $list): ?>
                    <label class="list-group-item d-flex align-items-center gap-2" for="list_<?= $list['id'] ?>">
                        <input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="list_ids[]"
                               value="<?= $list['id'] ?>" id="list_<?= $list['id'] ?>" data-math="0">
                        <span><?= htmlspecialchars($list['name']) ?>
                            <span class="text-muted small">(<?= htmlspecialchars($list['language_a']) ?> / <?= htmlspecialchars($list['language_b']) ?>)</span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($available_tags): ?>
            <!-- Themen-Session: listenübergreifend über alle eigenen Karten mit diesem Tag, siehe
                 begin-Handler. -->
            <div id="mode-tag-pane" class="mode-pane" style="<?= $default_mode === 'tag' ? '' : 'display:none;' ?>">
                <div id="tag-cloud-section">
                    <div class="d-flex gap-2 flex-wrap mb-2" id="tag-cloud">
                        <?php foreach ($available_tags as $t): $tid = 'tag_' . md5($t); ?>
                        <div data-tag="<?= htmlspecialchars($t) ?>">
                            <input type="radio" class="btn-check" name="tag" id="<?= $tid ?>" autocomplete="off"
                                   value="<?= htmlspecialchars($t) ?>" <?= $preset_tag === $t ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm rounded-pill" for="<?= $tid ?>">#<?= htmlspecialchars($t) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">
                        Gilt listenübergreifend über alle eigenen Karten mit diesem Tag — unabhängig von der Liste, aus der sie stammen.
                        <a href="#" id="clear-tag-link" style="<?= $preset_tag === '' ? 'display:none;' : '' ?>">Thema entfernen</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lernrichtung: bei Mathe-Auswahl ist sie immer dieselbe (Aufgabe→Ergebnis) — die ganze
         Sektion ist dann überflüssig statt nur eine "eingefrorene" Auswahl zu zeigen. Serverseitig
         ohnehin erzwungen (start_drill_session()), unabhängig davon ob überhaupt ein
         direction-Wert übermittelt wird — das Formularfeld darf also komplett fehlen. Segmentierte
         Toggle-Buttons (Bootstrap btn-check) statt gestapelter Radios. -->
    <div class="card shadow-sm mb-3" id="direction-section" style="<?= $is_math_preset ? 'display:none;' : '' ?>">
        <div class="card-body">
            <label class="form-label fw-semibold">Lernrichtung</label>
            <div class="row row-cols-2 g-2">
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_ab" value="a_to_b" autocomplete="off" <?= $default_direction === 'a_to_b' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary w-100" for="dir_ab" id="label_ab"><?= $lang_a ?> → <?= $lang_b ?></label>
                </div>
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_ba" value="b_to_a" autocomplete="off" <?= $default_direction === 'b_to_a' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary w-100" for="dir_ba" id="label_ba"><?= $lang_b ?> → <?= $lang_a ?></label>
                </div>
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_mix" value="mixed" autocomplete="off" <?= $default_direction === 'mixed' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary w-100" for="dir_mix">Gemischt</label>
                </div>
                <div class="col">
                    <input type="radio" class="btn-check" name="direction" id="dir_random" value="random" autocomplete="off" <?= $default_direction === 'random' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary w-100" for="dir_random">Zufall</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Timer (nur für diese Session, wird nicht dauerhaft gespeichert) -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold">Timer</label>
            <div class="input-group" style="max-width:220px;">
                <button type="button" class="btn btn-outline-secondary" onclick="adjustMinutes(-5)">−5</button>
                <input type="number" name="session_minutes" id="session_minutes" class="form-control text-center" value="<?= $default_drill_minutes ?>" min="1" max="120">
                <span class="input-group-text">Min.</span>
                <button type="button" class="btn btn-outline-secondary" onclick="adjustMinutes(5)">+5</button>
            </div>
            <div class="form-text">Gilt nur für diese Session. Standard aus den Einstellungen: <?= $default_drill_minutes ?> Min.</div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-play-fill me-1"></i>Drill starten</button>
</form>
<?php endif; ?>

<?php endif; ?>
</div>

<!-- Zusätzlicher leerer Bereich am Seitenende — der Container-Abstand (mb-5) allein reicht auf
     Mobilgeräten oft nicht: Browser-UI (Adressleiste/Home-Indicator) verdeckt sonst leicht den
     letzten Button bzw. die letzte Karte beim Scrollen. -->
<div style="height:15vh;"></div>

<?php if ($state && $card_data): ?>
<div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Session verlassen?</h5>
            </div>
            <div class="modal-body">
                Achtung: die laufende Session wird dadurch automatisch beendet.
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="confirmLeave">Verlassen</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
// Verhindert dass bfcache eine aufgeklappte Karte mit sichtbarer Antwort wiederherstellt
window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
});

function speakWord(btn) {
    if (!('speechSynthesis' in window)) return;
    var text = btn.dataset.speak;
    var lang = btn.dataset.lang;
    var u = new SpeechSynthesisUtterance(text);
    u.lang = lang;
    // utterance.lang allein wird von manchen Browsern/Geräten ignoriert und fällt auf die
    // Standardstimme des Systems zurück — passende Stimme explizit suchen und setzen.
    var voices = window.speechSynthesis.getVoices();
    var match = voices.find(function (v) { return v.lang === lang; })
             || voices.find(function (v) { return v.lang.split('-')[0] === lang.split('-')[0]; });
    if (match) u.voice = match;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(u);
}

function adjustMinutes(delta) {
    const el = document.getElementById('session_minutes');
    if (el) el.value = Math.max(1, Math.min(120, parseInt(el.value || <?= $default_drill_minutes ?>) + delta));
}

// "Was lernen": Mathe/Sprachen/Thema-Segmentbuttons schalten die zugehörige Sektion sichtbar,
// alle anderen aus — genau ein mode-pane ist je Auswahl sichtbar. Kein PHP-Guard nötig: existiert
// die Radiogruppe nicht (nur ein einziger Modus verfügbar), ist die NodeList einfach leer. Ein
// Moduswechsel setzt Listen- und Themenauswahl komplett zurück — sonst könnte eine unsichtbare,
// aber weiterhin angehakte Liste/ein Tag aus dem vorherigen Modus mit abgeschickt werden.
document.querySelectorAll('input[name="mode_select"]').forEach(function (r) {
    r.addEventListener('change', function () {
        document.querySelectorAll('.mode-pane').forEach(function (p) { p.style.display = 'none'; });
        var pane = document.getElementById(r.dataset.pane);
        if (pane) pane.style.display = '';

        document.querySelectorAll('input[name="list_ids[]"]').forEach(function (cb) { cb.checked = false; });
        document.querySelectorAll('input[name="tag"]').forEach(function (t) { t.checked = false; });

        if (typeof updateDirLabels === 'function') updateDirLabels();
        if (typeof window.__updateTagUI === 'function') window.__updateTagUI();
    });
});

<?php if (!$state && !$done_data && !$preset_list && $all_lists): ?>
// Richtungs-Labels bei Mehrfach-Listenauswahl dynamisch aktualisieren
const langMap = <?= json_encode(array_combine(
    array_column($all_lists, 'id'),
    array_map(fn($l) => ['a' => $l['language_a'], 'b' => $l['language_b'], 'math' => is_math_list($l)], $all_lists)
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function updateDirLabels() {
    const checked = Array.from(document.querySelectorAll('input[name="list_ids[]"]:checked'));
    const first = checked[0];
    const langs = first && langMap[first.value] ? langMap[first.value] : {a: 'A', b: 'B'};
    document.getElementById('label_ab').textContent = langs.a + ' → ' + langs.b;
    document.getElementById('label_ba').textContent = langs.b + ' → ' + langs.a;

    // Nur wenn AUSSCHLIESSLICH Mathe-Listen ausgewählt sind, ist "Aufgabe → Ergebnis" die einzig
    // sinnvolle Richtung — die ganze Lernrichtung-Sektion ist dann überflüssig (serverseitig
    // ohnehin erzwungen, siehe start_drill_session()). Bei Mischauswahl mit Wortlisten (aktuell
    // durch die Typ-Sperre ausgeschlossen) bliebe sie sichtbar.
    const allMath = checked.length > 0 && checked.every(cb => langMap[cb.value] && langMap[cb.value].math);
    document.getElementById('direction-section').style.display = allMath ? 'none' : '';
}

document.querySelectorAll('input[name="list_ids[]"]').forEach(cb => {
    cb.addEventListener('change', updateDirLabels);
});
updateDirLabels();
<?php endif; ?>

<?php if (!$state && !$done_data && $available_tags): ?>
// Themen-Session: "Thema entfernen"-Link nur einblenden, solange ein Thema gewählt ist.
(function () {
    var tagRadios = document.querySelectorAll('input[name="tag"]');
    var clearLink = document.getElementById('clear-tag-link');
    function updateTagUI() {
        var anyChecked = Array.from(tagRadios).some(function (r) { return r.checked; });
        if (clearLink) clearLink.style.display = anyChecked ? '' : 'none';
    }
    tagRadios.forEach(function (r) { r.addEventListener('change', updateTagUI); });
    if (clearLink) {
        clearLink.addEventListener('click', function (e) {
            e.preventDefault();
            tagRadios.forEach(function (r) { r.checked = false; });
            updateTagUI();
        });
    }

    // Vom mode_select-Handler (Mathe/Sprachen/Thema-Umschalter) aufgerufen, wenn der Thema-Modus
    // verlassen/betreten wird und die Tag-Auswahl entsprechend zurückgesetzt werden muss.
    window.__updateTagUI = updateTagUI;
})();
<?php endif; ?>

<?php if ($state && $card_data): ?>
function flipCard() {
    var card = document.getElementById('flip-card');
    card.style.transform = 'scaleX(0)';
    setTimeout(function () {
        document.getElementById('card-back').style.display = 'block';
        document.getElementById('flip-hint').style.display = 'none';
        card.style.transform = 'scaleX(1)';
    }, 150);
    setTimeout(function () {
        document.getElementById('answer-btns').classList.remove('d-none');
        card.style.cursor = 'default';
        card.onclick = null;
    }, 300);
}

(function () {
    var modal = new bootstrap.Modal(document.getElementById('leaveModal'));
    var target = null;
    document.querySelectorAll('a[href]').forEach(function (link) {
        var href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:')) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            target = href;
            modal.show();
        });
    });
    document.getElementById('confirmLeave').addEventListener('click', function () {
        if (target) window.location.href = 'drill.php?action=abort&to=' + encodeURIComponent(target);
    });
})();

// Vormerken per Fetch statt normalem Form-Submit — ein voller Seiten-Reload würde die aufgedeckte
// Antwort (rein clientseitiger Zustand, siehe flipCard()) wieder verstecken. Analog zu learn.php.
(function () {
    var form = document.getElementById('pin-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = form.querySelector('button');
        var icon = btn.querySelector('i');
        fetch('drill.php', { method: 'POST', body: new FormData(form) }).then(function (res) {
            if (!res.ok) return;
            var wasPinned = icon.classList.contains('bi-pin-angle-fill');
            icon.classList.toggle('bi-pin-angle-fill', !wasPinned);
            icon.classList.toggle('bi-pin-angle', wasPinned);
            btn.classList.toggle('btn-primary', !wasPinned);
            btn.classList.toggle('btn-outline-secondary', wasPinned);
            btn.title = wasPinned ? 'Für Drill vormerken' : 'Vormerkung entfernen';
        });
    });
})();
<?php endif; ?>

(function () {
    let remaining = <?= (int)$remaining_s ?>;
    const el = document.getElementById('drill-timer');
    if (!el || remaining <= 0) return;
    function tick() {
        if (remaining < 0) remaining = 0;
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        if (remaining > 0) { remaining--; setTimeout(tick, 1000); }
    }
    tick();
})();
</script>
</body>
</html>
