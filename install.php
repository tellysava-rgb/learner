<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$message = '';
$error   = '';

// -------------------------------------------------------
// Status prüfen
// -------------------------------------------------------
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool) $stmt->fetch();
}

function has_person(PDO $pdo): bool {
    if (!table_exists($pdo, 'persons')) return false;
    return (bool) $pdo->query("SELECT COUNT(*) FROM persons")->fetchColumn();
}

$tables_exist = table_exists($pdo, 'persons');
$person_exists = has_person($pdo);

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
                    id                   INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name                 VARCHAR(100) NOT NULL,
                    password_hash        VARCHAR(255) NULL DEFAULT NULL,
                    is_admin             TINYINT(1)   NOT NULL DEFAULT 0,
                    email                VARCHAR(255) NULL DEFAULT NULL,
                    reset_token_hash     VARCHAR(64)  NULL DEFAULT NULL,
                    reset_token_expires  DATETIME     NULL DEFAULT NULL,
                    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_person_email (email)
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

    if ($action === 'create_admin') {
        $name = trim($_POST['name'] ?? '');
        $pw   = $_POST['password']  ?? '';
        $pw2  = $_POST['password2'] ?? '';

        if ($person_exists) {
            $error = 'Es existiert bereits mindestens eine Person — weitere Personen über die Benutzerverwaltung (users.php) anlegen.';
        } elseif ($name === '') {
            $error = 'Name darf nicht leer sein.';
        } elseif (mb_strlen($pw) < 8) {
            $error = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO persons (name, password_hash, is_admin) VALUES (?, ?, 1)");
            $stmt->execute([$name, $hash]);
            $person_exists = true;
            $message = 'Admin-Konto „' . $name . '" wurde angelegt. Du kannst dich jetzt einloggen.';
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

    <!-- Schritt 2: Ersten Admin anlegen -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="fw-semibold">Schritt 2 — Ersten Admin anlegen</span>
            <?php if ($person_exists): ?>
            <span class="badge bg-success ms-auto">Erstellt</span>
            <?php else: ?>
            <span class="badge bg-warning text-dark ms-auto">Ausstehend</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$tables_exist): ?>
            <p class="text-muted small mb-0">Zuerst Schritt 1 ausführen.</p>
            <?php elseif ($person_exists): ?>
            <p class="text-muted small mb-0">Es existiert bereits mindestens eine Person. Weitere Personen, Passwort-Resets und Admin-Rechte werden über die Benutzerverwaltung (<code>users.php</code>, nach dem Login) verwaltet.</p>
            <?php else: ?>
            <p class="text-muted small mb-3">Legt die erste Person an — automatisch als Admin, damit du dich sofort einloggen und weitere Personen über <code>users.php</code> anlegen kannst.</p>
            <form method="post">
                <input type="hidden" name="action" value="create_admin">
                <div class="mb-2">
                    <input type="text" name="name" class="form-control form-control-sm"
                           placeholder="Name" required maxlength="100">
                </div>
                <div class="mb-2">
                    <input type="password" name="password" class="form-control form-control-sm"
                           placeholder="Passwort (min. 8 Zeichen)" required minlength="8">
                </div>
                <div class="mb-3">
                    <input type="password" name="password2" class="form-control form-control-sm"
                           placeholder="Passwort wiederholen" required>
                </div>
                <button class="btn btn-primary btn-sm">Admin anlegen</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tables_exist && $person_exists): ?>
    <div class="alert alert-success">
        Installation abgeschlossen. <a href="index.php">Zur App →</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
