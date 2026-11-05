<?php
require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $lifetime = SESSION_DAYS * 86400;

    // Eigener Speicherort, damit der Webhoster die Sessions nicht vorzeitig löscht.
    $savePath = __DIR__ . '/../data/sessions';
    if (!is_dir($savePath)) {
        @mkdir($savePath, 0770, true);
    }
    if (is_dir($savePath) && is_writable($savePath)) {
        session_save_path($savePath);
    }
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.use_strict_mode', '1');

    session_name('schulapp_session');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['username'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'],
        'is_admin' => !empty($_SESSION['is_admin']),
    ];
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['is_admin'];
}

/** Speichert die letzte Aktivität (max. 1x pro Minute in die DB). */
function touch_last_seen(): void
{
    $u = current_user();
    if ($u === null || $u['is_admin'] || $u['id'] === null) {
        return;
    }
    $last = $_SESSION['last_seen_write'] ?? 0;
    if (time() - $last < 60) {
        return;
    }
    $_SESSION['last_seen_write'] = time();
    $stmt = db()->prepare('UPDATE users SET last_seen = ? WHERE id = ?');
    $stmt->execute([now(), $u['id']]);
    if ($stmt->rowCount() === 0) {
        // Benutzer wurde gelöscht -> abmelden
        logout();
    }
}

/**
 * Prüft Benutzername + Passwort. Gibt bei Erfolg true zurück und meldet an.
 */
function attempt_login(string $username, string $password): bool
{
    start_session();
    $username = trim($username);

    // Admin (aus config.php)
    if (strcasecmp($username, ADMIN_USERNAME) === 0) {
        if (hash_equals((string) ADMIN_PASSWORD, $password)) {
            session_regenerate_id(true);
            $_SESSION = [
                'user_id' => null,
                'username' => ADMIN_USERNAME,
                'is_admin' => true,
            ];
            return true;
        }
        return false;
    }

    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    $time = now();
    db()->prepare('UPDATE users SET last_login = ?, last_seen = ? WHERE id = ?')
        ->execute([$time, $time, $user['id']]);
    db()->prepare('INSERT INTO logins (user_id, created_at) VALUES (?, ?)')
        ->execute([$user['id'], $time]);

    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'is_admin' => false,
        'last_seen_write' => time(),
    ];
    return true;
}

/** Registriert einen neuen Benutzer. Gibt eine Fehlermeldung oder null zurück. */
function register_user(string $username, string $password, string $password2): ?string
{
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,30}$/', $username)) {
        return 'Der Benutzername muss 3–30 Zeichen lang sein (Buchstaben, Zahlen, _ . -).';
    }
    if (strcasecmp($username, ADMIN_USERNAME) === 0) {
        return 'Dieser Benutzername ist nicht erlaubt.';
    }
    if (strlen($password) < 6) {
        return 'Das Passwort muss mindestens 6 Zeichen lang sein.';
    }
    if ($password !== $password2) {
        return 'Die Passwörter stimmen nicht überein.';
    }

    $stmt = db()->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(?)');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        return 'Dieser Benutzername ist bereits vergeben.';
    }

    try {
        db()->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), now()]);
    } catch (PDOException $e) {
        return 'Dieser Benutzername ist bereits vergeben.';
    }
    return null;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
    session_destroy();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): bool
{
    start_session();
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Nur relative Weiterleitungen innerhalb der Seite erlauben. */
function safe_next(?string $next): string
{
    if (!is_string($next) || $next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return '/';
    }
    return $next;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
