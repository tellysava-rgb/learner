<?php
define('APP_VERSION', '2.6.2');
define('TIMEZONE', 'Europe/Zurich');
define('LEITNER_INTERVALS', [1 => 1, 2 => 2, 3 => 7, 4 => 14, 5 => 30]);
date_default_timezone_set(TIMEZONE);

// Laufzeit-Einstellungen: aus config-runtime.php laden wenn vorhanden (gitignored, nie deployed)
// Sonst: Standardwerte
$_rt = [
    'APP_NAME'               => 'Learners',
    'SESSION_TIMEOUT'        => 3600,
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
