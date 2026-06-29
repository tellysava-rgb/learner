<?php
define('APP_NAME', 'Learners');
define('APP_VERSION', '0.7.1');
define('TIMEZONE', 'Europe/Zurich');
define('SESSION_TIMEOUT', 3600); // 60 Minuten

define('DAILY_CARD_LIMIT', 10);
define('LEITNER_DEFAULT_CARDS', 20);
define('LEITNER_INTERVALS', [1 => 1, 2 => 2, 3 => 7, 4 => 14, 5 => 30]);

define('DRILL_SESSION_SECONDS', 180); // 3 Minuten
define('DRILL_TOO_HARD_LIMIT', 5);
define('DRILL_MASTERY_THRESHOLD', 3); // 3× hintereinander korrekt = gemeistert
define('DRILL_KNOWN_RATIO', 9); // 9 bekannte Karten pro 1 neue/unbekannte

date_default_timezone_set(TIMEZONE);
