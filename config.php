<?php
/**
 * Konfiguration der Schul-App
 * ---------------------------
 * Hier kannst du das Admin-Passwort und die Datenbank einstellen.
 * Diese Datei ist per .htaccess vor direktem Aufruf im Browser geschützt.
 */

// ---------------------------------------------------------------
// Admin-Zugang
// Mit diesen Daten meldest du dich ganz normal auf der Login-Seite an.
// Danach siehst du das Admin-Fenster mit der Statistik.
// ---------------------------------------------------------------
define('ADMIN_USERNAME', 'Admin');
define('ADMIN_PASSWORD', '3518351881538153');

// ---------------------------------------------------------------
// Datenbank
// 'sqlite' = funktioniert sofort ohne Einrichtung (Datei im Ordner /data)
// 'mysql'  = MySQL/MariaDB vom Webhoster (unten Zugangsdaten eintragen)
// Die Tabellen werden beim ersten Aufruf automatisch angelegt.
// ---------------------------------------------------------------
define('DB_DRIVER', 'sqlite');

// SQLite
define('DB_SQLITE_PATH', __DIR__ . '/data/schulapp.sqlite');

// MySQL / MariaDB (nur nötig, wenn DB_DRIVER = 'mysql')
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'schulapp');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---------------------------------------------------------------
// Sonstiges
// ---------------------------------------------------------------
// Wie lange man angemeldet bleibt (in Tagen)
define('SESSION_DAYS', 30);
// Nutzer gelten als "online", wenn sie in den letzten X Minuten aktiv waren
define('ONLINE_MINUTES', 5);
// Neue Registrierungen erlauben?
define('ALLOW_REGISTRATION', true);
// Zeitzone
define('APP_TIMEZONE', 'Europe/Zurich');
