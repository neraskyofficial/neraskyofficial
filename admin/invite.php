<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$invite = $token !== ''
    ? row('SELECT i.*, u.full_name, u.email FROM admin_invites i JOIN users u ON u.id = i.user_id WHERE i.token_hash = ? AND i.used_at IS NULL AND i.expires_at > NOW()', [hash('sha256', $token)])
    : null;
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if (!$invite) {
        $error = 'This invitation is invalid, expired, or already used.';
    } elseif (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Use at least 12 characters with at least one letter and one number.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'The passwords do not match.';
    } else {
        $db = db();
        $db->beginTransaction();
        try {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $invite['user_id']]);
            q('UPDATE admin_invites SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$invite['id']]);
            audit_log((int) $invite['user_id'], 'activate_invite', 'admin_invite', (int) $invite['id'], ['user_id' => $invite['user_id']]);
            $db->commit();
            $success = true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

$pageTitle = 'Set up worker access';
require __DIR__ . '/header.php';
?>
<div class="admin-guard">
  <div class="admin-card">
    <div class="flex gap-2 mb-2" style="gap:.7rem"><?= logo_svg(40) ?><div><h2 style="font-size:1.4rem;margin:0">Set up worker access</h2><p class="muted" style="font-size:var(--fs-xs);margin:.2rem 0 0">One-time Nerasky invitation</p></div></div>
    <?php if ($success): ?>
      <div class="banner banner-ok mb-2"><span class="b-ic">✓</span><span>Your password is set. You can now sign in to the admin portal.</span></div>
      <a class="btn btn-primary btn-block" href="<?= e(base_url('login.php')) ?>">Continue to admin login</a>
    <?php elseif ($invite): ?>
      <?php if ($error): ?><div class="banner banner-bad mb-2"><span class="b-ic">✕</span><span><?= e($error) ?></span></div><?php endif; ?>
      <p class="muted">Set a private password for <b><?= e($invite['email']) ?></b>. This link expires after 48 hours and can only be used once.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label for="password">New password</label><input class="input" type="password" id="password" name="password" required minlength="12" autocomplete="new-password"></div>
        <div class="field"><label for="password_confirm">Confirm password</label><input class="input" type="password" id="password_confirm" name="password_confirm" required minlength="12" autocomplete="new-password"></div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Create secure password</button>
      </form>
    <?php else: ?>
      <div class="banner banner-bad"><span class="b-ic">✕</span><span>This invitation is invalid, expired, or already used.</span></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
