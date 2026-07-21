<?php
// Gemeinsame Session- und CSRF-Logik — wird in jeder Seite als erstes eingebunden

require_once __DIR__ . '/config.php';

session_start();

// Session-Timeout prüfen
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_start();
    header('Location: index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// App-Login prüfen (globales Passwort)
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
