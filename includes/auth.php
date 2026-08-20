<?php
// Gemeinsame Session- und CSRF-Logik — wird in jeder Seite als erstes eingebunden

require_once __DIR__ . '/config.php';

// Erkennt, ob das aktuell aufgerufene Skript in einem Unterverzeichnis liegt (aktuell nur
// infos/) — damit Redirects und Navbar-Links auch von dort korrekt auf die Root-Seiten zeigen,
// unabhängig vom Installationspfad der App. Nur der letzte Pfad-Teil wird geprüft, funktioniert
// also egal ob die App unter /learner/, einer anderen Subdomain o.ä. installiert ist.
function app_root_prefix(): string {
    static $prefix = null;
    if ($prefix === null) {
        $script_dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $prefix = ($script_dir === 'infos') ? '../' : '';
    }
    return $prefix;
}

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
    header('Location: ' . app_root_prefix() . 'index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Login prüfen (Name + Passwort pro Person)
function require_login(): void {
    if (empty($_SESSION['authenticated'])) {
        header('Location: ' . app_root_prefix() . 'index.php');
        exit;
    }
}

// Person-Auswahl prüfen
function require_person(): void {
    require_login();
    if (empty($_SESSION['person_id'])) {
        header('Location: ' . app_root_prefix() . 'home.php');
        exit;
    }
}

// Admin-Rolle prüfen (settings.php, deploy.php, users.php, "Person wechseln")
function require_admin(): void {
    require_person();
    if (empty($_SESSION['is_admin'])) {
        $_SESSION['flash_error'] = 'Nur für Admins zugänglich.';
        header('Location: ' . app_root_prefix() . 'home.php');
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

// Basis-URL aus der aktuellen Anfrage ableiten. NUR für Anzeige/Vorschlag in den Einstellungen
// verwenden — nicht für Links in E-Mails, dafür app_base_url() nutzen (HTTP_HOST ist fälschbar).
function current_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
}

// Vertrauenswürdige Basis-URL für Links in ausgehenden E-Mails.
// Bewusst NICHT aus $_SERVER['HTTP_HOST'] abgeleitet: der Host-Header kommt vom Client und ist
// fälschbar — sonst könnte ein Angreifer einen Passwort-Reset für eine fremde Adresse anfordern
// und dem Opfer eine Mail mit Link auf seine eigene Domain zustellen (Token-Diebstahl).
// Beim lokalen Entwickeln darf ersatzweise die aktuelle Adresse dienen, damit das Testen ohne
// Konfiguration funktioniert. Der Fallback hängt dabei bewusst an REMOTE_ADDR (Client-IP, nicht
// fälschbar) und NICHT an APP_ENV — APP_ENV wird in db.php ebenfalls aus HTTP_HOST abgeleitet und
// wäre damit über einen gefälschten Host-Header manipulierbar.
// Ohne konfigurierte APP_BASE_URL und ohne lokalen Client: '' (Aufrufer bricht ab).
function app_base_url(): string {
    if (APP_BASE_URL !== '') return rtrim(APP_BASE_URL, '/');
    $client_is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    return $client_is_local ? current_base_url() : '';
}

// -------------------------------------------------------
// Rate-Limiting (Login, Passwort-vergessen)
// -------------------------------------------------------
// Zählt Versuche pro Scope und Client-IP in der Tabelle auth_attempts. Bewusst schlicht: kein
// Sperren von Konten (das liesse sich zum Aussperren fremder Personen missbrauchen), sondern nur
// eine Bremse pro IP. Fehlt die Tabelle (z.B. direkt nach einem Deploy vor der Migration), darf
// das den Login niemals blockieren — deshalb sind alle Zugriffe in try/catch gekapselt.

const AUTH_LIMITS = [
    'login'  => ['max' => 10, 'minutes' => 15],
    'forgot' => ['max' => 5,  'minutes' => 60],
];

function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

// true, wenn für diesen Scope/diese IP das Limit erreicht ist.
function auth_limit_reached(PDO $pdo, string $scope): bool {
    $limit = AUTH_LIMITS[$scope] ?? null;
    if (!$limit) return false;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM auth_attempts
            WHERE scope = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)
        ");
        $stmt->execute([$scope, client_ip(), $limit['minutes']]);
        return (int) $stmt->fetchColumn() >= $limit['max'];
    } catch (PDOException $e) {
        return false;
    }
}

