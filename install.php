<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$message = '';
$error   = '';

// -------------------------------------------------------
// Status prüfen
// -------------------------------------------------------
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool) $stmt->fetch();
}

function password_is_set(PDO $pdo): bool {
    if (!table_exists($pdo, 'settings')) return false;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'password_hash'");
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

$tables_exist   = table_exists($pdo, 'persons');
$password_isset = password_is_set($pdo);

// -------------------------------------------------------
// POST: Tabellen erstellen + Passwort setzen
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'install') {
        // Tabellen anlegen (idempotent)
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
                    `value` TEXT         NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS persons (
                    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS lists (
                    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    person_id   INT          NOT NULL,
                    name        VARCHAR(200) NOT NULL,
                    description TEXT,
                    language_a  VARCHAR(50)  NOT NULL,
                    language_b  VARCHAR(50)  NOT NULL,
                    is_public   TINYINT(1)   NOT NULL DEFAULT 0,
                    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_used_at TIMESTAMP   NULL     DEFAULT NULL,
                    FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS cards (
                    id         INT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    list_id    INT       NOT NULL,
                    word_a     TEXT      NOT NULL,
                    word_b     TEXT      NOT NULL,
                    desc_a     TEXT,
                    desc_b     TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS card_progress (
                    id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    person_id       INT          NOT NULL,
                    card_id         INT          NOT NULL,
                    status          ENUM('queued','active','archived') NOT NULL DEFAULT 'queued',
                    leitner_box     TINYINT      NOT NULL DEFAULT 1,
                    next_due_date   DATE         NULL DEFAULT NULL,
                    drill_mastery   TINYINT      NOT NULL DEFAULT 0,
                    drill_too_hard  TINYINT(1)   NOT NULL DEFAULT 0,
                    last_drill_shown DATE        NULL DEFAULT NULL,
                    UNIQUE KEY unique_person_card (person_id, card_id),
                    FOREIGN KEY (card_id)   REFERENCES cards(id)   ON DELETE CASCADE,
                    FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS learning_sessions (
                    id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    person_id    INT          NOT NULL,
                    mode         VARCHAR(20)  NOT NULL,
                    direction    VARCHAR(10)  NULL DEFAULT NULL,
                    started_at   DATETIME     NOT NULL,
                    completed_at DATETIME     NULL DEFAULT NULL,
                    FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS session_lists (
                    session_id INT NOT NULL,
                    list_id    INT NOT NULL,
                    PRIMARY KEY (session_id, list_id),
                    FOREIGN KEY (session_id) REFERENCES learning_sessions(id) ON DELETE CASCADE,
                    FOREIGN KEY (list_id)    REFERENCES lists(id)             ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS learning_events (
                    id         INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    session_id INT         NOT NULL,
                    person_id  INT         NOT NULL,
                    card_id    INT         NOT NULL,
                    result     VARCHAR(20) NOT NULL,
                    learn_date DATE        NOT NULL,
                    FOREIGN KEY (session_id) REFERENCES learning_sessions(id) ON DELETE CASCADE,
                    FOREIGN KEY (card_id)    REFERENCES cards(id)             ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $tables_exist = true;
            $message = 'Tabellen erfolgreich erstellt (bereits vorhandene wurden übersprungen).';
        } catch (PDOException $e) {
            $error = 'Fehler beim Erstellen der Tabellen: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($action === 'set_password') {
        $pw  = $_POST['password']  ?? '';
        $pw2 = $_POST['password2'] ?? '';
        if (mb_strlen($pw) < 8) {
            $error = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('password_hash', ?)
                                   ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([$hash, $hash]);
            $password_isset = true;
            $message = 'Passwort erfolgreich gesetzt. Du kannst dich jetzt einloggen.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width:560px;">
    <h1 class="h3 mb-1"><?= APP_NAME ?> — Installation</h1>
    <p class="text-muted small mb-4">Nur auf Localhost zugänglich. Diese Seite kann jederzeit erneut aufgerufen werden.</p>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Schritt 1: Tabellen -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="fw-semibold">Schritt 1 — Datenbanktabellen</span>
            <?php if ($tables_exist): ?>
            <span class="badge bg-success ms-auto">Erstellt</span>
            <?php else: ?>
            <span class="badge bg-warning text-dark ms-auto">Ausstehend</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($tables_exist): ?>
            <p class="text-muted small mb-3">Alle Tabellen sind vorhanden. Erneutes Ausführen ist sicher (IF NOT EXISTS).</p>
            <?php else: ?>
            <p class="text-muted small mb-3">Erstellt alle benötigten Tabellen in der Datenbank.</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="install">
                <button class="btn btn-primary btn-sm">
                    <?= $tables_exist ? 'Tabellen erneut prüfen' : 'Tabellen erstellen' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Schritt 2: Passwort -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="fw-semibold">Schritt 2 — Passwort setzen</span>
            <?php if ($password_isset): ?>
            <span class="badge bg-success ms-auto">Gesetzt</span>
            <?php else: ?>
            <span class="badge bg-warning text-dark ms-auto">Ausstehend</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$tables_exist): ?>
            <p class="text-muted small">Zuerst Schritt 1 ausführen.</p>
            <?php else: ?>
            <?php if ($password_isset): ?>
            <p class="text-muted small mb-3">Passwort bereits gesetzt. Du kannst es hier zurücksetzen.</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="set_password">
                <div class="mb-2">
                    <input type="password" name="password" class="form-control form-control-sm"
                           placeholder="Neues Passwort (min. 8 Zeichen)" required minlength="8">
                </div>
                <div class="mb-3">
                    <input type="password" name="password2" class="form-control form-control-sm"
                           placeholder="Passwort wiederholen" required>
                </div>
                <button class="btn btn-primary btn-sm">Passwort setzen</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tables_exist && $password_isset): ?>
    <div class="alert alert-success">
        Installation abgeschlossen. <a href="index.php">Zur App →</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
