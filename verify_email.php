<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['resend_email'] ?? '')));
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $emailKey = $email !== '' ? 'verify-email:' . hash('sha256', $email) : '';

    if (!throttle_allows('verify-email-ip:' . $ip, 10, 900)
        || ($emailKey !== '' && !throttle_allows($emailKey, 5, 900))) {
        flash('warn', 'Too many verification requests. Please wait a few minutes before trying again.');
    } else {
        if ($email !== '') {
            $result = supabase_resend_signup_confirmation($email);
            if (!$result['ok']) {
                error_log('Supabase confirmation resend failed: ' . ($result['error'] ?? 'Unknown error'));
            }
        }
        // Keep the response neutral so this form cannot be used to enumerate accounts.
        flash('info', 'If the address belongs to an unconfirmed account, we will send a confirmation e-mail.');
    }

    redirect('verify_email.php');
}

$pageTitle = 'Verify your e-mail';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
  <section class="auth-right" style="margin:auto">
    <div class="auth-card reveal" style="max-width:460px">
      <span class="eyebrow">E-mail verification</span>
      <h2 style="font-size:1.5rem">Verify your e-mail</h2>

      <p class="muted" style="font-size:.9rem;margin-bottom:1rem">
        Enter the e-mail you signed up with to request a new confirmation link. Following it will return you to Nerasky.
      </p>

      <form method="post" action="<?= e(base_url('verify_email.php')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="resend_email">Account e-mail</label>
          <input class="input" type="email" id="resend_email" name="resend_email" required
                 placeholder="you@student.ph" autocomplete="email">
        </div>
        <button class="btn btn-primary btn-block mt-2" type="submit">Resend confirmation e-mail →</button>
      </form>
      <p class="hint center mt-2">
        <a href="<?= e(base_url('login.php')) ?>" style="font-weight:700">← Back to log in</a>
      </p>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
