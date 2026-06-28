<?php
define('APP_NAME', 'Learner');
define('APP_VERSION', '0.3.0');
define('TIMEZONE', 'Europe/Zurich');
define('SESSION_TIMEOUT', 3600); // 30 Minuten = 1800 Sekunden

define('DAILY_CARD_LIMIT', 10);
define('LEITNER_INTERVALS', [1 => 1, 2 => 2, 3 => 7, 4 => 14, 5 => 30]);

define('DRILL_ACTIVE_CARDS', 3);
define('DRILL_SESSION_SECONDS', 600); // 10 Minuten
define('DRILL_TOO_HARD_LIMIT', 5);

date_default_timezone_set(TIMEZONE);
