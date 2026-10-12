<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$me = require_admin();
$uid = (int) $me['id'];
$row = row('SELECT * FROM admin_2fa WHERE user_id = ?', [$uid]);
if (!$row) {
    $secret = admin_base32_secret();
    insert('INSERT INTO admin_2fa (user_id,secret) VALUES (?,?)', [$uid, $secret]);
    $row = ['secret' => $secret, 'enabled' => 0];
}
$error = '';
$successCodes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (admin_totp_valid((string) $row['secret'], (string) ($_POST['code'] ?? ''))) {
        $plainCodes = [];
        $hashedCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $plainCodes[] = strtoupper(bin2hex(random_bytes(4)));
            $hashedCodes[] = password_hash($plainCodes[$i], PASSWORD_DEFAULT);
        }
        q('UPDATE admin_2fa SET enabled=1, recovery_codes=?, enabled_at=NOW() WHERE user_id=?', [json_encode($hashedCodes), $uid]);
        $successCodes = $plainCodes;
    }
    $error = 'Enter the current 6-digit code from your authenticator app.';
}
$pageTitle = 'Set up two-factor authentication';
require __DIR__ . '/header.php';
?>
<section class="section"><div class="container" style="max-width:620px"><div class="card card-pad"><span class="eyebrow">Account security</span><h1>Set up two-factor authentication</h1><p class="muted">Add this secret to an authenticator app, then confirm one code.</p><?php if ($successCodes !== []): ?><div class="banner banner-good"><b>2FA is enabled.</b><br>Save these recovery codes somewhere private. Each works once:<br><code><?= e(implode(' · ', $successCodes)) ?></code></div><?php endif; ?><div class="banner banner-info"><b>Secret:</b> <?= e((string) $row['secret']) ?></div><?php if ($error): ?><div class="banner banner-bad"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="field"><label for="code">6-digit code</label><input class="input" id="code" name="code" inputmode="numeric" maxlength="6" required></div><button class="btn btn-primary" type="submit">Enable 2FA</button></form></div></div></section>
<?php require __DIR__ . '/footer.php'; ?>
