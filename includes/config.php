<?php
define('APP_VERSION', '3.2.24');
define('TIMEZONE', 'Europe/Zurich');
define('LEITNER_INTERVALS', [1 => 1, 2 => 2, 3 => 7, 4 => 14, 5 => 30]);
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
];
if (file_exists(__DIR__ . '/config-runtime.php')) {
    $_rt = array_merge($_rt, require __DIR__ . '/config-runtime.php');
}
foreach ($_rt as $_k => $_v) define($_k, $_v);
unset($_rt, $_k, $_v);
