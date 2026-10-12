<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$me  = require_user();
$uid = (int) $me['id'];

$earnings = rows(
    "SELECT o.*, s.title AS service_title, b.full_name AS buyer_name
     FROM orders o
     JOIN services s ON s.id = o.service_id
     JOIN users b    ON b.id = o.buyer_id
     WHERE o.seller_id = ? AND o.status = 'completed'
     ORDER BY o.completed_at DESC LIMIT 20",
    [$uid]
);

$withdrawals = rows('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$uid]);
$cashIns = rows('SELECT * FROM cash_in_requests WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$uid]);
$transactions = rows('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$uid]);

$totalEarned = (float) scalar(
    "SELECT COALESCE(SUM(seller_payout),0) FROM orders WHERE seller_id = ? AND status = 'completed'",
    [$uid]
);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'cash_in') {
        $amount = clean_price((string) ($_POST['cash_in_amount'] ?? ''));
        $reference = trim((string) ($_POST['cash_in_reference'] ?? ''));
        if (!ENABLE_EXTERNAL_PAYMENTS) {
            $errors[] = 'Manual GCash cash-in is not configured.';
        } elseif ($amount < 100 || $amount > 5000) {
            $errors[] = 'Choose a GCash cash-in amount between ₱100 and ₱5,000.';
        } elseif (!throttle_allows('cash-in:' . $uid, 5, 3600)) {
            $errors[] = 'Too many GCash cash-in requests. Please try again later.';
        } elseif (mb_strlen($reference) < 5 || mb_strlen($reference) > 60) {
            $errors[] = 'Enter a valid GCash transaction reference.';
        } elseif (row('SELECT id FROM cash_in_requests WHERE provider = ? AND reference_number = ?', ['GCash', $reference])) {
            $errors[] = 'That GCash transaction reference has already been submitted.';
        } else {
            $proof = upload_file($_FILES['cash_in_screenshot'] ?? [], 'cash-in-proofs', ['jpg', 'jpeg', 'png', 'webp']);
            if (!$proof[0]) {
                $errors[] = 'Payment screenshot: ' . $proof[1];
            } else {
                try {
                    $cashInId = insert(
                        'INSERT INTO cash_in_requests (user_id, amount, provider, reference_number, screenshot_file, status) VALUES (?,?,?,?,?,?)',
                        [$uid, $amount, 'GCash', $reference, $proof[1], 'pending']
                    );
                    notify_admins('cash_in', $me['full_name'] . ' submitted a GCash cash-in request for ' . money($amount) . '.', 'payments.php?cash_in=' . $cashInId);
                    flash('ok', 'GCash cash-in request submitted. Your wallet will be credited after an admin verifies the actual transaction.');
                    redirect('wallet.php');
                } catch (Throwable $e) {
                    @unlink(APP_ROOT . '/' . $proof[1]);
                    error_log('GCash cash-in submission failed: ' . $e->getMessage());
                    $errors[] = 'Could not submit your GCash cash-in. Please try again or contact support.';
                }
            }
        }
    }

    if ($action !== 'withdraw') {
        $amount = 0;
        $method = '';
        $acc = '';
        $num = '';
    } else {
        $amount = clean_price((string) ($_POST['amount'] ?? ''));
        $method = (string) ($_POST['method'] ?? '');
        $acc    = trim((string) ($_POST['account_name'] ?? ''));
        $num    = trim((string) ($_POST['account_number'] ?? ''));

        if (!in_array($method, PAYMENT_METHODS, true)) {
            $errors[] = 'Choose a payout method.';
        }
        if ($amount < 100) {
            $errors[] = 'Minimum withdrawal is ₱100.';
        }
        if ($amount > (float) $me['wallet']) {
            $errors[] = 'Amount exceeds your wallet balance of ' . money((float) $me['wallet']) . '.';
        }
        if ($acc === '' || $num === '') {
            $errors[] = 'Enter the account name and number for the payout.';
        }

        if ($errors === [] && wallet_debit($uid, $amount, 'Withdrawal reserved for ' . $method, null, 'withdrawal_reserve')) {
            insert(
                'INSERT INTO withdrawals (user_id, amount, method, account_name, account_number, status)
                 VALUES (?,?,?,?,?,?)',
                [$uid, $amount, $method, $acc, $num, 'pending']
            );
            flash('ok', 'Withdrawal requested! The amount is reserved while an admin processes it.');
            redirect('wallet.php');
        } elseif ($errors === []) {
            $errors[] = 'Your wallet balance changed before this request could be reserved. Please try again.';
        }
    }
}

$me = get_user();
$transactions = rows('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$uid]);
$pageTitle  = 'Wallet';
$currentNav = 'wallet';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Your money</span>
    <h1>Wallet</h1>
    <p class="muted">Track your available balance, completed-order earnings, PayMongo and GCash top-ups, and withdrawals.</p>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <div class="order-summary">
      <div class="stack">
        <div class="dark-band balance-band reveal" style="border-radius:var(--r-lg);padding:2rem 2.2rem;background:var(--dark)">
          <div class="flex-between" style="gap:1rem;flex-wrap:wrap">
            <div>
              <span class="muted" style="text-transform:uppercase;letter-spacing:.08em;font-size:var(--fs-xs);font-weight:700">Available balance</span>
              <div style="font-size:2.5rem;font-weight:800;color:#fff;letter-spacing:-.02em;margin-top:.2rem"><?= money((float) $me['wallet']) ?></div>
              <div class="flex gap-2" style="margin-top:.5rem"><span class="pill pill-ok"><span class="dot"></span>Ready to withdraw</span></div>
            </div>
            <div style="text-align:right">
              <div class="muted" style="font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.08em;font-weight:700">Lifetime earned</div>
              <div style="font-size:1.4rem;font-weight:800;color:#fff"><?= money($totalEarned) ?></div>
              <div class="muted" style="font-size:var(--fs-xs);margin-top:.3rem"><?= count($earnings) ?> completed order<?= count($earnings) === 1 ? '' : 's' ?></div>
            </div>
          </div>
        </div>

        <?php foreach ($errors as $err): ?>
          <div class="banner banner-bad"><span class="b-ic">✕</span><span><?= e($err) ?></span></div>
        <?php endforeach; ?>

        <div class="panel">
          <div class="panel-head"><h3>Earnings history</h3></div>
          <?php if (count($earnings) > 0): ?>
            <div class="table-wrap" style="border:0;box-shadow:none">
              <table class="tbl wallet-earnings-table">
                <thead><tr><th>Order</th><th>Service</th><th>Buyer</th><th>Completed</th><th>Payout</th></tr></thead>
                <tbody>
                  <?php foreach ($earnings as $e): ?>
                    <tr>
                      <td data-label="Order"><div class="row-title"><b><a href="<?= e(base_url('order.php?order=' . (int) $e['id'])) ?>">#<?= (int) $e['id'] ?></a></b></div></td>
                      <td data-label="Service"><div class="row-title"><b><?= e(mb_strimwidth($e['service_title'], 0, 40, '…')) ?></b></div></td>
                      <td data-label="Buyer" class="muted"><?= e($e['buyer_name']) ?></td>
                      <td data-label="Completed" class="muted" style="font-size:var(--fs-xs)"><?= $e['completed_at'] ? date('M j, Y', strtotime((string) $e['completed_at'])) : '—' ?></td>
                      <td data-label="Payout"><b style="color:var(--green-600)">+<?= money((float) $e['seller_payout']) ?></b></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state"><div class="es-ic"><?= ui_icon('cash') ?></div><b>No earnings yet</b><p class="muted">Complete an order as a seller and your 95% payout shows up here.</p></div>
          <?php endif; ?>
        </div>
<!-- withdrawal history -->
        <div class="panel">
          <div class="panel-head"><h3>Withdrawal requests</h3></div>
          <?php if (count($withdrawals) > 0): ?>
            <?php foreach ($withdrawals as $w): ?>
              <div class="order-row">
                <span class="st-ic" style="width:40px;height:40px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center"><?= ui_icon('send') ?></span>
                <div class="or-main">
                  <b><?= money((float) $w['amount']) ?> → <?= e($w['method']) ?></b>
                  <small><?= e($w['account_name']) ?> · <?= e($w['account_number']) ?> · <?= time_ago((string) $w['created_at']) ?></small>
                </div>
                <?= sb((string) $w['status']) ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state"><div class="es-ic"><?= ui_icon('message') ?></div><b>No withdrawals yet</b><p class="muted">Use the form to request a payout to your selected account.</p></div>
          <?php endif; ?>
        </div>

        <div class="panel">
          <div class="panel-head"><h3>Wallet activity</h3></div>
          <?php if ($transactions !== []): ?>
            <?php foreach ($transactions as $tx): ?>
              <div class="order-row">
                <span class="st-ic" style="width:40px;height:40px;border-radius:12px;background:<?= $tx['direction'] === 'credit' ? 'rgb(16 185 129/.12)' : 'rgb(239 68 68/.1)' ?>;color:<?= $tx['direction'] === 'credit' ? 'var(--green-600)' : 'var(--red-600)' ?>;display:flex;align-items:center;justify-content:center">
                  <?= $tx['direction'] === 'credit' ? '↑' : '↓' ?>
                </span>
                <div class="or-main">
                  <b><?= e($tx['description']) ?></b>
                  <small><?= time_ago((string) $tx['created_at']) ?><?= $tx['order_id'] ? ' · Order #' . (int) $tx['order_id'] : '' ?></small>
                </div>
                <b style="color:<?= $tx['direction'] === 'credit' ? 'var(--green-600)' : 'var(--red-600)' ?>"><?= $tx['direction'] === 'credit' ? '+' : '-' ?><?= money((float) $tx['amount']) ?></b>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state"><div class="es-ic"><?= ui_icon('wallet') ?></div><b>No wallet activity yet</b><p class="muted">Complete a sale or make a verified payment to see wallet activity here.</p></div>
          <?php endif; ?>
        </div>
      </div>

      <aside class="stack">
        <div class="card card-pad">
          <h3 style="font-size:1.05rem;margin-bottom:.3rem"><?= ui_icon('cash') ?> Add wallet balance with QR Ph</h3>
          <?php
          $checkoutPurpose = 'wallet';
          $checkoutInputAmount = true;
          require __DIR__ . '/includes/paymongo_checkout_ui.php';
          ?>
        </div>

        <?php if (ENABLE_EXTERNAL_PAYMENTS): ?>
          <div class="card card-pad">
            <h3 style="font-size:1.05rem;margin-bottom:.3rem"><?= ui_icon('cash') ?> Or add wallet balance with GCash</h3>
            <?php
            $gcashAccount = payment_account_details('GCash');
            $gcashInstructions = 'Scan the InstaPay QR with GCash or another supported QR Ph app, or send to the GCash number. Enter the same amount and reference below. The wallet is credited only after an admin verifies the actual transaction.';
            require __DIR__ . '/includes/gcash_payment_card.php';
            ?>
            <form method="post" enctype="multipart/form-data" action="<?= e(base_url('wallet.php')) ?>" class="mt-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cash_in">
              <div class="field">
                <label for="cash_in_amount">Amount</label>
                <div class="price-wrap"><span class="peso">₱</span><input class="input" type="number" step="0.01" min="100" max="5000" id="cash_in_amount" name="cash_in_amount" required placeholder="500"></div>
                <small class="hint">₱100–₱5,000 per cash-in.</small>
              </div>
              <div class="field"><label for="cash_in_reference">GCash transaction reference</label><input class="input" id="cash_in_reference" name="cash_in_reference" required maxlength="60" placeholder="Reference number from GCash"></div>
              <div class="field"><label for="cash_in_screenshot">GCash transaction screenshot</label><input class="input" type="file" id="cash_in_screenshot" name="cash_in_screenshot" accept=".jpg,.jpeg,.png,.webp" required><small class="hint">JPG, PNG, or WEBP · up to 6 MB. Include the amount, date, and reference.</small></div>
              <button class="btn btn-outline btn-block" type="submit">Submit GCash cash-in for admin review</button>
            </form>
          </div>
        <?php endif; ?>

        <div class="panel">
          <div class="panel-head"><h3>Cash-in requests</h3></div>
          <?php if ($cashIns !== []): ?>
            <?php foreach ($cashIns as $cashIn): ?>
              <div class="order-row">
                <span class="st-ic" style="width:40px;height:40px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center"><?= ui_icon('cash') ?></span>
                <div class="or-main"><b>GCash cash-in #<?= (int) $cashIn['id'] ?> · <?= money((float) $cashIn['amount']) ?></b><small>Ref <?= e($cashIn['reference_number']) ?> · <?= time_ago((string) $cashIn['created_at']) ?></small></div>
                <?= sb((string) $cashIn['status']) ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state"><div class="es-ic"><?= ui_icon('cash') ?></div><b>No GCash cash-ins yet</b><p class="muted">Submitted manual payment requests and their review status appear here.</p></div>
          <?php endif; ?>
        </div>

        <div class="card card-pad">
          <h3 style="font-size:1.05rem;margin-bottom:.3rem"><?= ui_icon('cash') ?> Request a withdrawal</h3>
          <p class="muted" style="font-size:var(--fs-sm)">Min ₱100 · an admin processes it and marks it paid.</p>
          <form method="post" action="<?= e(base_url('wallet.php')) ?>" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="withdraw">
            <div class="field">
              <label for="amount">Amount</label>
              <div class="price-wrap">
                <span class="peso">₱</span>
                <input type="number" step="0.01" min="100" max="<?= e((float) $me['wallet']) ?>" id="amount" name="amount" required placeholder="500">
              </div>
            </div>
            <div class="field">
              <label for="method">Payout method</label>
              <select class="input" id="method" name="method" required>
                <option value="">Choose…</option>
                <?php foreach (PAYMENT_METHODS as $pm): ?><option value="<?= e($pm) ?>"><?= e($pm) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="grid-2 gap-2">
              <div class="field">
                <label for="account_name">Account name</label>
                <input class="input" id="account_name" name="account_name" required placeholder="e.g. Janna Cruz">
              </div>
              <div class="field">
                <label for="account_number">Account number</label>
                <input class="input" id="account_number" name="account_number" required placeholder="0917 555 1420">
              </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit" <?= (float) $me['wallet'] < 100 ? 'disabled' : '' ?>>Request withdrawal</button>
            <?php if ((float) $me['wallet'] < 100): ?><p class="hint center mt-1">You need at least ₱100 to withdraw (you have <?= money((float) $me['wallet']) ?>).</p><?php endif; ?>
          </form>
        </div>

        <div class="card card-pad" style="background:rgb(99 102 241/.04)">
          <h3 style="font-size:.95rem"><?= ui_icon('wallet') ?> How payouts work</h3>
          <p class="muted" style="font-size:var(--fs-sm);margin:0">When a buyer accepts your deliverable, <b><?= is_premium($me) ? '97% on your first ' . PREMIUM_DAILY_DISCOUNTED_ORDERS . ' orders each day' : '95%' ?></b> is released to this wallet instantly. After the Pro daily allowance, the regular 95% payout applies. Request a withdrawal, and an admin verifies the account details before sending funds to your selected payout account. <?= !is_premium($me) ? '<a href="' . e(base_url('premium.php')) . '" style="font-weight:700">Go Pro for a daily 3% allowance →</a>' : '' ?></p>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>