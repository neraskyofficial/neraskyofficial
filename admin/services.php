<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
require_admin_area($me, 'services');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action    = (string) ($_POST['action'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);

    $sv = $serviceId > 0 ? row('SELECT * FROM services WHERE id = ?', [$serviceId]) : null;
    if (!$sv) {
        flash('bad', 'Service not found.');
        redirect('services.php');
    }

    if ($action === 'approve') {
        q("UPDATE services SET status = 'active' WHERE id = ?", [$serviceId]);
        audit_log($me['id'], 'approve', 'service', $serviceId, ['seller_id' => $sv['seller_id'], 'title' => $sv['title'], 'category' => $sv['category']]);
        notify((int) $sv['seller_id'], 'order', 'Your service "' . mb_strimwidth((string) $sv['title'], 0, 40, '…') . '" is now live on the marketplace!', 'dashboard.php');
        flash('ok', 'Service approved and now live on the marketplace.');
    } elseif ($action === 'reject') {
        q("UPDATE services SET status = 'rejected' WHERE id = ?", [$serviceId]);
        audit_log($me['id'], 'reject', 'service', $serviceId, ['seller_id' => $sv['seller_id'], 'title' => $sv['title'], 'category' => $sv['category']]);
        notify((int) $sv['seller_id'], 'moderation', 'Your service "' . mb_strimwidth((string) $sv['title'], 0, 40, '…') . '" was not approved.', 'dashboard.php');
        flash('info', 'Service rejected — the seller will see it as not approved.');
    }
    redirect('services.php');
}

$tab   = in_array($_GET['tab'] ?? '', ['pending', 'active', 'rejected'], true) ? (string) $_GET['tab'] : 'pending';
$where = match ($tab) {
    'active'   => "status = 'active'",
    'rejected' => "status = 'rejected'",
    default    => "status = 'pending'",
};
$services = rows(
    'SELECT s.*, u.full_name, u.account_status, u.is_minor, u.consent_status, u.profile_frame, u.profile_accent
     FROM services s JOIN users u ON u.id = s.seller_id
     WHERE s.' . $where . ' ORDER BY s.id DESC LIMIT 60'
);

$pageTitle = 'Review services';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <div class="flex-between">
      <div>
        <span class="eyebrow">Moderation</span>
        <h1>Service listings</h1>
        <p class="muted">Approve quality, keep the market safe.</p>
      </div>
      <div class="seg">
        <a href="<?= e(base_url('services.php?tab=pending')) ?>" class="<?= $tab === 'pending' ? 'on' : '' ?>">Pending</a>
        <a href="<?= e(base_url('services.php?tab=active')) ?>" class="<?= $tab === 'active' ? 'on' : '' ?>">Active</a>
        <a href="<?= e(base_url('services.php?tab=rejected')) ?>" class="<?= $tab === 'rejected' ? 'on' : '' ?>">Rejected</a>
      </div>
    </div>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <div class="dash-grid" style="grid-template-columns:220px 1fr;align-items:start">
      <?php echo admin_subnav('services'); ?>

      <div class="dash-main">
        <?php if (count($services) > 0): ?>
          <?php foreach ($services as $sv): $rating = seller_rating((int) $sv['seller_id']); ?>
            <div class="card card-pad" style="margin-bottom:1.1rem">
              <div class="flex-between mb-2">
                <div class="flex gap-2">
                  <span class="sv-cat" style="font-size:var(--fs-xs);font-weight:700;color:var(--brand-600);background:rgb(99 102 241/.08);padding:.3rem .7rem;border-radius:var(--r-pill)"><?= cat_icon((string) $sv['category']) ?> <?= e($sv['category']) ?></span>
                  <?= sb((string) $sv['status']) ?>
                </div>
                <span class="faint" style="font-size:var(--fs-xs)"><?= time_ago((string) $sv['created_at']) ?></span>
              </div>

              <h2 style="font-size:1.15rem"><?= e($sv['title']) ?></h2>
              <p class="muted" style="font-size:var(--fs-sm);white-space:pre-wrap"><?= e(mb_strimwidth($sv['description'], 0, 300, '…')) ?></p>

              <div class="flex-between" style="flex-wrap:wrap;gap:.8rem">
                <div class="flex">
                  <?= avatar_of($sv, 32) ?>
                  <div>
                    <b style="font-size:var(--fs-sm)"><?= e($sv['full_name']) ?></b>
                    <div class="muted" style="font-size:var(--fs-xs)">
                      <span class="pill <?= $sv['account_status'] === 'verified' ? 'pill-ok' : 'pill-warn' ?>" style="font-size:.65rem"><?= $sv['account_status'] ?></span>
                      <?php if ($sv['is_minor']): ?><span class="pill pill-warn" style="font-size:.65rem">Minor</span><?php endif; ?>
                      <?php if ($rating !== null): ?><?= star_row($rating) ?> <?= number_format($rating, 1) ?><?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="flex gap-2">
                  <span style="font-weight:800;font-size:1.05rem"><?= price((float) $sv['price']) ?></span>
                  <span class="muted" style="font-size:var(--fs-xs)"><?= ui_icon('box') ?> <?= (int) $sv['delivery_days'] ?>d</span>
                </div>
              </div>

              <?php if ($sv['status'] === 'pending'): ?>
                <div class="admin-verdict" style="border-top:1px solid var(--line);padding-top:.9rem;margin-top:.9rem">
                  <form method="post" action="<?= e(base_url('services.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="service_id" value="<?= (int) $sv['id'] ?>">
                    <button class="btn btn-primary" type="submit">✓ Approve listing</button>
                  </form>
                  <form method="post" action="<?= e(base_url('services.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="service_id" value="<?= (int) $sv['id'] ?>">
                    <button class="btn btn-danger" type="submit" data-confirm="Reject this listing?">✕ Reject</button>
                  </form>
                  <a class="btn btn-ghost" href="<?= e(market_url('service.php?id=' . (int) $sv['id'])) ?>" target="_blank">Preview ↗</a>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="card empty-state">
            <div class="es-ic"><?= ui_icon('sparkle') ?></div>
            <b>All clear here</b>
            <p class="muted"><?= $tab === 'pending' ? 'No services waiting for review.' : 'Nothing in this bucket right now.' ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
?>