<?php
// Gemeinsame Session- und CSRF-Logik — wird in jeder Seite als erstes eingebunden

require_once __DIR__ . '/config.php';

// Eigenes Session-Verzeichnis statt System-Standardpfad: viele Hoster (v.a. Debian/Ubuntu) räumen
// den Standardpfad per eigenem Cron-Job auf, basierend auf dem GLOBALEN php.ini-Wert — das läuft
// unabhängig von jedem ini_set() aus der App und löscht Sessions oft schon nach ~24 Min., egal was
// SESSION_TIMEOUT sagt. Ausserhalb dieses Pfads greift der Cron nicht mehr.
$_session_dir = __DIR__ . '/sessions';
if (!is_dir($_session_dir)) {
    mkdir($_session_dir, 0700, true);
}
session_save_path($_session_dir);

// Debian/Ubuntu deaktivieren PHPs eigene Garbage-Collection meist global (gc_probability=0), weil
// sonst der System-Cron zuständig ist — für unser eigenes Verzeichnis müssen wir sie deshalb explizit
// wieder aktivieren, sonst würden alte Sessions hier nie mehr gelöscht.
// Zusätzlich: gc_maxlifetime UND Cookie-Lebensdauer an SESSION_TIMEOUT koppeln — sonst löscht PHP die
// Session serverseitig bzw. der Browser das Cookie schon lange vor dem eigenen Inaktivitäts-Check unten.
$_session_timeout_seconds = SESSION_TIMEOUT * 60;
ini_set('session.gc_maxlifetime', (string) $_session_timeout_seconds);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
session_set_cookie_params([
    'lifetime' => $_session_timeout_seconds,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Session-Timeout prüfen (SESSION_TIMEOUT ist in Minuten)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT * 60) {
    session_unset();
    session_destroy();
    session_start();
    header('Location: index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Login prüfen (Name + Passwort pro Person)
function require_login(): void {
    if (empty($_SESSION['authenticated'])) {
        header('Location: index.php');
        exit;
    }
}

// Person-Auswahl prüfen
function require_person(): void {
    require_login();
    if (empty($_SESSION['person_id'])) {
        header('Location: home.php');
        exit;
    }
}

// Admin-Rolle prüfen (settings.php, deploy.php, users.php, "Person wechseln")
function require_admin(): void {
    require_person();
    if (empty($_SESSION['is_admin'])) {
        $_SESSION['flash_error'] = 'Nur für Admins zugänglich.';
        header('Location: home.php');
        exit;
    }
}

// CSRF-Token erzeugen (einmal pro Session)
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF-Token als verstecktes Formularfeld ausgeben
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// CSRF-Token validieren — bei Fehler sofort abbrechen
function csrf_validate(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Ungültige Anfrage (CSRF-Fehler). Bitte gehe zurück und versuche es erneut.');
    }
}

// Aktuellen Datum-String in Europe/Zurich zurückgeben (YYYY-MM-DD)
function today(): string {
    return (new DateTimeImmutable('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d');
}

// Streak-Badge für Navbar — liest aus Session-Cache, zeigt nichts wenn kein Person gewählt
function streak_badge(): string {
    if (empty($_SESSION['person_id'])) return '';
    $streak = (int)($_SESSION['streak'] ?? 0);
    if ($streak <= 0 || ($_SESSION['streak_date'] ?? '') !== today()) return '';
    $days = $streak === 1 ? 'Tag' : 'Tage';
    return '<span class="badge bg-warning text-dark">🔥 ' . $streak . ' ' . $days . '</span>';
}

// Logout
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Gemeinsame Navbar-Aktionen (Logout, eigenes Konto, Person wechseln) — wird von jeder Seite
// direkt nach csrf_validate() aufgerufen. Bei Treffer wird die Aktion ausgeführt und zur
// aktuellen URL zurückgeleitet (PRG); bei keinem Treffer kehrt die Funktion einfach zurück,
// damit die Seite ihre eigene, seitenspezifische Aktion weiterverarbeiten kann.
function handle_navbar_actions(PDO $pdo): void {
    $action        = $_POST['action'] ?? '';
    $person_id     = (int) ($_SESSION['person_id'] ?? 0);
    $real_is_admin = !empty($_SESSION['real_is_admin']);
    $back          = $_SERVER['REQUEST_URI'];

    if ($action === 'logout') {
        logout();
    }

    // Nur wer WIRKLICH Admin ist (real_is_admin, unabhängig davon als wen gerade agiert wird)
    // darf die Person wechseln — verhindert, dass man sich beim Wechseln selbst aussperrt.
    if ($action === 'switch_to_person' && $real_is_admin) {
        $id = intval($_POST['person_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, name, is_admin FROM persons WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if ($p) {
            unset($_SESSION['streak'], $_SESSION['streak_date']);
            session_regenerate_id(true);
            $_SESSION['person_id']   = $p['id'];
            $_SESSION['person_name'] = $p['name'];
            // Übernimmt die Berechtigungen der Zielperson (genau das sehen, was sie sieht) —
            // real_is_admin bleibt unverändert, damit "Person wechseln" weiterhin möglich ist.
            $_SESSION['is_admin']    = (bool) $p['is_admin'];
        } else {
            $_SESSION['flash_error'] = 'Person nicht gefunden.';
        }
        header('Location: ' . $back);
        exit;
    }

    if ($action === 'change_own_password') {
        $cur_pw  = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
        $new_pw2 = $_POST['new_password2']    ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM persons WHERE id = ?");
        $stmt->execute([$person_id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($cur_pw, $hash)) {
            $_SESSION['flash_error'] = 'Aktuelles Passwort ist falsch.';
        } elseif (mb_strlen($new_pw) < 8) {
            $_SESSION['flash_error'] = 'Neues Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($new_pw !== $new_pw2) {
            $_SESSION['flash_error'] = 'Die neuen Passwörter stimmen nicht überein.';
        } else {
            $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE persons SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $person_id]);
            $_SESSION['flash_success'] = 'Passwort erfolgreich geändert.';
        }
        header('Location: ' . $back);
        exit;
    }

    if ($action === 'change_own_email') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $stmt = $pdo->prepare("UPDATE persons SET email = NULL WHERE id = ?");
            $stmt->execute([$person_id]);
            $_SESSION['flash_success'] = 'E-Mail-Adresse entfernt.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE persons SET email = ? WHERE id = ?");
                $stmt->execute([$email, $person_id]);
                $_SESSION['flash_success'] = 'E-Mail-Adresse gespeichert.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = $e->getCode() === '23000'
                    ? 'Diese E-Mail-Adresse wird bereits von einer anderen Person verwendet.'
                    : 'Fehler beim Speichern der E-Mail-Adresse.';
            }
        }
        header('Location: ' . $back);
        exit;
    }
}