// Fehlversuch protokollieren. Räumt gelegentlich alte Einträge weg, damit die Tabelle nicht wächst.
function auth_attempt_record(PDO $pdo, string $scope): void {
    try {
        $pdo->prepare("INSERT INTO auth_attempts (scope, ip, attempted_at) VALUES (?, ?, NOW())")
            ->execute([$scope, client_ip()]);
        if (random_int(1, 20) === 1) {
            $pdo->exec("DELETE FROM auth_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
        }
    } catch (PDOException $e) {
        // Rate-Limiting darf den eigentlichen Ablauf nie verhindern.
    }
}

// Nach erfolgreichem Login: eigene Fehlversuche zurücksetzen.
function auth_attempts_clear(PDO $pdo, string $scope): void {
    try {
        $pdo->prepare("DELETE FROM auth_attempts WHERE scope = ? AND ip = ?")
            ->execute([$scope, client_ip()]);
    } catch (PDOException $e) {
        // siehe oben
    }
}

// Absenderadresse für ausgehende E-Mails. Konfigurierbar (Einstellungen → Absender-E-Mail), weil
// die aus der Basis-URL abgeleitete Adresse bei einer Subdomain zu Zustellproblemen führt: SPF wird
// nicht von der Hauptdomain vererbt, eine DMARC-Policy der Hauptdomain greift aber auch für
// Subdomains — die Mail scheitert dann an DMARC, obwohl mail() Erfolg meldet.
function mail_from_address(): string {
    if (MAIL_FROM !== '') return MAIL_FROM;
    $host = parse_url(app_base_url(), PHP_URL_HOST) ?: ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return 'no-reply@' . $host;
}

// Redirect-Ziel auf dieselbe Anwendung begrenzen (kein Open Redirect über absolute oder
// protokoll-relative URLs). Gleiche Absicherung wie in learn.php/drill.php.
function safe_redirect_target(?string $target, string $fallback = 'home.php'): string {
    $target = trim((string) $target);
    if ($target === '' || str_contains($target, '://') || str_starts_with($target, '//')
        || str_contains($target, "\r") || str_contains($target, "\n")) {
        return $fallback;
    }
    return $target;
}

// Streak-Badge für Navbar — liest aus Session-Cache, zeigt nichts wenn kein Person gewählt
function streak_badge(): string {
    if (empty($_SESSION['person_id'])) return '';
    $streak = (int)($_SESSION['streak'] ?? 0);
    if ($streak <= 0 || ($_SESSION['streak_date'] ?? '') !== today()) return '';
    $days = $streak === 1 ? 'Tag' : 'Tage';
    return '<a href="' . app_root_prefix() . 'stats.php" class="badge bg-warning text-dark text-decoration-none" title="Zur Statistik">🔥 ' . $streak . ' ' . $days . '</a>';
}

// Logout
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . app_root_prefix() . 'index.php');
    exit;
}

// Gemeinsame Navbar-Aktionen (Logout, eigenes Konto, Person wechseln) — wird von jeder Seite
// direkt nach csrf_validate() aufgerufen. Bei Treffer wird die Aktion ausgeführt und zur
// aktuellen URL zurückgeleitet (PRG); bei keinem Treffer kehrt die Funktion einfach zurück,
// damit die Seite ihre eigene, seitenspezifische Aktion weiterverarbeiten kann.
function handle_navbar_actions(PDO $pdo): void {
    $action        = $_POST['action'] ?? '';
    $real_is_admin = !empty($_SESSION['real_is_admin']);
    $back          = safe_redirect_target($_SERVER['REQUEST_URI'] ?? null);

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

    // change_own_password/change_own_email sind nach profile.php umgezogen (v3.18.0) — dort statt
    // im Navbar-Modal editierbar, siehe profile.php.
}

