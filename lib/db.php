<?php
require_once __DIR__ . '/../config.php';

date_default_timezone_set(APP_TIMEZONE);

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (DB_DRIVER === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            last_login DATETIME NULL,
            last_seen DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS logins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_logins_user (user_id),
            INDEX idx_logins_created (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            last_login TEXT NULL,
            last_seen TEXT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS logins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            created_at TEXT NOT NULL
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logins_user ON logins(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logins_created ON logins(created_at)');
    }

    add_konto_columns($pdo);

    return $pdo;
}

/** Spalten für dartx account ergänzen (auch bei schon bestehenden Datenbanken). */
function add_konto_columns(PDO $pdo): void
{
    $mysql = DB_DRIVER === 'mysql';
    $existing = [];
    if ($mysql) {
        foreach ($pdo->query('SHOW COLUMNS FROM users') as $c) {
            $existing[] = $c['Field'];
        }
    } else {
        foreach ($pdo->query('PRAGMA table_info(users)') as $c) {
            $existing[] = $c['name'];
        }
    }
    $columns = $mysql
        ? ['konto_sub' => 'VARCHAR(48) NULL', 'konto_token' => 'CHAR(64) NULL', 'konto_checked_at' => 'INT UNSIGNED NOT NULL DEFAULT 0']
        : ['konto_sub' => 'TEXT NULL', 'konto_token' => 'TEXT NULL', 'konto_checked_at' => 'INTEGER NOT NULL DEFAULT 0'];
    foreach ($columns as $name => $type) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $name $type");
        }
    }
    if (!in_array('konto_sub', $existing, true)) {
        $pdo->exec('CREATE UNIQUE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'idx_users_konto_sub ON users(konto_sub)');
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
