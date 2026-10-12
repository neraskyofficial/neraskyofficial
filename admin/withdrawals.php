<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
require_admin_area($me, 'withdrawals');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $wid    = (int) ($_POST['withdrawal_id'] ?? 0);

    $w = $wid > 0 ? row('SELECT * FROM withdrawals WHERE id = ?', [$wid]) : null;
    if (!$w) {
        flash('bad', 'Withdrawal not found.');
        redirect('withdrawals.php');
    }

    if ($action === 'pay') {
        q("UPDATE withdrawals SET status = 'paid' WHERE id = ?", [$wid]);
        audit_log($me['id'], 'mark_paid', 'withdrawal', $wid, ['amount' => $w['amount'], 'method' => $w['method'], 'user_id' => $w['user_id']]);
        notify((int) $w['user_id'], 'withdrawal', 'Your withdrawal of ' . money((float) $w['amount']) . ' to ' . $w['method'] . ' was paid out.', 'wallet.php');
        flash('ok', 'Withdrawal of ' . money((float) $w['amount']) . ' marked as paid to ' . $w['method'] . '.');
    } elseif ($action === 'reject') {
        q("UPDATE withdrawals SET status = 'rejected' WHERE id = ?", [$wid]);
        audit_log($me['id'], 'reject', 'withdrawal', $wid, ['amount' => $w['amount'], 'method' => $w['method'], 'user_id' => $w['user_id']]);
        wallet_credit((int) $w['user_id'], (float) $w['amount'], 'Refund for rejected withdrawal #' . $wid, null, 'withdrawal_refund');
        notify((int) $w['user_id'], 'withdrawal', 'Your withdrawal request of ' . money((float) $w['amount']) . ' was declined. The reserved funds were returned.', 'wallet.php');
        flash('info', 'Withdrawal rejected — funds remain in the seller\'s wallet.');
    }
    redirect('withdrawals.php');
}

$pending = rows(
    'SELECT w.*, u.full_name, u.email, u.wallet, u.profile_frame, u.profile_accent
     FROM withdrawals w JOIN users u ON u.id = w.user_id
     WHERE w.status = "pending"
     ORDER BY w.id ASC LIMIT 40'
);

$history = rows(
    'SELECT w.*, u.full_name FROM withdrawals w JOIN users u ON u.id = w.user_id
     WHERE w.status <> "pending" ORDER BY w.id DESC LIMIT 10'
);
$pageTitle = 'Pay withdrawals';
require __DIR__ . '/header.php';
?>
<section class="page-head">

<section class="page-head">
  <div class="container">
    <span class="eyebrow">Moderation</span>
    <h1>Withdrawal payouts</h1>
    <p class="muted">Seller requests to move their wallet balance to GCash — verify, pay, and record the transfer reference.</p>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <div class="dash-grid" style="grid-template-columns:220px 1fr;align-items:start">
      <?php echo admin_subnav('withdrawals'); ?>

      <div class="dash-main">
        <?php if (count($pending) > 0): ?>
          <div class="panel">
            <div class="panel-head"><h3>Pending payouts</h3><span class="ph-sub"><?= count($pending) ?> requests</span></div>
            <?php foreach ($pending as $w): ?>
              <div class="order-row">
                <span class="st-ic" style="width:42px;height:42px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;font-size:1.05rem"><?= ui_icon('send') ?></span>
                <div class="or-main" style="flex:2">
                  <b style="display:flex;align-items:center;gap:.55rem"><?= avatar_of($w, 34) ?><?= money((float) $w['amount']) ?> → <?= e($w['method']) ?></b>
                  <small><?= e($w['full_name']) ?> · <?= e($w['account_name']) ?> · <?= e($w['account_number']) ?> · requested <?= time_ago((string) $w['created_at']) ?></small>
                </div>
                <div class="muted" style="font-size:var(--fs-xs)">Wallet: <b><?= money((float) $w['wallet']) ?></b></div>
                <div class="quick-actions">
                  <form method="post" action="<?= e(base_url('withdrawals.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="withdrawal_id" value="<?= (int) $w['id'] ?>">
                    <button class="btn btn-primary btn-sm" type="submit" data-confirm="Mark this withdrawal as paid to <?= e($w['method']) ?>?">✓ Pay out</button>
                  </form>
                  <form method="post" action="<?= e(base_url('withdrawals.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="withdrawal_id" value="<?= (int) $w['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">✕ Reject</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="card empty-state"><div class="es-ic"><?= ui_icon('check') ?></div><b>No pending payouts</b><p class="muted">Every withdrawal request has been processed.</p></div>
        <?php endif; ?>

        <div class="panel">
          <div class="panel-head"><h3>Recent payouts</h3></div>
          <div class="table-wrap" style="border:0;box-shadow:none">
            <table class="tbl">
              <thead><tr><th>Student</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th></tr></thead>
              <tbody>
                <?php foreach ($history as $w): ?>
                  <tr>
                    <td><div class="row-title"><b><?= e($w['full_name']) ?></b></div></td>
                    <td><b><?= money((float) $w['amount']) ?></b></td>
                    <td><?= e($w['method']) ?></td>
                    <td><?= sb((string) $w['status']) ?></td>
                    <td class="muted" style="font-size:var(--fs-xs)"><?= time_ago((string) $w['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>