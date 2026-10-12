<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$message = '';
$resetLink = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid admin email address.';
    } else {
        $user = row("SELECT id FROM users WHERE email = ? AND role = 'admin'", [$email]);
        $message = 'If that address belongs to an admin, a reset link has been created.';
        if ($user) {
            q('UPDATE admin_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
            $token = bin2hex(random_bytes(32));
            insert('INSERT INTO admin_password_resets (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))', [
                (int) $user['id'],
                hash('sha256', $token),
            ]);
            $resetLink = base_url('password-reset.php?token=' . rawurlencode($token));
            error_log('Nerasky admin password reset link: ' . $resetLink);
        }
    }
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token !== '') {
    $reset = row(
        'SELECT r.id, r.user_id, u.email FROM admin_password_resets r JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.role = "admin"',
        [hash('sha256', $token)]
    );
    if (!$reset) {
        $error = 'This reset link is invalid or expired.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'reset') {
        verify_csrf();
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $error = 'Use at least 12 characters with letters and numbers.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'The passwords do not match.';
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
            q('UPDATE admin_password_resets SET used_at = NOW() WHERE id = ?', [(int) $reset['id']]);
            flash('ok', 'Password updated. You can now sign in.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Password recovery';
require __DIR__ . '/header.php';
?>
<div class="admin-guard">
  <div class="admin-card">
    <h2><?= $token !== '' ? 'Set a new password' : 'Recover admin access' ?></h2>
    <?php if ($error): ?><div class="banner banner-bad mb-2"><span class="b-ic">!</span><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="banner banner-ok mb-2"><span class="b-ic">✓</span><?= e($message) ?></div><?php endif; ?>
    <?php if ($token !== '' && isset($reset) && $reset): ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="reset">
        <div class="field"><label for="password">New password</label><input class="input" id="password" name="password" type="password" minlength="12" required autocomplete="new-password"></div>
        <div class="field"><label for="password_confirm">Confirm password</label><input class="input" id="password_confirm" name="password_confirm" type="password" minlength="12" required autocomplete="new-password"></div>
        <button class="btn btn-primary btn-block" type="submit">Update password</button>
      </form>
    <?php elseif ($token === ''): ?>
      <p class="muted">Enter your admin email. The one-time link is valid for 30 minutes.</p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="email">Admin email</label><input class="input" id="email" name="email" type="email" required autocomplete="email"></div>
        <button class="btn btn-primary btn-block" type="submit">Create reset link</button>
      </form>
      <?php if ($resetLink): ?><p class="hint mt-2">Local development link: <a href="<?= e($resetLink) ?>">Continue password reset</a></p><?php endif; ?>
    <?php endif; ?>
    <p class="center mt-2"><a href="<?= e(base_url('login.php')) ?>">Back to admin login</a></p>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
