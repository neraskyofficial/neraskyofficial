<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$me = require_user();
$uid = (int) $me['id'];
$profileChecks = [
    !empty(trim((string) ($me['full_name'] ?? ''))),
    filter_var((string) ($me['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false,
    trim((string) ($me['bio'] ?? '')) !== '',
    !empty($me['date_of_birth']),
    !empty($me['avatar_file']),
];
$profileCompleted = count(array_filter($profileChecks));
$profilePercent = (int) round(($profileCompleted / count($profileChecks)) * 100);

$stats = [
    'buying'  => (int) scalar('SELECT COUNT(*) FROM orders WHERE buyer_id = ?', [$uid]),
    'selling' => (int) scalar('SELECT COUNT(*) FROM orders WHERE seller_id = ?', [$uid]),
    'open'    => (int) scalar("SELECT COUNT(*) FROM orders WHERE (buyer_id = ? OR seller_id = ?) AND status IN ('awaiting_payment','in_progress','delivered')", [$uid, $uid]),
    'unread'  => unread_chat_count($uid),
];

$recentOrders = rows(
    'SELECT o.*, s.title AS service_title, b.full_name AS buyer_name, u.full_name AS seller_name
     FROM orders o
     JOIN services s ON s.id = o.service_id
     JOIN users b    ON b.id = o.buyer_id
     JOIN users u    ON u.id = o.seller_id
     WHERE o.buyer_id = ? OR o.seller_id = ?
     ORDER BY o.id DESC LIMIT 6',
    [$uid, $uid]
);

$myServices = rows('SELECT * FROM services WHERE seller_id = ? ORDER BY id DESC', [$uid]);
$savedCount = (int) scalar('SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$uid]);
$onboarding = [
    ['Profile basics', $profilePercent >= 100, 'profile.php?id=' . $uid . '#profile-account'],
    ['Save a service', $savedCount > 0, 'browse.php'],
    ['Place your first order', $stats['buying'] > 0, 'browse.php'],
    ['Post a service', count($myServices) > 0, 'sell.php'],
];
$onboardingDone = count(array_filter($onboarding, static fn (array $item): bool => $item[1]));

/* attention items */
$attention = [];
if ($me['account_status'] === 'verified') {
    if ((int) scalar('SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = "in_progress"', [$uid]) > 0) {
        $attention[] = [ui_icon('zap', 18), 'You have orders in progress — deliver the work to keep your buyers happy.', 'orders.php'];
    }
    if ((int) scalar('SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = "delivered"', [$uid]) > 0) {
        $attention[] = [ui_icon('box', 18), 'Some of your orders were delivered. Review them to release the payout.', 'orders.php?mine=buyer&status=delivered'];
    }
    $pendingExternalPayments = (int) scalar(
        'SELECT COUNT(*) FROM payments WHERE payer_id = ? AND status = "pending"',
        [$uid]
    ) + (int) scalar('SELECT COUNT(*) FROM paymongo_checkouts WHERE user_id = ? AND status = "pending"', [$uid])
      + (int) scalar('SELECT COUNT(*) FROM cash_in_requests WHERE user_id = ? AND status = "pending"', [$uid]);
    if ($pendingExternalPayments > 0) {
        $attention[] = [ui_icon('clock', 18), 'You have a payment awaiting confirmation or admin review. Check your order or Wallet page for its status.', 'orders.php'];
    }
}
if ($me['role'] === 'admin') {
    $np = (int) scalar("SELECT COUNT(*) FROM payments WHERE status = 'pending'")
        + (int) scalar("SELECT COUNT(*) FROM paymongo_checkouts WHERE status = 'pending'");
    $nu = (int) scalar("SELECT COUNT(*) FROM users WHERE account_status = 'pending'");
    $ns = (int) scalar("SELECT COUNT(*) FROM services WHERE status = 'pending'");
    $nw = (int) scalar("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'");
    if ($nu + $np + $ns + $nw > 0) {
        $attention[] = [ui_icon('shield', 18), $nu . ' account(s), ' . $np . ' PayMongo checkout(s), ' . $ns . ' service(s), ' . $nw . ' withdrawal(s) need attention.', 'admin/index.php'];
    }
}

$pageTitle  = 'Dashboard';
$currentNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head" style="padding-bottom:.4rem">
  <div class="container flex-between">
    <div class="flex">
      <?= avatar_of($me, 56) ?>
      <div>
        <h1 style="font-size:1.7rem;margin-bottom:.1rem">Hey, <?= e(explode(' ', $me['full_name'])[0]) ?></h1>
        <p class="muted" style="margin:0"><?= e($me['email']) ?> · Joined <?= date('M Y', strtotime((string) $me['created_at'])) ?> <?= sb((string) $me['account_status']) ?></p>
      </div>
    </div>
    <?php if ($me['role'] === 'admin' && ADMIN_SITE_URL !== ''): ?><a class="btn btn-dark" href="<?= e(ADMIN_SITE_URL) ?>" target="_blank" rel="noopener"><?= ui_icon('shield') ?> Admin portal</a><?php endif; ?>
  </div>
</section>

<div class="container">
  <?php if ($me['account_status'] === 'pending'): ?>
    <div class="banner banner-warn mb-3" style="margin-top:1.4rem">
      <span class="b-ic"><?= ui_icon('clock') ?></span>
      <span><b>Your account is under manual review.</b> A moderator verifies every account — check back soon. You can browse the marketplace while you wait.</span>
    </div>
  <?php elseif ($me['account_status'] === 'rejected'): ?>
    <div class="banner banner-bad mb-3" style="margin-top:1.4rem">
      <span class="b-ic">✕</span>
      <span><b>Your account was not approved.</b> If you believe this is a mistake, contact an admin.</span>
    </div>
  <?php endif; ?>

  <?php if (!$me || $me['role'] !== 'admin'): ?>
    <div class="banner banner-info mb-3" style="margin-top:1.4rem">
      <span class="b-ic">★</span>
      <span><b>Percentages that work for you.</b> Free sellers keep <?= 100 - (int) PLATFORM_FEE ?>% · Pro members keep <?= 100 - (int) PREMIUM_FEE ?>% and get boosted listings. <a href="<?= e(base_url('premium.php')) ?>" style="font-weight:700">See Nerasky Pro →</a></span>
    </div>
  <?php endif; ?>

  <?php if ($attention): ?>
    <div class="stack gap-2" style="margin:1.4rem 0">
      <?php foreach ($attention as $a): ?>
        <div class="banner banner-info"><span class="b-ic"><?= $a[0] ?></span><span><?= e($a[1]) ?> <a href="<?= e(base_url($a[2])) ?>" style="font-weight:700">Open →</a></span></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($profilePercent < 100): ?>
    <div class="card card-pad mb-3" style="margin-top:1.4rem">
      <div class="flex-between" style="gap:1rem;align-items:center">
        <div>
          <b><?= $profilePercent ?>% profile complete</b>
          <p class="muted" style="font-size:var(--fs-sm);margin:.2rem 0 0">Add a bio, photo and basic details so classmates know who they’re working with.</p>
        </div>
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('profile.php?id=' . $uid . '#profile-account')) ?>">Complete profile →</a>
      </div>
      <div class="seller-progress progress-fill-gradient" role="progressbar" aria-label="Profile completion" aria-valuenow="<?= $profilePercent ?>" aria-valuemin="0" aria-valuemax="100" style="margin-top:.8rem">
        <span style="width:<?= $profilePercent ?>%;min-width:<?= $profilePercent > 0 ? '6px' : '0' ?>"></span>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="container" style="padding-bottom:4rem">
  <div class="grid-4" style="margin-bottom:1.4rem">
    <div class="stat-card"><div class="st-ic"><?= ui_icon('grid', 20) ?></div><div><b><?= $stats['buying'] ?></b><small>Orders as buyer</small></div></div>
    <div class="stat-card"><div class="st-ic"><?= ui_icon('users', 20) ?></div><div><b><?= $stats['selling'] ?></b><small>Orders as seller</small></div></div>
    <div class="stat-card"><div class="st-ic"><?= ui_icon('zap', 20) ?></div><div><b><?= $stats['open'] ?></b><small>Active orders</small></div></div>
    <div class="stat-card"><div class="st-ic"><?= ui_icon('wallet', 20) ?></div><div><b><?= price((float) $me['wallet']) ?></b><small>Wallet balance</small></div></div>
  </div>

  <div class="dash-grid">
    <nav class="dash-side">
      <a class="dash-link active" href="<?= e(base_url('dashboard.php')) ?>"><span class="d-ic"><?= ui_icon('home', 17) ?></span>Overview</a>
      <a class="dash-link" href="<?= e(base_url('orders.php')) ?>"><span class="d-ic"><?= ui_icon('box', 17) ?></span>My orders</a>
      <a class="dash-link" href="<?= e(base_url('sell.php')) ?>"><span class="d-ic"><?= ui_icon('plus', 17) ?></span>Post a service</a>
      <a class="dash-link" href="<?= e(base_url('orders.php?mine=seller')) ?>"><span class="d-ic"><?= ui_icon('users', 17) ?></span>I'm a seller</a>
      <a class="dash-link" href="<?= e(base_url('wallet.php')) ?>"><span class="d-ic"><?= ui_icon('wallet', 17) ?></span>Wallet</a>
      <a class="dash-link <?= $currentNav === 'saved' ? 'active' : '' ?>" href="<?= e(base_url('saved.php')) ?>"><span class="d-ic"><?= ui_icon('star', 17) ?></span>Saved services<?php if ($savedCount > 0): ?> <small>(<?= $savedCount ?>)</small><?php endif; ?></a>
      <a class="dash-link <?= $currentNav === 'premium' ? 'active' : '' ?>" href="<?= e(base_url('premium.php')) ?>"><span class="d-ic"><?= ui_icon('sparkle', 17) ?></span><?= is_premium($me) ? 'Manage Pro' : 'Go Pro' ?></a>
      <a class="dash-link" href="<?= e(base_url('profile.php?id=' . $uid)) ?>"><span class="d-ic"><?= ui_icon('users', 17) ?></span>My profile</a>
      <?php if ($me['role'] === 'admin' && ADMIN_SITE_URL !== ''): ?><a class="dash-link" href="<?= e(ADMIN_SITE_URL) ?>" target="_blank" rel="noopener"><span class="d-ic"><?= ui_icon('shield', 17) ?></span>Admin portal</a><?php endif; ?>
    </nav>

    <div class="dash-main">
      <?php if ($onboardingDone < count($onboarding)): ?>
        <div class="panel mb-3">
          <div class="panel-head"><h3>Get started</h3><span class="muted" style="font-size:var(--fs-sm)"><?= $onboardingDone ?>/<?= count($onboarding) ?> complete</span></div>
          <p class="muted" style="font-size:var(--fs-sm)">A few quick steps to get the most from Nerasky.</p>
          <div class="stack gap-1">
          <?php foreach ($onboarding as $step): ?>
            <a class="flex-between" href="<?= e(base_url($step[2])) ?>" style="padding:.55rem .7rem;border:1px solid var(--line);border-radius:var(--r-sm);color:inherit">
              <span><?= $step[1] ? '✓' : '○' ?> <?= e($step[0]) ?></span><span class="muted"><?= $step[1] ? 'Done' : 'Open →' ?></span>
            </a>
          <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
<!-- recent orders -->
      <div class="panel">
        <div class="panel-head">
          <h3>Recent orders</h3>
          <a class="btn btn-ghost btn-sm" href="<?= e(base_url('orders.php')) ?>">View all →</a>
        </div>
        <?php if (count($recentOrders) > 0): ?>
          <?php foreach ($recentOrders as $o): $isB = (int) $o['buyer_id'] === $uid; ?>
            <a class="order-row" href="<?= e(base_url('order.php?order=' . (int) $o['id'])) ?>">
              <span class="st-ic" style="width:40px;height:40px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;font-size:1.05rem"><?= ui_icon('file') ?></span>
              <div class="or-main">
                <b><?= e($o['service_title']) ?></b>
                <small><?= $isB ? 'Bought from ' . e($o['seller_name']) : 'Sold to ' . e($o['buyer_name']) ?> · <?= time_ago((string) $o['created_at']) ?></small>
              </div>
              <span class="or-price"><?= money((float) $o['price']) ?></span>
              <?= sb((string) $o['status']) ?>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <div class="es-ic"><?= ui_icon('grid') ?></div>
            <b>No orders yet</b>
            <p class="muted">Browse the marketplace and place your first order — or post a service and start earning.</p>
            <a class="btn btn-primary" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- my services -->
      <div class="panel">
        <div class="panel-head">
          <h3>My services</h3>
          <a class="btn btn-primary btn-sm" href="<?= e(base_url('sell.php')) ?>">+ New</a>
        </div>
        <?php if (count($myServices) > 0): ?>
          <?php foreach ($myServices as $sv): ?>
            <a class="order-row" href="<?= e(base_url('service-edit.php?id=' . (int) $sv['id'])) ?>">
              <span class="st-ic" style="width:40px;height:40px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;font-size:1.05rem"><?= cat_icon((string) $sv['category']) ?></span>
              <div class="or-main">
                <b><?= e($sv['title']) ?></b>
                <small><?= e($sv['category']) ?> · <?= price((float) $sv['price']) ?> · <?= (int) $sv['delivery_days'] ?> day<?= (int) $sv['delivery_days'] > 1 ? 's' : '' ?></small>
              </div>
              <?= sb((string) $sv['status']) ?>
            </a>
          <?php endforeach; ?>
        <?php elseif ($me['account_status'] === 'verified'): ?>
          <div class="empty-state">
            <div class="es-ic"><?= ui_icon('grid') ?></div>
            <b>You haven't posted anything yet</b>
            <p class="muted">Post your first service — it goes live once a moderator approves it.</p>
            <a class="btn btn-primary" href="<?= e(base_url('sell.php')) ?>">Post a service</a>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="es-ic"><?= ui_icon('lock') ?></div>
            <b>Waiting for verification</b>
            <p class="muted">Selling unlocks once your account is verified (and as a minor, after parental consent is approved).</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>