// Zentrale Navbar — von jeder Seite mit Person-Kontext aufgerufen, damit Icons/Reihenfolge
// nur an einer Stelle gepflegt werden müssen. $abort_url: falls gesetzt, ersetzt "Session
// abbrechen" den Logout-Button (für laufende Leitner-/Drill-Sessions).
function render_navbar(PDO $pdo, ?string $abort_url = null): void {
    // Öffentliche Seiten (infos/*) rufen render_navbar() auch ohne aktive Anmeldung auf — daher
    // müssen alle personenbezogenen Daten hier bedingt geladen werden, statt sie wie bisher
    // ungeprüft aus der Session vorauszusetzen.
    $logged_in     = !empty($_SESSION['person_id']);
    $person_id     = $_SESSION['person_id'] ?? null;
    $person_name   = $_SESSION['person_name'] ?? '';
    $is_admin      = !empty($_SESSION['is_admin']);       // Berechtigungen der aktuell angezeigten Person
    $real_is_admin = !empty($_SESSION['real_is_admin']);  // wirkliche Berechtigung — steuert "Person wechseln"

    $persons = ($logged_in && $real_is_admin) ? $pdo->query("SELECT id, name FROM persons ORDER BY name")->fetchAll() : [];

    $root = app_root_prefix();
    // wissen.php liegt in infos/ — von Root-Seiten aus muss der Link deshalb IN das
    // Unterverzeichnis zeigen (infos/wissen.php), von infos/-Seiten aus dagegen nur "wissen.php"
    // (gleiches Verzeichnis). Das ist die Umkehrung von $root, das für die übrigen Root-Seiten gilt.
    $wissen_href = ($root === '../') ? 'wissen.php' : 'infos/wissen.php';
    $login_href  = $root . 'index.php';
    ?>
    <nav class="navbar navbar-expand-sm navbar-dark bg-primary">
        <div class="container-fluid">
            <!-- Linker Block (Marke + Infos-Icon + ggf. Login) bewusst in einem gemeinsamen
                 Flex-Wrapper: container-fluid verteilt seine direkten Kinder per
                 justify-content:space-between — ohne diesen Wrapper würde das Haus-Icon als
                 mittleres von 2-3 direkten Kindern in die Mitte statt an den linken Rand rutschen. -->
            <div class="d-flex align-items-center">
                <a class="navbar-brand fw-bold me-2" href="<?= $root ?>home.php"><?= APP_NAME ?></a>
                <!-- Infos-Icon bewusst ganz links direkt neben der Marke: die Infos-Seiten sind ohne
                     Anmeldung erreichbar, daher muss der Link auch ohne Session sichtbar und
                     erreichbar sein — unabhängig vom restlichen, nur für angemeldete Personen
                     sichtbaren Icon-Block rechts. Login (für nicht angemeldete Besucher:innen) steht
                     direkt daneben, da es ohne Session sonst keinen rechten Icon-Block gibt, in dem
                     es stehen könnte. -->
                <a href="<?= $wissen_href ?>" class="btn btn-sm btn-outline-light me-2" title="Wissenschaftlich Sprachen lernen" aria-label="Wissenschaftlich Sprachen lernen"><i class="bi bi-house"></i></a>
                <?php if (!$logged_in): ?>
                <a href="<?= htmlspecialchars($login_href) ?>" class="btn btn-sm btn-outline-light" title="Login" aria-label="Login"><i class="bi bi-box-arrow-in-right"></i></a>
                <?php endif; ?>
            </div>
            <?php if ($logged_in): ?>
            <!-- Hamburger-Umschalter: navbar-expand-sm blendet die Icon-Leiste unterhalb sm
                 (iPhone) automatisch hinter diesem Button aus (Standard-Bootstrap-Mechanik) —
                 vorher fehlte er, wodurch zu viele Icons auf schmalen Screens einfach in eine
                 zweite Zeile umgebrochen sind statt sich in einem Menü zu verstecken. -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarIcons"
                    aria-controls="navbarIcons" aria-expanded="false" aria-label="Menü öffnen/schliessen">
                <span class="navbar-toggler-icon"></span>
            </button>
            <!-- flex-column/-sm-row + align-items-stretch: unterhalb sm klappt der Inhalt als
                 linksbündige Liste über die volle Breite auf, jeder Eintrag zusätzlich mit
                 sichtbarem Text-Label (d-sm-none an den Spans unten); ab sm wie bisher als
                 kompakte, reine Icon-Leiste inline mit Tooltip. Streak-Badge und Personenname
                 bekommen align-self-start, damit sie beim Aufklappen nicht mit auf volle Breite
                 gestreckt werden (sähe bei einem farbigen Badge bzw. reinem Text seltsam aus). -->
            <div class="collapse navbar-collapse" id="navbarIcons">
                <!-- justify-content-sm-end statt ms-sm-auto: .navbar-collapse füllt dank Bootstraps
                     eigenem flex-grow:1 ohnehin schon die gesamte verbleibende Breite aus, ein
                     zusätzliches auto-Margin bewirkt darin nichts mehr — ohne justify-content-*-end
                     hängen die Icons dadurch links in diesem bereits vollen Bereich statt rechts. Nur
                     ab sm (Reihe), nicht auf Mobile (dort ist die Hauptachse vertikal — dort soll die
                     Liste oben beginnen, nicht ans untere Ende geschoben werden). -->
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-sm-end gap-2 py-2 py-sm-0">
                    <?php if ($abort_url): ?>
                    <!-- Während einer laufenden Session bewusst an erster Stelle: der Abbruch ist dann
                         die wichtigste Aktion und soll nicht zwischen den übrigen Icons gesucht werden -->
                    <a href="<?= htmlspecialchars($abort_url) ?>" class="btn btn-sm btn-outline-light text-start"
                       title="Session abbrechen" aria-label="Session abbrechen">
                        <i class="bi bi-x-lg"></i><span class="d-sm-none ms-2">Session abbrechen</span>
                    </a>
                    <?php endif; ?>
                    <div class="align-self-start"><?= streak_badge() ?></div>
                    <span class="text-white small d-none d-sm-inline align-self-start"><?= htmlspecialchars($person_name) ?></span>
                    <a href="<?= $root ?>profile.php" class="btn btn-sm btn-outline-light text-start" title="Profil" aria-label="Profil">
                        <i class="bi bi-person-circle"></i><span class="d-sm-none ms-2">Profil</span>
                    </a>
                    <?php if ($real_is_admin && count($persons) > 1): ?>
                    <div class="dropdown d-flex">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle text-start w-100" type="button" data-bs-toggle="dropdown"
                                title="Person wechseln" aria-label="Person wechseln">
                            <i class="bi bi-person-lines-fill"></i><span class="d-sm-none ms-2">Person wechseln</span>
                        </button>
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
                    <a href="<?= $root ?>users.php" class="btn btn-sm btn-outline-light text-start" title="Benutzerverwaltung" aria-label="Benutzerverwaltung">
                        <i class="bi bi-person-gear"></i><span class="d-sm-none ms-2">Benutzerverwaltung</span>
                    </a>
                    <a href="<?= $root ?>settings.php" class="btn btn-sm btn-outline-light text-start" title="Einstellungen" aria-label="Einstellungen">
                        <i class="bi bi-gear"></i><span class="d-sm-none ms-2">Einstellungen</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= $root ?>help.php" class="btn btn-sm btn-outline-light text-start" title="Hilfe" aria-label="Hilfe">
                        <i class="bi bi-info-lg"></i><span class="d-sm-none ms-2">Hilfe</span>
                    </a>
                    <?php if (!$abort_url): ?>
                    <!-- Logout ganz rechts als letztes Icon der Leiste. -->
                    <form method="post" class="d-flex">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-sm btn-outline-light text-start w-100" title="Logout" aria-label="Logout">
                            <i class="bi bi-box-arrow-right"></i><span class="d-sm-none ms-2">Logout</span>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>
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

// Bestimmt Frage-/Antwortseite einer Karte je Lernrichtung — gemeinsam von learn.php und
// drill.php genutzt, da die Lernkarte in beiden Modi identisch aufgebaut ist (siehe help.php,
// Abschnitt "Aufbau einer Lernkarte"). 'mixed' entscheidet pro Karte deterministisch über die
// Karten-ID, nicht zufällig bei jeder Anzeige, damit dieselbe Karte innerhalb einer Session nicht
// hin- und herspringt. Audio/Lautschrift gehören immer zur fremdsprachigen Seite (Sprache B),
// unabhängig davon ob diese als Frage oder Antwort erscheint.
function get_question_answer(array $card, string $direction): array {
    $b_first = ($direction === 'b_to_a') || ($direction === 'mixed' && $card['id'] % 2 === 0);
    $speech_lang_b = $card['speech_lang_b'] ?? null;

    if ($b_first) {
        return [
            'q' => $card['word_b'], 'a' => $card['word_a'],
            'q_desc' => $card['desc_b'], 'a_desc' => $card['desc_a'],
            'q_lang' => $card['language_b'], 'a_lang' => $card['language_a'],
            'q_audio' => $speech_lang_b ? $card['word_b'] : null, 'a_audio' => null,
            'q_phonetic' => $card['phonetic_b'] ?? null, 'a_phonetic' => null,
        ];
    }
    return [
        'q' => $card['word_a'], 'a' => $card['word_b'],
        'q_desc' => $card['desc_a'], 'a_desc' => $card['desc_b'],
        'q_lang' => $card['language_a'], 'a_lang' => $card['language_b'],
        'q_audio' => null, 'a_audio' => $speech_lang_b ? $card['word_b'] : null,
        'q_phonetic' => null, 'a_phonetic' => $card['phonetic_b'] ?? null,
    ];
}

// Erkennt Mathe-Listen (math.php) anhand des dort gesetzten Markers language_a === 'Aufgabe' — es
// gibt bewusst kein eigenes DB-Feld dafür, da math.php aktuell die einzige Stelle ist, die solche
// Listen erzeugt. Genutzt von learn.php/drill.php, um bei Mathe-Listen nur "Aufgabe → Ergebnis" als
// Lernrichtung zuzulassen (die anderen drei Richtungen ergeben bei Rechenaufgaben keinen Sinn).
function is_math_list(array $list): bool {
    return ($list['language_a'] ?? '') === 'Aufgabe';
}

// Löst die Lernrichtungs-Auswahl vom Setup-Formular (learn.php/drill.php) auf. 'random' ist kein
// eigener Kartenmodus für get_question_answer(), sondern wird hier einmalig pro Session in eine
// der drei echten Richtungen aufgelöst — die Session läuft danach durchgehend mit dieser einen
// Richtung, kein erneutes Auswürfeln pro Karte.
function resolve_direction(?string $input): string {
    $direction = in_array($input, ['a_to_b', 'b_to_a', 'mixed', 'random'], true) ? $input : 'random';
    if ($direction === 'random') {
        $direction = ['a_to_b', 'b_to_a', 'mixed'][random_int(0, 2)];
    }
    return $direction;
}

// Debug-Modus (Einstellungen → Debug, nur für Admins): Hilfsfunktionen für die Vorher/Nachher-
// Anzeige in learn.php/drill.php. Bewusst nur bei DEBUG_MODE + Admin aufgerufen — siehe
// docs/ANFORDERUNGEN.md, Abschnitt "Debug-Modus".
function debug_card_label(PDO $pdo, int $card_id): string {
    $stmt = $pdo->prepare("SELECT word_a FROM cards WHERE id = ?");
    $stmt->execute([$card_id]);
    $word = $stmt->fetchColumn();
    return '„' . htmlspecialchars($word ?: ('Karte #' . $card_id)) . '"';
}

function debug_format_date(?string $date): string {
    if (!$date) return '–';
    return date('d.m.', strtotime($date));
}

// $_SESSION['debug_last_answer'] ist ein Array aus mehreren Zeilen (Leitner: 3, Drill: 4-5 inkl.
// Deckgrösse als zweite Zeile — siehe debug_drill_message() in drill.php): [Karte, ggf. Deckgrösse,
// Antwort (Kontext), Detail(s)]. Wird zeilenweise dargestellt statt als ein langer Fliesstext-Satz —
// besser überflogen.
function debug_panel(): string {
    $lines = $_SESSION['debug_last_answer'] ?? null;
    unset($_SESSION['debug_last_answer']);
    if (!$lines) return '';
    // Zeilen werden beim Erzeugen bereits kontrolliert zusammengesetzt (debug_card_label escaped
    // den einzigen freien Textteil), daher hier kein zusätzliches htmlspecialchars.
    $lines[0] = 'Debug: ' . $lines[0];
    // Fixiert am unteren Bildschirmrand statt im normalen Textfluss, damit die Meldung ohne
    // Scrollen sichtbar ist. Bei Karten die den Viewport sprengen kann sie dadurch die
    // Antwort-Buttons überlagern — deshalb per Schliessen-Button wegklickbar (Bootstrap-JS ist
    // auf learn.php/drill.php bereits geladen, kein zusätzliches Script nötig).
    return '<div class="alert alert-info py-2 small mb-0 d-flex justify-content-between align-items-start gap-2"'
        . ' role="alert" style="position:fixed; bottom:0; left:0; right:0; z-index:1030; border-radius:0;'
        . ' padding-left:.75rem; padding-bottom:calc(.5rem + env(safe-area-inset-bottom));">'
        . '<div><i class="bi bi-bug me-1"></i>' . implode('<br>', $lines) . '</div>'
        . '<button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="alert" aria-label="Debug-Meldung schliessen"></button>'
        . '</div>';
}
