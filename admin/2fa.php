<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
$existing = row('SELECT * FROM admin_2fa WHERE user_id = ?', [(int) $me['id']]);
$secret = $existing['secret'] ?? admin_base32_secret();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!admin_totp_valid($secret, (string) ($_POST['code'] ?? ''))) {
        $error = 'That code is invalid or expired. Check your authenticator app and try again.';
    } else {
        q(
            'INSERT INTO admin_2fa (user_id, secret, enabled, enabled_at) VALUES (?,?,1,NOW())
             ON DUPLICATE KEY UPDATE secret = VALUES(secret), enabled = 1, enabled_at = NOW()',
            [$me['id'], $secret]
        );
        audit_log((int) $me['id'], 'enable_2fa', 'admin', (int) $me['id']);
        flash('ok', 'Two-factor authentication is now enabled for your account.');
        redirect('index.php');
    }
}

$label = rawurlencode('Nerasky:' . (string) $me['email']);
$issuer = rawurlencode('Nerasky Admin');
$otpauth = 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . $issuer;
$pageTitle = 'Set up two-factor authentication';
require __DIR__ . '/header.php';
?>
<section class="section-tight"><div class="container"><div class="admin-card">
  <span class="eyebrow">Security</span><h1 style="font-size:1.6rem">Two-factor authentication</h1>
  <p class="muted">Add this account to an authenticator app, then enter the 6-digit code to enable protection.</p>
  <?php if ($error): ?><div class="banner banner-bad mb-2"><span class="b-ic">✕</span><span><?= e($error) ?></span></div><?php endif; ?>
  <div class="card card-pad" style="word-break:break-all"><b>Manual setup key</b><p style="font-family:monospace;margin:.5rem 0"><?= e($secret) ?></p><small class="muted">Optional otpauth URI:</small><p style="font-size:var(--fs-xs);margin:.35rem 0"><?= e($otpauth) ?></p></div>
  <form method="post" style="margin-top:1rem">
    <?= csrf_field() ?><div class="field"><label for="code">Authenticator code</label><input class="input" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></div>
    <button class="btn btn-primary" type="submit">Enable 2FA</button>
  </form>
</div></div></section>
<?php require __DIR__ . '/footer.php'; ?>
