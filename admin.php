<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';

start_session();
if (!current_user()) {
    header('Location: /login.php?next=' . rawurlencode('/admin.php'));
    exit;
}
if (!is_admin()) {
    http_response_code(403);
    page_header('Kein Zugriff');
    echo '<h1>Kein Zugriff</h1><p class="sub">Diese Seite ist nur für den Admin.</p><a class="btn" href="/">Zur App</a>';
    page_footer();
    exit;
}

$pdo = db();
$today = date('Y-m-d 00:00:00');
$onlineSince = date('Y-m-d H:i:s', time() - ONLINE_MINUTES * 60);
$weekAgo = date('Y-m-d H:i:s', time() - 7 * 86400);

$count = function (string $sql, array $params = []) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

$totalUsers   = $count('SELECT COUNT(*) FROM users');
$onlineNow    = $count('SELECT COUNT(*) FROM users WHERE last_seen >= ?', [$onlineSince]);
$loginsToday  = $count('SELECT COUNT(*) FROM logins WHERE created_at >= ?', [$today]);
$usersToday   = $count('SELECT COUNT(DISTINCT user_id) FROM logins WHERE created_at >= ?', [$today]);
$usersWeek    = $count('SELECT COUNT(DISTINCT user_id) FROM logins WHERE created_at >= ?', [$weekAgo]);
$loginsTotal  = $count('SELECT COUNT(*) FROM logins');
$newToday     = $count('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$today]);
$kontoUsers   = $count('SELECT COUNT(*) FROM users WHERE konto_sub IS NOT NULL');

$stmt = $pdo->prepare('SELECT u.id, u.username, u.created_at, u.last_login, u.last_seen, u.konto_sub,
        (SELECT COUNT(*) FROM logins l WHERE l.user_id = u.id) AS login_count
    FROM users u
    ORDER BY (u.last_login IS NULL), u.last_login DESC, u.created_at DESC');
$stmt->execute();
$users = $stmt->fetchAll();

function fmt(?string $dt): string
{
    if (!$dt) {
        return '–';
    }
    return date('d.m.Y H:i', strtotime($dt));
}

page_header('Admin', true);
?>
  <meta http-equiv="refresh" content="60">
  <h1>Admin-Übersicht</h1>
  <p class="sub">Stand: <?= e(date('d.m.Y H:i')) ?> Uhr · aktualisiert sich jede Minute</p>

  <div class="stats">
    <div class="stat"><div class="n"><?= $totalUsers ?></div><div class="l">Registrierte Nutzer</div></div>
    <div class="stat"><div class="n"><?= $onlineNow ?></div><div class="l">Gerade online (letzte <?= (int) ONLINE_MINUTES ?> Min.)</div></div>
    <div class="stat"><div class="n"><?= $usersToday ?></div><div class="l">Heute angemeldet (<?= $loginsToday ?> Anmeldungen)</div></div>
    <div class="stat"><div class="n"><?= $usersWeek ?></div><div class="l">Angemeldet in den letzten 7 Tagen</div></div>
    <div class="stat"><div class="n"><?= $loginsTotal ?></div><div class="l">Anmeldungen insgesamt</div></div>
    <div class="stat"><div class="n"><?= $newToday ?></div><div class="l">Heute neu registriert</div></div>
    <div class="stat"><div class="n"><?= $kontoUsers ?></div><div class="l">Mit <?= e(KONTO_NAME) ?> verbunden</div></div>
  </div>

  <h1 style="font-size:16px;margin-top:22px">Alle Nutzer</h1>
  <?php if (!$users): ?>
    <p class="sub">Noch niemand registriert.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Benutzer</th><th>Registriert</th><th>Letzte Anmeldung</th><th>Anmeldungen</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $on = $u['last_seen'] && $u['last_seen'] >= $onlineSince; ?>
        <tr>
          <td><span class="dot<?= $on ? ' on' : '' ?>" title="<?= $on ? 'online' : 'offline' ?>"></span><?= e($u['username']) ?><?= $u['konto_sub'] ? ' <span class="tag">dartx</span>' : '' ?></td>
          <td><?= e(fmt($u['created_at'])) ?></td>
          <td><?= e(fmt($u['last_login'])) ?></td>
          <td><?= (int) $u['login_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="row">
    <a class="btn secondary" href="/" onclick="if (window.opener) { window.close(); return false; }">Zur App</a>
    <a class="btn" href="/logout.php">Abmelden</a>
  </div>
<?php
page_footer();