// Zentrale Navbar — von jeder Seite mit Person-Kontext aufgerufen, damit Icons/Reihenfolge
// nur an einer Stelle gepflegt werden müssen. $abort_url: falls gesetzt, ersetzt "Session
// abbrechen" den Logout-Button (für laufende Leitner-/Drill-Sessions).
function render_navbar(PDO $pdo, ?string $abort_url = null): void {
    $person_id     = $_SESSION['person_id'];
    $person_name   = $_SESSION['person_name'];
    $is_admin      = !empty($_SESSION['is_admin']);       // Berechtigungen der aktuell angezeigten Person
    $real_is_admin = !empty($_SESSION['real_is_admin']);  // wirkliche Berechtigung — steuert "Person wechseln"

    $persons = $real_is_admin ? $pdo->query("SELECT id, name FROM persons ORDER BY name")->fetchAll() : [];

    $stmt = $pdo->prepare("SELECT email FROM persons WHERE id = ?");
    $stmt->execute([$person_id]);
    $own_email = $stmt->fetchColumn() ?: '';
    ?>
    <nav class="navbar navbar-expand-sm navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="home.php"><?= APP_NAME ?></a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?= streak_badge() ?>
                <span class="text-white small"><?= htmlspecialchars($person_name) ?></span>
                <button type="button" class="btn btn-sm btn-outline-light" title="Passwort ändern" aria-label="Passwort ändern"
                        data-bs-toggle="modal" data-bs-target="#pwModal"><i class="bi bi-key"></i></button>
                <?php if ($real_is_admin && count($persons) > 1): ?>
                <div class="dropdown d-inline">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            title="Person wechseln" aria-label="Person wechseln"><i class="bi bi-person-lines-fill"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($persons as $p): ?>
                        <li>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="switch_to_person">
                                <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="dropdown-item<?= $p['id'] == $person_id ? ' active' : '' ?>">
                                    <?= htmlspecialchars($p['name']) ?>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                <a href="users.php" class="btn btn-sm btn-outline-light" title="Benutzerverwaltung" aria-label="Benutzerverwaltung"><i class="bi bi-person-gear"></i></a>
                <a href="settings.php" class="btn btn-sm btn-outline-light" title="Einstellungen" aria-label="Einstellungen"><i class="bi bi-gear"></i></a>
                <?php endif; ?>
                <?php if ($abort_url): ?>
                <a href="<?= htmlspecialchars($abort_url) ?>" class="btn btn-sm btn-outline-light">Session abbrechen</a>
                <?php else: ?>
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-sm btn-outline-light" title="Logout" aria-label="Logout"><i class="bi bi-box-arrow-right"></i></button>
                </form>
                <?php endif; ?>
                <a href="help.php" class="btn btn-sm btn-outline-light" title="Hilfe" aria-label="Hilfe"><i class="bi bi-info-lg"></i></a>
            </div>
        </div>
    </nav>

    <!-- Modal: eigenes Konto (E-Mail + Passwort) -->
    <div class="modal fade" id="pwModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Konto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
          </div>
          <div class="modal-body">

            <form method="post" class="mb-4 pb-4 border-bottom">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="change_own_email">
              <label class="form-label fw-medium">E-Mail-Adresse</label>
              <div class="form-text mb-2">Optional — nur nötig, um das Passwort selbst per E-Mail zurücksetzen zu können.</div>
              <div class="d-flex gap-2">
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($own_email) ?>" placeholder="name@beispiel.ch">
                <button type="submit" class="btn btn-outline-primary flex-shrink-0">Speichern</button>
              </div>
            </form>

            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="change_own_password">
              <label class="form-label fw-medium mb-2">Passwort ändern</label>
              <div class="mb-3">
                <input type="password" name="current_password" class="form-control" placeholder="Aktuelles Passwort" autocomplete="current-password" required>
              </div>
              <div class="mb-3">
                <input type="password" name="new_password" class="form-control" placeholder="Neues Passwort (min. 8 Zeichen)" autocomplete="new-password" minlength="8" required>
              </div>
              <div class="mb-3">
                <input type="password" name="new_password2" class="form-control" placeholder="Neues Passwort (Wiederholung)" autocomplete="new-password" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Passwort ändern</button>
            </form>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schliessen</button>
          </div>
        </div>
      </div>
    </div>
    <?php
}

