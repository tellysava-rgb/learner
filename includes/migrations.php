<?php
// Automatische DB-Migrationen — wird von db.php bei jedem Request aufgerufen.
// Neue Migrationen am Ende der Liste anfügen, Nummerierung fortlaufend.
// Bereits ausgeführte Migrationen werden anhand der db_version in der settings-Tabelle übersprungen.

function run_pending_migrations(PDO $pdo): void {
    // Migrations-Liste: ID => SQL
    // Jede Migration einmalig und in Reihenfolge ausführen.
    //
    // Migrationen 1–13 (v3.0.0 bis v3.2.20: Login-Modell, Aussprache/Lautschrift, is_active,
    // Entfernung von learning_sessions/session_lists) wurden entfernt — alle bekannten
    // Installationen (Dev + Prod) sind bereits auf dem aktuellen Schema (db_version 13),
    // und install.php bildet dieses Schema seit v3.2.20 vollständig von Grund auf ab, sodass
    // eine Neuinstallation ohnehin nie eine dieser Migrationen braucht. Nachzulesen in der
    // Git-Historie (includes/migrations.php vor v3.2.21), falls je ein sehr altes Backup
    // (vor v3.2.20) wiederhergestellt werden muss.
    // Nächste neue Migration hier mit der ID 16 beginnen (bestehende db_version bleibt bei 15).
    $migrations = [
        // Rate-Limiting für Login und "Passwort vergessen" (v3.2.23).
        14 => "CREATE TABLE IF NOT EXISTS auth_attempts (
                   id           INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
                   scope        VARCHAR(20) NOT NULL,
                   ip           VARCHAR(45) NOT NULL,
                   attempted_at DATETIME    NOT NULL,
                   INDEX idx_auth_attempts_lookup (scope, ip, attempted_at)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // "Für Drill vormerken" (v3.2.30): NULL = nicht vorgemerkt, 0..N-1 = korrekte Antworten
        // seit dem Vormerken — eigenständig von drill_mastery, siehe docs/ANFORDERUNGEN.md.
        15 => "ALTER TABLE card_progress ADD COLUMN drill_pinned_correct TINYINT NULL DEFAULT NULL",
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
