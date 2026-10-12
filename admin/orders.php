<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
require_admin_area($me, 'orders');

$status = $_GET['status'] ?? '';
$orders = rows(
    'SELECT o.*, s.title AS service_title, b.full_name AS buyer_name, u.full_name AS seller_name, b.profile_frame AS buyer_frame, b.profile_accent AS buyer_accent, u.profile_frame AS seller_frame, u.profile_accent AS seller_accent,
            (SELECT p.status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS payment_status,
            (SELECT wt.id FROM wallet_transactions wt WHERE wt.order_id = o.id AND wt.type = "wallet_payment" AND wt.direction = "debit" LIMIT 1) AS wallet_payment_id
     FROM orders o
     JOIN services s ON s.id = o.service_id
     JOIN users b    ON b.id = o.buyer_id
     JOIN users u    ON u.id = o.seller_id
     ' . ($status !== '' ? 'WHERE o.status = ?' : '') . '
     ORDER BY o.id DESC LIMIT 200',
    $status !== '' ? [$status] : []
);

$statusTabs = [
    ''                  => 'All',
    'awaiting_payment'  => 'Awaiting payment',
    'in_progress'       => 'In progress',
    'delivered'         => 'Delivered',
    'completed'         => 'Completed',
    'cancelled'         => 'Cancelled',
];

$pageTitle = 'All orders';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Moderation</span>
    <h1>All orders</h1>
    <p class="muted">A transparent view of every transaction on the platform.</p>
    <div class="status-tabs mt-3">
      <?php foreach ($statusTabs as $val => $label): ?>
        <a class="chip <?= $status === $val ? 'on' : '' ?>" href="<?= e(base_url('orders.php' . ($val !== '' ? '?status=' . $val : ''))) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <div class="dash-grid" style="grid-template-columns:220px 1fr;align-items:start">
      <?php echo admin_subnav('orders'); ?>

      <div class="dash-main">
        <?php if (count($orders) > 0): ?>
          <div class="table-wrap">
            <table class="tbl admin-orders-table">
              <thead><tr><th>Order</th><th>Service</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Payout</th><th>Status</th><th>Payment</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <tr>
                    <td data-label="Order"><div class="row-title" style="display:flex;align-items:center;gap:.55rem"><?= avatar_of(['full_name' => $o['buyer_name'], 'profile_frame' => $o['buyer_frame'] ?? 'none', 'profile_accent' => $o['buyer_accent'] ?? 'indigo'], 28) ?><b>#<?= (int) $o['id'] ?></b></div></td>
                    <td data-label="Service"><div class="row-title"><b><?= e(mb_strimwidth($o['service_title'], 0, 34, '…')) ?></b></div></td>
                    <td data-label="Buyer" class="muted"><?= e($o['buyer_name']) ?></td>
                    <td data-label="Seller" class="muted"><?= e($o['seller_name']) ?></td>
                    <td data-label="Amount"><b><?= money((float) $o['price']) ?></b></td>
                    <td data-label="Payout" class="muted" style="font-size:var(--fs-xs)"><?= money((float) $o['seller_payout']) ?></td>
                    <td data-label="Status"><?= sb((string) $o['status']) ?></td>
                    <td data-label="Payment">
                      <?php if ($o['wallet_payment_id']): ?><span class="admin-payment-state ok">Verified · wallet</span>
                      <?php elseif ($o['payment_status'] === 'verified'): ?><span class="admin-payment-state ok">Verified</span>
                      <?php elseif ($o['payment_status'] === 'pending'): ?><span class="admin-payment-state pending">Under review</span>
                      <?php elseif ($o['payment_status'] === 'rejected'): ?><span class="admin-payment-state bad">Rejected</span>
                      <?php else: ?><span class="admin-payment-state">Not submitted</span><?php endif; ?>
                    </td>
                    <td data-label="Action"><a class="btn btn-ghost btn-sm" href="<?= e(market_url('order.php?order=' . (int) $o['id'])) ?>" target="_blank">View order ↗</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="card empty-state"><div class="es-ic"><?= ui_icon('grid') ?></div><b>No orders</b><p class="muted">Nothing in this view right now.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>