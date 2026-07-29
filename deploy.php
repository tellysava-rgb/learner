<?php
// deploy.php — Deploy via GitHub ZIP-Download (public repo, kein Token nötig)
// Schützt sich über die eigene Skip-Liste vor Überschreiben — Änderungen manuell per FTP auf Prod kopieren
// Aufruf ohne Aktion: reine Statusanzeige (installierte vs. GitHub-Version), löst noch KEIN Deployment aus.
// Erst der Button "Deploy starten" auf dieser Seite (POST, action=run_deploy) führt das Deployment aus.

require_once __DIR__ . '/includes/auth.php';

// Admin-Session zusätzlich zum Token erforderlich (kein reiner Token-Aufruf mehr ohne Login)
if (empty($_SESSION['authenticated']) || empty($_SESSION['person_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('Admin-Login erforderlich, um diese Seite zu nutzen.');
}

$config_file = __DIR__ . '/includes/deploy-config.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    die('deploy-config.php fehlt.');
}
require $config_file;

// Token prüfen (GET für Direktaufruf/Statusanzeige, POST für den eigentlichen Deploy-Trigger)
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if (!defined('DEPLOY_TOKEN') || !hash_equals(DEPLOY_TOKEN, $token)) {
    http_response_code(403);
    die('Ungültiger Token.');
}

if (!function_exists('curl_init')) {
    die('cURL ist auf diesem Server nicht verfügbar.');
}

function deploy_read_local_version(string $path): string {
    if (!file_exists($path)) return 'unbekannt';
    $cfg = file_get_contents($path);
    return preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $cfg, $m) ? $m[1] : 'unbekannt';
}

function deploy_read_github_version(): string {
    $raw_url = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/main/includes/config.php';
    $ch = curl_init($raw_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'PHP-Deploy/1.0',
    ]);
    $remote_cfg = curl_exec($ch);
    unset($ch);
    return ($remote_cfg && preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $remote_cfg, $m)) ? $m[1] : 'unbekannt';
}

$local_config    = __DIR__ . '/includes/config.php';
$current_version = deploy_read_local_version($local_config);
$new_version     = deploy_read_github_version();

// GitHub-Version älter als installierte Version? (z.B. lokale Änderungen noch nicht gepusht)
$is_downgrade = $current_version !== 'unbekannt' && $new_version !== 'unbekannt'
    && version_compare($new_version, $current_version, '<');

$deploy_ran             = false;
$show_downgrade_warning = false;
$success                = false;
$log                    = [];

// Deployment nur bei explizitem POST-Trigger ausführen — reiner Seitenaufruf zeigt nur den Status.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_deploy') {
    csrf_validate();
    $confirmed_downgrade = ($_POST['confirm_downgrade'] ?? '') === '1';

    if ($is_downgrade && !$confirmed_downgrade) {
        // Erst warnen statt direkt zu deployen — verhindert versehentliches Überschreiben
        // einer neueren lokalen Version mit einem älteren GitHub-Stand.
        $show_downgrade_warning = true;
    } else {
        $deploy_ran = true;
        deploy_run($log, $success);
        $current_version = deploy_read_local_version($local_config);
    }
}

// Führt den eigentlichen Download/Kopiervorgang aus (Dateien, die nie deployed werden, siehe $protected)
function deploy_run(array &$log, bool &$success): void {
    $protected = [
        'db-credentials.php',
        'config-runtime.php',
        'deploy.php',
        'deploy-config.php',
        'install.php',
    ];

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

            // Match nur per basename(), nicht per Pfad — funktioniert unabhängig davon,
            // ob die Datei im Root oder in includes/ liegt. Nicht auf Pfadvergleich ändern.
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
        .versions { display: flex; gap: 32px; margin: 16px 0; align-items: center; }
        .ver  { background: #1e1e1e; border-radius: 6px; padding: 12px 20px; }
        .ver span { display: block; font-size: 0.8em; color: #888; margin-bottom: 4px; }
        .ver strong { font-size: 1.3em; color: #fff; }
        .arrow { font-size: 2em; align-self: center; color: #555; }
        pre  { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 6px; white-space: pre-wrap; }
        .ok  { color: #22c55e; font-weight: bold; }
        .err { color: #ef4444; font-weight: bold; }
        a    { color: #60a5fa; }
        .meta { color: #555; font-size: 0.85em; margin-top: 16px; }
        button { font-family: monospace; font-size: 1em; background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 10px 20px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <h2>Deploy — Learner</h2>

    <div class="versions">
        <div class="ver">
            <span>Installiert</span>
            <strong>v<?= htmlspecialchars($current_version) ?></strong>
        </div>
        <div class="arrow"><?= ($current_version === $new_version) ? '=' : '←' ?></div>
        <div class="ver">
            <span>GitHub (main)</span>
            <strong>v<?= htmlspecialchars($new_version) ?></strong>
        </div>
    </div>

    <?php if ($show_downgrade_warning): ?>
        <p class="err">⚠️ Achtung: Die Version auf GitHub (v<?= htmlspecialchars($new_version) ?>) ist ÄLTER als die hier installierte Version (v<?= htmlspecialchars($current_version) ?>).</p>
        <p>Ein Deployment würde die neuere lokale Version mit dem älteren GitHub-Stand überschreiben. Falls hier Änderungen liegen, die noch nicht auf GitHub gepusht wurden, gehen sie dabei verloren.</p>
        <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_deploy">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="confirm_downgrade" value="1">
            <button type="submit" style="background:#dc2626;">Ja, trotzdem deployen</button>
        </form>
        <a href="deploy.php?token=<?= urlencode($token) ?>">Abbrechen</a>
    <?php elseif (!$deploy_ran): ?>
        <?php if ($current_version === $new_version): ?>
        <p class="ok">✓ Bereits auf dem neuesten Stand.</p>
        <?php elseif ($is_downgrade): ?>
        <p>Die installierte Version ist neuer als der Stand auf GitHub (nicht gepushte lokale Änderungen?). Noch nichts wurde verändert — der Button unten fragt vor dem Deployment sicherheitshalber nochmal nach.</p>
        <?php else: ?>
        <p>Neue Version auf GitHub verfügbar. Noch nichts wurde verändert — erst der Button unten löst das Deployment aus.</p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_deploy">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <button type="submit">Deploy starten</button>
        </form>
    <?php else: ?>
        <p class="<?= $success ? 'ok' : 'err' ?>">
            <?= $success
                ? '✅ Version v' . htmlspecialchars($current_version) . ' wurde erfolgreich auf Prod installiert.'
                : '❌ Deploy fehlgeschlagen' ?>
        </p>
        <pre><?= htmlspecialchars(implode("\n", $log)) ?></pre>
        <p class="meta"><?= date('Y-m-d H:i:s') ?></p>
    <?php endif; ?>

    <p class="meta"><a href="settings.php">← Zurück zu Einstellungen</a></p>
</body>
</html>
