<?php
/**
 * Anmeldung mit dartx account
 *   /sso.php?start=1&next=/noten  -> Weiterleitung zu dartx account
 *   /sso.php?link=1               -> bestehendes Konto verbinden (angemeldet)
 *   /sso.php?code=…&state=…       -> Rückkehr von dartx account
 */
require_once __DIR__ . '/lib/konto.php';

header('Cache-Control: no-store');
if (!konto_enabled()) {
    header('Location: /login.php');
    exit;
}

if (isset($_GET['start'])) {
    header('Location: ' . konto_begin('login', (string) ($_GET['next'] ?? '/')));
    exit;
}
if (isset($_GET['link'])) {
    $u = current_user();
    if (!$u || $u['is_admin']) {
        header('Location: /login.php');
        exit;
    }
    header('Location: ' . konto_begin('link', '/'));
    exit;
}

try {
    $target = konto_callback();
    header('Location: ' . $target);
} catch (Throwable $e) {
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Die Anmeldung mit ' . KONTO_NAME . ' hat nicht geklappt.';
    header('Location: /login.php?konto_error=' . rawurlencode($msg));
}
exit;
