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

// Logout
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
