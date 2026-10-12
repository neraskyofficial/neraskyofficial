<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (admin_user()) {
    redirect('index.php');
}

$error = '';
$pendingTwoFactor = admin_2fa_pending_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($pendingTwoFactor) {
        $secret = admin_2fa_secret((int) $pendingTwoFactor['id']);
        $code = (string) ($_POST['code'] ?? '');
        if ($secret && (admin_totp_valid($secret, $code) || admin_recovery_valid((int) $pendingTwoFactor['id'], $code))) {
            $_SESSION['admin_uid'] = (int) $pendingTwoFactor['id'];
            unset($_SESSION['admin_2fa_pending']);
            flash('ok', 'Welcome back.');
            redirect('index.php');
        }
        $error = 'That verification code is invalid or expired.';
    } elseif (admin_attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        if (admin_2fa_pending_user()) {
            $pendingTwoFactor = admin_2fa_pending_user();
        } else {
            flash('ok', 'Welcome back.');
            redirect('index.php');
        }
    }
    if (!$pendingTwoFactor && !$error) {
        $error = 'Incorrect credentials, or this account is not an admin.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/header.php';
?>
<div class="admin-guard">
  <div class="admin-card">
    <div class="flex gap-2 mb-2" style="gap:.7rem">
      <?= logo_svg(40) ?>
      <div>
        <h2 style="font-size:1.4rem;margin:0">Nerasky Admin</h2>
        <p class="muted" style="font-size:var(--fs-xs);margin:.2rem 0 0">Isolated moderation portal · separate session</p>
      </div>
    </div>

    <?php if ($error): ?><div class="banner banner-bad mb-2"><span class="b-ic">✕</span><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" action="<?= e(base_url('login.php')) ?>">
      <?= csrf_field() ?>
      <?php if ($pendingTwoFactor): ?>
      <p class="muted">Enter the 6-digit code from your authenticator app, or use one saved recovery code.</p>
      <div class="field">
        <label for="code">Verification code</label>
        <input class="input" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" id="code" name="code" required autocomplete="one-time-code">
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Verify and enter</button>
      <?php else: ?>
      <div class="field">
        <label for="email">Admin email</label>
        <input class="input" type="email" id="email" name="email" required placeholder="admin@nerasky.ph" autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Enter admin portal</button>
      <?php endif; ?>
    </form>

    <p class="hint center mt-2" style="font-size:.76rem">Admin access is provisioned by the Nerasky owner. Never share your password.</p>
    <?php if (!$pendingTwoFactor): ?><p class="center mt-1"><a href="<?= e(base_url('password-reset.php')) ?>">Forgot your password?</a></p><?php endif; ?>
    <p class="hint center" style="font-size:.72rem;color:var(--faint)">Private moderation area · sign in with an account provisioned by the Nerasky owner.</p>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>