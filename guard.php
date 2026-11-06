<?php
/**
 * Schützt alle Seiten der App: Nur angemeldete Nutzer bekommen die Dateien.
 * Wird über die .htaccess für jede Anfrage aufgerufen, die nicht öffentlich ist.
 */
require_once __DIR__ . '/lib/konto.php';

$base = __DIR__;
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$rel = trim($path, '/');

function not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nicht gefunden';
    exit;
}

// Gesperrte Pfade: versteckte Dateien, PHP, interne Ordner
if (
    str_contains($rel, "\0")
    || preg_match('#(^|/)\.#', $rel)
    || preg_match('#\.php$#i', $rel)
    || preg_match('#^(data|lib)(/|$)#i', $rel)
) {
    not_found();
}

// Passende Datei suchen: "/" -> index.html, "/noten" -> noten.html, "/noten/detail" -> noten/detail.html
$candidates = $rel === '' ? ['index.html'] : [$rel . '.html', $rel, $rel . '/index.html'];
$file = null;
foreach ($candidates as $c) {
    $real = realpath($base . '/' . $c);
    if ($real !== false && is_file($real) && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        $file = $real;
        break;
    }
}

$types = [
    'html' => 'text/html; charset=utf-8',
    'txt' => 'text/plain; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'json' => 'application/json',
    'webmanifest' => 'application/manifest+json',
    'png' => 'image/png',
    'ico' => 'image/x-icon',
    'svg' => 'image/svg+xml',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'woff2' => 'font/woff2',
];
$ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : 'html';
if ($file && !isset($types[$ext])) {
    not_found();
}

$user = current_user();
if ($user !== null && ($kontoMsg = konto_check()) !== null) {
    // Bei dartx account gesperrt, gelöscht oder Zugriff entfernt
    $user = null;
    if ($ext === 'html') {
        header('Cache-Control: no-store');
        header('Location: /login.php?konto_error=' . rawurlencode($kontoMsg));
        exit;
    }
}
if ($user === null) {
    if ($ext === 'html') {
        $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        $next = '/' . $rel . ($query ? '?' . $query : '');
        header('Cache-Control: no-store');
        header('Location: /login.php?next=' . rawurlencode($next));
        exit;
    }
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Bitte anmelden';
    exit;
}
touch_last_seen();
session_write_close();

if ($file === null) {
    $file = $base . '/404.html';
    $ext = 'html';
    http_response_code(404);
}

header('Content-Type: ' . $types[$ext]);
header('Cache-Control: private, no-cache');

if ($ext !== 'html') {
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// Kleine Leiste mit Benutzername, Abmelden und (für Admin) dem Admin-Fenster einfügen
$name = e($user['username']);
$adminLink = $user['is_admin']
    ? '<a href="/admin.php" onclick="window.open(\'/admin.php\',\'schulapp_admin\',\'width=560,height=680\');return false;" style="color:#4f46e5;font-weight:600;text-decoration:none">Admin</a><span style="opacity:.4">·</span>'
    : '';
$kontoLink = '';
if (konto_enabled() && !$user['is_admin']) {
    $st = db()->prepare('SELECT konto_sub FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    if (!$st->fetchColumn()) {
        $kontoLink = '<a href="/sso.php?link=1" title="Mit ' . e(KONTO_NAME) . ' verbinden" style="color:#c2410c;text-decoration:none">Mit dartx verbinden</a><span style="opacity:.4">·</span>';
    }
}
$bar = <<<HTML
<div id="schulapp-auth" style="position:fixed;top:8px;right:8px;z-index:2147483000;display:flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.92);border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(15,23,42,.1);font:500 12px system-ui,-apple-system,sans-serif;color:#334155">
<span>👤 {$name}</span><span style="opacity:.4">·</span>{$adminLink}{$kontoLink}<a href="/logout.php" style="color:#dc2626;text-decoration:none">Abmelden</a>
</div>
HTML;

// Per Script einfügen und wieder einsetzen, falls React die Seite neu aufbaut und die Leiste entfernt.
$barJson = json_encode($bar, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$script = <<<HTML
<script>(function(){var h={$barJson};function ensure(){if(!document.body||document.getElementById("schulapp-auth"))return;var d=document.createElement("div");d.innerHTML=h;document.body.appendChild(d.firstElementChild);}
ensure();document.addEventListener("DOMContentLoaded",ensure);window.addEventListener("load",ensure);
new MutationObserver(ensure).observe(document.documentElement,{childList:true,subtree:true});})();</script>
HTML;

$html = file_get_contents($file);
$pos = strripos($html, '</body>');
echo $pos === false ? $html . $script : substr($html, 0, $pos) . $script . substr($html, $pos);
