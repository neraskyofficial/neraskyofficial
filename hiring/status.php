<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$reference = strtoupper(trim((string) ($_POST['reference'] ?? $_GET['reference'] ?? '')));
$application = null;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    verify_csrf();
    if ($token === '' || $reference === '') {
        $error = 'Enter your tracking reference and private token.';
    } else {
        $changed = q("UPDATE admin_applications SET withdrawn_at = NOW(), status = 'withdrawn' WHERE tracking_reference = ? AND tracking_token_hash = ? AND status NOT IN ('approved', 'rejected', 'withdrawn') AND withdrawn_at IS NULL", [$reference, hash('sha256', $token)])->rowCount();
        $message = $changed ? 'Your application has been withdrawn.' : 'This application cannot be withdrawn because it was not found or is already final.';
    }
}

if ($token !== '' && $reference !== '') {
    $application = row('SELECT tracking_reference, status, requested_role, created_at, interview_at, interview_location, withdrawn_at FROM admin_applications WHERE tracking_reference = ? AND tracking_token_hash = ?', [$reference, hash('sha256', $token)]);
    if (!$application && $error === '') {
        $error = 'We could not verify those tracking details.';
    }
}

$labels = ['submitted' => 'Submitted', 'pending' => 'Submitted', 'reviewing' => 'Reviewing', 'interview' => 'Interview', 'approved' => 'Approved', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'];
$pageTitle = 'Applicant status';
require __DIR__ . '/header.php';
?>
<section class="section-tight">
  <div class="container" style="max-width:720px">
    <div class="panel card-pad">
      <span class="eyebrow">Private applicant portal</span>
      <h1>Check your application status</h1>
      <p class="muted">Use the reference and private token shown after submission. No public applicant records are exposed.</p>
      <?php if ($error): ?><div class="banner banner-bad"><span class="b-ic">✕</span><span><?= e($error) ?></span></div><?php endif; ?>
      <?php if ($message): ?><div class="banner banner-good"><span class="b-ic">✓</span><span><?= e($message) ?></span></div><?php endif; ?>
      <?php if (!$application): ?>
        <form method="get">
          <div class="field"><label for="reference">Tracking reference</label><input class="input" id="reference" name="reference" required value="<?= e($reference) ?>" placeholder="NRK-XXXXXXXXXX"></div>
          <div class="field"><label for="token">Private token</label><input class="input" id="token" name="token" required value="<?= e($token) ?>" autocomplete="off"></div>
          <button class="btn btn-primary" type="submit">Check status</button>
        </form>
      <?php else: ?>
        <div class="panel" style="margin-top:1rem;padding:1rem;background:var(--surface-2)">
          <p><b>Reference:</b> <?= e($application['tracking_reference']) ?></p>
          <p><b>Role:</b> <?= e(ucwords(str_replace('_', ' ', (string) $application['requested_role']))) ?></p>
          <p><b>Status:</b> <span class="pill pill-<?= $application['status'] === 'approved' ? 'ok' : ($application['status'] === 'rejected' ? 'bad' : 'muted') ?>"><?= e($labels[$application['status']] ?? ucfirst((string) $application['status'])) ?></span></p>
          <?php if (!empty($application['interview_at'])): ?><p><b>Interview:</b> <?= e((string) $application['interview_at']) ?><?= $application['interview_location'] ? ' · ' . e((string) $application['interview_location']) : '' ?></p><?php endif; ?>
        </div>
        <?php if (!in_array($application['status'], ['approved', 'rejected', 'withdrawn'], true) && empty($application['withdrawn_at'])): ?>
          <form method="post" style="margin-top:1rem" onsubmit="return confirm('Withdraw this application?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="reference" value="<?= e($reference) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
            <button class="btn btn-danger" type="submit">Withdraw application</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
