<?php
// Sprachlevel-Verwaltung: CEFR-Niveau (A1-C2) pro Person UND Sprache (Freitext-Sprachname, analog
// zu lists.language_a/b — kein festes Enum für die Sprache selbst). Eigene Tabelle statt Feld auf
// persons, weil eine Person mehrere Sprachen mit unterschiedlichem Niveau lernen kann (z.B.
// Englisch B2, Italienisch A1) — siehe profile.php. Fehlt für eine Sprache ein Eintrag, gilt A1 als
// Anwendungs-Default (get_effective_level_for_language()), nicht als eigene DB-Zeile.

const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

// Alle Sprachlevel-Einträge einer Person, alphabetisch nach Sprache — für die CRUD-Liste in
// profile.php.
function get_person_language_levels(PDO $pdo, int $person_id): array {
    $stmt = $pdo->prepare("SELECT id, language, cefr_level FROM person_language_levels WHERE person_id = ? ORDER BY language");
    $stmt->execute([$person_id]);
    return $stmt->fetchAll();
}

// Legt einen Sprachlevel-Eintrag an oder überschreibt den bestehenden für dieselbe Sprache (über
// den UNIQUE-Key person_id+language) — "Hinzufügen" und "Ändern" sind für den Aufrufer dieselbe
// Aktion, kein separates Prüfen ob der Eintrag bereits existiert nötig.
function set_person_language_level(PDO $pdo, int $person_id, string $language, string $cefr_level): void {
    $stmt = $pdo->prepare("
        INSERT INTO person_language_levels (person_id, language, cefr_level) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE cefr_level = VALUES(cefr_level)
    ");
    $stmt->execute([$person_id, trim($language), $cefr_level]);
}

function delete_person_language_level(PDO $pdo, int $person_id, int $level_id): void {
    $pdo->prepare("DELETE FROM person_language_levels WHERE id = ? AND person_id = ?")->execute([$level_id, $person_id]);
}

// Effektives Niveau einer Sprache für eine Person — 'A1', falls kein Eintrag existiert (bewusste
// Anwendungsentscheidung: "kein Eintrag" bedeutet Anfängerniveau, nicht "unbekannt"). trim() nötig,
// da die DB selbst nicht trimmt (Gross-/Kleinschreibung übernimmt bereits die DB-Standard-Collation
// utf8mb4_*_ci, analog zu tags.name).
function get_effective_level_for_language(PDO $pdo, int $person_id, string $language): string {
    $stmt = $pdo->prepare("SELECT cefr_level FROM person_language_levels WHERE person_id = ? AND language = ?");
    $stmt->execute([$person_id, trim($language)]);
    return $stmt->fetchColumn() ?: 'A1';
}

// true, sobald mindestens eine aktive Liste dieser Person (gematcht über deren language_b, die
// Ziel-/Fremdsprache — Sprache A ist typischerweise die Muttersprache) ein effektives Niveau A1
// oder A2 hat, inkl. Default-A1 für Sprachen ohne eigenen Eintrag. Grundlage für die Sichtbarkeit
// der GLOBALEN Leitner-/Drill-Buttons auf home.php — die Pro-Liste-Buttons sind davon unabhängig
// immer sichtbar.
function person_has_beginner_active_list(PDO $pdo, int $person_id): bool {
    $stmt = $pdo->prepare("SELECT DISTINCT language_b FROM lists WHERE person_id = ? AND is_active = 1");
    $stmt->execute([$person_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $language_b) {
        $level = get_effective_level_for_language($pdo, $person_id, $language_b);
        if (in_array($level, ['A1', 'A2'], true)) return true;
    }
    return false;
}

// CEFR-Einzelniveau auf einen der drei Buckets aus infos/lernplan.php und infos/skala.php abbilden
// (dort sind A1+A2, B1+B2, C1+C2 jeweils zu einem Plan/einer Werte-Gruppe zusammengefasst).
function cefr_to_bucket(string $cefr_level): string {
    return match ($cefr_level) {
        'A1', 'A2' => 'a1a2',
        'B1', 'B2' => 'b1b2',
        'C1', 'C2' => 'c1plus',
        default    => 'a1a2',
    };
}
