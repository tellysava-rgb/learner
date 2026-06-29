<?php
// Vorlage für db-credentials.php
// Diese Datei kopieren, umbenennen zu "db-credentials.php" und mit den echten Zugangsdaten füllen.
// db-credentials.php ist in .gitignore — nie committen!

$_db_credentials = [

    'dev' => [
        'host' => 'localhost',         // Datenbankserver (lokal: localhost)
        'name' => 'learner',           // Name der Datenbank
        'user' => 'root',              // Datenbankbenutzer
        'pass' => '',                  // Passwort (lokal oft leer)
    ],

    'prod' => [
        'host' => 'localhost',         // Meist localhost, auch auf dem Webserver
        'name' => 'dein_db_name',      // Name der Datenbank beim Hoster
        'user' => 'dein_db_benutzer',  // Datenbankbenutzer beim Hoster
        'pass' => 'dein_db_passwort',  // Datenbankpasswort beim Hoster
    ],

];
