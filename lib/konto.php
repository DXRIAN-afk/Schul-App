<?php
/**
 * Anmeldung mit dartx account (OAuth 2 Authorization Code mit PKCE)
 * Ablauf wie bei Hexa Chat – siehe README von dartx account.
 */
require_once __DIR__ . '/auth.php';

function konto_enabled(): bool
{
    return KONTO_URL !== '';
}

function konto_base(): string
{
    return rtrim(KONTO_URL, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Rücksprung-Adresse (muss bei dartx account genau so eingetragen sein). */
function konto_redirect_uri(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host . '/sso.php';
}

function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/** Anmeldevorgang starten und Adresse bei dartx account liefern. mode: 'login' oder 'link'. */
function konto_begin(string $mode, string $next = '/'): string
{
    start_session();
    $state = bin2hex(random_bytes(32));
    $verifier = b64url(random_bytes(48));
    // Im Session-Cookie dieses Browsers gespeichert -> schützt vor untergeschobenen Anmeldungen
    $_SESSION['konto_sso'] = ['state' => $state, 'verifier' => $verifier, 'mode' => $mode, 'next' => safe_next($next), 'time' => time()];
    return konto_base() . '/authorize.php?' . http_build_query([
        'response_type' => 'code',
        'client_id' => KONTO_CLIENT_ID,
        'redirect_uri' => konto_redirect_uri(),
        'state' => $state,
        'code_challenge' => b64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
    ]);
}

/** Anfrage an dartx account (Server zu Server). Liefert [HTTP-Status, JSON-Daten]. */
function konto_request(string $path, ?array $post = null, string $token = ''): array
{
    $url = konto_base() . '/' . $path;
    $headers = ['Accept: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $body = $post !== null ? http_build_query($post) : null;
    if ($body !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => $body !== null, CURLOPT_POSTFIELDS => $body ?? '',
        ]);
        $res = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => $body !== null ? 'POST' : 'GET', 'header' => implode("\r\n", $headers),
            'content' => $body ?? '', 'timeout' => 8, 'ignore_errors' => true,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        $status = preg_match('~^HTTP/\S+\s+(\d{3})~', $http_response_header[0] ?? '', $m) ? (int) $m[1] : 0;
    }
    $data = is_string($res) ? json_decode($res, true) : null;
    return [$status, is_array($data) ? $data : []];
}

/** Freien Benutzernamen aus dem dartx-account-Namen bilden. */
function konto_username(string $wanted): string
{
    $base = preg_replace('/[^A-Za-z0-9_.\-]/', '', $wanted);
    if (strlen($base) < 3) {
        $base = 'user';
    }
    $base = substr($base, 0, 24);
    $name = $base;
    for ($i = 0; $i < 20; $i++) {
        $st = db()->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(?)');
        $st->execute([$name]);
        if (strcasecmp($name, ADMIN_USERNAME) !== 0 && !$st->fetchColumn()) {
            return $name;
        }
        $name = substr($base, 0, 24) . random_int(1000, 99999);
    }
    return 'user' . bin2hex(random_bytes(5));
}

/** Benutzer anmelden (Session setzen und Anmeldung zählen). */
function konto_login_user(array $user): void
{
    $time = now();
    db()->prepare('UPDATE users SET last_login = ?, last_seen = ? WHERE id = ?')->execute([$time, $time, $user['id']]);
    db()->prepare('INSERT INTO logins (user_id, created_at) VALUES (?, ?)')->execute([$user['id'], $time]);
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'is_admin' => false,
        'last_seen_write' => time(),
    ];
}

/**
 * Rückkehr von dartx account. Gibt bei Erfolg die Zieladresse zurück,
 * sonst wirft sie eine RuntimeException mit einer Meldung für den Benutzer.
 */
function konto_callback(): string
{
    start_session();
    $flow = $_SESSION['konto_sso'] ?? null;
    unset($_SESSION['konto_sso']);
    $state = (string) ($_GET['state'] ?? '');
    if (!is_array($flow) || $state === '' || !hash_equals($flow['state'], $state) || $flow['time'] < time() - 900) {
        throw new RuntimeException('Die Anmeldung ist abgelaufen. Bitte versuche es nochmals.');
    }
    if (isset($_GET['error'])) {
        throw new RuntimeException($_GET['error'] === 'access_denied' ? 'Anmeldung abgebrochen.' : 'Die Anmeldung mit ' . KONTO_NAME . ' hat nicht geklappt.');
    }
    [$status, $res] = konto_request('token.php', [
        'grant_type' => 'authorization_code',
        'code' => (string) ($_GET['code'] ?? ''),
        'redirect_uri' => konto_redirect_uri(),
        'client_id' => KONTO_CLIENT_ID,
        'code_verifier' => $flow['verifier'],
    ]);
    $info = $res['user'] ?? null;
    $token = (string) ($res['access_token'] ?? '');
    if ($status !== 200 || !is_array($info) || empty($info['sub']) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new RuntimeException((string) ($res['error_description'] ?? (KONTO_NAME . ' ist gerade nicht erreichbar. Bitte versuche es später erneut.')));
    }
    if (!empty($info['locked'])) {
        $reason = trim((string) ($info['lock_reason'] ?? ''));
        throw new RuntimeException('Dein ' . KONTO_NAME . ' ist gesperrt.' . ($reason !== '' ? ' Grund: ' . $reason : ''));
    }
    $sub = (string) $info['sub'];
    $st = db()->prepare('SELECT * FROM users WHERE konto_sub = ?');
    $st->execute([$sub]);
    $linked = $st->fetch() ?: null;

    // Bestehendes Schul-App-Konto mit dartx account verbinden
    if ($flow['mode'] === 'link') {
        $me = current_user();
        if (!$me || $me['is_admin'] || $me['id'] === null) {
            throw new RuntimeException('Bitte melde dich zuerst an.');
        }
        if ($linked && (int) $linked['id'] !== (int) $me['id']) {
            throw new RuntimeException('Dieser ' . KONTO_NAME . ' ist bereits mit einem anderen Konto verbunden.');
        }
        db()->prepare('UPDATE users SET konto_sub = ?, konto_token = ?, konto_checked_at = ? WHERE id = ?')
            ->execute([$sub, $token, time(), $me['id']]);
        return '/?konto=verbunden';
    }

    // Administrator von dartx account = Administrator der Schul-App
    if (!empty($info['is_admin']) && !$linked) {
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => null,
            'username' => ADMIN_USERNAME,
            'is_admin' => true,
            'konto_sub' => $sub,
            'konto_token' => $token,
            'konto_checked_at' => time(),
        ];
        return '/admin.php';
    }

    if (!$linked) {
        // Neu in der Schul-App: Konto ohne eigenes Passwort anlegen
        db()->prepare('INSERT INTO users (username, password_hash, created_at, konto_sub, konto_token, konto_checked_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([konto_username((string) ($info['username'] ?? '')), '', now(), $sub, $token, time()]);
        $st->execute([$sub]);
        $linked = $st->fetch();
    } else {
        db()->prepare('UPDATE users SET konto_token = ?, konto_checked_at = ? WHERE id = ?')->execute([$token, time(), $linked['id']]);
    }
    konto_login_user($linked);
    return $flow['next'];
}

