<?php
define('APP_NAME', 'Learner');
define('APP_VERSION', '0.6.1');
define('TIMEZONE', 'Europe/Zurich');
define('SESSION_TIMEOUT', 3600); // 30 Minuten = 1800 Sekunden

define('DAILY_CARD_LIMIT', 10);
define('LEITNER_INTERVALS', [1 => 1, 2 => 2, 3 => 7, 4 => 14, 5 => 30]);

define('DRILL_SESSION_SECONDS', 600); // 10 Minuten
define('DRILL_TOO_HARD_LIMIT', 5);
define('DRILL_MASTERY_THRESHOLD', 3); // 3× hintereinander korrekt = gemeistert
define('DRILL_KNOWN_RATIO', 9);       // 9 bekannte Karten pro 1 neue/unbekannte

date_default_timezone_set(TIMEZONE);
