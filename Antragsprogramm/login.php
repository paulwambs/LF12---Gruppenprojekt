<?php
require_once __DIR__ . '/db.php';

// Bereits angemeldet? -> zur Übersicht
if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'role'         => $user['role'],
            'abteilung_id' => $user['abteilung_id'] !== null ? (int)$user['abteilung_id'] : null,
        ];
        redirect('index.php');
    }
    $error = 'Benutzername oder Passwort ist falsch.';
}

$u = null;
require __DIR__ . '/_header.php';
?>
<div class="card" style="max-width:360px;margin:40px auto;">
  <h2>Anmeldung</h2>
  <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <?= csrf_field() ?>
    <label>Benutzername</label>
    <input type="text" name="username" autofocus value="<?= h($_POST['username'] ?? '') ?>">
    <label>Passwort</label>
    <input type="password" name="password">
    <button class="btn" type="submit">Anmelden</button>
  </form>
  <p class="hint">Noch keine Benutzer? Zuerst <a href="setup.php">setup.php</a> ausführen.<br>
  Demo-Passwort: <code>test1234</code></p>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
