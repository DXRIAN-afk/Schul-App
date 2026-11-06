<?php
require_once __DIR__ . '/lib/konto.php';
require_once __DIR__ . '/lib/layout.php';

start_session();
if (current_user()) {
    header('Location: /');
    exit;
}

$error = null;
$username = '';
if (ALLOW_REGISTRATION && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    if (!check_csrf()) {
        $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
    } else {
        $error = register_user($username, (string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
        if ($error === null) {
            header('Location: /login.php?registered=1');
            exit;
        }
    }
}

page_header('Registrieren');
?>
  <h1>Registrieren</h1>
<?php if (!ALLOW_REGISTRATION): ?>
  <p class="sub">Registrierungen sind zurzeit deaktiviert.</p>
  <a class="btn" href="/login.php">Zur Anmeldung</a>
<?php else: ?>
  <p class="sub">Erstelle ein Konto für den SchulOrganizer.</p>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/register.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label for="username">Benutzername</label>
    <input type="text" id="username" name="username" value="<?= e($username) ?>" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]{3,30}" autocomplete="username" autocapitalize="none" required autofocus>
    <label for="password">Passwort (mind. 6 Zeichen)</label>
    <input type="password" id="password" name="password" minlength="6" autocomplete="new-password" required>
    <label for="password2">Passwort wiederholen</label>
    <input type="password" id="password2" name="password2" minlength="6" autocomplete="new-password" required>
    <button type="submit">Konto erstellen</button>
  </form>
  <?= konto_button('/') ?>
  <p class="alt">Schon ein Konto? <a href="/login.php">Anmelden</a></p>
<?php endif; ?>
<?php
page_footer();
