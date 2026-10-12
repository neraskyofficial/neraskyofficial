<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
require_admin_area($me, 'payments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action  = (string) ($_POST['action'] ?? '');
    $cashInId = (int) ($_POST['cash_in_id'] ?? 0);
    $orderId = (int) ($_POST['order_id'] ?? 0);

    if (($action === 'approve_cash_in' || $action === 'reject_cash_in') && $cashInId > 0) {
        require_admin_area($me, 'cash_ins');
        $db = db();
        $db->beginTransaction();
        try {
            $locked = row('SELECT * FROM cash_in_requests WHERE id = ? FOR UPDATE', [$cashInId]);
            if (!$locked || $locked['status'] !== 'pending') {
                throw new RuntimeException('That cash-in request is no longer pending.');
            }
            if ($action === 'approve_cash_in') {
                if ($locked['provider'] !== 'GCash') {
                    throw new RuntimeException('Only GCash cash-in requests can be approved.');
                }
                wallet_credit((int) $locked['user_id'], (float) $locked['amount'], 'Verified ' . $locked['provider'] . ' cash-in #' . $cashInId, null, 'cash_in');
                q("UPDATE cash_in_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", [$me['id'], $cashInId]);
                audit_log($me['id'], 'approve', 'cash_in', $cashInId, ['amount' => $locked['amount'], 'provider' => $locked['provider']]);
            } else {
                q("UPDATE cash_in_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", [$me['id'], $cashInId]);
                audit_log($me['id'], 'reject', 'cash_in', $cashInId, ['amount' => $locked['amount'], 'provider' => $locked['provider']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('bad', 'Cash-in review failed. It may already have been reviewed; refresh the page and check its status.');
            redirect('payments.php');
        }

        if ($action === 'approve_cash_in') {
            notify((int) $locked['user_id'], 'wallet', 'Your GCash cash-in of ' . money((float) $locked['amount']) . ' was approved and added to your wallet.', 'wallet.php');
            flash('ok', 'GCash cash-in approved and wallet credited.');
        } else {
            notify((int) $locked['user_id'], 'wallet', 'Your cash-in request #' . $cashInId . ' was rejected. Please check the proof and contact support.', 'wallet.php');
            flash('info', 'Cash-in request rejected.');
        }
        redirect('payments.php');
    }

    $order = $orderId > 0 ? row('SELECT * FROM orders WHERE id = ?', [$orderId]) : null;
    if (!$order) {
        flash('bad', 'Order not found.');
        redirect('payments.php');
    }

    if ($action === 'approve_payment') {
        q("UPDATE payments SET status = 'verified', reviewed_by = ?, reviewed_at = NOW() WHERE order_id = ? AND status = 'pending'", [$me['id'], $orderId]);
        q("UPDATE orders SET status = 'in_progress' WHERE id = ? AND status = 'awaiting_payment'", [$orderId]);
        audit_log($me['id'], 'verify', 'payment', $orderId, ['buyer_id' => $order['buyer_id'], 'seller_id' => $order['seller_id']]);
        insert('INSERT INTO messages (order_id, sender_id, body, is_read) VALUES (?,?,?,0)',
            [$orderId, $me['id'], 'Payment verified by a moderator. Work can now begin — good luck!']);
        notify((int) $order['buyer_id'], 'payment', 'Your payment for order #' . $orderId . ' was verified — work can begin!', 'order.php?order=' . $orderId);
        notify((int) $order['seller_id'], 'order', 'Payment for order #' . $orderId . ' was verified — start working!', 'order.php?order=' . $orderId);
        flash('ok', 'Payment verified for order #' . $orderId . ' — order is now in progress.');
    } elseif ($action === 'reject_payment') {
        q("UPDATE payments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE order_id = ? AND status = 'pending'", [$me['id'], $orderId]);
        audit_log($me['id'], 'reject', 'payment', $orderId, ['buyer_id' => $order['buyer_id'], 'seller_id' => $order['seller_id']]);
        insert('INSERT INTO messages (order_id, sender_id, body, is_read) VALUES (?,?,?,0)',
            [$orderId, $me['id'], '⚠️ Payment proof was rejected by a moderator. Please upload a clear screenshot with the correct reference number before the order can start.']);
        notify((int) $order['buyer_id'], 'payment', 'Your payment proof for order #' . $orderId . ' was rejected — please re-upload.', 'order.php?order=' . $orderId);
        flash('info', 'Payment proof rejected — the buyer has been notified in-chat.');
    }
    redirect('payments.php');
}

$review = (int) ($_GET['review'] ?? 0);
$cashInReview = (int) ($_GET['cash_in'] ?? 0);
$reviewOrder = null;
$reviewPayments = [];
$reviewPending = false;

if ($review > 0) {
    $reviewOrder = row(
        'SELECT o.*, s.title AS service_title, s.category,
                b.full_name AS buyer_name, u.full_name AS seller_name
         FROM orders o
         JOIN services s ON s.id = o.service_id
         JOIN users b    ON b.id = o.buyer_id
         JOIN users u    ON u.id = o.seller_id
         WHERE o.id = ?',
        [$review]
    );
    $reviewPayments = rows('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$review]);
    $reviewPending  = !!array_filter($reviewPayments, fn ($p) => $p['status'] === 'pending');
}

$reviewCashIn = $cashInReview > 0 && admin_can($me, 'cash_ins')
    ? row('SELECT c.*, u.full_name, u.email FROM cash_in_requests c JOIN users u ON u.id = c.user_id WHERE c.id = ?', [$cashInReview])
    : null;

$pendingPayments = rows(
    'SELECT p.*, o.price, o.buyer_id, o.seller_id, u.full_name, s.title AS service_title
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     JOIN services s ON s.id = o.service_id
     JOIN users u  ON u.id = p.payer_id
     WHERE p.status = "pending"
     ORDER BY p.id ASC LIMIT 30'
);

$pendingCashIns = admin_can($me, 'cash_ins') ? rows(
    'SELECT c.*, u.full_name FROM cash_in_requests c
     JOIN users u ON u.id = c.user_id
     WHERE c.status = "pending"
     ORDER BY c.id ASC LIMIT 30'
) : [];

$recentPayments = rows(
    'SELECT p.*, o.price, u.full_name
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     JOIN users u  ON u.id = p.payer_id
     ORDER BY p.id DESC LIMIT 10'
);

$paymongoCheckouts = rows(
    'SELECT c.*, u.full_name
     FROM paymongo_checkouts c
     JOIN users u ON u.id = c.user_id
     ORDER BY c.id DESC LIMIT 30'
);
$pendingPaymongoCount = (int) scalar("SELECT COUNT(*) FROM paymongo_checkouts WHERE status = 'pending'");

$pageTitle = 'Verify payments';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Moderation</span>
    <h1>Payment verification</h1>
    <p class="muted">Monitor PayMongo QR Ph checkouts here. Successful payments are confirmed server-side and credited automatically; admins do not manually approve them.</p>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <div class="dash-grid" style="grid-template-columns:220px 1fr;align-items:start">
      <?php echo admin_subnav('payments'); ?>

      <div class="dash-main">
        <?php if ($review === 0 && $cashInReview === 0): ?>
          <div class="panel mb-2">
            <div class="panel-head"><h3>PayMongo QR Ph checkouts</h3><span class="ph-sub"><?= $pendingPaymongoCount ?> pending · latest 30</span></div>
            <?php if ($paymongoCheckouts !== []): ?>
              <div class="table-wrap" style="border:0;box-shadow:none">
                <table class="tbl">
                  <thead><tr><th>Checkout</th><th>Customer</th><th>Purpose</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
                  <tbody>
                    <?php foreach ($paymongoCheckouts as $checkout): ?>
                      <?php
                      $purposeLabel = match ((string) $checkout['purpose']) {
                          'order' => 'Order #' . (int) $checkout['order_id'],
                          'wallet' => 'Wallet top-up',
                          'pro' => 'Nerasky Pro',
                          default => 'Payment',
                      };
                      ?>
                      <tr>
                        <td><b>#<?= (int) $checkout['id'] ?></b></td>
                        <td><?= e($checkout['full_name']) ?></td>
                        <td>
                          <?php if ($checkout['purpose'] === 'order' && (int) $checkout['order_id'] > 0): ?>
                            <a href="<?= e(market_url('order.php?order=' . (int) $checkout['order_id'])) ?>" target="_blank" rel="noopener"><?= e($purposeLabel) ?> ↗</a>
                          <?php else: ?>
                            <?= e($purposeLabel) ?>
                          <?php endif; ?>
                        </td>
                        <td><?= money((int) $checkout['amount_centavos'] / 100) ?></td>
                        <td><?= sb((string) $checkout['status']) ?></td>
                        <td class="muted" style="font-size:var(--fs-xs)"><?= time_ago((string) $checkout['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="empty-state"><div class="es-ic"><?= ui_icon('card') ?></div><b>No PayMongo checkouts yet</b><p class="muted">New QR Ph transactions will appear here.</p></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($reviewOrder && $review > 0): ?>
          <?php $o = $reviewOrder; ?>
          <div class="stack">
            <div class="card card-pad">
              <div class="flex-between mb-2">
                <h2 style="font-size:1.2rem;margin:0">Order #<?= (int) $o['id'] ?> · <?= e($o['service_title']) ?></h2>
                <?= sb((string) $o['status']) ?>
              </div>
              <dl class="kv"><dt>Buyer</dt><dd><?= e($o['buyer_name']) ?></dd></dl>
              <dl class="kv"><dt>Seller</dt><dd><?= e($o['seller_name']) ?></dd></dl>
              <dl class="kv"><dt>Amount expected</dt><dd><?= money((float) $o['price']) ?></dd></dl>
              <dl class="kv" style="border-bottom:0"><dt>Order placed</dt><dd><?= time_ago((string) $o['created_at']) ?></dd></dl>
            </div>

            <?php foreach ($reviewPayments as $pay): ?>
              <div class="card card-pad">
                <div class="flex-between mb-1">
                  <h3 style="font-size:1rem;margin:0"><?= ui_icon('card') ?> Proof via <?= e($pay['provider']) ?></h3>
                  <?= sb((string) $pay['status']) ?>
                </div>
                <p class="muted" style="font-size:var(--fs-sm)">Reference: <b style="color:var(--ink-900)"><?= e($pay['reference_number']) ?></b></p>
                <a target="_blank" href="<?= e(admin_document_url((string) $pay['screenshot_file'])) ?>">
                  <img class="photo-thumb-lg" src="<?= e(admin_document_url((string) $pay['screenshot_file'])) ?>" alt="Payment proof" onerror="this.outerHTML='<div class=&quot;filebox&quot;><b>Can&apos;t preview</b><a class=&quot;btn btn-sm btn-primary&quot; href=&quot;<?= e(admin_document_url((string) $pay['screenshot_file'])) ?>&quot; target=&quot;_blank&quot;>Open file ↗</a></div>'">
                </a>

                <?php if ($reviewPending && !array_filter($reviewPayments, fn ($p) => $p['status'] === 'verified')): ?>
                  <div class="admin-verdict" style="border-top:1px solid var(--line);padding-top:.9rem;margin-top:.9rem">
                    <form method="post" action="<?= e(base_url('payments.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="approve_payment">
                      <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                      <button class="btn btn-primary" type="submit">✓ Verify &amp; start order</button>
                    </form>
                    <form method="post" action="<?= e(base_url('payments.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reject_payment">
                      <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                      <button class="btn btn-danger" type="submit" data-confirm="Reject this payment proof and notify the buyer?">✕ Reject</button>
                    </form>
                    <a class="btn btn-ghost" href="<?= e(market_url('order.php?order=' . (int) $o['id'])) ?>" target="_blank">Open order ↗</a>
                  </div>
                <?php elseif ($pay['status'] === 'verified'): ?>
                  <p class="muted" style="font-size:var(--fs-sm);margin-top:.8rem">Reviewed by admin on <?= date('M j, g:i A', strtotime((string) $pay['reviewed_at'])) ?>.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif ($reviewCashIn): ?>
          <div class="stack">
            <div class="card card-pad">
              <div class="flex-between mb-2"><h2 style="font-size:1.2rem;margin:0">Cash-in #<?= (int) $reviewCashIn['id'] ?></h2><?= sb((string) $reviewCashIn['status']) ?></div>
              <dl class="kv"><dt>User</dt><dd><?= e($reviewCashIn['full_name']) ?></dd></dl>
              <dl class="kv"><dt>Amount</dt><dd><?= money((float) $reviewCashIn['amount']) ?></dd></dl>
              <dl class="kv"><dt>Provider</dt><dd><?= e($reviewCashIn['provider']) ?></dd></dl>
              <dl class="kv" style="border-bottom:0"><dt>Reference</dt><dd><?= e($reviewCashIn['reference_number']) ?></dd></dl>
              <div class="banner banner-warn"><span class="b-ic">!</span><span>Before approving, find this exact GCash reference in Nerasky's actual GCash transaction history and confirm the received amount matches. A screenshot alone is not proof of payment.</span></div>
              <a target="_blank" href="<?= e(admin_document_url((string) $reviewCashIn['screenshot_file'])) ?>"><img class="photo-thumb-lg" src="<?= e(admin_document_url((string) $reviewCashIn['screenshot_file'])) ?>" alt="Cash-in proof"></a>
              <div class="admin-verdict" style="border-top:1px solid var(--line);padding-top:.9rem;margin-top:.9rem">
                <?php if ($reviewCashIn['provider'] === 'GCash'): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve_cash_in"><input type="hidden" name="cash_in_id" value="<?= (int) $reviewCashIn['id'] ?>"><button class="btn btn-primary" type="submit">✓ Approve &amp; credit wallet</button></form>
                <?php else: ?>
                  <span class="muted">Only GCash cash-ins can be approved in the current payment flow.</span>
                <?php endif; ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject_cash_in"><input type="hidden" name="cash_in_id" value="<?= (int) $reviewCashIn['id'] ?>"><button class="btn btn-danger" type="submit" data-confirm="Reject this cash-in proof?">✕ Reject</button></form>
              </div>
            </div>
          </div>
        <?php else: ?>
          <?php if (count($pendingCashIns) > 0): ?>
            <div class="panel mb-2">
              <div class="panel-head"><h3>Cash-in requests</h3><span class="ph-sub"><?= count($pendingCashIns) ?> in queue</span></div>
              <div class="table-wrap" style="border:0;box-shadow:none"><table class="tbl"><thead><tr><th>User</th><th>Provider</th><th>Reference</th><th>Amount</th><th></th></tr></thead><tbody>
                <?php foreach ($pendingCashIns as $c): ?><tr><td class="muted"><?= e($c['full_name']) ?></td><td><?= e($c['provider']) ?></td><td class="muted"><?= e($c['reference_number']) ?></td><td><b><?= money((float) $c['amount']) ?></b></td><td><a class="btn btn-primary btn-sm" href="<?= e(base_url('payments.php?cash_in=' . (int) $c['id'])) ?>">Review →</a></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </div>
          <?php endif; ?>
          <?php if (count($pendingPayments) > 0): ?>
            <div class="panel">
              <div class="panel-head"><h3>Awaiting verification</h3><span class="ph-sub"><?= count($pendingPayments) ?> in queue</span></div>
              <div class="table-wrap" style="border:0;box-shadow:none">
                <table class="tbl">
                  <thead><tr><th>Order</th><th>Service</th><th>Payer</th><th>Provider</th><th>Ref number</th><th>Expected</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($pendingPayments as $p): ?>
                      <tr>
                        <td><div class="row-title"><b>#<?= (int) $p['order_id'] ?></b></div></td>
                        <td><div class="row-title"><b><?= e(mb_strimwidth($p['service_title'], 0, 34, '…')) ?></b></div></td>
                        <td><div class="row-title" style="display:flex;align-items:center;gap:.5rem"><?= avatar_of($p, 28) ?><span class="muted"><?= e($p['full_name']) ?></span></div></td>
                        <td><?= e($p['provider']) ?></td>
                        <td class="muted" style="font-size:var(--fs-xs)"><?= e($p['reference_number']) ?></td>
                        <td><b><?= money((float) $p['price']) ?></b></td>
                        <td><a class="btn btn-primary btn-sm" href="<?= e(base_url('payments.php?review=' . (int) $p['order_id'])) ?>">Review →</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php else: ?>
            <div class="card empty-state"><div class="es-ic"><?= ui_icon('check') ?></div><b>No pending proofs</b><p class="muted">All payment verifications are up to date.</p></div>
          <?php endif; ?>

          <div class="panel">
            <div class="panel-head"><h3>Recently reviewed</h3></div>
            <div class="table-wrap" style="border:0;box-shadow:none">
              <table class="tbl">
                <thead><tr><th>Order</th><th>Payer</th><th>Provider</th><th>Status</th><th>When</th></tr></thead>
                <tbody>
                  <?php foreach ($recentPayments as $p): ?>
                    <tr>
                      <td><div class="row-title"><b><a href="<?= e(market_url('order.php?order=' . (int) $p['order_id'])) ?>" target="_blank">#<?= (int) $p['order_id'] ?></a></b></div></td>
                      <td class="muted"><?= e($p['full_name']) ?></td>
                      <td><?= e($p['provider']) ?></td>
                      <td><?= sb((string) $p['status']) ?></td>
                      <td class="muted" style="font-size:var(--fs-xs)"><?= time_ago((string) $p['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>