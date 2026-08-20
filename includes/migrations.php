<?php
// Automatische DB-Migrationen — wird von db.php bei jedem Request aufgerufen.
// Neue Migrationen am Ende der Liste anfügen, Nummerierung fortlaufend.
// Bereits ausgeführte Migrationen werden anhand der db_version in der settings-Tabelle übersprungen.

// Höchste vergebene Migrations-ID — von install.php verwendet, um eine frisch angelegte
// Datenbank sofort als "auf dem aktuellsten Stand" zu stempeln (siehe migration_definitions()).
function latest_migration_id(): int {
    return max(array_keys(migration_definitions()));
}

// Migrations-Definitionen als eigene Funktion, damit install.php die höchste ID abfragen kann,
// ohne die Migrationen auszuführen.
function migration_definitions(): array {
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
    // Nächste neue Migration hier mit der ID 19 beginnen.
    return [
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

        // Datenkorrektur (v3.5.1): tool_add_cards() im MCP-Server legte bisher nur die Karte an,
        // aber keinen card_progress-Eintrag. Solche Karten wurden in edit.php per COALESCE-Default
        // fälschlich als "Warteschlange" angezeigt, waren aber wegen des INNER JOIN in home.php
        // unsichtbar UND wurden von activate_daily_cards() (learn.php) nie aktiviert. Backfill für
        // alle betroffenen Karten, nicht nur eine Liste — legt für die Besitzer:in jeder Liste den
        // fehlenden card_progress-Eintrag als 'queued' an.
        16 => "INSERT INTO card_progress (person_id, card_id, status)
               SELECT l.person_id, c.id, 'queued'
               FROM cards c
               JOIN lists l ON l.id = c.list_id
               LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.person_id = l.person_id
               WHERE cp.id IS NULL",

        // Tags pro Karte (v3.9.0): eigene, pro Person eigenständige Tags-Tabelle + n:m-Verknüpfung
        // zu cards statt Freitextfeld — verhindert Substring-Fehltreffer beim Filtern (z.B.
        // "#Wetter" würde in einem Freitextfeld auch "#Wetterbericht" matchen) und hält
        // Schreibweisen konsistent. Siehe includes/tags.php.
        17 => function (PDO $pdo): void {
            $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
                id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                person_id  INT          NOT NULL,
                name       VARCHAR(100) NOT NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_person_tag (person_id, name),
                FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS card_tags (
                card_id INT NOT NULL,
                tag_id  INT NOT NULL,
                PRIMARY KEY (card_id, tag_id),
                FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        },

        // Aktivierungsdatum je Karte (v3.17.0): Bis dahin wurde "wie viele neue Karten wurden heute
        // schon aktiviert" indirekt aus (status='active' AND next_due_date=heute AND leitner_box=1)
        // erschlossen. Dieser Hilfsindikator verschwindet, sobald die Karte beantwortet wird (sie
        // verlaesst Fach 1 bzw. bekommt ein spaeteres Datum) — dadurch war das Tageslimit nach einer
        // abgeschlossenen Session wieder "frei" und jede weitere Session desselben Tages aktivierte
        // erneut bis zu DAILY_CARD_LIMIT neue Karten. Mit einem echten Datumsfeld ist die Zaehlung
        // unabhaengig vom spaeteren Lernverlauf. Bestandsdaten bleiben NULL: am ersten Tag nach der
        // Migration kann das Tageskontingent daher einmalig neu ausgeschoepft werden, danach greift
        // die Zaehlung korrekt.
        18 => "ALTER TABLE card_progress ADD COLUMN activated_on DATE NULL DEFAULT NULL",
    ];
}

function run_pending_migrations(PDO $pdo): void {
    $migrations = migration_definitions();

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