/**
 * Alle 5 Minuten bei dartx account nachfragen, ob das Konto noch aktiv ist.
 * Gesperrt -> abmelden. Zugriff entfernt/gelöscht -> Verbindung lösen
 * (ohne eigenes Passwort zusätzlich abmelden).
 * Gibt eine Meldung zurück, wenn der Benutzer abgemeldet wurde, sonst null.
 */
function konto_check(): ?string
{
    $u = current_user();
    if (!$u || !konto_enabled()) {
        return null;
    }
    if ($u['is_admin']) {
        if (empty($_SESSION['konto_token']) || ($_SESSION['konto_checked_at'] ?? 0) > time() - 300) {
            return null;
        }
        [$status, $info] = konto_request('userinfo.php', null, (string) $_SESSION['konto_token']);
        $_SESSION['konto_checked_at'] = time();
        if ($status === 401 || ($status === 200 && (!empty($info['locked']) || empty($info['is_admin'])))) {
            logout();
            return 'Bitte melde dich erneut an.';
        }
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$u['id']]);
    $row = $st->fetch();
    if (!$row || empty($row['konto_sub']) || empty($row['konto_token']) || (int) $row['konto_checked_at'] > time() - 300) {
        return null;
    }
    [$status, $info] = konto_request('userinfo.php', null, (string) $row['konto_token']);
    if ($status === 200 && ($info['sub'] ?? '') === $row['konto_sub']) {
        db()->prepare('UPDATE users SET konto_checked_at = ? WHERE id = ?')->execute([time(), $row['id']]);
        if (!empty($info['locked'])) {
            logout();
            $reason = trim((string) ($info['lock_reason'] ?? ''));
            return 'Dein ' . KONTO_NAME . ' wurde gesperrt.' . ($reason !== '' ? ' Grund: ' . $reason : '');
        }
        return null;
    }
    if ($status === 401) {
        db()->prepare('UPDATE users SET konto_token = NULL, konto_checked_at = ? WHERE id = ?')->execute([time(), $row['id']]);
        if ((string) $row['password_hash'] === '') {
            logout();
            return 'Bitte melde dich erneut mit ' . KONTO_NAME . ' an.';
        }
        return null;
    }
    // dartx account nicht erreichbar: weiterarbeiten, in einer Minute erneut prüfen
    db()->prepare('UPDATE users SET konto_checked_at = ? WHERE id = ?')->execute([time() - 240, $row['id']]);
    return null;
}

function konto_logo(int $size = 22): string
{
    return '<svg viewBox="0 0 512 512" width="' . $size . '" height="' . $size . '" aria-hidden="true" style="border-radius:6px;flex:none">'
        . '<rect width="512" height="512" rx="116" fill="#1F2023"/><rect x="196" y="190" width="214" height="150" rx="34" fill="none" stroke="#E8781F" stroke-width="18"/>'
        . '<rect x="96" y="146" width="236" height="162" rx="38" fill="#E8781F"/><circle cx="170" cy="210" r="30" fill="#1F2023"/>'
        . '<path d="M124 278 a46 40 0 0 1 92 0 Z" fill="#1F2023"/><rect x="236" y="196" width="66" height="16" rx="8" fill="#1F2023"/>'
        . '<rect x="236" y="232" width="48" height="16" rx="8" fill="#1F2023" opacity=".7"/></svg>';
}

/** Knopf «Mit dartx account anmelden» */
function konto_button(string $next = '/'): string
{
    if (!konto_enabled()) {
        return '';
    }
    return '<div class="divider"><span>oder</span></div>'
        . '<a class="btn konto" href="/sso.php?start=1&amp;next=' . e(rawurlencode($next)) . '">' . konto_logo() . '<span>Mit <b>' . e(KONTO_NAME) . '</b> anmelden</span></a>';
}