// Gültige ISO-639-1-Sprachcodes (klein) und ISO-3166-1-Alpha-2-Ländercodes (gross)
// für die Validierung von Aussprache-Sprachcodes (BCP-47, z.B. "en-GB").
const BCP47_LANGUAGES = ['aa','ab','ae','af','ak','am','an','ar','as','av','ay','az','ba','be','bg','bh','bi','bm','bn','bo','br','bs','ca','ce','ch','co','cr','cs','cu','cv','cy','da','de','dv','dz','ee','el','en','eo','es','et','eu','fa','ff','fi','fj','fo','fr','fy','ga','gd','gl','gn','gu','gv','ha','he','hi','ho','hr','ht','hu','hy','hz','ia','id','ie','ig','ii','ik','io','is','it','iu','ja','jv','ka','kg','ki','kj','kk','kl','km','kn','ko','kr','ks','ku','kv','kw','ky','la','lb','lg','li','ln','lo','lt','lu','lv','mg','mh','mi','mk','ml','mn','mr','ms','mt','my','na','nb','nd','ne','ng','nl','nn','no','nr','nv','ny','oc','oj','om','or','os','pa','pi','pl','ps','pt','qu','rm','rn','ro','ru','rw','sa','sc','sd','se','sg','si','sk','sl','sm','sn','so','sq','sr','ss','st','su','sv','sw','ta','te','tg','th','ti','tk','tl','tn','to','tr','ts','tt','tw','ty','ug','uk','ur','uz','ve','vi','vo','wa','wo','xh','yi','yo','za','zh','zu'];
const BCP47_REGIONS = ['AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ','BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ','CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR','GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY','HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP','KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY','MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ','NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY','QA','RE','RO','RS','RU','RW','SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ','TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ','UA','UG','UM','US','UY','UZ','VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZW'];

// Aussprache-Sprachcode normalisieren und validieren (BCP-47, z.B. "en-gb" → "en-GB").
// Gibt den normalisierten Code zurück, oder null wenn ungültig/leer.
function normalize_speech_lang(string $input): ?string {
    $input = trim($input);
    if ($input === '') return null;
    if (!preg_match('/^([a-zA-Z]{2,3})-([a-zA-Z]{2})$/', $input, $m)) return null;

    $lang   = strtolower($m[1]);
    $region = strtoupper($m[2]);

    if (!in_array($lang, BCP47_LANGUAGES, true))  return null;
    if (!in_array($region, BCP47_REGIONS, true))  return null;

    return $lang . '-' . $region;
}

// Breadcrumb-Navigation rendern: [['Label', 'url'], ...] — letztes Element ist immer aktiv (kein Link)
function breadcrumb(array $items): string {
    $html = '<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $url]) {
        if ($i === $last) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($label) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</a></li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}
