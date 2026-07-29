<?php
// Automatische DB-Migrationen — wird von db.php bei jedem Request aufgerufen.
// Neue Migrationen am Ende der Liste anfügen, Nummerierung fortlaufend.
// Bereits ausgeführte Migrationen werden anhand der db_version in der settings-Tabelle übersprungen.

function run_pending_migrations(PDO $pdo): void {
    // Migrations-Liste: ID => SQL
    // Jede Migration einmalig und in Reihenfolge ausführen.
    $migrations = [
        1 => "ALTER TABLE learning_sessions ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL DEFAULT NULL",
        2 => "ALTER TABLE lists ADD COLUMN IF NOT EXISTS speech_lang_b VARCHAR(10) NULL DEFAULT NULL",
        3 => "ALTER TABLE cards ADD COLUMN IF NOT EXISTS phonetic_b VARCHAR(200) NULL DEFAULT NULL",
        4 => "ALTER TABLE persons ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL DEFAULT NULL",
        5 => "ALTER TABLE persons ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0",
        // Bootstrap: bestehende Personen (ohne Zugangsdaten) bekommen ein Startpasswort,
        // "Beat" wird Admin. Sicherheitsnetz falls niemand so heisst: kleinste id wird Admin,
        // damit nach dem Deploy niemand komplett ausgesperrt ist.
        6 => function (PDO $pdo): void {
            $hash = password_hash('123456', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE persons SET password_hash = ? WHERE password_hash IS NULL")->execute([$hash]);
            $pdo->exec("UPDATE persons SET is_admin = 1 WHERE name = 'Beat'");

            $has_admin = (int) $pdo->query("SELECT COUNT(*) FROM persons WHERE is_admin = 1")->fetchColumn();
            if ($has_admin === 0) {
                $pdo->exec("UPDATE persons SET is_admin = 1 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM persons) t)");
            }
        },
        7 => "ALTER TABLE persons ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL DEFAULT NULL",
        8 => "ALTER TABLE persons ADD UNIQUE INDEX IF NOT EXISTS idx_persons_email (email)",
        9 => "ALTER TABLE persons ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(64) NULL DEFAULT NULL",
        10 => "ALTER TABLE persons ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME NULL DEFAULT NULL",
    ];

    // db_version aus settings lesen — falls Tabelle noch nicht existiert (vor install.php): abbrechen
    try {
        $stmt = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'db_version'");
        $row  = $stmt->fetchColumn();
    } catch (PDOException $e) {
        return;
    }

    if ($row === false) {
        // db_version-Eintrag noch nicht vorhanden — initialisieren
        $pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('db_version', '0')");
        $current = 0;
    } else {
        $current = (int) $row;
    }

    // Alle fehlenden Migrationen in Reihenfolge ausführen
    foreach ($migrations as $id => $migration) {
        if ($id <= $current) continue;

        if (is_callable($migration)) {
            $migration($pdo);
        } else {
            $pdo->exec($migration);
        }

        $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'db_version'")
            ->execute([$id]);
    }
}
