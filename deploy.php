<?php
// deploy.php — Deploy via GitHub ZIP-Download (public repo, kein Token nötig)
// NICHT im Git-Repo: in .gitignore eingetragen
// Aufruf: https://deinserver.ch/learner/deploy.php?token=DEPLOY_TOKEN

$config_file = __DIR__ . '/includes/deploy-config.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    die('deploy-config.php fehlt.');
}
require $config_file;

// Token prüfen (GET für Direktaufruf/Automation, POST für Settings-UI)
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if (!defined('DEPLOY_TOKEN') || !hash_equals(DEPLOY_TOKEN, $token)) {
    http_response_code(403);
    die('Ungültiger Token.');
}

if (!function_exists('curl_init')) {
    die('cURL ist auf diesem Server nicht verfügbar.');
}

// Aktuelle Version aus config.php lesen
$current_version = 'unbekannt';
$local_config = __DIR__ . '/includes/config.php';
if (file_exists($local_config)) {
    $cfg = file_get_contents($local_config);
    if (preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $cfg, $m)) {
        $current_version = $m[1];
    }
}

// Neue Version aus GitHub lesen
$new_version = 'unbekannt';
$raw_url = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/main/includes/config.php';
$ch = curl_init($raw_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_USERAGENT      => 'PHP-Deploy/1.0',
]);
$remote_cfg = curl_exec($ch);
unset($ch);
if ($remote_cfg && preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $remote_cfg, $m)) {
    $new_version = $m[1];
}

// Diese Dateien werden nie deployed (müssen manuell verwaltet werden)
$protected = [
    'db-credentials.php',
    'config-runtime.php',
    'deploy.php',
    'deploy-config.php',
    'install.php',
];

$log     = [];
$success = false;

try {
    // 1. ZIP von GitHub herunterladen
    $zip_url = 'https://github.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/archive/refs/heads/main.zip';
    $ch = curl_init($zip_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'PHP-Deploy/1.0',
    ]);
    $zip_data  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    unset($ch);

    if ($zip_data === false || $curl_err) {
        throw new RuntimeException('cURL-Fehler: ' . $curl_err);
    }
    if ($http_code !== 200) {
        throw new RuntimeException('Download fehlgeschlagen (HTTP ' . $http_code . '). Repo public?');
    }
    $log[] = 'ZIP heruntergeladen (' . round(strlen($zip_data) / 1024) . ' KB)';

    // 2. ZIP in temporäre Datei schreiben (tempnam erstellt Datei atomar — kein Race Condition)
    $tmp_zip = tempnam(sys_get_temp_dir(), 'lrnr_');
    if ($tmp_zip === false || file_put_contents($tmp_zip, $zip_data) === false) {
        throw new RuntimeException('Temporäre ZIP-Datei konnte nicht geschrieben werden.');
    }
    unset($zip_data);

    // 3. ZIP entpacken (zufälliger Name — nicht vorhersagbar)
    $tmp_dir = sys_get_temp_dir() . '/lrnr_' . bin2hex(random_bytes(8));
    mkdir($tmp_dir, 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($tmp_zip) !== true) {
        throw new RuntimeException('ZIP-Archiv konnte nicht geöffnet werden.');
    }
    $zip->extractTo($tmp_dir);
    $zip->close();
    unlink($tmp_zip);
    $log[] = 'ZIP entpackt';

    // 4. Inneres Verzeichnis finden (GitHub: repo-main/)
    $inner = glob($tmp_dir . '/*', GLOB_ONLYDIR);
    if (empty($inner)) {
        throw new RuntimeException('ZIP-Struktur unbekannt — kein Unterverzeichnis gefunden.');
    }
    $source = rtrim($inner[0], '/');

    // 5. Dateien rekursiv kopieren
    $copied  = 0;
    $skipped = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $item) {
        $rel    = substr($item->getPathname(), strlen($source) + 1);
        $target = __DIR__ . '/' . $rel;

        if ($item->isDir()) {
            if (!is_dir($target)) mkdir($target, 0755, true);
            continue;
        }

        if (in_array(basename($rel), $protected, true)) {
            $skipped++;
            continue;
        }

        if (copy($item->getPathname(), $target)) {
            $copied++;
        } else {
            $log[] = 'WARNUNG: Konnte nicht kopieren: ' . $rel;
        }
    }

    $log[] = $copied . ' Dateien kopiert, ' . $skipped . ' geschützte Dateien übersprungen';

    // 6. Temp-Verzeichnis aufräumen
    $cleanup = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($cleanup as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($tmp_dir);

    $success = true;
    $log[]   = 'Deploy abgeschlossen.';

} catch (RuntimeException $e) {
    $log[] = 'FEHLER: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Deploy — Learner</title>
    <style>
        body { font-family: monospace; max-width: 700px; margin: 40px auto; padding: 20px; background: #111; color: #ccc; }
        h2   { color: #fff; }
        .versions { display: flex; gap: 32px; margin: 16px 0; }
        .ver  { background: #1e1e1e; border-radius: 6px; padding: 12px 20px; }
        .ver span { display: block; font-size: 0.8em; color: #888; margin-bottom: 4px; }
        .ver strong { font-size: 1.3em; color: #fff; }
        .arrow { font-size: 2em; align-self: center; color: #555; }
        pre  { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 6px; white-space: pre-wrap; }
        .ok  { color: #22c55e; font-weight: bold; }
        .err { color: #ef4444; font-weight: bold; }
        a    { color: #60a5fa; }
        .meta { color: #555; font-size: 0.85em; margin-top: 16px; }
    </style>
</head>
<body>
    <h2>Deploy — Learner</h2>

    <div class="versions">
        <div class="ver">
            <span>Installiert</span>
            <strong>v<?= htmlspecialchars($current_version) ?></strong>
        </div>
        <div class="arrow">→</div>
        <div class="ver">
            <span>GitHub (main)</span>
            <strong>v<?= htmlspecialchars($new_version) ?></strong>
        </div>
    </div>

    <p class="<?= $success ? 'ok' : 'err' ?>">
        <?= $success ? '✅ Erfolgreich deployed' : '❌ Deploy fehlgeschlagen' ?>
    </p>
    <pre><?= htmlspecialchars(implode("\n", $log)) ?></pre>
    <p class="meta"><?= date('Y-m-d H:i:s') ?></p>
    <?php if ($success): ?>
    <p><a href="home.php">→ Zur App</a></p>
    <?php endif; ?>
</body>
</html>
