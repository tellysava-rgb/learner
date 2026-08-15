<?php
define('APP_VERSION', '3.10.0');
define('TIMEZONE', 'Europe/Zurich');
define('LEITNER_INTERVALS', [1 => 1, 2 => 2, 3 => 7, 4 => 14, 5 => 30]);
// Untere Grenze für den aktiven Drill-Pool (siehe DRILL_CARDS_PER_MINUTE) — auch eine sehr kurze
// Session bekommt noch ein sinnvoll grosses Arbeitsdeck statt z.B. nur 1-2 Karten.
define('DRILL_MIN_ACTIVE_CARDS', 5);
// Maximale Länge eines einzelnen Tag-Namens (Spalte tags.name, VARCHAR) — siehe includes/tags.php.
define('TAG_NAME_MAX_LENGTH', 100);
date_default_timezone_set(TIMEZONE);

// Laufzeit-Einstellungen: aus config-runtime.php laden wenn vorhanden (gitignored, nie deployed)
// Sonst: Standardwerte
$_rt = [
    'APP_NAME'               => 'Learners',
    'SESSION_TIMEOUT'        => 60, // in Minuten

    // Basis-URL der Installation, z.B. 'https://example.com/learner' (ohne Slash am Ende).
    // Wird für Links in ausgehenden E-Mails (Passwort-Reset) verwendet. Muss gesetzt sein, weil
    // $_SERVER['HTTP_HOST'] vom Client kommt und damit fälschbar ist: ein gefälschter Host-Header
    // würde sonst einen Reset-Link auf eine fremde Domain in die Mail schreiben (Token-Diebstahl).
    'APP_BASE_URL'           => '',

    // Absenderadresse für ausgehende E-Mails (Passwort-Reset, Test-Mail). Leer = 'no-reply@' plus
    // Host aus APP_BASE_URL. Wichtig, wenn die App auf einer Subdomain läuft: SPF wird NICHT von
    // der Hauptdomain vererbt, und eine DMARC-Policy der Hauptdomain gilt trotzdem auch für
    // Subdomains. Ohne eigenen SPF-Record der Subdomain scheitert DMARC — die Mail wird beim
    // Empfänger (z.B. Gmail) einsortiert oder verworfen, obwohl mail() Erfolg meldet. Deshalb hier
    // eine Adresse der Domain eintragen, deren SPF den Mailserver des Hosters abdeckt.
    'MAIL_FROM'              => '',

    'DAILY_CARD_LIMIT'       => 10,
    'LEITNER_DEFAULT_CARDS'  => 20,
    'DRILL_SESSION_SECONDS'  => 600,
    'DRILL_TOO_HARD_LIMIT'   => 5,
    'DRILL_MASTERY_THRESHOLD'=> 3,
    'DRILL_KNOWN_RATIO'      => 9,

    // Begrenzt, wie viele Karten pro Session überhaupt in die Rotation genommen werden — an die
    // Timer-Minuten gekoppelt (aktiver Pool = max(DRILL_MIN_ACTIVE_CARDS, Minuten × dieser Wert)).
    // Ohne diese Grenze wird die komplette Liste auf einmal geladen: bei einer grossen Liste
    // verdünnt sich die Wiederholung jeder einzelnen Karte so stark, dass "3× hintereinander
    // richtig" (Mastery-Schwelle) in einer kurzen Session kaum je erreicht wird, egal wie viele
    // Karten insgesamt beantwortet wurden. Überzählige Karten bleiben einfach liegen und werden
    // in einer künftigen Session neu geladen (kein Datenverlust, nur zeitlich gestreckt).
    'DRILL_CARDS_PER_MINUTE' => 1.0,

    // Priorität "Für Drill vormerken": 'absolute' = vorgemerkte Karte immer zuerst (solange
    // welche vorgemerkt sind), 'weighted' = alle DRILL_PIN_RATIO Karten eine vorgemerkte
    // einschieben, normale known/new-Rotation läuft parallel weiter.
    'DRILL_PIN_MODE'         => 'weighted',
    'DRILL_PIN_RATIO'        => 5,

    // Debug-Modus (Einstellungen → Debug): zeigt Admins nach jeder Antwort in Leitner/Drill ein
    // Panel mit Vorher/Nachher-Status der Karte (Fach, Fälligkeit, Zähler etc.).
    'DEBUG_MODE'             => false,
];
if (file_exists(__DIR__ . '/config-runtime.php')) {
    $_rt = array_merge($_rt, require __DIR__ . '/config-runtime.php');
}
foreach ($_rt as $_k => $_v) define($_k, $_v);
unset($_rt, $_k, $_v);
