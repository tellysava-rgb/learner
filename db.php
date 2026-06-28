<?php
// Umgebungserkennung: localhost/127.0.0.1 = dev, alles andere = prod
$_db_host = $_SERVER['HTTP_HOST'] ?? php_uname('n');
$_db_is_dev = (
    $_db_host === 'localhost' ||
    $_db_host === '127.0.0.1' ||
    str_starts_with($_db_host, 'localhost:')
);
define('APP_ENV', $_db_is_dev ? 'dev' : 'prod');

// Zugangsdaten aus separater Datei laden (nie committen)
$_db_creds_file = __DIR__ . '/db-credentials.php';
if (!file_exists($_db_creds_file)) {
    http_response_code(503);
    die('db-credentials.php fehlt. Bitte die Datei nach Vorlage anlegen.');
}
require $_db_creds_file;

$_db_env = $_db_credentials[APP_ENV];

try {
    $pdo = new PDO(
        "mysql:host={$_db_env['host']};dbname={$_db_env['name']};charset=utf8mb4",
        $_db_env['user'],
        $_db_env['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(503);
    die('Datenbankverbindung fehlgeschlagen. Bitte den Administrator kontaktieren.');
}

unset($_db_host, $_db_is_dev, $_db_creds_file, $_db_env, $_db_credentials);
