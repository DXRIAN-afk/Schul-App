<?php
require_once __DIR__ . '/lib/konto.php';
require_once __DIR__ . '/lib/layout.php';

start_session();
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '/');

// Schon angemeldet?
if (current_user()) {
    header('Location: ' . (is_admin() ? '/admin.php' : $next));
    exit;
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (!check_csrf()) {
        $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
    } elseif ($username === '' || $password === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } elseif (attempt_login($username, $password)) {
        header('Location: ' . (is_admin() ? '/admin.php' : $next));
        exit;
    } else {
        usleep(random_int(300000, 700000)); // bremst Passwort-Raten
        $error = 'Benutzername oder Passwort ist falsch.';
    }
}

page_header('Anmelden');
?>
  <h1>Anmelden</h1>
  <p class="sub">Bitte melde dich an, um die App zu benutzen.</p>
  <?php if (isset($_GET['registered'])): ?><div class="success">Registrierung erfolgreich! Du kannst dich jetzt anmelden.</div><?php endif; ?>
  <?php if (isset($_GET['loggedout'])): ?><div class="success">Du wurdest abgemeldet.</div><?php endif; ?>
  <?php if (isset($_GET['konto_error'])): ?><div class="error"><?= e((string) $_GET['konto_error']) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/login.php" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label for="username">Benutzername</label>
    <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" required autofocus>
    <label for="password">Passwort</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">Anmelden</button>
  </form>
  <?= konto_button($next) ?>
  <?php if (ALLOW_REGISTRATION): ?>
  <p class="alt">Noch kein Konto? <a href="/register.php">Jetzt registrieren</a></p>
  <?php endif; ?>
<?php
page_footer();
